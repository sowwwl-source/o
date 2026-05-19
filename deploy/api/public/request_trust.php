<?php
declare(strict_types=1);

function sowwwl_trusted_proxy_cidrs(): array
{
    $configured = trim((string) (getenv('SOWWWL_TRUSTED_PROXY_CIDRS') ?: ''));
    if ($configured !== '') {
        return array_values(array_filter(
            preg_split('/[\s,]+/', $configured) ?: [],
            static fn ($value): bool => trim((string) $value) !== ''
        ));
    }

    return [
        '127.0.0.1/32',
        '::1/128',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        'fc00::/7',
        'fe80::/10',
    ];
}

function sowwwl_is_valid_ip(string $value): bool
{
    return filter_var($value, FILTER_VALIDATE_IP) !== false;
}

function sowwwl_ip_in_cidr(string $ip, string $cidr): bool
{
    $ip = trim($ip);
    $cidr = trim($cidr);
    if ($ip === '' || $cidr === '' || !sowwwl_is_valid_ip($ip)) {
        return false;
    }

    if (!str_contains($cidr, '/')) {
        return sowwwl_is_valid_ip($cidr) && @inet_pton($ip) === @inet_pton($cidr);
    }

    [$network, $prefixLength] = explode('/', $cidr, 2);
    $network = trim($network);
    $prefixLength = trim($prefixLength);
    if (!sowwwl_is_valid_ip($network) || !ctype_digit($prefixLength)) {
        return false;
    }

    $ipBinary = @inet_pton($ip);
    $networkBinary = @inet_pton($network);
    if (!is_string($ipBinary) || !is_string($networkBinary) || strlen($ipBinary) !== strlen($networkBinary)) {
        return false;
    }

    $bits = (int) $prefixLength;
    $maxBits = strlen($ipBinary) * 8;
    if ($bits < 0 || $bits > $maxBits) {
        return false;
    }

    $fullBytes = intdiv($bits, 8);
    if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($networkBinary, 0, $fullBytes)) {
        return false;
    }

    $remainingBits = $bits % 8;
    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
    return (ord($ipBinary[$fullBytes]) & $mask) === (ord($networkBinary[$fullBytes]) & $mask);
}

function sowwwl_remote_addr(?array $server = null): string
{
    $server = is_array($server) ? $server : $_SERVER;
    $remoteAddr = trim((string) ($server['REMOTE_ADDR'] ?? ''));
    return sowwwl_is_valid_ip($remoteAddr) ? $remoteAddr : '';
}

function sowwwl_is_trusted_proxy(?string $remoteAddr = null, ?array $server = null): bool
{
    $candidate = trim((string) ($remoteAddr ?? sowwwl_remote_addr($server)));
    if ($candidate === '') {
        return false;
    }

    foreach (sowwwl_trusted_proxy_cidrs() as $cidr) {
        if (sowwwl_ip_in_cidr($candidate, (string) $cidr)) {
            return true;
        }
    }

    return false;
}

function sowwwl_effective_request_scheme(?array $server = null): string
{
    $server = is_array($server) ? $server : $_SERVER;

    if (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') {
        return 'https';
    }

    $remoteAddr = sowwwl_remote_addr($server);
    if ($remoteAddr !== '' && sowwwl_is_trusted_proxy($remoteAddr, $server)) {
        $forwardedProto = strtolower(trim((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($forwardedProto !== '') {
            $parts = preg_split('/\s*,\s*/', $forwardedProto) ?: [];
            $candidate = strtolower(trim((string) ($parts[0] ?? $forwardedProto)));
            if ($candidate === 'https' || $candidate === 'http') {
                return $candidate;
            }
        }

        $cfVisitor = (string) ($server['HTTP_CF_VISITOR'] ?? '');
        if (str_contains($cfVisitor, '"scheme":"https"')) {
            return 'https';
        }
    }

    return 'http';
}

function sowwwl_request_is_secure(?array $server = null): bool
{
    return sowwwl_effective_request_scheme($server) === 'https';
}
