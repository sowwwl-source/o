<?php

$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'test';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // En production, il vaut mieux logger l'erreur plutôt que de l'afficher
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = is_https_request();
    $params = session_get_cookie_params();

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'None',
    ]);

    ini_set('session.use_strict_mode', '1');
    session_start();
}

function is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])) {
        return strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on';
    }

    if (!empty($_SERVER['REQUEST_SCHEME'])) {
        return strtolower((string)$_SERVER['REQUEST_SCHEME']) === 'https';
    }

    return false;
}

function enforce_https(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    if (is_https_request()) {
        return;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    if ($host === '') {
        return;
    }

    $target = 'https://' . $host . $uri;
    header('Location: ' . $target, true, 301);
    exit;
}

function send_security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');

    if (is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }

    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; frame-src 'none'; object-src 'none'; manifest-src 'self'; img-src 'self' data: https://*.tile.openstreetmap.org; script-src 'self' https://unpkg.com; style-src 'self' https://unpkg.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; media-src 'self'; connect-src 'self'");
}

enforce_https();
send_security_headers();

function identity_auto_approve(): bool
{
    return getenv('IDENTITY_AUTO_APPROVE') === '1';
}

function identity_dev_mode(): bool
{
    return getenv('IDENTITY_DEV_MODE') === '1';
}

function generate_verification_code(int $length = 6): string
{
    $min = (int) pow(10, $length - 1);
    $max = (int) pow(10, $length) - 1;
    return (string) random_int($min, $max);
}

function hash_verification_code(string $code): string
{
    return password_hash($code, PASSWORD_DEFAULT);
}

function verify_verification_code(string $code, string $hash): bool
{
    return password_verify($code, $hash);
}

function send_sms_code(string $phone, string $code): bool
{
    $provider = getenv('IDENTITY_SMS_PROVIDER') ?: '';
    $apiKey = getenv('IDENTITY_SMS_API_KEY') ?: '';
    $from = getenv('IDENTITY_SMS_FROM') ?: '';

    if ($provider === '' || $apiKey === '' || $from === '') {
        return false;
    }

    if (strtolower($provider) !== 'twilio') {
        return false;
    }

    if (!function_exists('curl_init')) {
        return false;
    }

    $sid = getenv('IDENTITY_TWILIO_ACCOUNT_SID') ?: '';
    $token = getenv('IDENTITY_TWILIO_AUTH_TOKEN') ?: '';
    if ($sid === '' || $token === '') {
        return false;
    }

    $message = 'Votre code de validation O. : ' . $code;

    $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json');
    curl_setopt_array($ch, [
        CURLOPT_USERPWD => $sid . ':' . $token,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'To' => $phone,
            'From' => $from,
            'Body' => $message,
        ],
        CURLOPT_TIMEOUT => 20,
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        return false;
    }

    return $status >= 200 && $status < 300;
}

