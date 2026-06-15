                                <details class="xyz-archi-panel xyz-archi-panel--nested" id="xyz-panel-music-console" data-xyz-archi-panel data-xyz-archi-group="music-guide" data-xyz-archi-default-open="0">
                                    <summary class="xyz-archi-panel__summary">
                                        <span class="summary-label">console</span>
                                        <strong>Transport &amp; arrangement</strong>
                                        <span class="xyz-archi-panel__meta">BPM, FX, scenes, voyage, prises</span>
                                    </summary>
                                    <div class="xyz-archi-panel__content">
                                        <section class="xyz-music-desk" data-xyz-daw-root aria-label="<?= h($spatialMusicDeskAria) ?>">
                                            <?= render_home_partial('spatial_music_transport', get_defined_vars()) ?>
                                            <?= render_home_partial('spatial_music_fx', get_defined_vars()) ?>
                                            <?= render_home_partial('spatial_music_memory', get_defined_vars()) ?>
                                            <?= render_home_partial('spatial_music_arrangement', get_defined_vars()) ?>
                                            <?= render_home_partial('spatial_music_pattern', get_defined_vars()) ?>
                                            <?= render_home_partial('spatial_music_mixer', get_defined_vars()) ?>
                                            <?= render_home_partial('spatial_music_takes', get_defined_vars()) ?>
                                        </section>
                                    </div>
                                </details>
