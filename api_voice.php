<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/guide_voice.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function legacy_voice_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Allow: POST');
    legacy_voice_json(405, [
        'error' => 'method_not_allowed',
        'message' => 'Méthode non autorisée. Utilisez POST.',
    ]);
}

$raw = file_get_contents('php://input');
$payload = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($payload)) {
    legacy_voice_json(400, [
        'error' => 'invalid_json',
        'message' => 'Le message est vide ou mal formaté.',
    ]);
}

$csrfToken = trim((string) (
    $payload['csrf_token']
    ?? $_SERVER['HTTP_X_CSRF_TOKEN']
    ?? ''
));
if (!verify_csrf_token($csrfToken)) {
    legacy_voice_json(403, [
        'error' => 'invalid_csrf',
        'message' => 'Token CSRF invalide ou expiré.',
        'reply' => 'La session vocale a expiré. Recharge la page puis réessaie.',
    ]);
}

$utterance = trim((string) ($payload['message'] ?? $payload['utterance'] ?? ''));
if ($utterance === '') {
    legacy_voice_json(400, [
        'error' => 'empty_message',
        'message' => 'Le message est vide ou mal formaté.',
    ]);
}

try {
    enforce_rate_limit('0wlslw0-voice', 30, 300);
    $result = guide_voice_reply($utterance, current_authenticated_land());

    legacy_voice_json(200, [
        'status' => 'success',
        'reply' => trim((string) ($result['reply'] ?? '')),
        'route' => $result['route'] ?? null,
        'suggestions' => $result['suggestions'] ?? [],
        'source' => $result['source'] ?? 'local',
        'legacy' => true,
    ]);
} catch (Throwable $exception) {
    legacy_voice_json(502, [
        'error' => 'voice_backend_error',
        'message' => 'La liaison avec la matrice cognitive est brouillée.',
        'reply' => 'Le passage vocal est brouillé pour le moment. Tu peux réessayer dans un instant.',
    ]);
}
