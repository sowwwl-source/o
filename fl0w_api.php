<?php
require __DIR__ . '/config.php';

start_secure_session();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['username'])) {
    echo json_encode(['ok' => false, 'error' => 'unauthenticated']);
    exit;
}

$username = $_SESSION['username'];
$method   = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $stmt = $pdo->prepare("
            SELECT f.slug, f.name, f.created_at,
                   GROUP_CONCAT(fs.land_username ORDER BY fs.position SEPARATOR ',') AS steps_csv
            FROM flows f
            JOIN flow_steps fs ON fs.flow_id = f.id
            WHERE f.username = ?
            GROUP BY f.id
            ORDER BY f.created_at DESC
        ");
        $stmt->execute([$username]);
        $rows = $stmt->fetchAll();
        $flows = array_map(fn($r) => [
            'slug'  => $r['slug'],
            'name'  => $r['name'],
            'steps' => explode(',', $r['steps_csv']),
        ], $rows);
        echo json_encode(['ok' => true, 'flows' => $flows]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'unknown_action']);
    exit;
}

// ── POST ─────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        echo json_encode(['ok' => false, 'error' => 'invalid_json']);
        exit;
    }

    $csrf = (string)($data['csrf_token'] ?? '');
    if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrf)) {
        echo json_encode(['ok' => false, 'error' => 'csrf']);
        exit;
    }

    $action = $data['action'] ?? '';

    if ($action === 'save') {
        $name  = trim((string)($data['name'] ?? '')) ?: null;
        $steps = $data['steps'] ?? [];

        if (!is_array($steps) || count($steps) === 0 || count($steps) > 20) {
            echo json_encode(['ok' => false, 'error' => 'invalid_steps']);
            exit;
        }

        $slug = bin2hex(random_bytes(6));
        $pdo->prepare("INSERT INTO flows (slug, username, name) VALUES (?, ?, ?)")
            ->execute([$slug, $username, $name]);
        $flow_id = (int)$pdo->lastInsertId();

        $stmtStep = $pdo->prepare("INSERT INTO flow_steps (flow_id, position, land_username) VALUES (?, ?, ?)");
        foreach (array_values($steps) as $i => $land) {
            $stmtStep->execute([$flow_id, $i + 1, (string)$land]);
        }

        echo json_encode(['ok' => true, 'slug' => $slug]);
        exit;
    }

    if ($action === 'delete') {
        $slug = (string)($data['slug'] ?? '');
        $stmt = $pdo->prepare("SELECT id FROM flows WHERE slug = ? AND username = ?");
        $stmt->execute([$slug, $username]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare("DELETE FROM flow_steps WHERE flow_id = ?")->execute([$row['id']]);
            $pdo->prepare("DELETE FROM flows WHERE id = ?")->execute([$row['id']]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }
}

echo json_encode(['ok' => false, 'error' => 'unknown']);
