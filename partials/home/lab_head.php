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
