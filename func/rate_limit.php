<?php
// Rate Limiting Functions
// Provides per-IP rate limiting and attempt tracking for authentication flows.
//
// Usage:
//   check_rate_limit('send_code', 10, 3600)  -> true if allowed, false if blocked
//   record_attempt('send_code')              -> record a failed attempt
//   is_blocked('verify_totp')                -> true if currently blocked
//   block('verify_totp', 900)                -> block for 15 minutes
//   clear_attempts('verify_totp')            -> reset attempts (on success)
//
// Thresholds (per IP, per action):
//   send_code:          10 attempts / hour
//   verify_code:        5 wrong attempts -> invalidate OTP for user (see invalidate_user_otp)
//   verify_totp:        5 attempts / 15-minute window
//   verify_totp_setup:  5 attempts / 15-minute window

require_once(__DIR__ . '/logaction.php');

function rl_get_client_ip(): string {
    return get_client_ip();
}

/**
 * Returns the current timestamp formatted for MySQL DATETIME.
 */
function rl_now(): string {
    return date('Y-m-d H:i:s');
}

function verify_code_attempt_action(string $userId): string {
    return 'verify_code:' . substr(hash('sha256', $userId), 0, 32);
}

function rl_record_attempt_update_bind_types(): string {
    return 'sisisss';
}

/**
 * Look up the rate_limits row for a given (ip, action) pair.
 * Returns the row array or null.
 */
function rl_get_row(string $ip, string $action): ?array {
    global $db;
    $stmt = $db->prepare("SELECT attempts, window_start, blocked_until FROM rate_limits WHERE ip = ? AND action = ?");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("ss", $ip, $action);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Check whether the given (ip, action) is currently blocked.
 * - If blocked_until is in the future, returns true.
 * - Stale windows (window_start + windowSeconds < now) are NOT cleaned here
 *   to keep this read cheap; check_rate_limit() handles the sliding window.
 */
function is_blocked(string $action, int $windowSeconds = 900): bool {
    $ip = rl_get_client_ip();
    $row = rl_get_row($ip, $action);
    if (!$row) {
        return false;
    }
    if (!empty($row['blocked_until']) && strtotime($row['blocked_until']) > time()) {
        return true;
    }
    return false;
}

/**
 * Check if a new attempt is allowed under the (maxAttempts, windowSeconds) budget.
 * If a previous window has expired, it is reset transparently and the new
 * attempt is allowed.
 *
 * @return bool true if allowed, false if rate-limited
 */
function check_rate_limit(string $action, int $maxAttempts, int $windowSeconds): bool {
    $ip = rl_get_client_ip();

    // Respect an explicit block first.
    if (is_blocked($action, $windowSeconds)) {
        return false;
    }

    $row = rl_get_row($ip, $action);
    if ($row === null) {
        return true;
    }

    // If the window expired, treat the slot as fresh.
    $windowEnd = strtotime($row['window_start']) + $windowSeconds;
    if ($windowEnd <= time()) {
        return true;
    }

    return (int)$row['attempts'] < $maxAttempts;
}

/**
 * Record an attempt for the current IP/action. If the existing window has
 * expired (per the configured $windowSeconds), the counter is reset.
 * Otherwise the counter is incremented. Callers must pass the same
 * $windowSeconds they pass to check_rate_limit(), otherwise the counter
 * never resets for windows shorter than 1 hour.
 */
function record_attempt(string $action, int $windowSeconds = 3600): void {
    global $db;
    $ip = rl_get_client_ip();
    $now = rl_now();

    $row = rl_get_row($ip, $action);
    if ($row === null) {
        $stmt = $db->prepare("INSERT INTO rate_limits (ip, action, attempts, window_start) VALUES (?, ?, 1, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $ip, $action, $now);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }

    // Reset the window if it expired. Use the actual configured window
    // (passed by the caller) instead of a hard-coded 1 HOUR, so shorter
    // windows (e.g. the 15-minute TOTP lockout) actually roll over.
    $stmt = $db->prepare("UPDATE rate_limits SET attempts = IF(window_start < DATE_SUB(?, INTERVAL ? SECOND), 1, attempts + 1), window_start = IF(window_start < DATE_SUB(?, INTERVAL ? SECOND), ?, window_start) WHERE ip = ? AND action = ?");
    if ($stmt) {
        $stmt->bind_param(rl_record_attempt_update_bind_types(), $now, $windowSeconds, $now, $windowSeconds, $now, $ip, $action);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Block the current IP for a given action for $seconds. While blocked,
 * check_rate_limit() will refuse all attempts regardless of the attempt count.
 */
function block(string $action, int $seconds): void {
    global $db;
    $ip = rl_get_client_ip();
    $blockedUntil = date('Y-m-d H:i:s', time() + $seconds);

    $stmt = $db->prepare("INSERT INTO rate_limits (ip, action, attempts, window_start, blocked_until) VALUES (?, ?, 0, ?, ?) ON DUPLICATE KEY UPDATE blocked_until = VALUES(blocked_until)");
    if ($stmt) {
        $stmt->bind_param("ssss", $ip, $action, rl_now(), $blockedUntil);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Clear all attempt and block state for the current IP/action (e.g. on success).
 */
function clear_attempts(string $action): void {
    global $db;
    $ip = rl_get_client_ip();
    $stmt = $db->prepare("DELETE FROM rate_limits WHERE ip = ? AND action = ?");
    if ($stmt) {
        $stmt->bind_param("ss", $ip, $action);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Invalidate the OTP for a given user (set otp_code_hash / otp_expires to NULL)
 * after too many wrong verify_code attempts. They will need to request a new code.
 */
function invalidate_user_otp(string $userId): void {
    global $db;
    $stmt = $db->prepare("UPDATE cache SET otp_code_hash = NULL, otp_expires = NULL WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $stmt->close();
    }
}
?>
