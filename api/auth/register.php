<?php
// Endpoint API - Inscription Utilisateur
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError("Méthode non autorisée. POST requis.", 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$nom      = isset($input['nom']) ? trim($input['nom']) : '';
$prenom   = isset($input['prenom']) ? trim($input['prenom']) : '';
$email    = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';

if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
    sendJSONError("Veuillez remplir tous les champs obligatoires.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJSONError("Format de l'adresse e-mail invalide.");
}

if (strlen($password) < 6) {
    sendJSONError("Le mot de passe doit contenir au moins 6 caractères.");
}

try {
    $db = getDBConnection();
    
    $stmtCheck = $db->prepare("SELECT id FROM utilisateurs WHERE email = :email LIMIT 1");
    $stmtCheck->execute([':email' => $email]);
    if ($stmtCheck->fetch()) {
        sendJSONError("Cette adresse e-mail est déjà associée à un compte.");
    }
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $activationToken = bin2hex(random_bytes(32));
    
    $sql = "INSERT INTO utilisateurs (nom, prenom, email, password, role, statut, token_activation, avatar) 
            VALUES (:nom, :prenom, :email, :password, 'client', 'en_attente', :token, 'default-avatar.png')";
            
    $stmtInsert = $db->prepare($sql);
    $stmtInsert->execute([
        ':nom'      => $nom,
        ':prenom'   => $prenom,
        ':email'    => $email,
        ':password' => $hashedPassword,
        ':token'    => $activationToken
    ]);
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $confirmationLink = $protocol . "://" . $host . "/CTPHP/#/confirm?token=" . $activationToken;
    
    // Chargement du template HTML et remplacement des marqueurs
    $mailSent = sendTemplateMail($email, "Confirmez votre inscription - SocialConnect", "email_confirm.html", [
        "{{prenom}}"             => htmlspecialchars($prenom),
        "{{confirmation_link}}" => $confirmationLink
    ]);
    
    if ($mailSent) {
        sendJSONSuccess("Inscription réussie ! Un e-mail de confirmation vous a été envoyé.", [
            "email" => $email
        ]);
    } else {
        sendJSONSuccess("Inscription enregistrée avec succès (environnement local sans courriel).", [
            "email" => $email,
            "dev_mode_token" => $activationToken
        ]);
    }
} catch (PDOException $e) {
    error_log("Erreur Register SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors de la création de votre compte.", 500);
}