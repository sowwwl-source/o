                <article class="xyz-surface-note xyz-surface-note--volume">
                    <details class="xyz-archi-panel xyz-archi-panel--surface xyz-archi-panel--volume" id="xyz-panel-volume" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="volume 3D" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="1" open>
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">07 volume</span>
                            <strong>Exploration 3D du dôme</strong>
                            <span class="xyz-archi-panel__meta">noeuds, profondeur, trajectoire</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="xyz-spatial-volume" data-io-volume-root tabindex="0" aria-label="Exploration 3D des noeuds sowwwl.io">
                                <div class="xyz-spatial-volume__head">
                                    <div>
                                        <span class="summary-label">sowwwl.io spatial</span>
                                        <strong>Un volume navigable, pas une page plate.</strong>
                                    </div>
                                    <span class="badge badge-glass" data-io-volume-layer>seuil</span>
                                </div>
                                <div class="xyz-spatial-volume__scene-wrap">
                                    <div class="xyz-spatial-volume__scene" data-io-volume-scene aria-label="Noeuds navigables du volume sowwwl.io">
                                        <span class="xyz-spatial-volume__ring xyz-spatial-volume__ring--front"></span>
                                        <span class="xyz-spatial-volume__ring xyz-spatial-volume__ring--middle"></span>
                                        <span class="xyz-spatial-volume__ring xyz-spatial-volume__ring--back"></span>
                                        <span class="xyz-spatial-volume__axis xyz-spatial-volume__axis--x"></span>
                                        <span class="xyz-spatial-volume__axis xyz-spatial-volume__axis--y"></span>
                                        <?php foreach ($ioSpatialVolumeNodes as $node): ?>
                                        <a
                                            class="xyz-spatial-volume__node xyz-spatial-volume__node--<?= h($node['tone']) ?>"
                                            href="<?= h($node['href']) ?>"
                                            data-io-volume-node="<?= h($node['key']) ?>"
                                            data-io-volume-label="<?= h($node['label']) ?>"
                                            data-io-volume-copy="<?= h($node['copy']) ?>"
                                            data-io-volume-layer="<?= h($node['layer']) ?>"
                                            style="--io-node-x: <?= h((string) $node['x']) ?>rem; --io-node-y: <?= h((string) $node['y']) ?>rem; --io-node-z: <?= h((string) $node['z']) ?>rem;"
                                        >
                                            <span class="xyz-spatial-volume__node-kicker"><?= h($node['layer']) ?></span>
                                            <strong><?= h($node['label']) ?></strong>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="xyz-spatial-volume__readout" aria-live="polite">
                                    <span class="summary-label">prise active</span>
                                    <strong data-io-volume-title>0wlslw0</strong>
                                    <p data-io-volume-copy>Le centre de clarification. On y revient pour nommer la prochaine porte avant de dériver.</p>
                                    <p class="xyz-spatial-volume__suggestion" data-io-volume-suggestion hidden></p>
                                </div>
                                <div class="xyz-spatial-volume__controls" aria-label="Contrôles du volume 3D">
                                    <button type="button" class="ghost-link" data-io-volume-rotate="-1">pivoter -</button>
                                    <button type="button" class="ghost-link" data-io-volume-rotate="1">pivoter +</button>
                                    <button type="button" class="ghost-link" data-io-volume-depth-control="1">avancer</button>
                                    <button type="button" class="ghost-link" data-io-volume-depth-control="-1">reculer</button>
                                    <button type="button" class="ghost-link" data-io-volume-reset>recentre</button>
                                </div>
                                <p class="xyz-spatial-volume__hint">Flèches gauche/droite pour changer de nœud, haut/bas pour incliner, PageUp/PageDown pour la profondeur. Entrée ouvre la prise active.</p>
                            </div>
                        </div>
                    </details>
                </article>
