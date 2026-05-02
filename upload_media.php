<?php
// Fichier : /Users/pabloespallergues/Downloads/O_installation_FRESH/api/upload_media.php

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

// --- Configuration de l'upload ---
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50 MB
define('ALLOWED_MIME_TYPES', [
    'image/jpeg' => 'image',
    'image/png' => 'image',
    'image/gif' => 'image',
    'image/webp' => 'image',
    'video/mp4' => 'video',
    'video/webm' => 'video',
    'audio/mpeg' => 'audio',
    'audio/mp3' => 'audio',
    'audio/wav' => 'audio',
    'audio/ogg' => 'audio',
]);

// 1. Authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit();
}
$user_id = $_SESSION['user_id'];

// 2. Validation de la requête
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests are allowed.']);
    exit();
}

if (!isset($_FILES['media_file']) || !is_uploaded_file($_FILES['media_file']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded or invalid upload.']);
    exit();
}

$file = $_FILES['media_file'];

// 3. Validation du fichier
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'File upload error: ' . $file['error']]);
    exit();
}

if ($file['size'] > MAX_FILE_SIZE) {
    http_response_code(413); // Payload Too Large
    echo json_encode(['status' => 'error', 'message' => 'File is too large. Max size is ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB.']);
    exit();
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($file['tmp_name']);

if (!array_key_exists($mime_type, ALLOWED_MIME_TYPES)) {
    http_response_code(415); // Unsupported Media Type
    echo json_encode(['status' => 'error', 'message' => 'Unsupported file type: ' . $mime_type]);
    exit();
}

$media_type = ALLOWED_MIME_TYPES[$mime_type];

// 4. Préparation du stockage
$user_upload_dir = UPLOAD_DIR . '/' . $user_id;
if (!is_dir($user_upload_dir)) {
    if (!mkdir($user_upload_dir, 0755, true)) {
        http_response_code(500);
        error_log("Failed to create upload directory: " . $user_upload_dir);
        echo json_encode(['status' => 'error', 'message' => 'Server error: could not create storage directory.']);
        exit();
    }
}

$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$safe_filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
$destination_path = $user_upload_dir . '/' . $safe_filename;
$relative_path = 'uploads/' . $user_id . '/' . $safe_filename;

// 5. Génération de l'UUID
$uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

$title = trim($_POST['title'] ?? pathinfo($file['name'], PATHINFO_FILENAME));

// 6. Opérations Base de Données et Fichier (Transaction)
$pdo = get_db_connection();
try {
    $pdo->beginTransaction();

    // S'assurer que l'utilisateur a un espace 3D (conteneur)
    $stmt = $pdo->prepare("SELECT id FROM user_3d_spaces WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $space_id = $stmt->fetchColumn();

    if (!$space_id) {
        // Crée un conteneur par défaut si aucun n'existe
        $stmt_create_space = $pdo->prepare("INSERT INTO user_3d_spaces (user_id) VALUES (:user_id)");
        $stmt_create_space->execute([':user_id' => $user_id]);
        $space_id = $pdo->lastInsertId();
    }

    // Déplacer le fichier
    if (!move_uploaded_file($file['tmp_name'], $destination_path)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    // Insérer les métadonnées du média
    $stmt_insert = $pdo->prepare(
        "INSERT INTO user_media (user_id, space_id, uuid, media_type, file_path, title) 
         VALUES (:user_id, :space_id, :uuid, :media_type, :file_path, :title)"
    );
    $stmt_insert->execute([
        ':user_id' => $user_id,
        ':space_id' => $space_id,
        ':uuid' => $uuid,
        ':media_type' => $media_type,
        ':file_path' => $relative_path,
        ':title' => $title,
    ]);

    $pdo->commit();

    // 7. Réponse de succès
    http_response_code(201); // Created
    echo json_encode([
        'status' => 'success',
        'message' => 'Media uploaded successfully.',
        'data' => [
            'uuid' => $uuid,
            'type' => $media_type,
            'path' => $relative_path,
            'title' => $title,
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    if (file_exists($destination_path)) {
        unlink($destination_path); // Nettoyer le fichier si la DB a échoué
    }
    http_response_code(500);
    error_log("Upload failed: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An internal error occurred.']);
}
?>