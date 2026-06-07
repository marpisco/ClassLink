<?php

function trusted_referer_path(?string $referer, string $fallback = '/', ?string $currentHost = null): string {
    $safeFallback = trusted_local_path($fallback) ?? '/';
    if ($referer === null || trim($referer) === '') {
        return $safeFallback;
    }

    $referer = trim($referer);
    $localPath = trusted_local_path($referer);
    if ($localPath !== null) {
        return $localPath;
    }

    $parts = parse_url($referer);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return $safeFallback;
    }

    $scheme = strtolower($parts['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') {
        return $safeFallback;
    }

    // Compare host and (when present) port. parse_url() returns just
    // the hostname in 'host' while HTTP_HOST typically includes the
    // port (e.g. "example.test:8080"), so a direct string compare
    // would falsely reject genuinely same-origin referers on non-
    // default ports.
    $expected = parse_host_and_port((string)($currentHost ?? ($_SERVER['HTTP_HOST'] ?? '')));
    $actual = parse_host_and_port($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
    if ($expected['host'] === '' || $expected !== $actual) {
        return $safeFallback;
    }

    $path = $parts['path'] ?? '/';
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    return (trusted_local_path($path . $query) ?? $safeFallback);
}

/**
 * Split a Host / HTTP_HOST style string into lowercase host and port
 * (or null when the port is the default for the scheme). Returns an
 * array with 'host' and 'port' keys for easy comparison.
 */
function parse_host_and_port(string $hostPort): array {
    $value = trim($hostPort);
    if ($value === '') {
        return ['host' => '', 'port' => null];
    }

    // IPv6 literal: [::1] or [::1]:8080
    if ($value[0] === '[') {
        $closing = strpos($value, ']');
        if ($closing === false) {
            return ['host' => strtolower($value), 'port' => null];
        }
        $host = substr($value, 1, $closing - 1);
        $rest = substr($value, $closing + 1);
        $port = null;
        if ($rest !== '' && $rest[0] === ':') {
            $port = (int)substr($rest, 1);
        }
        return ['host' => strtolower($host), 'port' => $port];
    }

    // Strip credentials if present
    if (str_contains($value, '@')) {
        $value = substr($value, strrpos($value, '@') + 1);
    }

    $colonCount = substr_count($value, ':');
    if ($colonCount === 0) {
        return ['host' => strtolower($value), 'port' => null];
    }
    if ($colonCount === 1) {
        [$host, $port] = explode(':', $value, 2);
        return ['host' => strtolower($host), 'port' => (int)$port];
    }

    // Multiple colons: treat the whole string as an unbracketed IPv6
    // literal and assume default port. Caller should not normally reach
    // here because the IPv6 branch above handles bracketed addresses.
    return ['host' => strtolower($value), 'port' => null];
}

function trusted_referer_path_from_server(string $fallback = '/'): string {
    return trusted_referer_path($_SERVER['HTTP_REFERER'] ?? null, $fallback, $_SERVER['HTTP_HOST'] ?? null);
}

function trusted_local_path(string $path): ?string {
    if ($path === '' || $path[0] !== '/') {
        return null;
    }

    if (str_starts_with($path, '//') || str_contains($path, '\\')) {
        return null;
    }

    return $path;
}

?>
