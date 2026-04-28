<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/signals.php';

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
if ($host === 'sowwwl.xyz' || $host === 'www.sowwwl.xyz') {
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/signal_item.php');
    header('Location: https://sowwwl.com' . $path, true, 302);
    exit;
}

$id = trim((string) ($_GET['id'] ?? ''));
$signal = $id !== '' ? read_signal($id) : null;
$currentLand = current_authenticated_land();

if (!$signal || !signal_can_view($signal, $currentLand)) {
    http_response_code(404);
}

$brandDomain = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
$created = isset($_GET['created']) && $_GET['created'] === '1';
$isOwner = $signal ? signal_is_owner($signal, $currentLand) : false;
$signalDate = $signal ? human_created_label((string) (($signal['published_at'] ?? '') ?: ($signal['created_at'] ?? ''))) : null;
$signalLand = null;

if ($signal && !empty($signal['land_slug'])) {
    try {
        $signalLand = find_land((string) $signal['land_slug']);
    } catch (InvalidArgumentException $exception) {
        $signalLand = null;
    }
}

$ambientProfile = $signalLand ? land_visual_profile($signalLand) : land_collective_profile('dense');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Signal — détail d'une transmission sur <?= h($brandDomain) ?>.">
    <meta name="theme-color" content="#09090b">
    <title><?= $signal ? h((string) $signal['title']) . ' — Signal' : 'Signal introuvable — ' . h($brandDomain) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
</head>
<body class="experience signal-view">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'dense', 'signal') ?>

<main class="layout page-shell">
    <?php if ($signal): ?>
        <header class="hero page-header reveal">
            <p class="eyebrow"><strong>ferry 01</strong> <span>Flux / <?= h((string) $signal['kind']) ?></span></p>
            <h1 class="land-title signal-title">
                <strong><?= h((string) $signal['title']) ?></strong>
                <span><?= h((string) $signal['land_username']) ?></span>
            </h1>
            <p class="lead">Trace isolée dans l'océan public.</p>

            <div class="land-meta">
                <a class="meta-pill meta-pill-link" href="/signal.php">retour au flux</a>
                <span class="meta-pill"><?= h($signalDate ?? 'maintenant') ?></span>
                <span class="meta-pill"><?= h((string) $signal['kind']) ?></span>
                <?php if ($isOwner): ?>
                    <span class="meta-pill"><?= h((string) $signal['visibility']) ?></span>
                    <span class="meta-pill"><?= h((string) $signal['status']) ?></span>
                    <a class="meta-pill meta-pill-link" href="/land.php?u=<?= rawurlencode((string) $signal['land_slug']) ?>">gérer ma terre</a>
                <?php else: ?>
                    <a class="meta-pill meta-pill-link" href="/land.php?u=<?= rawurlencode((string) $signal['land_slug']) ?>">explorer l'île</a>
                    <?php if ($currentLand): ?>
                        <a class="meta-pill meta-pill-link" style="color: rgb(var(--land-secondary-rgb)); border-color: rgba(var(--land-secondary-rgb)/0.5);" href="/echo.php?u=<?= rawurlencode((string) $signal['land_username']) ?>">écho direct</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </header>

        <section class="panel reveal signal-detail-shell" aria-labelledby="signal-detail-title">
            <div class="section-topline">
                <div>
                    <h2 id="signal-detail-title">Empreinte</h2>
                    <p class="panel-copy">Trace laissée dans le flux.</p>
                </div>
                <?php if ($created): ?>
                    <span class="badge">signal transmis</span>
                <?php endif; ?>
            </div>

            <?php if ($created): ?>
                <div class="flash flash-success" aria-live="polite">
                    <p>Le signal a bien été transmis.</p>
                </div>
            <?php endif; ?>

            <article class="signal-full">
                <div class="signal-body">
                    <p><?= nl2br(h((string) $signal['body'])) ?></p>
                </div>

                <?php if (!empty($signal['tags']) && is_array($signal['tags'])): ?>
                    <div class="signal-tags">
                        <?php foreach ($signal['tags'] as $tag): ?>
                            <span class="meta-pill signal-tag">#<?= h((string) $tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    <?php else: ?>
        <section class="hero page-header reveal">
            <p class="eyebrow">signal introuvable</p>
            <h1>Cette transmission n’est pas lisible ici.</h1>
            <p class="lead">Elle est peut-être privée, brouillon, ou simplement absente.</p>
            <div class="hero-actions">
                <a class="pill-link" href="/signal.php">Retour au flux</a>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
