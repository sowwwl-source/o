<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/signal_mail.php';

$host = request_host();
$surfaceVariant = current_surface_variant($host);
$isSpatialHeadsetMode = $surfaceVariant === 'io' && spatial_preview_mode($host) === 'headset';

$brandDomain = current_brand_domain($host);
$csrfToken = csrf_token();
$guideHref = o_route_href('/0wlslw0', [], $host);
$homeHref = o_route_href('/', [], $host);
$joinHref = o_route_href('/rejoindre', [], $host);
$signalHref = o_route_href('/signal', [], $host);
$str3mHref = o_route_href('/str3m', [], $host);
$echoHref = o_route_href('/echo', [], $host);
$signalLiveHref = o_route_href('/signal_live.php', [], $host);
$echoThreadHref = static fn (string $username): string => o_route_href('/echo', ['u' => trim($username)], $host);
$signalThreadHref = static fn (string $slug): string => o_route_href('/signal', ['u' => trim($slug)], $host);
$echoGuide = guide_path('echo');

$land = current_authenticated_land();
$ambientProfile = $land ? land_visual_profile($land) : land_collective_profile('dense');
$targetIdentifier = trim((string) ($_GET['u'] ?? ''));
$message = '';
$messageType = 'info';
$signalSchemaStatus = signal_mail_schema_status();
$messagingReady = (bool) ($signalSchemaStatus['ready'] ?? false);
$signalSchemaHint = signal_mail_schema_status_hint($signalSchemaStatus);
$targetLand = $targetIdentifier !== '' ? signal_find_land_by_identifier($targetIdentifier) : null;
$targetUsername = trim((string) ($targetLand['username'] ?? ''));
$targetSlug = trim((string) ($targetLand['slug'] ?? ''));

// Traitement de l'envoi d'un écho
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!$land) {
        header('Location: ' . o_route_href('/echo', ['error' => 'auth'], $host), true, 303);
        exit;
    } elseif (!hash_equals($csrfToken, $postedToken)) {
        $message = "La résonance s'est dissipée. Réessaie.";
        $messageType = 'warning';
    } elseif (!$messagingReady) {
        $message = "Écho attend encore sa base de messagerie. " . $signalSchemaHint;
        $messageType = 'warning';
    } else {
        $body = trim((string) ($_POST['body'] ?? ''));
        $receiver = trim((string) ($_POST['receiver_slug'] ?? $_POST['receiver_username'] ?? ''));
        
        try {
            if ($body !== '' && $receiver !== '') {
                signal_send_message($land, $receiver, '', $body);
                $receiverLand = signal_find_land_by_identifier($receiver);
                $receiverUsername = trim((string) ($receiverLand['username'] ?? $receiver));
                header('Location: ' . $echoThreadHref($receiverUsername));
                exit;
            }
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $message = $exception->getMessage();
            $messageType = 'warning';
        } catch (Throwable $exception) {
            error_log('[sowwwl][echo] ' . $exception->getMessage());
            $message = 'Écho a perdu sa liaison SQL pour le moment.';
            $messageType = 'warning';
        }
    }
}

$errorCode = trim((string) ($_GET['error'] ?? ''));
if ($errorCode !== '') {
    $messageType = 'warning';
    $message = match ($errorCode) {
        'auth' => 'Ouvre une terre pour activer Echo en direct.',
        'session' => 'La session liée à la terre a expiré ou a été interrompue. Rouvre ta terre pour reprendre Echo.',
        default => 'Echo ne peut pas ouvrir la liaison pour le moment.',
    };
}

// Récupération des contacts (Terres existantes, excluant soi-même)
$contacts = [];
$history = [];
if ($land && $messagingReady) {
    try {
        foreach (signal_contact_candidates($land) as $contact) {
            $contacts[] = [
                'username' => trim((string) ($contact['counterpart_username'] ?? '')),
                'slug' => trim((string) ($contact['counterpart_slug'] ?? '')),
                'unread_count' => (int) ($contact['unread_count'] ?? 0),
            ];
        }

        if ($targetSlug !== '') {
            $history = signal_load_conversation($land, $targetSlug);
        }
    } catch (Throwable $exception) {
        error_log('[sowwwl][echo.load] ' . $exception->getMessage());
        $messagingReady = false;
        $signalSchemaHint = 'La couche SQL de Signal/Echo a répondu avec une erreur au chargement.';
        if ($message === '') {
            $message = 'Écho n’a pas pu relire sa base pour le moment. ' . $signalSchemaHint;
            $messageType = 'warning';
        }
    }
}

$echoHistoryHtml = signal_render_conversation_html($history, $land ?? [], false, 'Le silence règne entre vos deux terres.');
$echoHistoryHash = sha1($echoHistoryHtml);
$echoContactsHtml = signal_render_echo_contacts_html($contacts, $targetUsername);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Écho — résonance directe dans <?= h(SITE_TITLE) ?>.">
    <meta name="theme-color" content="#09090b">
    <title>Écho — <?= h(SITE_TITLE) ?></title>
<?= render_o_page_head_assets(pwa_default_app_id($host), $host) ?>
</head>
<body class="experience signal-view<?= $surfaceVariant === 'io' ? ' io-surface-view' : '' ?><?= $isSpatialHeadsetMode ? ' io-headset-mode' : '' ?>">
<?= render_skip_link() ?>
<?= render_nucleus_banner('echo') ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'dense', 'echo') ?>

