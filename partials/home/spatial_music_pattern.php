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
