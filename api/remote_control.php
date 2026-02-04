<?php
// A simple remote control to enable or disable the 'Sarzac' trap files.
// WARNING: The password '0' is not secure. It is strongly recommended to change this to a long, random string.
$secret_password = '0';

// --- VALIDATION ---
// Check if the secret password is provided and correct
if (!isset($_GET['secret']) || $_GET['secret'] !== $secret_password) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid or missing secret.']);
    exit;
}

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
