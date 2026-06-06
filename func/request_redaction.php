<?php

function redact_sensitive_request_data(array $data): array {
    $redacted = [];
    foreach ($data as $key => $value) {
        if (is_sensitive_request_key((string)$key)) {
            $redacted[$key] = '[REDACTED]';
            continue;
        }

        $redacted[$key] = is_array($value) ? redact_sensitive_request_data($value) : $value;
    }

    return $redacted;
}

function is_sensitive_request_key(string $key): bool {
    $normalized = strtolower($key);
    $sensitiveParts = [
        'password',
        'passwd',
        'secret',
        'token',
        'csrf',
        'otp',
        'code',
        'authorization',
        'api_key',
        'apikey',
        'private_key',
    ];

    foreach ($sensitiveParts as $part) {
        if (str_contains($normalized, $part)) {
            return true;
        }
    }

    return false;
}

?>
