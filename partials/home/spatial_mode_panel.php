                <article class="xyz-surface-note xyz-surface-note--spatial">
                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-spatial" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="mode casque" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="0">
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">08 casque</span>
                            <strong><?= h($spatialModeTitle) ?></strong>
                            <span class="xyz-archi-panel__meta">projection, headset, routes</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <p class="panel-copy"><?= h($spatialModeCopy) ?></p>
                            <div class="xyz-surface-route-links xyz-surface-route-links--mode" aria-label="Basculer le mode spatial">
                                <a class="ghost-link" href="<?= h($spatialModeScreenHref) ?>"<?= $isSpatialHeadsetMode ? '' : ' aria-current="page"' ?>>Projection écran</a>
                                <a class="ghost-link" href="<?= h($spatialModeHeadsetHref) ?>"<?= $isSpatialHeadsetMode ? ' aria-current="page"' : '' ?>>Mode casque web</a>
                            </div>
                            <div class="xyz-spatial-duet-routes" aria-label="Routes Terre et Mine">
                                <article class="xyz-spatial-duet-routes__group" data-xyz-route-hand="terre">
                                    <span class="summary-label">main terre</span>
                                    <strong>Porte et oriente</strong>
                                    <p>Ouvrir le seuil, lire le terrain, garder une vue large avant d inciser.</p>
                                    <div class="xyz-surface-route-links xyz-surface-route-links--spatial">
                                        <a class="ghost-link" href="<?= h($guideHref) ?>">0wlslw0</a>
                                        <a class="ghost-link" href="<?= h($mapHref) ?>">Carte</a>
                                    </div>
                                </article>
                                <article class="xyz-spatial-duet-routes__group" data-xyz-route-hand="mine">
                                    <span class="summary-label">main mine</span>
                                    <strong>Incise et relance</strong>
                                    <p>Entrer dans une liaison, prendre le courant de face, faire vibrer le détail.</p>
                                    <div class="xyz-surface-route-links xyz-surface-route-links--spatial">
                                        <a class="ghost-link" href="<?= h($signalHref) ?>">Signal</a>
                                        <a class="ghost-link" href="<?= h($str3mHref) ?>">Str3m</a>
                                    </div>
                                </article>
                            </div>
                            <p class="panel-copy xyz-spatial-duet-routes__hint">La main dominante du moment éclaire la colonne correspondante. Terre tient l orientation. Mine pousse le passage.</p>
                        </div>
                    </details>
                </article>