<main <?= main_landmark_attrs() ?> class="layout page-shell">
    <header class="hero page-header reveal">
        <p class="eyebrow"><strong>écho</strong> <span>liaison inter-terres</span></p>
        <h1 class="land-title signal-title">
            <strong>Résonance ciblée.</strong>
            <span>Point à point</span>
        </h1>
        <p class="lead">Même trame que Signal, mais en direct.</p>

        <div class="land-meta">
            <a class="meta-pill meta-pill-link" href="<?= h($signalHref) ?>">signal / boîte</a>
            <a class="meta-pill meta-pill-link" href="<?= h($guideHref) ?>">0wlslw0</a>
            <?php if ($land): ?>
                <span class="meta-pill">terre liée : <?= h((string) $land['slug']) ?></span>
            <?php else: ?>
                <span class="meta-pill">lecture de principe</span>
            <?php endif; ?>
        </div>
    </header>

    <?= render_spatial_context_bar('echo', $host) ?>

    <?php if ($message !== ''): ?>
        <section class="panel reveal">
            <div class="flash flash-<?= h($messageType) ?>" aria-live="polite">
                <p><?= h($message) ?></p>
            </div>
        </section>
    <?php endif; ?>

    <section class="panel reveal signal-mode-panel" aria-labelledby="echo-mode-title">
        <div class="section-topline">
            <div>
                <h2 id="echo-mode-title">Direct, mais pas séparé</h2>
                <p class="panel-copy" data-echo-ra-note><?= h((string) ($echoGuide['copy'] ?? 'Relier deux terres sans passer par le bruit public.')) ?></p>
            </div>
            <a class="ghost-link" href="<?= h($signalHref) ?>">Revenir à Signal</a>
        </div>
        <div class="signal-mode-grid">
            <article class="signal-mode-card" data-echo-ra-card="signal">
                <p class="signal-mode-kicker">Signal · mémoire</p>
                <h3>Relire, cadrer, laisser formuler</h3>
                <p class="panel-copy">Quand la liaison doit encore se préciser, Signal garde la couture et l’adresse.</p>
            </article>
            <article class="signal-mode-card signal-mode-card--primary" data-echo-ra-card="echo">
                <p class="signal-mode-kicker">Écho · prise directe</p>
                <h3>Toucher la terre sans détour</h3>
                <p class="panel-copy">Quand la prise est claire, Écho coupe court et relance la même terre.</p>
            </article>
        </div>
    </section>

    <section
        class="echo-grid reveal"
        data-message-live
        data-live-view="echo"
        data-live-api="<?= h($signalLiveHref) ?>"
        data-live-target="<?= h($targetSlug) ?>"
        data-live-interval="2500"
        data-live-hash="<?= h($echoHistoryHash) ?>"
        data-live-message-count="<?= h((string) count($history)) ?>"
    >
        <aside class="echo-contacts" data-echo-contacts-list data-echo-ra-zone="contacts">
            <?= $echoContactsHtml ?>
        </aside>

        <div class="echo-conversation panel" data-echo-ra-zone="direct">
            <?php if (!$land): ?>
                <div class="echo-empty-state">
                    <p class="panel-copy" data-echo-ra-empty-note>Echo a besoin d’une terre active pour ouvrir une liaison directe. Sans terre, tu peux déjà lire Signal et préparer l’entrée.</p>
                    <div class="action-row">
                        <a class="pill-link" href="<?= h($joinHref) ?>">Ouvrir une terre</a>
                        <a class="ghost-link" href="<?= h($signalHref) ?>">Voir Signal</a>
                    </div>
                </div>
            <?php elseif (!$messagingReady): ?>
                <p class="panel-copy">Écho reste en veille tant que la base SQL unifiée de Signal n’est pas active.</p>
                <p class="panel-copy"><?= h($signalSchemaHint) ?></p>
            <?php elseif ($targetSlug === '' || !$targetLand): ?>
                <div class="echo-empty-state">
                    <p class="panel-copy" data-echo-ra-empty-note>Choisis une terre à gauche pour ouvrir la liaison directe. Si tu préfères l’adresse et le carnet, passe par Signal.</p>
                    <div class="action-row">
                        <a class="pill-link" href="<?= h($signalHref) ?>">Ouvrir Signal</a>
                        <a class="ghost-link" href="<?= h($str3mHref) ?>">Retour au public</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="section-topline">
                    <div>
                        <h2>Liaison avec <?= h($targetUsername) ?></h2>
                        <p class="panel-copy" data-echo-ra-thread-note>Direct, sur la même trame que Signal. Reviens à la boîte si tu veux plus de contexte.</p>
                    </div>
                    <span class="badge"><?= h(signal_virtual_address($targetLand)) ?></span>
                    <span class="meta-pill" data-message-live-indicator role="status" aria-live="polite" aria-atomic="true">direct · veille</span>
                </div>
                
                <div class="echo-history" data-message-live-history>
                    <?= $echoHistoryHtml ?>
                </div>

                <form action="<?= h($echoThreadHref($targetUsername)) ?>" method="post" class="land-form echo-form">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="receiver_username" value="<?= h($targetUsername) ?>">
                    <input type="hidden" name="receiver_slug" value="<?= h($targetSlug) ?>">

                    <p class="panel-copy echo-compose-note" data-echo-ra-compose-note>Transmission courte, directe, sans détour inutile.</p>
                    
                    <label>
                        Transmission
                        <textarea name="body" required placeholder="Écrire à cette terre, simplement..." data-echo-ra-textarea></textarea>
                    </label>
                    
                    <div class="action-row">
                        <button type="submit">Transmettre</button>
                        <a class="ghost-link" href="<?= h($signalThreadHref($targetSlug)) ?>">Ouvrir la même liaison dans Signal</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>

</main>
</body>
</html>
