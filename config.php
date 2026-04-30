<?php

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

        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }

        $key = trim(substr($line, 0, $eq));
        if ($key === '') {
            continue;
        }

        // Only accept typical ENV keys (avoid weird injections).
        if (!preg_match('/^[A-Z0-9_]+$/i', $key)) {
            continue;
        }

        // Do not override values coming from the server / Docker.
        if (getenv($key) !== false) {
            continue;
        }

        $value = trim(substr($line, $eq + 1));
        if ($value === '') {
            $value = '';
        } else {
            $q = $value[0];
            if (($q === '"' || $q === "'") && strlen($value) >= 2 && substr($value, -1) === $q) {
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

load_dotenv(__DIR__ . '/.env');

function asset_url(string $path): string
{
    $path = '/' . ltrim($path, '/');

    $qpos = strpos($path, '?');
    $clean = $qpos === false ? $path : substr($path, 0, $qpos);

    $fs_path = __DIR__ . $clean;
    if (!is_file($fs_path)) {
        return $path;
    }

    $mtime = @filemtime($fs_path);
    if (!$mtime) {
        return $path;
    }

    $sep = $qpos === false ? '?' : '&';
    return $path . $sep . 'v=' . $mtime;
}

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

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443');
    $params = session_get_cookie_params();

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    session_start();

    // Auto-login to remove mandatory login
    if (!isset($_SESSION['username'])) {
        global $pdo;
        try {
            $stmt = $pdo->query("SELECT username FROM lands ORDER BY id ASC LIMIT 1");
            $first_user = $stmt->fetchColumn();
            if (!$first_user) {
                // Création d'un premier utilisateur par défaut pour que l'app soit ouverte
                $pdo->exec("INSERT INTO lands (username, password_hash, email_virtual, timezone, zone_code, shore_text) VALUES ('visiteur', '', 'visiteur@o.local', 'Europe/Paris', 'Europe/Paris', 'Silence.')");
                $first_user = 'visiteur';
            }
            if ($first_user) {
                $_SESSION['username'] = $first_user;
            }
        } catch (\Exception $e) {}
    }
}

function send_echo_notification(string $to_email, string $sender_username, string $receiver_username): void
{
    if ($to_email === '' || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $domain = getenv('SOWWWL_PUBLIC_ORIGIN') ?: 'https://sowwwl.com';
    $link = rtrim($domain, '/') . '/echo.php?u=' . rawurlencode($sender_username);
    $subject = '=?UTF-8?B?' . base64_encode('Nouvel écho de ' . $sender_username . ' sur O.') . '?=';
    $body = "Tu as reçu un écho de {$sender_username}.\n\nLire et répondre :\n{$link}\n\n—\nO. réseau minimal";
    $headers = implode("\r\n", [
        'From: noreply@sowwwl.com',
        'Reply-To: noreply@sowwwl.com',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: O.',
    ]);
    @mail($to_email, $subject, $body, $headers);
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

// ─── DO Spaces (AWS Sig V4) ───────────────────────────────────────────────────

function spaces_upload(string $local_path, string $remote_key): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'curl not available'];
    }

    $access_key = getenv('DO_SPACES_KEY')    ?: '';
    $secret_key = getenv('DO_SPACES_SECRET') ?: '';
    $bucket     = getenv('DO_SPACES_BUCKET') ?: 'sc34u-x';
    $region     = getenv('DO_SPACES_REGION') ?: 'ams3';

    if ($access_key === '' || $secret_key === '') {
        return ['ok' => false, 'error' => 'DO_SPACES_KEY / DO_SPACES_SECRET not configured'];
    }

    $host         = "{$bucket}.{$region}.digitaloceanspaces.com";
    $url          = "https://{$host}/{$remote_key}";
    $size         = (int)filesize($local_path);
    $datetime     = gmdate('Ymd\THis\Z');
    $date         = substr($datetime, 0, 8);
    $payload_hash = hash_file('sha256', $local_path);

    // Signed headers — sorted alphabetically
    $signed = [
        'content-type'         => 'application/zip',
        'host'                 => $host,
        'x-amz-acl'            => 'public-read',
        'x-amz-content-sha256' => $payload_hash,
        'x-amz-date'           => $datetime,
    ];
    ksort($signed);

    $headers_canon  = '';
    $signed_headers = '';
    foreach ($signed as $k => $v) {
        $headers_canon  .= "{$k}:{$v}\n";
        $signed_headers .= ($signed_headers ? ';' : '') . $k;
    }

    $canonical = implode("\n", [
        'PUT',
        '/' . $remote_key,
        '',
        $headers_canon,
        $signed_headers,
        $payload_hash,
    ]);

    $scope          = "{$date}/{$region}/s3/aws4_request";
    $string_to_sign = implode("\n", [
        'AWS4-HMAC-SHA256',
        $datetime,
        $scope,
        hash('sha256', $canonical),
    ]);

    $k_date    = hash_hmac('sha256', $date,          'AWS4' . $secret_key, true);
    $k_region  = hash_hmac('sha256', $region,        $k_date,              true);
    $k_service = hash_hmac('sha256', 's3',           $k_region,            true);
    $k_signing = hash_hmac('sha256', 'aws4_request', $k_service,           true);
    $signature = hash_hmac('sha256', $string_to_sign, $k_signing);

    $auth = "AWS4-HMAC-SHA256 Credential={$access_key}/{$scope}, SignedHeaders={$signed_headers}, Signature={$signature}";

    $fh = fopen($local_path, 'rb');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_PUT            => true,
        CURLOPT_INFILE         => $fh,
        CURLOPT_INFILESIZE     => $size,
        CURLOPT_HTTPHEADER     => [
            "Authorization: {$auth}",
            "Content-Type: application/zip",
            "x-amz-acl: public-read",
            "x-amz-content-sha256: {$payload_hash}",
            "x-amz-date: {$datetime}",
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
    ]);

    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fh);

    if ($status >= 200 && $status < 300) {
        return ['ok' => true, 'url' => $url, 'key' => $remote_key];
    }

    return ['ok' => false, 'status' => $status, 'error' => (string)$body];
}

function spaces_url(string $key): string
{
    $bucket = getenv('DO_SPACES_BUCKET') ?: 'sc34u-x';
    $region = getenv('DO_SPACES_REGION') ?: 'ams3';
    return "https://{$bucket}.{$region}.digitaloceanspaces.com/{$key}";
}
