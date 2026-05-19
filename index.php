<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/str3m_media.php';
require_once __DIR__ . '/lib/str3m_daily.php';

function signup_portal_steps(): array
{
    $steps = [
        ['slug' => '01', 'label' => 'Qui', 'file' => '01-qui-es-tu.html'],
        ['slug' => '02', 'label' => 'Projet', 'file' => '02-projet.html'],
        ['slug' => '03', 'label' => 'Valeurs', 'file' => '03-valeurs.html'],
        ['slug' => '04', 'label' => 'Démarche', 'file' => '04-demarche.html'],
        ['slug' => '05', 'label' => 'Pacte', 'file' => '05-pacte.html'],
    ];

    $portals = [];
    foreach ($steps as $step) {
        $path = __DIR__ . '/aza_portals/' . $step['file'];
        $markup = is_file($path) ? trim((string) file_get_contents($path)) : '';
        if ($markup === '') {
            continue;
        }

        $portals[] = [
            'slug' => $step['slug'],
            'label' => $step['label'],
            'markup' => $markup,
        ];
    }

    return $portals;
}

$host = request_host();
$surfaceVariant = current_surface_variant($host);
$isSowwwlXyz = $surfaceVariant === 'xyz';
$isSowwwlIo = $surfaceVariant === 'io';
$isLabSurface = $surfaceVariant === 'lab';
$isSpatialSurface = $isSowwwlXyz || $isSowwwlIo;
$isSpatialHeadsetMode = $isSowwwlIo && spatial_preview_mode($host) === 'headset';
// Spatial surfaces keep their own local preview via ?surface=xyz|io|lab on localhost.

$requestPath = o_request_path('/');
if (($host === '0wlslw0.com' || $host === 'www.0wlslw0.com') && ($requestPath === '/' || $requestPath === '/index.php')) {
    header('Location: ' . o_route_href('/0wlslw0'), true, 302);
    exit;
}

$message = '';
$messageType = 'info';
$timezoneSuggestions = [
    'Europe/Paris',
    'Europe/London',
    'America/New_York',
    'America/Los_Angeles',
    'America/Montreal',
    'Africa/Casablanca',
    'Asia/Tokyo',
    'Asia/Bangkok',
];
$authenticatedLand = current_authenticated_land();
$csrfToken = csrf_token();
$form = [
    'username' => '',
    'timezone' => DEFAULT_TIMEZONE,
    'password' => '',
    'login_identifier' => '',
    'land_program' => '',
    'lambda_nm' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create');
    $form['username'] = trim((string) ($_POST['username'] ?? ''));
    $form['timezone'] = trim((string) ($_POST['timezone'] ?? ''));
    $form['password'] = (string) ($_POST['password'] ?? '');
    $form['login_identifier'] = trim((string) ($_POST['login_identifier'] ?? ''));
    $form['land_program'] = trim((string) ($_POST['land_program'] ?? ''));
    $form['lambda_nm'] = trim((string) ($_POST['lambda_nm'] ?? ''));
    $csrfCandidate = (string) ($_POST['csrf_token'] ?? '');
    $honeypot = (string) ($_POST['website'] ?? '');

    if ($action === 'login') {
        if ($form['login_identifier'] === '' || $form['password'] === '') {
            $message = 'Écris le nom de ta terre et son secret.';
            $messageType = 'warning';
        } else {
            try {
                guard_land_login_request($csrfCandidate);
                $land = authenticate_land($form['login_identifier'], $form['password']);
                if (!$land) {
                    throw new RuntimeException('Identifiants incorrects.');
                }

                login_land($land);
                header('Location: ' . o_route_href('/land', ['u' => (string) $land['slug'], 'session' => '1']), true, 303);
                exit;
            } catch (InvalidArgumentException | RuntimeException $exception) {
                $message = $exception->getMessage();
                $messageType = 'warning';
            }
        }
    } else {
        if ($form['username'] === '') {
            $message = 'Écris un nom (2 à 42 caractères) pour créer ta terre.';
            $messageType = 'warning';
        } else {
            try {
                guard_land_creation_request($csrfCandidate, $honeypot);
                $land = create_land(
                    $form['username'],
                    $form['timezone'],
                    $form['password'],
                    $form['land_program'] !== '' ? $form['land_program'] : null,
                    $form['lambda_nm'] !== '' ? (int) $form['lambda_nm'] : null
                );
                login_land($land);
                header('Location: ' . o_route_href('/land', ['u' => (string) $land['slug'], 'created' => '1', 'session' => '1']), true, 303);
                exit;
            } catch (InvalidArgumentException $exception) {
                $message = $exception->getMessage();
                $messageType = 'warning';
            } catch (RuntimeException $exception) {
                $message = $exception->getMessage();
                $messageType = 'warning';
            }
        }
    }
}

remember_form_rendered_at();

$pulse = land_pulse();
$previewSlug = preview_land_slug($form['username']);
$previewTimezone = $form['timezone'] !== '' ? $form['timezone'] : DEFAULT_TIMEZONE;
$signupPrograms = land_visual_signup_catalog();
$signupPortals = signup_portal_steps();
$defaultSignupProgram = array_key_first($signupPrograms) ?: 'culbu1on';

try {
    $selectedSignupProgram = $form['land_program'] !== ''
        ? validate_land_visual_program($form['land_program'])
        : $defaultSignupProgram;
} catch (InvalidArgumentException $exception) {
    $selectedSignupProgram = $defaultSignupProgram;
}

$signupPreviewSeed = implode('|', [$previewSlug, $previewTimezone, 'signup-preview']);
$selectedSignupDefinition = $signupPrograms[$selectedSignupProgram] ?? land_visual_program_definition($selectedSignupProgram);
[$selectedSignupMinLambda, $selectedSignupMaxLambda] = land_visual_lambda_range($selectedSignupProgram);
$defaultSignupLambda = land_visual_default_lambda($selectedSignupProgram, $signupPreviewSeed);

try {
    $selectedSignupLambda = $form['lambda_nm'] !== ''
        ? validate_land_visual_lambda((int) $form['lambda_nm'], $selectedSignupProgram)
        : $defaultSignupLambda;
} catch (InvalidArgumentException $exception) {
$selectedSignupLambda = $defaultSignupLambda;
}

$selectedSignupLabel = (string) ($selectedSignupDefinition['label'] ?? $selectedSignupProgram);
$selectedSignupTone = (string) ($selectedSignupDefinition['tone'] ?? '');
$homeVisualOnly = $isSpatialSurface || $isLabSurface;
$dailyStream = str3m_build_daily_stream(null);
$dailyTextItem = is_array($dailyStream['items']['text'] ?? null) ? $dailyStream['items']['text'] : null;
$dailyImageItem = is_array($dailyStream['items']['image'] ?? null) ? $dailyStream['items']['image'] : null;
$dailyAudioItem = is_array($dailyStream['items']['audio'] ?? null) ? $dailyStream['items']['audio'] : null;
$dailyTextBody = $dailyTextItem ? str3m_load_text_body($dailyTextItem) : '';
$dailyTextExcerpt = trim((string) (($dailyTextItem['meta']['excerpt'] ?? '') ?: ''));
$dailyImagePath = $dailyImageItem ? str3m_resolve_media_path($dailyImageItem) : '';
$dailyAudioPath = $dailyAudioItem ? str3m_resolve_media_path($dailyAudioItem) : '';
$activeVisualProfile = $authenticatedLand
    ? land_visual_profile($authenticatedLand)
    : land_collective_profile((string) ($dailyStream['mood'] ?? 'calm'));
$activeLandProgram = (string) ($activeVisualProfile['program'] ?? 'collective');
$activeLandLabel = (string) ($activeVisualProfile['label'] ?? 'collectif');
$activeLandTone = (string) ($activeVisualProfile['tone'] ?? 'str3m public');
$activeLambda = (int) ($activeVisualProfile['lambda_nm'] ?? 548);
$activeLandSlug = $authenticatedLand ? (string) ($authenticatedLand['slug'] ?? '') : '';
$activeLandUsername = $authenticatedLand ? (string) ($authenticatedLand['username'] ?? '') : '';
$connectionNeedleAngle = $authenticatedLand
    ? (int) round(-34 + (($activeLambda - 380) / 400) * 68)
    : -38;
