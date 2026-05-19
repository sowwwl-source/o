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

header('Cache-Control: private, max-age=600');
header('Pragma: public');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
if (is_int($fileSize) && $fileSize >= 0) {
    header('Content-Length: ' . (string) $fileSize);
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD') {
    exit;
}

$handle = @fopen($realPath, 'rb');
if (!$handle) {
    aza_asset_fail(404);
}

while (!feof($handle)) {
    $chunk = fread($handle, 8192);
    if ($chunk === false) {
        break;
    }
    echo $chunk;
}

fclose($handle);
exit;
