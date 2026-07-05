<?php
// Endpoint API - Mot de passe oublié
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError("Méthode non autorisée. POST requis.", 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';

if (empty($email)) {
    sendJSONError("Veuillez saisir votre adresse e-mail.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJSONError("Format de l'adresse e-mail invalide.");
}

try {
    $db = getDBConnection();
    
    $stmt = $db->prepare("SELECT id, nom, prenom FROM utilisateurs WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendJSONSuccess("Si cette adresse existe, un e-mail de réinitialisation vous a été envoyé.");
        exit;
    }
    
    $resetToken = bin2hex(random_bytes(32));
    
    $stmtUpdate = $db->prepare("UPDATE utilisateurs SET token_activation = :token WHERE id = :id");
    $stmtUpdate->execute([
        ':token' => $resetToken,
        ':id'    => $user['id']
    ]);
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $resetLink = $protocol . "://" . $host . "/CTPHP/#/reset-password?token=" . $resetToken;
    
    // Remplacement correct pour correspondre au gabarit d'e-mail
    $mailSent = sendTemplateMail($email, "Réinitialisation de votre mot de passe - SocialConnect", "email_reset.html", [
        "{{reset_link}}" => $resetLink
    ]);
    
    if ($mailSent) {
        sendJSONSuccess("Un e-mail de réinitialisation vous a été envoyé.");
    } else {
        sendJSONSuccess("Demande enregistrée (environnement local sans courriel).", [
            "dev_mode_token" => $resetToken
        ]);
    }
} catch (PDOException $e) {
    error_log("Erreur Forgot Password SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors du traitement de votre demande.", 500);
}