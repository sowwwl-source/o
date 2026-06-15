                <article class="xyz-surface-note xyz-surface-note--spatial-sim">
                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-native-sim" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="injecteur natif" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="1" open>
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">09 injecteur</span>
                            <strong>Injecteur natif local</strong>
                            <span class="xyz-archi-panel__meta">visionos, quest, lumière, ancres</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="xyz-native-sim" data-spatial-native-sim>
                                <div class="xyz-native-sim__head">
                                    <div>
                                        <span class="summary-label">spatial-core</span>
                                        <strong>Simuler un client casque sans wrapper natif.</strong>
                                        <p class="xyz-native-sim__copy">Injecte regard, mains, lumière, ancrage et passthrough dans le shell <code>io</code> pour éprouver le tore, Terre &amp; Mine et la modulation RA avant visionOS ou Quest.</p>
                                    </div>
                                    <span class="badge badge-glass" data-spatial-native-sim-badge>web seul</span>
                                </div>

                                <div class="xyz-native-sim__actions" aria-label="Actions injecteur natif">
                                    <button type="button" class="ghost-link" data-spatial-native-sim-enable>activer</button>
                                    <button type="button" class="ghost-link" data-spatial-native-sim-disable>couper</button>
                                    <button type="button" class="ghost-link" data-spatial-native-sim-reset>recaler</button>
                                    <button type="button" class="copy-button" data-spatial-native-sim-share>copier URL</button>
                                </div>

                                <div class="xyz-native-sim__group">
                                    <span class="summary-label">presets runtime</span>
                                    <div class="xyz-native-sim__toggle-group" aria-label="Presets casque">
                                        <button type="button" class="ghost-link" data-spatial-native-sim-preset="visionos" aria-pressed="false">visionOS</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-preset="quest" aria-pressed="false">Quest</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-preset="browser" aria-pressed="false">browser</button>
                                    </div>
                                </div>

                                <div class="xyz-native-sim__group">
                                    <span class="summary-label">scénarios</span>
                                    <div class="xyz-native-sim__toggle-group" aria-label="Scénarios de simulation">
                                        <button type="button" class="ghost-link" data-spatial-native-sim-scenario="idle" aria-pressed="false">stable</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-scenario="lightSweep" aria-pressed="false">balayage</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-scenario="roomWalk" aria-pressed="false">marche</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-scenario="duetWeave" aria-pressed="false">duet</button>
                                    </div>
                                </div>

                                <div class="xyz-native-sim__grid">
                                    <label class="xyz-native-sim__control">
                                        <span>espace</span>
                                        <select data-spatial-native-sim-select="space">
                                            <option value="window">fenêtre</option>
                                            <option value="volume">volume</option>
                                            <option value="shared">shared</option>
                                            <option value="full">full</option>
                                        </select>
                                        <output data-spatial-native-sim-output="space">shared</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>passthrough</span>
                                        <select data-spatial-native-sim-select="passthrough">
                                            <option value="none">none</option>
                                            <option value="mixed">mixed</option>
                                            <option value="full">full</option>
                                            <option value="portal">portal</option>
                                            <option value="progressive">progressive</option>
                                        </select>
                                        <output data-spatial-native-sim-output="passthrough">mixed</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>lumière</span>
                                        <input type="range" min="0" max="100" step="1" value="68" data-spatial-native-sim-range="lightLevel">
                                        <output data-spatial-native-sim-output="lightLevel">68%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>contraste</span>
                                        <input type="range" min="0" max="100" step="1" value="24" data-spatial-native-sim-range="lightContrast">
                                        <output data-spatial-native-sim-output="lightContrast">24%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>lumière X</span>
                                        <input type="range" min="-100" max="100" step="1" value="16" data-spatial-native-sim-range="lightDirectionX">
                                        <output data-spatial-native-sim-output="lightDirectionX">+16%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>lumière Y</span>
                                        <input type="range" min="-100" max="100" step="1" value="-8" data-spatial-native-sim-range="lightDirectionY">
                                        <output data-spatial-native-sim-output="lightDirectionY">-8%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>tête</span>
                                        <input type="range" min="0" max="100" step="1" value="16" data-spatial-native-sim-range="headSpeed">
                                        <output data-spatial-native-sim-output="headSpeed">16%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>ancrage</span>
                                        <input type="range" min="0" max="100" step="1" value="84" data-spatial-native-sim-range="anchorStability">
                                        <output data-spatial-native-sim-output="anchorStability">84%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>ancres</span>
                                        <input type="range" min="0" max="12" step="1" value="5" data-spatial-native-sim-range="anchorsTracked">
                                        <output data-spatial-native-sim-output="anchorsTracked">5</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>plans</span>
                                        <input type="range" min="0" max="12" step="1" value="3" data-spatial-native-sim-range="planesTracked">
                                        <output data-spatial-native-sim-output="planesTracked">3</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>mains actives</span>
                                        <input type="range" min="0" max="2" step="1" value="2" data-spatial-native-sim-range="activeHands">
                                        <output data-spatial-native-sim-output="activeHands">2</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>prise gauche</span>
                                        <input type="range" min="0" max="100" step="1" value="22" data-spatial-native-sim-range="leftPinch">
                                        <output data-spatial-native-sim-output="leftPinch">22%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>prise droite</span>
                                        <input type="range" min="0" max="100" step="1" value="42" data-spatial-native-sim-range="rightPinch">
                                        <output data-spatial-native-sim-output="rightPinch">42%</output>
                                    </label>
                                </div>

                                <div class="xyz-native-sim__group">
                                    <span class="summary-label">flags</span>
                                    <div class="xyz-native-sim__toggle-group xyz-native-sim__toggle-group--flags" aria-label="Capacités simulées">
                                        <button type="button" class="ghost-link" data-spatial-native-sim-flag="gazeAvailable" aria-pressed="false">regard</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-flag="pinchAvailable" aria-pressed="false">pinch</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-flag="handTrackingAvailable" aria-pressed="false">mains</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-flag="roomTracked" aria-pressed="false">pièce</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-flag="meshTracked" aria-pressed="false">mesh</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-flag="spatialAudio" aria-pressed="false">audio 3D</button>
                                    </div>
                                </div>

                                <div class="xyz-native-sim__readout" aria-live="polite">
                                    <p><span>runtime</span><strong data-spatial-native-sim-runtime>web seul</strong></p>
                                    <p><span>espace</span><strong data-spatial-native-sim-space>projection ecran</strong></p>
                                    <p><span>entrée</span><strong data-spatial-native-sim-input>pointeur</strong></p>
                                    <p><span>ancrage</span><strong data-spatial-native-sim-anchor>aucun</strong></p>
                                    <p><span>monde</span><strong data-spatial-native-sim-world>passthrough none</strong></p>
                                </div>

                                <div class="xyz-native-sim__trace">
                                    <div class="xyz-native-sim__trace-head">
                                        <span class="summary-label">trace locale</span>
                                        <strong data-spatial-native-sim-trace-title>prête à rejouer</strong>
                                    </div>
                                    <ol class="xyz-native-sim__trace-list" data-spatial-native-sim-traces>
                                        <li>
                                            <span class="summary-label">veille</span>
                                            <strong>aucune injection</strong>
                                            <span>Active un preset ou ouvre une URL de simulation pour garder une passe reproductible.</span>
                                        </li>
                                    </ol>
                                </div>

                                <p class="xyz-native-sim__note" data-spatial-native-sim-note>L injecteur est au repos. Active un preset pour simuler regard, mains, lumière et ancres dans le shell io.</p>
                            </div>
                        </div>
                    </details>
                </article>
