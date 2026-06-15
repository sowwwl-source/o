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
