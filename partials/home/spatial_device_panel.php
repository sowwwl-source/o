                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-device" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="<?= h($isSowwwlIo ? 'presence & appareil' : 'appareil') ?>" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="0">
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label"><?= h($spatialDeviceSummaryLabel) ?></span>
                            <strong><?= h($spatialDevicePanelTitle) ?></strong>
                            <span class="xyz-archi-panel__meta"><?= h($spatialDevicePanelMeta) ?></span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="device-bridge-panel" data-device-bridge-root data-device-context="xyz">
                                <span class="summary-label"><?= h($isSowwwlIo ? 'presence' : 'appareil') ?></span>
                                <div class="device-bridge-grid" aria-label="État téléphone">
                                    <p><span>silence</span><strong data-device-silence-status>web sonore</strong></p>
                                    <p><span>volume</span><strong data-device-volume-status>82%</strong></p>
                                    <p><span>haptique</span><strong data-device-haptics-status>sur demande</strong></p>
                                    <p><span>visibilité</span><strong data-device-visibility-status>visible</strong></p>
                                    <p><span>app</span><strong data-device-standalone-status>navigateur</strong></p>
                                    <p><span>natif</span><strong data-device-native-status>web seul</strong></p>
                                </div>
                                <div class="device-bridge-controls">
                                    <button type="button" class="ghost-link" data-device-silence-toggle>Silence web</button>
                                    <label class="device-bridge-range">
                                        <span>niveau O.</span>
                                        <input type="range" min="0" max="100" step="1" value="82" data-device-volume-input>
                                        <strong data-device-volume-readout>82%</strong>
                                    </label>
                                    <div class="device-bridge-actions">
                                        <button type="button" class="ghost-link" data-device-install hidden>Installer</button>
                                        <button type="button" class="ghost-link" data-device-share>Partager</button>
                                    </div>
                                </div>
                                <p class="panel-copy device-bridge-note" data-device-native-note><?= h($spatialDeviceNote) ?></p>
                            </div>
                        </div>
                    </details>
