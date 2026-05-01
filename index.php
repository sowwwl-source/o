<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/str3m_media.php';
require_once __DIR__ . '/lib/str3m_daily.php';

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
if ($host === 'sowwwl.xyz' || $host === 'www.sowwwl.xyz') {
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: https://sowwwl.com' . $path, true, 302);
    exit;
}

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if (($host === '0wlslw0.com' || $host === 'www.0wlslw0.com') && ($requestPath === '/' || $requestPath === '/index.php')) {
    header('Location: /0wlslw0.php', true, 302);
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
$csrfToken = csrf_token();
$authenticatedLand = current_authenticated_land();
$form = [
    'username' => '',
    'timezone' => DEFAULT_TIMEZONE,
    'password' => '',
    'login_identifier' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create');
    $form['username'] = trim((string) ($_POST['username'] ?? ''));
    $form['timezone'] = trim((string) ($_POST['timezone'] ?? ''));
    $form['password'] = (string) ($_POST['password'] ?? '');
    $form['login_identifier'] = trim((string) ($_POST['login_identifier'] ?? ''));
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
                $land = create_land($form['username'], $form['timezone'], $form['password']);
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
$originBase = site_origin();
$brandDomain = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
$homeVisualOnly = false;
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

$unreadEchoes = 0;
if ($authenticatedLand) {
    $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM echoes WHERE receiver_username = ? AND is_read = 0");
    $stmtUnread->execute([$activeLandUsername]);
    $unreadEchoes = (int) $stmtUnread->fetchColumn();
}

$homeStatusLabel = $authenticatedLand ? 'terre liée' : 'surface collective';
$homeLead = $authenticatedLand
    ? 'Le torus suit la fréquence de ta terre. Signal laisse passer, aZa retient, str3m affleure.'
    : 'Un espace numérique épuré, public et partagé. Rejoins le mouvement pour un lieu à toi, discret et vivant.';
$homePrimaryActionHref = $authenticatedLand
    ? '/land.php?u=' . rawurlencode($activeLandSlug)
    : '#poser';
$homePrimaryActionLabel = $authenticatedLand ? 'Ouvrir ma terre' : 'Rejoindre le peuple de l\'O';
$guideHref = '/0wlslw0.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= h($brandDomain) ?> — Just the Three of Us. O.n0uSnoImenT.">
    <meta name="theme-color" content="#09090b">
    <title>O. — Le réseau minimal</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
</head>
<body
    class="experience home"
    data-land-program="<?= h($activeLandProgram) ?>"
    data-land-label="<?= h($activeLandLabel) ?>"
    data-land-lambda="<?= h((string) $activeLambda) ?>"
>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<a class="o-signature-mark" href="/" aria-label="Retour au noyau O.">
    <span class="o-signature-mark__glyph" aria-hidden="true">
        <span class="o-signature-mark__ring o-signature-mark__ring--outer"></span>
        <span class="o-signature-mark__ring o-signature-mark__ring--inner"></span>
        <svg class="o-signature-mark__wave" viewBox="0 0 72 18" fill="none" aria-hidden="true" focusable="false">
            <path d="M2 9C12 9 16 5 24 5C31 5 35 12.5 43 12.5C52 12.5 56 8 70 8" vector-effect="non-scaling-stroke" />
        </svg>
        <span class="o-signature-mark__dot"></span>
    </span>
</a>

<div class="world-container" aria-hidden="true">
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
        aria-label="Torus ambiant : glisser pour pivoter, roulette pour traverser, flèches pour dériver. Sur mobile, un appui long puis une glisse permettent aussi de naviguer. Swipe gauche vers Signal, haut vers Str3m, droite vers aZa, bas vers le noyau. Le centre ou un geste en O déclenchent aussi l'accès secret."
    ></canvas>
</div>

<main class="layout ui-overlay">
    <?php if (!$homeVisualOnly): ?>
    <header class="top-bar reveal">
        <span class="eyebrow eyebrow-pill"><?= h($brandDomain) ?></span>
        <div class="top-bar-cluster">
            <?php if ($unreadEchoes > 0): ?>
                <a href="/echo.php" class="badge badge-glass" style="border-color: rgba(var(--land-secondary-rgb) / 0.8); color: rgba(var(--land-secondary-rgb) / 0.9); text-decoration: none;">
                    <?= $unreadEchoes ?> ÉCHO<?= $unreadEchoes > 1 ? 'S' : '' ?> EN ATTENTE
                </a>
            <?php endif; ?>
            <span class="badge badge-glass current-mood"><?= h((string) ($dailyStream['mood'] ?? 'calm')) ?></span>
            <span class="badge badge-glass"><?= h($activeLandLabel) ?></span>
            <span class="badge badge-glass">λ <?= h((string) $activeLambda) ?> nm</span>
        </div>
    </header>

    <section class="hero-archipelago reveal">
        <article class="world-intro">
            <span class="summary-label"><?= h($homeStatusLabel) ?></span>
            <h1><span><?= $authenticatedLand ? 'La terre colore le torus.' : 'O. le réseau minimal' ?></span> <em><?= h($activeLandTone) ?></em></h1>
            <p class="vortex" aria-hidden="true">(.λ.)</p>
            <p class="lead"><?= h($homeLead) ?></p>
            <div class="hero-actions">
                <a class="pill-link" href="/signal.php">Flux</a>
                <a class="ghost-link" href="/str3m.php">Str3m</a>
                <a class="ghost-link" href="<?= h($guideHref) ?>">0wlslw0</a>
                <a class="ghost-link" href="<?= h($homePrimaryActionHref) ?>"><?= h($homePrimaryActionLabel) ?></a>
            </div>
        </article>

        <nav class="island-grid editorial-nav" aria-label="Ferries (Applications)">
            <a href="<?= h($guideHref) ?>" class="island-card">
                <span class="summary-label">Porte 00</span>
                <strong>0wlslw0</strong>
                <span>Comprendre O., choisir un passage, poser une terre.</span>
            </a>
            <a href="/signal.php" class="island-card">
                <span class="summary-label">Ferry 01</span>
                <strong>Flux</strong>
                <span>Émettre, capter, laisser passer.</span>
            </a>
            <a href="/str3m.php" class="island-card">
                <span class="summary-label">Ferry 02</span>
                <strong>Str3m</strong>
                <span>Explorer les îles des autres utilisateurs.</span>
            </a>
            <a href="/aza.php" class="island-card">
                <span class="summary-label">Ferry 03</span>
                <strong>Fichiers</strong>
                <span>Déposer, classer, retrouver.</span>
            </a>
            <a href="/echo.php" class="island-card">
                <span class="summary-label">Ferry 04</span>
                <strong style="display: flex; align-items: center; gap: 0.5rem;">
                    Écho
                    <?php if ($unreadEchoes > 0): ?>
                        <span style="background: rgba(var(--land-secondary-rgb) / 0.8); color: var(--panel-rgb); font-size: 0.8rem; font-weight: 600; padding: 0.1rem 0.5rem; border-radius: 99px;"><?= $unreadEchoes ?></span>
                    <?php endif; ?>
                </strong>
                <span>Résonance directe entre deux terres.</span>
            </a>
        </nav>

        <aside class="land-anchor" id="poser">
            <section class="land-signature" aria-label="Signature de la terre">
                <span class="summary-label">Signature</span>
                <strong class="preview-title"><?= h($authenticatedLand ? $activeLandUsername : 'Str3m public') ?></strong>
                <div class="signature-grid">
                    <p><span>Programme</span><strong><?= h($activeLandLabel) ?></strong></p>
                    <p><span>Longueur d’onde</span><strong>λ <?= h((string) $activeLambda) ?> nm</strong></p>
                    <p><span>Tonalité</span><strong><?= h($activeLandTone) ?></strong></p>
                </div>
                <?php if ($authenticatedLand): ?>
                    <div class="action-row auth-action-row">
                        <a class="pill-link" href="/land.php?u=<?= rawurlencode($activeLandSlug) ?>">Ouvrir la terre</a>
                        <a class="ghost-link" href="/logout.php">Retirer sa présence</a>
                    </div>
                <?php endif; ?>
            </section>

            <details class="minimal-auth"<?= $message !== '' ? ' open' : '' ?>>
                <summary class="signup-summary"><?= $authenticatedLand ? 'Relier une autre terre' : 'Rejoindre le peuple de l\'O' ?></summary>
                <div class="auth-box">
                    <p class="panel-copy">Connexion optionnelle.</p>

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
                        </div>
                    </div>
                </div>
            </details>
        </aside>
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
        <p><?= (int) $pulse['count'] ?> terres / <?= (int) $pulse['timezones'] ?> fuseaux / I inverse</p>
    </footer>
    <?php endif; ?>
</main>
</body>
</html>
