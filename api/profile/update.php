<?php
// Endpoint API - Mettre à jour les informations du profil et l'avatar
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';

// 1. Sécurité : Vérifier que l'utilisateur est connecté
$currentUser = requireLogin();

// Sécurité : Autoriser uniquement la méthode POST (obligatoire pour l'upload de fichiers via FormData)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError("Méthode non autorisée. POST requis.", 405);
}

// Récupération des données textuelles du formulaire (FormData standard)
$nom    = isset($_POST['nom']) ? trim($_POST['nom']) : '';
$prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';

// Validation de la présence des informations obligatoires
if (empty($nom) || empty($prenom)) {
    sendJSONError("Le nom et le prénom ne peuvent pas être vides.");
}

try {
    // Connexion à la base de données
    $db = getDBConnection();
    
    // Récupération de l'avatar actuel en base au cas où aucun nouveau fichier n'est fourni
    $stmtCurrent = $db->prepare("SELECT avatar FROM utilisateurs WHERE id = :id LIMIT 1");
    $stmtCurrent->execute([':id' => $currentUser['id']]);
    $userRow = $stmtCurrent->fetch();
    $avatarName = $userRow ? $userRow['avatar'] : 'default-avatar.png';

    // 2. Traitement et sécurisation du téléversement de l'avatar (si fourni)
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['avatar']['tmp_name'];
        $fileName    = $_FILES['avatar']['name'];
        $fileSize    = $_FILES['avatar']['size'];
        
        // Validation 1 : Vérification de l'extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            sendJSONError("Format d'image invalide. Extensions autorisées : JPG, JPEG, PNG, GIF.");
        }
        
        // Validation 2 : Vérification du type MIME réel (anti-falsification)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMimeType = finfo_file($finfo, $fileTmpPath);
        finfo_close($finfo);
        
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($realMimeType, $allowedMimeTypes)) {
            sendJSONError("Le fichier fourni n'est pas une image valide.");
        }
        
        // Validation 3 : Limitation de la taille (Exemple : 2 Mo maximum)
        $maxFileSize = 2 * 1024 * 1024;
        if ($fileSize > $maxFileSize) {
            sendJSONError("L'image est trop volumineuse. Taille maximale autorisée : 2 Mo.");
        }
        
        // Génération d'un nom de fichier unique et nettoyé pour éviter les injections de chemins
        $avatarName = 'avatar_' . $currentUser['id'] . '_' . time() . '.' . $fileExtension;
        
        // Chemin physique absolu vers le dossier assets/images/ du projet
        $uploadTargetDir = __DIR__ . '/../../assets/images/';
        $destPath = $uploadTargetDir . $avatarName;
        
        // Création du dossier s'il n'existe pas encore sur le serveur
        if (!is_dir($uploadTargetDir)) {
            mkdir($uploadTargetDir, 0755, true);
        }
        
        // Déplacement effectif du fichier temporaire vers la destination finale
        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            sendJSONError("Une erreur est survenue lors de l'enregistrement de l'image sur le serveur.");
        }
        
        // Optionnel : Supprimer l'ancien avatar du serveur s'il ne s'agit pas de l'image par défaut
        if ($userRow && !empty($userRow['avatar']) && $userRow['avatar'] !== 'default-avatar.png') {
            $oldAvatarPath = $uploadTargetDir . $userRow['avatar'];
            if (file_exists($oldAvatarPath)) {
                @unlink($oldAvatarPath);
            }
        }
    }

    // 3. Persistance : Mise à jour des informations dans la base de données MySQL
    $sql = "UPDATE utilisateurs 
            SET nom = :nom, prenom = :prenom, avatar = :avatar 
            WHERE id = :id";
            
    $stmtUpdate = $db->prepare($sql);
    $stmtUpdate->execute([
        ':nom'    => $nom,
        ':prenom' => $prenom,
        ':avatar' => $avatarName,
        ':id'     => $currentUser['id']
    ]);

    // 4. Synchronisation : Mise à jour des variables de la session active en mémoire
    $_SESSION['nom']    = $nom;
    $_SESSION['prenom'] = $prenom;
    $_SESSION['avatar'] = $avatarName;

    // 5. Envoi de la réponse JSON de succès avec les données fraîches
    sendJSONSuccess("Votre profil a été mis à jour avec succès !", [
        "user" => [
            "id"     => intval($currentUser['id']),
            "nom"    => $nom,
            "prenom" => $prenom,
            "email"  => $currentUser['email'],
            "role"   => $currentUser['role'],
            "avatar" => $avatarName
        ]
    ]);

} catch (PDOException $e) {
    // Journalisation interne de l'anomalie SQL
    error_log("Erreur Profile Update SQL : " . $e->getMessage());
    sendJSONError("Une erreur technique est survenue lors de la mise à jour de votre profil.", 500);
}