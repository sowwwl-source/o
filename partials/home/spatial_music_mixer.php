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
