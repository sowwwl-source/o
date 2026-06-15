        <header class="xyz-surface-head">
            <p class="eyebrow xyz-surface-head__eyebrow"><strong><?= h($spatialSurfaceHostLabel) ?></strong> <span><?= h($spatialSurfaceEyebrow) ?></span></p>
            <h1 class="xyz-surface-head__title"><?= h($spatialSurfaceTitle) ?></h1>
            <p class="lead xyz-surface-head__lead"><?= h($spatialSurfaceLead) ?></p>

            <div class="xyz-surface-actions">
                <button type="button" class="pill-link xyz-camera-toggle" data-xyz-camera-start><?= h($spatialActivationLabel) ?></button>
                <button type="button" class="ghost-link xyz-camera-toggle" data-xyz-camera-demo aria-pressed="false">Terre &amp; Mine</button>
                <button type="button" class="ghost-link xyz-camera-toggle hidden" data-xyz-camera-stop><?= h($spatialReleaseLabel) ?></button>
                <a class="ghost-link" href="<?= h($authenticatedLand ? o_route_href('/land', ['u' => $activeLandSlug]) : $homeConnectionHref) ?>"><?= h($authenticatedLand ? 'Ouvrir ma terre' : 'Relier une terre') ?></a>
                <a class="ghost-link" href="<?= h($guideHref) ?>">Passer par 0wlslw0</a>
            </div>

            <div class="xyz-surface-meta" aria-label="Signature de la surface">
                <span class="badge badge-glass">λ <?= h((string) $activeLambda) ?> nm</span>
                <span class="badge badge-glass"><?= h($activeLandLabel) ?></span>
                <span class="badge badge-glass"><?= h((string) ($dailyStream['mood'] ?? 'calm')) ?></span>
                <span class="badge badge-glass">local d’abord</span>
            </div>

            <nav class="xyz-surface-wayfinder" aria-label="<?= h($spatialWayfinderAria) ?>">
                <div class="xyz-surface-wayfinder__intro">
                    <span class="summary-label"><?= h($spatialWayfinderIntroLabel) ?></span>
                    <strong>Choisir sans masquer le centre.</strong>
                    <a href="#xyz-panel-routes">voir les sorties</a>
                </div>
                <?php foreach ($surfaceWayfinderAxes as $axis): ?>
                    <a
                        class="xyz-surface-wayfinder__item xyz-surface-wayfinder__item--<?= h((string) $axis['axis']) ?>"
                        href="<?= h((string) $axis['href']) ?>"
                    >
                        <span class="summary-label"><?= h((string) $axis['kicker']) ?></span>
                        <strong><?= h((string) $axis['title']) ?></strong>
                        <span><?= h((string) $axis['copy']) ?></span>
                        <small><?= h((string) $axis['signal']) ?></small>
                    </a>
                <?php endforeach; ?>
            </nav>
        </header>
