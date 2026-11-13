<?php
/**
 * Best-effort ISO 3166-1 alpha-2 country of the current visitor.
 *
 * Display-only: use it to decide which options a page shows (for example a
 * Colombia-only payment method), never to authorize a payment, price, or
 * permission. Anyone can reach a site through a VPN, and a request header
 * can be forged when the origin is reachable without the proxy that sets it.
 *
 * Detection is off unless the server-local VISITOR_COUNTRY_SOURCE is set:
 *
 * - "cloudflare": the CF-IPCountry header that Cloudflare adds when IP
 *   geolocation is on (the default on every plan). Only meaningful when all
 *   traffic reaches the origin through Cloudflare.
 * - "server-variable": a variable set by the web server's own GeoIP module,
 *   GEOIP_COUNTRY_CODE (mod_geoip) or MM_COUNTRY_CODE (mod_maxminddb), or the
 *   name given in VISITOR_COUNTRY_SERVER_VARIABLE.
 *
 * No request ever leaves the server to look a visitor up.
 */

require_once __DIR__ . '/runtime_config_helpers.php';

if (!function_exists('red_visitor_country_source')) {
    function red_visitor_country_source()
    {
        $source = red_server_config_value(
            'VISITOR_COUNTRY_SOURCE',
            ['RED_VISITOR_COUNTRY_SOURCE'],
            'off'
        );
        return in_array($source, ['cloudflare', 'server-variable'], true)
            ? $source
            : 'off';
    }
}

if (!function_exists('red_visitor_country_normalize')) {
    /** Returns an uppercase alpha-2 code, or null for unknown/special values. */
    function red_visitor_country_normalize($value)
    {
        if (!is_string($value)) {
            return null;
        }
        $code = strtoupper(trim($value));
        if (preg_match('/\A[A-Z]{2}\z/D', $code) !== 1) {
            return null;
        }
        // Cloudflare: XX unknown, T1 Tor (not matched above). EU/AP are
        // MaxMind continent placeholders rather than countries.
        if (in_array($code, ['XX', 'EU', 'AP', 'ZZ'], true)) {
            return null;
        }
        return $code;
    }
}

if (!function_exists('red_visitor_country_code')) {
    /**
     * @param array|null $server request server variables; defaults to $_SERVER
     * @return string|null alpha-2 code, or null when unknown or detection is off
     */
    function red_visitor_country_code($server = null)
    {
        $server = is_array($server) ? $server : $_SERVER;
        $source = red_visitor_country_source();
        if ($source === 'cloudflare') {
            return red_visitor_country_normalize(
                $server['HTTP_CF_IPCOUNTRY'] ?? null
            );
        }
        if ($source === 'server-variable') {
            $names = ['GEOIP_COUNTRY_CODE', 'MM_COUNTRY_CODE'];
            $configured = red_server_config_value(
                'VISITOR_COUNTRY_SERVER_VARIABLE',
                ['RED_VISITOR_COUNTRY_SERVER_VARIABLE'],
                ''
            );
            if (is_string($configured)
                && preg_match('/\A[A-Z][A-Z0-9_]{0,63}\z/D', $configured) === 1
                && strpos($configured, 'HTTP_') !== 0
            ) {
                array_unshift($names, $configured);
            }
            foreach ($names as $name) {
                // Apache may prefix variables with REDIRECT_ after rewrites.
                foreach ([$name, 'REDIRECT_' . $name] as $key) {
                    $code = red_visitor_country_normalize($server[$key] ?? null);
                    if ($code !== null) {
                        return $code;
                    }
                }
            }
        }
        return null;
    }
}

?>
