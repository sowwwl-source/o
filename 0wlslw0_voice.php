<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/guide_voice.php';

header('Content-Type: application/json; charset=UTF-8');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'method_not_allowed',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$payload = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'invalid_json',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$csrfToken = trim((string) ($payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'invalid_csrf',
        'reply' => 'La session vocale a expiré. Recharge la page puis réessaie.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$utterance = trim((string) ($payload['utterance'] ?? ''));
if ($utterance === '') {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'error' => 'empty_utterance',
        'reply' => 'Je n’ai rien reçu. Réessaie avec une phrase courte.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    enforce_rate_limit('0wlslw0-voice', 30, 300);
    $result = guide_voice_reply($utterance, current_authenticated_land());

    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'voice_backend_error',
        'reply' => 'Le passage vocal est brouillé pour le moment. Tu peux réessayer dans un instant.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
