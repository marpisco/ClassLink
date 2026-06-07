<?php
/**
 * Get the client's IP address for rate limiting and audit logging.
 *
 * By default this returns REMOTE_ADDR verbatim. The X-Forwarded-For and
 * Client-IP headers are only honoured when the immediate peer
 * (REMOTE_ADDR) is on the configured `trusted_proxies` allowlist, which
 * prevents attackers from forging a different IP on every request to
 * bypass per-IP rate limits on authentication.
 */
function get_client_ip() {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

    if (is_trusted_proxy($remoteAddr)) {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])
            && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // X-Forwarded-For can contain a chain: client, proxy1, proxy2, ...
            // The first entry is the original client.
            foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $candidate) {
                $candidate = trim($candidate);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }
    }

    if (filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        return $remoteAddr;
    }

    return 'Unknown';
}

/**
 * Return true if $remoteAddr is one of the configured trusted proxies.
 * Reads the comma-separated `trusted_proxies` config. The comparison is
 * exact (no CIDR matching) because the typical deployment runs a small,
 * well-known set of reverse proxies.
 */
function is_trusted_proxy(?string $remoteAddr): bool {
    if ($remoteAddr === null || $remoteAddr === '') {
        return false;
    }

    if (!function_exists('get_app_config')) {
        return false;
    }

    $configured = get_app_config('trusted_proxies', '');
    if (!is_string($configured) || $configured === '') {
        return false;
    }

    $allowed = array_filter(array_map('trim', explode(',', $configured)), 'strlen');
    if (empty($allowed)) {
        return false;
    }

    return in_array($remoteAddr, $allowed, true);
}

function logaction(string $loginfo, string $userid){
    require_once(__DIR__ . '/../func/genuuid.php');
    require_once(__DIR__ . "/../src/db.php");
    global $db;

    $id = uuid4();
    $ip_address = get_client_ip();
    $stmt = $db->prepare("INSERT INTO logs (id, loginfo, userid, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $id, $loginfo, $userid, $ip_address);
    $stmt->execute();
    $stmt->close();
};
?>
