<?php
// Endpoint API - Réaction Dislike (Système de bascule)
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
$postId = isset($input['post_id']) ? intval($input['post_id']) : null;
$type   = isset($input['type']) ? trim($input['type']) : 'dislike';

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
    $stmtCheckLike = $db->prepare("SELECT id, type FROM likes WHERE id_article = :id_article AND id_utilisateur = :id_utilisateur LIMIT 1");
    $stmtCheckLike->execute([
        ':id_article'     => $postId,
        ':id_utilisateur' => $currentUser['id']
    ]);
    $existingLike = $stmtCheckLike->fetch();

    $userReaction = null;
    $message = "";

    // 4. Logique de bascule (Toggle Dislike)
    if ($existingLike) {
        if ($existingLike['type'] === 'dislike') {
            // Déjà un dislike -> on retire la réaction
            $stmtDelete = $db->prepare("DELETE FROM likes WHERE id_article = :id_article AND id_utilisateur = :id_utilisateur");
            $stmtDelete->execute([
                ':id_article'     => $postId,
                ':id_utilisateur' => $currentUser['id']
            ]);
            $userReaction = null;
            $message = "Mention Je n'aime pas retirée.";
        } else {
            // C'était un like -> on bascule en dislike
            $stmtUpdate = $db->prepare("UPDATE likes SET type = 'dislike' WHERE id_article = :id_article AND id_utilisateur = :id_utilisateur");
            $stmtUpdate->execute([
                ':id_article'     => $postId,
                ':id_utilisateur' => $currentUser['id']
            ]);
            $userReaction = 'dislike';
            $message = "Mention Je n'aime pas ajoutée.";
        }
    } else {
        // Aucune réaction existante -> on insère un dislike
        $stmtInsert = $db->prepare("INSERT INTO likes (id_article, id_utilisateur, type, date_like) VALUES (:id_article, :id_utilisateur, 'dislike', NOW())");
        $stmtInsert->execute([
            ':id_article'     => $postId,
            ':id_utilisateur' => $currentUser['id']
        ]);
        $userReaction = 'dislike';
        $message = "Mention Je n'aime pas ajoutée.";
    }

    // 5. Calcul du nombre total de likes et dislikes pour cet article
    $stmtLikes = $db->prepare("SELECT COUNT(*) FROM likes WHERE id_article = :id_article AND type = 'like'");
    $stmtLikes->execute([':id_article' => $postId]);
    $likesCount = intval($stmtLikes->fetchColumn());

    $stmtDislikes = $db->prepare("SELECT COUNT(*) FROM likes WHERE id_article = :id_article AND type = 'dislike'");
    $stmtDislikes->execute([':id_article' => $postId]);
    $dislikesCount = intval($stmtDislikes->fetchColumn());

    // 6. Envoi de la réponse JSON
    sendJSONSuccess($message, [
        "post_id"         => $postId,
        "likes_count"     => $likesCount,
        "dislikes_count"  => $dislikesCount,
        "user_reaction"   => $userReaction
    ]);

} catch (PDOException $e) {
    error_log("Erreur Articles Dislike SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors du traitement de la réaction.", 500);
}
