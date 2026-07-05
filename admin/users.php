<?php
// Endpoint API - Gestion des comptes utilisateurs (Examen, Suppression et Blocage)
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// 1. Sécurité : Ouvert uniquement aux modérateurs et administrateurs
$currentUser = requireLogin();
if ($currentUser['role'] !== 'administrator' && $currentUser['role'] !== 'moderator') {
    sendJSONError("Accès refusé.", 403);
}

try {
    $db = getDBConnection();
    $currentUserId = intval($currentUser['id']);

    // --- CAS 1 : MÉTHODE GET -> Examiner et lister tous les comptes ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // On récupère toutes les informations importantes, y compris le statut (actif/bloque)
        $sql = "SELECT id, nom, prenom, email, role, avatar, statut, date_inscription 
                FROM utilisateurs 
                WHERE id != :current_id 
                ORDER BY date_inscription DESC";
                
        $stmt = $db->prepare($sql);
        $stmt->execute([':current_id' => $currentUserId]);
        $users = $stmt->fetchAll();

        foreach ($users as &$u) {
            $u['id'] = intval($u['id']);
            if (empty($u['avatar'])) $u['avatar'] = 'default-avatar.png';
        }

        sendJSONSuccess("Liste des comptes utilisateurs.", ["users" => $users]);
    }

    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $userId = isset($_GET['id']) ? intval($_GET['id']) : null;

        if (!$userId || $userId <= 0) {
            sendJSONError("Identifiant utilisateur manquant.", 400);
        }

        if ($userId === $currentUserId) {
            sendJSONError("Action impossible sur votre propre compte.", 400);
        }

        $stmtCheck = $db->prepare("SELECT role FROM utilisateurs WHERE id = :id LIMIT 1");
        $stmtCheck->execute([':id' => $userId]);
        $targetUser = $stmtCheck->fetch();

        if (!$targetUser) {
            sendJSONError("L'utilisateur ciblé n'existe pas.", 404);
        }

        // Sécurité : Un modérateur ne doit pas pouvoir agir sur un administrateur ni sur un autre modérateur
        if ($currentUser['role'] === 'moderator' && in_array($targetUser['role'], ['administrator', 'moderator'])) {
            sendJSONError("Action interdite. Un modérateur ne peut pas sanctionner un administrateur ou un autre modérateur.", 403);
        }

        $stmtDelete = $db->prepare("DELETE FROM utilisateurs WHERE id = :id");
        $stmtDelete->execute([':id' => $userId]);

        sendJSONSuccess("Le compte utilisateur a été supprimé définitivement.", ["user_id" => $userId, "action" => "delete"]);
    }

    // --- CAS 3 : MÉTHODE POST -> Actions de Modération (Supprimer OU Bloquer) ---
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input  = json_decode(file_get_contents('php://input'), true);
        $userId = isset($input['user_id']) ? intval($input['user_id']) : null;
        $action = isset($input['action']) ? trim($input['action']) : ''; // 'delete', 'block', ou 'unblock'

        // Validation de base des paramètres obligatoires
        if (!$userId || !in_array($action, ['delete', 'block', 'unblock'])) {
            sendJSONError("Paramètres manquants ou action invalide.", 400);
        }

        // Sécurité : Interdire de s'auto-sanctionner
        if ($userId === $currentUserId) {
            sendJSONError("Action impossible sur votre propre compte.", 400);
        }

        // Vérification de l'existence et du rôle de la cible en base de données
        $stmtCheck = $db->prepare("SELECT role FROM utilisateurs WHERE id = :id LIMIT 1");
        $stmtCheck->execute([':id' => $userId]);
        $targetUser = $stmtCheck->fetch();

        if (!$targetUser) {
            sendJSONError("L'utilisateur ciblé n'existe pas.", 404);
        }

        // Sécurité : Un modérateur n'a pas le droit de toucher à un administrateur
        // Sécurité : Empêcher un modérateur d'agir sur les administrateurs et les autres modérateurs
        if ($currentUser['role'] === 'moderator' && in_array($targetUser['role'], ['administrator', 'moderator'])) {
            sendJSONError("Action interdite. Un modérateur ne peut pas effectuer cette action sur un administrateur ou un autre modérateur.", 403);
        }

        // --- TRAITEMENT SELON L'ACTION DEMANDÉE ---
        
        if ($action === 'delete') {
            // Action A : Suppression définitive du compte
            $stmtDelete = $db->prepare("DELETE FROM utilisateurs WHERE id = :id");
            $stmtDelete->execute([':id' => $userId]);
            
            sendJSONSuccess("Le compte utilisateur a été supprimé définitivement.", [
                "user_id" => $userId,
                "action"  => "delete"
            ]);
            
        } else {
            // Action B & C : Blocage ou Déblocage (Changement de statut)
            $newStatus = ($action === 'block') ? 'bloque' : 'actif';
            
            $stmtUpdate = $db->prepare("UPDATE utilisateurs SET statut = :statut WHERE id = :id");
            $stmtUpdate->execute([
                ':statut' => $newStatus,
                ':id'     => $userId
            ]);
            
            $msg = ($action === 'block') ? "Le compte a été suspendu avec succès." : "Le compte a été réactivé.";
            
            sendJSONSuccess($msg, [
                "user_id" => $userId,
                "statut"  => $newStatus,
                "action"  => $action
            ]);
        }
    } 
    
    else {
        sendJSONError("Méthode non autorisée.", 405);
    }

} catch (PDOException $e) {
    error_log("Erreur Admin Users Combined : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors de l'action sur ce compte.", 500);
}