        <section class="panel reveal xyz-surface-foundation" aria-labelledby="xyz-surface-foundation-title">
            <div class="section-topline">
                <div>
                    <span class="summary-label">ordre de lecture</span>
                    <h2 id="xyz-surface-foundation-title">Quatre prises tiennent sowwwl.io.</h2>
                    <p class="panel-copy">Le volume devient lisible quand on sait d abord où se pose l adresse, où sédimente la matière, où entre le réel et comment une présence répond.</p>
                </div>
                <span class="badge">terre / memoire / capteurs / presence</span>
            </div>
            <div class="land-focus-grid">
                <?php foreach ($spatialReadingOrderCards as $card): ?>
                <article class="land-focus-card">
                    <p class="land-card-kicker"><?= h((string) $card['kicker']) ?></p>
                    <h3><?= h((string) $card['title']) ?></h3>
                    <p class="land-card-copy"><?= h((string) $card['copy']) ?></p>
                    <p class="panel-copy"><?= h((string) $card['meta']) ?></p>
                    <div class="xyz-surface-route-links">
                        <?php foreach ($card['links'] as $link): ?>
                            <a class="ghost-link" href="<?= h((string) $link['href']) ?>"><?= h((string) $link['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
