<?php
declare(strict_types=1);

function load_dotenv(string $path): void
{
    if (!is_readable($path) || is_dir($path)) {
        return;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || (isset($line[0]) && $line[0] === '#')) {
            continue;
        }

        if (strncmp($line, 'export ', 7) === 0) {
            $line = trim(substr($line, 7));
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $key = trim(substr($line, 0, $separator));
        if ($key === '' || !preg_match('/^[A-Z0-9_]+$/i', $key)) {
            continue;
        }

        if (getenv($key) !== false) {
            continue;
        }

        $value = trim(substr($line, $separator + 1));
        if ($value !== '') {
            $quote = $value[0];
            if (($quote === '"' || $quote === "'") && strlen($value) >= 2 && substr($value, -1) === $quote) {
                $value = substr($value, 1, -1);
            }
        }

        if (function_exists('putenv')) {
            @putenv($key . '=' . $value);
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

load_dotenv(dirname(__DIR__) . '/.env');
load_dotenv(__DIR__ . '/.env');

const SITE_DOMAIN = 'sowwwl.xyz';
const SITE_TAGLINE = 'Just the Three of Us';
const DEFAULT_TIMEZONE = 'Europe/Paris';
const CREATE_LAND_RATE_LIMIT_MAX_ATTEMPTS = 6;
const CREATE_LAND_RATE_LIMIT_WINDOW_SECONDS = 600;
const DEFAULT_AZA_MAX_UPLOAD_BYTES = 2147483648;

$publicOriginOverride = trim((string) (getenv('SOWWWL_PUBLIC_ORIGIN') ?: ''));
$rateLimitOverride = trim((string) (getenv('SOWWWL_RATE_LIMIT_DIR') ?: ''));
$storageOverride = trim((string) (getenv('SOWWWL_STORAGE_DIR') ?: ''));
$azaUploadOverride = trim((string) (getenv('SOWWWL_AZA_MAX_UPLOAD_BYTES') ?: ''));
$azaDirectOriginOverride = trim((string) (getenv('SOWWWL_AZA_DIRECT_ORIGIN') ?: ''));
define(
    'SITE_ORIGIN',
    $publicOriginOverride !== ''
        ? rtrim($publicOriginOverride, '/')
        : 'https://' . SITE_DOMAIN
);
define(
    'LANDS_DIR',
    $storageOverride !== ''
        ? rtrim($storageOverride, DIRECTORY_SEPARATOR)
        : __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'lands'
);

define(
    'RATE_LIMIT_DIR',
    $rateLimitOverride !== ''
        ? rtrim($rateLimitOverride, DIRECTORY_SEPARATOR)
        : __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'rate-limit'
);

define(
    'AZA_STORAGE_DIR',
    __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'aza'
);

define(
    'AZA_MAX_UPLOAD_BYTES',
    ctype_digit($azaUploadOverride)
        ? max(16 * 1024 * 1024, (int) $azaUploadOverride)
        : DEFAULT_AZA_MAX_UPLOAD_BYTES
);

define(
    'AZA_DIRECT_ORIGIN',
    $azaDirectOriginOverride !== ''
        ? rtrim($azaDirectOriginOverride, '/')
        : ''
);

require_once __DIR__ . '/lib/lands.php';
require_once __DIR__ . '/lib/aza_archive.php';
require_once __DIR__ . '/lib/security.php';

bootstrap_request();
