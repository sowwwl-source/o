            <section class="panel reveal mapping-panel mapping-panel--genie xyz-surface-mapping" id="mapping" aria-labelledby="mapping-title" data-mapping-genie data-mapping-theme="real" data-xyz-archi-section data-xyz-archi-label="cartographie">
                <div class="mapping-panel__veil" aria-hidden="true">
                    <span class="mapping-panel__veil-orbit mapping-panel__veil-orbit--outer"></span>
                    <span class="mapping-panel__veil-orbit mapping-panel__veil-orbit--inner"></span>
                    <span class="mapping-panel__veil-glow"></span>
                </div>

                <?= render_home_partial('mapping_topline', get_defined_vars()) ?>

                <div class="mapping-panel__scene">
                    <?= render_home_partial('mapping_genie_cards', get_defined_vars()) ?>
                    <?= render_home_partial('mapping_chorus', get_defined_vars()) ?>
                </div>

                <p class="mapping-panel__reading"><strong>Lecture&nbsp;:</strong> <?= h($spatialMappingReading) ?></p>
            </section>
