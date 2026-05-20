<?php
declare(strict_types=1);

define('SOWWWL_SKIP_BOOTSTRAP_REQUEST', true);
require_once __DIR__ . '/config.php';

function aza_asset_fail(int $status): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $status === 403 ? 'Forbidden' : 'Not found';
    exit;
}

/**
 * @return array{start:int,end:int,length:int}|null
 */
function aza_asset_parse_range(?string $headerValue, int $fileSize): ?array
{
    $candidate = trim((string) $headerValue);
    if ($candidate === '' || $fileSize <= 0) {
        return null;
    }

    if (preg_match('/^bytes=(\d*)-(\d*)$/', $candidate, $matches) !== 1) {
        return null;
    }

    $startRaw = $matches[1] ?? '';
    $endRaw = $matches[2] ?? '';
    if ($startRaw === '' && $endRaw === '') {
        return null;
    }

    if ($startRaw === '') {
        $suffixLength = (int) $endRaw;
        if ($suffixLength <= 0) {
            return null;
        }

        $start = max($fileSize - min($suffixLength, $fileSize), 0);
        $end = $fileSize - 1;
    } else {
        $start = (int) $startRaw;
        $end = $endRaw !== '' ? (int) $endRaw : ($fileSize - 1);
        if ($start >= $fileSize || $start > $end) {
            http_response_code(416);
            header('Content-Range: bytes */' . $fileSize);
            exit;
        }

        $end = min($end, $fileSize - 1);
    }

    return [
        'start' => $start,
        'end' => $end,
        'length' => ($end - $start) + 1,
    ];
}

function aza_asset_should_display_inline(string $contentType, string $path): bool
{
    $normalizedType = strtolower(trim($contentType));
    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

    if (str_starts_with($normalizedType, 'image/')
        || str_starts_with($normalizedType, 'audio/')
        || str_starts_with($normalizedType, 'video/')
        || str_starts_with($normalizedType, 'text/')
    ) {
        return true;
    }

    if (in_array($normalizedType, ['application/pdf', 'application/json', 'application/xml', 'image/svg+xml', 'model/gltf+json', 'model/gltf-binary'], true)) {
        return true;
    }

    return in_array($extension, ['svg', 'pdf', 'json', 'csv', 'xml', 'yaml', 'yml', 'tsv', 'gltf', 'glb'], true);
}

$publicPath = trim((string) ($_GET['f'] ?? ''));
if ($publicPath === '') {
    aza_asset_fail(404);
}

$normalized = ltrim(str_replace('\\', '/', $publicPath), '/');
$storageRoot = aza_public_storage_root();
$storagePrefix = $storageRoot . '/';
if ($normalized !== $storageRoot && !str_starts_with($normalized, $storagePrefix)) {
    aza_asset_fail(404);
}

$absolutePath = aza_absolute_storage_path($normalized);
if (!is_string($absolutePath) || !is_file($absolutePath)) {
    aza_asset_fail(404);
}

$realPath = realpath($absolutePath);
$storageRealPath = realpath(AZA_STORAGE_DIR);
if (!is_string($realPath) || !is_string($storageRealPath)) {
    aza_asset_fail(404);
}

$storagePrefixPath = rtrim($storageRealPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if ($realPath !== $storageRealPath && !str_starts_with($realPath, $storagePrefixPath)) {
    aza_asset_fail(403);
}

$contentType = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $detected = @finfo_file($finfo, $realPath);
        if (is_string($detected) && trim($detected) !== '') {
            $contentType = $detected;
        }
        @finfo_close($finfo);
    }
}

$downloadName = basename($realPath);
$asciiName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) ?: 'telechargement';
$fileSize = @filesize($realPath);
$range = is_int($fileSize) && $fileSize >= 0
    ? aza_asset_parse_range($_SERVER['HTTP_RANGE'] ?? null, $fileSize)
    : null;
$forceDownload = trim((string) ($_GET['download'] ?? '')) === '1';
$dispositionType = !$forceDownload && aza_asset_should_display_inline($contentType, $realPath) ? 'inline' : 'attachment';

header('Cache-Control: private, max-age=600');
header('Pragma: public');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: bytes');
header('Content-Type: ' . $contentType);
header('Content-Disposition: ' . $dispositionType . '; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
if ($range !== null) {
    http_response_code(206);
    header('Content-Range: bytes ' . $range['start'] . '-' . $range['end'] . '/' . $fileSize);
    header('Content-Length: ' . (string) $range['length']);
} elseif (is_int($fileSize) && $fileSize >= 0) {
    header('Content-Length: ' . (string) $fileSize);
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD') {
    exit;
}

$handle = @fopen($realPath, 'rb');
if (!$handle) {
    aza_asset_fail(404);
}

if ($range !== null) {
    fseek($handle, $range['start']);
    $remaining = $range['length'];
    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min(8192, $remaining));
        if ($chunk === false || $chunk === '') {
            break;
        }
        $remaining -= strlen($chunk);
        echo $chunk;
    }
} else {
    while (!feof($handle)) {
        $chunk = fread($handle, 8192);
        if ($chunk === false) {
            break;
        }
        echo $chunk;
    }
}

fclose($handle);
exit;
