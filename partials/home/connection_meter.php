<details
    class="connection-meter<?= $authenticatedLand ? ' is-linked' : ' is-public' ?><?= h($connectionNeedleClass) ?>"
    id="connexion"
    data-corner-dock
    data-corner-dock-side="left"
    data-corner-dock-priority="primary"
    data-corner-dock-default-open="<?= $connectionDockOpen ? '1' : '0' ?>"
    aria-labelledby="connection-meter-title"
    <?= $connectionDockOpen ? 'open' : '' ?>
>
    <summary class="connection-meter__toggle">
        <span class="corner-dock-toggle__kicker">Se relier</span>
        <strong><?= h($authenticatedLand ? $connectionStatusText : 'terre déjà posée ?') ?></strong>
        <span class="corner-dock-toggle__meta"><?= $authenticatedLand ? h('@' . $activeLandSlug) : 'ouvrir doucement' ?></span>
    </summary>

    <div class="connection-meter__dial" aria-hidden="true">
        <span class="connection-meter__arc"></span>
        <span class="connection-meter__tick connection-meter__tick--left"></span>
        <span class="connection-meter__tick connection-meter__tick--center"></span>
        <span class="connection-meter__tick connection-meter__tick--right"></span>
        <span class="connection-meter__needle"></span>
        <span class="connection-meter__pin"></span>
    </div>

    <div class="connection-meter__body">
        <div class="connection-meter__head">
            <span class="summary-label">VU connexion</span>
            <strong id="connection-meter-title"><?= h($connectionStatusText) ?></strong>
        </div>

        <?php if ($message !== ''): ?>
            <div class="connection-meter__flash flash flash-<?= h($messageType) ?>" aria-live="polite">
                <p><?= h($message) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($authenticatedLand): ?>
            <p class="connection-meter__copy">λ <?= h((string) $activeLambda) ?> nm · <?= h($activeLandUsername) ?></p>
            <div class="connection-meter__actions">
                <a class="pill-link" href="<?= h(o_route_href('/land', ['u' => $activeLandSlug])) ?>">ouvrir</a>
                <a class="ghost-link" href="<?= h($logoutHref) ?>">retirer</a>
            </div>
        <?php elseif ($shouldRenderHomeLoginForm): ?>
            <form method="post" action="<?= h($homeHref) ?>#connexion" class="connection-meter__form" autocomplete="on">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <label>
                    <span>Terre</span>
                    <input
                        type="text"
                        name="login_identifier"
                        placeholder="nom"
                        required
                        value="<?= h($form['login_identifier']) ?>"
                        autocomplete="username"
                    >
                </label>
                <label>
                    <span>Secret</span>
                    <input
                        type="password"
                        name="password"
                        placeholder="secret"
                        required
                        autocomplete="current-password"
                    >
                </label>
                <button type="submit">entrer</button>
            </form>
            <a class="connection-meter__create" href="<?= h($joinHref) ?>">poser une terre</a>
        <?php else: ?>
            <p class="connection-meter__copy">Ouvre la connexion seulement si une terre est déjà posée. Sinon, pose d’abord une terre ou passe par 0wlslw0.</p>
            <div class="connection-meter__actions">
                <a class="pill-link" href="<?= h($homeConnectionHref) ?>">ouvrir la connexion</a>
                <a class="ghost-link" href="<?= h($joinHref) ?>">poser une terre</a>
            </div>
        <?php endif; ?>
    </div>
</details>
