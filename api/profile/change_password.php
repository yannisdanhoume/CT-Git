<?php
// Endpoint API - Modifier le mot de passe d'un utilisateur connecté
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// 1. Sécurité : Vérifier que l'utilisateur est connecté
$currentUser = requireLogin();

// Sécurité : Autoriser uniquement la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError("Méthode non autorisée. POST requis.", 405);
}

// Récupération des données JSON envoyées par la SPA
$input = json_decode(file_get_contents('php://input'), true);
$currentPassword = isset($input['current_password']) ? trim($input['current_password']) : '';
$newPassword     = isset($input['new_password']) ? trim($input['new_password']) : '';

// Validation de la présence des champs obligatoires
if (empty($currentPassword) || empty($newPassword)) {
    sendJSONError("Champs obligatoires manquants. Veuillez remplir tous les champs.", 400);
}

// Validation de la force du nouveau mot de passe (Exemple : 6 caractères minimum)
if (strlen($newPassword) < 6) {
    sendJSONError("Le nouveau mot de passe doit contenir au moins 6 caractères.", 400);
}

// Sécurité : Empêcher l'utilisateur de choisir le même mot de passe
if ($currentPassword === $newPassword) {
    sendJSONError("Le nouveau mot de passe doit être différent du mot de passe actuel.", 400);
}

try {
    // Connexion à la base de données
    $db = getDBConnection();
    
    // 2. Récupération du mot de passe haché stocké en base de données
    $stmt = $db->prepare("SELECT password FROM utilisateurs WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $currentUser['id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendJSONError("Utilisateur introuvable.", 404);
    }
    
    // 3. Vérification de la validité du mot de passe actuel saisi
    if (!password_verify($currentPassword, $user['password'])) {
        sendJSONError("Le mot de passe actuel est incorrect.", 401);
    }
    
    // 4. Sécurisation : Hachage robuste du nouveau mot de passe
    $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // 5. Mise à jour dans la base de données (Requête préparée)
    $stmtUpdate = $db->prepare("UPDATE utilisateurs SET password = :password WHERE id = :id");
    $stmtUpdate->execute([
        ':password' => $newHashedPassword,
        ':id'       => $currentUser['id']
    ]);
    
    // 6. Envoi de la réponse JSON de succès
    sendJSONSuccess("Mot de passe modifié avec succès.");

} catch (PDOException $e) {
    // Journalisation interne de l'erreur SQL
    error_log("Erreur Change Password SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors de la modification du mot de passe.", 500);
}