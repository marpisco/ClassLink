<?php

require_once(__DIR__ . '/../func/trusted_referer.php');
require_once(__DIR__ . '/../func/request_redaction.php');
require_once(__DIR__ . '/../func/rate_limit.php');

function assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assert_not_same($unexpected, $actual, string $message): void {
    if ($unexpected === $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected/Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assert_true($value, string $message): void {
    if (!$value) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

assert_same('/reservar', trusted_referer_path(null, '/reservar', 'classlink.test'), 'Missing referer should use fallback');
assert_same('/admin/pedidos.php?estado=pending', trusted_referer_path('https://classlink.test/admin/pedidos.php?estado=pending', '/reservar', 'classlink.test'), 'Same-origin absolute referer should keep path and query');
assert_same('/reservar?x=1', trusted_referer_path('/reservar?x=1', '/reservar', 'classlink.test'), 'Local-path referer should be accepted');
assert_same('/reservar', trusted_referer_path('https://evil.test/phish', '/reservar', 'classlink.test'), 'External referer should use fallback');
assert_same('/reservar', trusted_referer_path('javascript:alert(1)', '/reservar', 'classlink.test'), 'Non-http referer should use fallback');

// Port handling: HTTP_HOST typically includes a port (classlink.test:8080)
// while parse_url($referer)['host'] strips it. A direct string compare
// would reject these as untrusted. Mismatched ports are not the same
// origin and must be rejected.
assert_same(
    '/admin/pedidos.php',
    trusted_referer_path('https://classlink.test:8080/admin/pedidos.php', '/reservar', 'classlink.test:8080'),
    'Same-origin referer with matching port should be accepted'
);
assert_same(
    '/reservar',
    trusted_referer_path('https://classlink.test:8080/admin/pedidos.php', '/reservar', 'classlink.test:9090'),
    'Same host but different port should be rejected'
);
assert_same(
    '/reservar',
    trusted_referer_path('https://classlink.test:8080/admin/pedidos.php', '/reservar', 'classlink.test'),
    'Referer with port and host without port should be rejected'
);
assert_same(
    '/reservar',
    trusted_referer_path('https://classlink.test/admin/pedidos.php', '/reservar', 'classlink.test:8080'),
    'Host without port and HTTP_HOST with port should be rejected'
);

assert_same(['host' => 'classlink.test', 'port' => 8080], parse_host_and_port('classlink.test:8080'), 'Plain host:port parses');
assert_same(['host' => 'classlink.test', 'port' => null], parse_host_and_port('classlink.test'), 'Host without port parses with null port');
assert_same(['host' => '::1', 'port' => 8080], parse_host_and_port('[::1]:8080'), 'Bracketed IPv6 with port parses');
assert_same(['host' => '::1', 'port' => null], parse_host_and_port('[::1]'), 'Bracketed IPv6 without port parses');
assert_same(['host' => 'classlink.test', 'port' => 80], parse_host_and_port('ClassLink.Test:80'), 'Host and port are lowercased');

$userAAction = verify_code_attempt_action('user_abc');
$userBAction = verify_code_attempt_action('pre_123');
assert_same($userAAction, verify_code_attempt_action('user_abc'), 'Same user should get stable verify_code key');
assert_not_same($userAAction, $userBAction, 'Different users should not share verify_code attempt key');
assert_true(str_starts_with($userAAction, 'verify_code:'), 'verify_code attempt key should stay namespaced');
assert_true(strlen($userAAction) <= 50, 'verify_code attempt key must fit rate_limits.action column');

assert_same('sisisss', rl_record_attempt_update_bind_types(), 'record_attempt UPDATE bind types should bind action as string');

$redacted = redact_sensitive_request_data([
    'email' => 'user@example.test',
    'csrf_token' => 'abc123',
    'password' => 'secret',
    'nested' => [
        'clientSecret' => 'oauth-secret',
        'motivo' => 'Reserva normal',
    ],
]);
assert_same('user@example.test', $redacted['email'], 'Non-sensitive log fields should be preserved');
assert_same('[REDACTED]', $redacted['csrf_token'], 'CSRF token should be redacted from logs');
assert_same('[REDACTED]', $redacted['password'], 'Password should be redacted from logs');
assert_same('[REDACTED]', $redacted['nested']['clientSecret'], 'Nested secret should be redacted from logs');
assert_same('Reserva normal', $redacted['nested']['motivo'], 'Nested non-sensitive field should be preserved');

echo "security_helpers_test passed\n";
