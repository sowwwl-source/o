
<?php
// A simple remote control to enable or disable the 'Sarzac' trap files.
// WARNING: The password '0' is not secure. It is strongly recommended to change this to a long, random string.
$secret_password = '0';

// --- ANTI-BRUTEFORCE ---
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_limit_file = __DIR__ . '/../logs/remote_control_rate_limit.json';
$rate_limit_max = 5; // max tentatives par 10 minutes
$rate_limit_window = 600; // 10 minutes en secondes

// Charger l'état du rate limit
if (file_exists($rate_limit_file)) {
    $rate_data = json_decode(file_get_contents($rate_limit_file), true) ?: [];
} else {
    $rate_data = [];
}
$now = time();
if (!isset($rate_data[$ip])) {
    $rate_data[$ip] = [];
}
// Nettoyer les anciennes tentatives
$rate_data[$ip] = array_filter($rate_data[$ip], function($ts) use ($now, $rate_limit_window) {
    return $ts > $now - $rate_limit_window;
});
if (count($rate_data[$ip]) >= $rate_limit_max) {
    $log = sprintf("[%s] BLOCKED remote_control (antibruteforce) from %s | UA: %s\n", date('c'), $ip, $_SERVER['HTTP_USER_AGENT'] ?? '-');
    file_put_contents(__DIR__ . '/../logs/security.log', $log, FILE_APPEND);
    // Alerte mail
    @mail('admin@sowwwl.com', '[ALERTE] Remote control BLOQUE', $log);
    header('HTTP/1.1 429 Too Many Requests');
    echo json_encode(['status' => 'error', 'message' => 'Trop de tentatives. Réessayez plus tard.']);
    exit;
}

// --- VALIDATION ---
if (!isset($_GET['secret']) || $_GET['secret'] !== $secret_password) {
    // Log accès refusé
    $log = sprintf("[%s] REFUSED remote_control from %s | UA: %s | secret: %s\n", date('c'), $ip, $_SERVER['HTTP_USER_AGENT'] ?? '-', $_GET['secret'] ?? '-');
    file_put_contents(__DIR__ . '/../logs/security.log', $log, FILE_APPEND);
    // Alerte mail
    @mail('admin@sowwwl.com', '[ALERTE] Remote control REFUSE', $log);
    // Ajouter la tentative au rate limit
    $rate_data[$ip][] = $now;
    file_put_contents($rate_limit_file, json_encode($rate_data));
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid or missing secret.']);
    exit;
}
// Réinitialiser le compteur si succès
$rate_data[$ip] = [];
file_put_contents($rate_limit_file, json_encode($rate_data));

// Journaliser chaque usage
$log = sprintf("[%s] USED remote_control from %s | UA: %s | action: %s\n", date('c'), $ip, $_SERVER['HTTP_USER_AGENT'] ?? '-', $_GET['action'] ?? '-');
file_put_contents(__DIR__ . '/../logs/security.log', $log, FILE_APPEND);
@mail('admin@sowwwl.com', '[INFO] Remote control UTILISE', $log);

// Check if an action is specified
if (!isset($_GET['action']) || !in_array($_GET['action'], ['enable', 'disable'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'message' => 'Bad Request: Missing or invalid action. Use "enable" or "disable".']);
    exit;
}

// --- FILE PATHS ---
// These files are in the parent directory relative to this API script.
$css_file = __DIR__ . '/../sarzac.css';
$js_file = __DIR__ . '/../sarzac.js';

$css_file_disabled = $css_file . '.disabled';
$js_file_disabled = $js_file . '.disabled';

$action = $_GET['action'];
$results = [];

// --- ACTION LOGIC ---
header('Content-Type: application/json');

if ($action === 'disable') {
    // --- DISABLE ACTION: Rename .css -> .css.disabled and .js -> .js.disabled ---
    $results['action'] = 'disable';

    // Disable CSS
    if (file_exists($css_file) && !file_exists($css_file_disabled)) {
        if (rename($css_file, $css_file_disabled)) {
            $results['css_status'] = 'disabled';
        } else {
            $results['css_status'] = 'error: could not rename';
        }
    } else {
        $results['css_status'] = 'already disabled or not found';
    }

    // Disable JS
    if (file_exists($js_file) && !file_exists($js_file_disabled)) {
        if (rename($js_file, $js_file_disabled)) {
            $results['js_status'] = 'disabled';
        } else {
            $results['js_status'] = 'error: could not rename';
        }
    } else {
        $results['js_status'] = 'already disabled or not found';
    }

} elseif ($action === 'enable') {
    // --- ENABLE ACTION: Rename .css.disabled -> .css and .js.disabled -> .js ---
    $results['action'] = 'enable';

    // Enable CSS
    if (file_exists($css_file_disabled) && !file_exists($css_file)) {
        if (rename($css_file_disabled, $css_file)) {
            $results['css_status'] = 'enabled';
        } else {
            $results['css_status'] = 'error: could not rename';
        }
    } else {
        $results['css_status'] = 'already enabled or not found';
    }

    // Enable JS
    if (file_exists($js_file_disabled) && !file_exists($js_file)) {
        if (rename($js_file_disabled, $js_file)) {
            $results['js_status'] = 'enabled';
        } else {
            $results['js_status'] = 'error: could not rename';
        }
    } else {
        $results['js_status'] = 'already enabled or not found';
    }
}

echo json_encode($results);

?>
