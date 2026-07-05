<?php
// Endpoint API - Récupérer le fil de discussion avec un ami ou envoyer un message
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

$currentUser = requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $contactId = isset($_GET['contact_id']) ? intval($_GET['contact_id']) : null;

    if (!$contactId || $contactId <= 0 || $contactId === intval($currentUser['id'])) {
        sendJSONError("Identifiant de contact invalide.", 400);
    }

    try {
        $db = getDBConnection();
        $currentUserId = intval($currentUser['id']);
        $since = isset($_GET['since']) ? trim($_GET['since']) : null;

        $sql = "SELECT id, id_expediteur, id_destinataire, contenu, image, date_message 
                FROM messages 
                WHERE (id_expediteur = :user1 AND id_destinataire = :contact1)
                   OR (id_expediteur = :contact2 AND id_destinataire = :user2)";

        if ($since) {
            $sql .= " AND date_message > :since";
        }

        $sql .= " ORDER BY date_message ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user1'    => $currentUserId,
            ':contact1' => $contactId,
            ':contact2' => $contactId,
            ':user2'    => $currentUserId
        ]);

        $messagesRows = $stmt->fetchAll();
        $messagesList = [];

        foreach ($messagesRows as $row) {
            $messagesList[] = [
                "id"              => intval($row['id']),
                "id_expediteur"   => intval($row['id_expediteur']),
                "id_destinataire" => intval($row['id_destinataire']),
                "content"         => $row['contenu'] ? htmlspecialchars_decode($row['contenu']) : '',
                "image"           => $row['image'] ? $row['image'] : null,
                "date"            => $row['date_message'],
                "is_me"           => (intval($row['id_expediteur']) === $currentUserId)
            ];
        }

        sendJSONSuccess("Historique des messages récupéré.", ["messages" => $messagesList]);

    } catch (PDOException $e) {
        error_log("Erreur Chat Messages SQL : " . $e->getMessage());
        sendJSONError("Une erreur technique est survenue lors de la récupération des messages.", 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiverId = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : null;
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $hasFile = isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK;

    if (!$receiverId || $receiverId <= 0 || $receiverId === intval($currentUser['id'])) {
        sendJSONError("Destinataire invalide.", 400);
    }

    if (empty($content) && !$hasFile) {
        sendJSONError("Impossible d'envoyer un message vide.", 400);
    }

    try {
        $db = getDBConnection();
        $currentUserId = intval($currentUser['id']);

        $stmtCheckUser = $db->prepare("SELECT id FROM utilisateurs WHERE id = :id LIMIT 1");
        $stmtCheckUser->execute([':id' => $receiverId]);
        if (!$stmtCheckUser->fetch()) {
            sendJSONError("Le destinataire n'existe pas.", 404);
        }

        $storedImage = null;
        if ($hasFile) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowedTypes, true)) {
                sendJSONError("Le fichier envoyé n'est pas une image valide.", 400);
            }

            $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'chat_' . time() . '_' . uniqid() . '.' . strtolower($extension);
            $targetPath = __DIR__ . '/../../assets/images/' . $filename;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                sendJSONError("Le téléchargement de l'image a échoué.", 500);
            }

            $storedImage = $filename;
        }

        $cleanContent = $content !== '' ? htmlspecialchars($content, ENT_QUOTES, 'UTF-8') : null;

        $sqlInsert = "INSERT INTO messages (id_expediteur, id_destinataire, contenu, image, date_message)
                      VALUES (:id_expediteur, :id_destinataire, :contenu, :image, NOW())";

        $stmtInsert = $db->prepare($sqlInsert);
        $stmtInsert->execute([
            ':id_expediteur'   => $currentUserId,
            ':id_destinataire' => $receiverId,
            ':contenu'         => $cleanContent,
            ':image'           => $storedImage
        ]);

        $messageId = $db->lastInsertId();

        sendJSONSuccess("Message envoyé avec succès.", [
            "message" => [
                "id"              => intval($messageId),
                "id_expediteur"   => $currentUserId,
                "id_destinataire" => $receiverId,
                "content"         => $content,
                "image"           => $storedImage,
                "date"            => date("Y-m-d H:i:s"),
                "is_me"           => true
            ]
        ], 201);

    } catch (PDOException $e) {
        error_log("Erreur Chat Send SQL : " . $e->getMessage());
        sendJSONError("Une erreur technique est survenue lors de l'envoi de votre message.", 500);
    }
}

sendJSONError("Méthode non autorisée.", 405);
