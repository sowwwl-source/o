<?php
declare(strict_types=1);

if (!function_exists('o_request_method')) {
    function o_request_method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }
}

if (!function_exists('o_allow_methods')) {
    function o_allow_methods(array $methods): void
    {
        header('Allow: ' . implode(', ', $methods));
    }
}

if (!function_exists('o_json_response')) {
    /**
     * @param array{
     *   cache_control?: string,
     *   origin?: ?string,
     *   cors_methods?: string[],
     *   cors_headers?: string[],
     *   cors_max_age?: int,
     *   vary_origin?: bool,
     *   pretty?: bool,
     *   extra_headers?: string[]
     * } $options
     */
    function o_json_response(int $status, array $payload, array $options = []): never
    {
        $cacheControl = array_key_exists('cache_control', $options)
            ? (string) $options['cache_control']
            : 'no-store';
        $origin = isset($options['origin']) ? (string) $options['origin'] : null;
        $corsMethods = isset($options['cors_methods']) && is_array($options['cors_methods'])
            ? array_values($options['cors_methods'])
            : [];
        $corsHeaders = isset($options['cors_headers']) && is_array($options['cors_headers'])
            ? array_values($options['cors_headers'])
            : ['Content-Type'];
        $corsMaxAge = isset($options['cors_max_age']) ? max(0, (int) $options['cors_max_age']) : 600;
        $varyOrigin = !array_key_exists('vary_origin', $options) || (bool) $options['vary_origin'];
        $pretty = (bool) ($options['pretty'] ?? false);
        $extraHeaders = isset($options['extra_headers']) && is_array($options['extra_headers'])
            ? array_values($options['extra_headers'])
            : [];

        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        if ($cacheControl !== '') {
            header('Cache-Control: ' . $cacheControl);
        }

        if ($origin !== null) {
            header('Access-Control-Allow-Origin: ' . $origin);
            if ($corsMethods !== []) {
                header('Access-Control-Allow-Methods: ' . implode(', ', $corsMethods));
            }
            if ($corsHeaders !== []) {
                header('Access-Control-Allow-Headers: ' . implode(', ', $corsHeaders));
            }
            if ($corsMaxAge > 0) {
                header('Access-Control-Max-Age: ' . (string) $corsMaxAge);
            }
            if ($varyOrigin) {
                header('Vary: Origin');
            }
        }

        foreach ($extraHeaders as $headerLine) {
            if (is_string($headerLine) && $headerLine !== '') {
                header($headerLine);
            }
        }

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        echo json_encode($payload, $flags);
        exit;
    }
}
