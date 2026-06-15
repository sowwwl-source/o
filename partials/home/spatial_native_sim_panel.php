                <article class="xyz-surface-note xyz-surface-note--spatial-sim">
                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-native-sim" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="injecteur natif" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="1" open>
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">09 injecteur</span>
                            <strong>Injecteur natif local</strong>
                            <span class="xyz-archi-panel__meta">visionos, quest, lumière, ancres</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="xyz-native-sim" data-spatial-native-sim>
                                <?= render_home_partial('spatial_native_sim_head', get_defined_vars()) ?>
                                <?= render_home_partial('spatial_native_sim_actions', get_defined_vars()) ?>
                                <?= render_home_partial('spatial_native_sim_presets', get_defined_vars()) ?>
                                <?= render_home_partial('spatial_native_sim_controls', get_defined_vars()) ?>
                                <?= render_home_partial('spatial_native_sim_flags', get_defined_vars()) ?>
                                <?= render_home_partial('spatial_native_sim_status', get_defined_vars()) ?>
                            </div>
                        </div>
                    </details>
                </article>
