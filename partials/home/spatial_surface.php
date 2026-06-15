    <section class="xyz-surface-shell reveal" data-xyz-surface>
        <div class="xyz-surface-shell__veil" aria-hidden="true">
            <span class="xyz-surface-shell__ring xyz-surface-shell__ring--outer"></span>
            <span class="xyz-surface-shell__ring xyz-surface-shell__ring--inner"></span>
            <span class="xyz-surface-shell__pulse"></span>
        </div>

        <?= render_home_partial('spatial_header', get_defined_vars()) ?>

        <?= render_spatial_context_bar('surface', $host) ?>

        <?= render_continuity_dome($isSowwwlIo ? 'instrument' : 'surface', [
            'host' => $host,
            'land' => $authenticatedLand,
            'land_slug' => $activeLandSlug,
            'land_username' => $activeLandUsername,
            'unread_signal' => $unreadSignal,
        ]) ?>

        <?php if ($isSowwwlIo): ?>
        <?= render_home_partial('spatial_foundation', get_defined_vars()) ?>
        <?php endif; ?>

        <div class="xyz-surface-grid">
            <?= render_home_partial('spatial_mapping', get_defined_vars()) ?>
            <?= render_home_partial('spatial_aside', get_defined_vars()) ?>
        </div>
    </section>
