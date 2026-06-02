<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host = request_host();
$surfaceVariant = current_surface_variant($host);
$isSpatialSurface = $surfaceVariant === 'io';
$isSpatialHeadsetMode = $surfaceVariant === 'io' && spatial_preview_mode($host) === 'headset';
$pageHeadVariant = pwa_default_app_id($host);
$cameraSlug = trim((string) ($_GET['camera'] ?? ''));
$cameraSlug = $cameraSlug !== '' ? strtolower($cameraSlug) : pocket_camera_slug();
$cameraFound = pocket_camera_matches_slug($cameraSlug);

if (!$cameraFound) {
    http_response_code(404);
}

$cameraLabel = $cameraFound ? pocket_camera_label() : $cameraSlug;
$cameraStreamHref = $cameraFound ? pocket_camera_stream_url($cameraSlug) : '';
$cameraSnapshotHref = $cameraFound ? pocket_camera_snapshot_url($cameraSlug) : '';
$recentEvents = $cameraFound ? plasma_recent_events(24, $cameraSlug) : [];
$recentEvents = array_slice($recentEvents, 0, 6);
$cameraWeather = $cameraFound
    ? plasma_weather_from_events($recentEvents)
    : [
        'tone' => 'idle',
        'badge' => '404',
        'lead' => 'Aucune camera ne repond pour ce slug.',
        'detail' => $isSpatialSurface
            ? 'Le volume garde la place, mais la source n existe pas.'
            : 'Le tore garde la place, mais la source n existe pas.',
        'energy' => 0.0,
        'count' => 0,
        'freshness' => 'idle',
        'stale' => false,
        'age_seconds' => null,
        'latest_at' => null,
        'stale_after_seconds' => 90,
    ];
$ambientVisual = visual_profile_tokens(null, (string) ($cameraWeather['tone'] ?? 'calm'));
$cameraAiFeedHref = $cameraFound ? pocket_camera_ai_feed_href($cameraSlug, $host) : '';
$landscapeChoirFeedHref = $cameraFound
    ? o_route_href('/plasma/recent', [
        'limit' => 8,
        'land_slug' => $cameraSlug,
        'scan' => 60,
    ], $host)
    : '';
