<?php
// Fichier : /Users/pabloespallergues/Downloads/O_installation_FRESH/api/save_media_placement.php

header('Content-Type: application/json');

// 1. Inclusion de la configuration et démarrage de la session
// On suppose que config.php gère session_start() et la connexion à la base de données.
// Le chemin est relatif à ce fichier API.
require_once __DIR__ . '/../config.php';

// Vérification de l'authentification de l'utilisateur
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Non autorisé
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// 2. Validation de la méthode de requête HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Méthode non autorisée
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests are allowed.']);
    exit();
}

// 3. Récupération et validation des données d'entrée
$input = json_decode(file_get_contents('php://input'), true);

$media_uuid = $input['media_uuid'] ?? null;
$placement_json = $input['placement_json'] ?? null;

if (!$media_uuid || $placement_json === null) { // placement_json peut être un objet vide, donc on vérifie null
    http_response_code(400); // Mauvaise requête
    echo json_encode(['status' => 'error', 'message' => 'Missing media_uuid or placement_json.']);
    exit();
}

// Validation basique du format UUID
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $media_uuid)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid media_uuid format.']);
    exit();
}

// Validation que placement_json est un JSON valide
$placement_json_string = json_encode($placement_json);
if ($placement_json_string === false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid placement_json format or failed to encode.']);
    exit();
}

// 4. Mise à jour de la base de données
try {
    $pdo = get_db_connection(); // On suppose que cette fonction est définie dans config.php

    $stmt = $pdo->prepare("UPDATE user_media SET placement_json = :placement_json WHERE uuid = :media_uuid AND user_id = :user_id");
    $stmt->bindParam(':placement_json', $placement_json_string, PDO::PARAM_STR);
    $stmt->bindParam(':media_uuid', $media_uuid, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Media placement updated successfully.']);
    } else {
        http_response_code(404); // Non trouvé ou non possédé par l'utilisateur
        echo json_encode(['status' => 'error', 'message' => 'Media not found or you do not own this media.']);
    }

} catch (PDOException $e) {
    http_response_code(500); // Erreur interne du serveur
    error_log("Database error in save_media_placement.php: " . $e->getMessage()); // Log l'erreur pour le débogage
    echo json_encode(['status' => 'error', 'message' => 'Database error.']); // Message générique pour l'utilisateur
}

?>