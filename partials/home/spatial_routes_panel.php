                <article class="xyz-surface-note">
                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-routes" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="sorties" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="0">
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label"><?= $isSowwwlIo ? ($showSpatialNativeSimulator ? '10 sorties' : '09 sorties') : '07 sorties' ?></span>
                            <strong><?= h($spatialRoutesTitle) ?></strong>
                            <span class="xyz-archi-panel__meta"><?= h($spatialRoutesMeta) ?></span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="xyz-surface-route-cluster">
                                <div class="xyz-surface-route-cluster__block">
                                    <span class="summary-label">trois axes</span>
                                    <div class="xyz-surface-route-links">
                                        <a class="ghost-link" href="<?= h($surfaceAzaHref) ?>">aZa</a>
                                        <a class="ghost-link" href="<?= h($surfaceStr3mHref) ?>">Str3m</a>
                                        <a class="ghost-link" href="<?= h($surfaceGuideHref) ?>">0wlslw0</a>
                                        <a class="ghost-link" href="<?= h($surfaceCounterpartHref) ?>"><?= h($surfaceCounterpartLabel) ?></a>
                                    </div>
                                    <p class="panel-copy"><?= h($surfaceRouteClusterCopy) ?></p>
                                </div>
                                <div class="xyz-archi-callout">
                                    <span class="summary-label">centre</span>
                                    <strong><?= h($isSowwwlIo ? 'vide spatial lisible' : 'membrane lisible') ?></strong>
                                    <p class="panel-copy"><?= h($isSowwwlIo ? 'La troisième colonne choisit sans recouvrir le volume central.' : 'La colonne de droite garde les choix sans fermer la membrane.') ?></p>
                                </div>
                            </div>
                        </div>
                    </details>
                </article>
