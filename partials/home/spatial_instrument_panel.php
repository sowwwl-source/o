                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-instrument" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="<?= h($spatialWorldPanelLabel) ?>" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="1" open>
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label"><?= h($spatialWorldSummaryLabel) ?></span>
                            <strong><?= h($spatialWorldPanelTitle) ?></strong>
                            <span class="xyz-archi-panel__meta">Terre, Mine, visage, paysage</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="xyz-world-instrument" data-xyz-instrument-root>
                                <div class="xyz-world-instrument__head">
                                    <span class="summary-label"><?= h($spatialWorldHeadLabel) ?></span>
                                    <div class="xyz-world-instrument__camera-switch" role="group" aria-label="Perspective caméra">
                                        <button type="button" class="ghost-link xyz-world-instrument__camera-button" data-xyz-camera-facing-button="user" aria-pressed="false">visage</button>
                                        <button type="button" class="ghost-link xyz-world-instrument__camera-button" data-xyz-camera-facing-button="environment" aria-pressed="false">paysage</button>
                                    </div>
                                </div>
                                <div class="xyz-world-instrument__grid" aria-label="État du monde comme instrument">
                                    <p><span>vue</span><strong data-xyz-instrument-view>visage</strong></p>
                                    <p><span>focus</span><strong data-xyz-instrument-focus>souffle proche</strong></p>
                                    <p><span>corps</span><strong data-xyz-instrument-body>corps tenu</strong></p>
                                    <p><span>mains</span><strong data-xyz-instrument-touch>aucune prise</strong></p>
                                    <p><span>lumière</span><strong data-xyz-instrument-light>lueur mixte</strong></p>
                                </div>
                                <div class="xyz-world-instrument__stage" data-xyz-instrument-stage tabindex="0" aria-label="<?= h($spatialWorldStageAria) ?>">
                                    <span class="xyz-world-instrument__axis xyz-world-instrument__axis--x" aria-hidden="true"></span>
                                    <span class="xyz-world-instrument__axis xyz-world-instrument__axis--y" aria-hidden="true"></span>
                                    <span class="xyz-world-instrument__orb xyz-world-instrument__orb--terre" data-xyz-instrument-terre aria-hidden="true"></span>
                                    <span class="xyz-world-instrument__orb xyz-world-instrument__orb--mine" data-xyz-instrument-mine aria-hidden="true"></span>
                                    <p class="xyz-world-instrument__hint" data-xyz-instrument-stage-copy>Glisse une ou deux mains ici. Terre porte le fond, Mine taille la note. WASD et flèches fonctionnent aussi. Bascule en paysage pour faire jouer le dehors.</p>
                                </div>
                                <p class="panel-copy xyz-world-instrument__copy" data-xyz-world-copy><?= h($spatialWorldStaticCopy) ?></p>
                            </div>
                        </div>
                    </details>
