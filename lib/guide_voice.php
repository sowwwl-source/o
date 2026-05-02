<?php
declare(strict_types=1);

function guide_voice_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $endpoint = trim((string) (getenv('SOWWWL_0WLSLW0_AGENT_ENDPOINT') ?: ''));
    $authHeader = trim((string) (getenv('SOWWWL_0WLSLW0_AGENT_AUTH_HEADER') ?: 'Authorization'));
    $authScheme = trim((string) (getenv('SOWWWL_0WLSLW0_AGENT_AUTH_SCHEME') ?: 'Bearer'));
    $agentKey = trim((string) (getenv('SOWWWL_0WLSLW0_AGENT_KEY') ?: ''));
    $requestMode = strtolower(trim((string) (getenv('SOWWWL_0WLSLW0_AGENT_MODE') ?: 'chat')));
    $inputField = trim((string) (getenv('SOWWWL_0WLSLW0_AGENT_INPUT_FIELD') ?: 'message'));
    $timeout = (int) (getenv('SOWWWL_0WLSLW0_AGENT_TIMEOUT_SECONDS') ?: 18);
    $timeout = max(5, min($timeout, 45));

    if (!in_array($requestMode, ['chat', 'message'], true)) {
        $requestMode = 'chat';
    }

    $config = [
        'api_path' => '/0wlslw0/voice',
        'chat_url' => trim((string) ((getenv('SOWWWL_0WLSLW0_CHAT_URL') ?: getenv('SOWWWL_0WLSLW0_AGENT_URL')) ?: '')),
        'endpoint' => $endpoint,
        'agent_key' => $agentKey,
        'auth_header' => $authHeader !== '' ? $authHeader : 'Authorization',
        'auth_scheme' => $authScheme !== '' ? $authScheme : 'Bearer',
        'request_mode' => $requestMode,
        'input_field' => $inputField !== '' ? $inputField : 'message',
        'timeout' => $timeout,
    ];

    return $config;
}

function guide_voice_upstream_configured(): bool
{
    $config = guide_voice_config();
    return $config['endpoint'] !== '';
}

function guide_voice_mode_label(): string
{
    if (guide_voice_upstream_configured()) {
        return 'guide vocal relaye';
    }

    return 'guide vocal local';
}

function guide_voice_browser_state(?array $authenticatedLand = null): array
{
    $config = guide_voice_config();
    $landSlug = trim((string) ($authenticatedLand['slug'] ?? ''));

    return [
        'api_path' => (string) $config['api_path'],
        'csrf_token' => csrf_token(),
        'greeting' => guide_voice_default_greeting($authenticatedLand),
        'upstream_configured' => guide_voice_upstream_configured(),
        'chat_url' => (string) $config['chat_url'],
        'land_slug' => $landSlug,
    ];
}

function guide_voice_default_greeting(?array $authenticatedLand = null): string
{
    $slug = trim((string) ($authenticatedLand['slug'] ?? ''));
    if ($slug !== '') {
        return 'Je suis 0wlslw0. Je peux t’aider à retrouver ta terre, visiter publiquement, ou passer par Signal, Str3m, aZa ou Echo. Dis-moi ce que tu cherches.';
    }

    return 'Je suis 0wlslw0. Je peux t’aider à comprendre O., visiter sans compte, ou poser une terre. Dis-moi simplement ce que tu veux faire.';
}

function guide_voice_reply(string $utterance, ?array $authenticatedLand = null): array
{
    $normalizedUtterance = guide_voice_normalize_text($utterance);
    $local = guide_voice_local_reply($normalizedUtterance, $authenticatedLand);
    $remote = guide_voice_upstream_configured()
        ? guide_voice_remote_reply($normalizedUtterance, $authenticatedLand, $local)
        : null;

    $result = is_array($remote) ? $remote : $local;
    $result['reply'] = guide_voice_compact_reply_text((string) ($result['reply'] ?? ''));
    $result['route'] = guide_voice_normalize_route($result['route'] ?? null);
    $result['source'] = trim((string) ($result['source'] ?? 'local')) ?: 'local';
    $result['heard'] = $normalizedUtterance;
    $result['ok'] = true;

    guide_voice_store_turn($normalizedUtterance, $result['reply']);

    return $result;
}

