<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host              = request_host();
$csrfToken         = csrf_token();
$brandDomain       = current_brand_domain($host);
$authenticatedLand = current_authenticated_land();
$homeHref = o_route_path('/');
$landBaseHref = o_route_path('/land');
$shoreBaseHref = o_route_path('/sh0re');
$str3mHref = o_route_path('/str3m');
$guideHref = o_route_path('/0wlslw0');
$nHref = o_route_path('/n');

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

                case 'shore_update':
                    if (!$isOwnShore) {
                        throw new RuntimeException('Seule la terre liée à ce rivage peut réécrire sa voix.');
                    }

                    $shoreText = (string) ($_POST['shore_text'] ?? '');
                    $updatedLand = update_land_shore_text($mySlug, $shoreText);
                    $authenticatedLand = $updatedLand;
                    $viewLand = $updatedLand;
                    $targetLand = $updatedLand;
                    $message = trim($shoreText) === ''
                        ? 'La voix stable du rivage a été retirée.'
                        : 'La voix du rivage a été mise à jour.';
                    $messageType = 'success';
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
$pendingIncomingCount = count($pendingIncoming);
$activeNousCount = count($activeNous);
$outgoingCount = count($outgoing);
$shoreB0t3Count = count($shoreB0t3s);
$shoreText = trim((string) ($viewLand['shore_text'] ?? ''));
$hasShoreText = $shoreText !== '';
$showShoreVoice = $viewLand && ($hasShoreText || $isOwnShore);
$sh0reViewLabel = $isOwnShore ? 'présence liée' : ($viewLand ? 'lecture publique' : 'lecture de principe');
$sh0rePublicCopy = $viewLand
    ? 'Ce rivage laisse voir les n0us actifs et reçoit des b0t3s, même sans présence liée.'
    : 'Ouvre une terre ou vise le rivage d’une autre land pour voir ce qui circule ici.';
$sh0rePrivateCopy = $isOwnShore
    ? 'Les t0ks entrants, l’acceptation et la dissolution restent réservés à ta session.'
    : ($isAuthenticated
        ? 'Depuis ta présence liée, tu peux lancer un t0k. Les réponses restent ensuite sur le rivage concerné.'
        : 'Pour lancer, accepter ou dissoudre un t0k, il faut une terre ouverte.');
$sh0reNowTitle = 'Aucun rivage ciblé';
$sh0reNowCopy = 'Passe par ta terre ou ouvre le rivage d’une autre land pour voir les échanges se déposer ici.';
$t0kPublicLabel = static fn (string $token): string => $nHref . '?t=' . rawurlencode($token);

