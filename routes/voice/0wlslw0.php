<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/lib/http_json.php';
require_once dirname(__DIR__, 2) . '/lib/guide_voice.php';

$method = o_request_method();
if (in_array($method, ['GET', 'HEAD'], true)) {
    o_json_response(200, [
        'ok' => true,
        'state' => guide_voice_browser_state(current_authenticated_land()),
    ], [
        'cache_control' => '',
    ]);
}

if ($method !== 'POST') {
    o_allow_methods(['GET', 'HEAD', 'POST']);
    o_json_response(405, [
        'ok' => false,
        'error' => 'method_not_allowed',
    ], [
        'cache_control' => '',
    ]);
}

$raw = file_get_contents('php://input');
$payload = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($payload)) {
    o_json_response(400, [
        'ok' => false,
        'error' => 'invalid_json',
    ], [
        'cache_control' => '',
    ]);
}

$csrfToken = trim((string) ($payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
if (!verify_csrf_token($csrfToken)) {
    o_json_response(403, [
        'ok' => false,
        'error' => 'invalid_csrf',
        'reply' => 'La session vocale a expiré. Recharge la page puis réessaie.',
    ], [
        'cache_control' => '',
    ]);
}

$utterance = trim((string) ($payload['utterance'] ?? ''));
if ($utterance === '') {
    o_json_response(422, [
        'ok' => false,
        'error' => 'empty_utterance',
        'reply' => 'Je n’ai rien reçu. Réessaie avec une phrase courte.',
    ], [
        'cache_control' => '',
    ]);
}

try {
    enforce_rate_limit('0wlslw0-voice', 30, 300);
    $result = guide_voice_reply($utterance, current_authenticated_land());
    o_json_response(200, $result, [
        'cache_control' => '',
    ]);
} catch (Throwable $exception) {
    o_json_response(500, [
        'ok' => false,
        'error' => 'voice_backend_error',
        'reply' => 'Le passage vocal est brouillé pour le moment. Tu peux réessayer dans un instant.',
    ], [
        'cache_control' => '',
    ]);
}
