    <section
        class="lab-console-shell reveal"
        id="atelier"
        data-lab-console
        data-lab-api-url="<?= h($labApiHealthHref) ?>"
        data-lab-guide-url="<?= h($guideHref) ?>"
        data-lab-str3m-url="<?= h($str3mHref) ?>"
        data-lab-signal-url="<?= h($signalHref) ?>"
        data-lab-pocket-url="<?= h($labPocketHref) ?>"
        data-lab-qa-url="<?= h($labQaIslandHref) ?>"
        data-lab-sensor-endpoint="<?= h($labSensorEndpointHref) ?>"
        data-lab-plasma-feed="<?= h($labPublicPlasmaFeedHref) ?>"
        data-lab-sensor-configured="<?= $labSensorConfigured ? '1' : '0' ?>"
    >
        <div class="lab-console-shell__veil" aria-hidden="true">
            <span class="lab-console-shell__ring lab-console-shell__ring--outer"></span>
            <span class="lab-console-shell__ring lab-console-shell__ring--inner"></span>
            <span class="lab-console-shell__pulse"></span>
        </div>

        <?= render_home_partial('lab_head', get_defined_vars()) ?>
        <?= render_home_partial('lab_mission', get_defined_vars()) ?>
        <?= render_home_partial('lab_protocol', get_defined_vars()) ?>
        <?= render_home_partial('lab_presence', get_defined_vars()) ?>

        <?= render_continuity_dome('lab', [
            'host' => $host,
            'land' => $authenticatedLand,
            'land_slug' => $activeLandSlug,
            'land_username' => $activeLandUsername,
            'trace_count' => count($labRecentPlasmaEvents),
            'island_status' => 'île QA prête',
        ]) ?>

        <div class="lab-console-grid">
            <?= render_home_partial('lab_sensor_card', get_defined_vars()) ?>
            <?= render_home_partial('lab_pocket_card', get_defined_vars()) ?>
            <?= render_home_partial('lab_api_card', get_defined_vars()) ?>
            <?= render_home_partial('lab_plasma_card', get_defined_vars()) ?>
            <?= render_home_partial('lab_delivery_card', get_defined_vars()) ?>
        </div>
    </section>
