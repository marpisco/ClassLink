<?php
/**
 * Helper file to retrieve configuration from the 'config' database table.
 */

if (!function_exists('get_app_config')) {
    function get_app_config($key, $default = null) {
        global $db;
        
        static $config_cache = [];
        
        // Cache to avoid multiple queries for the same key in a single request lifecycle
        if (isset($config_cache[$key])) {
            return $config_cache[$key];
        }
        
        if (!isset($db) || $db->connect_error) {
            return $default;
        }

        $stmt = $db->prepare("SELECT config_value FROM config WHERE config_key = ?");
        if ($stmt) {
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                // Decode JSON back to its format. Configs are stored as JSON for complex types or strings
                $value = json_decode($row['config_value'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $config_cache[$key] = $value;
                    $stmt->close();
                    return $value;
                } else {
                    $config_cache[$key] = $row['config_value'];
                    $stmt->close();
                    return $row['config_value'];
                }
            }
            $stmt->close();
        }
        
        return $default;
    }
}

if (!function_exists('is_development_mode')) {
    function is_development_mode() {
        $mode = get_app_config('app_mode', 'production');
        return $mode === 'development';
    }
}
?>