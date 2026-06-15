            <article class="home-live-card" aria-label="Courant public du jour">
                <div class="home-live-card__beam" aria-hidden="true"></div>
                <div class="home-live-card__top">
                    <span class="summary-label">courant du jour</span>
                    <span class="home-live-card__mood"><?= h($homeStreamMood) ?></span>
                </div>
                <h3><?= h($homeDailyTitle) ?></h3>
                <p><?= h($homeDailyCopy) ?></p>

                <div class="home-live-card__signals" aria-label="Matières disponibles">
                    <span>
                        <strong>audio</strong>
                        <?= h($dailyAudioPath !== '' ? $homeDailyAudioTitle : 'en veille') ?>
                    </span>
                    <span>
                        <strong>image</strong>
                        <?= h($dailyImagePath !== '' ? $homeDailyImageTitle : 'en veille') ?>
                    </span>
                    <span>
                        <strong>signal</strong>
                        <?= h($homeSignalState) ?>
                    </span>
                </div>

                <div class="home-live-card__actions">
                    <a class="pill-link" href="<?= h($str3mHref) ?>">Ouvrir Str3m</a>
                    <a class="ghost-link" href="<?= h($dailyAudioPath !== '' ? $dailyAudioPath : $azaHref) ?>"><?= $dailyAudioPath !== '' ? 'Écouter la source' : 'Préparer une matière' ?></a>
                </div>
            </article>
