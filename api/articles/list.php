<?php
// Endpoint API - Liste des articles (Fil d'actualité dynamique)
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// 1. Sécurité : Exiger que l'utilisateur soit connecté
$currentUser = requireLogin();

try {
    // Connexion à la base de données
    $db = getDBConnection();

    $userIdFilter = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
    $sqlArticles = "SELECT a.id, a.id_utilisateur, a.description, a.image, a.date_publication,
                           u.nom AS author_nom, u.prenom AS author_prenom, u.avatar AS author_avatar
                    FROM articles a
                    LEFT JOIN utilisateurs u ON a.id_utilisateur = u.id";

    $params = [];
    if ($userIdFilter && $userIdFilter > 0) {
        $sqlArticles .= " WHERE a.id_utilisateur = :user_id";
        $params[':user_id'] = $userIdFilter;
    }

    $sqlArticles .= " ORDER BY a.date_publication DESC";
    
    $stmtArticles = $db->prepare($sqlArticles);
    $stmtArticles->execute($params);
    $articlesRows = $stmtArticles->fetchAll();
    
    $feedArticles = [];
    
    foreach ($articlesRows as $row) {
        $articleId = intval($row['id']);
        $authorId  = intval($row['id_utilisateur']);
        
        // 3. Calcul du nombre de likes pour cet article
        $stmtLikes = $db->prepare("SELECT COUNT(*) FROM likes WHERE id_article = :id_article");
        $stmtLikes->execute([':id_article' => $articleId]);
        $likesCount = intval($stmtLikes->fetchColumn());
        
        // 4. Vérification de la réaction de l'utilisateur connecté (like ou dislike ?)
        $stmtUserReaction = $db->prepare("SELECT type FROM likes WHERE id_article = :id_article AND id_utilisateur = :id_user LIMIT 1");
        $stmtUserReaction->execute([
            ':id_article' => $articleId,
            ':id_user'    => $currentUser['id']
        ]);
        $reactionRow = $stmtUserReaction->fetch();
        $userReaction = $reactionRow ? $reactionRow['type'] : null;
        
        // 5. Récupération et comptage des commentaires associés à cet article
        $stmtComments = $db->prepare("SELECT c.id, c.contenu, c.date_commentaire, 
                                             u.nom, u.prenom, u.avatar
                                      FROM commentaires c
                                      LEFT JOIN utilisateurs u ON c.id_utilisateur = u.id
                                      WHERE c.id_article = :id_article
                                      ORDER BY c.date_commentaire ASC");
        $stmtComments->execute([':id_article' => $articleId]);
        $commentsRows = $stmtComments->fetchAll();
        
        $commentsList = [];
        foreach ($commentsRows as $comment) {
            $commentsList[] = [
                "author_name"   => $comment['prenom'] . ' ' . $comment['nom'],
                "author_avatar" => $comment['avatar'] ? $comment['avatar'] : 'default-avatar.png',
                "content"       => htmlspecialchars_decode($comment['contenu']) // Restitution du texte propre
            ];
        }
        
        // 6. Droits de suppression (can_delete) : l'auteur OU un modérateur/administrateur
        $canDelete = ($authorId === intval($currentUser['id'])) || 
                     ($currentUser['role'] === 'administrator' || $currentUser['role'] === 'moderator');
                     
        // 7. Structuration de l'article conforme aux attentes de feed.js
        $feedArticles[] = [
            "id"             => $articleId,
            "author_id"      => $authorId,
            "author_name"    => $row['author_prenom'] . ' ' . $row['author_nom'],
            "author_avatar"  => $row['author_avatar'] ? $row['author_avatar'] : 'default-avatar.png',
            "date"           => $row['date_publication'], // Vous pouvez formater la date ici ou côté JS
            "description"    => htmlspecialchars_decode($row['description']),
            "image"          => $row['image'], // Nom de l'image stockée ou null
            "likes_count"    => $likesCount,
            "comments_count" => count($commentsList),
            "user_reaction"  => $userReaction,
            "can_delete"     => $canDelete,
            "comments"       => $commentsList
        ];
    }
    
    // 8. Envoi du flux d'actualités au format JSON
    sendJSONSuccess("Fil d'actualité récupéré avec succès.", [
        "articles" => $feedArticles
    ]);

} catch (PDOException $e) {
    error_log("Erreur Articles List SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors du chargement du fil d'actualité.", 500);
}
