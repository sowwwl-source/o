<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host          = request_host();
$brandDomain   = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';

$tokenRaw      = trim((string) ($_GET['t'] ?? ''));
$t0k           = $tokenRaw !== '' ? t0k_find_by_token($tokenRaw) : null;
$authenticatedLand = current_authenticated_land();

// Redirect to sh0re if authenticated and t0k is active
if ($t0k && ($t0k['status'] ?? '') === 'active' && $authenticatedLand) {
    $partner = t0k_partner_slug($t0k, (string) $authenticatedLand['slug']);
    if ($partner !== '') {
        header('Location: /sh0re?u=' . rawurlencode($partner), true, 302);
        exit;
    }
}

// Resolve the land that owns the t0k (from_land)
$ownerLand = null;
if ($t0k) {
    try {
        $ownerLand = find_land((string) ($t0k['from_land'] ?? ''));
    } catch (Throwable) {
        $ownerLand = null;
    }
}

$ambientProfile = $ownerLand
    ? land_visual_profile($ownerLand)
    : land_collective_profile('nocturnal');

$statusLabel = $t0k ? t0k_status_label((string) ($t0k['status'] ?? '')) : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="T0k — fragment du n0us dans <?= h(SITE_TITLE) ?>.">
    <meta name="theme-color" content="#09090b">
    <title>T0k<?= $t0k ? ' · ' . h(t0k_format_token((string) $t0k['token'])) : '' ?> — <?= h(SITE_TITLE) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
</head>
<body class="experience n-view">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'nocturnal', 'n0us') ?>

<main class="layout page-shell">

    <header class="hero page-header reveal">
        <p class="eyebrow"><strong>t0k</strong> <span>fragment du n0us</span></p>
        <?php if (!$t0k): ?>
            <h1 class="land-title">
                <strong>Ce t0k n'existe pas.</strong>
                <span>ou n'existe plus.</span>
            </h1>
            <div class="land-meta">
                <a class="meta-pill meta-pill-link" href="/">Noyau</a>
                <a class="meta-pill meta-pill-link" href="/str3m">Str3m</a>
            </div>
        <?php elseif (($t0k['status'] ?? '') === 'dissolved'): ?>
            <h1 class="land-title">
                <strong><?= h(t0k_format_token((string) $t0k['token'])) ?></strong>
                <span>ce n0us a vécu.</span>
            </h1>
        <?php elseif (($t0k['status'] ?? '') === 'declined'): ?>
            <h1 class="land-title">
                <strong><?= h(t0k_format_token((string) $t0k['token'])) ?></strong>
                <span>pas cette fois.</span>
            </h1>
        <?php else: ?>
            <h1 class="land-title">
                <strong><?= h(t0k_format_token((string) $t0k['token'])) ?></strong>
                <span><?= h($statusLabel) ?></span>
            </h1>
            <div class="land-meta">
                <?php if ($ownerLand): ?>
                    <a class="meta-pill meta-pill-link" href="/land.php?u=<?= rawurlencode((string) $ownerLand['slug']) ?>">
                        Terre de <?= h((string) $ownerLand['username']) ?>
                    </a>
                    <a class="meta-pill meta-pill-link" href="/sh0re?u=<?= rawurlencode((string) $ownerLand['slug']) ?>">
                        Sh0re
                    </a>
                <?php endif; ?>
                <?php if ($authenticatedLand): ?>
                    <a class="meta-pill meta-pill-link" href="/sh0re">Mon sh0re</a>
                <?php else: ?>
                    <a class="meta-pill meta-pill-link" href="/">Ouvrir une land</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </header>

    <?php if ($t0k && in_array($t0k['status'] ?? '', ['pending', 'active'], true)): ?>
    <section class="panel reveal n-t0k-panel">
        <div class="t0k-card t0k-card-<?= h((string) $t0k['status']) ?>">
            <div class="t0k-token"><?= h(t0k_format_token((string) $t0k['token'])) ?></div>
            <p class="t0k-route">
                <?= h((string) $t0k['from_land']) ?>
                <span>→</span>
                <?= h((string) $t0k['to_land']) ?>
            </p>
            <?php if (!empty($t0k['notes'])): ?>
                <p class="t0k-notes"><?= h((string) $t0k['notes']) ?></p>
            <?php endif; ?>
            <?php if (!empty($t0k['formed_at'])): ?>
                <p class="t0k-date">n0us formé le <?= h(substr((string) $t0k['formed_at'], 0, 10)) ?></p>
            <?php elseif (!empty($t0k['sent_at'])): ?>
                <p class="t0k-date">lancé le <?= h(substr((string) $t0k['sent_at'], 0, 10)) ?></p>
            <?php endif; ?>
        </div>

        <?php if (!$authenticatedLand): ?>
        <div class="action-row">
            <a class="pill-link" href="/">Voulez-vous grandir avec moi ?</a>
        </div>
        <p class="panel-copy">Ce t0k vient d'une land sur <?= h($brandDomain) ?>. Pour répondre, ouvre ta propre land.</p>
        <?php elseif (($t0k['status'] ?? '') === 'pending' && (string) $authenticatedLand['slug'] === (string) ($t0k['to_land'] ?? '')): ?>
        <div class="action-row">
            <form method="post" action="/sh0re">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="accept">
                <input type="hidden" name="t0k_id" value="<?= h((string) $t0k['id']) ?>">
                <button type="submit" class="pill-link">Accepter · former le n0us</button>
            </form>
            <form method="post" action="/sh0re">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="decline">
                <input type="hidden" name="t0k_id" value="<?= h((string) $t0k['id']) ?>">
                <button type="submit" class="ghost-link">Décliner</button>
            </form>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (!$t0k): ?>
    <section class="panel reveal">
        <h2>T0k inconnu</h2>
        <p class="panel-copy">
            Ce token ne correspond à aucun t0k connu sur ce réseau.
            <?= $tokenRaw !== '' ? 'Token cherché : <code>' . h($tokenRaw) . '</code>.' : '' ?>
        </p>
        <div class="action-row">
            <a class="pill-link" href="/str3m">Str3m</a>
            <a class="ghost-link" href="/">Noyau</a>
        </div>
    </section>
    <?php endif; ?>

</main>
</body>
</html>
