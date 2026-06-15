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
