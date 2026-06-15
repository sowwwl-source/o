    <?= render_pocket_camera_panel([
        'id' => 'atelier',
        'context' => 'chamber',
        'class' => 'panel reveal pocket-camera-panel pocket-camera-panel--chamber',
        'title' => 'Chambre membrane / œil pocket',
        'lead' => 'Le Raspberry Pi 3 regarde déjà pour cette terre. Ici, le seuil relit son cadre sans détour.',
        'copy' => 'Le snapshot arrive d abord, puis le live MJPEG prend la main si le navigateur le tient bien. Le flux reste dans le cluster sowwwl, derrière le Pi 5.',
        'stream_url' => $pocketCameraStreamHref,
        'snapshot_url' => $pocketCameraSnapshotHref,
        'label' => $pocketCameraLabel,
        'autostart' => true,
    ]) ?>
