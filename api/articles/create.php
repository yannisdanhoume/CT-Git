<?php
// Endpoint API - Création d'un article / publication
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// 1. Sécurité : Vérifier que l'utilisateur est connecté
$currentUser = requireLogin();

// Sécurité : Autoriser uniquement la méthode POST (FormData contenant texte + fichier optionnel)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError("Méthode non autorisée. POST requis.", 405);
}

// Récupération de la description textuelle
$description = isset($_POST['description']) ? trim($_POST['description']) : '';

// Validation minimale : On impose que la publication contienne au moins du texte OU une image
$hasImage = (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK);

if (empty($description) && !$hasImage) {
    sendJSONError("Votre publication ne peut pas être vide. Écrivez un texte ou ajoutez une image.", 400);
}

$imageName = null;

try {
    // 2. Traitement et sécurisation strict de l'image optionnelle
    if ($hasImage) {
        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName    = $_FILES['image']['name'];
        $fileSize    = $_FILES['image']['size'];
        
        // Validation A : Extensions autorisées
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            sendJSONError("Format d'image non supporté. Extensions valides : JPG, JPEG, PNG, GIF.", 400);
        }
        
        // Validation B : Inspecter le type MIME réel du fichier (Protection contre le code masqué)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMimeType = finfo_file($finfo, $fileTmpPath);
        finfo_close($finfo);
        
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($realMimeType, $allowedMimeTypes)) {
            sendJSONError("Le fichier téléversé n'est pas une image authentique.", 400);
        }
        
        // Validation C : Limitation de la taille (Plafond à 5 Mo pour les images de posts)
        $maxFileSize = 5 * 1024 * 1024;
        if ($fileSize > $maxFileSize) {
            sendJSONError("L'image est trop lourde. Taille maximale autorisée : 5 Mo.", 400);
        }
        
        // Génération d'un nom unique basé sur l'horodatage et l'ID utilisateur
        $imageName = 'post_' . $currentUser['id'] . '_' . time() . '.' . $fileExtension;
        
        // Définition du chemin cible dans les ressources du projet
        $uploadTargetDir = __DIR__ . '/../../assets/images/';
        $destPath = $uploadTargetDir . $imageName;
        
        // Création automatique du dossier s'il n'existe pas
        if (!is_dir($uploadTargetDir)) {
            mkdir($uploadTargetDir, 0755, true);
        }
        
        // Déplacement du fichier temporaire vers sa destination définitive
        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            sendJSONError("Une erreur interne est survenue lors du stockage de l'image.", 500);
        }
    }

    // 3. Persistance : Insertion du nouvel article dans la base de données MySQL
    $db = getDBConnection();
    
    // La requête lie l'article à l'utilisateur actuellement authentifié via la session
    $sql = "INSERT INTO articles (id_utilisateur, description, image, date_publication) 
            VALUES (:id_utilisateur, :description, :image, NOW())";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':id_utilisateur' => $currentUser['id'],
        ':description'    => htmlspecialchars($description), // Protection contre les failles XSS
        ':image'          => $imageName
    ]);
    
    // Récupération de l'ID de l'article fraîchement créé pour le renvoyer à la SPA
    $articleId = $db->lastInsertId();

    // 4. Envoi de la réponse JSON de succès
    sendJSONSuccess("Votre publication a été partagée avec succès !", [
        "article" => [
            "id" => intval($articleId),
            "id_utilisateur" => intval($currentUser['id']),
            "description" => $description,
            "image" => $imageName,
            "date_publication" => date("Y-m-d H:i:s")
        ]
    ], 210); // Code 201 Created pour signifier une création de ressource réussie

} catch (PDOException $e) {
    // Journalisation de l'anomalie SQL en interne
    error_log("Erreur Articles Create SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors de la publication de votre article.", 500);
}