                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-music" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="<?= h($spatialWorkshopLabel) ?>" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="1" open>
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label"><?= h($spatialMusicSummaryLabel) ?></span>
                            <strong><?= h($spatialWorkshopTitle) ?></strong>
                            <span class="xyz-archi-panel__meta"><?= h($spatialMusicPanelMeta) ?></span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="xyz-music-guide" data-xyz-music-guide-root>
                                <?= render_home_partial('spatial_music_overview', get_defined_vars()) ?>
                                <?= render_home_partial('spatial_music_console', get_defined_vars()) ?>
                                <?= render_home_partial('spatial_music_duet', get_defined_vars()) ?>
                            </div>
                        </div>
                    </details>
