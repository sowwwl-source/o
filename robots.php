<?php
declare(strict_types=1);

define('SOWWWL_SKIP_BOOTSTRAP_REQUEST', true);
require_once __DIR__ . '/config.php';

header_remove('X-Powered-By');
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=900');
header('Vary: Host');
header('X-Content-Type-Options: nosniff');

$host = request_host();
$sitemapHref = request_public_origin($host) . '/sitemap.xml';

$lines = [
    '# Indexing is allowed; known AI-training crawlers stay blocked.',
    'User-agent: *',
    'Allow: /',
    '',
    'Sitemap: ' . $sitemapHref,
    '',
    'User-agent: Amazonbot',
    'Disallow: /',
    '',
    'User-agent: Applebot-Extended',
    'Disallow: /',
    '',
    'User-agent: Bytespider',
    'Disallow: /',
    '',
    'User-agent: CCBot',
    'Disallow: /',
    '',
    'User-agent: ClaudeBot',
    'Disallow: /',
    '',
    'User-agent: Google-Extended',
    'Disallow: /',
    '',
    'User-agent: GPTBot',
    'Disallow: /',
    '',
    'User-agent: meta-externalagent',
    'Disallow: /',
];

echo implode(PHP_EOL, $lines) . PHP_EOL;
