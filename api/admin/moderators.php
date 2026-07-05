<?php
// Endpoint API - Exclusif Administrateur : Gestion des rangs du personnel (Promotion/Rétrogradation)
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// Sécurité STRICTE : Exclusif à l'Administrateur
$currentUser = requireLogin();
if ($currentUser['role'] !== 'administrator') {
    sendJSONError("Accès strictement refusé. Seul l'Administrateur peut gérer le personnel.", 403);
}

try {
    $db = getDBConnection();

    // --- CAS GET : Voir uniquement l'équipe actuelle (pour l'auditer) ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $sql = "SELECT id, nom, prenom, email, role, avatar 
                FROM utilisateurs 
                WHERE role IN ('moderator', 'administrator')
                ORDER BY role ASC, nom ASC";
        $staff = $db->query($sql)->fetchAll();

        foreach ($staff as &$member) {
            $member['id'] = intval($member['id']);
            if (empty($member['avatar'])) $member['avatar'] = 'default-avatar.png';
        }

        sendJSONSuccess("Liste du personnel de modération.", ["staff" => $staff]);
    }

    // --- CAS POST : Promouvoir ou Rétrograder un utilisateur ---
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input   = json_decode(file_get_contents('php://input'), true);
        $userId  = isset($input['user_id']) ? intval($input['user_id']) : null;
        $newRole = isset($input['role']) ? trim($input['role']) : null; // 'client', 'moderator', 'administrator'

        if (!$userId || !in_array($newRole, ['client', 'moderator', 'administrator'])) {
            sendJSONError("Paramètres invalides. Rôle ou ID manquant.", 400);
        }

        // Empêcher l'administrateur de s'auto-rétrograder par erreur
        if ($userId === intval($currentUser['id'])) {
            sendJSONError("Vous ne pouvez pas modifier votre propre rôle de sécurité.", 400);
        }

        // Appliquer le nouveau rôle (Bascule / Affectation)
        $stmtUpdate = $db->prepare("UPDATE utilisateurs SET role = :role WHERE id = :id");
        $stmtUpdate->execute([
            ':role' => $newRole,
            ':id'   => $userId
        ]);

        $msg = ($newRole === 'client') ? "L'utilisateur a été rétrogradé au rang de membre standard." : "L'utilisateur a été promu avec succès au rôle de : " . $newRole;

        sendJSONSuccess($msg, [
            "user_id"  => $userId,
            "new_role" => $newRole
        ]);
    } 
    
    else {
        sendJSONError("Méthode non autorisée.", 405);
    }

} catch (PDOException $e) {
    error_log("Erreur Admin Moderators : " . $e->getMessage());
    sendJSONError("Une erreur est survenue lors de la modification des privilèges.", 500);
}