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
