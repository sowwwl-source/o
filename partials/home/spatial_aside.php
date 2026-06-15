            <aside class="xyz-surface-aside reveal">
                <article class="xyz-surface-note xyz-surface-note--camera" data-xyz-camera-panel>
                    <?= render_home_partial('spatial_sensor_panel', get_defined_vars()) ?>
                    <?= render_home_partial('spatial_device_panel', get_defined_vars()) ?>
                    <?= render_home_partial('spatial_instrument_panel', get_defined_vars()) ?>
                    <?= render_home_partial('spatial_music_panel', get_defined_vars()) ?>
                </article>

                <?= render_home_partial('spatial_ar_panel', get_defined_vars()) ?>
                <?= render_home_partial('spatial_gestures_panel', get_defined_vars()) ?>

                <?php if ($isSowwwlIo): ?>
                    <?= render_home_partial('spatial_volume_panel', get_defined_vars()) ?>
                    <?= render_home_partial('spatial_mode_panel', get_defined_vars()) ?>

                    <?php if ($showSpatialNativeSimulator): ?>
                        <?= render_home_partial('spatial_native_sim_panel', get_defined_vars()) ?>
                    <?php endif; ?>
                <?php endif; ?>

                <?= render_home_partial('spatial_routes_panel', get_defined_vars()) ?>
            </aside>
