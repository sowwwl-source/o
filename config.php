<?php
declare(strict_types=1);

const SITE_DOMAIN = 'sowwwl.xyz';
const SITE_TAGLINE = 'Just the Three of Us';
const DEFAULT_TIMEZONE = 'Europe/Paris';

$storageOverride = trim((string) (getenv('SOWWWL_STORAGE_DIR') ?: ''));
define(
    'LANDS_DIR',
    $storageOverride !== ''
        ? rtrim($storageOverride, DIRECTORY_SEPARATOR)
        : __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'lands'
);

require_once __DIR__ . '/lib/lands.php';
