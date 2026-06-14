<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/lib/http_json.php';
require_once dirname(__DIR__, 2) . '/lib/guide_voice.php';

if (o_request_method() !== 'POST') {
    o_allow_methods(['POST']);
    o_json_response(405, [
        'error' => 'method_not_allowed',
        'message' => 'Méthode non autorisée. Utilisez POST.',
    ]);
}

$raw = file_get_contents('php://input');
$payload = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($payload)) {
    o_json_response(400, [
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
    o_json_response(403, [
        'error' => 'invalid_csrf',
        'message' => 'Token CSRF invalide ou expiré.',
        'reply' => 'La session vocale a expiré. Recharge la page puis réessaie.',
    ]);
}

$utterance = trim((string) ($payload['message'] ?? $payload['utterance'] ?? ''));
if ($utterance === '') {
    o_json_response(400, [
        'error' => 'empty_message',
        'message' => 'Le message est vide ou mal formaté.',
    ]);
}

try {
    enforce_rate_limit('0wlslw0-voice', 30, 300);
    $result = guide_voice_reply($utterance, current_authenticated_land());

    o_json_response(200, [
        'status' => 'success',
        'reply' => trim((string) ($result['reply'] ?? '')),
        'route' => $result['route'] ?? null,
        'suggestions' => $result['suggestions'] ?? [],
        'source' => $result['source'] ?? 'local',
        'legacy' => true,
    ]);
} catch (Throwable $exception) {
    o_json_response(502, [
        'error' => 'voice_backend_error',
        'message' => 'La liaison avec la matrice cognitive est brouillée.',
        'reply' => 'Le passage vocal est brouillé pour le moment. Tu peux réessayer dans un instant.',
    ]);
}
