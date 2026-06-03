<?php
declare(strict_types=1);

define('SOWWWL_SKIP_BOOTSTRAP_REQUEST', true);
require_once __DIR__ . '/config.php';

header_remove('X-Powered-By');
header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=900');
header('X-Robots-Tag: noindex, follow');

$host = request_host();
$resolvedHost = preg_replace('/^www\./', '', $host);
$surfaceVariant = current_surface_variant($host);
$origin = request_public_origin($host);

$entries = [];
$append = static function (string $loc, string $filePath, string $changefreq, string $priority) use (&$entries): void {
    $lastmodTs = @filemtime($filePath) ?: time();
    $entries[] = [
        'loc' => $loc,
        'lastmod' => gmdate('c', $lastmodTs),
        'changefreq' => $changefreq,
        'priority' => $priority,
    ];
};

if ($resolvedHost === '0wlslw0.com') {
    $append(guide_owner_origin() . '/', __DIR__ . '/0wlslw0.php', 'daily', '0.9');
} elseif ($surfaceVariant === 'io') {
    $append($origin . o_route_href('/', [], $host), __DIR__ . '/index.php', 'daily', '1.0');
    $append($origin . o_route_href('/str3m', [], $host), __DIR__ . '/str3m.php', 'daily', '0.9');
    $append($origin . o_route_href('/map', [], $host), __DIR__ . '/map.php', 'weekly', '0.7');
    $append($origin . o_route_href('/0wlslw0', [], $host), __DIR__ . '/0wlslw0.php', 'weekly', '0.8');
} else {
    $append($origin . o_route_href('/', [], $host), __DIR__ . '/index.php', 'daily', '1.0');
    $append($origin . o_route_href('/str3m', [], $host), __DIR__ . '/str3m.php', 'daily', '0.9');
    $append($origin . o_route_href('/signal', [], $host), __DIR__ . '/signal.php', 'weekly', '0.8');
    $append($origin . o_route_href('/aza', [], $host), __DIR__ . '/aza.php', 'weekly', '0.7');
    $append($origin . o_route_href('/map', [], $host), __DIR__ . '/map.php', 'weekly', '0.7');
    $append($origin . o_route_href('/rejoindre', [], $host), __DIR__ . '/rejoindre.php', 'weekly', '0.8');
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ($entries as $entry) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars((string) $entry['loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</loc>\n";
    echo '    <lastmod>' . htmlspecialchars((string) $entry['lastmod'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</lastmod>\n";
    echo '    <changefreq>' . htmlspecialchars((string) $entry['changefreq'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</changefreq>\n";
    echo '    <priority>' . htmlspecialchars((string) $entry['priority'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</priority>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
