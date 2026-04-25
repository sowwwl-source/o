<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
if ($host === 'sowwwl.xyz' || $host === 'www.sowwwl.xyz') {
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: https://sowwwl.com' . $path, true, 302);
    exit;
}

$identifier = (string) ($_GET['u'] ?? '');
$land = null;
$notFound = false;

try {
    $land = find_land($identifier);
    $notFound = $land === null;
} catch (InvalidArgumentException $exception) {
    $notFound = true;
}

if ($notFound) {
    http_response_code(404);
}

$created = isset($_GET['created']) && $_GET['created'] === '1';
$sharePath = $land ? '/land.php?u=' . rawurlencode((string) $land['slug']) : '/';
$shareUrl = site_origin() . $sharePath;
$brandDomain = preg_replace('/^www\./', '', $host ?: 'sowwwl.com');
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= $land ? h((string) $land['username']) . ' — espace personnel sur ' . h($brandDomain) : 'Terre introuvable — ' . h($brandDomain) ?>">
    <meta name="theme-color" content="#09090b">
    <title><?= $land ? h((string) $land['username']) . ' — ' . h($brandDomain) : 'Terre introuvable — ' . h($brandDomain) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
</head>
<body class="experience land-view">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

<main class="layout page-shell">
    <?php if ($land): ?>
        <header class="hero page-header reveal">
            <p class="eyebrow"><strong>terre active</strong> <span><?= h((string) $land['slug']) ?></span></p>
            <h1 class="land-title">
                <strong><?= h((string) $land['username']) ?></strong>
                <span>I inverse</span>
            </h1>
            <p class="lead">
                Terre posée. Fuseau gardé.
            </p>

            <div class="land-meta">
                <span class="meta-pill"><?= h((string) $land['timezone']) ?></span>
                <span class="meta-pill"><?= h((string) $land['email_virtual']) ?></span>
                <span class="meta-pill"><?= h(human_created_label((string) ($land['created_at'] ?? '')) ?? 'maintenant') ?></span>
            </div>
        </header>

        <section class="panel-shell">
            <section class="panel reveal" aria-labelledby="clock-title">
                <div class="section-topline">
                    <div>
                        <h2 id="clock-title">Temps</h2>
                        <p class="panel-copy">Local.</p>
                    </div>
                    <?php if ($created): ?>
                        <span class="badge">terre posée</span>
                    <?php endif; ?>
                </div>

                <?php if ($created): ?>
                    <div class="flash flash-success" aria-live="polite">
                        <p>Votre terre est posée.</p>
                    </div>
                <?php endif; ?>

                <div
                    class="clock"
                    aria-live="polite"
                    data-live-clock
                    data-timezone="<?= h((string) $land['timezone']) ?>"
                >
                    <p class="clock-label" data-clock-label>Fuseau : —</p>
                    <p class="clock-time" data-clock-time>--:--:--</p>
                    <p class="clock-date" data-clock-date>--</p>
                </div>

                <div class="summary-grid">
                    <article class="summary-card">
                        <span class="summary-label">Zone</span>
                        <strong class="summary-value summary-value-small"><?= h((string) $land['zone_code']) ?></strong>
                    </article>
                    <article class="summary-card">
                        <span class="summary-label">Ouverture</span>
                        <strong class="summary-value summary-value-small"><?= h(human_created_label((string) ($land['created_at'] ?? '')) ?? 'maintenant') ?></strong>
                    </article>
                </div>
            </section>

            <aside class="panel reveal" aria-labelledby="ritual-title">
                <h2 id="ritual-title">Retour</h2>
                <p class="panel-copy">Trois gestes.</p>
                <p class="land-note">
                    Revenir. Déposer. Copier.
                </p>
                <div class="action-row">
                    <a class="pill-link" href="/">Retour au noyau</a>
                    <a class="ghost-link" href="/aza.php?u=<?= rawurlencode((string) $land['slug']) ?>">Ouvrir aZa</a>
                    <button
                        type="button"
                        class="copy-button"
                        data-copy-link="<?= h($shareUrl) ?>"
                    >Copier l'adresse</button>
                </div>
            </aside>
        </section>
    <?php else: ?>
        <section class="hero page-header reveal">
            <p class="eyebrow">terre introuvable</p>
            <h1>Cette porte ne mène nulle part.</h1>
            <p class="lead">
                Rien ici.
            </p>
            <div class="hero-actions">
                <a class="pill-link" href="/">Revenir à l’accueil</a>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
