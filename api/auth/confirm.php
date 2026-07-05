<?php
// Endpoint API - Confirmation d'inscription par email
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// Autoriser GET (lien cliqué dans l'email) ou POST (appel AJAX depuis la SPA)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = isset($_GET['token']) ? trim($_GET['token']) : '';
    $redirect = true;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = isset($input['token']) ? trim($input['token']) : '';
    $redirect = false;
} else {
    sendJSONError("Méthode non autorisée.", 405);
}

// Validation du token (64 caractères hexadécimaux)
if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
    if ($redirect) {
        header("Location: /CTPHP/#/confirm?error=token_invalide");
        exit;
    }
    sendJSONError("Le lien de confirmation est invalide ou a expiré.", 400);
}

try {
    $db = getDBConnection();

    // Recherche de l'utilisateur avec ce token et statut 'en_attente'
    $stmt = $db->prepare("SELECT id, email FROM utilisateurs WHERE token_activation = :token AND statut = 'en_attente' LIMIT 1");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        if ($redirect) {
            header("Location: /CTPHP/#/confirm?error=token_invalide");
            exit;
        }
        sendJSONError("Le lien de confirmation est invalide, a expiré ou le compte est déjà activé.", 400);
    }

    // Activation du compte : statut → 'actif', suppression du token
    $stmtUpdate = $db->prepare("UPDATE utilisateurs SET statut = 'actif', token_activation = NULL WHERE id = :id");
    $stmtUpdate->execute([':id' => $user['id']]);

    if ($redirect) {
        header("Location: /CTPHP/#/confirm?success=1");
        exit;
    }

    sendJSONSuccess("Votre compte a été activé avec succès ! Vous pouvez maintenant vous connecter.", [
        "email" => $user['email']
    ]);

} catch (PDOException $e) {
    error_log("Erreur Confirm SQL : " . $e->getMessage());
    if ($redirect) {
        header("Location: /CTPHP/#/confirm?error=technique");
        exit;
    }
    sendJSONError("Une erreur technique est survenue lors de l'activation de votre compte.", 500);
}
