<?php
// Endpoint API - Répondre à une invitation d'ami (Accepter ou Refuser)
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// 1. Sécurité : Vérifier que l'utilisateur est connecté
$currentUser = requireLogin();

// Sécurité : Autoriser uniquement la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError("Méthode non autorisée. POST requis.", 405);
}

// Lecture et décodage des paramètres JSON reçus de la SPA
$input = json_decode(file_get_contents('php://input'), true);
$senderId = isset($input['sender_id']) ? intval($input['sender_id']) : null;
$action   = isset($input['action']) ? trim($input['action']) : null;

// Validation des paramètres obligatoires
if (!$senderId || !in_array($action, ['accept', 'decline'])) {
    sendJSONError("Paramètres manquants ou invalides.", 400);
}

try {
    $db = getDBConnection();
    $currentUserId = intval($currentUser['id']);

    // 2. Vérification : La demande d'ami reçue existe-t-elle vraiment et est-elle toujours en attente ?
    $sqlCheck = "SELECT id FROM amis 
                 WHERE id_demandeur = :sender_id 
                   AND id_receveur = :current_user_id 
                   AND statut = 'pending' 
                 LIMIT 1";
                 
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->execute([
        ':sender_id'        => $senderId,
        ':current_user_id'  => $currentUserId
    ]);
    
    if (!$stmtCheck->fetch()) {
        sendJSONError("Aucune invitation en attente ne correspond à cette demande.", 404);
    }

    // 3. Traitement de la réponse en fonction de l'action choisie
    if ($action === 'accept') {
        $db->beginTransaction();

        // L'invitation est acceptée -> Passage du statut à 'accepted'
        $sqlUpdate = "UPDATE amis 
                      SET statut = 'accepted', date_affiliation = NOW() 
                      WHERE id_demandeur = :sender_id 
                        AND id_receveur = :current_user_id";
                        
        $stmtUpdate = $db->prepare($sqlUpdate);
        $stmtUpdate->execute([
            ':sender_id'       => $senderId,
            ':current_user_id' => $currentUserId
        ]);

        // Création automatique d'une conversation avec un message de bienvenue
        $welcomeMessage = 'salut';
        $stmtExisting = $db->prepare("SELECT id FROM messages WHERE (id_expediteur = :sender_id AND id_destinataire = :current_user_id) OR (id_expediteur = :current_user_id AND id_destinataire = :sender_id) LIMIT 1");
        $stmtExisting->execute([
            ':sender_id'       => $senderId,
            ':current_user_id' => $currentUserId
        ]);

        if (!$stmtExisting->fetch()) {
            $stmtInsert = $db->prepare("INSERT INTO messages (id_expediteur, id_destinataire, contenu, image, date_message) VALUES (:sender_id, :current_user_id, :contenu, NULL, NOW())");
            $stmtInsert->execute([
                ':sender_id'       => $senderId,
                ':current_user_id' => $currentUserId,
                ':contenu'         => htmlspecialchars($welcomeMessage, ENT_QUOTES, 'UTF-8')
            ]);
        }

        $db->commit();
        $message = "Vous êtes désormais amis !";
        
    } else {
        // L'invitation est déclinée ('decline') -> Suppression de la ligne pour libérer les suggestions
        $sqlDelete = "DELETE FROM amis 
                      WHERE id_demandeur = :sender_id 
                        AND id_receveur = :current_user_id";
                        
        $stmtDelete = $db->prepare($sqlDelete);
        $stmtDelete->execute([
            ':sender_id'       => $senderId,
            ':current_user_id' => $currentUserId
        ]);
        
        $message = "Invitation déclinée avec succès.";
    }

    // 4. Envoi de la réponse de succès à la SPA
    sendJSONSuccess($message, [
        "sender_id" => $senderId,
        "action"    => $action
    ]);

} catch (PDOException $e) {
    error_log("Erreur Friends Respond SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors du traitement de votre réponse.", 500);
}