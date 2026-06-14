<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/config.php';

$host = request_host();
$surfaceVariant = current_surface_variant($host);
$isSpatialSurface = $surfaceVariant === 'io';
$pageHeadVariant = pwa_default_app_id($host);
$deviceSlug = normalize_sceptre_slug((string) ($_GET['device'] ?? 'ensemble'));
$sceptreFeedHref = sceptre_feed_href($deviceSlug, $host);
$cameraHref = pocket_camera_view_href(null, $host);
$surfaceHref = sowwwl_instrument_href($host);
$sceptreViewHref = sceptre_view_href($deviceSlug, $host);
$pageTitle = 'Sceptre — ' . SITE_TITLE;
$pageDescription = $isSpatialSurface
    ? 'Sceptre — main physique du volume, climat, geste, percussion et halo pour sowwwl.io et Fenetre.'
    : 'Sceptre — climat, geste, percussion et halo de pilotage pour la surface.';
$sceptreLead = $isSpatialSurface
    ? 'Le sceptre attend encore sa première levée.'
    : 'Le sceptre attend encore son premier souffle.';
$sceptreSummary = $isSpatialSurface
    ? 'Le Pi 3 B+ et son Sensor HAT donnent au volume une main, un climat et un rythme.'
    : 'Le Pi 3 B+ et son Sensor HAT peuvent devenir une main, un climat et un rythme pour la surface.';
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
<body class="experience sceptre-view">
<?= render_skip_link() ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

<main <?= main_landmark_attrs() ?> class="layout ui-overlay">
    <?= render_spatial_context_bar('sceptre', $host, $isSpatialSurface
        ? 'Le sceptre tient ici la main physique du volume: mouvement, halo, rythme et climat restent dans la même présence.'
        : 'Le sceptre tient ici une main physique: mouvement, halo, rythme et climat restent dans la même surface.') ?>

    <section
        class="panel reveal sceptre-console"
        data-sceptre-console-root
        data-sceptre-device="<?= h($deviceSlug) ?>"
        data-sceptre-feed="<?= h($sceptreFeedHref) ?>"
        aria-labelledby="sceptre-console-title"
    >
        <div class="sceptre-console__head">
            <div>
                <p class="eyebrow"><strong>pi.sowwwl.cloud</strong> <span>main physique · climat · rythme</span></p>
                <h1 id="sceptre-console-title">Sceptre</h1>
                <p class="lead" data-sceptre-console-lead><?= h($sceptreLead) ?></p>
            </div>
            <span class="badge badge-glass" data-sceptre-console-badge>veille</span>
        </div>

        <div class="sceptre-console__magic">
            <p class="sceptre-console__spell" data-sceptre-console-spell>silence tenu</p>
            <p class="sceptre-console__summary" data-sceptre-console-summary><?= h($sceptreSummary) ?></p>
        </div>

        <div class="sceptre-console__grid" aria-label="Etat du sceptre">
            <p><span>rituel</span><strong data-sceptre-console-ritual>veille</strong></p>
            <p><span>page</span><strong data-sceptre-console-screen>veille</strong></p>
            <p><span>mouvement</span><strong data-sceptre-console-motion>0%</strong></p>
            <p><span>climat</span><strong data-sceptre-console-climate>neutre</strong></p>
            <p><span>percu</span><strong data-sceptre-console-percussion>0%</strong></p>
            <p><span>halo</span><strong data-sceptre-console-halo>0%</strong></p>
        </div>

        <div class="sceptre-console__meters">
            <label class="sceptre-console__meter">
                <span>tempo</span>
                <strong data-sceptre-console-tempo>0%</strong>
            </label>
            <label class="sceptre-console__meter">
                <span>filtre</span>
                <strong data-sceptre-console-filter>0%</strong>
            </label>
            <label class="sceptre-console__meter">
                <span>negatif</span>
                <strong data-sceptre-console-negative>0%</strong>
            </label>
            <label class="sceptre-console__meter">
                <span><?= h($isSpatialSurface ? 'volume' : 'tore') ?></span>
                <strong data-sceptre-console-spin>0%</strong>
            </label>
        </div>

        <div class="action-row sceptre-console__actions">
            <a class="pill-link" href="<?= h($surfaceHref) ?>">Ouvrir sowwwl.io</a>
            <a class="ghost-link" href="<?= h($cameraHref) ?>">Fenetre</a>
            <a class="ghost-link" href="<?= h($sceptreFeedHref) ?>">JSON</a>
            <a class="ghost-link" href="<?= h($sceptreViewHref) ?>">Recharger</a>
        </div>
    </section>
</main>
</body>
</html>
