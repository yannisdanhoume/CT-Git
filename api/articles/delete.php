<?php
// Endpoint API - Supprimer un article (Auteur ou Modérateur/Admin)
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// 1. Sécurité : Vérifier que l'utilisateur est connecté
$currentUser = requireLogin();

// Sécurité : Autoriser uniquement la méthode DELETE (ou POST/GET selon vos préférences AJAX, mais restons sur DELETE)
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError("Méthode non autorisée.", 405);
}

// Récupération de l'ID de l'article (soit via GET, soit via les paramètres d'URL)
$postId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$postId || $postId <= 0) {
    sendJSONError("Identifiant d'article manquant ou invalide.", 400);
}

try {
    // Connexion à la base de données
    $db = getDBConnection();
    
    // 2. Récupération de l'article pour vérifier l'auteur et l'existence d'une image rattachée
    $stmtCheck = $db->prepare("SELECT id_utilisateur, image FROM articles WHERE id = :id LIMIT 1");
    $stmtCheck->execute([':id' => $postId]);
    $article = $stmtCheck->fetch();
    
    if (!$article) {
        sendJSONError("L'article demandé n'existe pas ou a déjà été supprimé.", 404);
    }
    
    // 3. Vérification des droits d'accès (Autorisé si c'est l'auteur OU si l'utilisateur est modérateur/admin)
    $isAuthor = (intval($article['id_utilisateur']) === intval($currentUser['id']));
    $isAdminOrMod = ($currentUser['role'] === 'administrator' || $currentUser['role'] === 'moderator');
    
    if (!$isAuthor && !$isAdminOrMod) {
        sendJSONError("Accès refusé. Vous n'avez pas l'autorisation de supprimer cette publication.", 403);
    }
    
    // 4. Nettoyage du stockage : Suppression de l'image sur le serveur (si elle existe)
    if (!empty($article['image'])) {
        $imagePath = __DIR__ . '/../../assets/images/' . $article['image'];
        if (file_exists($imagePath)) {
            @unlink($imagePath); // Le symbole @ évite de lever une notice PHP si le fichier a déjà été retiré manuellement
        }
    }
    
    // 5. Suppression de la publication en base de données
    // Note : Si vos tables de commentaires ou de likes n'ont pas de contrainte ON DELETE CASCADE,
    // il faudra idéalement supprimer les likes et commentaires liés à cet article avant, pour éviter les erreurs d'intégrité.
    $stmtDelete = $db->prepare("DELETE FROM articles WHERE id = :id");
    $stmtDelete->execute([':id' => $postId]);
    
    // 6. Réponse JSON de succès
    sendJSONSuccess("La publication a été supprimée avec succès.");

} catch (PDOException $e) {
    // Journalisation interne et masquage technique pour le client
    error_log("Erreur Articles Delete SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors de la suppression de l'article.", 500);
}