<?php
// Endpoint API - Envoyer une invitation d'ami (Persistant)
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// 1. Sécurité : Vérifier que l'utilisateur est connecté
$currentUser = requireLogin();

// Sécurité : Autoriser uniquement la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError("Méthode non autorisée. POST requis.", 405);
}

// Lecture et décodage des données JSON envoyées par la SPA
$input = json_decode(file_get_contents('php://input'), true);
$receiverId = isset($input['receiver_id']) ? intval($input['receiver_id']) : null;

// Validation immédiate : ID manquant ou tentative de s'ajouter soi-même
if (!$receiverId || $receiverId === intval($currentUser['id'])) {
    sendJSONError("Destinataire invalide ou action impossible.", 400);
}

try {
    $db = getDBConnection();
    $currentUserId = intval($currentUser['id']);

    // 2. Vérification : Le destinataire existe-t-il réellement en BDD ?
    $stmtCheckUser = $db->prepare("SELECT id FROM utilisateurs WHERE id = :id LIMIT 1");
    $stmtCheckUser->execute([':id' => $receiverId]);
    if (!$stmtCheckUser->fetch()) {
        sendJSONError("L'utilisateur que vous tentez d'ajouter n'existe pas.", 404);
    }

    // 3. Vérification : Existe-t-il déjà un lien ou une invitation entre les deux ?
    // On cherche s'il y a une ligne (Moi -> Lui) OU (Lui -> Moi)
    $sqlCheckRelation = "SELECT id, id_demandeur, statut FROM amis 
                         WHERE (id_demandeur = :user1 AND id_receveur = :receiver1) 
                            OR (id_demandeur = :receiver2 AND id_receveur = :user2) 
                         LIMIT 1";
                         
    $stmtCheckRelation = $db->prepare($sqlCheckRelation);
    $stmtCheckRelation->execute([
        ':user1'     => $currentUserId,
        ':receiver1' => $receiverId,
        ':receiver2' => $receiverId,
        ':user2'     => $currentUserId
    ]);
    
    $relation = $stmtCheckRelation->fetch();

    if ($relation) {
        // Une relation existe déjà, on adapte le message selon le statut
        if ($relation['statut'] === 'accepted') {
            sendJSONError("Vous êtes déjà amis avec cet utilisateur.", 400);
        } elseif ($relation['statut'] === 'pending') {
            if (intval($relation['id_demandeur']) === $currentUserId) {
                sendJSONError("Vous avez déjà envoyé une invitation en attente à cet utilisateur.", 400);
            } else {
                sendJSONError("Cet utilisateur vous a déjà envoyé une invitation. Veuillez y répondre dans vos demandes.", 400);
            }
        }
    }

    // 4. Insertion de la demande d'amitié en base de données
    $sqlInsert = "INSERT INTO amis (id_demandeur, id_receveur, statut, date_affiliation) 
                  VALUES (:id_demandeur, :id_receveur, 'pending', NOW())";
                  
    $stmtInsert = $db->prepare($sqlInsert);
    $stmtInsert->execute([
        ':id_demandeur' => $currentUserId,
        ':id_receveur'  => $receiverId
    ]);

    // 5. Réponse JSON de succès
    sendJSONSuccess("Invitation d'amitié envoyée avec succès.");

} catch (PDOException $e) {
    error_log("Erreur Friends Request SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors de l'envoi de l'invitation.", 500);
}