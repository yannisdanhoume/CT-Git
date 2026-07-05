<?php
// Endpoint API - Liste des conversations actives de l'utilisateur (Persistant)
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// 1. Sécurité : Vérifier que l'utilisateur est connecté
$currentUser = requireLogin();

// Sécurité : Autoriser uniquement la méthode GET pour la lecture
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSONError("Méthode non autorisée. GET requis.", 405);
}

try {
    $db = getDBConnection();
    $currentUserId = intval($currentUser['id']);

    /**
     * 2. Requête SQL avancée (Sous-requête de regroupement)
     * Cette requête permet d'isoler le dernier message de chaque conversation (croisée)
     * et de faire une jointure avec la table 'utilisateurs' pour récupérer l'identité du correspondant.
     */
    $sql = "SELECT 
                u.id AS partner_id,
                u.nom AS partner_nom,
                u.prenom AS partner_prenom,
                u.avatar AS partner_avatar,
                m.contenu AS last_message,
                m.date_message AS updated_at
            FROM messages m
            INNER JOIN utilisateurs u ON u.id = IF(m.id_expediteur = :user_id1, m.id_destinataire, m.id_expediteur)
            INNER JOIN (
                SELECT 
                    IF(id_expediteur = :user_id2, id_destinataire, id_expediteur) AS partner_id,
                    MAX(date_message) AS max_date
                FROM messages
                WHERE id_expediteur = :user_id3 OR id_destinataire = :user_id4
                GROUP BY partner_id
            ) last_msg ON (IF(m.id_expediteur = :user_id5, m.id_destinataire, m.id_expediteur) = last_msg.partner_id AND m.date_message = last_msg.max_date)
            WHERE m.id_expediteur = :user_id6 OR m.id_destinataire = :user_id7
            ORDER BY m.date_message DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':user_id1' => $currentUserId,
        ':user_id2' => $currentUserId,
        ':user_id3' => $currentUserId,
        ':user_id4' => $currentUserId,
        ':user_id5' => $currentUserId,
        ':user_id6' => $currentUserId,
        ':user_id7' => $currentUserId
    ]);

    $conversationsRows = $stmt->fetchAll();
    $conversationsList = [];

    // 3. Formatage propre et sécurisé pour l'affichage dans la SPA
    foreach ($conversationsRows as $row) {
        $conversationsList[] = [
            "partner_id"     => intval($row['partner_id']),
            "partner_name"   => $row['partner_prenom'] . ' ' . $row['partner_nom'],
            "partner_avatar" => !empty($row['partner_avatar']) ? $row['partner_avatar'] : 'default-avatar.png',
            "last_message"   => !empty($row['last_message']) ? htmlspecialchars_decode($row['last_message']) : 'Image envoyée',
            "updated_at"     => $row['updated_at']
        ];
    }

    // 4. Envoi de la réponse JSON au frontend
    sendJSONSuccess("Liste des conversations chargée de manière dynamique.", $conversationsList);

} catch (PDOException $e) {
    error_log("Erreur Chat Conversations SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors de la récupération des discussions.", 500);
}