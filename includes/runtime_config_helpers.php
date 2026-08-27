<?php
/**
 * Server-local runtime configuration helpers.
 */

if (!function_exists('red_local_config_values')) {
    function red_local_config_values()
    {
        static $loaded = false;
        static $values = [];

        if ($loaded) {
            return $values;
        }

        $loaded = true;
        $localConfigFile = __DIR__ . '/config.local.php';
        if (!is_file($localConfigFile)) {
            return $values;
        }

        $loadedConfig = require $localConfigFile;
        if (is_array($loadedConfig)) {
            $values = $loadedConfig;
        }

        return $values;
    }
}

if (!function_exists('red_config_value')) {
    function red_config_value($localKey, $envKeys, $default = '')
    {
        foreach ((array) $envKeys as $envKey) {
            $envValue = getenv($envKey);
            if ($envValue !== false && $envValue !== '') {
                return $envValue;
            }
            if (isset($_ENV[$envKey]) && $_ENV[$envKey] !== '') {
                return $_ENV[$envKey];
            }
            if (isset($_SERVER[$envKey]) && $_SERVER[$envKey] !== '') {
                return $_SERVER[$envKey];
            }
        }

        $localValues = red_local_config_values();
        if (array_key_exists($localKey, $localValues)) {
            return $localValues[$localKey];
        }

        return $default;
    }
}

if (!function_exists('red_server_config_value')) {
    /**
     * Reads server-local configuration that must never be influenced by a
     * request header projected into $_SERVER.
     *
     * This intentionally accepts operating-system environment values and the
     * local ignored configuration file only. Compatibility configuration can
     * continue to use red_config_value(), which preserves its older $_ENV and
     * $_SERVER fallback behavior.
     */
    function red_server_config_value($localKey, $envKeys, $default = '')
    {
        if (!is_string($localKey) || $localKey === '') {
            return $default;
        }

        foreach ((array) $envKeys as $envKey) {
            if (!is_string($envKey) || $envKey === '') {
                continue;
            }
            $envValue = getenv($envKey);
            if (is_string($envValue) && $envValue !== '') {
                return $envValue;
            }
        }

        $localValues = red_local_config_values();
        if (array_key_exists($localKey, $localValues)) {
            return $localValues[$localKey];
        }

        return $default;
    }
}

if (!function_exists('red_runtime_database_constants_bootstrap')) {
    /**
     * Defines only the legacy database constants required by package-owned
     * runtime services on early front-controller routes. Values come from the
     * server-only environment/local configuration boundary, never request
     * headers. Existing constants are preserved exactly.
     */
    function red_runtime_database_constants_bootstrap()
    {
        $definitions = [
            'DBHOST' => ['DBHOST', ['RED_DB_HOST', 'DBHOST'], 'localhost'],
            'DBUSER' => ['DBUSER', ['RED_DB_USER', 'DBUSER'], ''],
            'DBPASS' => ['DBPASS', ['RED_DB_PASS', 'DBPASS'], ''],
            'DBNAME' => ['DBNAME', ['RED_DB_NAME', 'DBNAME'], ''],
        ];
        foreach ($definitions as $constant => $definition) {
            if (!defined($constant)) {
                $value = red_server_config_value(
                    $definition[0],
                    $definition[1],
                    $definition[2]
                );
                if (!is_string($value)
                    || !define($constant, $value)
                ) {
                    return false;
                }
            }
            if (!is_string(constant($constant))) {
                return false;
            }
        }
        return true;
    }
}

?>
