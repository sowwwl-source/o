<?php
declare(strict_types=1);

function plasma_bridge_url(): string
{
    return sowwwl_runtime_url('SOWWWL_MEMBRANE_BRIDGE_URL', '/ingest/membrane');
}

function plasma_feed_url(): string
{
    return sowwwl_runtime_url('SOWWWL_PLASMA_FEED_URL', '/plasma/recent');
}

function pocket_camera_stream_url(?string $cameraSlug = null): string
{
    $slug = trim((string) ($cameraSlug ?? pocket_camera_slug()));
    $slug = $slug !== '' ? $slug : pocket_camera_slug();

    return sowwwl_runtime_href('SOWWWL_PI_CAMERA_STREAM_URL', '/camera/' . rawurlencode($slug) . '/stream.mjpg');
}

function pocket_camera_snapshot_url(?string $cameraSlug = null): string
{
    $slug = trim((string) ($cameraSlug ?? pocket_camera_slug()));
    $slug = $slug !== '' ? $slug : pocket_camera_slug();

    return sowwwl_runtime_href('SOWWWL_PI_CAMERA_SNAPSHOT_URL', '/camera/' . rawurlencode($slug) . '/snapshot.jpg');
}

function pocket_camera_ai_feed_href(?string $cameraSlug = null, ?string $host = null): string
{
    $slug = trim((string) ($cameraSlug ?? pocket_camera_slug()));
    $slug = $slug !== '' ? $slug : pocket_camera_slug();

    return o_route_href('/camera-ai/' . rawurlencode($slug) . '.json', [], $host);
}

function normalize_sceptre_slug(?string $deviceSlug): string
{
    $candidate = strtolower(trim((string) $deviceSlug));
    $candidate = (string) preg_replace('/[^a-z0-9-]+/i', '-', $candidate);
    $candidate = trim((string) preg_replace('/-+/', '-', $candidate), '-');

    return $candidate !== '' ? $candidate : 'ensemble';
}

function sceptre_view_href(?string $deviceSlug = null, ?string $host = null): string
{
    $slug = normalize_sceptre_slug($deviceSlug);

    return o_route_href('/sceptre/' . rawurlencode($slug), [], $host);
}

function sceptre_feed_href(?string $deviceSlug = null, ?string $host = null): string
{
    $slug = normalize_sceptre_slug($deviceSlug);

    return o_route_href('/sceptre/' . rawurlencode($slug) . '.json', [], $host);
}

function sceptre_constellation_feed_href(?string $host = null): string
{
    return o_route_href('/sceptre/constellation.json', [], $host);
}

function sceptre_primary_device_slug(): string
{
    $override = trim((string) (getenv('SOWWWL_SCEPTRE_PRIMARY_DEVICE') ?: ''));

    return normalize_sceptre_slug($override !== '' ? $override : 'ensemble');
}

function pocket_camera_label(): string
{
    $label = trim((string) (getenv('SOWWWL_PI_CAMERA_LABEL') ?: ''));
    return $label !== '' ? $label : 'pi3-camera-01';
}

function pocket_camera_slug(): string
{
    $candidate = trim((string) (getenv('SOWWWL_PI_CAMERA_SLUG') ?: pocket_camera_label()));
    $normalized = strtolower((string) preg_replace('/[^a-z0-9-]+/i', '-', $candidate));
    $normalized = trim((string) preg_replace('/-+/', '-', $normalized), '-');

    return $normalized !== '' ? $normalized : 'pi3-camera-01';
}

function pocket_camera_view_href(?string $cameraSlug = null, ?string $host = null): string
{
    $slug = trim((string) ($cameraSlug ?? pocket_camera_slug()));
    $slug = $slug !== '' ? $slug : pocket_camera_slug();

    return o_route_href('/camera/' . rawurlencode($slug), [], $host);
}

function pocket_camera_matches_slug(?string $cameraSlug): bool
{
    $candidate = strtolower(trim((string) $cameraSlug));
    if ($candidate === '') {
        return false;
    }

    return $candidate === strtolower(pocket_camera_slug());
}

function camera_ai_storage_dir(): string
{
    $override = trim((string) (getenv('SOWWWL_CAMERA_AI_DIR') ?: ''));

    return $override !== '' ? $override : '/var/www/runtime/camera-ai';
}

