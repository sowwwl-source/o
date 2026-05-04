<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$requestedApp = trim((string) ($_GET['app'] ?? ''));
$config = pwa_app_config($requestedApp !== '' ? $requestedApp : null);

header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');

echo json_encode([
    'id' => (string) ($config['id'] ?? '/app/main'),
    'name' => (string) ($config['name'] ?? SITE_TITLE),
    'short_name' => (string) ($config['short_name'] ?? 'O.'),
    'description' => (string) ($config['description'] ?? SITE_TITLE),
    'lang' => (string) ($config['lang'] ?? 'fr'),
    'start_url' => (string) ($config['start_url'] ?? '/'),
    'scope' => (string) ($config['scope'] ?? '/'),
    'display' => (string) ($config['display'] ?? 'standalone'),
    'background_color' => (string) ($config['background_color'] ?? '#09090b'),
    'theme_color' => (string) ($config['theme_color'] ?? '#09090b'),
    'orientation' => (string) ($config['orientation'] ?? 'portrait'),
    'icons' => is_array($config['icons'] ?? null) ? $config['icons'] : [],
    'shortcuts' => is_array($config['shortcuts'] ?? null) ? $config['shortcuts'] : [],
    'prefer_related_applications' => false,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
