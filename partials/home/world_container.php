<div class="world-container" aria-hidden="true">
    <?php if ($isSpatialSurface): ?>
    <div
        class="xyz-camera-layer"
        data-xyz-camera-root
        data-xyz-plasma-bridge="<?= h($membraneBridgeHref) ?>"
        data-xyz-plasma-land="<?= h($activeLandSlug) ?>"
        data-xyz-sceptre-feed="<?= h($sceptreFeedHref) ?>"
        data-xyz-sceptre-constellation-feed="<?= h($sceptreConstellationFeedHref) ?>"
        data-xyz-sceptre-device="<?= h($sceptreDeviceSlug) ?>"
    >
        <video
            class="xyz-camera-layer__video"
            data-xyz-camera-video
            autoplay
            muted
            playsinline
            aria-hidden="true"
        ></video>
        <div class="xyz-camera-layer__sceptre" data-xyz-sceptre-veil aria-hidden="true"></div>
        <div class="xyz-camera-layer__fallback" data-xyz-camera-fallback aria-hidden="true"></div>
    </div>
    <?php endif; ?>
    <canvas
        id="torus-ambient"
        class="main-torus"
        data-torus-cloud
        data-land-type="<?= h($activeLandProgram) ?>"
        data-land-label="<?= h($activeLandLabel) ?>"
        data-lambda="<?= h((string) $activeLambda) ?>"
        data-stream-mood="<?= h((string) ($dailyStream['mood'] ?? 'calm')) ?>"
        tabindex="0"
        role="img"
        aria-label="<?= h($torusAriaLabel) ?>"
    ></canvas>
</div>
