                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-fx" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="0">
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">matiere</span>
                                                    <strong>Master &amp; FX</strong>
                                                    <span class="xyz-archi-panel__meta">espace, echo, matiere, air</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__fx" aria-label="Matière du master">
                                                        <div class="xyz-music-fx">
                                                            <div class="xyz-music-fx__head">
                                                                <span class="summary-label">matiere</span>
                                                                <strong data-xyz-daw-fx-state>nu proche</strong>
                                                            </div>
                                                            <p class="xyz-music-fx__copy" data-xyz-daw-fx-copy><?= h($spatialMusicFxCopy) ?></p>
                                                            <div class="xyz-music-fx__presets" role="group" aria-label="Presets de matière">
                                                                <button type="button" class="ghost-link xyz-music-fx__preset" data-xyz-daw-fx-preset="bare">nu</button>
                                                                <button type="button" class="ghost-link xyz-music-fx__preset" data-xyz-daw-fx-preset="mist">brume</button>
                                                                <button type="button" class="ghost-link xyz-music-fx__preset" data-xyz-daw-fx-preset="glass">verriere</button>
                                                                <button type="button" class="ghost-link xyz-music-fx__preset" data-xyz-daw-fx-preset="ember">braise</button>
                                                            </div>
                                                            <div class="xyz-music-fx__grid">
                                                                <label class="xyz-music-desk__field">
                                                                    <span>espace</span>
                                                                    <input type="range" min="0" max="100" step="1" value="18" data-xyz-daw-fx-space-input>
                                                                    <strong data-xyz-daw-fx-space-readout>18%</strong>
                                                                </label>
                                                                <label class="xyz-music-desk__field">
                                                                    <span>echo</span>
                                                                    <input type="range" min="0" max="100" step="1" value="12" data-xyz-daw-fx-echo-input>
                                                                    <strong data-xyz-daw-fx-echo-readout>12%</strong>
                                                                </label>
                                                                <label class="xyz-music-desk__field">
                                                                    <span>matiere</span>
                                                                    <input type="range" min="0" max="100" step="1" value="9" data-xyz-daw-fx-dirt-input>
                                                                    <strong data-xyz-daw-fx-dirt-readout>9%</strong>
                                                                </label>
                                                                <label class="xyz-music-desk__field">
                                                                    <span>air</span>
                                                                    <input type="range" min="0" max="100" step="1" value="54" data-xyz-daw-fx-air-input>
                                                                    <strong data-xyz-daw-fx-air-readout>54%</strong>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>
