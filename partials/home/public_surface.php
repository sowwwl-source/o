    <section class="hero-archipelago reveal">
        <article class="world-intro world-intro--entry world-intro--threshold world-intro--vu-<?= h($homeHeroVuState) ?>" data-vu-state="<?= h($homeHeroVuState) ?>">
            <span class="summary-label"><?= h($homeStatusLabel) ?></span>
            <h1 class="world-intro-title <?= $authenticatedLand ? 'world-intro-title--linked' : 'world-intro-title--public' ?>">
                <span class="world-intro-title__line world-intro-title__line--primary"><?= h($homeHeroLineOne) ?></span>
                <span class="world-intro-title__line world-intro-title__line--secondary"><?= h($homeHeroLineTwo) ?></span>
            </h1>
            <p class="lead"><?= h($homeLead) ?></p>
            <div class="home-hero-quickbar" aria-label="Actions immédiates du seuil">
                <a class="pill-link home-hero-quickbar__primary" href="<?= h($homePrimaryActionHref) ?>"><?= h($homeHeroPrimaryLabel) ?></a>
                <a class="ghost-link home-hero-quickbar__secondary" href="<?= h($homeHeroSecondaryHref) ?>"><?= h($homeHeroSecondaryLabel) ?></a>
            </div>
            <div class="home-hero-proofline" aria-label="État rapide du seuil">
                <?php foreach ($homeHeroQuickFacts as $fact): ?>
                    <span class="home-hero-proof">
                        <strong><?= h((string) $fact['value']) ?></strong>
                        <small><?= h((string) $fact['label']) ?></small>
                    </span>
                <?php endforeach; ?>
            </div>
            <div class="home-threshold-links" aria-label="Repères du seuil">
                <a class="ghost-link" href="<?= h($guideHref) ?>">Comprendre avec 0wlslw0</a>
                <a class="ghost-link" href="<?= h($publicInstrumentHref) ?>">Instrument · sowwwl.io</a>
                <?php if ($authenticatedLand): ?>
                    <a class="ghost-link" href="<?= h($signalHref) ?>">Signal<?= $unreadSignal > 0 ? ' · ' . $unreadSignal . ' en attente' : ' · boîte' ?></a>
                <?php else: ?>
                    <a class="ghost-link" href="<?= h($mapHref) ?>">Voir le tore</a>
                <?php endif; ?>
            </div>
            <p class="world-intro-note world-intro-note--threshold"><?= h($homeThresholdHint) ?></p>
        </article>

        <nav class="entry-grid editorial-nav" aria-label="Entrées principales du noyau">
            <div class="entry-grid__head">
                <div class="entry-grid__intro">
                    <span class="summary-label">routes immédiates</span>
                    <strong>Choisir sans se perdre.</strong>
                </div>
                <p class="entry-grid__prompt">Choisir en un geste. Si tu préfères la voix, dis simplement la phrase indiquée à 0wlslw0.</p>
            </div>
            <div class="entry-grid__cards">
                <?php foreach ($homeEntryCards as $card): ?>
                    <a href="<?= h((string) $card['href']) ?>" class="<?= h((string) $card['class']) ?>">
                        <span class="entry-card__kicker">
                            <span class="summary-label"><?= h((string) $card['kicker']) ?></span>
                            <span class="entry-card__state"><?= h((string) $card['state']) ?></span>
                        </span>
                        <strong><?= h((string) $card['title']) ?></strong>
                        <span class="entry-card__copy"><?= h((string) $card['copy']) ?></span>
                        <small class="entry-card__hint"><?= h((string) $card['hint']) ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>
    </section>

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
            <article class="home-live-card" aria-label="Courant public du jour">
                <div class="home-live-card__beam" aria-hidden="true"></div>
                <div class="home-live-card__top">
                    <span class="summary-label">courant du jour</span>
                    <span class="home-live-card__mood"><?= h($homeStreamMood) ?></span>
                </div>
                <h3><?= h($homeDailyTitle) ?></h3>
                <p><?= h($homeDailyCopy) ?></p>

                <div class="home-live-card__signals" aria-label="Matières disponibles">
                    <span>
                        <strong>audio</strong>
                        <?= h($dailyAudioPath !== '' ? $homeDailyAudioTitle : 'en veille') ?>
                    </span>
                    <span>
                        <strong>image</strong>
                        <?= h($dailyImagePath !== '' ? $homeDailyImageTitle : 'en veille') ?>
                    </span>
                    <span>
                        <strong>signal</strong>
                        <?= h($homeSignalState) ?>
                    </span>
                </div>

                <div class="home-live-card__actions">
                    <a class="pill-link" href="<?= h($str3mHref) ?>">Ouvrir Str3m</a>
                    <a class="ghost-link" href="<?= h($dailyAudioPath !== '' ? $dailyAudioPath : $azaHref) ?>"><?= $dailyAudioPath !== '' ? 'Écouter la source' : 'Préparer une matière' ?></a>
                </div>
            </article>

            <nav class="home-route-orbit" aria-label="Chaînons publics du seuil">
                <?php foreach ($homeRouteNodes as $routeNode): ?>
                    <a class="home-route-node home-route-node--<?= h((string) $routeNode['kicker']) ?>" href="<?= h((string) $routeNode['href']) ?>">
                        <span class="home-route-node__index"><?= h((string) $routeNode['index']) ?></span>
                        <span class="home-route-node__body">
                            <span class="summary-label"><?= h((string) $routeNode['kicker']) ?></span>
                            <strong><?= h((string) $routeNode['title']) ?></strong>
                            <span><?= h((string) $routeNode['copy']) ?></span>
                        </span>
                        <span class="home-route-node__signal"><?= h((string) $routeNode['signal']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
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

    <?= render_continuity_dome('surface', [
        'host' => $host,
        'land' => $authenticatedLand,
        'land_slug' => $activeLandSlug,
        'land_username' => $activeLandUsername,
        'unread_signal' => $unreadSignal,
    ]) ?>