function send_postal_code(array $address, string $code): bool
{
    $provider = getenv('IDENTITY_POSTAL_PROVIDER') ?: '';
    $apiKey = getenv('IDENTITY_POSTAL_API_KEY') ?: '';

    if ($provider === '' || $apiKey === '') {
        return false;
    }

    if (strtolower($provider) !== 'lob') {
        return false;
    }

    if (!function_exists('curl_init')) {
        return false;
    }

    $from = [
        'name' => getenv('IDENTITY_POSTAL_FROM_NAME') ?: 'O',
        'address_line1' => getenv('IDENTITY_POSTAL_FROM_ADDRESS1') ?: '',
        'address_line2' => getenv('IDENTITY_POSTAL_FROM_ADDRESS2') ?: '',
        'address_city' => getenv('IDENTITY_POSTAL_FROM_CITY') ?: '',
        'address_state' => getenv('IDENTITY_POSTAL_FROM_STATE') ?: '',
        'address_zip' => getenv('IDENTITY_POSTAL_FROM_ZIP') ?: '',
        'address_country' => getenv('IDENTITY_POSTAL_FROM_COUNTRY') ?: 'US',
    ];

    if ($from['address_line1'] === '' || $from['address_city'] === '' || $from['address_country'] === '') {
        return false;
    }

    $to = [
        'name' => $address['name'] ?? 'Resident',
        'address_line1' => $address['line1'] ?? '',
        'address_line2' => $address['line2'] ?? '',
        'address_city' => $address['city'] ?? '',
        'address_state' => $address['region'] ?? '',
        'address_zip' => $address['postal_code'] ?? '',
        'address_country' => $address['country'] ?? '',
    ];

    if ($to['address_line1'] === '' || $to['address_city'] === '' || $to['address_country'] === '') {
        return false;
    }

    $html = '<html><body style="font-family:Arial,sans-serif;">'
        . '<h2>Code de validation</h2>'
        . '<p>Votre code postal de validation :</p>'
        . '<div style="font-size:24px;letter-spacing:4px;font-weight:bold;">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</div>'
        . '<p>Ce code expire dans 30 jours.</p>'
        . '</body></html>';

    $payload = [
        'description' => 'Identity verification code',
        'to[name]' => $to['name'],
        'to[address_line1]' => $to['address_line1'],
        'to[address_line2]' => $to['address_line2'],
        'to[address_city]' => $to['address_city'],
        'to[address_state]' => $to['address_state'],
        'to[address_zip]' => $to['address_zip'],
        'to[address_country]' => $to['address_country'],
        'from[name]' => $from['name'],
        'from[address_line1]' => $from['address_line1'],
        'from[address_line2]' => $from['address_line2'],
        'from[address_city]' => $from['address_city'],
        'from[address_state]' => $from['address_state'],
        'from[address_zip]' => $from['address_zip'],
        'from[address_country]' => $from['address_country'],
        'file' => $html,
        'color' => 'false',
    ];

    $ch = curl_init('https://api.lob.com/v1/letters');
    curl_setopt_array($ch, [
        CURLOPT_USERPWD => $apiKey . ':',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 25,
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        return false;
    }

    return $status >= 200 && $status < 300;
}

function aza_api_request(string $path, array $payload, string $method = 'POST'): array
{
    $base = rtrim(getenv('AZA_API_BASE_URL') ?: 'https://api.sowwwl.cloud', '/');
    $token = getenv('AZA_API_TOKEN') ?: '';

    if ($token === '') {
        return [
            'ok' => false,
            'status' => 0,
            'error' => 'AZA_API_TOKEN is not configured.',
        ];
    }

    $url = $base . $path;
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ];

    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout' => 20,
        ],
    ];

    $context = stream_context_create($options);
    $body = @file_get_contents($url, false, $context);

    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\\S+\\s+(\\d+)#', $http_response_header[0], $match)) {
        $status = (int)$match[1];
    }

    if ($body === false) {
        $error = error_get_last();
        return [
            'ok' => false,
            'status' => $status,
            'error' => $error['message'] ?? 'Request failed.',
        ];
    }

    $decoded = json_decode($body, true);

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $decoded ?? $body,
    ];
}

function aza_api_upload(string $file_path, string $file_name, string $user_id, ?string $workspace_id = null): array
{
    $base = rtrim(getenv('AZA_API_BASE_URL') ?: 'https://api.sowwwl.cloud', '/');
    $token = getenv('AZA_API_TOKEN') ?: '';

    if ($token === '') {
        return [
            'ok' => false,
            'status' => 0,
            'error' => 'AZA_API_TOKEN is not configured.',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'status' => 0,
            'error' => 'PHP cURL extension is not available.',
        ];
    }

    $mime = 'application/octet-stream';
    if (function_exists('mime_content_type')) {
        $detected = mime_content_type($file_path);
        if ($detected) {
            $mime = $detected;
        }
    }

    $url = $base . '/upload';
    $ch = curl_init($url);

    $post_fields = [
        'file' => new CURLFile($file_path, $mime, $file_name),
        'user_id' => $user_id,
    ];
    if ($workspace_id) {
        $post_fields['workspace_id'] = $workspace_id;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post_fields,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'ok' => false,
            'status' => $status,
            'error' => $error ?: 'Upload failed.',
        ];
    }

    curl_close($ch);
    $decoded = json_decode($body, true);

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $decoded ?? $body,
    ];
}