if ($viewLand) {
    if ($isOwnShore) {
        if ($pendingIncomingCount > 0) {
            $sh0reNowTitle = 'Des réponses t’attendent';
            $sh0reNowCopy = $pendingIncomingCount . ' t0k' . ($pendingIncomingCount > 1 ? 's' : '') . ' attend' . ($pendingIncomingCount > 1 ? 'ent' : '') . ' une réponse sur ton propre rivage.';
        } else {
            $sh0reNowTitle = 'Ton rivage est prêt';
            $sh0reNowCopy = 'Tu peux lancer un t0k, accueillir un n0us ou déposer une ligne publique sans changer de page.';
        }
    } elseif ($activeNousCount > 0) {
        $sh0reNowTitle = 'Le rivage montre déjà des liaisons';
        $sh0reNowCopy = $activeNousCount . ' n0us actif' . ($activeNousCount > 1 ? 's' : '') . ' reste' . ($activeNousCount > 1 ? 'nt' : '') . ' visible' . ($activeNousCount > 1 ? 's' : '') . ' depuis ce bord public.';
    } else {
        $sh0reNowTitle = 'Le rivage reste calme';
        $sh0reNowCopy = 'Ici, on voit surtout le bord public de la terre. Les gestes de formation restent ailleurs.';
    }
}

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
<?= render_o_page_head_assets(pwa_default_app_id($host), $host) ?>
</head>
<body class="experience sh0re-view">
<?= render_skip_link() ?>
<?= render_nucleus_banner('sh0re') ?>
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
        <p class="lead">Sh0re montre le bord public d’une terre. On peut y voir les n0us actifs et y déposer des b0t3s ; les gestes de formation restent liés à une présence ouverte.</p>
        <div class="land-meta">
            <?php if ($viewLand): ?>
                <a class="meta-pill meta-pill-link" href="<?= h($landBaseHref) ?>?u=<?= rawurlencode((string) $viewLand['slug']) ?>">Terre</a>
                <span class="meta-pill"><?= h((string) $viewLand['slug']) ?></span>
            <?php endif; ?>
            <?php if ($isAuthenticated): ?>
                <?php if (!$isOwnShore && $viewLand): ?>
                    <a class="meta-pill meta-pill-link" href="<?= h($shoreBaseHref) ?>">Mon sh0re</a>
                <?php endif; ?>
                <span class="meta-pill"><?= h((string) $authenticatedLand['username']) ?></span>
            <?php else: ?>
                <a class="meta-pill meta-pill-link" href="<?= h($homeHref) ?>">Ouvrir une land</a>
            <?php endif; ?>
            <span class="meta-pill"><?= h($sh0reViewLabel) ?></span>
            <?php if ($viewLand): ?>
                <span class="meta-pill"><?= $activeNousCount ?> n0us</span>
                <span class="meta-pill"><?= $shoreB0t3Count ?> b0t3<?= $shoreB0t3Count > 1 ? 's' : '' ?></span>
            <?php endif; ?>
            <a class="meta-pill meta-pill-link" href="<?= h($str3mHref) ?>">str3m</a>
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
                <p class="t0k-url"><?= h($t0kPublicLabel((string) $sent['token'])) ?></p>
                <?php if (!empty($sent['notes'])): ?>
                    <p class="t0k-notes"><?= h((string) $sent['notes']) ?></p>
                <?php endif; ?>
            </div>
            <p class="panel-copy">Ce token peut être gravé, imprimé, mis en NFC. Il attend à la porte de <?= h((string) $sent['to_land']) ?>.</p>
        </section>
    <?php endif; ?>

    <section class="panel reveal sh0re-mode-panel" aria-labelledby="sh0re-mode-title">
        <div class="section-topline">
            <div>
                <h2 id="sh0re-mode-title">Repères du rivage</h2>
                <p class="panel-copy">Sh0re sépare le bord public de la terre et les gestes qui demandent une présence liée. On peut regarder, déposer, puis seulement ensuite former ou répondre.</p>
            </div>
            <span class="badge"><?= h($sh0reViewLabel) ?></span>
        </div>
        <div class="land-focus-grid sh0re-focus-grid">
            <article class="land-focus-card">
                <p class="land-card-kicker">ouvert</p>
                <h3>Voir le bord public</h3>
                <p class="land-card-copy"><?= h($sh0rePublicCopy) ?></p>
            </article>
            <article class="land-focus-card">
                <p class="land-card-kicker">réservé</p>
                <h3>Former, répondre, dissoudre</h3>
                <p class="land-card-copy"><?= h($sh0rePrivateCopy) ?></p>
            </article>
            <article class="land-focus-card">
                <p class="land-card-kicker">ici maintenant</p>
                <h3><?= h($sh0reNowTitle) ?></h3>
                <p class="land-card-copy"><?= h($sh0reNowCopy) ?></p>
            </article>
        </div>
    </section>

    <?php if (!$viewLand): ?>
        <section class="panel reveal" aria-labelledby="sh0re-empty-title">
            <div class="section-topline">
                <div>
                    <h2 id="sh0re-empty-title">Choisir un rivage</h2>
                    <p class="panel-copy">Sans terre ouverte ni rivage ciblé, Sh0re reste seulement un principe. Ouvre une terre ou vise une autre rive pour voir les n0us et les b0t3s se déposer.</p>
                </div>
                <span class="badge">orientation</span>
            </div>
            <div class="action-row">
                <a class="pill-link" href="<?= h($homeHref) ?>">Ouvrir une land</a>
                <a class="ghost-link" href="<?= h($str3mHref) ?>">Rester dans str3m</a>
                <a class="ghost-link" href="<?= h($guideHref) ?>">Passer par 0wlslw0</a>
            </div>
        </section>
    <?php else: ?>
    <?php if ($showShoreVoice): ?>
    <section class="panel reveal sh0re-voice-panel" aria-labelledby="sh0re-voice-title">
        <div class="section-topline">
            <div>
                <h2 id="sh0re-voice-title">Voix du rivage</h2>
                <p class="panel-copy">Ligne plus stable que les b0t3s. Elle reste visible sur le bord public tant que la terre la tient.</p>
            </div>
            <span class="badge">public</span>
        </div>

        <?php if ($hasShoreText): ?>
            <div class="sh0re-voice-copy">
                <?= nl2br(h($shoreText)) ?>
            </div>
        <?php else: ?>
            <p class="panel-copy"><?= $isOwnShore ? 'Ton rivage n’a pas encore de ligne stable. Tu peux en poser une ici.' : 'Ce rivage n’a pas encore laissé de ligne durable visible.' ?></p>
        <?php endif; ?>

        <?php if ($isOwnShore): ?>
            <form method="post" class="land-form sh0re-voice-form">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="shore_update">
                <label>
                    Texte du rivage
                    <textarea
                        name="shore_text"
                        rows="6"
                        maxlength="<?= LAND_SHORE_TEXT_MAX_LENGTH ?>"
                        placeholder="Une ligne durable. Une adresse lente. Ce que le rivage garde."
                    ><?= h($shoreText) ?></textarea>
                    <span class="input-hint">Visible publiquement sur ce sh0re. Les b0t3s restent plus passants ; cette voix tient un peu plus longtemps.</span>
                </label>
                <button type="submit">Mettre à jour la voix du rivage</button>
            </form>
        <?php else: ?>
            <p class="sh0re-voice-note">Les b0t3s déposent des lignes passantes. Cette voix, elle, reste le texte tenu par la terre elle-même.</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="panel-shell sh0re-shell">

        <?php if ($isOwnShore && $pendingIncoming): ?>
        <section class="panel reveal" aria-labelledby="sh0re-incoming-title">
            <div class="section-topline">
                <div>
                    <h2 id="sh0re-incoming-title">Réservé · t0ks à ta porte</h2>
                    <p class="panel-copy">Ces demandes n’apparaissent qu’à la présence liée à ce rivage.</p>
                </div>
                <span class="badge"><?= $pendingIncomingCount ?> en attente</span>
            </div>
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
            <div class="section-topline">
                <div>
                    <h2 id="sh0re-nous-title">N0us visibles<?= $viewLand ? ' · ' . h((string) $viewLand['username']) : '' ?></h2>
                    <p class="panel-copy"><?= $isOwnShore ? 'Depuis ici, on voit ce qui tient déjà entre les rives.' : 'La partie visible du rivage montre les liaisons déjà formées.' ?></p>
                </div>
                <span class="badge"><?= $activeNousCount ?> actif<?= $activeNousCount > 1 ? 's' : '' ?></span>
            </div>
            <?php if ($activeNous): ?>
                <div class="t0k-list t0k-list-nous">
                    <?php foreach ($activeNous as $t0k): ?>
                        <?php $partner = $viewLand ? t0k_partner_slug($t0k, (string) $viewLand['slug']) : ''; ?>
                        <article class="t0k-card t0k-card-active">
                            <div class="t0k-token"><?= h(t0k_format_token((string) $t0k['token'])) ?></div>
                            <p class="t0k-route">
                                <a class="ghost-link" href="<?= h($shoreBaseHref) ?>?u=<?= rawurlencode($partner) ?>"><?= h($partner) ?></a>
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
                    <h2 id="sh0re-send-title"><?= $isOwnShore ? 'Présence liée · lancer un t0k' : 'Depuis ma présence · lancer un t0k' ?></h2>
                    <p class="panel-copy"><?= $isOwnShore ? 'Un geste, une adresse, une question. Le départ se fait depuis ton propre rivage.' : 'Même en visitant un autre rivage, le geste part de ta terre et attend ensuite à leur porte.' ?></p>
                </div>
                <?php if ($viewLand && !$isOwnShore): ?>
                    <span class="badge">vers <?= h((string) $viewLand['slug']) ?></span>
                <?php endif; ?>
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
        <section class="panel reveal" aria-labelledby="sh0re-auth-title">
            <div class="section-topline">
                <div>
                    <h2 id="sh0re-auth-title">Présence requise pour lancer un t0k</h2>
                    <p class="panel-copy">Le rivage reste visible, mais l’envoi d’un t0k demande une terre ouverte.</p>
                </div>
                <span class="badge">présence liée</span>
            </div>
            <div class="action-row">
                <a class="pill-link" href="<?= h($homeHref) ?>">Ouvrir une land</a>
                <a class="ghost-link" href="<?= h($str3mHref) ?>">Rester dans str3m</a>
            </div>
        </section>
        <?php endif; ?>

    </section>

    <?php if ($outgoing): ?>
    <section class="panel reveal" aria-labelledby="sh0re-outgoing-title">
        <div class="section-topline">
            <div>
                <h2 id="sh0re-outgoing-title">T0ks en chemin</h2>
                <p class="panel-copy">Partis de ce rivage, visibles tant qu’ils n’ont pas encore reçu de réponse.</p>
            </div>
            <span class="badge"><?= $outgoingCount ?> en transit</span>
        </div>
        <div class="t0k-list">
            <?php foreach ($outgoing as $t0k): ?>
                <article class="t0k-card t0k-card-pending">
                    <div class="t0k-token"><?= h(t0k_format_token((string) $t0k['token'])) ?></div>
                    <p class="t0k-route">→ <a class="ghost-link" href="<?= h($shoreBaseHref) ?>?u=<?= rawurlencode((string) $t0k['to_land']) ?>"><?= h((string) $t0k['to_land']) ?></a></p>
                    <p class="t0k-url"><?= h($t0kPublicLabel((string) $t0k['token'])) ?></p>
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
                <p class="panel-copy">Dépôt public du rivage. Touche long ou clic pour déformer.</p>
            </div>
            <span class="badge"><?= $shoreB0t3Count ?> ligne<?= $shoreB0t3Count > 1 ? 's' : '' ?></span>
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
            <p class="sh0re-visibility-note">
                <?php if ($isOwnShore): ?>
                    Toute personne qui atteint ce rivage peut déposer une ligne. Toi, tu peux aussi dissoudre celles qui te concernent.
                <?php elseif ($isAuthenticated): ?>
                    Le dépôt reste public, mais l’origine peut encore être liée à ta terre si tu es déjà présent ici.
                <?php else: ?>
                    Déposer une ligne ici ne demande pas de présence liée. Les t0ks, eux, restent réservés aux terres ouvertes.
                <?php endif; ?>
            </p>
        </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php endif; ?>

</main>
</body>
</html>
