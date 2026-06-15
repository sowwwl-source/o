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

        <header class="lab-console-head">
            <p class="eyebrow lab-console-head__eyebrow"><strong>lab.sowwwl.cloud</strong> <span>atelier du tore / 3ternet</span></p>
            <h1 class="lab-console-head__title">Console d’essai du tore.</h1>
            <p class="lead lab-console-head__lead">Le lab sert à éprouver la membrane, la présence, le pocket et les replays physiques avant promotion en prod. Il ne rejoue plus la home publique: il teste les passages qui demandent du réel.</p>

            <div class="lab-console-actions">
                <button type="button" class="pill-link" data-lab-activate>Activer les capteurs</button>
                <button type="button" class="ghost-link" data-lab-replay>Mode replay</button>
                <a class="ghost-link" href="<?= h($labPocketHref) ?>">Ouvrir pocket</a>
                <a class="ghost-link" href="<?= h($labQaIslandHref) ?>">Île QA</a>
            </div>

            <div class="lab-console-meta" aria-label="Signature du lab">
                <span class="badge badge-glass"><?= h($labSensorConfigured ? 'token capteur prêt' : 'token capteur absent') ?></span>
                <span class="badge badge-glass"><?= h($authenticatedLand ? '@' . $activeLandSlug : 'surface collective') ?></span>
                <span class="badge badge-glass">mission : surface + présence + pocket</span>
            </div>

            <p class="lab-console-head__status" data-lab-activation-status>Séquence cible : capteurs → tore → pocket → plasma → reprise différée.</p>
        </header>

        <section class="panel reveal lab-console-mission" aria-labelledby="lab-mission-title">
            <div class="lab-console-mission__intro">
                <span class="summary-label">mission du lab</span>
                <h2 id="lab-mission-title">Tester les passages situés avant de les rendre publics.</h2>
                <p class="panel-copy">Le lab garde quatre chantiers actifs : la surface, la présence, le pocket et l’instrumentation. Tout ce qui relève surtout de l’entrée publique, de l’éditorial ou de l’onboarding complet doit vivre ailleurs.</p>
            </div>
            <div class="lab-console-mission__grid" aria-label="Piliers du lab">
                <article class="lab-console-mission__pillar">
                    <span class="summary-label">01 · surface</span>
                    <strong>Membrane, tore, lumière, geste.</strong>
                    <p>Le téléphone devient la peau d’essai du tore, puis prépare les variantes XR et RA.</p>
                </article>
                <article class="lab-console-mission__pillar">
                    <span class="summary-label">02 · présence</span>
                    <strong>Veille, roaming, reprise.</strong>
                    <p>Le lab dit honnêtement si la terre répond, dort, dérive ou revient.</p>
                </article>
                <article class="lab-console-mission__pillar">
                    <span class="summary-label">03 · pocket</span>
                    <strong>Faux pocket maintenant, Pi ensuite.</strong>
                    <p>On teste le passage shore → pocket avant de le confier au hardware réel.</p>
                </article>
                <article class="lab-console-mission__pillar">
                    <span class="summary-label">04 · instrumentation</span>
                    <strong>API, plasma, replays, traces.</strong>
                    <p>Chaque essai doit laisser une trace lisible et une reprise rejouable.</p>
                </article>
            </div>
            <p class="lab-console-mission__outro">Les routes publiques restent disponibles via <a href="<?= h($guideHref) ?>">0wlslw0</a> et <a href="<?= h($str3mHref) ?>">Str3m</a>, mais elles deviennent ici des sorties secondaires, pas la mission principale du lab.</p>
        </section>

        <section class="panel reveal lab-console-protocol" aria-labelledby="lab-protocol-title">
            <div class="lab-console-protocol__head">
                <span class="summary-label">protocole court</span>
                <h2 id="lab-protocol-title">Une passe utile en quatre temps.</h2>
                <p class="panel-copy">Le lab doit rendre visible ce qui s’ouvre, ce qui dérive, ce qui laisse une trace et ce qui reprend.</p>
            </div>
            <ol class="lab-console-protocol__steps" data-lab-protocol-steps aria-label="Progression d’essai du lab">
                <li class="lab-console-protocol__step" data-lab-phase="sensor" data-lab-phase-state="pending">
                    <span class="lab-console-protocol__index">01</span>
                    <div class="lab-console-protocol__copy">
                        <strong>Surface</strong>
                        <span>Capteurs, lumière, voix, caméra.</span>
                    </div>
                    <small data-lab-phase-status="sensor">en attente</small>
                </li>
                <li class="lab-console-protocol__step" data-lab-phase="pocket" data-lab-phase-state="pending">
                    <span class="lab-console-protocol__index">02</span>
                    <div class="lab-console-protocol__copy">
                        <strong>Présence</strong>
                        <span>Pocket, veille, roaming, retour.</span>
                    </div>
                    <small data-lab-phase-status="pocket">veille</small>
                </li>
                <li class="lab-console-protocol__step" data-lab-phase="plasma" data-lab-phase-state="pending">
                    <span class="lab-console-protocol__index">03</span>
                    <div class="lab-console-protocol__copy">
                        <strong>Journal</strong>
                        <span>API, plasma, traces et capteurs.</span>
                    </div>
                    <small data-lab-phase-status="plasma">aucune trace</small>
                </li>
                <li class="lab-console-protocol__step" data-lab-phase="delivery" data-lab-phase-state="pending">
                    <span class="lab-console-protocol__index">04</span>
                    <div class="lab-console-protocol__copy">
                        <strong>Reprise</strong>
                        <span>Différé, retour, continuité.</span>
                    </div>
                    <small data-lab-phase-status="delivery">en attente</small>
                </li>
            </ol>
            <p class="lab-console-protocol__next" data-lab-next-step>Commence par activer les capteurs ou lance le replay pour ouvrir une première passe.</p>
        </section>

        <section class="panel reveal lab-console-presence" aria-labelledby="lab-presence-title">
            <div class="lab-console-presence__head">
                <div class="lab-console-presence__intro">
                    <span class="summary-label">présence</span>
                    <h2 id="lab-presence-title">Le lab dit où en est la terre.</h2>
                    <p class="panel-copy">Présence n’est pas un simple on/off. Le lab expose ici cinq états lisibles pour préparer le futur shore / pocket / relay.</p>
                </div>
                <div class="lab-console-presence__actions">
                    <button type="button" class="ghost-link" data-lab-presence-cycle>Cycle présence</button>
                    <button type="button" class="ghost-link" data-lab-presence-auto>Retour auto</button>
                </div>
            </div>

            <div class="lab-console-presence__current" data-lab-presence-state="asleep">
                <div class="lab-console-presence__current-head">
                    <span class="badge badge-glass" data-lab-presence-badge>endormi</span>
                    <span class="summary-label" data-lab-presence-mode>mode auto</span>
                </div>
                <strong class="lab-console-presence__current-title" data-lab-presence-title>Terre endormie, seuil encore lisible.</strong>
                <p class="panel-copy lab-console-presence__current-copy" data-lab-presence-copy>Le pocket dort pour l’instant. Le seuil reste public, mais la reprise devra attendre un retour de présence.</p>
            </div>

            <div class="lab-console-presence__steer" aria-label="Effets de la présence dans le lab">
                <article class="lab-console-presence__steer-card">
                    <span class="summary-label">prise tore</span>
                    <strong data-lab-presence-torus-title>Tore en veille large.</strong>
                    <p class="panel-copy" data-lab-presence-torus-copy>La peau reste diffuse. Le seuil demeure lisible, mais le tore n’appelle pas encore une prise forte.</p>
                </article>
                <article class="lab-console-presence__steer-card">
                    <span class="summary-label">routes conseillées</span>
                    <div class="lab-console-presence__route-links">
                        <a class="pill-link" href="<?= h($guideHref) ?>" data-lab-presence-route-primary>Passer par 0wlslw0</a>
                        <a class="ghost-link" href="<?= h($str3mHref) ?>" data-lab-presence-route-secondary>Ouvrir Str3m</a>
                    </div>
                    <p class="panel-copy" data-lab-presence-route-copy>Quand la terre dort, le lab garde un seuil public et une trace légère plutôt qu’une relance forcée.</p>
                </article>
                <article class="lab-console-presence__steer-card">
                    <span class="summary-label">guidage situé</span>
                    <strong data-lab-presence-voice-title>Prépare une reprise douce.</strong>
                    <p class="panel-copy" data-lab-presence-voice-copy>0wlslw0 doit garder une orientation honnête: seuil public, continuité lisible, pas de promesse de présence directe.</p>
                    <code class="lab-console-presence__prompt" data-lab-presence-voice-prompt>Guide-moi vers une reprise différée.</code>
                </article>
            </div>

            <div class="lab-console-presence__grid" aria-label="États de présence du lab">
                <article class="lab-console-presence__node" data-lab-presence-node="present">
                    <span class="summary-label">01 · présent</span>
                    <strong>Le pocket répond maintenant.</strong>
                    <p>Le passage direct est ouvert. Écrire et rejouer peuvent redevenir immédiats.</p>
                </article>
                <article class="lab-console-presence__node" data-lab-presence-node="near">
                    <span class="summary-label">02 · proche</span>
                    <strong>La terre peut être réveillée.</strong>
                    <p>Le corps n’est pas encore ouvert, mais un appareil proche peut relancer la relation.</p>
                </article>
                <article class="lab-console-presence__node" data-lab-presence-node="asleep">
                    <span class="summary-label">03 · endormi</span>
                    <strong>Le seuil reste honnête.</strong>
                    <p>Le pocket dort. Les traces publiques restent lisibles, la suite glisse vers le différé.</p>
                </article>
                <article class="lab-console-presence__node" data-lab-presence-node="roaming">
                    <span class="summary-label">04 · roaming</span>
                    <strong>La terre bouge encore.</strong>
                    <p>Le routeur capte une présence mobile ou instable. La reprise demande un peu de patience.</p>
                </article>
                <article class="lab-console-presence__node" data-lab-presence-node="split">
                    <span class="summary-label">05 · split</span>
                    <strong>Shore et pocket divergent.</strong>
                    <p>Le lab force ici le cas difficile: traces en désaccord, reprise suspendue, réconciliation à relire.</p>
                </article>
            </div>
        </section>

        <?= render_continuity_dome('lab', [
            'host' => $host,
            'land' => $authenticatedLand,
            'land_slug' => $activeLandSlug,
            'land_username' => $activeLandUsername,
            'trace_count' => count($labRecentPlasmaEvents),
            'island_status' => 'île QA prête',
        ]) ?>

        <div class="lab-console-grid">
            <article class="panel reveal lab-console-card lab-console-card--sensor" data-lab-card="sensor" data-lab-state="idle">
                <div class="lab-console-card__topline">
                    <div>
                        <span class="summary-label">01 · surface</span>
                        <h2 class="lab-console-card__title">Le téléphone devient membrane.</h2>
                    </div>
                    <span class="badge badge-glass" data-lab-sensor-badge>en veille</span>
                </div>
                <p class="panel-copy">Première porte du lab. Gyroscope, accéléromètre, lumière, micro, caméra et écran éveillé alimentent le tore. Quand une API manque, le test bascule honnêtement en fallback ou en replay.</p>
                <div class="lab-console-sensor-grid" aria-label="État des capteurs">
                    <p><span>orientation</span><strong data-lab-orientation-status>en attente</strong></p>
                    <p><span>mouvement</span><strong data-lab-motion-status>en attente</strong></p>
                    <p><span>lumière</span><strong data-lab-light-status>en attente</strong></p>
                    <p><span>micro</span><strong data-lab-audio-status>en attente</strong></p>
                    <p><span>caméra</span><strong data-lab-camera-status>en attente</strong></p>
                    <p><span>wake lock</span><strong data-lab-wake-status>en attente</strong></p>
                </div>
                <div class="lab-console-camera-preview-wrap">
                    <video class="lab-console-camera-preview" data-lab-camera-preview playsinline muted aria-hidden="true"></video>
                    <div class="lab-console-camera-preview-fallback" data-lab-camera-fallback>aperçu local</div>
                </div>
                <?php if ($pocketCameraAvailable): ?>
                <?= render_pocket_camera_panel([
                    'tag' => 'div',
                    'context' => 'lab',
                    'class' => 'pocket-camera-panel pocket-camera-panel--lab',
                    'title' => 'Œil pocket distant',
                    'lead' => 'Le Pi 3 caméra pousse déjà ses traces. Ici, on relit aussi son cadre via le Pi 5.',
                    'copy' => 'Le navigateur peut garder l aperçu local du téléphone en haut, puis ce second panneau lit la caméra matérielle séparée.',
                    'stream_url' => $pocketCameraStreamHref,
                    'snapshot_url' => $pocketCameraSnapshotHref,
                    'label' => $pocketCameraLabel,
                    'autostart' => true,
                ]) ?>
                <?php endif; ?>
                <div class="device-bridge-panel device-bridge-panel--lab" data-device-bridge-root data-device-context="lab">
                    <span class="summary-label">appareil</span>
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
                    <p class="panel-copy device-bridge-note" data-device-native-note>Le lab utilise déjà veille active, haptique, partage et mode app. S’il reçoit un pont natif, il affichera ici le vrai silence et le vrai volume du téléphone.</p>
                </div>
            </article>

            <article class="panel reveal lab-console-card lab-console-card--pocket" data-lab-card="pocket" data-lab-state="idle">
                <div class="lab-console-card__topline">
                    <div>
                        <span class="summary-label">02 · pocket</span>
                        <h2 class="lab-console-card__title">Fake pocket avant le Pi.</h2>
                    </div>
                    <span class="badge badge-glass">présence simulée</span>
                </div>
                <p class="panel-copy">Le pocket du lab est un corps temporaire: il dort, rôde, revient, puis sert de cible aux scénarios de reprise et de livraison différée avant le pocket matériel.</p>
                <strong class="lab-console-card__lead" data-lab-pocket-status>En veille douce. Le replay peut le faire dériver.</strong>
                <p class="panel-copy" data-lab-pocket-note>Ouvre la route pocket, ou laisse le mode replay alterner sommeil, roaming et retour.</p>
                <div class="action-row">
                    <a class="pill-link" href="<?= h($labPocketHref) ?>">Ouvrir pocket</a>
                    <a class="ghost-link" href="<?= h($labQaIslandHref) ?>">Île QA</a>
                </div>
            </article>

            <article class="panel reveal lab-console-card lab-console-card--api" data-lab-card="api" data-lab-state="idle">
                <div class="lab-console-card__topline">
                    <div>
                        <span class="summary-label">03 · instrumentation</span>
                        <h2 class="lab-console-card__title">Le fond parle encore même sans image.</h2>
                    </div>
                    <span class="badge badge-glass">healthz</span>
                </div>
                <p class="panel-copy">Le lab garde un point fixe: la santé de l’API, l’origine capteur et l’URL qui recevra les premiers signaux physiques. C’est le socle minimal avant toute lecture plus poétique.</p>
                <strong class="lab-console-card__lead" data-lab-api-status>Sondage en attente.</strong>
                <p class="panel-copy"><code><?= h($labApiHealthHref) ?></code></p>
                <p class="panel-copy"><code><?= h($labSensorEndpointHref) ?></code><?= $labSensorConfigured ? ' · prêt pour un Bearer token.' : ' · attend encore son token.' ?></p>
            </article>

            <article class="panel reveal lab-console-card lab-console-card--plasma" data-lab-card="plasma" data-lab-state="<?= h((string) ($labPlasmaWeather['tone'] ?? 'idle')) ?>">
                <div class="lab-console-card__topline">
                    <div>
                        <span class="summary-label">04 · plasma</span>
                        <h2 class="lab-console-card__title">Le journal du flux reste lisible.</h2>
                    </div>
                    <span class="badge badge-glass" data-lab-plasma-badge><?= h((string) ($labPlasmaWeather['badge'] ?? ($labRecentPlasmaEvents ? 'traces présentes' : 'aucune trace locale'))) ?></span>
                </div>
                <p class="panel-copy">Le browser n’envoie rien ici sans accord fort. En revanche, le lab relit son plasma local, les traces Pi et la simulation locale pour que chaque essai laisse un journal exploitable.</p>
                <strong class="lab-console-card__lead" data-lab-plasma-status><?= h((string) ($labPlasmaWeather['lead'] ?? 'Aucune trace plasma lue dans le runtime pour l’instant.')) ?></strong>
                <p class="panel-copy lab-console-plasma-copy" data-lab-plasma-weather-copy><?= h((string) ($labPlasmaWeather['detail'] ?? 'Le premier ping capteur apparaîtra ici dès qu’un événement traversera le pont plasma.')) ?></p>
                <ol class="lab-console-trace-list" data-lab-runtime-traces>
                    <?php if ($labRecentPlasmaEvents): ?>
                        <?php foreach ($labRecentPlasmaEvents as $event): ?>
                            <li>
                                <span class="summary-label"><?= h($event['event'] !== '' ? $event['event'] : 'signal') ?></span>
                                <strong><?= h($event['source'] !== '' ? $event['source'] : ($event['camera'] !== '' ? $event['camera'] : 'unknown')) ?></strong>
                                <span><?= h($event['message'] !== '' ? $event['message'] : ($event['timestamp'] !== '' ? $event['timestamp'] : 'trace sans message')) ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>
                            <span class="summary-label">veille</span>
                            <strong>runtime</strong>
                            <span>Le premier ping capteur apparaîtra ici dès qu’un événement traversera le pont plasma.</span>
                        </li>
                    <?php endif; ?>
                </ol>
                <ol class="lab-console-trace-list lab-console-trace-list--session" data-lab-session-traces hidden></ol>
            </article>

            <article class="panel reveal lab-console-card lab-console-card--delivery" data-lab-card="delivery" data-lab-state="idle">
                <div class="lab-console-card__topline">
                    <div>
                        <span class="summary-label">05 · présence</span>
                        <h2 class="lab-console-card__title">Préparer l’absence sans perdre le fil.</h2>
                    </div>
                    <span class="badge badge-glass">roaming</span>
                </div>
                <p class="panel-copy">La logique visée est simple: une terre s’endort, le signal reste en attente, puis le retour du pocket réouvre le passage. Le replay en montre déjà le rythme avant le hardware réel.</p>
                <strong class="lab-console-card__lead" data-lab-delivery-status>Réveil non rejoué. Le tore attend encore une première séquence.</strong>
                <ul class="lab-console-sequence" aria-label="Séquence d’expérimentation">
                    <li>1. activer les capteurs</li>
                    <li>2. bouger ou parler</li>
                    <li>3. laisser pocket dormir</li>
                    <li>4. relancer en replay</li>
                    <li>5. lire la reprise dans le plasma</li>
                </ul>
                <div class="action-row">
                    <a class="pill-link" href="<?= h($labQaIslandHref) ?>">Relire l’île QA</a>
                    <a class="ghost-link" href="<?= h($signalHref) ?>">Ouvrir Signal</a>
                </div>
            </article>
        </div>
    </section>
