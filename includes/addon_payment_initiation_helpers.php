<?php
/**
 * Provider-neutral payment-initiation result boundary.
 *
 * This dependency-free helper does not load a package, resolve a secret, open
 * a database, publish a route, or contact a provider. It preserves the
 * existing hosted reference/URL value and adds one closed out-of-band shape
 * for approval in a provider-owned application.
 */

if (!function_exists('red_addon_payment_initiation_result')) {
    function red_addon_payment_initiation_result($reason = 'invalid')
    {
        return [
            'accepted' => false,
            'mode' => '',
            'reason' => (string) $reason,
            'value' => null,
        ];
    }
}

if (!function_exists('red_addon_payment_initiation_exact_keys')) {
    function red_addon_payment_initiation_exact_keys(
        array $value,
        array $expected
    ) {
        return array_keys($value) === $expected;
    }
}

if (!function_exists('red_addon_payment_initiation_reference_valid')) {
    function red_addon_payment_initiation_reference_valid($value)
    {
        return is_string($value)
            && preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,159}\z/D',
                $value
            ) === 1;
    }
}

if (!function_exists('red_addon_payment_initiation_https_url_valid')) {
    function red_addon_payment_initiation_https_url_valid($value)
    {
        if (!is_string($value)
            || strlen($value) < 1
            || strlen($value) > 2048
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
        ) {
            return false;
        }
        $url = parse_url($value);
        return is_array($url)
            && ($url['scheme'] ?? null) === 'https'
            && is_string($url['host'] ?? null)
            && preg_match(
                '/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}'
                    . '[a-z0-9])?\.)+[a-z]{2,63}\z/D',
                $url['host']
            ) === 1
            && !array_key_exists('user', $url)
            && !array_key_exists('pass', $url)
            && !array_key_exists('port', $url)
            && !array_key_exists('query', $url)
            && !array_key_exists('fragment', $url)
            && is_string($url['path'] ?? null)
            && str_starts_with($url['path'], '/')
            && $url['path'] !== '/';
    }
}

if (!function_exists('red_addon_payment_initiation_normalize')) {
    function red_addon_payment_initiation_normalize($mode, $value)
    {
        $result = red_addon_payment_initiation_result(
            'initiation_mode_invalid'
        );
        if (!is_string($mode) || !is_array($value)) {
            return $result;
        }

        if ($mode === 'hosted_redirect') {
            if (!red_addon_payment_initiation_exact_keys($value, [
                'providerReference', 'checkoutUrl',
            ])
                || !red_addon_payment_initiation_reference_valid(
                    $value['providerReference'] ?? null
                )
                || !red_addon_payment_initiation_https_url_valid(
                    $value['checkoutUrl'] ?? null
                )
            ) {
                $result['reason'] = 'hosted_redirect_invalid';
                return $result;
            }
        } elseif ($mode === 'out_of_band_confirmation') {
            if (!red_addon_payment_initiation_exact_keys($value, [
                'providerReference', 'state', 'customerAction',
            ])
                || !red_addon_payment_initiation_reference_valid(
                    $value['providerReference'] ?? null
                )
                || ($value['state'] ?? null) !== 'pending'
                || ($value['customerAction'] ?? null)
                    !== 'approve_in_provider_app'
            ) {
                $result['reason'] = 'out_of_band_confirmation_invalid';
                return $result;
            }
        } else {
            return $result;
        }

        $result['accepted'] = true;
        $result['mode'] = $mode;
        $result['reason'] = 'initiation_accepted';
        $result['value'] = $value;
        return $result;
    }
}

?>