$connectionNeedleAngle = max(-44, min(44, $connectionNeedleAngle));
$connectionNeedleClass = $connectionNeedleAngle < -12
    ? ' connection-meter--low'
    : ($connectionNeedleAngle > 12 ? ' connection-meter--high' : ' connection-meter--mid');
$homeHeroVuState = $connectionNeedleAngle < -12
    ? 'low'
    : ($connectionNeedleAngle > 12 ? 'high' : 'mid');
$connectionStatusText = $authenticatedLand ? 'terre liée 3h33' : 'surface publique';

$pdoConn = null;
if (isset($pdo) && $pdo instanceof PDO) {
    $pdoConn = $pdo;
} elseif (function_exists('get_pdo')) {
    try {
        $candidate = get_pdo();
        if ($candidate instanceof PDO) {
            $pdoConn = $candidate;
        }
    } catch (Throwable $exception) {
        $pdoConn = null;
    }
}

$signalReady = false;
$unreadSignal = 0;
$signalIdentityLabel = '';
if ($authenticatedLand) {
    try {
        $signalReady = signal_mail_tables_ready();
        if ($signalReady) {
            $unreadSignal = signal_unread_total($authenticatedLand);
            $signalMailbox = signal_mailbox_for_land($authenticatedLand);
            $signalIdentityLabel = signal_identity_status_label((string) ($signalMailbox['identity_status'] ?? SIGNAL_IDENTITY_UNVERIFIED));
        }
    } catch (Throwable $exception) {
        $signalReady = false;
        $unreadSignal = 0;
        $signalIdentityLabel = '';
    }
}

$homeStatusLabel = $authenticatedLand ? 'terre liée' : 'surface collective';
$homeLead = $authenticatedLand
    ? 'Le tore suit la fréquence de ta terre. Ouvrir, écrire, dériver.'
    : 'Trois portes : public, terre, 0wlslw0.';
$homePrimaryActionHref = $authenticatedLand
    ? o_route_href('/land', ['u' => $activeLandSlug])
    : o_route_href('/rejoindre');
$guideHref = o_route_href('/0wlslw0');
$homeHref = o_route_href('/');
$signalHref = o_route_href('/signal');
$str3mHref = o_route_href('/str3m');
$mapHref = o_route_href('/map');
$azaHref = o_route_href('/aza');
$joinHref = o_route_href('/rejoindre');
$logoutHref = o_route_href('/logout.php');
$promptSeeds = guide_prompt_seeds();
$homeHeroLineOne = $authenticatedLand ? 'Ta terre' : 'Le tore';
$homeHeroLineTwo = $authenticatedLand ? 'module le tore.' : 'écoute le monde réel.';
$homeThresholdHint = $authenticatedLand
    ? 'Le noyau reste simple : terre, adresse, courant.'
    : 'Comprendre sans quitter l’entrée.';
$membraneBridgeHref = plasma_bridge_url();
$labSensorEndpointHref = o_route_href('/ingest/sensor');
$labPublicPlasmaFeedHref = plasma_feed_url();
$labQaIslandHref = o_route_href('/island', ['u' => 'qa-multimatiere']);
$labPocketHref = 'https://pocket.lab.sowwwl.cloud/';
$labApiHealthHref = 'https://api.lab.sowwwl.cloud/healthz';
$labSensorConfigured = trim((string) (getenv('SOWWWL_PI_TOKEN') ?: '')) !== '';
$labRecentPlasmaEvents = $isLabSurface ? plasma_recent_events(6) : [];
$labPlasmaWeather = plasma_weather_from_events($labRecentPlasmaEvents);
$spatialSurfaceHostLabel = surface_brand_label($host);
$spatialSurfaceEyebrow = $isSowwwlIo ? 'surface spatiale / vision / casque' : 'surface torique / monde reel';
$spatialSurfaceTitle = $isSowwwlIo ? 'Le tore s ouvre dans l espace.' : 'Le tore écoute le monde réel.';
$spatialSurfaceLead = $isSowwwlIo
    ? 'Ici, la surface devient volume. Regard, geste, voix, lumière et orientation préparent un client spatial pour casque.'
    : 'Ici, la surface devient membrane. Mouvement, souffle, lumière et grain entrent, puis le tore les rend lisibles.';
$spatialMappingModeLabel = $isSowwwlIo ? 'espace / plasma / tore' : 'réalité / plasma / tore';
$spatialMappingTitle = $isSowwwlIo ? 'Le volume filtre ce qu il reçoit.' : 'La peau filtre ce qu’elle reçoit.';
$spatialMappingCopy = $isSowwwlIo
    ? 'Le geste touche, le plasma traduit, le tore ouvre une lecture spatiale.'
    : 'Le réel touche, le plasma traduit, le tore ouvre la lecture.';
$spatialMappingBadge = $isSowwwlIo ? 'spatial preview' : 'real-world map';
$spatialCameraTitle = $isSowwwlIo ? 'La couche spatiale attend un geste.' : 'La membrane attend un geste.';
$spatialCameraStatus = $isSowwwlIo
    ? 'Active la couche pour ouvrir mouvement, voix, lumière, caméra et veille active, puis préparer une lecture spatiale du tore. Terre et Mine permet aussi de tester la montée sans capteurs. Aucune image brute n est envoyée. Si le pont plasma est actif, seuls des signaux synthétiques quittent cette couche.'
    : 'Active la membrane pour ouvrir mouvement, voix, lumière, caméra et veille active, puis laisser le téléphone jouer un thérémin local et accorder légèrement la voix. Terre et Mine permet aussi de tester la montée sans capteurs. Aucune image brute n est envoyée. Si le pont plasma est actif, seuls des signaux synthétiques quittent cette couche.';
$spatialDeviceNote = $isSowwwlIo
    ? 'Le web pilote ici silence, niveau, haptique, partage et mode app. Un client visionOS ou Quest pourra ensuite relier le tore à des permissions spatiales natives plus fines.'
    : 'Le web pilote ici silence, niveau, haptique, partage et mode app. Un wrapper natif pourra ensuite donner le silence et le volume réels du téléphone.';
$spatialGestureTitle = $isSowwwlIo
    ? 'Traverse, puis laisse regard, geste et appareil infléchir le tore.'
    : 'Traverse, puis laisse le téléphone infléchir le tore.';
$spatialGestureCopy = $isSowwwlIo
    ? ($isSpatialHeadsetMode
        ? 'Mode casque web: Tab, flèches, focus large et clic gardent la lecture stable. Le regard, le pinch et l ancrage spatial viendront avec le client natif.'
        : 'Mode écran: glisse ou pointe pour pivoter, puis ouvre les routes avant de basculer en mode casque web. Le centre et le geste en O ouvrent toujours 0wlslw0.')
    : 'Glisse pour pivoter. Sur mobile, l orientation et le mouvement déplacent aussi la peau. Le centre et le geste en O ouvrent toujours 0wlslw0.';
$torusAriaLabel = $isSowwwlIo
    ? ($isSpatialHeadsetMode
        ? 'Torus ambiant : tabulation, flèches, focus large et clic gardent la dérive stable en mode casque web. 0wlslw0 reste au centre comme porte rapide.'
        : 'Torus ambiant : pointe ou glisse pour prendre un repère en mode écran, puis flèches pour dériver au clavier. 0wlslw0 reste au centre comme porte rapide.')
    : 'Torus ambiant : glisser pour pivoter, roulette pour traverser, flèches pour dériver. Sur mobile, un appui long puis une glisse permettent aussi de naviguer. Swipe gauche vers Signal, haut vers Str3m, droite vers aZa, bas vers le noyau. Le centre ou un geste en O ouvrent aussi 0wlslw0.';
