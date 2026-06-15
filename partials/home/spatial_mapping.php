            <section class="panel reveal mapping-panel mapping-panel--genie xyz-surface-mapping" id="mapping" aria-labelledby="mapping-title" data-mapping-genie data-mapping-theme="real" data-xyz-archi-section data-xyz-archi-label="cartographie">
                <div class="mapping-panel__veil" aria-hidden="true">
                    <span class="mapping-panel__veil-orbit mapping-panel__veil-orbit--outer"></span>
                    <span class="mapping-panel__veil-orbit mapping-panel__veil-orbit--inner"></span>
                    <span class="mapping-panel__veil-glow"></span>
                </div>

                <div class="section-topline mapping-panel__topline">
                    <div>
                        <p class="eyebrow mapping-panel__eyebrow">
                            <strong><?= h($spatialSurfaceHostLabel) ?></strong>
                            <span><?= h($spatialMappingModeLabel) ?></span>
                        </p>
                        <h2 id="mapping-title"><?= h($spatialMappingTitle) ?></h2>
                        <p class="panel-copy mapping-panel__copy"><?= h($spatialMappingCopy) ?></p>
                    </div>
                    <span class="badge mapping-panel__badge"><?= h($spatialMappingBadge) ?></span>
                </div>

                <div class="mapping-panel__scene">
                    <div class="mapping-genie" role="list" aria-label="<?= h($isSowwwlIo ? 'Cartographie du volume' : 'Cartographie du tore') ?>" data-mapping-genie-list>
                        <button
                            type="button"
                            class="mapping-genie-card mapping-genie-card--real is-active"
                            data-mapping-card
                            data-mapping-tone="real"
                            data-mapping-label="Réalité"
                            data-mapping-whisper="Rue, souffle, corps, lumière : le monde avant sa traduction."
                            data-mapping-summary="La réalité contient les phénomènes, les gestes, les traces et les intensités qui n’ont pas encore trouvé leur forme navigable."
                            aria-expanded="true"
                            aria-pressed="true"
                            aria-describedby="mapping-keys"
                        >
                            <span class="mapping-genie-card__mist" aria-hidden="true"></span>
                            <span class="mapping-genie-card__sigil" aria-hidden="true">🌍</span>
                            <span class="mapping-genie-card__head">
                                <span class="summary-label">plan 01</span>
                                <strong>Réalité</strong>
                            </span>
                            <span class="mapping-genie-card__body">Présences, sons, météo, rencontres, lumière, usage. Tout ce qui touche avant d’être lu.</span>
                        </button>

                        <div class="mapping-genie-link" aria-hidden="true">
                            <span class="mapping-genie-link__line"></span>
                            <span class="mapping-genie-link__label">traduction</span>
                        </div>

                        <button
                            type="button"
                            class="mapping-genie-card mapping-genie-card--plasma"
                            data-mapping-card
                            data-mapping-tone="plasma"
                            data-mapping-label="Plasma"
                            data-mapping-whisper="Le flux garde, transforme, relie."
                            data-mapping-summary="Le plasma est la couche de calcul, de mémoire et de circulation. Il transporte le réel jusqu’à la surface sous forme de signes, de données et de rythme."
                            aria-expanded="false"
                            aria-pressed="false"
                            aria-describedby="mapping-keys"
                        >
                            <span class="mapping-genie-card__mist" aria-hidden="true"></span>
                            <span class="mapping-genie-card__sigil" aria-hidden="true">💧</span>
                            <span class="mapping-genie-card__head">
                                <span class="summary-label">plan 02</span>
                                <strong>Plasma</strong>
                            </span>
                            <span class="mapping-genie-card__body">Flux, mémoire, calcul, médiation. La couche fluide qui rend le réel transmissible sans l’éteindre.</span>
                        </button>

                        <div class="mapping-genie-link" aria-hidden="true">
                            <span class="mapping-genie-link__line"></span>
                            <span class="mapping-genie-link__label">déploiement</span>
                        </div>

                        <button
                            type="button"
                            class="mapping-genie-card mapping-genie-card--torus"
                            data-mapping-card
                            data-mapping-tone="torus"
                            data-mapping-label="Tore"
                            data-mapping-whisper="<?= h($spatialMappingTorusWhisper) ?>"
                            data-mapping-summary="<?= h($spatialMappingTorusSummary) ?>"
                            aria-expanded="false"
                            aria-pressed="false"
                            aria-describedby="mapping-keys"
                        >
                            <span class="mapping-genie-card__mist" aria-hidden="true"></span>
                            <span class="mapping-genie-card__sigil" aria-hidden="true">🌀</span>
                            <span class="mapping-genie-card__head">
                                <span class="summary-label">plan 03</span>
                                <strong>Tore</strong>
                            </span>
                            <span class="mapping-genie-card__body"><?= h($spatialTorusBodyCopy) ?></span>
                        </button>
                    </div>

                    <aside class="mapping-chorus xyz-surface-chorus" aria-live="polite">
                        <span class="summary-label">écho actif</span>
                        <strong class="mapping-chorus__title" data-mapping-active-label>Réalité</strong>
                        <p class="mapping-chorus__whisper" data-mapping-active-whisper>Rue, souffle, corps, lumière : le monde avant sa traduction.</p>
                        <p class="mapping-chorus__summary" data-mapping-active-summary>La réalité contient les phénomènes, les gestes, les traces et les intensités qui n’ont pas encore trouvé leur forme navigable.</p>
                        <p class="mapping-chorus__ra" data-mapping-ra-note><?= h($spatialMappingRaNote) ?></p>
                        <p class="mapping-chorus__hint" id="mapping-keys">Tab pour parcourir chaque plan. Entrée ou clic pour l activer. En mode casque web, les flèches, Home et End gardent aussi la dérive.</p>
                        <div class="mapping-chorus__meter" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </aside>
                </div>

                <p class="mapping-panel__reading"><strong>Lecture&nbsp;:</strong> <?= h($spatialMappingReading) ?></p>
            </section>
