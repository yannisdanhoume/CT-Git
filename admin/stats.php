<?php
// Endpoint API - Statistiques du Dashboard Admin
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// Sécurité : Réservé aux modérateurs et administrateurs
$currentUser = requireLogin();
if ($currentUser['role'] !== 'administrator' && $currentUser['role'] !== 'moderator') {
    sendJSONError("Accès refusé. Privilèges insuffisants.", 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSONError("Méthode non autorisée. GET requis.", 405);
}

try {
    $db = getDBConnection();

    // 1. Nombre total d'inscrits
    $totalUsers = intval($db->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn());

    // 2. Nombre d'articles publiés
    $totalArticles = intval($db->query("SELECT COUNT(*) FROM articles")->fetchColumn());

    // 3. Nombre de messages de chat envoyés
    $totalMessages = intval($db->query("SELECT COUNT(*) FROM messages")->fetchColumn());

    // 4. Activité récente : Nouveaux inscrits ces 7 derniers jours
    $recentRegistrations = intval($db->query("SELECT COUNT(*) FROM utilisateurs WHERE date_inscription >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn());

    // 5. Liste de tous les comptes avec leur statut (pour l'affichage détaillé)
    $stmtAccounts = $db->query("SELECT id, nom, prenom, email, role, statut, date_inscription FROM utilisateurs ORDER BY date_inscription DESC");
    $accounts = $stmtAccounts->fetchAll();
    foreach ($accounts as &$acc) {
        $acc['id'] = intval($acc['id']);
        if (empty($acc['role'])) $acc['role'] = 'client';
        if (empty($acc['statut'])) $acc['statut'] = 'actif';
    }

    sendJSONSuccess("Indicateurs clés récupérés.", [
        "stats" => [
            "total_users"          => $totalUsers,
            "total_articles"       => $totalArticles,
            "total_messages"       => $totalMessages,
            "recent_registrations" => $recentRegistrations
        ],
        "accounts" => $accounts
    ]);

} catch (PDOException $e) {
    error_log("Erreur Admin Stats : " . $e->getMessage());
    sendJSONError("Impossible de calculer les indicateurs clés.", 500);
}