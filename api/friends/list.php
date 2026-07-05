<?php
// Endpoint API - Récupération dynamique des amis, demandes en attente et suggestions
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// 1. Sécurité : Exiger que l'utilisateur soit connecté
$currentUser = requireLogin();

try {
    $db = getDBConnection();
    $currentUserId = intval($currentUser['id']);

    // --- A. RÉCUPÉRATION DES AMIS CONFIRMÉS ('accepted') ---
    // Une relation d'amitié peut avoir été initiée par l'un ou l'autre des utilisateurs.
    // On cible donc les lignes où le statut est 'accepted' et où l'utilisateur connecté est soit le demandeur, soit le destinataire.
    $sqlFriends = "SELECT u.id, u.nom, u.prenom, u.email, u.avatar 
                   FROM amis a
                   JOIN utilisateurs u ON (a.id_demandeur = u.id OR a.id_receveur = u.id)
                   WHERE u.id != :current_id1 
                     AND (a.id_demandeur = :current_id2 OR a.id_receveur = :current_id3)
                     AND a.statut = 'accepted'";
                     
    $stmtFriends = $db->prepare($sqlFriends);
    $stmtFriends->execute([
        ':current_id1' => $currentUserId,
        ':current_id2' => $currentUserId,
        ':current_id3' => $currentUserId
    ]);
    $friends = $stmtFriends->fetchAll();

    // Uniformisation des avatars pour l'affichage
    foreach ($friends as &$f) {
        $f['id'] = intval($f['id']);
        if (empty($f['avatar'])) $f['avatar'] = 'default-avatar.png';
    }

    // --- B. RÉCUPÉRATION DES DEMANDES REÇUES EN ATTENTE ('pending') ---
    // On sélectionne les profils des utilisateurs ayant envoyé une demande dont l'utilisateur connecté est le receveur.
    $sqlRequests = "SELECT u.id, u.nom, u.prenom, u.email, u.avatar 
                    FROM amis a
                    JOIN utilisateurs u ON a.id_demandeur = u.id
                    WHERE a.id_receveur = :current_id 
                      AND a.statut = 'pending'";
                      
    $stmtRequests = $db->prepare($sqlRequests);
    $stmtRequests->execute([':current_id' => $currentUserId]);
    $requests = $stmtRequests->fetchAll();

    foreach ($requests as &$r) {
        $r['id'] = intval($r['id']);
        if (empty($r['avatar'])) $r['avatar'] = 'default-avatar.png';
    }

    // --- C. RÉCUPÉRATION DES INVITATIONS ENVOYÉES EN ATTENTE ('pending') ---
    $sqlSentRequests = "SELECT u.id, u.nom, u.prenom, u.email, u.avatar 
                        FROM amis a
                        JOIN utilisateurs u ON a.id_receveur = u.id
                        WHERE a.id_demandeur = :current_id
                          AND a.statut = 'pending'";

    $stmtSentRequests = $db->prepare($sqlSentRequests);
    $stmtSentRequests->execute([':current_id' => $currentUserId]);
    $sentRequests = $stmtSentRequests->fetchAll();

    foreach ($sentRequests as &$sr) {
        $sr['id'] = intval($sr['id']);
        if (empty($sr['avatar'])) $sr['avatar'] = 'default-avatar.png';
    }

    // --- D. RÉCUPÉRATION DES SUGGESTIONS D'AMIS ---
    // On suggère des utilisateurs qui ne sont ni nous-mêmes, ni déjà amis, ni impliqués dans une demande (émise ou reçue).
    $sqlSuggestions = "SELECT id, nom, prenom, email, avatar 
                       FROM utilisateurs 
                       WHERE id != :current_id 
                         AND id NOT IN (
                             SELECT id_receveur FROM amis WHERE id_demandeur = :current_id_dem
                             UNION
                             SELECT id_demandeur FROM amis WHERE id_receveur = :current_id_rec
                         )
                       LIMIT 10"; // Limité à 10 propositions pour optimiser les performances
                       
    $stmtSuggestions = $db->prepare($sqlSuggestions);
    $stmtSuggestions->execute([
        ':current_id'     => $currentUserId,
        ':current_id_dem' => $currentUserId,
        ':current_id_rec' => $currentUserId
    ]);
    $suggestions = $stmtSuggestions->fetchAll();

    foreach ($suggestions as &$s) {
        $s['id'] = intval($s['id']);
        if (empty($s['avatar'])) $s['avatar'] = 'default-avatar.png';
    }

    // 2. Envoi des listes réelles et synchronisées au format JSON
    sendJSONSuccess("Listes chargées de manière dynamique.", [
        "friends"               => $friends,
        "requests"              => $requests,
        "sent_requests"         => $sentRequests,
        "pending_requests_count"=> count($requests),
        "suggestions"           => $suggestions
    ]);

} catch (PDOException $e) {
    error_log("Erreur Friends List SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors du chargement des listes de relations.", 500);
}