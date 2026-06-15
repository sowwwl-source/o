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
