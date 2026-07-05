<?php
// Endpoint API - Lire le profil d'un utilisateur (Public ou Privé)
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// 1. Sécurité : Vérifier que l'utilisateur est connecté
$currentUser = requireLogin();

// 2. Détermination de l'ID ciblé (ID passé en paramètre GET, sinon l'utilisateur connecté lui-même)
$userId = isset($_GET['id']) ? intval($_GET['id']) : $currentUser['id'];

if ($userId <= 0) {
    sendJSONError("Identifiant utilisateur invalide.");
}

try {
    // Connexion à la base de données
    $db = getDBConnection();
    
    // 3. Récupération des informations de l'utilisateur ciblé
    $stmt = $db->prepare("SELECT id, nom, prenom, email, role, statut, avatar FROM utilisateurs WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    
    // 4. Vérification de l'existence de l'utilisateur
    if (!$user) {
        sendJSONError("Utilisateur introuvable.", 404);
    }

    $isOwnProfile = ($user['id'] === $currentUser['id']);
    $userIdInt = intval($user['id']);
    $currentUserIdInt = intval($currentUser['id']);

    $stmtFriendsCount = $db->prepare("SELECT COUNT(*) FROM amis WHERE statut = 'accepted' AND (id_demandeur = :user_id OR id_receveur = :user_id)");
    $stmtFriendsCount->execute([':user_id' => $userIdInt]);
    $friendsCount = intval($stmtFriendsCount->fetchColumn());

    $stmtRelationship = $db->prepare("SELECT statut, id_demandeur, id_receveur FROM amis WHERE ((id_demandeur = :viewer AND id_receveur = :target) OR (id_demandeur = :target AND id_receveur = :viewer)) LIMIT 1");
    $stmtRelationship->execute([
        ':viewer' => $currentUserIdInt,
        ':target' => $userIdInt
    ]);
    $relationship = $stmtRelationship->fetch();

    $relationshipLabel = 'Vous n\'êtes pas encore amis.';
    if ($isOwnProfile) {
        $relationshipLabel = 'C\'est votre profil.';
    } elseif ($relationship) {
        if ($relationship['statut'] === 'accepted') {
            $relationshipLabel = 'Vous êtes amis.';
        } elseif ($relationship['statut'] === 'pending') {
            if (intval($relationship['id_demandeur']) === $currentUserIdInt) {
                $relationshipLabel = 'Vous avez déjà envoyé une invitation.';
            } else {
                $relationshipLabel = 'Cet utilisateur vous a envoyé une invitation.';
            }
        }
    }
    
    // 5. Filtrage des données selon le contexte (Profil Propre vs Profil Public d'un tiers)
    if ($isOwnProfile) {
        // Profil Privé / Personnel : On transmet la totalité des informations utiles
        $profileData = [
            "id"     => $userIdInt,
            "nom"    => $user['nom'],
            "prenom" => $user['prenom'],
            "email"  => $user['email'],
            "role"   => $user['role'],
            "statut" => $user['statut'],
            "avatar" => $user['avatar'] ? $user['avatar'] : 'default-avatar.png',
            "is_owner" => true,
            "friends_count" => $friendsCount,
            "relationship_label" => $relationshipLabel
        ];
    } else {
        // Profil Public d'un tiers : Filtrage de l'e-mail et du statut pour la vie privée
        $profileData = [
            "id"     => $userIdInt,
            "nom"    => $user['nom'],
            "prenom" => $user['prenom'],
            "role"   => $user['role'],
            "avatar" => $user['avatar'] ? $user['avatar'] : 'default-avatar.png',
            "statut" => $user['statut'] ? $user['statut'] : 'actif',
            "is_owner" => false,
            "friends_count" => $friendsCount,
            "relationship_label" => $relationshipLabel
        ];
    }
    
    // 6. Envoi de la réponse de succès avec les données filtrées
    sendJSONSuccess("Profil récupéré avec succès.", [
        "user" => $profileData
    ]);

} catch (PDOException $e) {
    // Journalisation interne de l'anomalie SQL
    error_log("Erreur Profile Get SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors de la récupération du profil.", 500);
}