function guide_voice_remote_reply(string $utterance, ?array $authenticatedLand, array $localFallback): ?array
{
    $config = guide_voice_config();
    $endpoint = (string) $config['endpoint'];
    if ($endpoint === '') {
        return null;
    }

    $history = guide_voice_recent_history();
    $messages = [
        [
            'role' => 'system',
            'content' => guide_voice_system_prompt(),
        ],
    ];

    foreach ($history as $turn) {
        $user = trim((string) ($turn['user'] ?? ''));
        $assistant = trim((string) ($turn['assistant'] ?? ''));
        if ($user !== '') {
            $messages[] = ['role' => 'user', 'content' => $user];
        }
        if ($assistant !== '') {
            $messages[] = ['role' => 'assistant', 'content' => $assistant];
        }
    }

    $messages[] = ['role' => 'user', 'content' => $utterance];

    $context = [
        'origin' => site_origin(),
        'authenticated_land_slug' => trim((string) ($authenticatedLand['slug'] ?? '')),
        'route_map' => [
            'home' => '/',
            'create_land' => '/#poser',
            'signal' => '/signal',
            'str3m' => '/str3m',
            'aza' => '/aza',
            'echo' => '/echo',
            'guide' => '/0wlslw0',
        ],
        'voice_only' => true,
    ];

    if ($config['request_mode'] === 'message') {
        $payload = [
            (string) $config['input_field'] => $utterance,
            'session_id' => session_id(),
            'history' => $history,
            'context' => $context,
            'system' => guide_voice_system_prompt(),
        ];
    } else {
        $payload = [
            'session_id' => session_id(),
            'messages' => $messages,
            'context' => $context,
        ];
    }

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'User-Agent: sowwwl-0wlslw0-voice/1.0',
    ];

    if ($config['agent_key'] !== '') {
        $headers[] = $config['auth_header'] . ': ' . trim($config['auth_scheme'] . ' ' . $config['agent_key']);
    }

    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($body)) {
        return null;
    }

    $contextResource = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => (int) $config['timeout'],
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($endpoint, false, $contextResource);
    $responseHeaders = $http_response_header ?? [];
    $statusCode = guide_voice_http_status($responseHeaders);
    if ($statusCode < 200 || $statusCode >= 300 || !is_string($raw) || trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    $reply = guide_voice_extract_text($decoded);
    if ($reply === '' && is_string($raw)) {
        $reply = guide_voice_compact_reply_text($raw);
    }

    if ($reply === '') {
        return null;
    }

    $route = guide_voice_extract_route($decoded);
    if ($route === null && !empty($localFallback['route'])) {
        $route = $localFallback['route'];
    }

    return [
        'reply' => $reply,
        'route' => $route,
        'source' => 'remote',
    ];
}

function guide_voice_http_status(array $headers): int
{
    $statusLine = (string) ($headers[0] ?? '');
    if (preg_match('/\s(\d{3})\s/', $statusLine, $matches) === 1) {
        return (int) $matches[1];
    }

    return 0;
}

function guide_voice_extract_text(mixed $payload): string
{
    if (is_string($payload)) {
        return guide_voice_compact_reply_text($payload);
    }

    if (!is_array($payload)) {
        return '';
    }

    $priorityKeys = ['output_text', 'answer', 'response', 'reply', 'text', 'message', 'content'];
    foreach ($priorityKeys as $key) {
        if (!array_key_exists($key, $payload)) {
            continue;
        }

        $text = guide_voice_extract_text($payload[$key]);
        if ($text !== '') {
            return $text;
        }
    }

    if (isset($payload['choices']) && is_array($payload['choices'])) {
        foreach ($payload['choices'] as $choice) {
            $text = guide_voice_extract_text($choice);
            if ($text !== '') {
                return $text;
            }
        }
    }

    if (isset($payload['messages']) && is_array($payload['messages'])) {
        foreach ($payload['messages'] as $message) {
            if (is_array($message) && (($message['role'] ?? '') === 'assistant')) {
                $text = guide_voice_extract_text($message['content'] ?? null);
                if ($text !== '') {
                    return $text;
                }
            }
        }
    }

    foreach ($payload as $value) {
        $text = guide_voice_extract_text($value);
        if ($text !== '') {
            return $text;
        }
    }

    return '';
}

