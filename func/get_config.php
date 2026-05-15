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

if (!function_exists('get_available_databases')) {
    function get_available_databases() {
        $dbConfigs = get_app_config('db_configs', []);
        
        // If db_configs is a string, use it directly
        if (is_string($dbConfigs) && !empty($dbConfigs)) {
            return [$dbConfigs];
        }
        
        // If db_configs is an array with named entries (new format)
        if (is_array($dbConfigs) && !empty($dbConfigs)) {
            // Check if it's the new format with name/db keys
            $first = reset($dbConfigs);
            if (is_array($first) && isset($first['db'])) {
                return array_keys($dbConfigs); // Return keys as database identifiers
            }
            // Old format: array of database names
            return array_keys($dbConfigs);
        }
        
        return [];
    }
}

if (!function_exists('should_show_db_picker')) {
    function should_show_db_picker() {
        $dbConfigs = get_app_config('db_configs', []);
        
        // If db_configs is empty or a single string, no picker needed
        if (empty($dbConfigs)) {
            return false;
        }
        
        if (is_string($dbConfigs)) {
            return false; // Single DB, no picker
        }
        
        // If array has more than one entry, show picker
        if (is_array($dbConfigs) && count($dbConfigs) > 1) {
            return true;
        }
        
        return false;
    }
}
?>