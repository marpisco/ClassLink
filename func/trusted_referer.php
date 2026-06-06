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

    $host = strtolower($parts['host']);
    $expectedHost = strtolower((string)($currentHost ?? ($_SERVER['HTTP_HOST'] ?? '')));
    if ($expectedHost === '' || $host !== $expectedHost) {
        return $safeFallback;
    }

    $path = $parts['path'] ?? '/';
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    return (trusted_local_path($path . $query) ?? $safeFallback);
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
