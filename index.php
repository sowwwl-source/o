<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
if ($host === 'sowwwl.xyz' || $host === 'www.sowwwl.xyz') {
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: https://sowwwl.com' . $path, true, 302);
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
$form = [
    'username' => '',
    'timezone' => DEFAULT_TIMEZONE,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['username'] = trim((string) ($_POST['username'] ?? ''));
    $form['timezone'] = trim((string) ($_POST['timezone'] ?? ''));
    $csrfCandidate = (string) ($_POST['csrf_token'] ?? '');
    $honeypot = (string) ($_POST['website'] ?? '');

    if ($form['username'] === '') {
        $message = 'Écris un nom (2 à 42 caractères) pour créer ta terre.';
        $messageType = 'warning';
    } else {
        try {
            guard_land_creation_request($csrfCandidate, $honeypot);
            $land = create_land($form['username'], $form['timezone']);
            header('Location: /land.php?u=' . urlencode((string) $land['slug']) . '&created=1', true, 303);
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

remember_form_rendered_at();

$pulse = land_pulse();
$previewSlug = preview_land_slug($form['username']);
$previewTimezone = $form['timezone'] !== '' ? $form['timezone'] : DEFAULT_TIMEZONE;
$originBase = site_origin();
$brandDomain = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= h($brandDomain) ?> — Just the Three of Us. O.n0uSnoImenT.">
    <meta name="theme-color" content="#09090b">
    <title><?= h($brandDomain) ?> — O.</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
</head>
<body class="experience home">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

<main class="layout">
    <header class="hero reveal">
        <div class="hero-grid">
            <div class="hero-backdrop" aria-hidden="true"></div>

            <section class="hero-copy">
                <span class="eyebrow eyebrow-pill"><?= h($brandDomain) ?> / terre</span>
                <h1><span>Pose une terre.</span> <em>I pour inverser.</em></h1>
                <p class="vortex" aria-hidden="true">(.0.)</p>
                <p class="lead">
                    Un nom. Un fuseau. Une porte.
                </p>
                <div class="hero-actions">
                    <a class="pill-link" href="#poser">Entrer</a>
                    <a class="ghost-link" href="#surface">Temps</a>
                </div>
            </section>

            <div class="hero-cloud">
                <div class="torus-shell">
                    <canvas
                        class="torus-cloud"
                        data-torus-cloud
                        tabindex="0"
                        role="img"
                        aria-label="Nuage thorique navigable : glisser pour pivoter, roulette pour traverser, flèches pour dériver."
                    ></canvas>
                    <p class="torus-hint">x11 · glisser / roulette / flèches</p>
                </div>
            </div>

            <aside class="hero-aside">
                <div class="status-card status-card-primary">
                    <div class="status-label">Mode</div>
                    <div class="status-value"><strong>Calme</strong> / sans centre</div>
                    <p class="status-meta">
                        Double-clic dans le vide ou touche I.
                    </p>
                </div>

                <section class="signup-shell" id="poser" aria-labelledby="install-title">
                    <div class="signup-head">
                        <div>
                            <h2 id="install-title">Entrée</h2>
                            <p class="panel-copy">Minimal.</p>
                        </div>
                        <span class="badge badge-warm">sans mot de passe</span>
                    </div>

                    <?php if ($message !== ''): ?>
                        <div class="flash flash-<?= h($messageType) ?>" aria-live="polite">
                            <p><?= h($message) ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="land-form" autocomplete="off">
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
                            <span class="input-hint">Simple.</span>
                        </label>

                        <input
                            type="hidden"
                            name="timezone"
                            value="<?= h($previewTimezone) ?>"
                            data-timezone-input
                        >

                        <button type="submit">Créer ma terre</button>
                    </form>

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
                </section>
            </aside>
        </div>
    </header>

    <section class="panel reveal surface-panel" id="surface" aria-labelledby="surface-title">
        <div class="section-topline">
            <div>
                <h2 id="surface-title">Temps</h2>
                <p class="panel-copy">I inverse.</p>
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
                    <p class="bootline" id="bootline">[ L'aspiration est en cours... George Duke is ON. ]</p>
                </div>
            </section>

            <section class="clock-shell" aria-labelledby="signals-title">
                <div>
                    <h3 id="signals-title">Fuseau</h3>
                    <p class="panel-copy">Aperçu vivant.</p>
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

    <footer class="site-footer reveal">
        <p><?= (int) $pulse['count'] ?> terres / <?= (int) $pulse['timezones'] ?> fuseaux / I inverse</p>
    </footer>
</main>
</body>
</html>
