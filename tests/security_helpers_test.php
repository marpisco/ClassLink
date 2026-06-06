<?php

require_once(__DIR__ . '/../func/trusted_referer.php');
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

$userAAction = verify_code_attempt_action('user_abc');
$userBAction = verify_code_attempt_action('pre_123');
assert_same($userAAction, verify_code_attempt_action('user_abc'), 'Same user should get stable verify_code key');
assert_not_same($userAAction, $userBAction, 'Different users should not share verify_code attempt key');
assert_true(str_starts_with($userAAction, 'verify_code:'), 'verify_code attempt key should stay namespaced');
assert_true(strlen($userAAction) <= 50, 'verify_code attempt key must fit rate_limits.action column');

echo "security_helpers_test passed\n";
