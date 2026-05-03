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
$isSowwwlXyz = ($host === 'sowwwl.xyz' || $host === 'www.sowwwl.xyz');
// Désactive la redirection pour sowwwl.xyz, on veut un contenu spécial
// if ($isSowwwlXyz) {
//     $path = (string) ($_SERVER['REQUEST_URI'] ?? '/');
//     header('Location: https://sowwwl.com' . $path, true, 302);
//     exit;
// }

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if (($host === '0wlslw0.com' || $host === 'www.0wlslw0.com') && ($requestPath === '/' || $requestPath === '/index.php')) {
    header('Location: /0wlslw0', true, 302);
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
                header('Location: /land.php?u=' . urlencode((string) $land['slug']) . '&session=1', true, 303);
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
                header('Location: /land.php?u=' . urlencode((string) $land['slug']) . '&created=1&session=1', true, 303);
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
$originBase = site_origin();
$brandDomain = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
$homeVisualOnly = $isSowwwlXyz;
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

$unreadEchoes = 0;

$signalReady = false;
$unreadSignal = 0;
$signalIdentityLabel = '';
if ($authenticatedLand) {
    try {
        $signalReady = signal_mail_tables_ready();
        if ($signalReady) {
            $unreadSignal = signal_unread_total($authenticatedLand);
            $unreadEchoes = $unreadSignal;
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
    ? 'Le torus suit la fréquence de ta terre. Signal adresse, aZa retient, Str3m affleure.'
    : 'Un espace numérique épuré, public et partagé. Rejoins le mouvement pour un lieu à toi, discret et vivant.';
$homePrimaryActionHref = $authenticatedLand
    ? '/land.php?u=' . rawurlencode($activeLandSlug)
    : '#poser';
$homePrimaryActionLabel = $authenticatedLand ? 'Ouvrir ma terre' : 'Rejoindre le peuple de l\'O';
$guideHref = '/0wlslw0';
$promptSeeds = guide_prompt_seeds();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= h(SITE_TITLE) ?> — un espace vivant, personnel, discret. Pose ta terre et laisse la nuit coder le reste.">
    <meta name="theme-color" content="#09090b">
    <title><?= h(SITE_TITLE) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
</head>
<body
    class="experience home<?= $isSowwwlXyz ? ' xyz-surface-view' : '' ?>"
    data-land-program="<?= h($activeLandProgram) ?>"
    data-land-label="<?= h($activeLandLabel) ?>"
    data-land-lambda="<?= h((string) $activeLambda) ?>"
    data-land-tone="<?= h($activeLandTone) ?>"
>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

<details
    class="connection-meter<?= $authenticatedLand ? ' is-linked' : ' is-public' ?><?= h($connectionNeedleClass) ?>"
    id="connexion"
    data-corner-dock
    data-corner-dock-side="left"
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
                <a class="pill-link" href="/land.php?u=<?= rawurlencode($activeLandSlug) ?>">ouvrir</a>
                <a class="ghost-link" href="/logout.php">retirer</a>
            </div>
        <?php else: ?>
            <form method="post" action="/#connexion" class="connection-meter__form" autocomplete="on">
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
            <a class="connection-meter__create" href="#poser">poser une terre</a>
        <?php endif; ?>
    </div>
</details>

<div class="world-container" aria-hidden="true">
    <?php if ($isSowwwlXyz): ?>
    <div class="xyz-camera-layer" data-xyz-camera-root>
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
        aria-label="Torus ambiant : glisser pour pivoter, roulette pour traverser, flèches pour dériver. Sur mobile, un appui long puis une glisse permettent aussi de naviguer. Swipe gauche vers Signal, haut vers Str3m, droite vers aZa, bas vers le noyau. Le centre ou un geste en O ouvrent aussi 0wlslw0."
    ></canvas>
</div>

<main class="layout ui-overlay">
    <?php if ($isSowwwlXyz): ?>
    <section class="xyz-surface-shell reveal" data-xyz-surface>
        <div class="xyz-surface-shell__veil" aria-hidden="true">
            <span class="xyz-surface-shell__ring xyz-surface-shell__ring--outer"></span>
            <span class="xyz-surface-shell__ring xyz-surface-shell__ring--inner"></span>
            <span class="xyz-surface-shell__pulse"></span>
        </div>

        <header class="xyz-surface-head">
            <p class="eyebrow xyz-surface-head__eyebrow"><strong>sowwwl.xyz</strong> <span>surface torique / monde reel</span></p>
            <h1 class="xyz-surface-head__title">Le tore écoute le monde réel.</h1>
            <p class="lead xyz-surface-head__lead">Ici, la surface ne présente pas seulement O. : elle agit comme une membrane de lecture. Le réel entre, le plasma traduit, le tore déploie.</p>

            <div class="xyz-surface-actions">
                <button type="button" class="pill-link xyz-camera-toggle" data-xyz-camera-start>Ouvrir Ocam</button>
                <button type="button" class="ghost-link xyz-camera-toggle hidden" data-xyz-camera-stop>Refermer Ocam</button>
                <a class="pill-link" href="<?= h($guideHref) ?>">Entrer par 0wlslw0</a>
                <a class="ghost-link" href="/signal">Laisser une enveloppe</a>
                <a class="ghost-link" href="/str3m">Dériver dans Str3m</a>
                <a class="ghost-link" href="/map">Voir la carte</a>
            </div>

            <div class="xyz-surface-meta" aria-label="Signature de la surface">
                <span class="badge badge-glass">λ <?= h((string) $activeLambda) ?> nm</span>
                <span class="badge badge-glass"><?= h($activeLandLabel) ?></span>
                <span class="badge badge-glass"><?= h((string) ($dailyStream['mood'] ?? 'calm')) ?></span>
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
                            <strong>sowwwl.xyz</strong>
                            <span>réalité / plasma / tore</span>
                        </p>
                        <h2 id="mapping-title">La carte ne représente pas le monde, elle le filtre.</h2>
                        <p class="panel-copy mapping-panel__copy">Une action, une présence, une voix ou un climat passent par une couche plasma avant d’apparaître sur la peau torique. Ce n’est pas une simple interface&nbsp;: c’est une traduction continue.</p>
                    </div>
                    <span class="badge mapping-panel__badge">real-world map</span>
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
                    <span class="summary-label">Ocam / peau comestible</span>
                    <strong data-xyz-camera-title>Ocam peut nourrir la surface.</strong>
                    <p class="panel-copy" data-xyz-camera-status>Active Ocam pour laisser le tore mordre doucement dans le réel : lumière, grain, souffle, texture. Rien n’est envoyé côté serveur depuis cette couche.</p>
                </article>

                <article class="xyz-surface-note">
                    <span class="summary-label">gestes</span>
                    <strong>Traverse la surface.</strong>
                    <p class="panel-copy">Glisse sur le tore pour pivoter. Sur mobile, appui long puis dérive pour viser un passage. Le centre et le geste en O ouvrent aussi 0wlslw0.</p>
                </article>

                <article class="xyz-surface-note">
                    <span class="summary-label">phrases d’entrée</span>
                    <ul class="guide-prompt-list xyz-surface-prompt-list">
                        <?php foreach (array_slice($promptSeeds, 0, 4) as $prompt): ?>
                            <li><code><?= h($prompt) ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                </article>

                <article class="xyz-surface-note">
                    <span class="summary-label">situation</span>
                    <strong><?= h($authenticatedLand ? 'ta terre module le champ' : 'surface publique en écoute') ?></strong>
                    <p class="panel-copy"><?= h($authenticatedLand ? 'La fréquence de ta terre colore déjà la membrane. Tu peux entrer, écrire, ou laisser le tore simplement respirer.' : 'Aucune terre liée pour l’instant : la membrane reste collective, disponible, poreuse.') ?></p>
                </article>
            </aside>
        </div>
    </section>
    <?php endif; ?>
    <?php if (!$homeVisualOnly): ?>
    <header class="top-bar reveal">
        <span class="eyebrow eyebrow-pill"><?= h($brandDomain) ?></span>
        <div class="top-bar-cluster">
            <?php if ($unreadSignal > 0): ?>
                <a href="/signal" class="badge badge-glass" style="border-color: rgba(var(--land-secondary-rgb) / 0.8); color: rgba(var(--land-secondary-rgb) / 0.9); text-decoration: none;">
                    <?= $unreadSignal ?> SIGNAL<?= $unreadSignal > 1 ? 'S' : '' ?> EN ATTENTE
                </a>
            <?php endif; ?>
            <?php if ($unreadEchoes > 0): ?>
                <a href="/echo" class="badge badge-glass" style="border-color: rgba(var(--land-secondary-rgb) / 0.8); color: rgba(var(--land-secondary-rgb) / 0.9); text-decoration: none;">
                    <?= $unreadEchoes ?> ÉCHO<?= $unreadEchoes > 1 ? 'S' : '' ?> EN ATTENTE
                </a>
            <?php endif; ?>
            <span class="badge badge-glass current-mood" data-spectral-mood-label><?= h((string) ($dailyStream['mood'] ?? 'calm')) ?></span>
            <span class="badge badge-glass"><?= h($activeLandLabel) ?></span>
            <span class="badge badge-glass">λ <span data-spectral-lambda><?= h((string) $activeLambda) ?></span> nm</span>
        </div>
    </header>

    <section class="hero-archipelago reveal">
        <article class="world-intro world-intro--entry">
            <span class="summary-label"><?= h($homeStatusLabel) ?></span>
            <h1><span><?= $authenticatedLand ? 'La terre colore le torus.' : 'O. le réseau minimal' ?></span> <em><?= h($activeLandTone) ?></em></h1>
            <p class="vortex" aria-hidden="true">(.λ.)</p>
            <p class="lead"><?= h($homeLead) ?></p>
            <p class="world-intro-note">Trois gestes suffisent&nbsp;: entrer, poser, retrouver.</p>
            <div class="secondary-links" aria-label="Passages secondaires du noyau">
                <a class="ghost-link" href="<?= h($guideHref) ?>">0wlslw0</a>
                <a class="ghost-link" href="/map">Map</a>
                <a class="ghost-link" href="/aza.php">Fichiers</a>
            </div>
        </article>

        <nav class="entry-grid editorial-nav" aria-label="Entrées principales du noyau">
            <?php if ($authenticatedLand): ?>
                <a href="<?= h($homePrimaryActionHref) ?>" class="entry-card entry-card--primary">
                    <span class="summary-label">01 · terre</span>
                    <strong>Ouvrir ma terre</strong>
                    <span>Revenir immédiatement à ton noyau situé.</span>
                </a>
                <a href="/signal" class="entry-card">
                    <span class="summary-label">02 · adresse</span>
                    <strong>Écrire</strong>
                    <span>Aller droit vers Signal<?= $unreadSignal > 0 ? ' · ' . $unreadSignal . ' en attente' : '' ?>.</span>
                </a>
                <a href="/str3m" class="entry-card">
                    <span class="summary-label">03 · public</span>
                    <strong>Entrer publiquement</strong>
                    <span>Voir le courant avant de replonger dans ta terre.</span>
                </a>
            <?php else: ?>
                <a href="/str3m" class="entry-card entry-card--primary">
                    <span class="summary-label">01 · public</span>
                    <strong>Entrer publiquement</strong>
                    <span>Lire le courant, regarder les îles, sentir la surface.</span>
                </a>
                <a href="#poser" class="entry-card">
                    <span class="summary-label">02 · terre</span>
                    <strong>Poser une terre</strong>
                    <span>Créer un lieu à toi, discret, situé, lié à une fréquence.</span>
                </a>
                <a href="#connexion" class="entry-card">
                    <span class="summary-label">03 · retour</span>
                    <strong>Retrouver ma terre</strong>
                    <span>Revenir par le compteur de connexion, sans détour.</span>
                </a>
            <?php endif; ?>
        </nav>

        <aside class="entry-secondary" aria-label="Passages secondaires">
            <p class="summary-label">Autres passages</p>
            <div class="entry-secondary-links">
                <a class="ghost-link" href="/echo">Écho<?= $unreadEchoes > 0 ? ' · ' . $unreadEchoes : '' ?></a>
                <a class="ghost-link" href="/signal">Signal<?= $unreadSignal > 0 ? ' · ' . $unreadSignal : '' ?></a>
                <a class="ghost-link" href="<?= h($guideHref) ?>">Comprendre</a>
            </div>
            <p class="panel-copy">Le noyau n’explique plus tout d’un coup. Il ouvre, puis laisse respirer.</p>
        </aside>
    </section>

    <section class="home-secondary-grid reveal" id="poser">
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
                    <a class="pill-link" href="/land.php?u=<?= rawurlencode($activeLandSlug) ?>">Ouvrir la terre</a>
                    <a class="ghost-link" href="/logout.php">Retirer sa présence</a>
                </div>
            <?php endif; ?>
        </section>

        <details class="minimal-auth home-secondary-panel"<?= $message !== '' ? ' open' : '' ?>>
            <summary class="signup-summary"><?= $authenticatedLand ? 'Relier une autre terre' : 'Poser une terre' ?></summary>
            <div class="auth-box">
                <p class="panel-copy">L’entrée est minimale. Le rituel complet reste ici, un cran plus bas.</p>

                <?php if ($message !== ''): ?>
                    <div class="flash flash-<?= h($messageType) ?>" aria-live="polite">
                        <p><?= h($message) ?></p>
                    </div>
                <?php endif; ?>

                <form method="post" class="land-form" autocomplete="off">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                    <div class="form-trap" aria-hidden="true">
                        <label>
                            Site web
                            <input type="text" name="website" tabindex="-1" autocomplete="off">
                        </label>
                    </div>

                    <label>
                        Nom d’usage
                        <input
                            type="text"
                            name="username"
                            placeholder="ex: nox"
                            required
                            minlength="2"
                            maxlength="42"
                            value="<?= h($form['username']) ?>"
                            data-username-input
                        >
                        <span class="input-hint">Le nom devient ton repère.</span>
                    </label>

                    <?php if ($signupPortals): ?>
                        <section class="signup-portal-ritual" aria-labelledby="signup-portal-title">
                            <div class="signup-head signup-portal-head">
                                <div>
                                    <span class="summary-label">Passage aZa</span>
                                    <h3 id="signup-portal-title">Configurer la terre avant de la sceller</h3>
                                    <p class="panel-copy">On ne pose pas juste un compte : on choisit un axe, une amplitude, une manière d’entrer.</p>
                                </div>
                            </div>

                            <ol class="signup-portal-grid" aria-label="Parcours aZa pour l’inscription">
                                <?php foreach ($signupPortals as $portal): ?>
                                    <li class="signup-portal-card">
                                        <span class="summary-label">Portail <?= h((string) $portal['slug']) ?> · <?= h((string) $portal['label']) ?></span>
                                        <div class="signup-portal-copy"><?= $portal['markup'] ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </section>
                    <?php endif; ?>

                    <section class="signup-spectrum" aria-labelledby="signup-spectrum-title">
                        <div class="signup-head signup-spectrum-head">
                            <div>
                                <span class="summary-label">Signature native</span>
                                <h3 id="signup-spectrum-title">Choisis le programme et la longueur d’onde de ta terre</h3>
                                <p class="panel-copy">Ici tu règles l’identité durable. Le slider 24h du noyau restera ensuite une modulation légère, pas un remplacement.</p>
                            </div>
                        </div>

                        <div class="signup-program-grid" role="radiogroup" aria-label="Choisir un programme de terre">
                            <?php foreach ($signupPrograms as $programKey => $programDefinition): ?>
                                <?php [$programMin, $programMax] = land_visual_lambda_range($programKey); ?>
                                <?php $programDefaultLambda = land_visual_default_lambda($programKey, $signupPreviewSeed); ?>
                                <label class="signup-program-card" data-signup-program-card>
                                    <input
                                        type="radio"
                                        name="land_program"
                                        value="<?= h($programKey) ?>"
                                        <?= $selectedSignupProgram === $programKey ? 'checked' : '' ?>
                                        data-signup-program-input
                                        data-program-label="<?= h((string) ($programDefinition['label'] ?? $programKey)) ?>"
                                        data-program-tone="<?= h((string) ($programDefinition['tone'] ?? '')) ?>"
                                        data-lambda-min="<?= h((string) $programMin) ?>"
                                        data-lambda-max="<?= h((string) $programMax) ?>"
                                        data-lambda-default="<?= h((string) $programDefaultLambda) ?>"
                                    >
                                    <span class="summary-label"><?= h($programKey) ?></span>
                                    <strong><?= h((string) ($programDefinition['label'] ?? $programKey)) ?></strong>
                                    <span><?= h((string) ($programDefinition['tone'] ?? '')) ?></span>
                                    <span>λ <?= h((string) $programMin) ?>–<?= h((string) $programMax) ?> nm</span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <label class="signup-lambda-field">
                            <span class="input-hint">Amplitude choisie pour la terre</span>
                            <strong><span data-signup-program-label><?= h($selectedSignupLabel) ?></span> · λ <span data-signup-lambda-value><?= h((string) $selectedSignupLambda) ?></span> nm</strong>
                            <input
                                type="range"
                                name="lambda_nm"
                                min="<?= h((string) $selectedSignupMinLambda) ?>"
                                max="<?= h((string) $selectedSignupMaxLambda) ?>"
                                step="1"
                                value="<?= h((string) $selectedSignupLambda) ?>"
                                data-signup-lambda-input
                            >
                            <span class="signup-lambda-range"><span data-signup-program-tone><?= h($selectedSignupTone) ?></span> · plage <span data-signup-lambda-range><?= h((string) $selectedSignupMinLambda) ?>–<?= h((string) $selectedSignupMaxLambda) ?> nm</span></span>
                        </label>
                    </section>

                    <label>
                        Secret
                        <input
                            type="password"
                            name="password"
                            placeholder="8 caractères minimum"
                            required
                            minlength="<?= AUTH_MIN_PASSWORD_LENGTH ?>"
                            value="<?= h($form['password']) ?>"
                            autocomplete="new-password"
                        >
                        <span class="input-hint">Il protège ta terre.</span>
                    </label>

                    <input
                        type="hidden"
                        name="timezone"
                        value="<?= h($previewTimezone) ?>"
                        data-timezone-input
                    >

                    <button type="submit">Rejoindre le peuple de l'O</button>
                </form>

                <div class="signup-preview auth-login-preview" aria-labelledby="login-title">
                    <div class="signup-head auth-head">
                        <div>
                            <h3 id="login-title">Connexion</h3>
                            <p class="panel-copy">Retrouver une terre.</p>
                        </div>
                    </div>

                    <form method="post" class="land-form" autocomplete="on">
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                        <label>
                            Terre
                            <input
                                type="text"
                                name="login_identifier"
                                placeholder="ex: nox"
                                required
                                value="<?= h($form['login_identifier']) ?>"
                                autocomplete="username"
                            >
                        </label>

                        <label>
                            Secret
                            <input
                                type="password"
                                name="password"
                                placeholder="mot de passe"
                                required
                                autocomplete="current-password"
                            >
                        </label>

                        <button type="submit">Affirmer sa présence</button>
                    </form>
                </div>

                <div
                    class="signup-preview"
                    data-origin-base="<?= h($originBase) ?>"
                    data-preview-shell
                >
                    <span class="summary-label">Aperçu</span>
                    <strong class="preview-title" data-slug-output><?= h($previewSlug) ?></strong>
                    <div class="preview-grid">
                        <p><span>Lien</span><code data-land-link-output><?= h($originBase . '/land.php?u=' . $previewSlug) ?></code></p>
                        <p><span>Email virtuel</span><code data-email-output><?= h($previewSlug . '@o.local') ?></code></p>
                        <p><span>Fuseau</span><strong data-preview-timezone><?= h($previewTimezone) ?></strong></p>
                        <p><span>Programme</span><strong data-preview-program-label><?= h($selectedSignupLabel) ?></strong></p>
                        <p><span>Tonalité</span><strong data-preview-program-tone><?= h($selectedSignupTone) ?></strong></p>
                        <p><span>Signature</span><strong>λ <span data-signup-lambda-value><?= h((string) $selectedSignupLambda) ?></span> nm</strong></p>
                    </div>
                </div>
            </div>
        </details>
    </section>
    <?php endif; ?>

    <?php if (!$homeVisualOnly): ?>
    <section class="panel reveal guide-panel guide-home-callout" aria-labelledby="guide-home-title">
        <div class="section-topline">
            <div>
                <h2 id="guide-home-title">Porte 00 · 0wlslw0</h2>
                <p class="panel-copy">Le projet entier gagne en clarté quand l’entrée n’est pas un simple formulaire mais une intelligence d’orientation. 0wlslw0 est cette couche-là : le seuil qui comprend avant d’envoyer.</p>
            </div>
            <a class="pill-link" href="<?= h($guideHref) ?>">Ouvrir 0wlslw0</a>
        </div>

        <div class="guide-grid">
            <article class="guide-panel">
                <span class="summary-label">Rôle</span>
                <strong>Faire tenir ensemble les ferries.</strong>
                <p class="panel-copy">Signal adresse, Str3m montre, aZa sédimente, Écho relie — mais 0wlslw0 donne la bonne première phrase au visiteur pour qu’il ne dérive pas avant même d’avoir commencé.</p>
                <div class="action-row">
                    <a class="ghost-link" href="<?= h($guideHref) ?>">Voir le guide complet</a>
                    <a class="ghost-link" href="/str3m">Entrer publiquement</a>
                    <a class="ghost-link" href="/#poser">Poser une terre</a>
                </div>
            </article>

            <article class="guide-panel">
                <span class="summary-label">Phrases de départ</span>
                <ul class="guide-prompt-list">
                    <?php foreach (array_slice($promptSeeds, 0, 3) as $prompt): ?>
                        <li><code><?= h($prompt) ?></code></li>
                    <?php endforeach; ?>
                </ul>
                <p class="panel-copy guide-embed-note">Le centre du torus et le geste en O ouvrent aussi cette porte. Pour une fois, le secret sert à éclaircir plutôt qu’à obscurcir.</p>
            </article>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!$homeVisualOnly): ?>
    <section class="panel reveal surface-panel" id="surface" aria-labelledby="surface-title">
        <div class="section-topline">
            <div>
                <h2 id="surface-title">Temps situé</h2>
                <p class="panel-copy">Ici, l’heure prend terre.</p>
            </div>
            <span class="badge"><?= h($brandDomain) ?></span>
        </div>

        <div class="surface-grid">
            <section class="telemetry-block" aria-labelledby="telemetry-title">
                <h3 id="telemetry-title">Signal</h3>
                <div class="data-grid telemetry-grid">
                    <p>&gt; DOMAINE : <span class="highlight"><?= h($brandDomain) ?></span></p>
                    <p>&gt; MODE : <span class="highlight">terre</span></p>
                    <p>&gt; TEMPS : <span class="highlight">local</span></p>
                    <p class="bootline" id="bootline">[ L'aspiration est en cours... ]</p>
                </div>
            </section>

            <section class="clock-shell" aria-labelledby="signals-title">
                <div>
                    <h3 id="signals-title">Fuseau</h3>
                    <p class="panel-copy">Le temps n’est pas neutre.</p>
                </div>
                <div
                    class="clock"
                    aria-live="polite"
                    data-live-clock
                    data-preview-clock
                    data-timezone="<?= h($previewTimezone) ?>"
                >
                    <p class="clock-label" data-clock-label>Fuseau : —</p>
                    <p class="clock-time" data-clock-time>--:--:--</p>
                    <p class="clock-date" data-clock-date>--</p>
                </div>
            </section>
        </div>
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
