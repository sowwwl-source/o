<?php
// Fichier : /Users/pabloespallergues/Downloads/O_installation_FRESH/api/get_user_media.php

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

// Démarrer la session sécurisée via la fonction de ton config.php
start_secure_session();

// 1. Authentification
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit();
}

$username = $_SESSION['username'];
global $pdo;

try {
    // 2. Récupérer l'ID (entier) de l'utilisateur à partir de son nom (username)
    $stmt_user = $pdo->prepare("SELECT id FROM lands WHERE username = :username");
    $stmt_user->execute([':username' => $username]);
    $user_id = $stmt_user->fetchColumn();

    if (!$user_id) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User land not found.']);
        exit();
    }

    // 3. Récupérer tous les médias de cet utilisateur
    $stmt_media = $pdo->prepare("
        SELECT uuid, media_type, file_path, title, is_public, placement_json, created_at 
        FROM user_media 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC
    ");
    $stmt_media->execute([':user_id' => $user_id]);
    $media_list = $stmt_media->fetchAll();

    // 4. Décoder le JSON du placement pour renvoyer un véritable objet JS (et non une string)
    foreach ($media_list as &$media) {
        if (!empty($media['placement_json'])) {
            $media['placement_json'] = json_decode($media['placement_json'], true);
        }
    }

    // 5. Renvoyer la réponse
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $media_list
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in get_user_media.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An internal database error occurred.']);
}
?>