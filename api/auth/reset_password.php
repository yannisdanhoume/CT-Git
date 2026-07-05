<?php
// Endpoint API - Réinitialisation effective du mot de passe
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// Sécurité : Autoriser uniquement la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError("Méthode non autorisée. POST requis.", 405);
}

// Lecture et décodage des données JSON envoyées par la SPA
$input = json_decode(file_get_contents('php://input'), true);

$token    = isset($input['token']) ? trim($input['token']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';

// 1. Validation de la présence des paramètres requis
if (empty($token) || empty($password)) {
    sendJSONError("Paramètres manquants. Jeton et mot de passe requis.", 400);
}

// 2. Validation de la force du nouveau mot de passe (Exemple : 6 caractères minimum)
if (strlen($password) < 6) {
    sendJSONError("Le nouveau mot de passe doit contenir au moins 6 caractères.", 400);
}

try {
    // Connexion à la base de données via notre helper
    $db = getDBConnection();
    
    // 3. Recherche de l'utilisateur possédant ce jeton actif (Requête préparée)
    // Note : Dans forgot_password.php, nous avons stocké le jeton de réinitialisation dans la colonne 'token_activation'
    $stmtUser = $db->prepare("SELECT id FROM utilisateurs WHERE token_activation = :token LIMIT 1");
    $stmtUser->execute([':token' => $token]);
    $user = $stmtUser->fetch();
    
    // Si aucun utilisateur ne correspond, le jeton est invalide ou a déjà été utilisé
    if (!$user) {
        sendJSONError("Le lien de réinitialisation est invalide ou a expiré.", 400);
    }
    
    // 4. Sécurisation : Hachage robuste du nouveau mot de passe
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // 5. Mise à jour du mot de passe et invalidation immédiate du jeton (Sécurité anti-réutilisation)
    // On repasse 'token_activation' à NULL et s'il y avait un statut 'en_attente', on en profite pour activer le compte.
    $sqlUpdate = "UPDATE utilisateurs 
                  SET password = :password, 
                      token_activation = NULL, 
                      statut = 'actif' 
                  WHERE id = :id";
                  
    $stmtUpdate = $db->prepare($sqlUpdate);
    $stmtUpdate->execute([
        ':password' => $hashedPassword,
        ':id'       => $user['id']
    ]);
    
    // 6. Envoi de la réponse JSON de succès
    sendJSONSuccess("Votre mot de passe a été modifié avec succès. Vous pouvez maintenant vous connecter.");

} catch (PDOException $e) {
    // Journalisation de l'erreur SQL interne et masquage technique pour le client
    error_log("Erreur Reset Password SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors de la réinitialisation de votre mot de passe.", 500);
}