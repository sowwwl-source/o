    <section class="home-polish-shell reveal" aria-labelledby="home-polish-title">
        <div class="home-polish-shell__halo" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <header class="home-polish-head">
            <p class="eyebrow"><strong>sowwwl.com</strong> <span>réseau minimal</span></p>
            <h2 id="home-polish-title">Réseau minimal, déjà relié au dôme.</h2>
            <p>La page d’entrée devient une chambre claire : elle montre le courant du jour, les cinq portes actives et les preuves discrètes du tore.</p>
        </header>

        <div class="home-polish-grid">
            <?= render_home_partial('public_live_card', get_defined_vars()) ?>
            <?= render_home_partial('public_route_orbit', get_defined_vars()) ?>
        </div>

        <div class="home-proof-strip" aria-label="Preuves de surface">
            <?php foreach ($homeSurfaceProofs as $proof): ?>
                <span>
                    <strong><?= h((string) $proof['value']) ?></strong>
                    <small><?= h((string) $proof['label']) ?></small>
                </span>
            <?php endforeach; ?>
        </div>
    </section>