function normalize_camera_state_slug(?string $cameraSlug): string
{
    $candidate = strtolower(trim((string) $cameraSlug));
    $candidate = (string) preg_replace('/[^a-z0-9-]+/i', '-', $candidate);
    $candidate = trim((string) preg_replace('/-+/', '-', $candidate), '-');

    return $candidate !== '' ? $candidate : pocket_camera_slug();
}

function camera_ai_state_path(?string $cameraSlug = null): string
{
    return rtrim(camera_ai_storage_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . normalize_camera_state_slug($cameraSlug) . '.json';
}

function camera_ai_stale_after_seconds(): int
{
    $override = (int) (getenv('SOWWWL_CAMERA_AI_STALE_AFTER_SECONDS') ?: 0);
    if ($override <= 0) {
        return 18;
    }

    return max(5, min(3600, $override));
}

function camera_ai_age_seconds(?string $timestamp): ?int
{
    $value = trim((string) $timestamp);
    if ($value === '') {
        return null;
    }

    $epoch = strtotime($value);
    if ($epoch === false) {
        return null;
    }

    return max(0, time() - $epoch);
}

function camera_ai_age_label(?int $ageSeconds): string
{
    if ($ageSeconds === null) {
        return 'temps inconnu';
    }

    if ($ageSeconds < 60) {
        return $ageSeconds . 's';
    }

    if ($ageSeconds < 3600) {
        return (int) floor($ageSeconds / 60) . 'min';
    }

    return (int) floor($ageSeconds / 3600) . 'h';
}

function camera_ai_ingest_token(): string
{
    $token = trim((string) (getenv('SOWWWL_PI_AI_TOKEN') ?: ''));
    if ($token !== '') {
        return $token;
    }

    return trim((string) (getenv('SOWWWL_PI_TOKEN') ?: ''));
}

function camera_ai_default_state(?string $cameraSlug = null): array
{
    $slug = normalize_camera_state_slug($cameraSlug);

    return [
        'ok' => true,
        'camera' => $slug,
        'scene' => 'veille',
        'lead' => 'IA du Pi 5 en veille douce.',
        'summary' => 'Aucune détection IA récente. Le paysage garde encore sa propre respiration.',
        'model' => '',
        'dominant_label' => '',
        'dominant_score' => 0.0,
        'attention' => 0.0,
        'movement' => 0.0,
        'density' => 0.0,
        'object_count' => 0,
        'person_count' => 0,
        'vehicle_count' => 0,
        'animal_count' => 0,
        'detections' => [],
        'updated_at' => null,
        'freshness' => 'idle',
        'stale' => false,
        'age_seconds' => null,
        'stale_after_seconds' => camera_ai_stale_after_seconds(),
    ];
}

function camera_ai_normalize_freshness(array $state): array
{
    $normalized = array_merge(camera_ai_default_state((string) ($state['camera'] ?? '')), $state);
    $ageSeconds = camera_ai_age_seconds((string) ($normalized['updated_at'] ?? ''));
    $staleAfter = camera_ai_stale_after_seconds();
    $hasSignal = !empty($normalized['detections'])
        || (int) ($normalized['object_count'] ?? 0) > 0
        || (float) ($normalized['attention'] ?? 0.0) > 0.0
        || (float) ($normalized['movement'] ?? 0.0) > 0.0
        || (float) ($normalized['density'] ?? 0.0) > 0.0;
    $stale = $hasSignal && ($ageSeconds === null || $ageSeconds > $staleAfter);

    $normalized['age_seconds'] = $ageSeconds;
    $normalized['stale_after_seconds'] = $staleAfter;
    $normalized['stale'] = $stale;
    $normalized['freshness'] = $stale
        ? 'stale'
        : (($normalized['updated_at'] ?? null) ? 'fresh' : 'idle');

    if (!$stale) {
        return $normalized;
    }

    $normalized['scene'] = 'stale';
    $normalized['lead'] = 'Le Pi 5 attend un nouveau regard.';
    $normalized['summary'] = 'Derniere lecture ' . camera_ai_age_label($ageSeconds) . ' plus tot. Les detections sont suspendues jusqu a la prochaine image fraiche.';
    $normalized['dominant_label'] = '';
    $normalized['dominant_score'] = 0.0;
    $normalized['attention'] = 0.0;
    $normalized['movement'] = 0.0;
    $normalized['density'] = 0.0;
    $normalized['object_count'] = 0;
    $normalized['person_count'] = 0;
    $normalized['vehicle_count'] = 0;
    $normalized['animal_count'] = 0;
    $normalized['detections'] = [];

    return $normalized;
}

function sceptre_storage_dir(): string
{
    $override = trim((string) (getenv('SOWWWL_SCEPTRE_DIR') ?: ''));

    return $override !== '' ? $override : '/var/www/runtime/sceptre';
}

function sceptre_state_path(?string $deviceSlug = null): string
{
    return rtrim(sceptre_storage_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . normalize_sceptre_slug($deviceSlug) . '.json';
}

function sceptre_tokens_file(): string
{
    return trim((string) (getenv('SOWWWL_SCEPTRE_TOKENS_FILE') ?: ''));
}

function sceptre_stale_after_seconds(): int
{
    $override = (int) (getenv('SOWWWL_SCEPTRE_STALE_AFTER_SECONDS') ?: 0);
    if ($override <= 0) {
        return 14;
    }

    return max(4, min(3600, $override));
}

function sceptre_ingest_token(): string
{
    $token = trim((string) (getenv('SOWWWL_SCEPTRE_TOKEN') ?: ''));
    if ($token !== '') {
        return $token;
    }

    return trim((string) (getenv('SOWWWL_PI_TOKEN') ?: ''));
}

function sceptre_device_tokens(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    $tokensPath = sceptre_tokens_file();
    if ($tokensPath === '' || !is_file($tokensPath) || !is_readable($tokensPath)) {
        return $cache;
    }

    $rawTokens = @file_get_contents($tokensPath);
    if (!is_string($rawTokens) || trim($rawTokens) === '') {
        return $cache;
    }

    $decoded = json_decode($rawTokens, true);
    if (!is_array($decoded)) {
        return $cache;
    }

    foreach ($decoded as $deviceSlug => $token) {
        if (!is_string($token)) {
            continue;
        }

        $normalizedSlug = normalize_sceptre_slug(is_string($deviceSlug) ? $deviceSlug : '');
        $normalizedToken = trim($token);
        if ($normalizedSlug === '' || $normalizedToken === '') {
            continue;
        }

        $cache[$normalizedSlug] = $normalizedToken;
    }

    return $cache;
}

function sceptre_expected_token_for_device(?string $deviceSlug = null): string
{
    $slug = normalize_sceptre_slug($deviceSlug ?: sceptre_primary_device_slug());
    $deviceTokens = sceptre_device_tokens();
    if (isset($deviceTokens[$slug])) {
        return $deviceTokens[$slug];
    }

    return sceptre_ingest_token();
}

function sceptre_default_state(?string $deviceSlug = null): array
{
    $slug = normalize_sceptre_slug($deviceSlug);

    return [
        'ok' => true,
        'device' => $slug,
        'source' => 'pi3-bplus-sceptre',
        'scene' => 'veille',
        'ritual_mode' => 'veille',
        'lead' => 'Le sceptre dort encore dans le tore.',
        'summary' => 'Le Pi 3 B+ et son Sensor HAT peuvent deja devenir une main, un climat et un rythme pour la surface.',
        'updated_at' => null,
        'freshness' => 'idle',
        'stale' => false,
        'age_seconds' => null,
        'stale_after_seconds' => sceptre_stale_after_seconds(),
        'motion' => [
            'pitch' => 0.0,
            'roll' => 0.0,
            'yaw' => 0.0,
            'tilt_x' => 0.0,
            'tilt_y' => 0.0,
            'sway' => 0.0,
            'shake' => 0.0,
            'stillness' => 1.0,
            'heading' => 0.0,
        ],
        'climate' => [
            'temperature' => 0.5,
            'humidity' => 0.5,
            'pressure' => 0.5,
            'temperature_c' => null,
            'humidity_percent' => null,
            'pressure_hpa' => null,
        ],
        'music' => [
            'tempo_bias' => 0.0,
            'swing_bias' => 0.0,
            'drone_bias' => 0.0,
            'filter_bias' => 0.0,
            'percussion_bias' => 0.0,
            'volume_bias' => 0.0,
        ],
        'visual' => [
            'brightness' => 0.0,
            'negative_bias' => 0.0,
            'torus_spin' => 0.0,
            'halo' => 0.0,
            'contrast_bias' => 0.0,
            'tint_warmth' => 0.0,
        ],
        'triggers' => [
            'kick' => 0.0,
            'snare' => 0.0,
            'hihat' => 0.0,
            'accent' => 0.0,
        ],
        'screen' => [
            'page' => 'veille',
            'mode' => 'listen',
            'label' => 'veille',
        ],
        'magic' => [
            'sigil' => 'lune',
            'palette' => 'ardoise',
            'spell' => 'silence tenu',
        ],
    ];
}

function sceptre_normalize_state(array $state): array
{
    $normalized = sceptre_default_state((string) ($state['device'] ?? ''));
    $normalized['ok'] = ($state['ok'] ?? true) !== false;
    $normalized['device'] = normalize_sceptre_slug((string) ($state['device'] ?? $normalized['device']));

    foreach (['source', 'scene', 'ritual_mode', 'lead', 'summary', 'updated_at'] as $key) {
        $value = trim((string) ($state[$key] ?? ''));
        if ($value !== '') {
            $normalized[$key] = $value;
        }
    }

    foreach ([
        'pitch' => [-1.0, 1.0],
        'roll' => [-1.0, 1.0],
        'yaw' => [-1.0, 1.0],
        'tilt_x' => [-1.0, 1.0],
        'tilt_y' => [-1.0, 1.0],
        'sway' => [0.0, 1.0],
        'shake' => [0.0, 1.0],
        'stillness' => [0.0, 1.0],
        'heading' => [0.0, 1.0],
    ] as $key => [$minimum, $maximum]) {
        if (isset($state['motion'][$key])) {
            $normalized['motion'][$key] = max($minimum, min($maximum, (float) $state['motion'][$key]));
        }
    }

    foreach ([
        'temperature' => [0.0, 1.0],
        'humidity' => [0.0, 1.0],
        'pressure' => [0.0, 1.0],
    ] as $key => [$minimum, $maximum]) {
        if (isset($state['climate'][$key])) {
            $normalized['climate'][$key] = max($minimum, min($maximum, (float) $state['climate'][$key]));
        }
    }

    foreach (['temperature_c', 'humidity_percent', 'pressure_hpa'] as $key) {
        if (array_key_exists($key, (array) ($state['climate'] ?? []))) {
            $value = $state['climate'][$key];
            $normalized['climate'][$key] = $value === null ? null : (float) $value;
        }
    }

    foreach ([
        'tempo_bias' => [-1.0, 1.0],
        'swing_bias' => [-1.0, 1.0],
        'drone_bias' => [0.0, 1.0],
        'filter_bias' => [-1.0, 1.0],
        'percussion_bias' => [0.0, 1.0],
        'volume_bias' => [0.0, 1.0],
    ] as $key => [$minimum, $maximum]) {
        if (isset($state['music'][$key])) {
            $normalized['music'][$key] = max($minimum, min($maximum, (float) $state['music'][$key]));
        }
    }

    foreach ([
        'brightness' => [-1.0, 1.0],
        'negative_bias' => [0.0, 1.0],
        'torus_spin' => [-1.0, 1.0],
        'halo' => [0.0, 1.0],
        'contrast_bias' => [0.0, 1.0],
        'tint_warmth' => [-1.0, 1.0],
    ] as $key => [$minimum, $maximum]) {
        if (isset($state['visual'][$key])) {
            $normalized['visual'][$key] = max($minimum, min($maximum, (float) $state['visual'][$key]));
        }
    }

    foreach ([
        'kick' => [0.0, 1.0],
        'snare' => [0.0, 1.0],
        'hihat' => [0.0, 1.0],
        'accent' => [0.0, 1.0],
    ] as $key => [$minimum, $maximum]) {
        if (isset($state['triggers'][$key])) {
            $normalized['triggers'][$key] = max($minimum, min($maximum, (float) $state['triggers'][$key]));
        }
    }

    foreach (['page', 'mode', 'label'] as $key) {
        $value = trim((string) ($state['screen'][$key] ?? ''));
        if ($value !== '') {
            $normalized['screen'][$key] = $value;
        }
    }

    foreach (['sigil', 'palette', 'spell'] as $key) {
        $value = trim((string) ($state['magic'][$key] ?? ''));
        if ($value !== '') {
            $normalized['magic'][$key] = $value;
        }
    }

    return $normalized;
}

function sceptre_normalize_freshness(array $state): array
{
    $normalized = sceptre_normalize_state($state);
    $ageSeconds = camera_ai_age_seconds((string) ($normalized['updated_at'] ?? ''));
    $staleAfter = sceptre_stale_after_seconds();
    $dynamicSignal = max(
        abs((float) ($normalized['motion']['pitch'] ?? 0.0)),
        abs((float) ($normalized['motion']['roll'] ?? 0.0)),
        (float) ($normalized['motion']['sway'] ?? 0.0),
        (float) ($normalized['motion']['shake'] ?? 0.0),
        (float) ($normalized['music']['percussion_bias'] ?? 0.0),
        (float) ($normalized['visual']['halo'] ?? 0.0),
        (float) ($normalized['triggers']['accent'] ?? 0.0)
    );
    $stale = ($normalized['updated_at'] ?? null) && ($ageSeconds === null || $ageSeconds > $staleAfter);

    $normalized['age_seconds'] = $ageSeconds;
    $normalized['stale_after_seconds'] = $staleAfter;
    $normalized['stale'] = $stale;
    $normalized['freshness'] = $stale
        ? 'stale'
        : (($normalized['updated_at'] ?? null) ? 'fresh' : 'idle');

    if (!$stale) {
        return $normalized;
    }

    $normalized['scene'] = 'stale';
    $normalized['lead'] = 'Le sceptre attend un nouveau geste.';
    $normalized['summary'] = 'Derniere lecture ' . camera_ai_age_label($ageSeconds) . ' plus tot. Les impulsions directes sont suspendues jusqu au prochain souffle frais.';
    $normalized['motion'] = array_merge($normalized['motion'], [
        'pitch' => 0.0,
        'roll' => 0.0,
        'yaw' => 0.0,
        'tilt_x' => 0.0,
        'tilt_y' => 0.0,
        'sway' => 0.0,
        'shake' => 0.0,
        'stillness' => max(0.0, (float) ($normalized['motion']['stillness'] ?? 0.0)),
        'heading' => 0.0,
    ]);
    $normalized['music'] = array_merge($normalized['music'], [
        'tempo_bias' => 0.0,
        'swing_bias' => 0.0,
        'drone_bias' => 0.0,
        'filter_bias' => 0.0,
        'percussion_bias' => 0.0,
        'volume_bias' => 0.0,
    ]);
    $normalized['visual'] = array_merge($normalized['visual'], [
        'brightness' => 0.0,
        'negative_bias' => 0.0,
        'torus_spin' => 0.0,
        'halo' => 0.0,
        'contrast_bias' => 0.0,
        'tint_warmth' => 0.0,
    ]);
    $normalized['triggers'] = array_merge($normalized['triggers'], [
        'kick' => 0.0,
        'snare' => 0.0,
        'hihat' => 0.0,
        'accent' => 0.0,
    ]);
    if ($dynamicSignal <= 0.02) {
        $normalized['ritual_mode'] = 'veille';
    }

    return $normalized;
}

function sceptre_motion_level(array $state): float
{
    return max(
        0.0,
        min(
            1.0,
            max(
                (float) ($state['motion']['sway'] ?? 0.0),
                (float) ($state['motion']['shake'] ?? 0.0),
                abs((float) ($state['motion']['pitch'] ?? 0.0)) * 0.42
            )
        )
    );
}

function sceptre_percussion_level(array $state): float
{
    return max(
        0.0,
        min(
            1.0,
            max(
                (float) ($state['music']['percussion_bias'] ?? 0.0),
                (float) ($state['triggers']['accent'] ?? 0.0),
                (float) ($state['triggers']['kick'] ?? 0.0) * 0.86
            )
        )
    );
}

function sceptre_halo_level(array $state): float
{
    return max(0.0, min(1.0, (float) ($state['visual']['halo'] ?? 0.0)));
}

function sceptre_read_state(?string $deviceSlug = null): array
{
    $slug = normalize_sceptre_slug($deviceSlug ?: sceptre_primary_device_slug());
    $payload = sceptre_default_state($slug);
    $statePath = sceptre_state_path($slug);

    if (is_file($statePath) && is_readable($statePath)) {
        $rawState = @file_get_contents($statePath);
        if (is_string($rawState) && trim($rawState) !== '') {
            $decoded = json_decode($rawState, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
    }

    $normalized = sceptre_normalize_freshness($payload);
    $normalized['device'] = normalize_sceptre_slug((string) ($normalized['device'] ?? $slug));

    return $normalized;
}

function sceptre_state_roster_entry(array $state, string $primaryDeviceSlug): array
{
    $normalized = sceptre_normalize_freshness($state);
    $deviceSlug = normalize_sceptre_slug((string) ($normalized['device'] ?? $primaryDeviceSlug));

    return [
        'device' => $deviceSlug,
        'freshness' => (string) ($normalized['freshness'] ?? 'idle'),
        'stale' => !empty($normalized['stale']),
        'scene' => (string) ($normalized['scene'] ?? 'veille'),
        'ritual_mode' => (string) ($normalized['ritual_mode'] ?? 'veille'),
        'lead' => (string) ($normalized['lead'] ?? ''),
        'summary' => (string) ($normalized['summary'] ?? ''),
        'updated_at' => (string) ($normalized['updated_at'] ?? ''),
        'age_seconds' => isset($normalized['age_seconds']) ? (int) $normalized['age_seconds'] : null,
        'motion_level' => sceptre_motion_level($normalized),
        'percussion_level' => sceptre_percussion_level($normalized),
        'halo_level' => sceptre_halo_level($normalized),
        'screen' => $normalized['screen'] ?? [],
        'magic' => $normalized['magic'] ?? [],
        'is_primary' => $deviceSlug === $primaryDeviceSlug,
    ];
}

function sceptre_constellation_states(?string $primaryDeviceSlug = null): array
{
    $primary = normalize_sceptre_slug($primaryDeviceSlug ?: sceptre_primary_device_slug());
    $stateDir = sceptre_storage_dir();
    $states = [];
    $tokensRegistryPath = sceptre_tokens_file();
    $tokensRegistryRealPath = $tokensRegistryPath !== '' ? realpath($tokensRegistryPath) : false;

    if (is_dir($stateDir)) {
        $statePaths = glob(rtrim($stateDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.json') ?: [];
        foreach ($statePaths as $statePath) {
            if (!is_string($statePath) || !is_file($statePath) || !is_readable($statePath)) {
                continue;
            }

            $stateRealPath = realpath($statePath);
            if ($tokensRegistryRealPath !== false && $stateRealPath !== false && $stateRealPath === $tokensRegistryRealPath) {
                continue;
            }

            $rawState = @file_get_contents($statePath);
            if (!is_string($rawState) || trim($rawState) === '') {
                continue;
            }

            $decoded = json_decode($rawState, true);
            if (!is_array($decoded)) {
                continue;
            }

            if (
                !array_key_exists('device', $decoded)
                && !array_key_exists('updated_at', $decoded)
                && !array_key_exists('scene', $decoded)
                && !array_key_exists('motion', $decoded)
            ) {
                continue;
            }

            $normalized = sceptre_normalize_freshness($decoded);
            $deviceSlug = normalize_sceptre_slug((string) ($normalized['device'] ?? pathinfo($statePath, PATHINFO_FILENAME)));
            $normalized['device'] = $deviceSlug;
            $states[$deviceSlug] = $normalized;
        }
    }

    if (!isset($states[$primary])) {
        $states[$primary] = sceptre_normalize_freshness(sceptre_default_state($primary));
    }

    uasort($states, static function (array $left, array $right) use ($primary): int {
        $leftPrimary = normalize_sceptre_slug((string) ($left['device'] ?? '')) === $primary ? 1 : 0;
        $rightPrimary = normalize_sceptre_slug((string) ($right['device'] ?? '')) === $primary ? 1 : 0;
        if ($leftPrimary !== $rightPrimary) {
            return $rightPrimary <=> $leftPrimary;
        }

        $freshnessRank = static function (array $state): int {
            return match ((string) ($state['freshness'] ?? 'idle')) {
                'fresh' => 3,
                'stale' => 2,
                default => 1,
            };
        };

        $freshnessCompare = $freshnessRank($right) <=> $freshnessRank($left);
        if ($freshnessCompare !== 0) {
            return $freshnessCompare;
        }

        $leftUpdatedAt = strtotime((string) ($left['updated_at'] ?? '')) ?: 0;
        $rightUpdatedAt = strtotime((string) ($right['updated_at'] ?? '')) ?: 0;
        return $rightUpdatedAt <=> $leftUpdatedAt;
    });

    return array_values($states);
}

function sceptre_pick_active_state(array $states, string $primaryDeviceSlug): array
{
    foreach ($states as $state) {
        if (normalize_sceptre_slug((string) ($state['device'] ?? '')) === $primaryDeviceSlug
            && (string) ($state['freshness'] ?? 'idle') === 'fresh'
        ) {
            return $state;
        }
    }

    foreach ($states as $state) {
        if ((string) ($state['freshness'] ?? 'idle') === 'fresh') {
            return $state;
        }
    }

    foreach ($states as $state) {
        if (normalize_sceptre_slug((string) ($state['device'] ?? '')) === $primaryDeviceSlug) {
            return $state;
        }
    }

    return $states[0] ?? sceptre_normalize_freshness(sceptre_default_state($primaryDeviceSlug));
}

function sceptre_constellation_payload(?string $primaryDeviceSlug = null): array
{
    $primary = normalize_sceptre_slug($primaryDeviceSlug ?: sceptre_primary_device_slug());
    $states = sceptre_constellation_states($primary);
    $active = sceptre_pick_active_state($states, $primary);

    $freshCount = 0;
    $staleCount = 0;
    $idleCount = 0;
    $deviceEntries = [];

    foreach ($states as $state) {
        $entry = sceptre_state_roster_entry($state, $primary);
        $deviceEntries[] = $entry;

        switch ($entry['freshness']) {
            case 'fresh':
                $freshCount++;
                break;
            case 'stale':
                $staleCount++;
                break;
            default:
                $idleCount++;
                break;
        }
    }

    return [
        'ok' => true,
        'primary_device' => $primary,
        'active_device' => normalize_sceptre_slug((string) ($active['device'] ?? $primary)),
        'count' => count($deviceEntries),
        'fresh_count' => $freshCount,
        'stale_count' => $staleCount,
        'idle_count' => $idleCount,
        'active' => $active,
        'devices' => $deviceEntries,
    ];
}

function render_pocket_camera_panel(array $options = []): string
{
    $streamUrl = trim((string) ($options['stream_url'] ?? ''));
    $snapshotUrl = trim((string) ($options['snapshot_url'] ?? ''));
    if ($streamUrl === '' && $snapshotUrl === '') {
        return '';
    }

    $tagCandidate = (string) ($options['tag'] ?? 'section');
    $tag = preg_match('/^[a-z][a-z0-9-]*$/i', $tagCandidate) === 1
        ? $tagCandidate
        : 'section';
    $id = trim((string) ($options['id'] ?? ''));
    $context = trim((string) ($options['context'] ?? 'default'));
    $className = trim((string) ($options['class'] ?? 'pocket-camera-panel'));
    $title = trim((string) ($options['title'] ?? 'Œil pocket'));
    $lead = trim((string) ($options['lead'] ?? 'Le flux attend encore sa première image.'));
    $copy = trim((string) ($options['copy'] ?? 'Le Raspberry Pi caméra reste visible ici via le proxy du shore node.'));
    $label = trim((string) ($options['label'] ?? pocket_camera_label()));
    $autostart = !empty($options['autostart']);
    $initialSrc = $snapshotUrl !== '' ? $snapshotUrl : $streamUrl;
    $initialModeLabel = $snapshotUrl !== '' ? 'snapshot' : 'live';
    $initialBadge = $snapshotUrl !== '' ? 'image fixe' : 'flux live';
    $openHref = $streamUrl !== '' ? $streamUrl : $snapshotUrl;
    $accessHost = parse_url($openHref, PHP_URL_HOST);
    $accessLabel = is_string($accessHost) && $accessHost !== '' ? $accessHost : 'local';

    $attrs = [
        'class="' . h($className) . '"',
        'data-pocket-camera-root',
        'data-pocket-camera-context="' . h($context) . '"',
        'data-pocket-camera-stream="' . h($streamUrl) . '"',
        'data-pocket-camera-snapshot="' . h($snapshotUrl) . '"',
        'data-pocket-camera-label="' . h($label) . '"',
        'data-pocket-camera-autostart="' . ($autostart ? '1' : '0') . '"',
    ];
    if ($id !== '') {
        $attrs[] = 'id="' . h($id) . '"';
    }
    $attrHtml = implode(' ', $attrs);

    $titleEsc = h($title);
    $leadEsc = h($lead);
    $copyEsc = h($copy);
    $labelEsc = h($label);
    $initialSrcEsc = h($initialSrc);
    $initialModeLabelEsc = h($initialModeLabel);
    $initialBadgeEsc = h($initialBadge);
    $openHrefEsc = h($openHref);
    $accessLabelEsc = h($accessLabel);
    $disabledSnapshot = $snapshotUrl === '' ? ' disabled aria-disabled="true"' : '';
    $disabledLive = $streamUrl === '' ? ' disabled aria-disabled="true"' : '';

    ob_start();
    ?>
<<?= $tag ?> <?= $attrHtml ?>>
    <div class="pocket-camera-panel__topline">
        <div>
            <span class="summary-label">œil pocket</span>
            <h2 class="pocket-camera-panel__title"><?= $titleEsc ?></h2>
        </div>
        <span class="badge badge-glass" data-pocket-camera-badge><?= $initialBadgeEsc ?></span>
    </div>
    <strong class="pocket-camera-panel__lead" data-pocket-camera-status><?= $leadEsc ?></strong>
    <p class="panel-copy pocket-camera-panel__copy"><?= $copyEsc ?></p>
    <div class="pocket-camera-panel__stage">
        <img
            class="pocket-camera-panel__frame"
            data-pocket-camera-frame
            src="<?= $initialSrcEsc ?>"
            alt="Flux caméra <?= $labelEsc ?>"
            loading="eager"
            decoding="async"
            referrerpolicy="same-origin"
        >
        <div class="pocket-camera-panel__fallback" data-pocket-camera-fallback>œil pocket</div>
    </div>
    <div class="pocket-camera-panel__meta" aria-label="État du flux caméra">
        <p><span>source</span><strong><?= $labelEsc ?></strong></p>
        <p><span>mode</span><strong data-pocket-camera-mode><?= $initialModeLabelEsc ?></strong></p>
        <p><span>accès</span><strong><?= $accessLabelEsc ?></strong></p>
        <p><span>cadre</span><strong data-pocket-camera-presence>chargement</strong></p>
    </div>
    <div class="action-row pocket-camera-panel__actions">
        <button type="button" class="pill-link" data-pocket-camera-live<?= $disabledLive ?>>Voir le live</button>
        <button type="button" class="ghost-link" data-pocket-camera-snapshot<?= $disabledSnapshot ?>>Recharger l’image</button>
        <a class="ghost-link" data-pocket-camera-open href="<?= $openHrefEsc ?>" target="_blank" rel="noreferrer">Ouvrir brut</a>
    </div>
</<?= $tag ?>>
    <?php
    return trim((string) ob_get_clean());
}

function plasma_configured_allowed_origins(): array
{
    return sowwwl_parse_origin_list((string) (getenv('SOWWWL_PLASMA_ALLOWED_ORIGINS') ?: ''));
}

function plasma_connect_src_origins(?string $host = null): array
{
    $resolvedHost = request_host($host);
    $surfaceVariant = current_surface_variant($resolvedHost);
    if (!in_array($surfaceVariant, ['xyz', 'io', 'lab'], true)) {
        return [];
    }

    $origins = [];
    $currentOrigin = request_public_origin($resolvedHost);

    foreach ([plasma_bridge_url(), plasma_feed_url()] as $url) {
        $origin = sowwwl_url_origin($url);
        if ($origin !== null && $origin !== $currentOrigin) {
            $origins[] = $origin;
        }
    }

    if ($surfaceVariant === 'lab') {
        $origins[] = 'https://api.lab.sowwwl.cloud';
    }

    return array_values(array_unique($origins));
}
