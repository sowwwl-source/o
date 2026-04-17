<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

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
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = trim((string) ($_SERVER['HTTP_HOST'] ?? SITE_DOMAIN));
$shareUrl = $host !== '' ? $scheme . '://' . $host . $sharePath : $sharePath;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="sowwwl.xyz — espace personnel.">
    <meta name="theme-color" content="#09090b">
    <title><?= $land ? h((string) $land['username']) . ' — sowwwl.xyz' : 'Terre introuvable — sowwwl.xyz' ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="/styles.css">
    <script defer src="/main.js"></script>
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
                <span><?= h(SITE_TAGLINE) ?></span>
            </h1>
            <p class="lead">
                Ta terre est posée. Elle garde ton fuseau, ton nom d’usage, et une porte simple pour revenir.
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
                        <h2 id="clock-title">Temps local</h2>
                        <p class="panel-copy">Le temps de ta terre, calculé en direct dans le navigateur.</p>
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
                        <span class="summary-label">Nom d’usage</span>
                        <strong class="summary-value"><?= h((string) $land['username']) ?></strong>
                        <p>Le nom visible de ta présence sur cette terre.</p>
                    </article>
                    <article class="summary-card">
                        <span class="summary-label">Code zone</span>
                        <strong class="summary-value summary-value-small"><?= h((string) $land['zone_code']) ?></strong>
                        <p>Le repère utilisé pour synchroniser le temps vivant.</p>
                    </article>
                    <article class="summary-card">
                        <span class="summary-label">Ouverture</span>
                        <strong class="summary-value summary-value-small"><?= h(human_created_label((string) ($land['created_at'] ?? '')) ?? 'maintenant') ?></strong>
                        <p>Première apparition de cette terre dans la constellation.</p>
                    </article>
                </div>
            </section>

            <aside class="panel reveal" aria-labelledby="ritual-title">
                <h2 id="ritual-title">Rituel</h2>
                <p class="panel-copy">Une terre minuscule a besoin de peu pour rester habitable.</p>
                <p class="land-note">
                    Garde le lien, garde le fuseau, et reviens quand tu veux. Le reste peut grandir plus tard,
                    sans casser la base.
                </p>
                <div class="action-row">
                    <a class="pill-link" href="/">Retour au noyau</a>
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
                Le lien est vide, abîmé, ou la terre n’a jamais été posée.
            </p>
            <div class="hero-actions">
                <a class="pill-link" href="/">Revenir à l’accueil</a>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
