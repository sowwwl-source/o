    <?= render_home_partial('public_hero_section', get_defined_vars()) ?>
    <?= render_home_partial('public_polish_section', get_defined_vars()) ?>

    <?= render_continuity_dome('surface', [
        'host' => $host,
        'land' => $authenticatedLand,
        'land_slug' => $activeLandSlug,
        'land_username' => $activeLandUsername,
        'unread_signal' => $unreadSignal,
    ]) ?>
