<?php
// Read the upload token from an environment variable for better security.
$token = getenv('AZA_UPLOAD_TOKEN');

if ($token === false) {
    // Fallback or error handling if the environment variable is not set.
    // In a production environment, you should log this error and prevent uploads.
    error_log('CRITICAL: AZA_UPLOAD_TOKEN environment variable is not set.');
    // Using a default non-functional token to prevent accidental uploads if misconfigured.
    $UPLOAD_TOKEN = 'CONFIGURATION_ERROR_TOKEN_NOT_SET';
} else {
    $UPLOAD_TOKEN = $token;
}
