<?php
// Endpoint API - Ajouter un commentaire sur un article (Zéro rechargement)
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// 1. Sécurité : Vérifier que l'utilisateur est connecté
$currentUser = requireLogin();

// Sécurité : Autoriser uniquement la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError("Méthode non autorisée. POST requis.", 405);
}

// Lecture et décodage du corps de la requête JSON (Axios / Fetch)
$input   = json_decode(file_get_contents('php://input'), true);
$postId  = isset($input['post_id']) ? intval($input['post_id']) : null;
$content = isset($input['content']) ? trim($input['content']) : '';

// Validation de la présence et de la conformité des données
if (!$postId || $postId <= 0 || empty($content)) {
    sendJSONError("Champs vides ou invalides. Impossible de publier un commentaire vide.", 400);
}

try {
    // Connexion à la base de données
    $db = getDBConnection();
    
    // 2. Sécurité : Vérifier l'existence réelle de l'article à commenter
    $stmtCheckPost = $db->prepare("SELECT id FROM articles WHERE id = :id LIMIT 1");
    $stmtCheckPost->execute([':id' => $postId]);
    if (!$stmtCheckPost->fetch()) {
        sendJSONError("L'article ciblé n'existe pas ou a été supprimé.", 404);
    }
    
    // 3. Protection Anti-XSS : Neutralisation des balises HTML/Script potentiellement dangereuses
    $cleanContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    
    // 4. Insertion du commentaire en base de données (Requête préparée)
    $sqlInsert = "INSERT INTO commentaires (id_article, id_utilisateur, contenu, date_commentaire) 
                  VALUES (:id_article, :id_utilisateur, :contenu, NOW())";
                  
    $stmtInsert = $db->prepare($sqlInsert);
    $stmtInsert->execute([
        ':id_article'    => $postId,
        ':id_utilisateur' => $currentUser['id'],
        ':contenu'       => $cleanContent
    ]);
    
    // Récupération de l'ID du commentaire inséré
    $commentId = $db->lastInsertId();

    // 5. Envoi des données complètes à la SPA pour une injection visuelle instantanée
    // On combine l'identité de la session pour éviter une requête SELECT superflue
    sendJSONSuccess("Commentaire ajouté avec succès !", [
        "comment" => [
            "id"             => intval($commentId),
            "post_id"        => $postId,
            "author_name"    => $currentUser['prenom'] . ' ' . $currentUser['nom'],
            "author_avatar"  => !empty($_SESSION['avatar']) ? $_SESSION['avatar'] : 'default-avatar.png',
            "content"        => $content, // Le JS réinjectera le texte brut (sécurisé par l'affichage texte de la SPA)
            "date"           => date("Y-m-d H:i:s")
        ]
    ], 201); // 201 Created

} catch (PDOException $e) {
    // Journalisation interne de l'erreur SQL
    error_log("Erreur Commentaires Add SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors de l'enregistrement de votre commentaire.", 500);
}