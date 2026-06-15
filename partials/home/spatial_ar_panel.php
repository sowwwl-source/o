                <article class="xyz-surface-note xyz-surface-note--modulation" data-xyz-ar-modulation data-xyz-ar-mode="<?= h($isSowwwlIo ? 'anchor' : 'weave') ?>">
                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-ar" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="modulation RA" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="0">
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">05 RA</span>
                            <strong>Modulation situee</strong>
                            <span class="xyz-archi-panel__meta">reel, plasma, tore</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <strong data-xyz-ar-title><?= h($spatialArTitle) ?></strong>
                            <p class="panel-copy" data-xyz-ar-status><?= h($spatialArStatus) ?></p>
                            <div class="xyz-ar-mode-switch" aria-label="Mode de modulation en réalité augmentée">
                                <button type="button" class="ghost-link xyz-ar-mode-button" data-xyz-ar-mode-button="anchor" aria-pressed="true">Ancrer</button>
                                <button type="button" class="ghost-link xyz-ar-mode-button" data-xyz-ar-mode-button="translate" aria-pressed="false">Traduire</button>
                                <button type="button" class="ghost-link xyz-ar-mode-button" data-xyz-ar-mode-button="loop" aria-pressed="false">Boucler</button>
                                <button type="button" class="ghost-link xyz-ar-mode-button" data-xyz-ar-mode-button="weave" aria-pressed="false">Tresser</button>
                            </div>
                            <div class="xyz-ar-layer-grid" aria-label="Poids des trois couches">
                                <article class="xyz-ar-layer xyz-ar-layer--real" data-xyz-ar-layer="real">
                                    <div class="xyz-ar-layer__head">
                                        <span>réalité</span>
                                        <strong data-xyz-ar-real-value>34%</strong>
                                    </div>
                                    <div class="xyz-ar-layer__meter" aria-hidden="true"><span data-xyz-ar-real-meter></span></div>
                                    <p data-xyz-ar-real-copy>Plans, bords, souffle, lumière, obstacles: ce qui ancre le monde avant l inscription.</p>
                                </article>
                                <article class="xyz-ar-layer xyz-ar-layer--plasma" data-xyz-ar-layer="plasma">
                                    <div class="xyz-ar-layer__head">
                                        <span>plasma</span>
                                        <strong data-xyz-ar-plasma-value>33%</strong>
                                    </div>
                                    <div class="xyz-ar-layer__meter" aria-hidden="true"><span data-xyz-ar-plasma-meter></span></div>
                                    <p data-xyz-ar-plasma-copy>Flux, mémoire, météo, voix, signes et calcul: la couche qui traduit sans éteindre.</p>
                                </article>
                                <article class="xyz-ar-layer xyz-ar-layer--torus" data-xyz-ar-layer="torus">
                                    <div class="xyz-ar-layer__head">
                                        <span>tore</span>
                                        <strong data-xyz-ar-torus-value>33%</strong>
                                    </div>
                                    <div class="xyz-ar-layer__meter" aria-hidden="true"><span data-xyz-ar-torus-meter></span></div>
                                    <p data-xyz-ar-torus-copy>Seuils, routes, prises, zones et dérive: la peau qui boucle l espace en interface.</p>
                                </article>
                            </div>
                            <p class="xyz-ar-directive" data-xyz-ar-directive><?= h($spatialArDirective) ?></p>
                            <div class="xyz-ar-pilot" data-xyz-ar-pilot>
                                <p class="xyz-ar-pilot__title" data-xyz-ar-pilot-title><?= h($spatialArPilotTitle) ?></p>
                                <p class="xyz-ar-pilot__copy" data-xyz-ar-pilot-copy>Commence par la carte pour tenir les plans, puis repasse par 0wlslw0 si tu dois réorienter la lecture située.</p>
                                <div class="xyz-surface-route-links xyz-surface-route-links--ar" aria-label="Routes conseillées en réalité augmentée">
                                    <a class="ghost-link" href="<?= h($mapHref) ?>" data-xyz-ar-primary-link>Ouvrir Map</a>
                                    <a class="ghost-link" href="<?= h($guideHref) ?>" data-xyz-ar-secondary-link>Passer par 0wlslw0</a>
                                </div>
                            </div>
                            <p class="xyz-ar-usage" data-xyz-ar-usage><?= h($spatialArUsage) ?></p>
                        </div>
                    </details>
                </article>
