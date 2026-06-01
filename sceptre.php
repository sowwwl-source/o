<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host = request_host();
$pageHeadVariant = pwa_default_app_id($host);
$deviceSlug = normalize_sceptre_slug((string) ($_GET['device'] ?? 'ensemble'));
$sceptreFeedHref = sceptre_feed_href($deviceSlug, $host);
$cameraHref = pocket_camera_view_href(null, $host);
$surfaceHref = o_route_href('/#xyz-panel-instrument', [], $host);
$sceptreViewHref = sceptre_view_href($deviceSlug, $host);
$pageTitle = 'Sceptre harmonique — ' . SITE_TITLE;
$pageDescription = 'Console du sceptre harmonique — climat, geste, percussion et magie de pilotage pour sowwwl.io et Fenetre harmonique.';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= h($pageDescription) ?>">
    <meta name="theme-color" content="#09090b">
    <title><?= h($pageTitle) ?></title>
<?= render_o_page_head_assets($pageHeadVariant, $host) ?>
</head>
<body class="experience sceptre-view">
<?= render_skip_link() ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

<main <?= main_landmark_attrs() ?> class="layout ui-overlay">
    <section
        class="panel reveal sceptre-console"
        data-sceptre-console-root
        data-sceptre-device="<?= h($deviceSlug) ?>"
        data-sceptre-feed="<?= h($sceptreFeedHref) ?>"
        aria-labelledby="sceptre-console-title"
    >
        <div class="sceptre-console__head">
            <div>
                <p class="eyebrow"><strong>pi.sowwwl.cloud</strong> <span>sceptre harmonique</span></p>
                <h1 id="sceptre-console-title">Sceptre harmonique</h1>
                <p class="lead" data-sceptre-console-lead>Le sceptre attend encore son premier souffle.</p>
            </div>
            <span class="badge badge-glass" data-sceptre-console-badge>veille</span>
        </div>

        <div class="sceptre-console__magic">
            <p class="sceptre-console__spell" data-sceptre-console-spell>silence tenu</p>
            <p class="sceptre-console__summary" data-sceptre-console-summary>Le Pi 3 B+ et son Sensor HAT peuvent devenir une main, un climat et un rythme pour la surface.</p>
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
                <span>tore</span>
                <strong data-sceptre-console-spin>0%</strong>
            </label>
        </div>

        <div class="action-row sceptre-console__actions">
            <a class="pill-link" href="<?= h($surfaceHref) ?>">Ouvrir sowwwl.io</a>
            <a class="ghost-link" href="<?= h($cameraHref) ?>">Fenetre harmonique</a>
            <a class="ghost-link" href="<?= h($sceptreFeedHref) ?>">JSON</a>
            <a class="ghost-link" href="<?= h($sceptreViewHref) ?>">Recharger</a>
        </div>
    </section>
</main>
</body>
</html>
