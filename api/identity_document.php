async function askAI(prompt) {
  const response = await fetch('https://s3f3wvw4cnmqbszk26zsan2m.agents.do-ai.run', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer X1LXjMN7plD4yOfe5Xv9RgLxjflesAQ1'
    },
    body: JSON.stringify({ prompt })
  });
  const data = await response.json();
  return data.completion || data.result || data; // adapte selon la structure de la réponse
}

// Exemple d’utilisation :
askAI("Bonjour, qui es-tu ?").then(console.log);<?php
// identity_document.php : upload et gestion du statut du document d'identité
// POST /api/identity_document.php?action=upload
// Champs attendus : user_id, doc_type, fichier (multipart/form-data)

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'upload') {
    // Authentification à ajouter selon votre logique
    $user_id = intval($_POST['user_id'] ?? 0);
    $doc_type = $_POST['doc_type'] ?? 'other';
    if (!$user_id || !isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'user_id et fichier requis']);
        exit;
    }
    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','pdf'];
    if (!in_array($ext, $allowed)) {
        http_response_code(400);
        echo json_encode(['error' => 'Format non supporté']);
        exit;
    }
    $dir = __DIR__ . '/../sowwwl_assets/identity_docs/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $fname = 'doc_' . $user_id . '_' . time() . '.' . $ext;
    $dest = $dir . $fname;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur upload']);
        exit;
    }
    $rel_path = 'sowwwl_assets/identity_docs/' . $fname;
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO identity_documents (user_id, file_path, doc_type) VALUES (?, ?, ?)');
    $stmt->execute([$user_id, $rel_path, $doc_type]);
    echo json_encode(['success' => true, 'file' => $rel_path]);
    exit;
}

if ($action === 'status') {
    $user_id = intval($_GET['user_id'] ?? 0);
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['error' => 'user_id requis']);
        exit;
    }
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM identity_documents WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 1');
    $stmt->execute([$user_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doc) {
        echo json_encode(['status' => 'none']);
    } else {
        echo json_encode(['status' => $doc['status'], 'doc' => $doc]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'action invalide']);
