<?php
// Endpoint API - Envoyer un message privé à un ami (Persistant)
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// 1. Sécurité : Vérifier que l'utilisateur est connecté
$currentUser = requireLogin();

// Sécurité : Autoriser uniquement la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError("Méthode non autorisée. POST requis.", 405);
}

// Lecture et décodage du corps JSON envoyé par la SPA
$input      = json_decode(file_get_contents('php://input'), true);
$receiverId = isset($input['receiver_id']) ? intval($input['receiver_id']) : null;
$content    = isset($input['content']) ? trim($input['content']) : '';

// 2. Validation immédiate des champs obligatoires
if (!$receiverId || $receiverId <= 0 || $receiverId === intval($currentUser['id'])) {
    sendJSONError("Destinataire invalide.", 400);
}

if (empty($content)) {
    sendJSONError("Impossible d'envoyer un message vide.", 400);
}

try {
    $db = getDBConnection();
    $currentUserId = intval($currentUser['id']);

    // Sécurité supplémentaire optionnelle : Vérifier si le destinataire existe en BDD
    $stmtCheckUser = $db->prepare("SELECT id FROM utilisateurs WHERE id = :id LIMIT 1");
    $stmtCheckUser->execute([':id' => $receiverId]);
    if (!$stmtCheckUser->fetch()) {
        sendJSONError("Le destinataire n'existe pas.", 404);
    }

    // 3. Protection Anti-XSS : Neutralisation du texte
    $cleanContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

    // 4. Insertion du message en base de données (Requête préparée)
    $sqlInsert = "INSERT INTO messages (id_expediteur, id_destinataire, contenu, date_message) 
                  VALUES (:id_expediteur, :id_destinataire, :contenu, NOW())";
                  
    $stmtInsert = $db->prepare($sqlInsert);
    $stmtInsert->execute([
        ':id_expediteur'   => $currentUserId,
        ':id_destinataire' => $receiverId,
        ':contenu'         => $cleanContent
    ]);
    
    // Récupération de l'ID du message inséré
    $messageId = $db->lastInsertId();

    // 5. Envoi du message formaté à la SPA pour une mise à jour instantanée du DOM
    sendJSONSuccess("Message envoyé avec succès.", [
        "message" => [
            "id"              => intval($messageId),
            "id_expediteur"   => $currentUserId,
            "id_destinataire" => $receiverId,
            "content"         => $content, // On renvoie le texte brut pour le JS (qui le gère de façon sécurisée)
            "date"            => date("Y-m-d H:i:s"),
            "is_me"           => true // C'est l'expéditeur qui appelle ce script, donc c'est forcément "lui"
        ]
    ], 201); // 201 Created

} catch (PDOException $e) {
    error_log("Erreur Chat Send SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors de l'envoi de votre message.", 500);
}