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
$connectionDockOpen = $authenticatedLand || $message !== '';

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

$homeStatusLabel = $authenticatedLand ? 'terre liée' : 'réseau minimal';
$homeLead = $authenticatedLand
    ? 'Le tore suit la fréquence de ta terre. Ouvrir, écrire, dériver.'
    : 'Un réseau minimal : courant public, terre personnelle, guide discret.';
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
$publicNucleusHref = 'https://sowwwl.com/';
$publicAzaHref = 'https://sowwwl.com/aza';
$publicStr3mHref = 'https://sowwwl.com/str3m';
$publicGuideHref = 'https://0wlslw0.com/';
$publicIoHref = 'https://sowwwl.io/';
$publicXyzHref = 'https://sowwwl.xyz/';
$promptSeeds = guide_prompt_seeds();
$homeHeroLineOne = $authenticatedLand ? 'Ta terre' : 'Réseau';
$homeHeroLineTwo = $authenticatedLand ? 'module le tore.' : 'minimal.';
$homeThresholdHint = $authenticatedLand
    ? 'Le noyau reste simple : terre, adresse, courant.'
    : 'Un point d’entrée simple, sans forcer l’identification.';
$homeStreamMood = (string) ($dailyStream['mood'] ?? 'calm');
$homeStreamTemplate = (string) ($dailyStream['template'] ?? 'empty');
$homeDailyTitle = $dailyTextItem
    ? (string) ($dailyTextItem['title'] ?? 'Texte du jour')
    : ($dailyAudioItem ? (string) ($dailyAudioItem['title'] ?? 'Nappe du jour') : 'Courant public en veille');
$homeDailyCopySource = $dailyTextBody !== ''
    ? $dailyTextBody
    : ($dailyTextExcerpt !== ''
        ? $dailyTextExcerpt
        : (string) ($dailyAudioItem['meta']['excerpt'] ?? $dailyAudioItem['meta']['description'] ?? 'Le courant du jour relie les portes publiques, les terres et le guide sans forcer le passage.'));
$homeDailyCopy = plasma_compact_text($homeDailyCopySource, 220);
$homeDailyAudioTitle = $dailyAudioItem ? (string) ($dailyAudioItem['title'] ?? 'Nappe du jour') : 'Nappe en veille';
$homeDailyImageTitle = $dailyImageItem ? (string) ($dailyImageItem['title'] ?? 'Surface du jour') : 'Surface en attente';
$homeSignalState = $authenticatedLand
    ? ($unreadSignal > 0 ? $unreadSignal . ' en attente' : 'boîte claire')
    : 'liaison possible';
$homeSurfaceProofs = [
    ['label' => 'lambda', 'value' => 'λ ' . $activeLambda . ' nm'],
    ['label' => 'mood', 'value' => $homeStreamMood],
    ['label' => 'terres', 'value' => (string) (int) ($pulse['count'] ?? 0)],
    ['label' => 'fuseaux', 'value' => (string) (int) ($pulse['timezones'] ?? 0)],
];
$homeRouteNodes = [
    [
        'index' => '01',
        'kicker' => 'str3m',
        'title' => 'Lire le courant',
        'copy' => 'La matière publique du jour, accordée au mood ' . $homeStreamMood . '.',
        'href' => $str3mHref,
        'signal' => $homeStreamTemplate,
    ],
    [
        'index' => '02',
        'kicker' => 'terre',
        'title' => $authenticatedLand ? 'Rouvrir ta terre' : 'Poser une terre',
        'copy' => $authenticatedLand
            ? 'Revenir au noyau ' . ($activeLandSlug !== '' ? $activeLandSlug : $activeLandLabel) . ', avec sa fréquence située.'
            : 'Créer un point stable dans le tore, lisible sans perdre la douceur du seuil.',
        'href' => $homePrimaryActionHref,
        'signal' => $activeLandLabel,
    ],
    [
        'index' => '03',
        'kicker' => 'signal',
        'title' => 'Écrire juste',
        'copy' => $authenticatedLand
            ? 'La boîte reste disponible pour relier, répondre, préciser.'
            : 'La porte d’adresse attend une terre pour devenir vraiment personnelle.',
        'href' => $signalHref,
        'signal' => $homeSignalState,
    ],
    [
        'index' => '04',
        'kicker' => '0wlslw0',
        'title' => 'Se faire guider',
        'copy' => 'Un guide bref pour choisir la prochaine entrée sans casser le fil.',
        'href' => $guideHref,
        'signal' => 'guide',
    ],
];
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
$ioSpatialVolumeNodes = [];
if ($isSowwwlIo) {
    $ioSpatialVolumeNodes = [
        [
            'key' => 'guide',
            'label' => '0wlslw0',
            'layer' => 'seuil',
            'tone' => 'guide',
            'href' => $guideHref,
            'x' => 0,
            'y' => -3.8,
            'z' => 9.4,
            'copy' => 'Le centre de clarification. On y revient pour nommer la prochaine porte avant de dériver.',
        ],
        [
            'key' => 'land',
            'label' => $authenticatedLand ? 'Terre' : 'Rejoindre',
            'layer' => 'ancre',
            'tone' => 'land',
            'href' => $authenticatedLand ? o_route_href('/land', ['u' => $activeLandSlug]) : $joinHref,
            'x' => 0,
            'y' => 2.1,
            'z' => 1.2,
            'copy' => $authenticatedLand ? 'L identité active module déjà le volume et la fréquence de lecture.' : 'La terre donne une adresse au volume avant les lectures plus profondes.',
        ],
        [
            'key' => 'aza',
            'label' => 'aZa',
            'layer' => 'matiere',
            'tone' => 'aza',
            'href' => $authenticatedLand ? o_route_href('/aza', ['u' => $activeLandSlug]) : $azaHref,
            'x' => 7.6,
            'y' => 4.2,
            'z' => -2.6,
            'copy' => 'La matière déposée devient strate, source, fichier, indice et future île lisible.',
        ],
        [
            'key' => 'island',
            'label' => 'Île',
            'layer' => 'lecture',
            'tone' => 'island',
            'href' => $authenticatedLand ? o_route_href('/island', ['u' => $activeLandSlug]) : $joinHref,
            'x' => -3.5,
            'y' => 8.2,
            'z' => -8.4,
            'copy' => $authenticatedLand ? 'L île relit les matières en lecteurs situés, au calme, avec reprise possible.' : 'Une terre active rendra l île lisible et partageable.',
        ],
        [
            'key' => 'map',
            'label' => 'Map',
            'layer' => 'orientation',
            'tone' => 'map',
            'href' => $mapHref,
            'x' => -8.5,
            'y' => -1.2,
            'z' => 4.6,
            'copy' => 'La carte garde les nœuds, les courants et la géométrie du tore visibles.',
        ],
        [
            'key' => 'signal',
            'label' => 'Signal',
            'layer' => 'relation',
            'tone' => 'signal',
            'href' => $signalHref,
            'x' => 8.4,
            'y' => -0.8,
            'z' => 4.1,
            'copy' => 'La boîte tient les fils, le contexte et les reprises entre présences.',
        ],
        [
            'key' => 'str3m',
            'label' => 'Str3m',
            'layer' => 'courant',
            'tone' => 'str3m',
            'href' => $str3mHref,
            'x' => -7.2,
            'y' => 4.6,
            'z' => -2.9,
            'copy' => 'Le courant public montre ce qui circule maintenant et ce qui peut devenir prise.',
        ],
        [
            'key' => 'echo',
            'label' => 'Echo',
            'layer' => 'réponse',
            'tone' => 'echo',
            'href' => o_route_href('/echo'),
            'x' => 3.8,
            'y' => 8.1,
            'z' => -8.1,
            'copy' => 'L écho relance une présence en direct sans perdre la mémoire de Signal.',
        ],
        [
            'key' => 'lab',
            'label' => 'Lab',
            'layer' => 'prototype',
            'tone' => 'lab',
            'href' => 'https://lab.sowwwl.cloud/',
            'x' => 0,
            'y' => -7.7,
            'z' => -6.3,
            'copy' => 'Le lab raccorde capteurs, pocket, plasma et vérification avant passage en production.',
        ],
    ];
}
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
    data-corner-dock-default-open="<?= $connectionDockOpen ? '1' : '0' ?>"
    aria-labelledby="connection-meter-title"
    <?= $connectionDockOpen ? 'open' : '' ?>
