<?php
// Endpoint API - Réaction Like/Dislike (Système de bascule)
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// 1. Sécurité : Vérifier que l'utilisateur est connecté
$currentUser = requireLogin();

// Sécurité : Autoriser uniquement la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError("Méthode non autorisée. POST requis.", 405);
}

// Lecture et décodage des données JSON envoyées par la SPA (Axios / Fetch)
$input = json_decode(file_get_contents('php://input'), true);
$postId = isset($input['post_id']) ? intval($input['post_id']) : null;
$type   = isset($input['type']) ? trim($input['type']) : 'like'; // Par défaut 'like'

// Validation de l'identifiant de la publication
if (!$postId || $postId <= 0) {
    sendJSONError("Identifiant d'article manquant ou invalide.", 400);
}

try {
    // Connexion à la base de données
    $db = getDBConnection();
    
    // 2. Vérification de l'existence de l'article ciblé
    $stmtCheckPost = $db->prepare("SELECT id FROM articles WHERE id = :id LIMIT 1");
    $stmtCheckPost->execute([':id' => $postId]);
    if (!$stmtCheckPost->fetch()) {
        sendJSONError("L'article ciblé n'existe pas.", 404);
    }
    
    // 3. Vérification d'une réaction existante de cet utilisateur sur cet article
    $stmtCheckLike = $db->prepare("SELECT id FROM likes WHERE id_article = :id_article AND id_utilisateur = :id_utilisateur LIMIT 1");
    $stmtCheckLike->execute([
        ':id_article'    => $postId,
        ':id_utilisateur' => $currentUser['id']
    ]);
    $existingLike = $stmtCheckLike->fetch();
    
    $userReaction = null;
    $message = "";

    // 4. Logique de bascule (Toggle Likes)
    if ($existingLike) {
        // L'utilisateur a déjà aimé cet article -> On retire le like
        $stmtDelete = $db->prepare("DELETE FROM likes WHERE id_article = :id_article AND id_utilisateur = :id_utilisateur");
        $stmtDelete->execute([
            ':id_article'    => $postId,
            ':id_utilisateur' => $currentUser['id']
        ]);
        $userReaction = null; // Plus de réaction
        $message = "Mention J'aime retirée.";
    } else {
        // L'utilisateur n'a pas encore aimé -> On ajoute le like
        $stmtInsert = $db->prepare("INSERT INTO likes (id_article, id_utilisateur, date_like) VALUES (:id_article, :id_utilisateur, NOW())");
        $stmtInsert->execute([
            ':id_article'    => $postId,
            ':id_utilisateur' => $currentUser['id']
        ]);
        $userReaction = "like";
        $message = "Mention J'aime ajoutée.";
    }
    
    // 5. Calcul du nombre total de likes mis à jour pour cet article
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM likes WHERE id_article = :id_article");
    $stmtCount->execute([':id_article' => $postId]);
    $likesCount = intval($stmtCount->fetchColumn());
    
    // 6. Envoi de la réponse JSON contenant l'état frais pour la SPA
    sendJSONSuccess($message, [
        "post_id"       => $postId,
        "likes_count"   => $likesCount,
        "user_reaction" => $userReaction
    ]);

} catch (PDOException $e) {
    // Journalisation de l'anomalie SQL en interne
    error_log("Erreur Articles Like SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors du traitement de la réaction.", 500);
}