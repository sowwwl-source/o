#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/guide_voice.php';

function check_0wlslw0_usage(): string
{
    return <<<TXT
Usage:
  php scripts/check_0wlslw0_agent.php [--json] [--require-configured] [--require-remote-ok] [--utterance "Guide-moi vers Str3m."]

What this script checks:
  - whether the 0wlslw0 upstream relay is configured
  - whether the remote endpoint can answer a real probe
  - whether the reply is actually parseable by the live app

Exit codes:
  0  probe or configuration state is acceptable for the chosen flags
  1  the required configuration or remote probe is not ready
TXT;
}

$options = getopt('', [
    'json',
    'require-configured',
    'require-remote-ok',
    'utterance:',
    'help',
]);

if (array_key_exists('help', $options)) {
    fwrite(STDOUT, check_0wlslw0_usage() . PHP_EOL);
    exit(0);
}

$utterance = trim((string) ($options['utterance'] ?? 'Guide-moi vers Str3m.'));
$requireConfigured = array_key_exists('require-configured', $options) || array_key_exists('require-remote-ok', $options);
$requireRemoteOk = array_key_exists('require-remote-ok', $options);
$probe = guide_voice_probe_upstream($utterance);

if (array_key_exists('json', $options)) {
    fwrite(STDOUT, json_encode($probe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
} else {
    $statusText = !empty($probe['ok']) ? 'yes' : 'no';
    $configuredText = !empty($probe['configured']) ? 'yes' : 'no';
    $authText = !empty($probe['auth_present']) ? 'yes' : 'no';
    $endpointHost = trim((string) ($probe['endpoint_host'] ?? '')) ?: 'none';
    $requestMode = trim((string) ($probe['request_mode'] ?? 'chat')) ?: 'chat';
    $inputField = trim((string) ($probe['input_field'] ?? 'message')) ?: 'message';
    $timeout = (int) ($probe['timeout'] ?? 18);
    $statusCode = (int) ($probe['status'] ?? 0);

    fwrite(STDOUT, "0wlslw0 upstream state : " . (string) ($probe['state'] ?? 'unknown') . " (" . (string) ($probe['label'] ?? 'unknown') . ")\n");
    fwrite(STDOUT, "configured            : {$configuredText}\n");
    fwrite(STDOUT, "auth present          : {$authText}\n");
    fwrite(STDOUT, "endpoint host         : {$endpointHost}\n");
    fwrite(STDOUT, "request mode          : {$requestMode}\n");
    fwrite(STDOUT, "input field           : {$inputField}\n");
    fwrite(STDOUT, "timeout               : {$timeout}s\n");
    fwrite(STDOUT, "probe ok              : {$statusText}\n");

    if ($statusCode > 0) {
        fwrite(STDOUT, "http status           : {$statusCode}\n");
    }

    $error = trim((string) ($probe['error'] ?? ''));
    if ($error !== '') {
        fwrite(STDOUT, "error                 : {$error}\n");
    }

    $replyExcerpt = trim((string) ($probe['reply_excerpt'] ?? ''));
    if ($replyExcerpt !== '') {
        fwrite(STDOUT, "reply excerpt         : {$replyExcerpt}\n");
    }

    $routeHref = trim((string) (($probe['route']['href'] ?? '')));
    if ($routeHref !== '') {
        fwrite(STDOUT, "route                 : {$routeHref}\n");
    }
}

$exitCode = 0;
if ($requireConfigured && empty($probe['configured'])) {
    $exitCode = 1;
}
if ($requireRemoteOk && empty($probe['ok'])) {
    $exitCode = 1;
}

exit($exitCode);
