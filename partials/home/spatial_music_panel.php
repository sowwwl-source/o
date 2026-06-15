                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-music" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="<?= h($spatialWorkshopLabel) ?>" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="1" open>
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label"><?= h($spatialMusicSummaryLabel) ?></span>
                            <strong><?= h($spatialWorkshopTitle) ?></strong>
                            <span class="xyz-archi-panel__meta"><?= h($spatialMusicPanelMeta) ?></span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="xyz-music-guide" data-xyz-music-guide-root>
                                <details class="xyz-archi-panel xyz-archi-panel--nested" id="xyz-panel-music-overview" data-xyz-archi-panel data-xyz-archi-group="music-guide" data-xyz-archi-default-open="1" open>
                                    <summary class="xyz-archi-panel__summary">
                                        <span class="summary-label">atelier</span>
                                        <strong>Lecture &amp; reglages</strong>
                                        <span class="xyz-archi-panel__meta">gamme, timbre, percu</span>
                                    </summary>
                                    <div class="xyz-archi-panel__content">
                                        <div class="xyz-music-guide__grid" aria-label="<?= h($spatialMusicGuideGridAria) ?>">
                                            <p><span>mode</span><strong data-xyz-music-mode>Mi éolien</strong></p>
                                            <p><span>note</span><strong data-xyz-music-note>Mi2</strong></p>
                                            <p><span>timbre</span><strong data-xyz-music-timbre>peau</strong></p>
                                            <p><span>percu</span><strong data-xyz-music-percussion>kick + hh</strong></p>
                                            <p><span>rythme</span><strong data-xyz-music-rhythm>drone stable</strong></p>
                                            <p><span>terre</span><strong data-xyz-hand-terre-state>porte le champ</strong></p>
                                            <p><span>mine</span><strong data-xyz-hand-mine-state>creuse la note</strong></p>
                                        </div>
                                        <div class="xyz-music-guide__controls" aria-label="<?= h($spatialMusicControlsAria) ?>">
                                            <label class="xyz-music-guide__control">
                                                <span>gamme</span>
                                                <select data-xyz-music-scale>
                                                    <option value="auto">auto</option>
                                                    <option value="aeolian">éolien</option>
                                                    <option value="dorian">dorien</option>
                                                    <option value="lydian">lydien</option>
                                                    <option value="pentatonic">pentatonique</option>
                                                </select>
                                            </label>
                                            <label class="xyz-music-guide__control">
                                                <span>timbre</span>
                                                <select data-xyz-music-instrument>
                                                    <option value="membrane">peau</option>
                                                    <option value="glass">verre</option>
                                                    <option value="reed">roseau</option>
                                                    <option value="bronze">bronze</option>
                                                </select>
                                            </label>
                                            <div class="xyz-music-guide__percussion">
                                                <span>percu</span>
                                                <div class="xyz-music-guide__toggle-group" role="group" aria-label="Percussions actives">
                                                    <button type="button" class="ghost-link xyz-music-guide__toggle" data-xyz-percussion-button="kick" aria-pressed="true">kick</button>
                                                    <button type="button" class="ghost-link xyz-music-guide__toggle" data-xyz-percussion-button="snare" aria-pressed="false">snare</button>
                                                    <button type="button" class="ghost-link xyz-music-guide__toggle" data-xyz-percussion-button="hihat" aria-pressed="true">hh</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="xyz-music-rituals" data-xyz-music-rituals>
                                            <div class="xyz-music-rituals__head">
                                                <div>
                                                    <span class="summary-label">rituels</span>
                                                    <strong data-xyz-music-ritual-state>arche prête</strong>
                                                </div>
                                                <button type="button" class="ghost-link xyz-music-rituals__arch" data-xyz-music-ritual-arch>créer arche A-D</button>
                                            </div>
                                            <p class="xyz-music-rituals__copy" data-xyz-music-ritual-copy>Charge un profil musical complet: pose Terre/Mine, gamme, timbre, tempo, FX, motif et scène associée.</p>
                                            <div class="xyz-music-rituals__grid" role="group" aria-label="Rituels musicaux">
                                                <button type="button" class="ghost-link xyz-music-ritual" data-xyz-music-ritual="aube" aria-pressed="false">
                                                    <span>A</span>
                                                    <strong>aube claire</strong>
                                                    <em>verriere · 84 bpm</em>
                                                </button>
                                                <button type="button" class="ghost-link xyz-music-ritual" data-xyz-music-ritual="seuil" aria-pressed="false">
                                                    <span>B</span>
                                                    <strong>seuil profond</strong>
                                                    <em>peau · 96 bpm</em>
                                                </button>
                                                <button type="button" class="ghost-link xyz-music-ritual" data-xyz-music-ritual="marche" aria-pressed="false">
                                                    <span>C</span>
                                                    <strong>marche plasma</strong>
                                                    <em>roseau · 112 bpm</em>
                                                </button>
                                                <button type="button" class="ghost-link xyz-music-ritual" data-xyz-music-ritual="braise" aria-pressed="false">
                                                    <span>D</span>
                                                    <strong>braise dense</strong>
                                                    <em>bronze · 126 bpm</em>
                                                </button>
                                            </div>
                                        </div>
                                        <p class="panel-copy" data-xyz-music-guide>Choisis une gamme, un timbre et la percussion utile. La lumière colore l accord, l inclinaison tient la note, Terre porte la base et Mine ouvre l accent.</p>
                                    </div>
                                </details>

                                <details class="xyz-archi-panel xyz-archi-panel--nested" id="xyz-panel-music-console" data-xyz-archi-panel data-xyz-archi-group="music-guide" data-xyz-archi-default-open="0">
                                    <summary class="xyz-archi-panel__summary">
                                        <span class="summary-label">console</span>
                                        <strong>Transport &amp; arrangement</strong>
                                        <span class="xyz-archi-panel__meta">BPM, FX, scenes, voyage, prises</span>
                                    </summary>
                                    <div class="xyz-archi-panel__content">
                                        <section class="xyz-music-desk" data-xyz-daw-root aria-label="<?= h($spatialMusicDeskAria) ?>">
                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-transport" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="1" open>
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">session</span>
                                                    <strong>Transport &amp; capture</strong>
                                                    <span class="xyz-archi-panel__meta">lecture, rec, stems, projet</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__transport">
                                                        <div class="xyz-music-desk__transport-head">
                                                            <span class="summary-label">session</span>
                                                            <strong data-xyz-daw-status>prête</strong>
                                                        </div>
                                                        <div class="xyz-music-desk__transport-actions" role="group" aria-label="Transport musical">
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-play>lecture locale</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-stop>stop</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button xyz-music-desk__transport-button--record" data-xyz-daw-record>rec audio</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button xyz-music-desk__transport-button--performance" data-xyz-daw-record-performance>rec perf</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-export-stems>export stems</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-export-project>export projet</button>
                                                        </div>
                                                        <div class="xyz-music-desk__transport-grid">
                                                            <label class="xyz-music-desk__field">
                                                                <span>bpm</span>
                                                                <input type="range" min="60" max="168" step="1" value="96" data-xyz-daw-bpm-input>
                                                                <strong data-xyz-daw-bpm-readout>96</strong>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>swing</span>
                                                                <input type="range" min="0" max="40" step="1" value="12" data-xyz-daw-swing-input>
                                                                <strong data-xyz-daw-swing-readout>12%</strong>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>humanize</span>
                                                                <input type="range" min="0" max="36" step="1" value="14" data-xyz-daw-humanize-input>
                                                                <strong data-xyz-daw-humanize-readout>14%</strong>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>microtiming</span>
                                                                <input type="range" min="0" max="24" step="1" value="9" data-xyz-daw-microtiming-input>
                                                                <strong data-xyz-daw-microtiming-readout>9 ms</strong>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>boucle</span>
                                                                <select data-xyz-daw-loop-select>
                                                                    <option value="2">2 mesures</option>
                                                                    <option value="4" selected>4 mesures</option>
                                                                    <option value="8">8 mesures</option>
                                                                    <option value="16">16 mesures</option>
                                                                </select>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>quantize</span>
                                                                <select data-xyz-daw-quantize-select>
                                                                    <option value="1/4">1/4</option>
                                                                    <option value="1/8" selected>1/8</option>
                                                                    <option value="1/16">1/16</option>
                                                                </select>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>count-in</span>
                                                                <select data-xyz-daw-countin-select>
                                                                    <option value="0">off</option>
                                                                    <option value="1" selected>1 mesure</option>
                                                                    <option value="2">2 mesures</option>
                                                                </select>
                                                            </label>
                                                            <p class="xyz-music-desk__clock" data-xyz-daw-clock>boucle 4 mesures · mesure 1 · temps 1</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>

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

                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-memory" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="1" open>
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">memoire</span>
                                                    <strong>Scenes &amp; geste</strong>
                                                    <span class="xyz-archi-panel__meta">rappel, capture, boucle</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__memory" aria-label="Mémoire immersive">
                                                        <div class="xyz-music-memory">
                                                            <div class="xyz-music-memory__head">
                                                                <span class="summary-label">scenes</span>
                                                                <strong><?= h($spatialMusicMemoryTitle) ?></strong>
                                                            </div>
                                                            <p class="xyz-music-memory__copy">Mémorise une position Terre/Mine, le timbre, la gamme et le mix, puis relance-les comme des états jouables.</p>
                                                            <div class="xyz-music-memory__grid" role="group" aria-label="<?= h($spatialMusicScenesAria) ?>">
                                                                <article class="xyz-music-scene" data-xyz-daw-scene="scene-a">
                                                                    <div class="xyz-music-scene__head">
                                                                        <span class="summary-label">A</span>
                                                                        <strong>aube</strong>
                                                                    </div>
                                                                    <p class="xyz-music-scene__state" data-xyz-daw-scene-state="scene-a">vide</p>
                                                                    <div class="xyz-music-scene__actions">
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-trigger="scene-a">jouer</button>
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-store="scene-a">mémoriser</button>
                                                                    </div>
                                                                </article>
                                                                <article class="xyz-music-scene" data-xyz-daw-scene="scene-b">
                                                                    <div class="xyz-music-scene__head">
                                                                        <span class="summary-label">B</span>
                                                                        <strong>seuil</strong>
                                                                    </div>
                                                                    <p class="xyz-music-scene__state" data-xyz-daw-scene-state="scene-b">vide</p>
                                                                    <div class="xyz-music-scene__actions">
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-trigger="scene-b">jouer</button>
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-store="scene-b">mémoriser</button>
                                                                    </div>
                                                                </article>
                                                                <article class="xyz-music-scene" data-xyz-daw-scene="scene-c">
                                                                    <div class="xyz-music-scene__head">
                                                                        <span class="summary-label">C</span>
                                                                        <strong>marche</strong>
                                                                    </div>
                                                                    <p class="xyz-music-scene__state" data-xyz-daw-scene-state="scene-c">vide</p>
                                                                    <div class="xyz-music-scene__actions">
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-trigger="scene-c">jouer</button>
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-store="scene-c">mémoriser</button>
                                                                    </div>
                                                                </article>
                                                                <article class="xyz-music-scene" data-xyz-daw-scene="scene-d">
                                                                    <div class="xyz-music-scene__head">
                                                                        <span class="summary-label">D</span>
                                                                        <strong>sève</strong>
                                                                    </div>
                                                                    <p class="xyz-music-scene__state" data-xyz-daw-scene-state="scene-d">vide</p>
                                                                    <div class="xyz-music-scene__actions">
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-trigger="scene-d">jouer</button>
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-store="scene-d">mémoriser</button>
                                                                    </div>
                                                                </article>
                                                            </div>
                                                        </div>
                                                        <div class="xyz-music-memory xyz-music-memory--gesture">
                                                            <div class="xyz-music-memory__head">
                                                                <span class="summary-label">geste</span>
                                                                <strong data-xyz-daw-gesture-state>aucune boucle</strong>
                                                            </div>
                                                            <p class="xyz-music-memory__copy" data-xyz-daw-gesture-copy>Capture un trajet Terre/Mine sur une boucle, puis rejoue-le comme une automation vivante de l instrument.</p>
                                                            <div class="xyz-music-memory__actions" role="group" aria-label="Boucle de geste">
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-gesture-record>capturer geste</button>
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-gesture-play>jouer boucle</button>
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-gesture-clear>effacer</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>

                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-arrangement" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="0">
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">voyage</span>
                                                    <strong>Scenes en chaine</strong>
                                                    <span class="xyz-archi-panel__meta">A B C D, mesures, boucle</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__arrangement" aria-label="Voyage de scènes">
                                                        <div class="xyz-music-arrangement">
                                                            <div class="xyz-music-arrangement__head">
                                                                <span class="summary-label">voyage</span>
                                                                <strong data-xyz-daw-arrangement-state>aucun voyage</strong>
                                                            </div>
                                                            <p class="xyz-music-arrangement__copy" data-xyz-daw-arrangement-copy><?= h($spatialWorkshopCopy) ?></p>
                                                            <div class="xyz-music-arrangement__actions" role="group" aria-label="Transport du voyage">
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-arrangement-play>jouer voyage</button>
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-arrangement-build>charger A B C D</button>
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-arrangement-clear>effacer</button>
                                                                <label class="xyz-music-arrangement__loop">
                                                                    <input type="checkbox" data-xyz-daw-arrangement-loop checked>
                                                                    <span>boucle voyage</span>
                                                                </label>
                                                            </div>
                                                            <div class="xyz-music-arrangement__grid">
                                                                <?php for ($arrangementStep = 0; $arrangementStep < 6; $arrangementStep += 1): ?>
                                                                    <?php $arrangementStepLabel = str_pad((string) ($arrangementStep + 1), 2, '0', STR_PAD_LEFT); ?>
                                                                    <article class="xyz-music-arrangement-step" data-xyz-daw-arrangement-card="<?= $arrangementStep ?>">
                                                                        <div class="xyz-music-arrangement-step__head">
                                                                            <span class="summary-label"><?= h($arrangementStepLabel) ?></span>
                                                                            <strong data-xyz-daw-arrangement-step-state="<?= $arrangementStep ?>">libre</strong>
                                                                        </div>
                                                                        <label class="xyz-music-arrangement-step__field">
                                                                            <span>scene</span>
                                                                            <select data-xyz-daw-arrangement-scene="<?= $arrangementStep ?>">
                                                                                <option value="">libre</option>
                                                                                <option value="scene-a">A · aube</option>
                                                                                <option value="scene-b">B · seuil</option>
                                                                                <option value="scene-c">C · marche</option>
                                                                                <option value="scene-d">D · sève</option>
                                                                            </select>
                                                                        </label>
                                                                        <label class="xyz-music-arrangement-step__field">
                                                                            <span>mesures</span>
                                                                            <select data-xyz-daw-arrangement-bars="<?= $arrangementStep ?>">
                                                                                <option value="1">1</option>
                                                                                <option value="2" selected>2</option>
                                                                                <option value="4">4</option>
                                                                                <option value="8">8</option>
                                                                            </select>
                                                                        </label>
                                                                    </article>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>

                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-pattern" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="0">
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">motif</span>
                                                    <strong>Sequence rythmique</strong>
                                                    <span class="xyz-archi-panel__meta">kick, snare, hh, accent, proba</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__pattern" aria-label="Séquence rythmique">
                                                        <div class="xyz-music-pattern">
                                                            <div class="xyz-music-pattern__head">
                                                                <span class="summary-label">motif</span>
                                                                <strong data-xyz-daw-pattern-state>aucun motif</strong>
                                                            </div>
                                                            <p class="xyz-music-pattern__copy" data-xyz-daw-pattern-copy>Écris un pas de kick, snare et hh, puis laisse le swing et la boucle faire respirer la marche. Alt accentue, Ctrl change la proba, Shift ouvre le ratchet.</p>
                                                            <div class="xyz-music-pattern__presets" role="group" aria-label="Presets rythmiques">
                                                                <button type="button" class="ghost-link xyz-music-pattern__preset" data-xyz-daw-pattern-preset="pulse">pouls</button>
                                                                <button type="button" class="ghost-link xyz-music-pattern__preset" data-xyz-daw-pattern-preset="stride">marche</button>
                                                                <button type="button" class="ghost-link xyz-music-pattern__preset" data-xyz-daw-pattern-preset="drizzle">bruine</button>
                                                                <button type="button" class="ghost-link xyz-music-pattern__preset" data-xyz-daw-pattern-preset="storm">orage</button>
                                                                <button type="button" class="ghost-link xyz-music-pattern__preset" data-xyz-daw-pattern-preset="clear">effacer</button>
                                                            </div>
                                                            <div class="xyz-music-pattern__editor" aria-label="Edition du pas">
                                                                <div class="xyz-music-pattern__editor-head">
                                                                    <span class="summary-label">pas cible</span>
                                                                    <strong data-xyz-daw-pattern-editor-state>kick · pas 1</strong>
                                                                </div>
                                                                <p class="xyz-music-pattern__editor-copy" data-xyz-daw-pattern-editor-copy>Choisis un pas puis sculpte son accent, sa probabilité et son ratchet. Les raccourcis Alt / Ctrl / Shift-clique font la même chose directement dans la grille.</p>
                                                                <div class="xyz-music-pattern__editor-controls">
                                                                    <button type="button" class="ghost-link xyz-music-pattern__editor-toggle" data-xyz-daw-pattern-accent aria-pressed="false">accent</button>
                                                                    <label class="xyz-music-pattern__editor-field">
                                                                        <span>proba</span>
                                                                        <select data-xyz-daw-pattern-probability>
                                                                            <option value="1">100%</option>
                                                                            <option value="0.75">75%</option>
                                                                            <option value="0.5">50%</option>
                                                                            <option value="0.25">25%</option>
                                                                        </select>
                                                                    </label>
                                                                    <label class="xyz-music-pattern__editor-field">
                                                                        <span>ratchet</span>
                                                                        <select data-xyz-daw-pattern-ratchet>
                                                                            <option value="1">1x</option>
                                                                            <option value="2">2x</option>
                                                                            <option value="3">3x</option>
                                                                            <option value="4">4x</option>
                                                                        </select>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <?php $patternTracks = ['kick' => 'kick', 'snare' => 'snare', 'hihat' => 'hh']; ?>
                                                            <?php foreach ($patternTracks as $patternTrackKey => $patternTrackLabel): ?>
                                                                <article class="xyz-music-pattern__lane" data-xyz-daw-pattern-row="<?= h($patternTrackKey) ?>">
                                                                    <div class="xyz-music-pattern__lane-head">
                                                                        <span class="summary-label"><?= h($patternTrackLabel) ?></span>
                                                                        <strong><?= h($patternTrackKey === 'hihat' ? 'temps fins' : ($patternTrackKey === 'snare' ? 'contre-champ' : 'ancrage')) ?></strong>
                                                                    </div>
                                                                    <div class="xyz-music-pattern__row-shell">
                                                                        <div class="xyz-music-pattern__steps" role="group" aria-label="Pas <?= h($patternTrackLabel) ?>">
                                                                            <?php for ($patternStep = 0; $patternStep < 16; $patternStep += 1): ?>
                                                                                <?php $stepBeatLabel = ($patternStep % 4) === 0 ? (string) (((int) floor($patternStep / 4)) + 1) : '·'; ?>
                                                                                <button
                                                                                    type="button"
                                                                                    class="ghost-link xyz-music-pattern__step"
                                                                                    data-xyz-daw-pattern-step="<?= h($patternTrackKey) ?>:<?= $patternStep ?>"
                                                                                    data-xyz-daw-pattern-track="<?= h($patternTrackKey) ?>"
                                                                                    data-xyz-daw-pattern-index="<?= $patternStep ?>"
                                                                                    aria-pressed="false"
                                                                                    aria-label="<?= h($patternTrackLabel) ?> pas <?= $patternStep + 1 ?>"
                                                                                ><?= h($stepBeatLabel) ?></button>
                                                                            <?php endfor; ?>
                                                                        </div>
                                                                    </div>
                                                                </article>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>

                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-mixer" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="0">
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">mix</span>
                                                    <strong>Pistes &amp; master</strong>
                                                    <span class="xyz-archi-panel__meta">terre, mine, basse, percu</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__mixer" aria-label="<?= h($spatialMusicMixerAria) ?>">
                                                        <article class="xyz-music-track" data-xyz-track-card="terre">
                                                            <div class="xyz-music-track__head">
                                                                <span class="summary-label">terre</span>
                                                                <strong>fond</strong>
                                                            </div>
                                                            <p class="xyz-music-track__copy"><?= h($spatialTrackTerreCopy) ?></p>
                                                            <div class="xyz-music-track__actions">
                                                                <button type="button" class="ghost-link xyz-music-track__toggle" data-xyz-track-mute="terre" aria-pressed="false">mute</button>
                                                                <button type="button" class="ghost-link xyz-music-track__toggle" data-xyz-track-solo="terre" aria-pressed="false">solo</button>
                                                            </div>
                                                            <label class="xyz-music-track__level">
                                                                <span>niveau</span>
                                                                <input type="range" min="0" max="100" step="1" value="82" data-xyz-track-volume="terre">
                                                                <strong data-xyz-track-volume-readout="terre">82%</strong>
                                                            </label>
                                                        </article>
                                                        <article class="xyz-music-track" data-xyz-track-card="mine">
                                                            <div class="xyz-music-track__head">
                                                                <span class="summary-label">mine</span>
                                                                <strong>harmonie</strong>
                                                            </div>
                                                            <p class="xyz-music-track__copy">La ligne qui creuse, eclaire ou assombrit la phrase.</p>
                                                            <div class="xyz-music-track__actions">
                                                                <button type="button" class="ghost-link xyz-music-track__toggle" data-xyz-track-mute="mine" aria-pressed="false">mute</button>
                                                                <button type="button" class="ghost-link xyz-music-track__toggle" data-xyz-track-solo="mine" aria-pressed="false">solo</button>
                                                            </div>
                                                            <label class="xyz-music-track__level">
                                                                <span>niveau</span>
                                                                <input type="range" min="0" max="100" step="1" value="72" data-xyz-track-volume="mine">
                                                                <strong data-xyz-track-volume-readout="mine">72%</strong>
                                                            </label>
                                                        </article>
                                                        <article class="xyz-music-track" data-xyz-track-card="bass">
                                                            <div class="xyz-music-track__head">
                                                                <span class="summary-label">basse</span>
                                                                <strong>ancrage</strong>
                                                            </div>
                                                            <p class="xyz-music-track__copy">Le sous-sol, la tenue et la respiration grave du champ.</p>
                                                            <div class="xyz-music-track__actions">
                                                                <button type="button" class="ghost-link xyz-music-track__toggle" data-xyz-track-mute="bass" aria-pressed="false">mute</button>
                                                                <button type="button" class="ghost-link xyz-music-track__toggle" data-xyz-track-solo="bass" aria-pressed="false">solo</button>
                                                            </div>
                                                            <label class="xyz-music-track__level">
                                                                <span>niveau</span>
                                                                <input type="range" min="0" max="100" step="1" value="76" data-xyz-track-volume="bass">
                                                                <strong data-xyz-track-volume-readout="bass">76%</strong>
                                                            </label>
                                                        </article>
                                                        <article class="xyz-music-track" data-xyz-track-card="percu">
                                                            <div class="xyz-music-track__head">
                                                                <span class="summary-label">percu</span>
                                                                <strong>accents</strong>
                                                            </div>
                                                            <p class="xyz-music-track__copy">Kick, snare et hh dessinent le pas, la marche et la nervure.</p>
                                                            <div class="xyz-music-track__actions">
                                                                <button type="button" class="ghost-link xyz-music-track__toggle" data-xyz-track-mute="percu" aria-pressed="false">mute</button>
                                                                <button type="button" class="ghost-link xyz-music-track__toggle" data-xyz-track-solo="percu" aria-pressed="false">solo</button>
                                                            </div>
                                                            <label class="xyz-music-track__level">
                                                                <span>niveau</span>
                                                                <input type="range" min="0" max="100" step="1" value="78" data-xyz-track-volume="percu">
                                                                <strong data-xyz-track-volume-readout="percu">78%</strong>
                                                            </label>
                                                        </article>
                                                        <article class="xyz-music-track xyz-music-track--master" data-xyz-track-card="master">
                                                            <div class="xyz-music-track__head">
                                                                <span class="summary-label">master</span>
                                                                <strong>sortie</strong>
                                                            </div>
                                                            <p class="xyz-music-track__copy"><?= h($spatialMasterCopy) ?></p>
                                                            <label class="xyz-music-track__level">
                                                                <span>niveau</span>
                                                                <input type="range" min="0" max="100" step="1" value="98" data-xyz-daw-master-input>
                                                                <strong data-xyz-daw-master-readout>98%</strong>
                                                            </label>
                                                        </article>
                                                    </div>
                                                </div>
                                            </details>

                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-takes" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="1" open>
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">prises</span>
                                                    <strong>Rec audio, perf, stems</strong>
                                                    <span class="xyz-archi-panel__meta">ecoute, telechargement, replay</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__recorder">
                                                        <div class="xyz-music-desk__recorder-head">
                                                            <span class="summary-label">prises</span>
                                                            <strong data-xyz-daw-recording-state>aucune prise</strong>
                                                        </div>
                                                        <p class="panel-copy" data-xyz-daw-recording-copy>Le master peut etre enregistre directement dans l app, puis extrait comme audio ou relu plus tard dans la terre.</p>
                                                        <ul class="xyz-music-desk__takes" data-xyz-daw-takes>
                                                            <li class="xyz-music-desk__take xyz-music-desk__take--empty">Aucune prise pour l instant.</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </details>
                                        </section>
                                    </div>
                                </details>

                                <details class="xyz-archi-panel xyz-archi-panel--nested" id="xyz-panel-music-duet" data-xyz-archi-panel data-xyz-archi-group="music-guide" data-xyz-archi-default-open="0">
                                    <summary class="xyz-archi-panel__summary">
                                        <span class="summary-label">duo</span>
                                        <strong>Partition Terre &amp; Mine</strong>
                                        <span class="xyz-archi-panel__meta">portee, accent, souffle</span>
                                    </summary>
                                    <div class="xyz-archi-panel__content">
                                        <div class="xyz-music-guide__duet" aria-label="Partition Terre et Mine">
                                            <article class="xyz-music-guide__hand xyz-music-guide__hand--terre">
                                                <span class="summary-label">main terre</span>
                                                <strong data-xyz-hand-terre-title>Elle porte.</strong>
                                                <p data-xyz-hand-terre-copy>Elle stabilise le mode, ouvre ou ferme la lumière, puis garde le drone respirable.</p>
                                            </article>
                                            <article class="xyz-music-guide__hand xyz-music-guide__hand--mine">
                                                <span class="summary-label">main mine</span>
                                                <strong data-xyz-hand-mine-title>Elle creuse.</strong>
                                                <p data-xyz-hand-mine-copy>Elle tient la note, ouvre l accent et laisse la percussion respirer sans salir l accord.</p>
                                            </article>
                                        </div>
                                        <p class="xyz-music-guide__duet-state" data-xyz-duet-state>Terre porte le seuil, Mine y ouvre un trajet.</p>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </details>