$landscapeChoirSeedJson = json_encode([
    'weather' => $cameraWeather,
    'events' => $recentEvents,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$landscapeChoirSeedJson = is_string($landscapeChoirSeedJson) ? $landscapeChoirSeedJson : '{"weather":{},"events":[]}';
$cameraFallbackSrc = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
$cameraInitialSrc = $cameraSnapshotHref !== '' ? $cameraSnapshotHref : $cameraStreamHref;
$cameraInitialSrc = $cameraInitialSrc !== '' ? $cameraInitialSrc : $cameraFallbackSrc;
$cameraInitialModeLabel = $cameraFound
    ? ($cameraSnapshotHref !== '' ? 'snapshot' : 'live')
    : 'offline';
$cameraInitialBadge = $cameraFound
    ? ($cameraSnapshotHref !== '' ? 'image fixe' : 'flux live')
    : 'absente';
$cameraOpenHref = $cameraStreamHref !== '' ? $cameraStreamHref : ($cameraSnapshotHref !== '' ? $cameraSnapshotHref : '#');
$pageDescription = $cameraFound
    ? 'Fenetre — prise réelle publique, lumière et traces récentes du nœud caméra ' . $cameraLabel . '.'
    : 'Fenetre — cette prise réelle n existe pas ou n est plus disponible.';
$pageTitle = 'Fenetre — ' . SITE_TITLE;
$cameraAutostart = $cameraFound && $cameraStreamHref !== '';
$cameraStatusText = $cameraFound ? 'Prise réelle.' : 'Caméra absente.';
$cameraVisionText = $cameraFound ? 'Lecture en veille.' : 'Aucune source pour ce slug.';
$cameraPresenceText = $cameraFound ? 'ouverture' : 'introuvable';
$choirStatusText = $cameraFound ? (string) ($cameraWeather['badge'] ?? 'veille') : 'silence';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= h($pageDescription) ?>">
    <meta name="theme-color" content="#09090b">
    <title><?= h($pageTitle) ?></title>
<?= render_o_discovery_head_tags($pageTitle, $pageDescription, $host) ?>
<?= render_o_page_head_assets($pageHeadVariant, $host) ?>
</head>
<body class="experience camera-view camera-negative-view<?= $surfaceVariant === 'io' ? ' io-surface-view' : '' ?><?= $isSpatialHeadsetMode ? ' io-headset-mode' : '' ?>">
<?= render_skip_link() ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

    <section
        class="camera-negative-layer"
        data-pocket-camera-root
        data-pocket-camera-context="camera-negative"
        data-pocket-camera-stream="<?= h($cameraStreamHref) ?>"
        data-pocket-camera-snapshot="<?= h($cameraSnapshotHref) ?>"
        data-pocket-camera-ai-feed="<?= h($cameraAiFeedHref) ?>"
        data-pocket-camera-label="<?= h($cameraLabel) ?>"
        data-pocket-camera-autostart="<?= $cameraAutostart ? '1' : '0' ?>"
        aria-labelledby="camera-negative-title"
    >
        <div class="camera-negative-layer__stage" aria-hidden="true">
            <img
                class="camera-negative-layer__frame camera-negative-layer__frame--base"
                data-pocket-camera-frame
                src="<?= h($cameraInitialSrc) ?>"
                alt=""
                loading="eager"
                decoding="async"
                referrerpolicy="same-origin"
            >
            <div class="camera-negative-layer__wash"></div>
            <canvas
                class="camera-negative-layer__torus"
                data-torus-cloud
                data-torus-passive="1"
                data-land-type="<?= h((string) ($ambientVisual['program'] ?? 'collective')) ?>"
                data-land-label="<?= h((string) ($ambientVisual['label'] ?? 'collectif')) ?>"
                data-lambda="<?= h((string) ($ambientVisual['lambda'] ?? 548)) ?>"
                data-stream-mood="<?= h((string) ($ambientVisual['mood'] ?? 'calm')) ?>"
            ></canvas>
            <div class="camera-negative-layer__spectra" aria-hidden="true">
                <div class="camera-negative-layer__focus"></div>
                <div class="camera-negative-layer__detections" data-pocket-camera-overlay></div>
            </div>
            <div class="camera-negative-layer__fallback" data-pocket-camera-fallback></div>
        </div>

        <div class="camera-negative-layer__hud panel reveal">
            <div class="camera-negative-layer__topline">
                <div>
                    <h2 id="camera-negative-title">Fenetre</h2>
                </div>
                <span class="badge badge-glass" data-pocket-camera-badge><?= h($cameraInitialBadge) ?></span>
            </div>
            <strong class="camera-negative-layer__status" data-pocket-camera-status><?= h($cameraStatusText) ?></strong>
            <p class="camera-negative-layer__vision" data-pocket-camera-vision><?= h($cameraVisionText) ?></p>
            <div class="camera-negative-layer__meta" aria-label="État du flux caméra">
                <p><span>source</span><strong><?= h($cameraLabel) ?></strong></p>
                <p><span>mode</span><strong data-pocket-camera-mode><?= h($cameraInitialModeLabel) ?></strong></p>
                <p><span>cadre</span><strong data-pocket-camera-presence><?= h($cameraPresenceText) ?></strong></p>
            </div>
            <div class="action-row camera-negative-layer__actions">
                <button type="button" class="pill-link" data-pocket-camera-live<?= $cameraStreamHref === '' ? ' disabled aria-disabled="true"' : '' ?>>Live</button>
                <button type="button" class="ghost-link" data-pocket-camera-snapshot<?= $cameraSnapshotHref === '' ? ' disabled aria-disabled="true"' : '' ?>>Image</button>
                <a class="ghost-link" data-pocket-camera-open href="<?= h($cameraOpenHref) ?>" target="_blank" rel="noreferrer">Brut</a>
            </div>
        </div>
    </section>

<main <?= main_landmark_attrs() ?> class="layout ui-overlay camera-page-shell camera-page-shell--minimal">
    <?= render_spatial_context_bar('camera', $host, $isSpatialSurface
        ? 'Fenetre tient ici le capteur du réel: paysage, lumière, cadence des événements et reprise douce vers le volume.'
        : 'Fenetre garde ici une prise réelle: paysage, lumière, cadence des événements et reprise douce vers le tore.') ?>

    <section
        class="panel reveal landscape-choir landscape-choir--compact camera-choir-dock"
        data-landscape-choir-root
        data-landscape-choir-feed="<?= h($landscapeChoirFeedHref) ?>"
        data-landscape-choir-ai-feed="<?= h($cameraAiFeedHref) ?>"
        data-landscape-choir-camera="<?= h($cameraSlug) ?>"
        data-landscape-choir-label="<?= h($cameraLabel) ?>"
        aria-labelledby="landscape-choir-title"
    >
        <div class="landscape-choir__head landscape-choir__head--compact">
            <h2 id="landscape-choir-title">Chant du paysage</h2>
            <span class="badge badge-glass" data-landscape-choir-badge><?= h((string) ($cameraWeather['badge'] ?? 'veille')) ?></span>
        </div>
        <strong class="landscape-choir__lead landscape-choir__lead--compact" data-landscape-choir-status>
            <?= h($choirStatusText) ?>
        </strong>
        <div class="landscape-choir__controls">
            <div class="landscape-choir__modes landscape-choir__modes--compact" role="group" aria-label="manière de faire chanter le paysage">
                <button type="button" class="landscape-choir__mode" data-landscape-choir-mode="soft" aria-pressed="true">doux</button>
                <button type="button" class="landscape-choir__mode" data-landscape-choir-mode="ritual" aria-pressed="false">rituel</button>
            </div>
            <div class="landscape-choir__actions action-row landscape-choir__actions--compact">
                <button type="button" class="pill-link" data-landscape-choir-toggle aria-pressed="false">Écouter</button>
                <label class="landscape-choir__field landscape-choir__field--compact">
                    <input type="range" min="0" max="100" step="1" value="58" data-landscape-choir-volume aria-label="volume">
                    <strong data-landscape-choir-volume-label>58%</strong>
                </label>
            </div>
        </div>
        <script type="application/json" data-landscape-choir-seed><?= h($landscapeChoirSeedJson) ?></script>
    </section>
</main>
</body>
</html>
