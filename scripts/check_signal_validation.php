#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/signal_mail.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$options = getopt('', ['slug:', 'json']);
$slug = trim((string) ($options['slug'] ?? ''));
$asJson = array_key_exists('json', $options);

$payload = [
    'generated_at' => gmdate(DATE_ATOM),
    'schema' => signal_mail_schema_status(),
    'delivery' => signal_identity_delivery_status(),
    'delivery_hint' => signal_identity_delivery_status_hint(),
];

if ($slug !== '') {
    try {
        $land = find_land($slug);
    } catch (Throwable $exception) {
        $land = null;
    }

    if ($land === null) {
        $payload['land'] = [
            'slug' => $slug,
            'found' => false,
        ];
    } else {
        $mailbox = [];
        if (($payload['schema']['ready'] ?? false) === true) {
            $mailbox = signal_mailbox_for_land($land);
        }

        $payload['land'] = [
            'slug' => (string) ($land['slug'] ?? $slug),
            'username' => (string) ($land['username'] ?? ''),
            'found' => true,
            'virtual_address' => signal_virtual_address($land),
            'mailbox' => [
                'identity_status' => (string) ($mailbox['identity_status'] ?? ''),
                'notification_email' => (string) ($mailbox['notification_email'] ?? ''),
                'verification_token_sent_at' => (string) ($mailbox['verification_token_sent_at'] ?? ''),
                'verified_at' => (string) ($mailbox['verified_at'] ?? ''),
            ],
            'identity_hint' => $mailbox !== [] ? signal_identity_status_hint($mailbox) : '',
        ];
    }
}

if ($asJson) {
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

$schema = $payload['schema'];
$delivery = $payload['delivery'];

echo "Signal validation status\n";
echo "========================\n\n";

echo "Schema\n";
echo "------\n";
printf("database available : %s\n", ($schema['database_available'] ?? false) ? 'yes' : 'no');
printf("signal_mailboxes  : %s\n", ($schema['signal_mailboxes'] ?? false) ? 'yes' : 'no');
printf("signal_messages   : %s\n", ($schema['signal_messages'] ?? false) ? 'yes' : 'no');
printf("lands.email col   : %s\n", ($schema['lands_notification_email'] ?? false) ? 'yes' : 'no');
printf("schema ready      : %s\n", ($schema['ready'] ?? false) ? 'yes' : 'no');
if (!empty($schema['issues'])) {
    echo 'issues            : ' . implode(', ', array_map('strval', (array) $schema['issues'])) . "\n";
}

echo "\nDelivery\n";
echo "--------\n";
printf("mode              : %s\n", (string) ($delivery['mode'] ?? 'mail'));
printf("delivery ready    : %s\n", ($delivery['ready'] ?? false) ? 'yes' : 'no');
echo 'hint              : ' . (string) ($payload['delivery_hint'] ?? '') . "\n";
if (!empty($delivery['issues'])) {
    echo 'issues            : ' . implode(', ', array_map('strval', (array) $delivery['issues'])) . "\n";
}

if (isset($payload['land'])) {
    echo "\nLand\n";
    echo "----\n";
    $land = $payload['land'];
    printf("found             : %s\n", ($land['found'] ?? false) ? 'yes' : 'no');
    printf("slug              : %s\n", (string) ($land['slug'] ?? ''));
    if (($land['found'] ?? false) === true) {
        printf("username          : %s\n", (string) ($land['username'] ?? ''));
        printf("virtual address   : %s\n", (string) ($land['virtual_address'] ?? ''));
        $mailbox = (array) ($land['mailbox'] ?? []);
        printf("identity status   : %s\n", (string) ($mailbox['identity_status'] ?? ''));
        printf("notif email       : %s\n", (string) ($mailbox['notification_email'] ?? ''));
        printf("token sent at     : %s\n", (string) ($mailbox['verification_token_sent_at'] ?? ''));
        printf("verified at       : %s\n", (string) ($mailbox['verified_at'] ?? ''));
        echo 'identity hint     : ' . (string) ($land['identity_hint'] ?? '') . "\n";
    }
}
