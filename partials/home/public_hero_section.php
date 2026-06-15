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

        <?= render_home_partial('public_entry_nav', get_defined_vars()) ?>
    </section>