>
    <summary class="connection-meter__toggle">
        <span class="corner-dock-toggle__kicker">Se relier</span>
        <strong><?= h($authenticatedLand ? $connectionStatusText : 'terre déjà posée ?') ?></strong>
        <span class="corner-dock-toggle__meta"><?= $authenticatedLand ? h('@' . $activeLandSlug) : 'ouvrir doucement' ?></span>
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

        <?php if ($message !== ''): ?>
            <div class="connection-meter__flash flash flash-<?= h($messageType) ?>" aria-live="polite">
                <p><?= h($message) ?></p>
            </div>
        <?php endif; ?>

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
                <a class="ghost-link" href="<?= h($authenticatedLand ? o_route_href('/land', ['u' => $activeLandSlug]) : '#connexion') ?>"><?= h($authenticatedLand ? 'Ouvrir ma terre' : 'Relier une terre') ?></a>
                <a class="ghost-link" href="<?= h($guideHref) ?>">Passer par 0wlslw0</a>
            </div>

            <div class="xyz-surface-meta" aria-label="Signature de la surface">
                <span class="badge badge-glass">λ <?= h((string) $activeLambda) ?> nm</span>
                <span class="badge badge-glass"><?= h($activeLandLabel) ?></span>
                <span class="badge badge-glass"><?= h((string) ($dailyStream['mood'] ?? 'calm')) ?></span>
                <span class="badge badge-glass">local d’abord</span>
            </div>
        </header>

        <?= render_spatial_context_bar('surface', $host) ?>

        <?= render_continuity_dome('surface', [
            'host' => $host,
            'land' => $authenticatedLand,
            'land_slug' => $activeLandSlug,
            'land_username' => $activeLandUsername,
            'unread_signal' => $unreadSignal,
        ]) ?>

        <div class="xyz-surface-grid">
            <section class="panel reveal mapping-panel mapping-panel--genie xyz-surface-mapping" id="mapping" aria-labelledby="mapping-title" data-mapping-genie data-mapping-theme="real" data-xyz-archi-section data-xyz-archi-label="cartographie">
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
                <article class="xyz-surface-note xyz-surface-note--archi" aria-label="Axes de sélection de la surface">
                    <details class="xyz-archi-dock xyz-archi-dock--inline" data-xyz-archi-dock open>
                        <summary class="xyz-archi-dock__toggle">
                            <span class="corner-dock-toggle__kicker">sommaire</span>
                            <strong>Trois axes autour du centre</strong>
                            <span class="corner-dock-toggle__meta" data-xyz-archi-current>cartographie</span>
                        </summary>

                        <div class="xyz-archi-dock__head">
                            <div class="xyz-archi-dock__copy">
                                <p class="eyebrow"><strong><?= h($spatialSurfaceHostLabel) ?></strong> <span>archiborescence intégrée</span></p>
                                <p>Le centre reste vide pour voir. Les décisions se posent autour : matière, guide, appareillage.</p>
                            </div>
                        </div>

                        <div class="xyz-archi-axis-map" aria-label="Trois axes de redirection">
                            <span class="xyz-archi-axis-center">centre libre</span>
                            <a class="xyz-archi-axis-card xyz-archi-axis-card--content" href="<?= h($publicNucleusHref) ?>">
                                <span>axe 01</span>
                                <strong>aZa / Str3m</strong>
                                <small>sowwwl.com</small>
                            </a>
                            <a class="xyz-archi-axis-card xyz-archi-axis-card--guide" href="<?= h($publicGuideHref) ?>">
                                <span>axe 02</span>
                                <strong>0wlslw0</strong>
                                <small>clarifier</small>
                            </a>
                            <a class="xyz-archi-axis-card xyz-archi-axis-card--device" href="<?= h($isSowwwlIo ? $publicXyzHref : $publicIoHref) ?>">
                                <span>axe 03</span>
                                <strong><?= h($isSowwwlIo ? 'xyz membrane' : 'io spatial') ?></strong>
                                <small>appareillage</small>
                            </a>
                        </div>

                        <div class="xyz-archi-dock__actions">
                            <button type="button" class="ghost-link xyz-archi-dock__action" data-xyz-archi-expand>ouvrir tout</button>
                            <button type="button" class="ghost-link xyz-archi-dock__action" data-xyz-archi-collapse>resserrer</button>
                        </div>

                        <nav class="xyz-archi-dock__nav" aria-label="Menu hiérarchisé de la surface">
                            <details class="xyz-archi-dock__group" open>
                                <summary>
                                    <span class="summary-label">matière</span>
                                    <strong>sowwwl.com</strong>
                                </summary>
                                <div class="xyz-archi-dock__group-body">
                                    <a class="xyz-archi-dock__link" href="<?= h($publicAzaHref) ?>">
                                        <span class="summary-label">aZa</span>
                                        <strong>Déposer / relire</strong>
                                        <span>matières, fichiers, sources</span>
                                    </a>
                                    <a class="xyz-archi-dock__link" href="<?= h($publicStr3mHref) ?>">
                                        <span class="summary-label">Str3m</span>
                                        <strong>Lire le courant</strong>
                                        <span>texte, image, musique</span>
                                    </a>
                                    <button type="button" class="xyz-archi-dock__link" data-xyz-archi-nav="xyz-panel-music">
                                        <span class="summary-label">interne</span>
                                        <strong>Atelier membrane</strong>
                                        <span>lecture, motif, prises</span>
                                    </button>
                                </div>
                            </details>

                            <details class="xyz-archi-dock__group">
                                <summary>
                                    <span class="summary-label">guide</span>
                                    <strong>0wlslw0.com</strong>
                                </summary>
                                <div class="xyz-archi-dock__group-body">
                                    <a class="xyz-archi-dock__link" href="<?= h($publicGuideHref) ?>">
                                        <span class="summary-label">seuil</span>
                                        <strong>Nommer la route</strong>
                                        <span>question, orientation, retour</span>
                                    </a>
                                    <button type="button" class="xyz-archi-dock__link" data-xyz-archi-nav="mapping">
                                        <span class="summary-label">interne</span>
                                        <strong>Cartographie</strong>
                                        <span>réel, plasma, tore</span>
                                    </button>
                                    <button type="button" class="xyz-archi-dock__link" data-xyz-archi-nav="xyz-panel-routes">
                                        <span class="summary-label">interne</span>
                                        <strong>Sorties</strong>
                                        <span>les trois portes finales</span>
                                    </button>
                                </div>
                            </details>

                            <details class="xyz-archi-dock__group" <?= $isSowwwlIo ? 'open' : '' ?>>
                                <summary>
                                    <span class="summary-label">appareil</span>
                                    <strong><?= h($isSowwwlIo ? 'sowwwl.io' : 'sowwwl.xyz') ?></strong>
                                </summary>
                                <div class="xyz-archi-dock__group-body">
                                    <a class="xyz-archi-dock__link" href="<?= h($publicIoHref) ?>">
                                        <span class="summary-label">spatial</span>
                                        <strong>sowwwl.io</strong>
                                        <span>écran, casque, volume</span>
                                    </a>
                                    <a class="xyz-archi-dock__link" href="<?= h($publicXyzHref) ?>">
                                        <span class="summary-label">membrane</span>
                                        <strong>sowwwl.xyz</strong>
                                        <span>téléphone, réel, musique</span>
                                    </a>
                                    <button type="button" class="xyz-archi-dock__link" data-xyz-archi-nav="xyz-panel-rituel">
                                        <span class="summary-label">interne</span>
                                        <strong>Rituel &amp; capteurs</strong>
                                        <span>caméra, mouvement, veille</span>
                                    </button>
                                    <button type="button" class="xyz-archi-dock__link" data-xyz-archi-nav="xyz-panel-device">
                                        <span class="summary-label">interne</span>
                                        <strong>Appareil</strong>
                                        <span>niveau O., partage, natif</span>
                                    </button>
                                    <button type="button" class="xyz-archi-dock__link" data-xyz-archi-nav="xyz-panel-instrument">
                                        <span class="summary-label">interne</span>
                                        <strong>Monde instrument</strong>
                                        <span>Terre, Mine, visage, paysage</span>
                                    </button>
                                    <button type="button" class="xyz-archi-dock__link" data-xyz-archi-nav="xyz-panel-ar">
                                        <span class="summary-label">interne</span>
                                        <strong>Modulation RA</strong>
                                        <span>ancrer, traduire, boucler</span>
                                    </button>
                                    <button type="button" class="xyz-archi-dock__link" data-xyz-archi-nav="xyz-panel-gestures">
                                        <span class="summary-label">interne</span>
                                        <strong>Gestes</strong>
                                        <span>prise, dérive, orientation</span>
                                    </button>
                                    <?php if ($isSowwwlIo): ?>
                                    <button type="button" class="xyz-archi-dock__link" data-xyz-archi-nav="xyz-panel-volume">
                                        <span class="summary-label">interne</span>
                                        <strong>Volume 3D</strong>
                                        <span>noeuds, profondeur, routes</span>
                                    </button>
                                    <button type="button" class="xyz-archi-dock__link" data-xyz-archi-nav="xyz-panel-spatial">
                                        <span class="summary-label">interne</span>
                                        <strong>Mode casque</strong>
                                        <span>projection, headset, routes</span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </nav>
                    </details>
                </article>

                <article class="xyz-surface-note xyz-surface-note--camera" data-xyz-camera-panel>
                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-rituel" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="rituel & capteurs" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="1" open>
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">01 membrane</span>
                            <strong>Rituel &amp; capteurs</strong>
                            <span class="xyz-archi-panel__meta">camera, mouvement, lumiere, veille</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <strong data-xyz-camera-title><?= h($spatialCameraTitle) ?></strong>
                            <p class="panel-copy" data-xyz-camera-status><?= h($spatialCameraStatus) ?></p>
                            <div class="xyz-surface-sensor-grid" aria-label="État direct de la membrane">
                                <p><span>orientation</span><strong data-xyz-sensor-orientation>en attente</strong></p>
                                <p><span>mouvement</span><strong data-xyz-sensor-motion>en attente</strong></p>
                                <p><span>lumière</span><strong data-xyz-sensor-light>en attente</strong></p>
                                <p><span>ambiance</span><strong data-xyz-sensor-audio>en attente</strong></p>
                                <p><span>caméra</span><strong data-xyz-sensor-camera>en attente</strong></p>
                                <p><span>veille</span><strong data-xyz-sensor-wake>en attente</strong></p>
                            </div>
                        </div>
                    </details>

                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-device" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="appareil" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="0">
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">02 appareil</span>
                            <strong>Sortie, partage, pont</strong>
                            <span class="xyz-archi-panel__meta">niveau O., silence, natif</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
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
                                    <div class="device-bridge-actions">
                                        <button type="button" class="ghost-link" data-device-install hidden>Installer</button>
                                        <button type="button" class="ghost-link" data-device-share>Partager</button>
                                    </div>
                                </div>
                                <p class="panel-copy device-bridge-note" data-device-native-note><?= h($spatialDeviceNote) ?></p>
                            </div>
                        </div>
                    </details>

                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-instrument" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="monde instrument" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="1" open>
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">03 monde</span>
                            <strong>Monde instrument</strong>
                            <span class="xyz-archi-panel__meta">Terre, Mine, visage, paysage</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
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
                        </div>
                    </details>

                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-music" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="atelier membrane" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="1" open>
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">04 atelier</span>
                            <strong>Atelier membrane</strong>
                            <span class="xyz-archi-panel__meta">lecture, voyage, motif, prises</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="xyz-music-guide" data-xyz-music-guide-root>
                                <details class="xyz-archi-panel xyz-archi-panel--nested" id="xyz-panel-music-overview" data-xyz-archi-panel data-xyz-archi-group="music-guide" data-xyz-archi-default-open="1" open>
                                    <summary class="xyz-archi-panel__summary">
                                        <span class="summary-label">atelier</span>
                                        <strong>Lecture &amp; reglages</strong>
                                        <span class="xyz-archi-panel__meta">gamme, timbre, percu</span>
                                    </summary>
                                    <div class="xyz-archi-panel__content">
                                        <div class="xyz-music-guide__grid" aria-label="Lecture musicale du tore">
                                            <p><span>mode</span><strong data-xyz-music-mode>Mi éolien</strong></p>
                                            <p><span>note</span><strong data-xyz-music-note>Mi2</strong></p>
                                            <p><span>timbre</span><strong data-xyz-music-timbre>peau</strong></p>
                                            <p><span>percu</span><strong data-xyz-music-percussion>kick + hh</strong></p>
                                            <p><span>rythme</span><strong data-xyz-music-rhythm>drone stable</strong></p>
                                            <p><span>terre</span><strong data-xyz-hand-terre-state>porte le champ</strong></p>
                                            <p><span>mine</span><strong data-xyz-hand-mine-state>creuse la note</strong></p>
                                        </div>
                                        <div class="xyz-music-guide__controls" aria-label="Réglages musicaux de la membrane">
                                            <label class="xyz-music-guide__control">
                                                <span>gamme</span>
                                                <select data-xyz-music-scale>
                                                    <option value="auto">auto</option>
                                                    <option value="aeolian">éolien</option>
                                                    <option value="dorian">dorien</option>
                                                    <option value="lydian">lydien</option>
                                                    <option value="pentatonic">pentatonique</option>
                                                </select>
                                            </label>
                                            <label class="xyz-music-guide__control">
                                                <span>timbre</span>
                                                <select data-xyz-music-instrument>
                                                    <option value="membrane">peau</option>
                                                    <option value="glass">verre</option>
                                                    <option value="reed">roseau</option>
                                                    <option value="bronze">bronze</option>
                                                </select>
                                            </label>
                                            <div class="xyz-music-guide__percussion">
                                                <span>percu</span>
                                                <div class="xyz-music-guide__toggle-group" role="group" aria-label="Percussions actives">
                                                    <button type="button" class="ghost-link xyz-music-guide__toggle" data-xyz-percussion-button="kick" aria-pressed="true">kick</button>
                                                    <button type="button" class="ghost-link xyz-music-guide__toggle" data-xyz-percussion-button="snare" aria-pressed="false">snare</button>
                                                    <button type="button" class="ghost-link xyz-music-guide__toggle" data-xyz-percussion-button="hihat" aria-pressed="true">hh</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="xyz-music-rituals" data-xyz-music-rituals>
                                            <div class="xyz-music-rituals__head">
                                                <div>
                                                    <span class="summary-label">rituels</span>
                                                    <strong data-xyz-music-ritual-state>arche prête</strong>
                                                </div>
                                                <button type="button" class="ghost-link xyz-music-rituals__arch" data-xyz-music-ritual-arch>créer arche A-D</button>
                                            </div>
                                            <p class="xyz-music-rituals__copy" data-xyz-music-ritual-copy>Charge un profil musical complet: pose Terre/Mine, gamme, timbre, tempo, FX, motif et scène associée.</p>
                                            <div class="xyz-music-rituals__grid" role="group" aria-label="Rituels musicaux">
                                                <button type="button" class="ghost-link xyz-music-ritual" data-xyz-music-ritual="aube" aria-pressed="false">
                                                    <span>A</span>
                                                    <strong>aube claire</strong>
                                                    <em>verriere · 84 bpm</em>
                                                </button>
                                                <button type="button" class="ghost-link xyz-music-ritual" data-xyz-music-ritual="seuil" aria-pressed="false">
                                                    <span>B</span>
                                                    <strong>seuil profond</strong>
                                                    <em>peau · 96 bpm</em>
                                                </button>
                                                <button type="button" class="ghost-link xyz-music-ritual" data-xyz-music-ritual="marche" aria-pressed="false">
                                                    <span>C</span>
                                                    <strong>marche plasma</strong>
                                                    <em>roseau · 112 bpm</em>
                                                </button>
                                                <button type="button" class="ghost-link xyz-music-ritual" data-xyz-music-ritual="braise" aria-pressed="false">
                                                    <span>D</span>
                                                    <strong>braise dense</strong>
                                                    <em>bronze · 126 bpm</em>
                                                </button>
                                            </div>
                                        </div>
                                        <p class="panel-copy" data-xyz-music-guide>Choisis une gamme, un timbre et la percussion utile. La lumière colore l accord, l inclinaison tient la note, Terre porte la base et Mine ouvre l accent.</p>
                                    </div>
                                </details>

                                <details class="xyz-archi-panel xyz-archi-panel--nested" id="xyz-panel-music-console" data-xyz-archi-panel data-xyz-archi-group="music-guide" data-xyz-archi-default-open="0">
                                    <summary class="xyz-archi-panel__summary">
                                        <span class="summary-label">console</span>
                                        <strong>Transport &amp; arrangement</strong>
                                        <span class="xyz-archi-panel__meta">BPM, FX, scenes, voyage, prises</span>
                                    </summary>
                                    <div class="xyz-archi-panel__content">
                                        <section class="xyz-music-desk" data-xyz-daw-root aria-label="Console musicale membrane">
                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-transport" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="1" open>
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">session</span>
                                                    <strong>Transport &amp; capture</strong>
                                                    <span class="xyz-archi-panel__meta">lecture, rec, stems, projet</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__transport">
                                                        <div class="xyz-music-desk__transport-head">
                                                            <span class="summary-label">session</span>
                                                            <strong data-xyz-daw-status>prête</strong>
                                                        </div>
                                                        <div class="xyz-music-desk__transport-actions" role="group" aria-label="Transport musical">
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-play>lecture locale</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-stop>stop</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button xyz-music-desk__transport-button--record" data-xyz-daw-record>rec audio</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button xyz-music-desk__transport-button--performance" data-xyz-daw-record-performance>rec perf</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-export-stems>export stems</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-export-project>export projet</button>
                                                        </div>
                                                        <div class="xyz-music-desk__transport-grid">
                                                            <label class="xyz-music-desk__field">
                                                                <span>bpm</span>
                                                                <input type="range" min="60" max="168" step="1" value="96" data-xyz-daw-bpm-input>
                                                                <strong data-xyz-daw-bpm-readout>96</strong>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>swing</span>
                                                                <input type="range" min="0" max="40" step="1" value="12" data-xyz-daw-swing-input>
                                                                <strong data-xyz-daw-swing-readout>12%</strong>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>humanize</span>
                                                                <input type="range" min="0" max="36" step="1" value="14" data-xyz-daw-humanize-input>
                                                                <strong data-xyz-daw-humanize-readout>14%</strong>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>microtiming</span>
                                                                <input type="range" min="0" max="24" step="1" value="9" data-xyz-daw-microtiming-input>
                                                                <strong data-xyz-daw-microtiming-readout>9 ms</strong>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>boucle</span>
                                                                <select data-xyz-daw-loop-select>
                                                                    <option value="2">2 mesures</option>
                                                                    <option value="4" selected>4 mesures</option>
                                                                    <option value="8">8 mesures</option>
                                                                    <option value="16">16 mesures</option>
                                                                </select>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>quantize</span>
                                                                <select data-xyz-daw-quantize-select>
                                                                    <option value="1/4">1/4</option>
                                                                    <option value="1/8" selected>1/8</option>
                                                                    <option value="1/16">1/16</option>
                                                                </select>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>count-in</span>
                                                                <select data-xyz-daw-countin-select>
                                                                    <option value="0">off</option>
                                                                    <option value="1" selected>1 mesure</option>
                                                                    <option value="2">2 mesures</option>
                                                                </select>
                                                            </label>
                                                            <p class="xyz-music-desk__clock" data-xyz-daw-clock>boucle 4 mesures · mesure 1 · temps 1</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>

                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-fx" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="0">
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">matiere</span>
                                                    <strong>Master &amp; FX</strong>
                                                    <span class="xyz-archi-panel__meta">espace, echo, matiere, air</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__fx" aria-label="Matière du master">
                                                        <div class="xyz-music-fx">
                                                            <div class="xyz-music-fx__head">
                                                                <span class="summary-label">matiere</span>
                                                                <strong data-xyz-daw-fx-state>nu proche</strong>
                                                            </div>
                                                            <p class="xyz-music-fx__copy" data-xyz-daw-fx-copy>Ouvre l espace, la repetition, le grain et l air du master pour transformer la membrane en chambre, vitre, brume ou braise.</p>
                                                            <div class="xyz-music-fx__presets" role="group" aria-label="Presets de matière">
                                                                <button type="button" class="ghost-link xyz-music-fx__preset" data-xyz-daw-fx-preset="bare">nu</button>
                                                                <button type="button" class="ghost-link xyz-music-fx__preset" data-xyz-daw-fx-preset="mist">brume</button>
                                                                <button type="button" class="ghost-link xyz-music-fx__preset" data-xyz-daw-fx-preset="glass">verriere</button>
                                                                <button type="button" class="ghost-link xyz-music-fx__preset" data-xyz-daw-fx-preset="ember">braise</button>
                                                            </div>
                                                            <div class="xyz-music-fx__grid">
                                                                <label class="xyz-music-desk__field">
                                                                    <span>espace</span>
                                                                    <input type="range" min="0" max="100" step="1" value="18" data-xyz-daw-fx-space-input>
                                                                    <strong data-xyz-daw-fx-space-readout>18%</strong>
                                                                </label>
                                                                <label class="xyz-music-desk__field">
                                                                    <span>echo</span>
                                                                    <input type="range" min="0" max="100" step="1" value="12" data-xyz-daw-fx-echo-input>
                                                                    <strong data-xyz-daw-fx-echo-readout>12%</strong>
                                                                </label>
                                                                <label class="xyz-music-desk__field">
                                                                    <span>matiere</span>
                                                                    <input type="range" min="0" max="100" step="1" value="9" data-xyz-daw-fx-dirt-input>
                                                                    <strong data-xyz-daw-fx-dirt-readout>9%</strong>
                                                                </label>
                                                                <label class="xyz-music-desk__field">
                                                                    <span>air</span>
                                                                    <input type="range" min="0" max="100" step="1" value="54" data-xyz-daw-fx-air-input>
                                                                    <strong data-xyz-daw-fx-air-readout>54%</strong>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>

                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-memory" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="1" open>
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">memoire</span>
                                                    <strong>Scenes &amp; geste</strong>
                                                    <span class="xyz-archi-panel__meta">rappel, capture, boucle</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__memory" aria-label="Mémoire immersive">
                                                        <div class="xyz-music-memory">
                                                            <div class="xyz-music-memory__head">
                                                                <span class="summary-label">scenes</span>
                                                                <strong>memoire du tore</strong>
                                                            </div>
                                                            <p class="xyz-music-memory__copy">Mémorise une position Terre/Mine, le timbre, la gamme et le mix, puis relance-les comme des états jouables.</p>
                                                            <div class="xyz-music-memory__grid" role="group" aria-label="Scènes de la membrane">
                                                                <article class="xyz-music-scene" data-xyz-daw-scene="scene-a">
                                                                    <div class="xyz-music-scene__head">
                                                                        <span class="summary-label">A</span>
                                                                        <strong>aube</strong>
                                                                    </div>
                                                                    <p class="xyz-music-scene__state" data-xyz-daw-scene-state="scene-a">vide</p>
                                                                    <div class="xyz-music-scene__actions">
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-trigger="scene-a">jouer</button>
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-store="scene-a">mémoriser</button>
                                                                    </div>
                                                                </article>
                                                                <article class="xyz-music-scene" data-xyz-daw-scene="scene-b">
                                                                    <div class="xyz-music-scene__head">
                                                                        <span class="summary-label">B</span>
                                                                        <strong>seuil</strong>
                                                                    </div>
                                                                    <p class="xyz-music-scene__state" data-xyz-daw-scene-state="scene-b">vide</p>
                                                                    <div class="xyz-music-scene__actions">
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-trigger="scene-b">jouer</button>
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-store="scene-b">mémoriser</button>
                                                                    </div>
                                                                </article>
                                                                <article class="xyz-music-scene" data-xyz-daw-scene="scene-c">
                                                                    <div class="xyz-music-scene__head">
                                                                        <span class="summary-label">C</span>
                                                                        <strong>marche</strong>
                                                                    </div>
                                                                    <p class="xyz-music-scene__state" data-xyz-daw-scene-state="scene-c">vide</p>
                                                                    <div class="xyz-music-scene__actions">
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-trigger="scene-c">jouer</button>
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-store="scene-c">mémoriser</button>
                                                                    </div>
                                                                </article>
                                                                <article class="xyz-music-scene" data-xyz-daw-scene="scene-d">
                                                                    <div class="xyz-music-scene__head">
                                                                        <span class="summary-label">D</span>
                                                                        <strong>sève</strong>
                                                                    </div>
                                                                    <p class="xyz-music-scene__state" data-xyz-daw-scene-state="scene-d">vide</p>
                                                                    <div class="xyz-music-scene__actions">
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-trigger="scene-d">jouer</button>
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-store="scene-d">mémoriser</button>
                                                                    </div>
                                                                </article>
                                                            </div>
                                                        </div>
                                                        <div class="xyz-music-memory xyz-music-memory--gesture">
                                                            <div class="xyz-music-memory__head">
                                                                <span class="summary-label">geste</span>
                                                                <strong data-xyz-daw-gesture-state>aucune boucle</strong>
                                                            </div>
                                                            <p class="xyz-music-memory__copy" data-xyz-daw-gesture-copy>Capture un trajet Terre/Mine sur une boucle, puis rejoue-le comme une automation vivante de l instrument.</p>
                                                            <div class="xyz-music-memory__actions" role="group" aria-label="Boucle de geste">
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-gesture-record>capturer geste</button>
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-gesture-play>jouer boucle</button>
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-gesture-clear>effacer</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>

                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-arrangement" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="0">
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">voyage</span>
                                                    <strong>Scenes en chaine</strong>
                                                    <span class="xyz-archi-panel__meta">A B C D, mesures, boucle</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__arrangement" aria-label="Voyage de scènes">
                                                        <div class="xyz-music-arrangement">
                                                            <div class="xyz-music-arrangement__head">
                                                                <span class="summary-label">voyage</span>
                                                                <strong data-xyz-daw-arrangement-state>aucun voyage</strong>
                                                            </div>
                                                            <p class="xyz-music-arrangement__copy" data-xyz-daw-arrangement-copy>Enchaîne des scènes mémorisées sur plusieurs mesures pour transformer la membrane en forme jouable et enregistrable.</p>
                                                            <div class="xyz-music-arrangement__actions" role="group" aria-label="Transport du voyage">
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-arrangement-play>jouer voyage</button>
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-arrangement-build>charger A B C D</button>
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-arrangement-clear>effacer</button>
                                                                <label class="xyz-music-arrangement__loop">
                                                                    <input type="checkbox" data-xyz-daw-arrangement-loop checked>
                                                                    <span>boucle voyage</span>
                                                                </label>
                                                            </div>
                                                            <div class="xyz-music-arrangement__grid">
                                                                <?php for ($arrangementStep = 0; $arrangementStep < 6; $arrangementStep += 1): ?>
                                                                    <?php $arrangementStepLabel = str_pad((string) ($arrangementStep + 1), 2, '0', STR_PAD_LEFT); ?>
                                                                    <article class="xyz-music-arrangement-step" data-xyz-daw-arrangement-card="<?= $arrangementStep ?>">
                                                                        <div class="xyz-music-arrangement-step__head">
                                                                            <span class="summary-label"><?= h($arrangementStepLabel) ?></span>
                                                                            <strong data-xyz-daw-arrangement-step-state="<?= $arrangementStep ?>">libre</strong>
                                                                        </div>
                                                                        <label class="xyz-music-arrangement-step__field">
                                                                            <span>scene</span>
                                                                            <select data-xyz-daw-arrangement-scene="<?= $arrangementStep ?>">
                                                                                <option value="">libre</option>
                                                                                <option value="scene-a">A · aube</option>
                                                                                <option value="scene-b">B · seuil</option>
                                                                                <option value="scene-c">C · marche</option>
                                                                                <option value="scene-d">D · sève</option>
                                                                            </select>
                                                                        </label>
                                                                        <label class="xyz-music-arrangement-step__field">
                                                                            <span>mesures</span>
                                                                            <select data-xyz-daw-arrangement-bars="<?= $arrangementStep ?>">
                                                                                <option value="1">1</option>
                                                                                <option value="2" selected>2</option>
                                                                                <option value="4">4</option>
                                                                                <option value="8">8</option>
                                                                            </select>
                                                                        </label>
                                                                    </article>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>

                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-pattern" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="0">
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">motif</span>
                                                    <strong>Sequence rythmique</strong>
                                                    <span class="xyz-archi-panel__meta">kick, snare, hh, accent, proba</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__pattern" aria-label="Séquence rythmique">
                                                        <div class="xyz-music-pattern">
                                                            <div class="xyz-music-pattern__head">
                                                                <span class="summary-label">motif</span>
                                                                <strong data-xyz-daw-pattern-state>aucun motif</strong>
                                                            </div>
                                                            <p class="xyz-music-pattern__copy" data-xyz-daw-pattern-copy>Écris un pas de kick, snare et hh, puis laisse le swing et la boucle faire respirer la marche. Alt accentue, Ctrl change la proba, Shift ouvre le ratchet.</p>
                                                            <div class="xyz-music-pattern__presets" role="group" aria-label="Presets rythmiques">
                                                                <button type="button" class="ghost-link xyz-music-pattern__preset" data-xyz-daw-pattern-preset="pulse">pouls</button>
                                                                <button type="button" class="ghost-link xyz-music-pattern__preset" data-xyz-daw-pattern-preset="stride">marche</button>
                                                                <button type="button" class="ghost-link xyz-music-pattern__preset" data-xyz-daw-pattern-preset="drizzle">bruine</button>
                                                                <button type="button" class="ghost-link xyz-music-pattern__preset" data-xyz-daw-pattern-preset="storm">orage</button>
                                                                <button type="button" class="ghost-link xyz-music-pattern__preset" data-xyz-daw-pattern-preset="clear">effacer</button>
                                                            </div>
                                                            <div class="xyz-music-pattern__editor" aria-label="Edition du pas">
                                                                <div class="xyz-music-pattern__editor-head">
                                                                    <span class="summary-label">pas cible</span>
                                                                    <strong data-xyz-daw-pattern-editor-state>kick · pas 1</strong>
                                                                </div>
                                                                <p class="xyz-music-pattern__editor-copy" data-xyz-daw-pattern-editor-copy>Choisis un pas puis sculpte son accent, sa probabilité et son ratchet. Les raccourcis Alt / Ctrl / Shift-clique font la même chose directement dans la grille.</p>
                                                                <div class="xyz-music-pattern__editor-controls">
                                                                    <button type="button" class="ghost-link xyz-music-pattern__editor-toggle" data-xyz-daw-pattern-accent aria-pressed="false">accent</button>
                                                                    <label class="xyz-music-pattern__editor-field">
                                                                        <span>proba</span>
                                                                        <select data-xyz-daw-pattern-probability>
                                                                            <option value="1">100%</option>
                                                                            <option value="0.75">75%</option>
                                                                            <option value="0.5">50%</option>
                                                                            <option value="0.25">25%</option>
                                                                        </select>
                                                                    </label>
                                                                    <label class="xyz-music-pattern__editor-field">
                                                                        <span>ratchet</span>
                                                                        <select data-xyz-daw-pattern-ratchet>
                                                                            <option value="1">1x</option>
                                                                            <option value="2">2x</option>
                                                                            <option value="3">3x</option>
                                                                            <option value="4">4x</option>
                                                                        </select>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <?php $patternTracks = ['kick' => 'kick', 'snare' => 'snare', 'hihat' => 'hh']; ?>
                                                            <?php foreach ($patternTracks as $patternTrackKey => $patternTrackLabel): ?>
                                                                <article class="xyz-music-pattern__lane" data-xyz-daw-pattern-row="<?= h($patternTrackKey) ?>">
                                                                    <div class="xyz-music-pattern__lane-head">
                                                                        <span class="summary-label"><?= h($patternTrackLabel) ?></span>
                                                                        <strong><?= h($patternTrackKey === 'hihat' ? 'temps fins' : ($patternTrackKey === 'snare' ? 'contre-champ' : 'ancrage')) ?></strong>
                                                                    </div>
                                                                    <div class="xyz-music-pattern__row-shell">
                                                                        <div class="xyz-music-pattern__steps" role="group" aria-label="Pas <?= h($patternTrackLabel) ?>">
                                                                            <?php for ($patternStep = 0; $patternStep < 16; $patternStep += 1): ?>
                                                                                <?php $stepBeatLabel = ($patternStep % 4) === 0 ? (string) (((int) floor($patternStep / 4)) + 1) : '·'; ?>
                                                                                <button
                                                                                    type="button"
                                                                                    class="ghost-link xyz-music-pattern__step"
                                                                                    data-xyz-daw-pattern-step="<?= h($patternTrackKey) ?>:<?= $patternStep ?>"
                                                                                    data-xyz-daw-pattern-track="<?= h($patternTrackKey) ?>"
                                                                                    data-xyz-daw-pattern-index="<?= $patternStep ?>"
                                                                                    aria-pressed="false"
                                                                                    aria-label="<?= h($patternTrackLabel) ?> pas <?= $patternStep + 1 ?>"
                                                                                ><?= h($stepBeatLabel) ?></button>
                                                                            <?php endfor; ?>
                                                                        </div>
                                                                    </div>
                                                                </article>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>

                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-mixer" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="0">
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">mix</span>
                                                    <strong>Pistes &amp; master</strong>
                                                    <span class="xyz-archi-panel__meta">terre, mine, basse, percu</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__mixer" aria-label="Mixer membrane">
                                                        <article class="xyz-music-track" data-xyz-track-card="terre">
                                                            <div class="xyz-music-track__head">
                                                                <span class="summary-label">terre</span>
                                                                <strong>fond</strong>
                                                            </div>
                                                            <p class="xyz-music-track__copy">La charpente stable, la gravite et le corps du tore.</p>
                                                            <div class="xyz-music-track__actions">
                                                                <button type="button" class="ghost-link xyz-music-track__toggle" data-xyz-track-mute="terre" aria-pressed="false">mute</button>
                                                                <button type="button" class="ghost-link xyz-music-track__toggle" data-xyz-track-solo="terre" aria-pressed="false">solo</button>
                                                            </div>
                                                            <label class="xyz-music-track__level">
                                                                <span>niveau</span>
                                                                <input type="range" min="0" max="100" step="1" value="82" data-xyz-track-volume="terre">
                                                                <strong data-xyz-track-volume-readout="terre">82%</strong>
                                                            </label>
                                                        </article>
                                                        <article class="xyz-music-track" data-xyz-track-card="mine">
                                                            <div class="xyz-music-track__head">
                                                                <span class="summary-label">mine</span>
                                                                <strong>harmonie</strong>
                                                            </div>
                                                            <p class="xyz-music-track__copy">La ligne qui creuse, eclaire ou assombrit la phrase.</p>
                                                            <div class="xyz-music-track__actions">
                                                                <button type="button" class="ghost-link xyz-music-track__toggle" data-xyz-track-mute="mine" aria-pressed="false">mute</button>
                                                                <button type="button" class="ghost-link xyz-music-track__toggle" data-xyz-track-solo="mine" aria-pressed="false">solo</button>
                                                            </div>
                                                            <label class="xyz-music-track__level">
                                                                <span>niveau</span>
                                                                <input type="range" min="0" max="100" step="1" value="72" data-xyz-track-volume="mine">
                                                                <strong data-xyz-track-volume-readout="mine">72%</strong>
                                                            </label>
                                                        </article>
                                                        <article class="xyz-music-track" data-xyz-track-card="bass">
                                                            <div class="xyz-music-track__head">
                                                                <span class="summary-label">basse</span>
                                                                <strong>ancrage</strong>
                                                            </div>
                                                            <p class="xyz-music-track__copy">Le sous-sol, la tenue et la respiration grave du champ.</p>
                                                            <div class="xyz-music-track__actions">
                                                                <button type="button" class="ghost-link xyz-music-track__toggle" data-xyz-track-mute="bass" aria-pressed="false">mute</button>
                                                                <button type="button" class="ghost-link xyz-music-track__toggle" data-xyz-track-solo="bass" aria-pressed="false">solo</button>
                                                            </div>
                                                            <label class="xyz-music-track__level">
                                                                <span>niveau</span>
                                                                <input type="range" min="0" max="100" step="1" value="76" data-xyz-track-volume="bass">
                                                                <strong data-xyz-track-volume-readout="bass">76%</strong>
                                                            </label>
                                                        </article>
                                                        <article class="xyz-music-track" data-xyz-track-card="percu">
                                                            <div class="xyz-music-track__head">
                                                                <span class="summary-label">percu</span>
                                                                <strong>accents</strong>
                                                            </div>
                                                            <p class="xyz-music-track__copy">Kick, snare et hh dessinent le pas, la marche et la nervure.</p>
                                                            <div class="xyz-music-track__actions">
                                                                <button type="button" class="ghost-link xyz-music-track__toggle" data-xyz-track-mute="percu" aria-pressed="false">mute</button>
                                                                <button type="button" class="ghost-link xyz-music-track__toggle" data-xyz-track-solo="percu" aria-pressed="false">solo</button>
                                                            </div>
                                                            <label class="xyz-music-track__level">
                                                                <span>niveau</span>
                                                                <input type="range" min="0" max="100" step="1" value="78" data-xyz-track-volume="percu">
                                                                <strong data-xyz-track-volume-readout="percu">78%</strong>
                                                            </label>
                                                        </article>
                                                        <article class="xyz-music-track xyz-music-track--master" data-xyz-track-card="master">
                                                            <div class="xyz-music-track__head">
                                                                <span class="summary-label">master</span>
                                                                <strong>sortie</strong>
                                                            </div>
                                                            <p class="xyz-music-track__copy">Le bus final de la membrane, celui qui part vers l oreille et les prises.</p>
                                                            <label class="xyz-music-track__level">
                                                                <span>niveau</span>
                                                                <input type="range" min="0" max="100" step="1" value="98" data-xyz-daw-master-input>
                                                                <strong data-xyz-daw-master-readout>98%</strong>
                                                            </label>
                                                        </article>
                                                    </div>
                                                </div>
                                            </details>

                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-takes" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="1" open>
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">prises</span>
                                                    <strong>Rec audio, perf, stems</strong>
                                                    <span class="xyz-archi-panel__meta">ecoute, telechargement, replay</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__recorder">
                                                        <div class="xyz-music-desk__recorder-head">
                                                            <span class="summary-label">prises</span>
                                                            <strong data-xyz-daw-recording-state>aucune prise</strong>
                                                        </div>
                                                        <p class="panel-copy" data-xyz-daw-recording-copy>Le master peut etre enregistre directement dans l app, puis extrait comme audio ou relu plus tard dans la terre.</p>
                                                        <ul class="xyz-music-desk__takes" data-xyz-daw-takes>
                                                            <li class="xyz-music-desk__take xyz-music-desk__take--empty">Aucune prise pour l instant.</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </details>
                                        </section>
                                    </div>
                                </details>

                                <details class="xyz-archi-panel xyz-archi-panel--nested" id="xyz-panel-music-duet" data-xyz-archi-panel data-xyz-archi-group="music-guide" data-xyz-archi-default-open="0">
                                    <summary class="xyz-archi-panel__summary">
                                        <span class="summary-label">duo</span>
                                        <strong>Partition Terre &amp; Mine</strong>
                                        <span class="xyz-archi-panel__meta">portee, accent, souffle</span>
                                    </summary>
                                    <div class="xyz-archi-panel__content">
                                        <div class="xyz-music-guide__duet" aria-label="Partition Terre et Mine">
                                            <article class="xyz-music-guide__hand xyz-music-guide__hand--terre">
                                                <span class="summary-label">main terre</span>
                                                <strong data-xyz-hand-terre-title>Elle porte.</strong>
                                                <p data-xyz-hand-terre-copy>Elle stabilise le mode, ouvre ou ferme la lumière, puis garde le drone respirable.</p>
                                            </article>
                                            <article class="xyz-music-guide__hand xyz-music-guide__hand--mine">
                                                <span class="summary-label">main mine</span>
                                                <strong data-xyz-hand-mine-title>Elle creuse.</strong>
                                                <p data-xyz-hand-mine-copy>Elle tient la note, ouvre l accent et laisse la percussion respirer sans salir l accord.</p>
                                            </article>
                                        </div>
                                        <p class="xyz-music-guide__duet-state" data-xyz-duet-state>Terre porte le seuil, Mine y ouvre un trajet.</p>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </details>
                </article>

                <article class="xyz-surface-note xyz-surface-note--modulation" data-xyz-ar-modulation data-xyz-ar-mode="<?= h($isSowwwlIo ? 'anchor' : 'weave') ?>">
                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-ar" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="modulation RA" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="0">
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">05 RA</span>
                            <strong>Modulation situee</strong>
                            <span class="xyz-archi-panel__meta">reel, plasma, tore</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
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
                        </div>
                    </details>
                </article>

                <article class="xyz-surface-note">
                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-gestures" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="gestes" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="0">
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">06 gestes</span>
                            <strong><?= h($spatialGestureTitle) ?></strong>
                            <span class="xyz-archi-panel__meta">prise, derive, orientation</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <p class="panel-copy"><?= h($spatialGestureCopy) ?></p>
                        </div>
                    </details>
                </article>

                <?php if ($isSowwwlIo): ?>
                <article class="xyz-surface-note xyz-surface-note--volume">
                    <details class="xyz-archi-panel xyz-archi-panel--surface xyz-archi-panel--volume" id="xyz-panel-volume" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="volume 3D" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="1" open>
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">07 volume</span>
                            <strong>Exploration 3D du dôme</strong>
                            <span class="xyz-archi-panel__meta">noeuds, profondeur, trajectoire</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="xyz-spatial-volume" data-io-volume-root tabindex="0" aria-label="Exploration 3D des noeuds sowwwl.io">
                                <div class="xyz-spatial-volume__head">
                                    <div>
                                        <span class="summary-label">sowwwl.io spatial</span>
                                        <strong>Un volume navigable, pas une page plate.</strong>
                                    </div>
                                    <span class="badge badge-glass" data-io-volume-layer>seuil</span>
                                </div>
                                <div class="xyz-spatial-volume__scene-wrap">
                                    <div class="xyz-spatial-volume__scene" data-io-volume-scene aria-label="Noeuds navigables du volume sowwwl.io">
                                        <span class="xyz-spatial-volume__ring xyz-spatial-volume__ring--front"></span>
                                        <span class="xyz-spatial-volume__ring xyz-spatial-volume__ring--middle"></span>
                                        <span class="xyz-spatial-volume__ring xyz-spatial-volume__ring--back"></span>
                                        <span class="xyz-spatial-volume__axis xyz-spatial-volume__axis--x"></span>
                                        <span class="xyz-spatial-volume__axis xyz-spatial-volume__axis--y"></span>
                                        <?php foreach ($ioSpatialVolumeNodes as $node): ?>
                                        <a
                                            class="xyz-spatial-volume__node xyz-spatial-volume__node--<?= h($node['tone']) ?>"
                                            href="<?= h($node['href']) ?>"
                                            data-io-volume-node="<?= h($node['key']) ?>"
                                            data-io-volume-label="<?= h($node['label']) ?>"
                                            data-io-volume-copy="<?= h($node['copy']) ?>"
                                            data-io-volume-layer="<?= h($node['layer']) ?>"
                                            style="--io-node-x: <?= h((string) $node['x']) ?>rem; --io-node-y: <?= h((string) $node['y']) ?>rem; --io-node-z: <?= h((string) $node['z']) ?>rem;"
                                        >
                                            <span class="xyz-spatial-volume__node-kicker"><?= h($node['layer']) ?></span>
                                            <strong><?= h($node['label']) ?></strong>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="xyz-spatial-volume__readout" aria-live="polite">
                                    <span class="summary-label">prise active</span>
                                    <strong data-io-volume-title>0wlslw0</strong>
                                    <p data-io-volume-copy>Le centre de clarification. On y revient pour nommer la prochaine porte avant de dériver.</p>
                                    <p class="xyz-spatial-volume__suggestion" data-io-volume-suggestion hidden></p>
                                </div>
                                <div class="xyz-spatial-volume__controls" aria-label="Contrôles du volume 3D">
                                    <button type="button" class="ghost-link" data-io-volume-rotate="-1">pivoter -</button>
                                    <button type="button" class="ghost-link" data-io-volume-rotate="1">pivoter +</button>
                                    <button type="button" class="ghost-link" data-io-volume-depth-control="1">avancer</button>
                                    <button type="button" class="ghost-link" data-io-volume-depth-control="-1">reculer</button>
                                    <button type="button" class="ghost-link" data-io-volume-reset>recentre</button>
                                </div>
                                <p class="xyz-spatial-volume__hint">Flèches gauche/droite pour changer de nœud, haut/bas pour incliner, PageUp/PageDown pour la profondeur. Entrée ouvre la prise active.</p>
                            </div>
                        </div>
                    </details>
                </article>

                <article class="xyz-surface-note xyz-surface-note--spatial">
                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-spatial" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="mode casque" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="0">
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">08 casque</span>
                            <strong><?= h($spatialModeTitle) ?></strong>
                            <span class="xyz-archi-panel__meta">projection, headset, routes</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
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
                        </div>
                    </details>
                </article>
                <?php endif; ?>

                <article class="xyz-surface-note">
                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-routes" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="sorties" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="0">
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label"><?= $isSowwwlIo ? '09 sorties' : '07 sorties' ?></span>
                            <strong>Sorties &amp; appareillage</strong>
                            <span class="xyz-archi-panel__meta">matière, guide, membrane</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="xyz-surface-route-cluster">
                                <div class="xyz-surface-route-cluster__block">
                                    <span class="summary-label">trois axes</span>
                                    <div class="xyz-surface-route-links">
                                        <a class="ghost-link" href="<?= h($publicAzaHref) ?>">aZa</a>
                                        <a class="ghost-link" href="<?= h($publicStr3mHref) ?>">Str3m</a>
                                        <a class="ghost-link" href="<?= h($publicGuideHref) ?>">0wlslw0</a>
                                        <a class="ghost-link" href="<?= h($isSowwwlIo ? $publicXyzHref : $publicIoHref) ?>"><?= h($isSowwwlIo ? 'xyz' : 'io') ?></a>
                                    </div>
                                    <p class="panel-copy">Quand le centre a fini de respirer, la sortie ne se disperse pas : matière sur sowwwl.com, guide sur 0wlslw0.com, appareillage entre io et xyz.</p>
                                </div>
                                <div class="xyz-archi-callout">
                                    <span class="summary-label">centre</span>
                                    <strong><?= h($isSowwwlIo ? 'vide spatial lisible' : 'membrane lisible') ?></strong>
                                    <p class="panel-copy"><?= h($isSowwwlIo ? 'La troisième colonne choisit sans recouvrir le volume central.' : 'La colonne de droite garde les choix sans fermer la membrane.') ?></p>
                                </div>
                            </div>
                        </div>
                    </details>
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

        <?= render_continuity_dome('lab', [
            'host' => $host,
            'land' => $authenticatedLand,
            'land_slug' => $activeLandSlug,
            'land_username' => $activeLandUsername,
            'trace_count' => count($labRecentPlasmaEvents),
            'island_status' => 'île QA prête',
        ]) ?>

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

    <section class="home-polish-shell reveal" aria-labelledby="home-polish-title">
        <div class="home-polish-shell__halo" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <header class="home-polish-head">
            <p class="eyebrow"><strong>sowwwl.com</strong> <span>réseau minimal</span></p>
            <h2 id="home-polish-title">Réseau minimal, déjà relié au dôme.</h2>
            <p>La page d’entrée devient une chambre claire : elle montre le courant du jour, les quatre portes actives et les preuves discrètes du tore.</p>
        </header>

        <div class="home-polish-grid">
            <article class="home-live-card" aria-label="Courant public du jour">
                <div class="home-live-card__beam" aria-hidden="true"></div>
                <div class="home-live-card__top">
                    <span class="summary-label">courant du jour</span>
                    <span class="home-live-card__mood"><?= h($homeStreamMood) ?></span>
                </div>
                <h3><?= h($homeDailyTitle) ?></h3>
                <p><?= h($homeDailyCopy) ?></p>

                <div class="home-live-card__signals" aria-label="Matières disponibles">
                    <span>
                        <strong>audio</strong>
                        <?= h($dailyAudioPath !== '' ? $homeDailyAudioTitle : 'en veille') ?>
                    </span>
                    <span>
                        <strong>image</strong>
                        <?= h($dailyImagePath !== '' ? $homeDailyImageTitle : 'en veille') ?>
                    </span>
                    <span>
                        <strong>signal</strong>
                        <?= h($homeSignalState) ?>
                    </span>
                </div>

                <div class="home-live-card__actions">
                    <a class="pill-link" href="<?= h($str3mHref) ?>">Ouvrir Str3m</a>
                    <a class="ghost-link" href="<?= h($dailyAudioPath !== '' ? $dailyAudioPath : $azaHref) ?>"><?= $dailyAudioPath !== '' ? 'Écouter la source' : 'Préparer une matière' ?></a>
                </div>
            </article>

            <nav class="home-route-orbit" aria-label="Chaînons publics du seuil">
                <?php foreach ($homeRouteNodes as $routeNode): ?>
                    <a class="home-route-node home-route-node--<?= h((string) $routeNode['kicker']) ?>" href="<?= h((string) $routeNode['href']) ?>">
                        <span class="home-route-node__index"><?= h((string) $routeNode['index']) ?></span>
                        <span class="home-route-node__body">
                            <span class="summary-label"><?= h((string) $routeNode['kicker']) ?></span>
                            <strong><?= h((string) $routeNode['title']) ?></strong>
                            <span><?= h((string) $routeNode['copy']) ?></span>
                        </span>
                        <span class="home-route-node__signal"><?= h((string) $routeNode['signal']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="home-proof-strip" aria-label="Preuves de surface">
            <?php foreach ($homeSurfaceProofs as $proof): ?>
                <span>
                    <strong><?= h((string) $proof['value']) ?></strong>
                    <small><?= h((string) $proof['label']) ?></small>
                </span>
            <?php endforeach; ?>
        </div>
    </section>

    <?= render_continuity_dome('surface', [
        'host' => $host,
        'land' => $authenticatedLand,
        'land_slug' => $activeLandSlug,
        'land_username' => $activeLandUsername,
        'unread_signal' => $unreadSignal,
    ]) ?>
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
