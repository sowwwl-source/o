    <section class="xyz-surface-shell reveal" data-xyz-surface>
        <div class="xyz-surface-shell__veil" aria-hidden="true">
            <span class="xyz-surface-shell__ring xyz-surface-shell__ring--outer"></span>
            <span class="xyz-surface-shell__ring xyz-surface-shell__ring--inner"></span>
            <span class="xyz-surface-shell__pulse"></span>
        </div>

        <header class="xyz-surface-head">
            <p class="eyebrow xyz-surface-head__eyebrow"><strong><?= h($spatialSurfaceHostLabel) ?></strong> <span><?= h($spatialSurfaceEyebrow) ?></span></p>
            <h1 class="xyz-surface-head__title"><?= h($spatialSurfaceTitle) ?></h1>
            <p class="lead xyz-surface-head__lead"><?= h($spatialSurfaceLead) ?></p>

            <div class="xyz-surface-actions">
                <button type="button" class="pill-link xyz-camera-toggle" data-xyz-camera-start><?= h($spatialActivationLabel) ?></button>
                <button type="button" class="ghost-link xyz-camera-toggle" data-xyz-camera-demo aria-pressed="false">Terre &amp; Mine</button>
                <button type="button" class="ghost-link xyz-camera-toggle hidden" data-xyz-camera-stop><?= h($spatialReleaseLabel) ?></button>
                <a class="ghost-link" href="<?= h($authenticatedLand ? o_route_href('/land', ['u' => $activeLandSlug]) : $homeConnectionHref) ?>"><?= h($authenticatedLand ? 'Ouvrir ma terre' : 'Relier une terre') ?></a>
                <a class="ghost-link" href="<?= h($guideHref) ?>">Passer par 0wlslw0</a>
            </div>

            <div class="xyz-surface-meta" aria-label="Signature de la surface">
                <span class="badge badge-glass">λ <?= h((string) $activeLambda) ?> nm</span>
                <span class="badge badge-glass"><?= h($activeLandLabel) ?></span>
                <span class="badge badge-glass"><?= h((string) ($dailyStream['mood'] ?? 'calm')) ?></span>
                <span class="badge badge-glass">local d’abord</span>
            </div>

            <nav class="xyz-surface-wayfinder" aria-label="<?= h($spatialWayfinderAria) ?>">
                <div class="xyz-surface-wayfinder__intro">
                    <span class="summary-label"><?= h($spatialWayfinderIntroLabel) ?></span>
                    <strong>Choisir sans masquer le centre.</strong>
                    <a href="#xyz-panel-routes">voir les sorties</a>
                </div>
                <?php foreach ($surfaceWayfinderAxes as $axis): ?>
                    <a
                        class="xyz-surface-wayfinder__item xyz-surface-wayfinder__item--<?= h((string) $axis['axis']) ?>"
                        href="<?= h((string) $axis['href']) ?>"
                    >
                        <span class="summary-label"><?= h((string) $axis['kicker']) ?></span>
                        <strong><?= h((string) $axis['title']) ?></strong>
                        <span><?= h((string) $axis['copy']) ?></span>
                        <small><?= h((string) $axis['signal']) ?></small>
                    </a>
                <?php endforeach; ?>
            </nav>
        </header>

        <?= render_spatial_context_bar('surface', $host) ?>

        <?= render_continuity_dome($isSowwwlIo ? 'instrument' : 'surface', [
            'host' => $host,
            'land' => $authenticatedLand,
            'land_slug' => $activeLandSlug,
            'land_username' => $activeLandUsername,
            'unread_signal' => $unreadSignal,
        ]) ?>

        <?php if ($isSowwwlIo): ?>
        <section class="panel reveal xyz-surface-foundation" aria-labelledby="xyz-surface-foundation-title">
            <div class="section-topline">
                <div>
                    <span class="summary-label">ordre de lecture</span>
                    <h2 id="xyz-surface-foundation-title">Quatre prises tiennent sowwwl.io.</h2>
                    <p class="panel-copy">Le volume devient lisible quand on sait d abord où se pose l adresse, où sédimente la matière, où entre le réel et comment une présence répond.</p>
                </div>
                <span class="badge">terre / memoire / capteurs / presence</span>
            </div>
            <div class="land-focus-grid">
                <?php foreach ($spatialReadingOrderCards as $card): ?>
                <article class="land-focus-card">
                    <p class="land-card-kicker"><?= h((string) $card['kicker']) ?></p>
                    <h3><?= h((string) $card['title']) ?></h3>
                    <p class="land-card-copy"><?= h((string) $card['copy']) ?></p>
                    <p class="panel-copy"><?= h((string) $card['meta']) ?></p>
                    <div class="xyz-surface-route-links">
                        <?php foreach ($card['links'] as $link): ?>
                            <a class="ghost-link" href="<?= h((string) $link['href']) ?>"><?= h((string) $link['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <div class="xyz-surface-grid">
            <section class="panel reveal mapping-panel mapping-panel--genie xyz-surface-mapping" id="mapping" aria-labelledby="mapping-title" data-mapping-genie data-mapping-theme="real" data-xyz-archi-section data-xyz-archi-label="cartographie">
                <div class="mapping-panel__veil" aria-hidden="true">
                    <span class="mapping-panel__veil-orbit mapping-panel__veil-orbit--outer"></span>
                    <span class="mapping-panel__veil-orbit mapping-panel__veil-orbit--inner"></span>
                    <span class="mapping-panel__veil-glow"></span>
                </div>

                <div class="section-topline mapping-panel__topline">
                    <div>
                        <p class="eyebrow mapping-panel__eyebrow">
                            <strong><?= h($spatialSurfaceHostLabel) ?></strong>
                            <span><?= h($spatialMappingModeLabel) ?></span>
                        </p>
                        <h2 id="mapping-title"><?= h($spatialMappingTitle) ?></h2>
                        <p class="panel-copy mapping-panel__copy"><?= h($spatialMappingCopy) ?></p>
                    </div>
                    <span class="badge mapping-panel__badge"><?= h($spatialMappingBadge) ?></span>
                </div>

                <div class="mapping-panel__scene">
                    <div class="mapping-genie" role="list" aria-label="<?= h($isSowwwlIo ? 'Cartographie du volume' : 'Cartographie du tore') ?>" data-mapping-genie-list>
                        <button
                            type="button"
                            class="mapping-genie-card mapping-genie-card--real is-active"
                            data-mapping-card
                            data-mapping-tone="real"
                            data-mapping-label="Réalité"
                            data-mapping-whisper="Rue, souffle, corps, lumière : le monde avant sa traduction."
                            data-mapping-summary="La réalité contient les phénomènes, les gestes, les traces et les intensités qui n’ont pas encore trouvé leur forme navigable."
                            aria-expanded="true"
                            aria-pressed="true"
                            aria-describedby="mapping-keys"
                        >
                            <span class="mapping-genie-card__mist" aria-hidden="true"></span>
                            <span class="mapping-genie-card__sigil" aria-hidden="true">🌍</span>
                            <span class="mapping-genie-card__head">
                                <span class="summary-label">plan 01</span>
                                <strong>Réalité</strong>
                            </span>
                            <span class="mapping-genie-card__body">Présences, sons, météo, rencontres, lumière, usage. Tout ce qui touche avant d’être lu.</span>
                        </button>

                        <div class="mapping-genie-link" aria-hidden="true">
                            <span class="mapping-genie-link__line"></span>
                            <span class="mapping-genie-link__label">traduction</span>
                        </div>

                        <button
                            type="button"
                            class="mapping-genie-card mapping-genie-card--plasma"
                            data-mapping-card
                            data-mapping-tone="plasma"
                            data-mapping-label="Plasma"
                            data-mapping-whisper="Le flux garde, transforme, relie."
                            data-mapping-summary="Le plasma est la couche de calcul, de mémoire et de circulation. Il transporte le réel jusqu’à la surface sous forme de signes, de données et de rythme."
                            aria-expanded="false"
                            aria-pressed="false"
                            aria-describedby="mapping-keys"
                        >
                            <span class="mapping-genie-card__mist" aria-hidden="true"></span>
                            <span class="mapping-genie-card__sigil" aria-hidden="true">💧</span>
                            <span class="mapping-genie-card__head">
                                <span class="summary-label">plan 02</span>
                                <strong>Plasma</strong>
                            </span>
                            <span class="mapping-genie-card__body">Flux, mémoire, calcul, médiation. La couche fluide qui rend le réel transmissible sans l’éteindre.</span>
                        </button>

                        <div class="mapping-genie-link" aria-hidden="true">
                            <span class="mapping-genie-link__line"></span>
                            <span class="mapping-genie-link__label">déploiement</span>
                        </div>

                        <button
                            type="button"
                            class="mapping-genie-card mapping-genie-card--torus"
                            data-mapping-card
                            data-mapping-tone="torus"
                            data-mapping-label="Tore"
                            data-mapping-whisper="<?= h($spatialMappingTorusWhisper) ?>"
                            data-mapping-summary="<?= h($spatialMappingTorusSummary) ?>"
                            aria-expanded="false"
                            aria-pressed="false"
                            aria-describedby="mapping-keys"
                        >
                            <span class="mapping-genie-card__mist" aria-hidden="true"></span>
                            <span class="mapping-genie-card__sigil" aria-hidden="true">🌀</span>
                            <span class="mapping-genie-card__head">
                                <span class="summary-label">plan 03</span>
                                <strong>Tore</strong>
                            </span>
                            <span class="mapping-genie-card__body"><?= h($spatialTorusBodyCopy) ?></span>
                        </button>
                    </div>

                    <aside class="mapping-chorus xyz-surface-chorus" aria-live="polite">
                        <span class="summary-label">écho actif</span>
                        <strong class="mapping-chorus__title" data-mapping-active-label>Réalité</strong>
                        <p class="mapping-chorus__whisper" data-mapping-active-whisper>Rue, souffle, corps, lumière : le monde avant sa traduction.</p>
                        <p class="mapping-chorus__summary" data-mapping-active-summary>La réalité contient les phénomènes, les gestes, les traces et les intensités qui n’ont pas encore trouvé leur forme navigable.</p>
                        <p class="mapping-chorus__ra" data-mapping-ra-note><?= h($spatialMappingRaNote) ?></p>
                        <p class="mapping-chorus__hint" id="mapping-keys">Tab pour parcourir chaque plan. Entrée ou clic pour l activer. En mode casque web, les flèches, Home et End gardent aussi la dérive.</p>
                        <div class="mapping-chorus__meter" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </aside>
                </div>

                <p class="mapping-panel__reading"><strong>Lecture&nbsp;:</strong> <?= h($spatialMappingReading) ?></p>
            </section>

            <aside class="xyz-surface-aside reveal">
                <article class="xyz-surface-note xyz-surface-note--camera" data-xyz-camera-panel>
                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-rituel" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="<?= h($spatialSensorPanelLabel) ?>" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="1" open>
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label"><?= h($spatialSensorSummaryLabel) ?></span>
                            <strong><?= h($spatialSensorPanelTitle) ?></strong>
                            <span class="xyz-archi-panel__meta"><?= h($spatialSensorPanelMeta) ?></span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <strong data-xyz-camera-title><?= h($spatialCameraTitle) ?></strong>
                            <p class="panel-copy" data-xyz-camera-status><?= h($spatialCameraStatus) ?></p>
                            <div class="xyz-surface-sensor-grid" aria-label="<?= h($spatialSensorAriaLabel) ?>">
                                <p><span>orientation</span><strong data-xyz-sensor-orientation>en attente</strong></p>
                                <p><span>mouvement</span><strong data-xyz-sensor-motion>en attente</strong></p>
                                <p><span>lumière</span><strong data-xyz-sensor-light>en attente</strong></p>
                                <p><span>ambiance</span><strong data-xyz-sensor-audio>en attente</strong></p>
                                <p><span>caméra</span><strong data-xyz-sensor-camera>en attente</strong></p>
                                <p><span>veille</span><strong data-xyz-sensor-wake>en attente</strong></p>
                                <p><span>sceptre</span><strong data-xyz-sensor-sceptre>veille</strong></p>
                                <p><span>rituel</span><strong data-xyz-sensor-ritual>veille</strong></p>
                                <p><span>climat</span><strong data-xyz-sensor-climate>neutre</strong></p>
                                <p><span>écran</span><strong data-xyz-sensor-screen>veille</strong></p>
                            </div>
                            <div class="xyz-archi-callout xyz-archi-callout--sceptre">
                                <span class="summary-label">sceptre</span>
                                <strong data-xyz-sceptre-state><?= h($spatialSceptreState) ?></strong>
                                <p class="panel-copy" data-xyz-sceptre-copy><?= h($spatialSceptreCopy) ?></p>
                                <p class="panel-copy" data-xyz-sceptre-roster>Le premier sceptre attend encore sa levée.</p>
                                <div class="action-row">
                                    <a class="ghost-link" data-xyz-sceptre-console href="<?= h($sceptreViewHref) ?>">Ouvrir la console</a>
                                    <a class="ghost-link" data-xyz-sceptre-active href="<?= h($sceptreViewHref) ?>">Actif</a>
                                </div>
                            </div>
                        </div>
                    </details>

                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-device" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="<?= h($isSowwwlIo ? 'presence & appareil' : 'appareil') ?>" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="0">
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label"><?= h($spatialDeviceSummaryLabel) ?></span>
                            <strong><?= h($spatialDevicePanelTitle) ?></strong>
                            <span class="xyz-archi-panel__meta"><?= h($spatialDevicePanelMeta) ?></span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="device-bridge-panel" data-device-bridge-root data-device-context="xyz">
                                <span class="summary-label"><?= h($isSowwwlIo ? 'presence' : 'appareil') ?></span>
                                <div class="device-bridge-grid" aria-label="État téléphone">
                                    <p><span>silence</span><strong data-device-silence-status>web sonore</strong></p>
                                    <p><span>volume</span><strong data-device-volume-status>82%</strong></p>
                                    <p><span>haptique</span><strong data-device-haptics-status>sur demande</strong></p>
                                    <p><span>visibilité</span><strong data-device-visibility-status>visible</strong></p>
                                    <p><span>app</span><strong data-device-standalone-status>navigateur</strong></p>
                                    <p><span>natif</span><strong data-device-native-status>web seul</strong></p>
                                </div>
                                <div class="device-bridge-controls">
                                    <button type="button" class="ghost-link" data-device-silence-toggle>Silence web</button>
                                    <label class="device-bridge-range">
                                        <span>niveau O.</span>
                                        <input type="range" min="0" max="100" step="1" value="82" data-device-volume-input>
                                        <strong data-device-volume-readout>82%</strong>
                                    </label>
                                    <div class="device-bridge-actions">
                                        <button type="button" class="ghost-link" data-device-install hidden>Installer</button>
                                        <button type="button" class="ghost-link" data-device-share>Partager</button>
                                    </div>
                                </div>
                                <p class="panel-copy device-bridge-note" data-device-native-note><?= h($spatialDeviceNote) ?></p>
                            </div>
                        </div>
                    </details>

                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-instrument" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="<?= h($spatialWorldPanelLabel) ?>" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="1" open>
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label"><?= h($spatialWorldSummaryLabel) ?></span>
                            <strong><?= h($spatialWorldPanelTitle) ?></strong>
                            <span class="xyz-archi-panel__meta">Terre, Mine, visage, paysage</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="xyz-world-instrument" data-xyz-instrument-root>
                                <div class="xyz-world-instrument__head">
                                    <span class="summary-label"><?= h($spatialWorldHeadLabel) ?></span>
                                    <div class="xyz-world-instrument__camera-switch" role="group" aria-label="Perspective caméra">
                                        <button type="button" class="ghost-link xyz-world-instrument__camera-button" data-xyz-camera-facing-button="user" aria-pressed="false">visage</button>
                                        <button type="button" class="ghost-link xyz-world-instrument__camera-button" data-xyz-camera-facing-button="environment" aria-pressed="false">paysage</button>
                                    </div>
                                </div>
                                <div class="xyz-world-instrument__grid" aria-label="État du monde comme instrument">
                                    <p><span>vue</span><strong data-xyz-instrument-view>visage</strong></p>
                                    <p><span>focus</span><strong data-xyz-instrument-focus>souffle proche</strong></p>
                                    <p><span>corps</span><strong data-xyz-instrument-body>corps tenu</strong></p>
                                    <p><span>mains</span><strong data-xyz-instrument-touch>aucune prise</strong></p>
                                    <p><span>lumière</span><strong data-xyz-instrument-light>lueur mixte</strong></p>
                                </div>
                                <div class="xyz-world-instrument__stage" data-xyz-instrument-stage tabindex="0" aria-label="<?= h($spatialWorldStageAria) ?>">
                                    <span class="xyz-world-instrument__axis xyz-world-instrument__axis--x" aria-hidden="true"></span>
                                    <span class="xyz-world-instrument__axis xyz-world-instrument__axis--y" aria-hidden="true"></span>
                                    <span class="xyz-world-instrument__orb xyz-world-instrument__orb--terre" data-xyz-instrument-terre aria-hidden="true"></span>
                                    <span class="xyz-world-instrument__orb xyz-world-instrument__orb--mine" data-xyz-instrument-mine aria-hidden="true"></span>
                                    <p class="xyz-world-instrument__hint" data-xyz-instrument-stage-copy>Glisse une ou deux mains ici. Terre porte le fond, Mine taille la note. WASD et flèches fonctionnent aussi. Bascule en paysage pour faire jouer le dehors.</p>
                                </div>
                                <p class="panel-copy xyz-world-instrument__copy" data-xyz-world-copy><?= h($spatialWorldStaticCopy) ?></p>
                            </div>
                        </div>
                    </details>

                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-music" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="<?= h($spatialWorkshopLabel) ?>" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="1" open>
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label"><?= h($spatialMusicSummaryLabel) ?></span>
                            <strong><?= h($spatialWorkshopTitle) ?></strong>
                            <span class="xyz-archi-panel__meta"><?= h($spatialMusicPanelMeta) ?></span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="xyz-music-guide" data-xyz-music-guide-root>
                                <details class="xyz-archi-panel xyz-archi-panel--nested" id="xyz-panel-music-overview" data-xyz-archi-panel data-xyz-archi-group="music-guide" data-xyz-archi-default-open="1" open>
                                    <summary class="xyz-archi-panel__summary">
                                        <span class="summary-label">atelier</span>
                                        <strong>Lecture &amp; reglages</strong>
                                        <span class="xyz-archi-panel__meta">gamme, timbre, percu</span>
                                    </summary>
                                    <div class="xyz-archi-panel__content">
                                        <div class="xyz-music-guide__grid" aria-label="<?= h($spatialMusicGuideGridAria) ?>">
                                            <p><span>mode</span><strong data-xyz-music-mode>Mi éolien</strong></p>
                                            <p><span>note</span><strong data-xyz-music-note>Mi2</strong></p>
                                            <p><span>timbre</span><strong data-xyz-music-timbre>peau</strong></p>
                                            <p><span>percu</span><strong data-xyz-music-percussion>kick + hh</strong></p>
                                            <p><span>rythme</span><strong data-xyz-music-rhythm>drone stable</strong></p>
                                            <p><span>terre</span><strong data-xyz-hand-terre-state>porte le champ</strong></p>
                                            <p><span>mine</span><strong data-xyz-hand-mine-state>creuse la note</strong></p>
                                        </div>
                                        <div class="xyz-music-guide__controls" aria-label="<?= h($spatialMusicControlsAria) ?>">
                                            <label class="xyz-music-guide__control">
                                                <span>gamme</span>
                                                <select data-xyz-music-scale>
                                                    <option value="auto">auto</option>
                                                    <option value="aeolian">éolien</option>
                                                    <option value="dorian">dorien</option>
                                                    <option value="lydian">lydien</option>
                                                    <option value="pentatonic">pentatonique</option>
                                                </select>
                                            </label>
                                            <label class="xyz-music-guide__control">
                                                <span>timbre</span>
                                                <select data-xyz-music-instrument>
                                                    <option value="membrane">peau</option>
                                                    <option value="glass">verre</option>
                                                    <option value="reed">roseau</option>
                                                    <option value="bronze">bronze</option>
                                                </select>
                                            </label>
                                            <div class="xyz-music-guide__percussion">
                                                <span>percu</span>
                                                <div class="xyz-music-guide__toggle-group" role="group" aria-label="Percussions actives">
                                                    <button type="button" class="ghost-link xyz-music-guide__toggle" data-xyz-percussion-button="kick" aria-pressed="true">kick</button>
                                                    <button type="button" class="ghost-link xyz-music-guide__toggle" data-xyz-percussion-button="snare" aria-pressed="false">snare</button>
                                                    <button type="button" class="ghost-link xyz-music-guide__toggle" data-xyz-percussion-button="hihat" aria-pressed="true">hh</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="xyz-music-rituals" data-xyz-music-rituals>
                                            <div class="xyz-music-rituals__head">
                                                <div>
                                                    <span class="summary-label">rituels</span>
                                                    <strong data-xyz-music-ritual-state>arche prête</strong>
                                                </div>
                                                <button type="button" class="ghost-link xyz-music-rituals__arch" data-xyz-music-ritual-arch>créer arche A-D</button>
                                            </div>
                                            <p class="xyz-music-rituals__copy" data-xyz-music-ritual-copy>Charge un profil musical complet: pose Terre/Mine, gamme, timbre, tempo, FX, motif et scène associée.</p>
                                            <div class="xyz-music-rituals__grid" role="group" aria-label="Rituels musicaux">
                                                <button type="button" class="ghost-link xyz-music-ritual" data-xyz-music-ritual="aube" aria-pressed="false">
                                                    <span>A</span>
                                                    <strong>aube claire</strong>
                                                    <em>verriere · 84 bpm</em>
                                                </button>
                                                <button type="button" class="ghost-link xyz-music-ritual" data-xyz-music-ritual="seuil" aria-pressed="false">
                                                    <span>B</span>
                                                    <strong>seuil profond</strong>
                                                    <em>peau · 96 bpm</em>
                                                </button>
                                                <button type="button" class="ghost-link xyz-music-ritual" data-xyz-music-ritual="marche" aria-pressed="false">
                                                    <span>C</span>
                                                    <strong>marche plasma</strong>
                                                    <em>roseau · 112 bpm</em>
                                                </button>
                                                <button type="button" class="ghost-link xyz-music-ritual" data-xyz-music-ritual="braise" aria-pressed="false">
                                                    <span>D</span>
                                                    <strong>braise dense</strong>
                                                    <em>bronze · 126 bpm</em>
                                                </button>
                                            </div>
                                        </div>
                                        <p class="panel-copy" data-xyz-music-guide>Choisis une gamme, un timbre et la percussion utile. La lumière colore l accord, l inclinaison tient la note, Terre porte la base et Mine ouvre l accent.</p>
                                    </div>
                                </details>

                                <details class="xyz-archi-panel xyz-archi-panel--nested" id="xyz-panel-music-console" data-xyz-archi-panel data-xyz-archi-group="music-guide" data-xyz-archi-default-open="0">
                                    <summary class="xyz-archi-panel__summary">
                                        <span class="summary-label">console</span>
                                        <strong>Transport &amp; arrangement</strong>
                                        <span class="xyz-archi-panel__meta">BPM, FX, scenes, voyage, prises</span>
                                    </summary>
                                    <div class="xyz-archi-panel__content">
                                        <section class="xyz-music-desk" data-xyz-daw-root aria-label="<?= h($spatialMusicDeskAria) ?>">
                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-transport" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="1" open>
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">session</span>
                                                    <strong>Transport &amp; capture</strong>
                                                    <span class="xyz-archi-panel__meta">lecture, rec, stems, projet</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__transport">
                                                        <div class="xyz-music-desk__transport-head">
                                                            <span class="summary-label">session</span>
                                                            <strong data-xyz-daw-status>prête</strong>
                                                        </div>
                                                        <div class="xyz-music-desk__transport-actions" role="group" aria-label="Transport musical">
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-play>lecture locale</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-stop>stop</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button xyz-music-desk__transport-button--record" data-xyz-daw-record>rec audio</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button xyz-music-desk__transport-button--performance" data-xyz-daw-record-performance>rec perf</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-export-stems>export stems</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-export-project>export projet</button>
                                                        </div>
                                                        <div class="xyz-music-desk__transport-grid">
                                                            <label class="xyz-music-desk__field">
                                                                <span>bpm</span>
                                                                <input type="range" min="60" max="168" step="1" value="96" data-xyz-daw-bpm-input>
                                                                <strong data-xyz-daw-bpm-readout>96</strong>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>swing</span>
                                                                <input type="range" min="0" max="40" step="1" value="12" data-xyz-daw-swing-input>
                                                                <strong data-xyz-daw-swing-readout>12%</strong>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>humanize</span>
                                                                <input type="range" min="0" max="36" step="1" value="14" data-xyz-daw-humanize-input>
                                                                <strong data-xyz-daw-humanize-readout>14%</strong>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>microtiming</span>
                                                                <input type="range" min="0" max="24" step="1" value="9" data-xyz-daw-microtiming-input>
                                                                <strong data-xyz-daw-microtiming-readout>9 ms</strong>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>boucle</span>
                                                                <select data-xyz-daw-loop-select>
                                                                    <option value="2">2 mesures</option>
                                                                    <option value="4" selected>4 mesures</option>
                                                                    <option value="8">8 mesures</option>
                                                                    <option value="16">16 mesures</option>
                                                                </select>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>quantize</span>
                                                                <select data-xyz-daw-quantize-select>
                                                                    <option value="1/4">1/4</option>
                                                                    <option value="1/8" selected>1/8</option>
                                                                    <option value="1/16">1/16</option>
                                                                </select>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>count-in</span>
                                                                <select data-xyz-daw-countin-select>
                                                                    <option value="0">off</option>
                                                                    <option value="1" selected>1 mesure</option>
                                                                    <option value="2">2 mesures</option>
                                                                </select>
                                                            </label>
                                                            <p class="xyz-music-desk__clock" data-xyz-daw-clock>boucle 4 mesures · mesure 1 · temps 1</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>

                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-fx" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="0">
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">matiere</span>
                                                    <strong>Master &amp; FX</strong>
                                                    <span class="xyz-archi-panel__meta">espace, echo, matiere, air</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__fx" aria-label="Matière du master">
                                                        <div class="xyz-music-fx">
                                                            <div class="xyz-music-fx__head">
                                                                <span class="summary-label">matiere</span>
                                                                <strong data-xyz-daw-fx-state>nu proche</strong>
                                                            </div>
                                                            <p class="xyz-music-fx__copy" data-xyz-daw-fx-copy><?= h($spatialMusicFxCopy) ?></p>
                                                            <div class="xyz-music-fx__presets" role="group" aria-label="Presets de matière">
                                                                <button type="button" class="ghost-link xyz-music-fx__preset" data-xyz-daw-fx-preset="bare">nu</button>
                                                                <button type="button" class="ghost-link xyz-music-fx__preset" data-xyz-daw-fx-preset="mist">brume</button>
                                                                <button type="button" class="ghost-link xyz-music-fx__preset" data-xyz-daw-fx-preset="glass">verriere</button>
                                                                <button type="button" class="ghost-link xyz-music-fx__preset" data-xyz-daw-fx-preset="ember">braise</button>
                                                            </div>
                                                            <div class="xyz-music-fx__grid">
                                                                <label class="xyz-music-desk__field">
                                                                    <span>espace</span>
                                                                    <input type="range" min="0" max="100" step="1" value="18" data-xyz-daw-fx-space-input>
                                                                    <strong data-xyz-daw-fx-space-readout>18%</strong>
                                                                </label>
                                                                <label class="xyz-music-desk__field">
                                                                    <span>echo</span>
                                                                    <input type="range" min="0" max="100" step="1" value="12" data-xyz-daw-fx-echo-input>
                                                                    <strong data-xyz-daw-fx-echo-readout>12%</strong>
                                                                </label>
                                                                <label class="xyz-music-desk__field">
                                                                    <span>matiere</span>
                                                                    <input type="range" min="0" max="100" step="1" value="9" data-xyz-daw-fx-dirt-input>
                                                                    <strong data-xyz-daw-fx-dirt-readout>9%</strong>
                                                                </label>
                                                                <label class="xyz-music-desk__field">
                                                                    <span>air</span>
                                                                    <input type="range" min="0" max="100" step="1" value="54" data-xyz-daw-fx-air-input>
                                                                    <strong data-xyz-daw-fx-air-readout>54%</strong>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>

                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-memory" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="1" open>
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">memoire</span>
                                                    <strong>Scenes &amp; geste</strong>
                                                    <span class="xyz-archi-panel__meta">rappel, capture, boucle</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__memory" aria-label="Mémoire immersive">
                                                        <div class="xyz-music-memory">
                                                            <div class="xyz-music-memory__head">
                                                                <span class="summary-label">scenes</span>
                                                                <strong><?= h($spatialMusicMemoryTitle) ?></strong>
                                                            </div>
                                                            <p class="xyz-music-memory__copy">Mémorise une position Terre/Mine, le timbre, la gamme et le mix, puis relance-les comme des états jouables.</p>
                                                            <div class="xyz-music-memory__grid" role="group" aria-label="<?= h($spatialMusicScenesAria) ?>">
                                                                <article class="xyz-music-scene" data-xyz-daw-scene="scene-a">
                                                                    <div class="xyz-music-scene__head">
                                                                        <span class="summary-label">A</span>
                                                                        <strong>aube</strong>
                                                                    </div>
                                                                    <p class="xyz-music-scene__state" data-xyz-daw-scene-state="scene-a">vide</p>
                                                                    <div class="xyz-music-scene__actions">
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-trigger="scene-a">jouer</button>
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-store="scene-a">mémoriser</button>
                                                                    </div>
                                                                </article>
                                                                <article class="xyz-music-scene" data-xyz-daw-scene="scene-b">
                                                                    <div class="xyz-music-scene__head">
                                                                        <span class="summary-label">B</span>
                                                                        <strong>seuil</strong>
                                                                    </div>
                                                                    <p class="xyz-music-scene__state" data-xyz-daw-scene-state="scene-b">vide</p>
                                                                    <div class="xyz-music-scene__actions">
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-trigger="scene-b">jouer</button>
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-store="scene-b">mémoriser</button>
                                                                    </div>
                                                                </article>
                                                                <article class="xyz-music-scene" data-xyz-daw-scene="scene-c">
                                                                    <div class="xyz-music-scene__head">
                                                                        <span class="summary-label">C</span>
                                                                        <strong>marche</strong>
                                                                    </div>
                                                                    <p class="xyz-music-scene__state" data-xyz-daw-scene-state="scene-c">vide</p>
                                                                    <div class="xyz-music-scene__actions">
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-trigger="scene-c">jouer</button>
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-store="scene-c">mémoriser</button>
                                                                    </div>
                                                                </article>
                                                                <article class="xyz-music-scene" data-xyz-daw-scene="scene-d">
                                                                    <div class="xyz-music-scene__head">
                                                                        <span class="summary-label">D</span>
                                                                        <strong>sève</strong>
                                                                    </div>
                                                                    <p class="xyz-music-scene__state" data-xyz-daw-scene-state="scene-d">vide</p>
                                                                    <div class="xyz-music-scene__actions">
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-trigger="scene-d">jouer</button>
                                                                        <button type="button" class="ghost-link xyz-music-scene__button" data-xyz-daw-scene-store="scene-d">mémoriser</button>
                                                                    </div>
                                                                </article>
                                                            </div>
                                                        </div>
                                                        <div class="xyz-music-memory xyz-music-memory--gesture">
                                                            <div class="xyz-music-memory__head">
                                                                <span class="summary-label">geste</span>
                                                                <strong data-xyz-daw-gesture-state>aucune boucle</strong>
                                                            </div>
                                                            <p class="xyz-music-memory__copy" data-xyz-daw-gesture-copy>Capture un trajet Terre/Mine sur une boucle, puis rejoue-le comme une automation vivante de l instrument.</p>
                                                            <div class="xyz-music-memory__actions" role="group" aria-label="Boucle de geste">
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-gesture-record>capturer geste</button>
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-gesture-play>jouer boucle</button>
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-gesture-clear>effacer</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>

                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-arrangement" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="0">
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">voyage</span>
                                                    <strong>Scenes en chaine</strong>
                                                    <span class="xyz-archi-panel__meta">A B C D, mesures, boucle</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__arrangement" aria-label="Voyage de scènes">
                                                        <div class="xyz-music-arrangement">
                                                            <div class="xyz-music-arrangement__head">
                                                                <span class="summary-label">voyage</span>
                                                                <strong data-xyz-daw-arrangement-state>aucun voyage</strong>
                                                            </div>
                                                            <p class="xyz-music-arrangement__copy" data-xyz-daw-arrangement-copy><?= h($spatialWorkshopCopy) ?></p>
                                                            <div class="xyz-music-arrangement__actions" role="group" aria-label="Transport du voyage">
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-arrangement-play>jouer voyage</button>
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-arrangement-build>charger A B C D</button>
                                                                <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-arrangement-clear>effacer</button>
                                                                <label class="xyz-music-arrangement__loop">
                                                                    <input type="checkbox" data-xyz-daw-arrangement-loop checked>
                                                                    <span>boucle voyage</span>
                                                                </label>
                                                            </div>
                                                            <div class="xyz-music-arrangement__grid">
                                                                <?php for ($arrangementStep = 0; $arrangementStep < 6; $arrangementStep += 1): ?>
                                                                    <?php $arrangementStepLabel = str_pad((string) ($arrangementStep + 1), 2, '0', STR_PAD_LEFT); ?>
                                                                    <article class="xyz-music-arrangement-step" data-xyz-daw-arrangement-card="<?= $arrangementStep ?>">
                                                                        <div class="xyz-music-arrangement-step__head">
                                                                            <span class="summary-label"><?= h($arrangementStepLabel) ?></span>
                                                                            <strong data-xyz-daw-arrangement-step-state="<?= $arrangementStep ?>">libre</strong>
                                                                        </div>
                                                                        <label class="xyz-music-arrangement-step__field">
                                                                            <span>scene</span>
                                                                            <select data-xyz-daw-arrangement-scene="<?= $arrangementStep ?>">
                                                                                <option value="">libre</option>
                                                                                <option value="scene-a">A · aube</option>
                                                                                <option value="scene-b">B · seuil</option>
                                                                                <option value="scene-c">C · marche</option>
                                                                                <option value="scene-d">D · sève</option>
                                                                            </select>
                                                                        </label>
                                                                        <label class="xyz-music-arrangement-step__field">
                                                                            <span>mesures</span>
                                                                            <select data-xyz-daw-arrangement-bars="<?= $arrangementStep ?>">
                                                                                <option value="1">1</option>
                                                                                <option value="2" selected>2</option>
                                                                                <option value="4">4</option>
                                                                                <option value="8">8</option>
                                                                            </select>
                                                                        </label>
                                                                    </article>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>

                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-pattern" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="0">
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">motif</span>
                                                    <strong>Sequence rythmique</strong>
                                                    <span class="xyz-archi-panel__meta">kick, snare, hh, accent, proba</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__pattern" aria-label="Séquence rythmique">
                                                        <div class="xyz-music-pattern">
                                                            <div class="xyz-music-pattern__head">
                                                                <span class="summary-label">motif</span>
                                                                <strong data-xyz-daw-pattern-state>aucun motif</strong>
                                                            </div>
                                                            <p class="xyz-music-pattern__copy" data-xyz-daw-pattern-copy>Écris un pas de kick, snare et hh, puis laisse le swing et la boucle faire respirer la marche. Alt accentue, Ctrl change la proba, Shift ouvre le ratchet.</p>
                                                            <div class="xyz-music-pattern__presets" role="group" aria-label="Presets rythmiques">
                                                                <button type="button" class="ghost-link xyz-music-pattern__preset" data-xyz-daw-pattern-preset="pulse">pouls</button>
                                                                <button type="button" class="ghost-link xyz-music-pattern__preset" data-xyz-daw-pattern-preset="stride">marche</button>
                                                                <button type="button" class="ghost-link xyz-music-pattern__preset" data-xyz-daw-pattern-preset="drizzle">bruine</button>
                                                                <button type="button" class="ghost-link xyz-music-pattern__preset" data-xyz-daw-pattern-preset="storm">orage</button>
                                                                <button type="button" class="ghost-link xyz-music-pattern__preset" data-xyz-daw-pattern-preset="clear">effacer</button>
                                                            </div>
                                                            <div class="xyz-music-pattern__editor" aria-label="Edition du pas">
                                                                <div class="xyz-music-pattern__editor-head">
                                                                    <span class="summary-label">pas cible</span>
                                                                    <strong data-xyz-daw-pattern-editor-state>kick · pas 1</strong>
                                                                </div>
                                                                <p class="xyz-music-pattern__editor-copy" data-xyz-daw-pattern-editor-copy>Choisis un pas puis sculpte son accent, sa probabilité et son ratchet. Les raccourcis Alt / Ctrl / Shift-clique font la même chose directement dans la grille.</p>
                                                                <div class="xyz-music-pattern__editor-controls">
                                                                    <button type="button" class="ghost-link xyz-music-pattern__editor-toggle" data-xyz-daw-pattern-accent aria-pressed="false">accent</button>
                                                                    <label class="xyz-music-pattern__editor-field">
                                                                        <span>proba</span>
                                                                        <select data-xyz-daw-pattern-probability>
                                                                            <option value="1">100%</option>
                                                                            <option value="0.75">75%</option>
                                                                            <option value="0.5">50%</option>
                                                                            <option value="0.25">25%</option>
                                                                        </select>
                                                                    </label>
                                                                    <label class="xyz-music-pattern__editor-field">
                                                                        <span>ratchet</span>
                                                                        <select data-xyz-daw-pattern-ratchet>
                                                                            <option value="1">1x</option>
                                                                            <option value="2">2x</option>
                                                                            <option value="3">3x</option>
                                                                            <option value="4">4x</option>
                                                                        </select>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <?php $patternTracks = ['kick' => 'kick', 'snare' => 'snare', 'hihat' => 'hh']; ?>
                                                            <?php foreach ($patternTracks as $patternTrackKey => $patternTrackLabel): ?>
                                                                <article class="xyz-music-pattern__lane" data-xyz-daw-pattern-row="<?= h($patternTrackKey) ?>">
                                                                    <div class="xyz-music-pattern__lane-head">
                                                                        <span class="summary-label"><?= h($patternTrackLabel) ?></span>
                                                                        <strong><?= h($patternTrackKey === 'hihat' ? 'temps fins' : ($patternTrackKey === 'snare' ? 'contre-champ' : 'ancrage')) ?></strong>
                                                                    </div>
                                                                    <div class="xyz-music-pattern__row-shell">
                                                                        <div class="xyz-music-pattern__steps" role="group" aria-label="Pas <?= h($patternTrackLabel) ?>">
                                                                            <?php for ($patternStep = 0; $patternStep < 16; $patternStep += 1): ?>
                                                                                <?php $stepBeatLabel = ($patternStep % 4) === 0 ? (string) (((int) floor($patternStep / 4)) + 1) : '·'; ?>
                                                                                <button
                                                                                    type="button"
                                                                                    class="ghost-link xyz-music-pattern__step"
                                                                                    data-xyz-daw-pattern-step="<?= h($patternTrackKey) ?>:<?= $patternStep ?>"
                                                                                    data-xyz-daw-pattern-track="<?= h($patternTrackKey) ?>"
                                                                                    data-xyz-daw-pattern-index="<?= $patternStep ?>"
                                                                                    aria-pressed="false"
                                                                                    aria-label="<?= h($patternTrackLabel) ?> pas <?= $patternStep + 1 ?>"
                                                                                ><?= h($stepBeatLabel) ?></button>
                                                                            <?php endfor; ?>
                                                                        </div>
                                                                    </div>
                                                                </article>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>

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

                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-takes" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="1" open>
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">prises</span>
                                                    <strong>Rec audio, perf, stems</strong>
                                                    <span class="xyz-archi-panel__meta">ecoute, telechargement, replay</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__recorder">
                                                        <div class="xyz-music-desk__recorder-head">
                                                            <span class="summary-label">prises</span>
                                                            <strong data-xyz-daw-recording-state>aucune prise</strong>
                                                        </div>
                                                        <p class="panel-copy" data-xyz-daw-recording-copy>Le master peut etre enregistre directement dans l app, puis extrait comme audio ou relu plus tard dans la terre.</p>
                                                        <ul class="xyz-music-desk__takes" data-xyz-daw-takes>
                                                            <li class="xyz-music-desk__take xyz-music-desk__take--empty">Aucune prise pour l instant.</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </details>
                                        </section>
                                    </div>
                                </details>

                                <details class="xyz-archi-panel xyz-archi-panel--nested" id="xyz-panel-music-duet" data-xyz-archi-panel data-xyz-archi-group="music-guide" data-xyz-archi-default-open="0">
                                    <summary class="xyz-archi-panel__summary">
                                        <span class="summary-label">duo</span>
                                        <strong>Partition Terre &amp; Mine</strong>
                                        <span class="xyz-archi-panel__meta">portee, accent, souffle</span>
                                    </summary>
                                    <div class="xyz-archi-panel__content">
                                        <div class="xyz-music-guide__duet" aria-label="Partition Terre et Mine">
                                            <article class="xyz-music-guide__hand xyz-music-guide__hand--terre">
                                                <span class="summary-label">main terre</span>
                                                <strong data-xyz-hand-terre-title>Elle porte.</strong>
                                                <p data-xyz-hand-terre-copy>Elle stabilise le mode, ouvre ou ferme la lumière, puis garde le drone respirable.</p>
                                            </article>
                                            <article class="xyz-music-guide__hand xyz-music-guide__hand--mine">
                                                <span class="summary-label">main mine</span>
                                                <strong data-xyz-hand-mine-title>Elle creuse.</strong>
                                                <p data-xyz-hand-mine-copy>Elle tient la note, ouvre l accent et laisse la percussion respirer sans salir l accord.</p>
                                            </article>
                                        </div>
                                        <p class="xyz-music-guide__duet-state" data-xyz-duet-state>Terre porte le seuil, Mine y ouvre un trajet.</p>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </details>
                </article>

                <article class="xyz-surface-note xyz-surface-note--modulation" data-xyz-ar-modulation data-xyz-ar-mode="<?= h($isSowwwlIo ? 'anchor' : 'weave') ?>">
                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-ar" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="modulation RA" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="0">
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">05 RA</span>
                            <strong>Modulation situee</strong>
                            <span class="xyz-archi-panel__meta">reel, plasma, tore</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <strong data-xyz-ar-title><?= h($spatialArTitle) ?></strong>
                            <p class="panel-copy" data-xyz-ar-status><?= h($spatialArStatus) ?></p>
                            <div class="xyz-ar-mode-switch" aria-label="Mode de modulation en réalité augmentée">
                                <button type="button" class="ghost-link xyz-ar-mode-button" data-xyz-ar-mode-button="anchor" aria-pressed="true">Ancrer</button>
                                <button type="button" class="ghost-link xyz-ar-mode-button" data-xyz-ar-mode-button="translate" aria-pressed="false">Traduire</button>
                                <button type="button" class="ghost-link xyz-ar-mode-button" data-xyz-ar-mode-button="loop" aria-pressed="false">Boucler</button>
                                <button type="button" class="ghost-link xyz-ar-mode-button" data-xyz-ar-mode-button="weave" aria-pressed="false">Tresser</button>
                            </div>
                            <div class="xyz-ar-layer-grid" aria-label="Poids des trois couches">
                                <article class="xyz-ar-layer xyz-ar-layer--real" data-xyz-ar-layer="real">
                                    <div class="xyz-ar-layer__head">
                                        <span>réalité</span>
                                        <strong data-xyz-ar-real-value>34%</strong>
                                    </div>
                                    <div class="xyz-ar-layer__meter" aria-hidden="true"><span data-xyz-ar-real-meter></span></div>
                                    <p data-xyz-ar-real-copy>Plans, bords, souffle, lumière, obstacles: ce qui ancre le monde avant l inscription.</p>
                                </article>
                                <article class="xyz-ar-layer xyz-ar-layer--plasma" data-xyz-ar-layer="plasma">
                                    <div class="xyz-ar-layer__head">
                                        <span>plasma</span>
                                        <strong data-xyz-ar-plasma-value>33%</strong>
                                    </div>
                                    <div class="xyz-ar-layer__meter" aria-hidden="true"><span data-xyz-ar-plasma-meter></span></div>
                                    <p data-xyz-ar-plasma-copy>Flux, mémoire, météo, voix, signes et calcul: la couche qui traduit sans éteindre.</p>
                                </article>
                                <article class="xyz-ar-layer xyz-ar-layer--torus" data-xyz-ar-layer="torus">
                                    <div class="xyz-ar-layer__head">
                                        <span>tore</span>
                                        <strong data-xyz-ar-torus-value>33%</strong>
                                    </div>
                                    <div class="xyz-ar-layer__meter" aria-hidden="true"><span data-xyz-ar-torus-meter></span></div>
                                    <p data-xyz-ar-torus-copy>Seuils, routes, prises, zones et dérive: la peau qui boucle l espace en interface.</p>
                                </article>
                            </div>
                            <p class="xyz-ar-directive" data-xyz-ar-directive><?= h($spatialArDirective) ?></p>
                            <div class="xyz-ar-pilot" data-xyz-ar-pilot>
                                <p class="xyz-ar-pilot__title" data-xyz-ar-pilot-title><?= h($spatialArPilotTitle) ?></p>
                                <p class="xyz-ar-pilot__copy" data-xyz-ar-pilot-copy>Commence par la carte pour tenir les plans, puis repasse par 0wlslw0 si tu dois réorienter la lecture située.</p>
                                <div class="xyz-surface-route-links xyz-surface-route-links--ar" aria-label="Routes conseillées en réalité augmentée">
                                    <a class="ghost-link" href="<?= h($mapHref) ?>" data-xyz-ar-primary-link>Ouvrir Map</a>
                                    <a class="ghost-link" href="<?= h($guideHref) ?>" data-xyz-ar-secondary-link>Passer par 0wlslw0</a>
                                </div>
                            </div>
                            <p class="xyz-ar-usage" data-xyz-ar-usage><?= h($spatialArUsage) ?></p>
                        </div>
                    </details>
                </article>

                <article class="xyz-surface-note">
                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-gestures" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="gestes" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="0">
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">06 gestes</span>
                            <strong><?= h($spatialGestureTitle) ?></strong>
                            <span class="xyz-archi-panel__meta">prise, derive, orientation</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <p class="panel-copy"><?= h($spatialGestureCopy) ?></p>
                        </div>
                    </details>
                </article>

                <?php if ($isSowwwlIo): ?>
                <article class="xyz-surface-note xyz-surface-note--volume">
                    <details class="xyz-archi-panel xyz-archi-panel--surface xyz-archi-panel--volume" id="xyz-panel-volume" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="volume 3D" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="1" open>
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">07 volume</span>
                            <strong>Exploration 3D du dôme</strong>
                            <span class="xyz-archi-panel__meta">noeuds, profondeur, trajectoire</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="xyz-spatial-volume" data-io-volume-root tabindex="0" aria-label="Exploration 3D des noeuds sowwwl.io">
                                <div class="xyz-spatial-volume__head">
                                    <div>
                                        <span class="summary-label">sowwwl.io spatial</span>
                                        <strong>Un volume navigable, pas une page plate.</strong>
                                    </div>
                                    <span class="badge badge-glass" data-io-volume-layer>seuil</span>
                                </div>
                                <div class="xyz-spatial-volume__scene-wrap">
                                    <div class="xyz-spatial-volume__scene" data-io-volume-scene aria-label="Noeuds navigables du volume sowwwl.io">
                                        <span class="xyz-spatial-volume__ring xyz-spatial-volume__ring--front"></span>
                                        <span class="xyz-spatial-volume__ring xyz-spatial-volume__ring--middle"></span>
                                        <span class="xyz-spatial-volume__ring xyz-spatial-volume__ring--back"></span>
                                        <span class="xyz-spatial-volume__axis xyz-spatial-volume__axis--x"></span>
                                        <span class="xyz-spatial-volume__axis xyz-spatial-volume__axis--y"></span>
                                        <?php foreach ($ioSpatialVolumeNodes as $node): ?>
                                        <a
                                            class="xyz-spatial-volume__node xyz-spatial-volume__node--<?= h($node['tone']) ?>"
                                            href="<?= h($node['href']) ?>"
                                            data-io-volume-node="<?= h($node['key']) ?>"
                                            data-io-volume-label="<?= h($node['label']) ?>"
                                            data-io-volume-copy="<?= h($node['copy']) ?>"
                                            data-io-volume-layer="<?= h($node['layer']) ?>"
                                            style="--io-node-x: <?= h((string) $node['x']) ?>rem; --io-node-y: <?= h((string) $node['y']) ?>rem; --io-node-z: <?= h((string) $node['z']) ?>rem;"
                                        >
                                            <span class="xyz-spatial-volume__node-kicker"><?= h($node['layer']) ?></span>
                                            <strong><?= h($node['label']) ?></strong>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="xyz-spatial-volume__readout" aria-live="polite">
                                    <span class="summary-label">prise active</span>
                                    <strong data-io-volume-title>0wlslw0</strong>
                                    <p data-io-volume-copy>Le centre de clarification. On y revient pour nommer la prochaine porte avant de dériver.</p>
                                    <p class="xyz-spatial-volume__suggestion" data-io-volume-suggestion hidden></p>
                                </div>
                                <div class="xyz-spatial-volume__controls" aria-label="Contrôles du volume 3D">
                                    <button type="button" class="ghost-link" data-io-volume-rotate="-1">pivoter -</button>
                                    <button type="button" class="ghost-link" data-io-volume-rotate="1">pivoter +</button>
                                    <button type="button" class="ghost-link" data-io-volume-depth-control="1">avancer</button>
                                    <button type="button" class="ghost-link" data-io-volume-depth-control="-1">reculer</button>
                                    <button type="button" class="ghost-link" data-io-volume-reset>recentre</button>
                                </div>
                                <p class="xyz-spatial-volume__hint">Flèches gauche/droite pour changer de nœud, haut/bas pour incliner, PageUp/PageDown pour la profondeur. Entrée ouvre la prise active.</p>
                            </div>
                        </div>
                    </details>
                </article>

                <article class="xyz-surface-note xyz-surface-note--spatial">
                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-spatial" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="mode casque" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="0">
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">08 casque</span>
                            <strong><?= h($spatialModeTitle) ?></strong>
                            <span class="xyz-archi-panel__meta">projection, headset, routes</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <p class="panel-copy"><?= h($spatialModeCopy) ?></p>
                            <div class="xyz-surface-route-links xyz-surface-route-links--mode" aria-label="Basculer le mode spatial">
                                <a class="ghost-link" href="<?= h($spatialModeScreenHref) ?>"<?= $isSpatialHeadsetMode ? '' : ' aria-current="page"' ?>>Projection écran</a>
                                <a class="ghost-link" href="<?= h($spatialModeHeadsetHref) ?>"<?= $isSpatialHeadsetMode ? ' aria-current="page"' : '' ?>>Mode casque web</a>
                            </div>
                            <div class="xyz-spatial-duet-routes" aria-label="Routes Terre et Mine">
                                <article class="xyz-spatial-duet-routes__group" data-xyz-route-hand="terre">
                                    <span class="summary-label">main terre</span>
                                    <strong>Porte et oriente</strong>
                                    <p>Ouvrir le seuil, lire le terrain, garder une vue large avant d inciser.</p>
                                    <div class="xyz-surface-route-links xyz-surface-route-links--spatial">
                                        <a class="ghost-link" href="<?= h($guideHref) ?>">0wlslw0</a>
                                        <a class="ghost-link" href="<?= h($mapHref) ?>">Carte</a>
                                    </div>
                                </article>
                                <article class="xyz-spatial-duet-routes__group" data-xyz-route-hand="mine">
                                    <span class="summary-label">main mine</span>
                                    <strong>Incise et relance</strong>
                                    <p>Entrer dans une liaison, prendre le courant de face, faire vibrer le détail.</p>
                                    <div class="xyz-surface-route-links xyz-surface-route-links--spatial">
                                        <a class="ghost-link" href="<?= h($signalHref) ?>">Signal</a>
                                        <a class="ghost-link" href="<?= h($str3mHref) ?>">Str3m</a>
                                    </div>
                                </article>
                            </div>
                            <p class="panel-copy xyz-spatial-duet-routes__hint">La main dominante du moment éclaire la colonne correspondante. Terre tient l orientation. Mine pousse le passage.</p>
                        </div>
                    </details>
                </article>

                <?php if ($showSpatialNativeSimulator): ?>
                <article class="xyz-surface-note xyz-surface-note--spatial-sim">
                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-native-sim" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="injecteur natif" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="1" open>
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label">09 injecteur</span>
                            <strong>Injecteur natif local</strong>
                            <span class="xyz-archi-panel__meta">visionos, quest, lumière, ancres</span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="xyz-native-sim" data-spatial-native-sim>
                                <div class="xyz-native-sim__head">
                                    <div>
                                        <span class="summary-label">spatial-core</span>
                                        <strong>Simuler un client casque sans wrapper natif.</strong>
                                        <p class="xyz-native-sim__copy">Injecte regard, mains, lumière, ancrage et passthrough dans le shell <code>io</code> pour éprouver le tore, Terre &amp; Mine et la modulation RA avant visionOS ou Quest.</p>
                                    </div>
                                    <span class="badge badge-glass" data-spatial-native-sim-badge>web seul</span>
                                </div>

                                <div class="xyz-native-sim__actions" aria-label="Actions injecteur natif">
                                    <button type="button" class="ghost-link" data-spatial-native-sim-enable>activer</button>
                                    <button type="button" class="ghost-link" data-spatial-native-sim-disable>couper</button>
                                    <button type="button" class="ghost-link" data-spatial-native-sim-reset>recaler</button>
                                    <button type="button" class="copy-button" data-spatial-native-sim-share>copier URL</button>
                                </div>

                                <div class="xyz-native-sim__group">
                                    <span class="summary-label">presets runtime</span>
                                    <div class="xyz-native-sim__toggle-group" aria-label="Presets casque">
                                        <button type="button" class="ghost-link" data-spatial-native-sim-preset="visionos" aria-pressed="false">visionOS</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-preset="quest" aria-pressed="false">Quest</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-preset="browser" aria-pressed="false">browser</button>
                                    </div>
                                </div>

                                <div class="xyz-native-sim__group">
                                    <span class="summary-label">scénarios</span>
                                    <div class="xyz-native-sim__toggle-group" aria-label="Scénarios de simulation">
                                        <button type="button" class="ghost-link" data-spatial-native-sim-scenario="idle" aria-pressed="false">stable</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-scenario="lightSweep" aria-pressed="false">balayage</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-scenario="roomWalk" aria-pressed="false">marche</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-scenario="duetWeave" aria-pressed="false">duet</button>
                                    </div>
                                </div>

                                <div class="xyz-native-sim__grid">
                                    <label class="xyz-native-sim__control">
                                        <span>espace</span>
                                        <select data-spatial-native-sim-select="space">
                                            <option value="window">fenêtre</option>
                                            <option value="volume">volume</option>
                                            <option value="shared">shared</option>
                                            <option value="full">full</option>
                                        </select>
                                        <output data-spatial-native-sim-output="space">shared</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>passthrough</span>
                                        <select data-spatial-native-sim-select="passthrough">
                                            <option value="none">none</option>
                                            <option value="mixed">mixed</option>
                                            <option value="full">full</option>
                                            <option value="portal">portal</option>
                                            <option value="progressive">progressive</option>
                                        </select>
                                        <output data-spatial-native-sim-output="passthrough">mixed</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>lumière</span>
                                        <input type="range" min="0" max="100" step="1" value="68" data-spatial-native-sim-range="lightLevel">
                                        <output data-spatial-native-sim-output="lightLevel">68%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>contraste</span>
                                        <input type="range" min="0" max="100" step="1" value="24" data-spatial-native-sim-range="lightContrast">
                                        <output data-spatial-native-sim-output="lightContrast">24%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>lumière X</span>
                                        <input type="range" min="-100" max="100" step="1" value="16" data-spatial-native-sim-range="lightDirectionX">
                                        <output data-spatial-native-sim-output="lightDirectionX">+16%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>lumière Y</span>
                                        <input type="range" min="-100" max="100" step="1" value="-8" data-spatial-native-sim-range="lightDirectionY">
                                        <output data-spatial-native-sim-output="lightDirectionY">-8%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>tête</span>
                                        <input type="range" min="0" max="100" step="1" value="16" data-spatial-native-sim-range="headSpeed">
                                        <output data-spatial-native-sim-output="headSpeed">16%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>ancrage</span>
                                        <input type="range" min="0" max="100" step="1" value="84" data-spatial-native-sim-range="anchorStability">
                                        <output data-spatial-native-sim-output="anchorStability">84%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>ancres</span>
                                        <input type="range" min="0" max="12" step="1" value="5" data-spatial-native-sim-range="anchorsTracked">
                                        <output data-spatial-native-sim-output="anchorsTracked">5</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>plans</span>
                                        <input type="range" min="0" max="12" step="1" value="3" data-spatial-native-sim-range="planesTracked">
                                        <output data-spatial-native-sim-output="planesTracked">3</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>mains actives</span>
                                        <input type="range" min="0" max="2" step="1" value="2" data-spatial-native-sim-range="activeHands">
                                        <output data-spatial-native-sim-output="activeHands">2</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>prise gauche</span>
                                        <input type="range" min="0" max="100" step="1" value="22" data-spatial-native-sim-range="leftPinch">
                                        <output data-spatial-native-sim-output="leftPinch">22%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>prise droite</span>
                                        <input type="range" min="0" max="100" step="1" value="42" data-spatial-native-sim-range="rightPinch">
                                        <output data-spatial-native-sim-output="rightPinch">42%</output>
                                    </label>
                                </div>

                                <div class="xyz-native-sim__group">
                                    <span class="summary-label">flags</span>
                                    <div class="xyz-native-sim__toggle-group xyz-native-sim__toggle-group--flags" aria-label="Capacités simulées">
                                        <button type="button" class="ghost-link" data-spatial-native-sim-flag="gazeAvailable" aria-pressed="false">regard</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-flag="pinchAvailable" aria-pressed="false">pinch</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-flag="handTrackingAvailable" aria-pressed="false">mains</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-flag="roomTracked" aria-pressed="false">pièce</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-flag="meshTracked" aria-pressed="false">mesh</button>
                                        <button type="button" class="ghost-link" data-spatial-native-sim-flag="spatialAudio" aria-pressed="false">audio 3D</button>
                                    </div>
                                </div>

                                <div class="xyz-native-sim__readout" aria-live="polite">
                                    <p><span>runtime</span><strong data-spatial-native-sim-runtime>web seul</strong></p>
                                    <p><span>espace</span><strong data-spatial-native-sim-space>projection ecran</strong></p>
                                    <p><span>entrée</span><strong data-spatial-native-sim-input>pointeur</strong></p>
                                    <p><span>ancrage</span><strong data-spatial-native-sim-anchor>aucun</strong></p>
                                    <p><span>monde</span><strong data-spatial-native-sim-world>passthrough none</strong></p>
                                </div>

                                <div class="xyz-native-sim__trace">
                                    <div class="xyz-native-sim__trace-head">
                                        <span class="summary-label">trace locale</span>
                                        <strong data-spatial-native-sim-trace-title>prête à rejouer</strong>
                                    </div>
                                    <ol class="xyz-native-sim__trace-list" data-spatial-native-sim-traces>
                                        <li>
                                            <span class="summary-label">veille</span>
                                            <strong>aucune injection</strong>
                                            <span>Active un preset ou ouvre une URL de simulation pour garder une passe reproductible.</span>
                                        </li>
                                    </ol>
                                </div>

                                <p class="xyz-native-sim__note" data-spatial-native-sim-note>L injecteur est au repos. Active un preset pour simuler regard, mains, lumière et ancres dans le shell io.</p>
                            </div>
                        </div>
                    </details>
                </article>
                <?php endif; ?>
                <?php endif; ?>

                <article class="xyz-surface-note">
                    <details class="xyz-archi-panel xyz-archi-panel--surface" id="xyz-panel-routes" data-xyz-archi-panel data-xyz-archi-section data-xyz-archi-label="sorties" data-xyz-archi-group="surface-archi" data-xyz-archi-default-open="0">
                        <summary class="xyz-archi-panel__summary">
                            <span class="summary-label"><?= $isSowwwlIo ? ($showSpatialNativeSimulator ? '10 sorties' : '09 sorties') : '07 sorties' ?></span>
                            <strong><?= h($spatialRoutesTitle) ?></strong>
                            <span class="xyz-archi-panel__meta"><?= h($spatialRoutesMeta) ?></span>
                        </summary>
                        <div class="xyz-archi-panel__content">
                            <div class="xyz-surface-route-cluster">
                                <div class="xyz-surface-route-cluster__block">
                                    <span class="summary-label">trois axes</span>
                                    <div class="xyz-surface-route-links">
                                        <a class="ghost-link" href="<?= h($surfaceAzaHref) ?>">aZa</a>
                                        <a class="ghost-link" href="<?= h($surfaceStr3mHref) ?>">Str3m</a>
                                        <a class="ghost-link" href="<?= h($surfaceGuideHref) ?>">0wlslw0</a>
                                        <a class="ghost-link" href="<?= h($surfaceCounterpartHref) ?>"><?= h($surfaceCounterpartLabel) ?></a>
                                    </div>
                                    <p class="panel-copy"><?= h($surfaceRouteClusterCopy) ?></p>
                                </div>
                                <div class="xyz-archi-callout">
                                    <span class="summary-label">centre</span>
                                    <strong><?= h($isSowwwlIo ? 'vide spatial lisible' : 'membrane lisible') ?></strong>
                                    <p class="panel-copy"><?= h($isSowwwlIo ? 'La troisième colonne choisit sans recouvrir le volume central.' : 'La colonne de droite garde les choix sans fermer la membrane.') ?></p>
                                </div>
                            </div>
                        </div>
                    </details>
                </article>
            </aside>
        </div>
    </section>
