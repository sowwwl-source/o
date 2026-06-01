#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';

try {
    if (!plasma_sqlite_available()) {
        throw new RuntimeException('SQLite driver unavailable for plasma buffer.');
    }

    $pdo = plasma_connection();
    $before = plasma_database_event_count($pdo);
    $imported = plasma_import_legacy_log($pdo);
    $after = plasma_database_event_count($pdo);

    $result = [
        'ok' => true,
        'db_path' => plasma_db_path(),
        'fallback_path' => plasma_event_log_path(),
        'before' => $before,
        'imported' => $imported,
        'after' => $after,
        'mode' => $imported > 0 ? 'backfilled' : 'noop',
    ];

    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}
