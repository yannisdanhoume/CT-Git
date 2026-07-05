<?php
// Configuration de la Base de Données

// On inclut response.php pour harmoniser le format des erreurs de l'API
require_once __DIR__ . '/../helpers/response.php';

define('DB_HOST', 'localhost');
define('DB_NAME', 'social_db');
define('DB_USER', 'root');
define('DB_PASS', ''); // Par défaut vide sur XAMPP / Laravel Herd

/**
 * Configure et retourne une instance globale de PDO connectée à MySQL
 */
function getDBConnection() {
    static $db = null;
    
    if ($db === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Active les exceptions
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // Utilise les vraies requêtes préparées de MySQL (Sécurité Injection SQL)
            ];
            $db = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Sécurité : On masque les détails techniques internes au client lambda
            // Mais on enregistre l'erreur réelle dans les logs du serveur pour le débogage
            error_log("Erreur de connexion BDD : " . $e->getMessage());
            
            // Réutilisation de notre helper standardisé avec un code 500 (Internal Server Error)
            sendJSONError("Une erreur interne est survenue lors de la connexion aux données.", 500);
        }
    }
    
    return $db;
}