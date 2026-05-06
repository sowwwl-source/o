<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host              = request_host();
$csrfToken         = csrf_token();
$brandDomain       = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
$authenticatedLand = current_authenticated_land();

// Which land's shore are we visiting?
$targetSlug = trim((string) ($_GET['u'] ?? ''));
$targetLand = null;
if ($targetSlug !== '') {
    try {
        $targetLand = find_land($targetSlug);
        if ($targetLand) {
            $targetSlug = (string) $targetLand['slug'];
        }
    } catch (Throwable) {
        $targetLand = null;
    }
}

$viewLand        = $targetLand ?: $authenticatedLand;
$isOwnShore      = $authenticatedLand && $viewLand && ($viewLand['slug'] === $authenticatedLand['slug']);
$isAuthenticated = $authenticatedLand !== null;

$message     = '';
$messageType = 'info';
$sent        = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAuthenticated) {
    $csrfCandidate = (string) ($_POST['csrf_token'] ?? '');
    $action        = trim((string) ($_POST['action'] ?? ''));
    $mySlug        = (string) $authenticatedLand['slug'];

    if (!verify_csrf_token($csrfCandidate)) {
        $message     = 'Session expirée. Recharge la page.';
        $messageType = 'warning';
    } else {
        try {
            switch ($action) {
                case 'send':
                    $toSlug = strtolower(trim((string) ($_POST['to_land'] ?? '')));
                    $notes  = trim((string) ($_POST['notes'] ?? ''));
                    if (find_land($toSlug) === null) {
                        throw new InvalidArgumentException('Cette land n\'existe pas.');
                    }
                    $sent        = t0k_send($mySlug, $toSlug, $notes);
                    $message     = 'T0k lancé depuis ton sh0re vers ' . h($toSlug) . '. Il attend à leur porte.';
                    $messageType = 'success';
                    break;

                case 'accept':
                    $t0kId = trim((string) ($_POST['t0k_id'] ?? ''));
                    t0k_accept($t0kId, $mySlug);
                    $message     = 'N0us formé.';
                    $messageType = 'success';
                    break;

                case 'decline':
                    $t0kId = trim((string) ($_POST['t0k_id'] ?? ''));
                    t0k_decline($t0kId, $mySlug);
                    $message     = 'T0k décliné.';
                    $messageType = 'info';
                    break;

                case 'dissolve':
                    $t0kId = trim((string) ($_POST['t0k_id'] ?? ''));
                    t0k_dissolve($t0kId, $mySlug);
                    $message     = 'Ce n0us a été dissous.';
                    $messageType = 'info';
                    break;
            }
        } catch (Throwable $e) {
            $message     = $e->getMessage();
            $messageType = 'warning';
        }
    }
}

// Handle b0t3 deposit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'b0t3_deposit') {
    $csrfCandidate = (string) ($_POST['csrf_token'] ?? '');
    if (!verify_csrf_token($csrfCandidate)) {
        $message = 'Session expirée.';
        $messageType = 'warning';
    } elseif ($viewLand) {
        try {
            $b0t3Text        = trim((string) ($_POST['b0t3_text'] ?? ''));
            $b0t3Kind        = trim((string) ($_POST['b0t3_kind'] ?? 'human'));
            $b0t3Instability = (float) ($_POST['b0t3_instability'] ?? 0.25);
            $b0t3Origin      = $isAuthenticated ? (string) $authenticatedLand['slug'] : '';
            b0t3_deposit((string) $viewLand['slug'], $b0t3Text, $b0t3Kind, $b0t3Instability, $b0t3Origin);
            $message     = 'B0t3 déposé sur ce sh0re.';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message     = $e->getMessage();
            $messageType = 'warning';
        }
    }
}

// Handle b0t3 dissolve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'b0t3_dissolve' && $isAuthenticated) {
    $csrfCandidate = (string) ($_POST['csrf_token'] ?? '');
    if (verify_csrf_token($csrfCandidate)) {
        try {
            b0t3_dissolve(trim((string) ($_POST['b0t3_id'] ?? '')), (string) $authenticatedLand['slug']);
            $message     = 'B0t3 dissous.';
            $messageType = 'info';
        } catch (Throwable $e) {
            $message     = $e->getMessage();
            $messageType = 'warning';
        }
    }
}

