<?php
declare(strict_types=1);

define('SOWWWL_SKIP_BOOTSTRAP_REQUEST', true);
require_once __DIR__ . '/config.php';

http_response_code(404);
send_security_headers();
header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, follow');

$host = request_host();
$homeHref = o_route_href('/', [], $host);
$joinHref = o_route_href('/rejoindre', [], $host);
$str3mHref = o_route_href('/str3m', [], $host);
$guideHref = guide_public_href($host);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,follow">
    <meta name="theme-color" content="#09090b">
    <title>Page introuvable — <?= h(SITE_TITLE) ?></title>
    <link rel="icon" href="<?= h(o_public_href('favicon.svg')) ?>" type="image/svg+xml">
    <style>
        :root {
            color-scheme: dark;
            --bg: #09090b;
            --panel: rgba(16, 18, 24, 0.9);
            --line: rgba(244, 244, 245, 0.12);
            --text: #f4f4f5;
            --muted: rgba(244, 244, 245, 0.72);
            --accent: #9ae6b4;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem;
            background:
                radial-gradient(circle at top, rgba(154, 230, 180, 0.18), transparent 34%),
                linear-gradient(180deg, #09090b 0%, #050507 100%);
            color: var(--text);
            font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
        }

        main {
            width: min(720px, 100%);
            padding: 2rem;
            border: 1px solid var(--line);
            border-radius: 28px;
            background: var(--panel);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.45);
        }

        .eyebrow {
            margin: 0 0 0.75rem;
            color: var(--accent);
            font-size: 0.78rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: clamp(2.4rem, 8vw, 4.4rem);
            line-height: 0.96;
        }

        p {
            margin: 1rem 0 0;
            max-width: 38rem;
            color: var(--muted);
            line-height: 1.6;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-top: 1.75rem;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .pill,
        .ghost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.9rem;
            padding: 0.8rem 1.1rem;
            border-radius: 999px;
            border: 1px solid var(--line);
            transition: transform 160ms ease, border-color 160ms ease, background 160ms ease;
        }

        .pill {
            background: rgba(154, 230, 180, 0.12);
            border-color: rgba(154, 230, 180, 0.28);
        }

        .ghost {
            background: rgba(255, 255, 255, 0.02);
        }

        .pill:hover,
        .ghost:hover,
        .pill:focus-visible,
        .ghost:focus-visible {
            transform: translateY(-1px);
            border-color: rgba(154, 230, 180, 0.42);
            background: rgba(154, 230, 180, 0.16);
            outline: none;
        }
    </style>
</head>
<body>
<main>
    <p class="eyebrow">404 · couche absente</p>
    <h1>La route a glissé hors du tore.</h1>
    <p>La page demandée ne répond pas ici. Repars par une entrée stable, relis le courant public, ou laisse 0wlslw0 te redonner la bonne porte.</p>
    <div class="actions">
        <a class="pill" href="<?= h($homeHref) ?>">Retour au noyau</a>
        <a class="ghost" href="<?= h($str3mHref) ?>">Ouvrir Str3m</a>
        <a class="ghost" href="<?= h($joinHref) ?>">Poser une terre</a>
        <a class="ghost" href="<?= h($guideHref) ?>">Passer par 0wlslw0</a>
    </div>
</main>
</body>
</html>
