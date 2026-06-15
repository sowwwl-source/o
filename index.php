<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/home_surface.php';

extract(home_surface_request_context(), EXTR_SKIP);
extract(
    home_surface_profile_context(
        $form,
        $authenticatedLand,
        $isSpatialSurface,
        $isLabSurface,
        $requestMethod,
        $homeConnectionRequested,
        $message,
        $userCloudSlug
    ),
    EXTR_SKIP
);
extract(home_surface_signal_context($authenticatedLand), EXTR_SKIP);
extract(home_surface_view_context(get_defined_vars()), EXTR_SKIP);
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
<?= render_o_page_head_assets($pageHeadVariant, $host, ['script_bundle' => $pageScriptBundle]) ?>
<?php if ($isUserCloudChamberEntry): ?>
    <script>
        if (!window.location.hash) {
            if (window.history && typeof window.history.replaceState === "function") {
                window.history.replaceState(null, "", `${window.location.pathname}${window.location.search}#atelier`);
            } else {
                window.location.hash = "#atelier";
            }
        }
    </script>
<?php endif; ?>
</head>
<body
    class="experience home<?= $isSpatialSurface ? ' xyz-surface-view' : '' ?><?= $isSowwwlIo ? ' io-surface-view' : '' ?><?= $isSpatialHeadsetMode ? ' io-headset-mode' : '' ?><?= $isLabSurface ? ' lab-console-view' : '' ?>"
    data-land-program="<?= h($activeLandProgram) ?>"
    data-land-label="<?= h($activeLandLabel) ?>"
    data-land-lambda="<?= h((string) $activeLambda) ?>"
    data-land-tone="<?= h($activeLandTone) ?>"
    data-user-cloud-host="<?= $userCloudSlug !== null ? '1' : '0' ?>"
    data-user-cloud-slug="<?= h($userCloudSlug ?? '') ?>"
    data-user-chamber-entry="<?= $isUserCloudChamberEntry ? '1' : '0' ?>"
>
<?= render_skip_link() ?>
<?= render_nucleus_banner($isLabSurface ? 'atelier' : 'noyau') ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

<?= render_home_partial('connection_meter', get_defined_vars()) ?>

<?= render_home_partial('world_container', get_defined_vars()) ?>

<main <?= main_landmark_attrs() ?> class="layout ui-overlay">
    <?php if ($isUserCloudChamberEntry && $pocketCameraAvailable): ?>
    <?= render_home_partial('chamber_panel', get_defined_vars()) ?>
    <?php endif; ?>

    <?php if ($isSpatialSurface): ?>
    <?= render_home_partial('spatial_surface', get_defined_vars()) ?>
    <?php endif; ?>

    <?php if ($isLabSurface): ?>
    <?= render_home_partial('lab_surface', get_defined_vars()) ?>
    <?php endif; ?>

    <?php if (!$homeVisualOnly): ?>
    <?= render_home_partial('public_surface', get_defined_vars()) ?>
    <?php endif; ?>

    <?php if (!$homeVisualOnly && $authenticatedLand): ?>
    <?= render_home_partial('land_signature', get_defined_vars()) ?>
    <?php endif; ?>

    <?php if (!$homeVisualOnly): ?>
    <?= render_home_partial('site_footer', get_defined_vars()) ?>
    <?php endif; ?>
</main>

</body>
</html>