// Load data for the viewed shore
$pendingIncoming = $viewLand ? t0k_pending_for_land((string) $viewLand['slug']) : [];
$activeNous      = $viewLand ? t0k_active_for_land((string) $viewLand['slug']) : [];
$outgoing        = $viewLand ? array_filter(
    t0k_list_for_land((string) $viewLand['slug']),
    static fn ($t) => ($t['from_land'] ?? '') === $viewLand['slug'] && ($t['status'] ?? '') === 'pending'
) : [];
$shoreB0t3s      = $viewLand ? b0t3_list_for_shore((string) $viewLand['slug']) : [];

$ambientProfile = $viewLand ? land_visual_profile($viewLand) : land_collective_profile('nocturnal');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sh0re — le rivage de <?= h((string) ($viewLand['username'] ?? 'cette land')) ?> dans <?= h(SITE_TITLE) ?>.">
    <meta name="theme-color" content="#09090b">
    <title>Sh0re<?= $viewLand ? ' · ' . h((string) $viewLand['username']) : '' ?> — <?= h(SITE_TITLE) ?></title>
<?= render_o_page_head_assets('main') ?>
</head>
<body class="experience sh0re-view">
<?= render_skip_link() ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'nocturnal', 'sh0re') ?>

<main <?= main_landmark_attrs() ?> class="layout page-shell">

    <header class="hero page-header reveal">
        <p class="eyebrow"><strong>sh0re</strong> <span>rivage · n0us · t0ks</span></p>
        <h1 class="land-title">
            <?php if ($viewLand): ?>
                <strong><?= h((string) $viewLand['username']) ?></strong>
                <span>voulez-vous grandir avec moi ?</span>
            <?php else: ?>
                <strong>Le rivage.</strong>
                <span>où les t0ks arrivent</span>
            <?php endif; ?>
        </h1>
        <div class="land-meta">
            <?php if ($viewLand): ?>
                <a class="meta-pill meta-pill-link" href="/land.php?u=<?= rawurlencode((string) $viewLand['slug']) ?>">Terre</a>
            <?php endif; ?>
            <?php if ($isAuthenticated): ?>
                <?php if (!$isOwnShore && $viewLand): ?>
                    <a class="meta-pill meta-pill-link" href="/sh0re">Mon sh0re</a>
                <?php endif; ?>
                <span class="meta-pill"><?= h((string) $authenticatedLand['username']) ?></span>
            <?php else: ?>
                <a class="meta-pill meta-pill-link" href="/">Ouvrir une land</a>
            <?php endif; ?>
            <a class="meta-pill meta-pill-link" href="/str3m">str3m</a>
        </div>
    </header>

    <?php if ($message !== ''): ?>
        <div class="flash flash-<?= h($messageType) ?>" aria-live="polite"><p><?= $message ?></p></div>
    <?php endif; ?>

    <?php if ($sent): ?>
        <section class="panel reveal sh0re-t0k-sent">
            <h2>T0k en chemin</h2>
            <div class="t0k-card t0k-card-pending">
                <div class="t0k-token"><?= h(t0k_format_token((string) $sent['token'])) ?></div>
                <p class="t0k-route"><?= h((string) $sent['from_land']) ?> <span>→</span> <?= h((string) $sent['to_land']) ?></p>
                <p class="t0k-url">/n?t=<?= h((string) $sent['token']) ?></p>
                <?php if (!empty($sent['notes'])): ?>
                    <p class="t0k-notes"><?= h((string) $sent['notes']) ?></p>
                <?php endif; ?>
            </div>
            <p class="panel-copy">Ce token peut être gravé, imprimé, mis en NFC. Il attend à la porte de <?= h((string) $sent['to_land']) ?>.</p>
        </section>
    <?php endif; ?>

    <section class="panel-shell sh0re-shell">

        <?php if ($isOwnShore && $pendingIncoming): ?>
        <section class="panel reveal" aria-labelledby="sh0re-incoming-title">
            <h2 id="sh0re-incoming-title">T0ks à ta porte</h2>
            <p class="panel-copy"><?= count($pendingIncoming) ?> t0k<?= count($pendingIncoming) > 1 ? 's' : '' ?> attend<?= count($pendingIncoming) === 1 ? '' : 'ent' ?> une réponse.</p>
            <div class="t0k-list">
                <?php foreach ($pendingIncoming as $t0k): ?>
                    <article class="t0k-card t0k-card-incoming">
                        <div class="t0k-token"><?= h(t0k_format_token((string) $t0k['token'])) ?></div>
                        <p class="t0k-route"><strong><?= h((string) $t0k['from_land']) ?></strong> <span>→</span> toi</p>
                        <?php if (!empty($t0k['notes'])): ?>
                            <p class="t0k-notes"><?= h((string) $t0k['notes']) ?></p>
                        <?php endif; ?>
                        <p class="t0k-date"><?= h(substr((string) $t0k['sent_at'], 0, 10)) ?></p>
                        <div class="t0k-actions">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="action" value="accept">
                                <input type="hidden" name="t0k_id" value="<?= h((string) $t0k['id']) ?>">
                                <button type="submit" class="pill-link">Accepter · former le n0us</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="action" value="decline">
                                <input type="hidden" name="t0k_id" value="<?= h((string) $t0k['id']) ?>">
                                <button type="submit" class="ghost-link">Décliner</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="panel reveal" aria-labelledby="sh0re-nous-title">
            <h2 id="sh0re-nous-title">N0us<?= $viewLand ? ' de ' . h((string) $viewLand['username']) : '' ?></h2>
            <?php if ($activeNous): ?>
                <p class="panel-copy"><?= count($activeNous) ?> n0us actif<?= count($activeNous) > 1 ? 's' : '' ?>.</p>
                <div class="t0k-list t0k-list-nous">
                    <?php foreach ($activeNous as $t0k): ?>
                        <?php $partner = $viewLand ? t0k_partner_slug($t0k, (string) $viewLand['slug']) : ''; ?>
                        <article class="t0k-card t0k-card-active">
                            <div class="t0k-token"><?= h(t0k_format_token((string) $t0k['token'])) ?></div>
                            <p class="t0k-route">
                                <a class="ghost-link" href="/sh0re?u=<?= rawurlencode($partner) ?>"><?= h($partner) ?></a>
                            </p>
                            <?php if (!empty($t0k['formed_at'])): ?>
                                <p class="t0k-date">depuis le <?= h(substr((string) $t0k['formed_at'], 0, 10)) ?></p>
                            <?php endif; ?>
                            <?php if ($isOwnShore): ?>
                                <form method="post" class="t0k-dissolve">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="action" value="dissolve">
                                    <input type="hidden" name="t0k_id" value="<?= h((string) $t0k['id']) ?>">
                                    <button type="submit" class="ghost-link t0k-dissolve-btn">Dissoudre</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="panel-copy">Aucun n0us actif<?= $isOwnShore ? ' pour l\'instant.' : '.' ?></p>
            <?php endif; ?>
        </section>

        <?php if ($isAuthenticated): ?>
        <section class="panel reveal" aria-labelledby="sh0re-send-title">
            <div class="section-topline">
                <div>
                    <h2 id="sh0re-send-title">Lancer un t0k</h2>
                    <p class="panel-copy">Un geste. Une question. Voulez-vous grandir avec moi ?</p>
                </div>
            </div>
            <form method="post" class="land-form sh0re-form">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="send">
                <label>
                    Vers la land
                    <input type="text" name="to_land"
                        placeholder="ex: nox"
                        value="<?= $viewLand && !$isOwnShore ? h((string) $viewLand['slug']) : '' ?>"
                        required>
                    <span class="input-hint">Le slug de la land destinataire.</span>
                </label>
                <label>
                    Message (facultatif)
                    <textarea name="notes" rows="3" placeholder="Ce que tu veux dire en lançant ce t0k."></textarea>
                </label>
                <button type="submit">Lancer le t0k depuis mon sh0re</button>
            </form>
        </section>
        <?php elseif (!$isAuthenticated): ?>
        <section class="panel reveal">
            <h2>Pour lancer un t0k</h2>
            <p class="panel-copy">Il faut une land. Le n0us commence là.</p>
            <div class="action-row">
                <a class="pill-link" href="/">Ouvrir une land</a>
            </div>
        </section>
        <?php endif; ?>

    </section>

    <?php if ($outgoing): ?>
    <section class="panel reveal" aria-labelledby="sh0re-outgoing-title">
        <h2 id="sh0re-outgoing-title">T0ks en chemin</h2>
        <p class="panel-copy">Partis de ce sh0re, pas encore répondus.</p>
        <div class="t0k-list">
            <?php foreach ($outgoing as $t0k): ?>
                <article class="t0k-card t0k-card-pending">
                    <div class="t0k-token"><?= h(t0k_format_token((string) $t0k['token'])) ?></div>
                    <p class="t0k-route">→ <a class="ghost-link" href="/sh0re?u=<?= rawurlencode((string) $t0k['to_land']) ?>"><?= h((string) $t0k['to_land']) ?></a></p>
                    <p class="t0k-url">/n?t=<?= h((string) $t0k['token']) ?></p>
                    <p class="t0k-date"><?= h(substr((string) $t0k['sent_at'], 0, 10)) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($shoreB0t3s || $viewLand): ?>
    <section class="panel reveal sh0re-b0t3-section" aria-labelledby="sh0re-b0t3-title">
        <div class="section-topline">
            <div>
                <h2 id="sh0re-b0t3-title">B0t3s</h2>
                <p class="panel-copy">Lignes déposées. Touche long ou clic pour déformer.</p>
            </div>
        </div>

        <?php if ($shoreB0t3s): ?>
        <div class="b0t3-field">
            <?php foreach ($shoreB0t3s as $b0t3): ?>
                <div class="b0t3-line-wrap">
                    <span
                        class="b0t3-line"
                        data-b0t3="<?= h((string) $b0t3['text']) ?>"
                        data-b0t3-instability="<?= h((string) $b0t3['instability']) ?>"
                        data-b0t3-kind="<?= h((string) $b0t3['kind']) ?>"
                        title="<?= h((string) $b0t3['kind']) ?><?= !empty($b0t3['origin_land']) ? ' · ' . h((string) $b0t3['origin_land']) : '' ?>"
                    ><?= h((string) $b0t3['text']) ?></span>
                    <?php
                    $canDissolve = $isAuthenticated && (
                        (string) $authenticatedLand['slug'] === (string) ($b0t3['origin_land'] ?? '')
                        || (string) $authenticatedLand['slug'] === (string) ($b0t3['target_land'] ?? '')
                    );
                    ?>
                    <?php if ($canDissolve): ?>
                        <form method="post" class="b0t3-dissolve-form">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                            <input type="hidden" name="action" value="b0t3_dissolve">
                            <input type="hidden" name="b0t3_id" value="<?= h((string) $b0t3['id']) ?>">
                            <button type="submit" class="b0t3-dissolve-btn" title="Dissoudre">×</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($viewLand): ?>
        <form method="post" class="land-form b0t3-deposit-form">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="b0t3_deposit">
            <div class="b0t3-form-row">
                <input
                    type="text"
                    name="b0t3_text"
                    class="b0t3-input"
                    placeholder="une ligne. infiniment brouillée."
                    maxlength="280"
                    autocomplete="off"
                    spellcheck="false"
                >
                <select name="b0t3_kind" class="b0t3-kind-select">
                    <?php foreach (b0t3_kinds() as $k => $kl): ?>
                        <option value="<?= h($k) ?>"><?= h($kl) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="b0t3-instability-label" title="Instabilité">
                    <input type="range" name="b0t3_instability" min="0" max="1" step="0.05" value="0.25" class="b0t3-instability-range">
                </label>
                <button type="submit" class="b0t3-submit">déposer</button>
            </div>
        </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>

</main>
</body>
</html>