function guide_voice_extract_route(mixed $payload): ?array
{
    if (!is_array($payload)) {
        return null;
    }

    $candidate = $payload['route'] ?? null;
    if (is_array($candidate) && !empty($candidate['href'])) {
        return guide_voice_normalize_route($candidate);
    }

    foreach (['href', 'url', 'cta_url'] as $key) {
        $href = trim((string) ($payload[$key] ?? ''));
        if ($href !== '') {
            return guide_voice_normalize_route([
                'href' => $href,
                'label' => (string) ($payload['label'] ?? $payload['cta'] ?? 'Aller plus loin'),
                'auto_navigate' => false,
            ]);
        }
    }

    foreach ($payload as $value) {
        $route = guide_voice_extract_route($value);
        if ($route !== null) {
            return $route;
        }
    }

    return null;
}

function guide_voice_local_reply(string $utterance, ?array $authenticatedLand = null): array
{
    $text = guide_voice_normalize_text($utterance);
    $slug = trim((string) ($authenticatedLand['slug'] ?? ''));

    if ($text === '') {
        return [
            'reply' => 'Je n’ai pas bien entendu. Dis par exemple comprendre le projet, visiter publiquement, ou poser une terre.',
            'route' => null,
            'source' => 'local',
        ];
    }

    if (guide_voice_contains($text, ['merci', 'a bientot', 'à bientot', 'au revoir', 'bye'])) {
        return [
            'reply' => 'Je reste au passage si tu veux reprendre plus tard. Tu peux revenir quand tu veux.',
            'route' => null,
            'source' => 'local',
        ];
    }

    if (guide_voice_contains($text, ['signal', 'message', 'messagerie', 'mailbox', 'inbox', 'courrier', 'boite'])) {
        return guide_voice_build_route_reply(
            'Signal est la boite situee de chaque terre. Tu y passes pour lire, ecrire, et valider une identite legere.',
            '/signal',
            'Aller vers Signal',
            $text
        );
    }

    if (guide_voice_contains($text, ['str3m', 'stream', 'public', 'visiter', 'voir', 'observer', 'decouvrir', 'découvrir'])) {
        return guide_voice_build_route_reply(
            'Str3m te laisse sentir le projet publiquement, sans poser de terre tout de suite. C’est la meilleure porte pour regarder avant de t’engager.',
            '/str3m',
            'Aller vers Str3m',
            $text
        );
    }

    if (guide_voice_contains($text, ['aza', 'archive', 'archives', 'memoire', 'mémoire', 'souvenir'])) {
        return guide_voice_build_route_reply(
            'aZa est la couche de memoire et d’archives. Tu peux y lire publiquement ce qui a deja ete depose.',
            '/aza',
            'Lire aZa',
            $text
        );
    }

    if (guide_voice_contains($text, ['echo', 'écho', 'direct', 'resonance', 'résonance'])) {
        return guide_voice_build_route_reply(
            'Echo sert aux resonances directes entre terres. Ce n’est pas un mur public, mais un passage plus adresse.',
            '/echo',
            'Aller vers Echo',
            $text
        );
    }

    if (guide_voice_contains($text, ['poser une terre', 'creer une terre', 'créer une terre', 'creation', 'création', 'inscription', 'commencer', 'entrer'])) {
        return guide_voice_build_route_reply(
            'Pour entrer vraiment, il faut poser une terre. Tu choisis un nom, un fuseau, puis tu reçois ton ancrage dans O.',
            '/#poser',
            'Poser une terre',
            $text
        );
    }

    if (guide_voice_contains($text, ['retrouver ma terre', 'rouvrir ma terre', 'ouvrir ma terre', 'ma terre', 'terre existante', 'revenir'])) {
        if ($slug !== '') {
            return guide_voice_build_route_reply(
                'Ta terre est deja liee ici. Je peux te renvoyer directement vers ton espace.',
                '/land.php?u=' . rawurlencode($slug),
                'Ouvrir ma terre',
                $text
            );
        }

        return guide_voice_build_route_reply(
            'Je ne vois pas de terre liee dans cette session. Repars du noyau pour te reconnecter ou relancer la creation.',
            '/',
            'Retour au noyau',
            $text
        );
    }

    if (guide_voice_contains($text, ['comprendre', 'c est quoi', 'c’est quoi', 'que fais tu', 'qui es tu', 'qui es-tu', 'explique', 'projet'])) {
        return [
            'reply' => 'O. est un ensemble de terres et de portes. Je suis ici pour clarifier le projet, identifier ton intention, puis t’emmener vers la bonne page sans te perdre dans le decor.',
            'route' => null,
            'source' => 'local',
        ];
    }

    return [
        'reply' => 'Je peux t’aider a comprendre O., visiter publiquement, poser une terre, ou rejoindre Signal, Str3m, aZa ou Echo. Dis-moi juste la porte que tu veux.',
        'route' => null,
        'source' => 'local',
    ];
}

