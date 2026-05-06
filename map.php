<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host = request_host();
if ($host === 'sowwwl.xyz' || $host === 'www.sowwwl.xyz') {
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/map');
    header('Location: https://sowwwl.com' . $path, true, 302);
    exit;
}

$brandDomain = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Map — surface torique vivante de <?= h(SITE_TITLE) ?>, terres et courants d’activité.">
    <meta name="theme-color" content="#09090b">
    <title>Map — <?= h(SITE_TITLE) ?></title>
<?= render_o_page_head_assets('main') ?>
</head>
<body class="experience map-view">
<?= render_skip_link() ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

<main <?= main_landmark_attrs() ?> class="map-shell reveal on">
    <header class="map-head">
        <div>
            <p class="eyebrow"><strong>map</strong> <span>tore vivant / activité</span></p>
            <h1 class="land-title"><strong>Le tore des terres actives</strong> <span>nœuds et courants chauds</span></h1>
        </div>
        <div class="meta">
            <a class="meta-pill meta-pill-link" href="/">retour au noyau</a>
            <a class="meta-pill meta-pill-link" href="/str3m">str3m</a>
            <span class="meta-pill">surface vivante</span>
        </div>
    </header>

    <div id="sowwwl-map-surface" class="map-fallback" aria-live="polite"></div>
    <section class="map-lexical-console" aria-labelledby="map-lexical-title">
        <form class="map-lexical-console__bar" data-map-lexical-form>
            <span class="map-lexical-console__prompt" aria-hidden="true">λ&gt;</span>
            <label class="sr-only" for="map-lexical-input" id="map-lexical-title">Console lexicale de la map</label>
            <input id="map-lexical-input" name="q" type="search" placeholder="chaud · terres · courants · @slug · fragment lexical" autocomplete="off" data-map-lexical-input>
            <button type="submit">lire</button>
        </form>
        <div class="map-lexical-console__hints" aria-label="Commandes de la console">
            <span class="map-lexical-chip">chaud</span>
            <span class="map-lexical-chip">terres</span>
            <span class="map-lexical-chip">courants</span>
            <span class="map-lexical-chip">@slug</span>
            <span class="map-lexical-chip">aide</span>
        </div>
        <div class="map-lexical-console__output" data-map-lexical-output aria-live="polite"></div>
    </section>
    <p class="map-note" id="map-note">Chargement du tore vivant…</p>
</main>

</body>
</html>
