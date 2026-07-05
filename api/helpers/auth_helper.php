<?php
// Helper d'Authentification Backend (Simule les Sessions PHP via Jeton sessionStorage)

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/../config/db.php';

/**
 * Initialise ou restaure la session PHP à partir du token d'autorisation du client
 */
function initSessionFromToken() {
    $token = null;
    
    // 1. Essai de lecture du token dans l'en-tête Authorization
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
    }
    
    // 2. Si non trouvé dans Authorization, vérification dans les paramètres GET/POST (fallback)
    if (!$token && isset($_REQUEST['token'])) {
        $token = $_REQUEST['token'];
    }
    
    // Si un token est fourni, on configure l'ID de session PHP avec ce token avant de démarrer la session
    if ($token && preg_match('/^[a-zA-Z0-9,-]{22,128}$/', $token)) {
        session_id($token);
    }
    
    // Démarrage de la session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Exige que l'utilisateur soit connecté. Retourne ses informations de session ou bloque avec une erreur 401.
 */
function requireLogin() {
    initSessionFromToken();
    
    if (!isset($_SESSION['user_id'])) {
        sendJSONError("Accès refusé. Veuillez vous connecter.", 401);
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'nom' => $_SESSION['nom'],
        'prenom' => $_SESSION['prenom'],
        'email' => $_SESSION['email'],
        'role' => $_SESSION['role']
    ];
}

/**
 * Exige que l'utilisateur ait un des rôles d'administration spécifiés (moderator, administrator).
 */
function requireRole($allowedRoles = ['moderator', 'administrator']) {
    $user = requireLogin();
    
    if (!in_array($user['role'], $allowedRoles)) {
        sendJSONError("Accès interdit. Privilèges insuffisants.", 403);
    }
    
    return $user;
}