$spatialModeScreenHref = $isSowwwlIo ? o_current_route_href(['spatial' => null], $host, false) : '';
$spatialModeHeadsetHref = $isSowwwlIo ? o_current_route_href(['spatial' => 'headset'], $host, false) : '';
$spatialModeTitle = $isSowwwlIo
    ? ($isSpatialHeadsetMode ? 'Mode casque web actif.' : 'Mode écran actif.')
    : '';
$spatialModeCopy = $isSowwwlIo
    ? ($isSpatialHeadsetMode
        ? 'Cette passe privilégie le focus large, le clavier et les actions franches pour tester un casque dès maintenant, sans promettre encore le vrai passthrough ni les gestes natifs.'
        : 'Cette passe garde une lecture écran plus souple pour maquetter, puis permet de basculer explicitement en mode casque quand on veut tester le parcours spatial.')
    : '';
$pageHeadVariant = $isSowwwlIo ? 'io' : ($isSowwwlXyz ? 'xyz' : ($isLabSurface ? 'lab' : 'main'));
$pageDescription = $isLabSurface
    ? 'O. Lab — atelier mobile du tore pour capteurs, pocket, plasma et livraison différée.'
    : ($isSowwwlIo
        ? 'SOWWWL IO — surface spatiale du tore pour Vision Pro, casques XR et lecture située.'
        : (SITE_TITLE . ' — entrer publiquement, poser une terre, ou passer par 0wlslw0.'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= h($pageDescription) ?>">
    <meta name="theme-color" content="#09090b">
    <title><?= h(SITE_TITLE) ?></title>
<?= render_o_page_head_assets($pageHeadVariant) ?>
</head>
<body
    class="experience home<?= $isSpatialSurface ? ' xyz-surface-view' : '' ?><?= $isSowwwlIo ? ' io-surface-view' : '' ?><?= $isSpatialHeadsetMode ? ' io-headset-mode' : '' ?><?= $isLabSurface ? ' lab-console-view' : '' ?>"
    data-land-program="<?= h($activeLandProgram) ?>"
    data-land-label="<?= h($activeLandLabel) ?>"
    data-land-lambda="<?= h((string) $activeLambda) ?>"
    data-land-tone="<?= h($activeLandTone) ?>"
>
<?= render_skip_link() ?>
<?= render_nucleus_banner($isLabSurface ? 'atelier' : 'noyau') ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

<details
    class="connection-meter<?= $authenticatedLand ? ' is-linked' : ' is-public' ?><?= h($connectionNeedleClass) ?>"
    id="connexion"
    data-corner-dock
    data-corner-dock-side="left"
    data-corner-dock-priority="primary"
    aria-labelledby="connection-meter-title"
    open
>
    <summary class="connection-meter__toggle">
        <span class="corner-dock-toggle__kicker">Connexion</span>
        <strong><?= h($authenticatedLand ? $connectionStatusText : 'retrouver une terre') ?></strong>
        <span class="corner-dock-toggle__meta"><?= $authenticatedLand ? h('@' . $activeLandSlug) : 'ouvrir' ?></span>
    </summary>

    <div class="connection-meter__dial" aria-hidden="true">
        <span class="connection-meter__arc"></span>
        <span class="connection-meter__tick connection-meter__tick--left"></span>
        <span class="connection-meter__tick connection-meter__tick--center"></span>
        <span class="connection-meter__tick connection-meter__tick--right"></span>
        <span class="connection-meter__needle"></span>
        <span class="connection-meter__pin"></span>
    </div>

    <div class="connection-meter__body">
        <div class="connection-meter__head">
            <span class="summary-label">VU connexion</span>
            <strong id="connection-meter-title"><?= h($connectionStatusText) ?></strong>
        </div>

        <?php if ($authenticatedLand): ?>
            <p class="connection-meter__copy">λ <?= h((string) $activeLambda) ?> nm · <?= h($activeLandUsername) ?></p>
            <div class="connection-meter__actions">
                <a class="pill-link" href="<?= h(o_route_href('/land', ['u' => $activeLandSlug])) ?>">ouvrir</a>
                <a class="ghost-link" href="<?= h($logoutHref) ?>">retirer</a>
            </div>
        <?php else: ?>
            <form method="post" action="<?= h($homeHref) ?>#connexion" class="connection-meter__form" autocomplete="on">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <label>
                    <span>Terre</span>
                    <input
                        type="text"
                        name="login_identifier"
                        placeholder="nom"
                        required
                        value="<?= h($form['login_identifier']) ?>"
                        autocomplete="username"
                    >
                </label>
                <label>
                    <span>Secret</span>
                    <input
                        type="password"
                        name="password"
                        placeholder="secret"
                        required
                        autocomplete="current-password"
                    >
                </label>
                <button type="submit">entrer</button>
            </form>
            <a class="connection-meter__create" href="<?= h($joinHref) ?>">poser une terre</a>
        <?php endif; ?>
    </div>
</details>

<div class="world-container" aria-hidden="true">
    <?php if ($isSpatialSurface): ?>
    <div
        class="xyz-camera-layer"
        data-xyz-camera-root
        data-xyz-plasma-bridge="<?= h($membraneBridgeHref) ?>"
        data-xyz-plasma-land="<?= h($activeLandSlug) ?>"
    >
        <video
            class="xyz-camera-layer__video"
            data-xyz-camera-video
            autoplay
            muted
            playsinline
            aria-hidden="true"
        ></video>
        <div class="xyz-camera-layer__fallback" data-xyz-camera-fallback aria-hidden="true"></div>
    </div>
    <?php endif; ?>
    <canvas
        id="torus-ambient"
        class="main-torus"
        data-torus-cloud
        data-land-type="<?= h($activeLandProgram) ?>"
        data-land-label="<?= h($activeLandLabel) ?>"
        data-lambda="<?= h((string) $activeLambda) ?>"
        data-stream-mood="<?= h((string) ($dailyStream['mood'] ?? 'calm')) ?>"
        tabindex="0"
        role="img"
        aria-label="<?= h($torusAriaLabel) ?>"
    ></canvas>
</div>

<main <?= main_landmark_attrs() ?> class="layout ui-overlay">
    <?php if ($isSpatialSurface): ?>
    <section class="xyz-surface-shell reveal" data-xyz-surface>
        <div class="xyz-surface-shell__veil" aria-hidden="true">
            <span class="xyz-surface-shell__ring xyz-surface-shell__ring--outer"></span>
            <span class="xyz-surface-shell__ring xyz-surface-shell__ring--inner"></span>
            <span class="xyz-surface-shell__pulse"></span>
        </div>

        <header class="xyz-surface-head">
            <p class="eyebrow xyz-surface-head__eyebrow"><strong><?= h($spatialSurfaceHostLabel) ?></strong> <span><?= h($spatialSurfaceEyebrow) ?></span></p>
            <h1 class="xyz-surface-head__title"><?= h($spatialSurfaceTitle) ?></h1>
            <p class="lead xyz-surface-head__lead"><?= h($spatialSurfaceLead) ?></p>

            <div class="xyz-surface-actions">
                <button type="button" class="pill-link xyz-camera-toggle" data-xyz-camera-start>Activer la membrane</button>
                <button type="button" class="ghost-link xyz-camera-toggle" data-xyz-camera-demo aria-pressed="false">Terre &amp; Mine</button>
                <button type="button" class="ghost-link xyz-camera-toggle hidden" data-xyz-camera-stop>Relâcher la membrane</button>
                <a class="ghost-link" href="<?= h($guideHref) ?>">Passer par 0wlslw0</a>
            </div>

            <div class="xyz-surface-meta" aria-label="Signature de la surface">
                <span class="badge badge-glass">λ <?= h((string) $activeLambda) ?> nm</span>
                <span class="badge badge-glass"><?= h($activeLandLabel) ?></span>
                <span class="badge badge-glass"><?= h((string) ($dailyStream['mood'] ?? 'calm')) ?></span>
                <span class="badge badge-glass">local d’abord</span>
            </div>
        </header>

        <div class="xyz-surface-grid">
            <section class="panel reveal mapping-panel mapping-panel--genie xyz-surface-mapping" id="mapping" aria-labelledby="mapping-title" data-mapping-genie data-mapping-theme="real">
                <div class="mapping-panel__veil" aria-hidden="true">
                    <span class="mapping-panel__veil-orbit mapping-panel__veil-orbit--outer"></span>
                    <span class="mapping-panel__veil-orbit mapping-panel__veil-orbit--inner"></span>
                    <span class="mapping-panel__veil-glow"></span>
                </div>

                <div class="section-topline mapping-panel__topline">
                    <div>
                        <p class="eyebrow mapping-panel__eyebrow">
                            <strong><?= h($spatialSurfaceHostLabel) ?></strong>
                            <span><?= h($spatialMappingModeLabel) ?></span>
                        </p>
                        <h2 id="mapping-title"><?= h($spatialMappingTitle) ?></h2>
                        <p class="panel-copy mapping-panel__copy"><?= h($spatialMappingCopy) ?></p>
                    </div>
                    <span class="badge mapping-panel__badge"><?= h($spatialMappingBadge) ?></span>
                </div>

                <div class="mapping-panel__scene">
                    <div class="mapping-genie" role="list" aria-label="Cartographie du tore" data-mapping-genie-list>
                        <button
                            type="button"
                            class="mapping-genie-card mapping-genie-card--real is-active"
                            data-mapping-card
                            data-mapping-tone="real"
                            data-mapping-label="Réalité"
                            data-mapping-whisper="Rue, souffle, corps, lumière : le monde avant sa traduction."
                            data-mapping-summary="La réalité contient les phénomènes, les gestes, les traces et les intensités qui n’ont pas encore trouvé leur forme navigable."
                            aria-expanded="true"
                            aria-pressed="true"
                            aria-describedby="mapping-keys"
                        >
                            <span class="mapping-genie-card__mist" aria-hidden="true"></span>
                            <span class="mapping-genie-card__sigil" aria-hidden="true">🌍</span>
                            <span class="mapping-genie-card__head">
                                <span class="summary-label">plan 01</span>
                                <strong>Réalité</strong>
                            </span>
                            <span class="mapping-genie-card__body">Présences, sons, météo, rencontres, lumière, usage. Tout ce qui touche avant d’être lu.</span>
                        </button>

                        <div class="mapping-genie-link" aria-hidden="true">
                            <span class="mapping-genie-link__line"></span>
                            <span class="mapping-genie-link__label">traduction</span>
                        </div>

                        <button
                            type="button"
                            class="mapping-genie-card mapping-genie-card--plasma"
                            data-mapping-card
                            data-mapping-tone="plasma"
                            data-mapping-label="Plasma"
                            data-mapping-whisper="Le flux garde, transforme, relie."
                            data-mapping-summary="Le plasma est la couche de calcul, de mémoire et de circulation. Il transporte le réel jusqu’à la surface sous forme de signes, de données et de rythme."
                            aria-expanded="false"
                            aria-pressed="false"
                            aria-describedby="mapping-keys"
                        >
                            <span class="mapping-genie-card__mist" aria-hidden="true"></span>
                            <span class="mapping-genie-card__sigil" aria-hidden="true">💧</span>
                            <span class="mapping-genie-card__head">
                                <span class="summary-label">plan 02</span>
                                <strong>Plasma</strong>
                            </span>
                            <span class="mapping-genie-card__body">Flux, mémoire, calcul, médiation. La couche fluide qui rend le réel transmissible sans l’éteindre.</span>
                        </button>

                        <div class="mapping-genie-link" aria-hidden="true">
                            <span class="mapping-genie-link__line"></span>
                            <span class="mapping-genie-link__label">déploiement</span>
                        </div>

                        <button
                            type="button"
                            class="mapping-genie-card mapping-genie-card--torus"
                            data-mapping-card
                            data-mapping-tone="torus"
                            data-mapping-label="Tore"
                            data-mapping-whisper="La surface devient seuil, navigation, orientation."
                            data-mapping-summary="Le tore est la peau visible de Sowwwl. Il accueille la projection du réel et permet d’entrer dans le réseau par dérive, lecture et résonance."
                            aria-expanded="false"
                            aria-pressed="false"
                            aria-describedby="mapping-keys"
                        >
                            <span class="mapping-genie-card__mist" aria-hidden="true"></span>
                            <span class="mapping-genie-card__sigil" aria-hidden="true">🌀</span>
                            <span class="mapping-genie-card__head">
                                <span class="summary-label">plan 03</span>
                                <strong>Tore</strong>
                            </span>
                            <span class="mapping-genie-card__body">Une membrane navigable où les intensités deviennent lecture, interface et dérive située.</span>
                        </button>
                    </div>

                    <aside class="mapping-chorus xyz-surface-chorus" aria-live="polite">
                        <span class="summary-label">écho actif</span>
                        <strong class="mapping-chorus__title" data-mapping-active-label>Réalité</strong>
                        <p class="mapping-chorus__whisper" data-mapping-active-whisper>Rue, souffle, corps, lumière : le monde avant sa traduction.</p>
                        <p class="mapping-chorus__summary" data-mapping-active-summary>La réalité contient les phénomènes, les gestes, les traces et les intensités qui n’ont pas encore trouvé leur forme navigable.</p>
                        <p class="mapping-chorus__ra" data-mapping-ra-note>Quand la membrane s ouvre, la couche dominante peut reprendre la main ici pour garder la lecture située.</p>
                        <p class="mapping-chorus__hint" id="mapping-keys">Tab pour parcourir chaque plan. Entrée ou clic pour l activer. En mode casque web, les flèches, Home et End gardent aussi la dérive.</p>
                        <div class="mapping-chorus__meter" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </aside>
                </div>

                <p class="mapping-panel__reading"><strong>Lecture&nbsp;:</strong> le <span class="mapping-panel__accent mapping-panel__accent--plasma">plasma</span> fait le lien entre <span class="mapping-panel__accent mapping-panel__accent--real">la réalité</span> et <span class="mapping-panel__accent mapping-panel__accent--torus">la surface torique</span>. Le tore n’est pas au-dessus du monde&nbsp;: il s’y branche.</p>
            </section>

            <aside class="xyz-surface-aside reveal">
                <article class="xyz-surface-note xyz-surface-note--camera" data-xyz-camera-panel>
                    <span class="summary-label">rituel</span>
                    <strong data-xyz-camera-title><?= h($spatialCameraTitle) ?></strong>
                    <p class="panel-copy" data-xyz-camera-status><?= h($spatialCameraStatus) ?></p>
                    <div class="xyz-surface-sensor-grid" aria-label="État direct de la membrane">
                        <p><span>orientation</span><strong data-xyz-sensor-orientation>en attente</strong></p>
                        <p><span>mouvement</span><strong data-xyz-sensor-motion>en attente</strong></p>
                        <p><span>lumière</span><strong data-xyz-sensor-light>en attente</strong></p>
                        <p><span>voix</span><strong data-xyz-sensor-audio>en attente</strong></p>
                        <p><span>caméra</span><strong data-xyz-sensor-camera>en attente</strong></p>
                        <p><span>veille</span><strong data-xyz-sensor-wake>en attente</strong></p>
                    </div>
                    <div class="device-bridge-panel" data-device-bridge-root data-device-context="xyz">
                        <span class="summary-label">appareil</span>
                        <div class="device-bridge-grid" aria-label="État téléphone">
                            <p><span>silence</span><strong data-device-silence-status>web sonore</strong></p>
                            <p><span>volume</span><strong data-device-volume-status>82%</strong></p>
                            <p><span>haptique</span><strong data-device-haptics-status>sur demande</strong></p>
                            <p><span>visibilité</span><strong data-device-visibility-status>visible</strong></p>
                            <p><span>app</span><strong data-device-standalone-status>navigateur</strong></p>
                            <p><span>natif</span><strong data-device-native-status>web seul</strong></p>
                        </div>
                        <div class="device-bridge-controls">
                            <button type="button" class="ghost-link" data-device-silence-toggle>Silence web</button>
                            <label class="device-bridge-range">
                                <span>niveau O.</span>
                                <input type="range" min="0" max="100" step="1" value="82" data-device-volume-input>
                                <strong data-device-volume-readout>82%</strong>
                            </label>
                            <label class="device-bridge-range">
                                <span>écho voix</span>
                                <input type="range" min="0" max="100" step="1" value="18" data-xyz-voice-echo-input>
                                <strong data-xyz-voice-echo-readout>18%</strong>
                            </label>
                            <div class="device-bridge-actions">
                                <button type="button" class="ghost-link" data-device-install hidden>Installer</button>
                                <button type="button" class="ghost-link" data-device-share>Partager</button>
                            </div>
                        </div>
                        <p class="panel-copy device-bridge-note" data-device-native-note><?= h($spatialDeviceNote) ?></p>
                    </div>
                    <div class="xyz-world-instrument" data-xyz-instrument-root>
                        <div class="xyz-world-instrument__head">
                            <span class="summary-label">monde instrument</span>
                            <div class="xyz-world-instrument__camera-switch" role="group" aria-label="Perspective caméra">
                                <button type="button" class="ghost-link xyz-world-instrument__camera-button" data-xyz-camera-facing-button="user" aria-pressed="false">visage</button>
                                <button type="button" class="ghost-link xyz-world-instrument__camera-button" data-xyz-camera-facing-button="environment" aria-pressed="false">paysage</button>
                            </div>
                        </div>
                        <div class="xyz-world-instrument__grid" aria-label="État du monde comme instrument">
                            <p><span>vue</span><strong data-xyz-instrument-view>visage</strong></p>
                            <p><span>focus</span><strong data-xyz-instrument-focus>souffle proche</strong></p>
                            <p><span>corps</span><strong data-xyz-instrument-body>corps tenu</strong></p>
                            <p><span>mains</span><strong data-xyz-instrument-touch>aucune prise</strong></p>
                            <p><span>lumière</span><strong data-xyz-instrument-light>lueur mixte</strong></p>
                        </div>
                        <div class="xyz-world-instrument__stage" data-xyz-instrument-stage tabindex="0" aria-label="Surface de jeu Terre et Mine, jouable au doigt, au pointeur et au clavier">
                            <span class="xyz-world-instrument__axis xyz-world-instrument__axis--x" aria-hidden="true"></span>
                            <span class="xyz-world-instrument__axis xyz-world-instrument__axis--y" aria-hidden="true"></span>
                            <span class="xyz-world-instrument__orb xyz-world-instrument__orb--terre" data-xyz-instrument-terre aria-hidden="true"></span>
                            <span class="xyz-world-instrument__orb xyz-world-instrument__orb--mine" data-xyz-instrument-mine aria-hidden="true"></span>
                            <p class="xyz-world-instrument__hint" data-xyz-instrument-stage-copy>Glisse une ou deux mains ici. Terre porte le fond, Mine taille la note. WASD et flèches fonctionnent aussi. Bascule en paysage pour faire jouer le dehors.</p>
                        </div>
                        <p class="panel-copy xyz-world-instrument__copy" data-xyz-world-copy>Le monde reste un instrument: visage, corps, lumière, paysage et toucher peuvent tous nourrir le tore.</p>
                    </div>
                    <div class="xyz-music-guide" data-xyz-music-guide-root>
                        <span class="summary-label">atelier</span>
                        <div class="xyz-music-guide__grid" aria-label="Lecture musicale du tore">
                            <p><span>mode</span><strong data-xyz-music-mode>Mi mineur</strong></p>
                            <p><span>note</span><strong data-xyz-music-note>Mi2</strong></p>
                            <p><span>rythme</span><strong data-xyz-music-rhythm>drone stable</strong></p>
                            <p><span>terre</span><strong data-xyz-hand-terre-state>porte le champ</strong></p>
                            <p><span>mine</span><strong data-xyz-hand-mine-state>creuse la note</strong></p>
                        </div>
                        <div class="xyz-music-guide__duet" aria-label="Partition Terre et Mine">
                            <article class="xyz-music-guide__hand xyz-music-guide__hand--terre">
                                <span class="summary-label">main terre</span>
                                <strong data-xyz-hand-terre-title>Elle porte.</strong>
                                <p data-xyz-hand-terre-copy>Elle stabilise le mode, ouvre ou ferme la lumière, puis garde le drone respirable.</p>
                            </article>
                            <article class="xyz-music-guide__hand xyz-music-guide__hand--mine">
                                <span class="summary-label">main mine</span>
                                <strong data-xyz-hand-mine-title>Elle creuse.</strong>
                                <p data-xyz-hand-mine-copy>Elle tient la note, déclenche l’accent, cherche la voix et relance le shaker.</p>
                            </article>
                        </div>
                        <p class="xyz-music-guide__duet-state" data-xyz-duet-state>Terre porte le seuil, Mine y ouvre un trajet.</p>
                        <p class="panel-copy" data-xyz-music-guide>La lumière ouvre le majeur, l’ombre garde le mineur, l’inclinaison tient la note, la voix s’y accroche et la secousse déclenche un shaker.</p>
                    </div>
                </article>

                <article class="xyz-surface-note xyz-surface-note--modulation" data-xyz-ar-modulation data-xyz-ar-mode="<?= h($isSowwwlIo ? 'anchor' : 'weave') ?>">
                    <span class="summary-label">modulation RA</span>
                    <strong data-xyz-ar-title>Le tore se pose sur le monde.</strong>
                    <p class="panel-copy" data-xyz-ar-status>La réalité garde encore la main. Active la membrane pour laisser les trois couches se répartir.</p>
                    <div class="xyz-ar-mode-switch" aria-label="Mode de modulation en réalité augmentée">
                        <button type="button" class="ghost-link xyz-ar-mode-button" data-xyz-ar-mode-button="anchor" aria-pressed="true">Ancrer</button>
                        <button type="button" class="ghost-link xyz-ar-mode-button" data-xyz-ar-mode-button="translate" aria-pressed="false">Traduire</button>
                        <button type="button" class="ghost-link xyz-ar-mode-button" data-xyz-ar-mode-button="loop" aria-pressed="false">Boucler</button>
                        <button type="button" class="ghost-link xyz-ar-mode-button" data-xyz-ar-mode-button="weave" aria-pressed="false">Tresser</button>
                    </div>
                    <div class="xyz-ar-layer-grid" aria-label="Poids des trois couches">
                        <article class="xyz-ar-layer xyz-ar-layer--real" data-xyz-ar-layer="real">
                            <div class="xyz-ar-layer__head">
                                <span>réalité</span>
                                <strong data-xyz-ar-real-value>34%</strong>
                            </div>
                            <div class="xyz-ar-layer__meter" aria-hidden="true"><span data-xyz-ar-real-meter></span></div>
                            <p data-xyz-ar-real-copy>Plans, bords, souffle, lumière, obstacles: ce qui ancre le monde avant l inscription.</p>
                        </article>
                        <article class="xyz-ar-layer xyz-ar-layer--plasma" data-xyz-ar-layer="plasma">
                            <div class="xyz-ar-layer__head">
                                <span>plasma</span>
                                <strong data-xyz-ar-plasma-value>33%</strong>
                            </div>
                            <div class="xyz-ar-layer__meter" aria-hidden="true"><span data-xyz-ar-plasma-meter></span></div>
                            <p data-xyz-ar-plasma-copy>Flux, mémoire, météo, voix, signes et calcul: la couche qui traduit sans éteindre.</p>
                        </article>
                        <article class="xyz-ar-layer xyz-ar-layer--torus" data-xyz-ar-layer="torus">
                            <div class="xyz-ar-layer__head">
                                <span>tore</span>
                                <strong data-xyz-ar-torus-value>33%</strong>
                            </div>
                            <div class="xyz-ar-layer__meter" aria-hidden="true"><span data-xyz-ar-torus-meter></span></div>
                            <p data-xyz-ar-torus-copy>Seuils, routes, prises, zones et dérive: la peau qui boucle l espace en interface.</p>
                        </article>
                    </div>
                    <p class="xyz-ar-directive" data-xyz-ar-directive>Directive: garder les plans du monde lisibles, laisser le plasma annoter, puis ouvrir le tore seulement là où il doit prendre.</p>
                    <div class="xyz-ar-pilot" data-xyz-ar-pilot>
                        <p class="xyz-ar-pilot__title" data-xyz-ar-pilot-title>Prise active: cadrer le volume.</p>
                        <p class="xyz-ar-pilot__copy" data-xyz-ar-pilot-copy>Commence par la carte pour tenir les plans, puis repasse par 0wlslw0 si tu dois réorienter la lecture située.</p>
                        <div class="xyz-surface-route-links xyz-surface-route-links--ar" aria-label="Routes conseillées en réalité augmentée">
                            <a class="ghost-link" href="<?= h($mapHref) ?>" data-xyz-ar-primary-link>Ouvrir Map</a>
                            <a class="ghost-link" href="<?= h($guideHref) ?>" data-xyz-ar-secondary-link>Passer par 0wlslw0</a>
                        </div>
                    </div>
                    <p class="xyz-ar-usage" data-xyz-ar-usage>Raccourcis: R ancre, P traduit, T boucle, M tresse. En mode casque web, le tore peut changer de régime sans perdre la lecture située.</p>
                </article>

                <article class="xyz-surface-note">
                    <span class="summary-label">gestes</span>
                    <strong><?= h($spatialGestureTitle) ?></strong>
                    <p class="panel-copy"><?= h($spatialGestureCopy) ?></p>
                </article>

                <?php if ($isSowwwlIo): ?>
                <article class="xyz-surface-note xyz-surface-note--spatial">
                    <span class="summary-label">mode casque</span>
                    <strong><?= h($spatialModeTitle) ?></strong>
                    <p class="panel-copy"><?= h($spatialModeCopy) ?></p>
                    <div class="xyz-surface-route-links xyz-surface-route-links--mode" aria-label="Basculer le mode spatial">
                        <a class="ghost-link" href="<?= h($spatialModeScreenHref) ?>"<?= $isSpatialHeadsetMode ? '' : ' aria-current="page"' ?>>Projection écran</a>
                        <a class="ghost-link" href="<?= h($spatialModeHeadsetHref) ?>"<?= $isSpatialHeadsetMode ? ' aria-current="page"' : '' ?>>Mode casque web</a>
                    </div>
                    <div class="xyz-spatial-duet-routes" aria-label="Routes Terre et Mine">
                        <article class="xyz-spatial-duet-routes__group" data-xyz-route-hand="terre">
                            <span class="summary-label">main terre</span>
                            <strong>Porte et oriente</strong>
                            <p>Ouvrir le seuil, lire le terrain, garder une vue large avant d inciser.</p>
                            <div class="xyz-surface-route-links xyz-surface-route-links--spatial">
                                <a class="ghost-link" href="<?= h($guideHref) ?>">0wlslw0</a>
                                <a class="ghost-link" href="<?= h($mapHref) ?>">Carte</a>
                            </div>
                        </article>
                        <article class="xyz-spatial-duet-routes__group" data-xyz-route-hand="mine">
                            <span class="summary-label">main mine</span>
                            <strong>Incise et relance</strong>
                            <p>Entrer dans une liaison, prendre le courant de face, faire vibrer le détail.</p>
                            <div class="xyz-surface-route-links xyz-surface-route-links--spatial">
                                <a class="ghost-link" href="<?= h($signalHref) ?>">Signal</a>
                                <a class="ghost-link" href="<?= h($str3mHref) ?>">Str3m</a>
                            </div>
                        </article>
                    </div>
                    <p class="panel-copy xyz-spatial-duet-routes__hint">La main dominante du moment éclaire la colonne correspondante. Terre tient l orientation. Mine pousse le passage.</p>
                </article>
                <?php endif; ?>

                <article class="xyz-surface-note">
                    <span class="summary-label">autres dérives</span>
                    <div class="xyz-surface-route-links">
                        <a class="ghost-link" href="<?= h($signalHref) ?>">Signal</a>
                        <a class="ghost-link" href="<?= h($str3mHref) ?>">Str3m</a>
                        <a class="ghost-link" href="<?= h($mapHref) ?>">Carte</a>
                    </div>
                    <p class="panel-copy">Quand la membrane a fini de lire, tu peux ouvrir une enveloppe, dériver dans le courant, ou relire la carte.</p>
                </article>

                <article class="xyz-surface-note">
                    <span class="summary-label">situation</span>
                    <strong><?= h($authenticatedLand ? 'ta terre module le champ' : 'surface publique en écoute') ?></strong>
                    <p class="panel-copy"><?= h($authenticatedLand ? 'Ta fréquence colore déjà la membrane.' : 'Aucune terre liée pour l’instant. La membrane reste collective.') ?></p>
                </article>
            </aside>
        </div>
    </section>
    <?php endif; ?>
    <?php if ($isLabSurface): ?>
    <section
        class="lab-console-shell reveal"
        id="atelier"
        data-lab-console
        data-lab-api-url="<?= h($labApiHealthHref) ?>"
        data-lab-pocket-url="<?= h($labPocketHref) ?>"
        data-lab-qa-url="<?= h($labQaIslandHref) ?>"
        data-lab-sensor-endpoint="<?= h($labSensorEndpointHref) ?>"
        data-lab-plasma-feed="<?= h($labPublicPlasmaFeedHref) ?>"
        data-lab-sensor-configured="<?= $labSensorConfigured ? '1' : '0' ?>"
    >
        <div class="lab-console-shell__veil" aria-hidden="true">
            <span class="lab-console-shell__ring lab-console-shell__ring--outer"></span>
            <span class="lab-console-shell__ring lab-console-shell__ring--inner"></span>
            <span class="lab-console-shell__pulse"></span>
        </div>

        <header class="lab-console-head">
            <p class="eyebrow lab-console-head__eyebrow"><strong>lab.sowwwl.cloud</strong> <span>atelier du tore / 3ternet</span></p>
            <h1 class="lab-console-head__title">L’atelier mobile du tore.</h1>
            <p class="lead lab-console-head__lead">Ici, le lab n’imite plus la home publique. Il active le téléphone, rejoue la présence, garde le plasma visible, puis prépare le passage vers le pocket.</p>

            <div class="lab-console-actions">
                <button type="button" class="pill-link" data-lab-activate>Activer les capteurs</button>
                <button type="button" class="ghost-link" data-lab-replay>Mode replay</button>
                <a class="ghost-link" href="<?= h($guideHref) ?>">0wlslw0</a>
                <a class="ghost-link" href="<?= h($str3mHref) ?>">Str3m</a>
            </div>

            <div class="lab-console-meta" aria-label="Signature du lab">
                <span class="badge badge-glass"><?= h($labSensorConfigured ? 'token capteur prêt' : 'token capteur absent') ?></span>
                <span class="badge badge-glass"><?= h($authenticatedLand ? '@' . $activeLandSlug : 'surface collective') ?></span>
                <span class="badge badge-glass">pocket simulé avant Pi</span>
            </div>

            <p class="lab-console-head__status" data-lab-activation-status>Le tore attend un geste pour ouvrir mouvement, voix, lumière, caméra et veille active.</p>
        </header>

        <div class="lab-console-grid">
            <article class="panel reveal lab-console-card lab-console-card--sensor" data-lab-card="sensor" data-lab-state="idle">
                <div class="lab-console-card__topline">
                    <div>
                        <span class="summary-label">01 · capteurs</span>
                        <h2 class="lab-console-card__title">Le téléphone devient membrane.</h2>
                    </div>
                    <span class="badge badge-glass" data-lab-sensor-badge>en veille</span>
                </div>
                <p class="panel-copy">Gyroscope, accéléromètre, lumière, micro, caméra et écran éveillé alimentent le tore. Quand une API manque, le lab bascule en fallback ou en replay.</p>
                <div class="lab-console-sensor-grid" aria-label="État des capteurs">
                    <p><span>orientation</span><strong data-lab-orientation-status>en attente</strong></p>
                    <p><span>mouvement</span><strong data-lab-motion-status>en attente</strong></p>
                    <p><span>lumière</span><strong data-lab-light-status>en attente</strong></p>
                    <p><span>micro</span><strong data-lab-audio-status>en attente</strong></p>
                    <p><span>caméra</span><strong data-lab-camera-status>en attente</strong></p>
                    <p><span>wake lock</span><strong data-lab-wake-status>en attente</strong></p>
                </div>
                <div class="lab-console-camera-preview-wrap">
                    <video class="lab-console-camera-preview" data-lab-camera-preview playsinline muted aria-hidden="true"></video>
                    <div class="lab-console-camera-preview-fallback" data-lab-camera-fallback>aperçu local</div>
                </div>
                <div class="device-bridge-panel device-bridge-panel--lab" data-device-bridge-root data-device-context="lab">
                    <span class="summary-label">appareil</span>
                    <div class="device-bridge-grid" aria-label="État téléphone">
                        <p><span>silence</span><strong data-device-silence-status>web sonore</strong></p>
                        <p><span>volume</span><strong data-device-volume-status>82%</strong></p>
                        <p><span>haptique</span><strong data-device-haptics-status>sur demande</strong></p>
                        <p><span>visibilité</span><strong data-device-visibility-status>visible</strong></p>
                        <p><span>app</span><strong data-device-standalone-status>navigateur</strong></p>
                        <p><span>natif</span><strong data-device-native-status>web seul</strong></p>
                    </div>
                    <div class="device-bridge-controls">
                        <button type="button" class="ghost-link" data-device-silence-toggle>Silence web</button>
                        <label class="device-bridge-range">
                            <span>niveau O.</span>
                            <input type="range" min="0" max="100" step="1" value="82" data-device-volume-input>
                            <strong data-device-volume-readout>82%</strong>
                        </label>
                        <div class="device-bridge-actions">
                            <button type="button" class="ghost-link" data-device-install hidden>Installer</button>
                            <button type="button" class="ghost-link" data-device-share>Partager</button>
                        </div>
                    </div>
                    <p class="panel-copy device-bridge-note" data-device-native-note>Le lab utilise déjà veille active, haptique, partage et mode app. S’il reçoit un pont natif, il affichera ici le vrai silence et le vrai volume du téléphone.</p>
                </div>
            </article>

            <article class="panel reveal lab-console-card lab-console-card--pocket" data-lab-card="pocket" data-lab-state="idle">
                <div class="lab-console-card__topline">
                    <div>
                        <span class="summary-label">02 · pocket</span>
                        <h2 class="lab-console-card__title">Fake pocket avant le Pi.</h2>
                    </div>
                    <span class="badge badge-glass">présence simulée</span>
                </div>
                <p class="panel-copy">Le pocket du lab est un corps temporaire: il dort, rôde, revient, puis sert de cible aux scénarios de livraison différée.</p>
                <strong class="lab-console-card__lead" data-lab-pocket-status>En veille douce. Le replay peut le faire dériver.</strong>
                <p class="panel-copy" data-lab-pocket-note>Ouvre la route pocket, ou laisse le mode replay alterner sommeil, roaming et retour.</p>
                <div class="action-row">
                    <a class="pill-link" href="<?= h($labPocketHref) ?>">Ouvrir pocket</a>
                    <a class="ghost-link" href="<?= h($labQaIslandHref) ?>">Île QA</a>
                </div>
            </article>

            <article class="panel reveal lab-console-card lab-console-card--api" data-lab-card="api" data-lab-state="idle">
                <div class="lab-console-card__topline">
                    <div>
                        <span class="summary-label">03 · api</span>
                        <h2 class="lab-console-card__title">Le fond parle encore même sans image.</h2>
                    </div>
                    <span class="badge badge-glass">healthz</span>
                </div>
                <p class="panel-copy">Le lab garde un point fixe: la santé de l’API, l’origine capteur, et l’URL qui recevra les premiers signaux physiques.</p>
                <strong class="lab-console-card__lead" data-lab-api-status>Sondage en attente.</strong>
                <p class="panel-copy"><code><?= h($labApiHealthHref) ?></code></p>
                <p class="panel-copy"><code><?= h($labSensorEndpointHref) ?></code><?= $labSensorConfigured ? ' · prêt pour un Bearer token.' : ' · attend encore son token.' ?></p>
            </article>

            <article class="panel reveal lab-console-card lab-console-card--plasma" data-lab-card="plasma" data-lab-state="<?= h((string) ($labPlasmaWeather['tone'] ?? 'idle')) ?>">
                <div class="lab-console-card__topline">
                    <div>
                        <span class="summary-label">04 · plasma</span>
                        <h2 class="lab-console-card__title">Le journal du flux reste lisible.</h2>
                    </div>
                    <span class="badge badge-glass" data-lab-plasma-badge><?= h((string) ($labPlasmaWeather['badge'] ?? ($labRecentPlasmaEvents ? 'traces présentes' : 'aucune trace locale'))) ?></span>
                </div>
                <p class="panel-copy">Le browser n’envoie rien ici sans accord fort. En revanche, le lab relit son plasma local, les traces Pi et la simulation locale.</p>
                <strong class="lab-console-card__lead" data-lab-plasma-status><?= h((string) ($labPlasmaWeather['lead'] ?? 'Aucune trace plasma lue dans le runtime pour l’instant.')) ?></strong>
                <p class="panel-copy lab-console-plasma-copy" data-lab-plasma-weather-copy><?= h((string) ($labPlasmaWeather['detail'] ?? 'Le premier ping capteur apparaîtra ici dès qu’un événement traversera le pont plasma.')) ?></p>
                <ol class="lab-console-trace-list" data-lab-runtime-traces>
                    <?php if ($labRecentPlasmaEvents): ?>
                        <?php foreach ($labRecentPlasmaEvents as $event): ?>
                            <li>
                                <span class="summary-label"><?= h($event['event'] !== '' ? $event['event'] : 'signal') ?></span>
                                <strong><?= h($event['source'] !== '' ? $event['source'] : ($event['camera'] !== '' ? $event['camera'] : 'unknown')) ?></strong>
                                <span><?= h($event['message'] !== '' ? $event['message'] : ($event['timestamp'] !== '' ? $event['timestamp'] : 'trace sans message')) ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>
                            <span class="summary-label">veille</span>
                            <strong>runtime</strong>
                            <span>Le premier ping capteur apparaîtra ici dès qu’un événement traversera le pont plasma.</span>
                        </li>
                    <?php endif; ?>
                </ol>
                <ol class="lab-console-trace-list lab-console-trace-list--session" data-lab-session-traces hidden></ol>
            </article>

            <article class="panel reveal lab-console-card lab-console-card--delivery" data-lab-card="delivery" data-lab-state="idle">
                <div class="lab-console-card__topline">
                    <div>
                        <span class="summary-label">05 · différé</span>
                        <h2 class="lab-console-card__title">Préparer l’absence sans perdre le fil.</h2>
                    </div>
                    <span class="badge badge-glass">roaming</span>
                </div>
                <p class="panel-copy">La logique visée est simple: une terre s’endort, le signal reste en attente, puis le retour du pocket réouvre le passage. Le replay en montre déjà le rythme.</p>
                <strong class="lab-console-card__lead" data-lab-delivery-status>Réveil non rejoué. Le tore attend encore une première séquence.</strong>
                <ul class="lab-console-sequence" aria-label="Séquence d’expérimentation">
                    <li>1. activer les capteurs</li>
                    <li>2. bouger ou parler</li>
                    <li>3. laisser pocket dormir</li>
                    <li>4. relancer en replay</li>
                    <li>5. lire la reprise dans le plasma</li>
                </ul>
                <div class="action-row">
                    <a class="pill-link" href="<?= h($labQaIslandHref) ?>">Relire l’île QA</a>
                    <a class="ghost-link" href="<?= h($signalHref) ?>">Ouvrir Signal</a>
                </div>
            </article>
        </div>
    </section>
    <?php endif; ?>
    <?php if (!$homeVisualOnly): ?>
    <section class="hero-archipelago reveal">
        <article class="world-intro world-intro--entry world-intro--threshold world-intro--vu-<?= h($homeHeroVuState) ?>" data-vu-state="<?= h($homeHeroVuState) ?>">
            <span class="summary-label"><?= h($homeStatusLabel) ?></span>
            <h1 class="world-intro-title <?= $authenticatedLand ? 'world-intro-title--linked' : 'world-intro-title--public' ?>">
                <span class="world-intro-title__line world-intro-title__line--primary"><?= h($homeHeroLineOne) ?></span>
                <span class="world-intro-title__line world-intro-title__line--secondary"><?= h($homeHeroLineTwo) ?></span>
            </h1>
            <p class="lead"><?= h($homeLead) ?></p>
            <div class="home-threshold-links" aria-label="Repères du seuil">
                <a class="ghost-link" href="<?= h($guideHref) ?>">Comprendre avec 0wlslw0</a>
                <?php if ($authenticatedLand): ?>
                    <a class="ghost-link" href="<?= h($signalHref) ?>">Signal<?= $unreadSignal > 0 ? ' · ' . $unreadSignal . ' en attente' : ' · boîte' ?></a>
                <?php else: ?>
                    <a class="ghost-link" href="<?= h($mapHref) ?>">Voir le tore</a>
                <?php endif; ?>
            </div>
            <p class="world-intro-note world-intro-note--threshold"><?= h($homeThresholdHint) ?></p>
        </article>

        <nav class="entry-grid editorial-nav" aria-label="Entrées principales du noyau">
            <p class="entry-grid__prompt">Choisir en un geste. Si tu préfères la voix, dis simplement la phrase indiquée à 0wlslw0.</p>
            <?php if ($authenticatedLand): ?>
                <a href="<?= h($homePrimaryActionHref) ?>" class="entry-card entry-card--primary">
                    <span class="summary-label">01 · terre</span>
                    <strong>Rouvrir ma terre</strong>
                    <span>Revenir immédiatement à ton noyau situé.</span>
                    <small class="entry-card__hint">Dire : « ouvre ma terre »</small>
                </a>
                <a href="<?= h($signalHref) ?>" class="entry-card">
                    <span class="summary-label">02 · adresse</span>
                    <strong>Écrire maintenant</strong>
                    <span>Aller droit vers Signal<?= $unreadSignal > 0 ? ' · ' . $unreadSignal . ' en attente' : '' ?>.</span>
                    <small class="entry-card__hint">Dire : « ouvre Signal »</small>
                </a>
                <a href="<?= h($str3mHref) ?>" class="entry-card">
                    <span class="summary-label">03 · public</span>
                    <strong>Relire le public</strong>
                    <span>Voir le courant avant de replonger dans ta terre.</span>
                    <small class="entry-card__hint">Dire : « ramène-moi vers Str3m »</small>
                </a>
            <?php else: ?>
                <a href="<?= h($str3mHref) ?>" class="entry-card entry-card--primary">
                    <span class="summary-label">01 · public</span>
                    <strong>Voir d’abord</strong>
                    <span>Entrer publiquement dans Str3m et sentir le courant.</span>
                    <small class="entry-card__hint">Dire : « je veux visiter publiquement »</small>
                </a>
                <a href="<?= h($joinHref) ?>" class="entry-card">
                    <span class="summary-label">02 · terre</span>
                    <strong>Poser une terre</strong>
                    <span>Ouvrir un lieu à toi, situé, avec sa fréquence.</span>
                    <small class="entry-card__hint">Dire : « je veux poser une terre »</small>
                </a>
                <a href="<?= h($guideHref) ?>" class="entry-card">
                    <span class="summary-label">03 · 0wlslw0</span>
                    <strong>Me faire guider</strong>
                    <span>Passer par 0wlslw0 pour clarifier vite, puis continuer.</span>
                    <small class="entry-card__hint">Dire : « aide-moi à choisir »</small>
                </a>
            <?php endif; ?>
        </nav>
    </section>
    <?php endif; ?>

    <?php if (!$homeVisualOnly && $authenticatedLand): ?>
    <section class="home-secondary-grid home-secondary-grid--single reveal" id="poser">
        <section class="land-signature home-secondary-panel" aria-label="Signature de la terre">
            <span class="summary-label">Signature</span>
            <strong class="preview-title"><?= h($authenticatedLand ? $activeLandUsername : 'Str3m public') ?></strong>
            <div class="signature-grid">
                <p><span>Programme</span><strong><?= h($activeLandLabel) ?></strong></p>
                <p><span>Longueur d’onde</span><strong>λ <span data-spectral-lambda><?= h((string) $activeLambda) ?></span> nm</strong></p>
                <p><span>Tonalité</span><strong><?= h($activeLandTone) ?></strong></p>
            </div>

            <section class="spectral-tuner" data-spectral-tuner data-default-lambda="<?= h((string) $activeLambda) ?>" data-default-mood="<?= h((string) ($dailyStream['mood'] ?? 'calm')) ?>" aria-labelledby="spectral-tuner-title">
                <div class="spectral-tuner__head">
                    <div>
                        <span class="summary-label">Réglage 24h</span>
                        <strong id="spectral-tuner-title">Dans quel mood es-tu ?</strong>
                    </div>
                    <span class="badge badge-glass spectral-tuner__badge" data-spectral-expiry>mode instantané</span>
                </div>

                <label class="spectral-tuner__label" for="spectral-tuner-range">
                    <span>Fais glisser, puis valide ta longueur d’onde pour 24h.</span>
                    <strong><span data-spectral-mode-name>clair</span> · λ <span data-spectral-lambda><?= h((string) $activeLambda) ?></span> nm</strong>
                </label>

                <input
                    id="spectral-tuner-range"
                    class="spectral-tuner__range"
                    type="range"
                    min="0"
                    max="4"
                    step="1"
                    value="2"
                    data-spectral-range
                    aria-describedby="spectral-tuner-copy"
                >

                <div class="spectral-tuner__stops" aria-hidden="true">
                    <span>brume</span>
                    <span>écume</span>
                    <span>clair</span>
                    <span>braise</span>
                    <span>nuit chaude</span>
                </div>

                <p class="panel-copy spectral-tuner__copy" id="spectral-tuner-copy" data-spectral-copy>Un réglage léger, local à ce navigateur, pour stabiliser ta fréquence de surface pendant 24h.</p>

                <div class="action-row spectral-tuner__actions">
                    <button type="button" data-spectral-save>Valider 24h</button>
                    <button type="button" class="ghost-link spectral-tuner__reset" data-spectral-reset>Relâcher</button>
                </div>
            </section>
            <?php if ($authenticatedLand): ?>
                <div class="action-row auth-action-row">
                    <a class="pill-link" href="<?= h(o_route_href('/land', ['u' => $activeLandSlug])) ?>">Ouvrir la terre</a>
                    <a class="ghost-link" href="<?= h($logoutHref) ?>">Retirer sa présence</a>
                </div>
            <?php endif; ?>
        </section>
    </section>
    <?php endif; ?>

    <?php if (!$homeVisualOnly): ?>
    <footer class="site-footer reveal glass-footer">
        <p><?= (int) $pulse['count'] ?> terres / <?= (int) $pulse['timezones'] ?> fuseaux / I inverse + voix</p>
    </footer>
    <?php endif; ?>
</main>
</body>
</html>
