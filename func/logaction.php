<?php
/**
 * Get the client's IP address for rate limiting and audit logging.
 *
 * By default this returns REMOTE_ADDR verbatim. The X-Forwarded-For
 * header is only honoured when the immediate peer (REMOTE_ADDR) is on
 * the configured `trusted_proxies` allowlist, and the chain is walked
 * from the right: any proxies in the allowlist are skipped, and the
 * first untrusted address (the rightmost) is returned. The
 * non-standard HTTP_CLIENT_IP header is never trusted — no real
 * reverse proxy populates it, and the leftmost XFF entry remains
 * attacker-controlled.
 */
function get_client_ip() {
    return resolve_client_ip($_SERVER, get_trusted_proxy_set());
}

/**
 * Pure resolver for the client IP. $server should be a $_SERVER-style
 * array; $trustedProxies is a hash set keyed by IP of the allowed
 * proxies. Kept as a standalone function so it can be unit-tested
 * without a database or globals.
 */
function resolve_client_ip(array $server, array $trustedProxies): string {
    $remoteAddr = $server['REMOTE_ADDR'] ?? '';

    if (is_string($remoteAddr) && $remoteAddr !== '' && isset($trustedProxies[$remoteAddr])) {
        $xff = $server['HTTP_X_FORWARDED_FOR'] ?? '';
        if (is_string($xff) && $xff !== '') {
            // Walk right to left: drop any trusted hops, take the first
            // untrusted address. Anything on the left of that point is
            // still attacker-controlled and is ignored.
            $candidates = array_reverse(array_map('trim', explode(',', $xff)));
            foreach ($candidates as $candidate) {
                if ($candidate === '' || !filter_var($candidate, FILTER_VALIDATE_IP)) {
                    continue;
                }
                if (isset($trustedProxies[$candidate])) {
                    continue;
                }
                return $candidate;
            }
        }
    }

    if (is_string($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
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

    $trusted = get_trusted_proxy_set();
    return isset($trusted[$remoteAddr]);
}

/**
 * Return the trusted_proxies config as a hash set keyed by IP for fast
 * O(1) lookups. Empty when no proxies are configured (the default), in
 * which case X-Forwarded-For is never trusted and REMOTE_ADDR is used
 * verbatim.
 */
function get_trusted_proxy_set(): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    if (!function_exists('get_app_config')) {
        return $cache;
    }

    $configured = get_app_config('trusted_proxies', '');
    if (!is_string($configured) || $configured === '') {
        return $cache;
    }

    foreach (array_map('trim', explode(',', $configured)) as $ip) {
        if ($ip !== '') {
            $cache[$ip] = true;
        }
    }
    return $cache;
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
