<?php
// Helpers de Réponses HTTP/JSON

// Gestion globale et automatique des requêtes de Preflight CORS (OPTIONS)
// Requis pour les requêtes asynchrones Fetch/Axios avec en-têtes personnalisés (Authorization)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("HTTP/1.1 204 No Content");
    exit();
}

/**
 * Envoie une réponse JSON formatée avec un code d'état HTTP
 */
function sendJSONResponse($data, $statusCode = 200) {
    // Nettoyage des tampons de sortie précédents pour éviter des caractères indésirables
    if (ob_get_length()) ob_clean();
    
    // En-têtes HTTP
    header("Content-Type: application/json; charset=utf-8");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Envoie une erreur formatée en JSON
 */
function sendJSONError($message, $statusCode = 400) {
    sendJSONResponse([
        "status" => "error",
        "message" => $message
    ], $statusCode);
}

/**
 * Envoie un succès formaté en JSON
 */
function sendJSONSuccess($message, $data = [], $statusCode = 200) {
    $response = [
        "status" => "success",
        "message" => $message
    ];
    if (!empty($data)) {
        $response["data"] = $data;
    }
    sendJSONResponse($response, $statusCode);
}