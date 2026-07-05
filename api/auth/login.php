<?php
// Endpoint API - Connexion Utilisateur
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// Sécurité : Méthode POST exclusivement requise
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError("Méthode non autorisée. POST requis.", 405);
}

// Récupération des données JSON envoyées par la SPA
$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';

// Validation des champs requis
if (empty($email) || empty($password)) {
    sendJSONError("Veuillez remplir tous les champs (Email et Mot de passe).", 400);
}

try {
    // 1. Connexion à la base de données via notre helper
    $db = getDBConnection();
    
    // 2. Recherche de l'utilisateur par e-mail (Requête préparée contre les injections SQL)
    $stmt = $db->prepare("SELECT id, nom, prenom, email, password, role, avatar, statut FROM utilisateurs WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    
    // 3. Vérification de l'existence de l'utilisateur et du mot de passe haché
    // Sécurité : On utilise un message d'erreur générique pour ne pas donner d'indices à un attaquant
    if (!$user || !password_verify($password, $user['password'])) {
        sendJSONError("Identifiants ou mot de passe incorrects.", 401);
    }

    // Sécurité : Empêcher la connexion si le compte est bloqué
    if (password_verify($password, $user['password'])) {
    
        // VÉRIFICATION DU BLOCAGE
        if (isset($user['statut']) && $user['statut'] === 'bloque') {
            sendJSONError("Votre compte a été suspendu par un administrateur. Accès refusé.", 403);
        }
        
        // 4. Facultatif mais recommandé : Vérification si le compte a validé son inscription par mail
        if (isset($user['statut']) && $user['statut'] === 'en_attente') {
            sendJSONError("Votre compte n'est pas encore activé. Veuillez vérifier vos e-mails pour confirmer votre inscription.", 403);
        }
        
        // 5. Initialisation de la session native PHP pour générer le token (PHPSESSID)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Enregistrement des données utilisateur dans la session globale
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['nom']     = $user['nom'];
        $_SESSION['prenom']  = $user['prenom'];
        $_SESSION['email']   = $user['email'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['avatar']  = $user['avatar'] ?? 'default-avatar.png';
        
        // 6. Envoi de la réponse de succès avec le jeton de session pour le sessionStorage
        sendJSONSuccess("Connexion réussie.", [
            "token" => session_id(),
            "user" => [
                "id"     => $_SESSION['user_id'],
                "nom"    => $_SESSION['nom'],
                "prenom" => $_SESSION['prenom'],
                "email"  => $_SESSION['email'],
                "role"   => $_SESSION['role'],
                "avatar" => $_SESSION['avatar']
            ]
        ]);
    }

} catch (PDOException $e) {
    // En cas de problème de requête SQL, on journalise et on renvoie une erreur propre
    error_log("Erreur Login SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors de l'authentification.", 500);
}