function guide_voice_build_route_reply(string $reply, string $href, string $label, string $utterance): array
{
    return [
        'reply' => $reply,
        'route' => [
            'href' => $href,
            'label' => $label,
            'auto_navigate' => guide_voice_should_auto_navigate($utterance),
        ],
        'source' => 'local',
    ];
}

function guide_voice_should_auto_navigate(string $utterance): bool
{
    return preg_match('/\b(go|vas-y|ouvre|ouvrir|emmene|emmène|amene|amène|mene|mène|allons|conduis|direction|direct)\b/u', $utterance) === 1;
}

function guide_voice_contains(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if ($needle !== '' && str_contains($haystack, guide_voice_normalize_text($needle))) {
            return true;
        }
    }

    return false;
}

function guide_voice_normalize_text(string $value): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function guide_voice_compact_reply_text(string $value): string
{
    $value = strip_tags($value);
    $value = preg_replace('/[`*_#>]+/', '', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (function_exists('mb_substr') && function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > 420) {
        return rtrim(mb_substr($value, 0, 417, 'UTF-8')) . '…';
    }

    if (strlen($value) > 420) {
        return rtrim(substr($value, 0, 417)) . '...';
    }

    return $value;
}

function guide_voice_normalize_route(mixed $route): ?array
{
    if (!is_array($route)) {
        return null;
    }

    $href = trim((string) ($route['href'] ?? ''));
    if ($href === '') {
        return null;
    }

    if (!preg_match('~^(?:/|https?://)~i', $href)) {
        $href = '/' . ltrim($href, '/');
    }

    return [
        'href' => $href,
        'label' => trim((string) ($route['label'] ?? 'Continuer')) ?: 'Continuer',
        'auto_navigate' => !empty($route['auto_navigate']),
    ];
}

function guide_voice_recent_history(): array
{
    $history = $_SESSION['guide_voice_history'] ?? [];
    if (!is_array($history)) {
        return [];
    }

    $sanitized = [];
    foreach ($history as $turn) {
        if (!is_array($turn)) {
            continue;
        }

        $user = guide_voice_compact_reply_text((string) ($turn['user'] ?? ''));
        $assistant = guide_voice_compact_reply_text((string) ($turn['assistant'] ?? ''));
        if ($user === '' && $assistant === '') {
            continue;
        }

        $sanitized[] = [
            'user' => $user,
            'assistant' => $assistant,
        ];
    }

    return array_slice($sanitized, -6);
}

function guide_voice_store_turn(string $user, string $assistant): void
{
    $history = guide_voice_recent_history();
    $history[] = [
        'user' => guide_voice_compact_reply_text($user),
        'assistant' => guide_voice_compact_reply_text($assistant),
    ];

    $_SESSION['guide_voice_history'] = array_slice($history, -6);
}

function guide_voice_system_prompt(): string
{
    static $prompt = null;

    if (is_string($prompt)) {
        return $prompt;
    }

    $agentPath = dirname(__DIR__) . '/0wlslw0_AGENT.md';
    $knowledgePath = dirname(__DIR__) . '/0wlslw0_KNOWLEDGE.md';
    $playbookPath = dirname(__DIR__) . '/0wlslw0_VOICE_PLAYBOOK.md';
    $agent = is_readable($agentPath) ? trim((string) file_get_contents($agentPath)) : '';
    $knowledge = is_readable($knowledgePath) ? trim((string) file_get_contents($knowledgePath)) : '';
    $playbook = is_readable($playbookPath) ? trim((string) file_get_contents($playbookPath)) : '';

    $prompt = trim(implode("\n\n", array_filter([
        'You are 0wlslw0, the voice-only guide for O.',
        'Reply in French, in a calm and precise tone.',
        'Keep answers short enough to be spoken aloud comfortably, ideally under three sentences.',
        'Do not use markdown, bullets, or code formatting in your final reply.',
        'If the visitor intent is clear, orient them toward one primary route only.',
        'Never invent permissions, signup completion, or private access.',
        $agent,
        $knowledge,
        $playbook,
    ])));

    return $prompt;
}
