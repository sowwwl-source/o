                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-rituel" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="<?= h($spatialSensorPanelLabel) ?>" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="1" open>
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label"><?= h($spatialSensorSummaryLabel) ?></span>
                            <strong><?= h($spatialSensorPanelTitle) ?></strong>
                            <span class="xyz-archi-panel__meta"><?= h($spatialSensorPanelMeta) ?></span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <strong data-xyz-camera-title><?= h($spatialCameraTitle) ?></strong>
                            <p class="panel-copy" data-xyz-camera-status><?= h($spatialCameraStatus) ?></p>
                            <div class="xyz-surface-sensor-grid" aria-label="<?= h($spatialSensorAriaLabel) ?>">
                                <p><span>orientation</span><strong data-xyz-sensor-orientation>en attente</strong></p>
                                <p><span>mouvement</span><strong data-xyz-sensor-motion>en attente</strong></p>
                                <p><span>lumière</span><strong data-xyz-sensor-light>en attente</strong></p>
                                <p><span>ambiance</span><strong data-xyz-sensor-audio>en attente</strong></p>
                                <p><span>caméra</span><strong data-xyz-sensor-camera>en attente</strong></p>
                                <p><span>veille</span><strong data-xyz-sensor-wake>en attente</strong></p>
                                <p><span>sceptre</span><strong data-xyz-sensor-sceptre>veille</strong></p>
                                <p><span>rituel</span><strong data-xyz-sensor-ritual>veille</strong></p>
                                <p><span>climat</span><strong data-xyz-sensor-climate>neutre</strong></p>
                                <p><span>écran</span><strong data-xyz-sensor-screen>veille</strong></p>
                            </div>
                            <div class="xyz-archi-callout xyz-archi-callout--sceptre">
                                <span class="summary-label">sceptre</span>
                                <strong data-xyz-sceptre-state><?= h($spatialSceptreState) ?></strong>
                                <p class="panel-copy" data-xyz-sceptre-copy><?= h($spatialSceptreCopy) ?></p>
                                <p class="panel-copy" data-xyz-sceptre-roster>Le premier sceptre attend encore sa levée.</p>
                                <div class="action-row">
                                    <a class="ghost-link" data-xyz-sceptre-console href="<?= h($sceptreViewHref) ?>">Ouvrir la console</a>
                                    <a class="ghost-link" data-xyz-sceptre-active href="<?= h($sceptreViewHref) ?>">Actif</a>
                                </div>
                            </div>
                        </div>
                    </details>
