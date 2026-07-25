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

?>
