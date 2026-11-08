<?php
/**
 * Provider-neutral payment-initiation result boundary.
 *
 * This dependency-free helper does not load a package, resolve a secret, open
 * a database, publish a route, or contact a provider. It preserves the
 * existing hosted reference/URL value and adds one closed out-of-band shape
 * for approval in a provider-owned application, plus one closed
 * provider-hosted GET form (Wompi Web Checkout).
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
    function red_addon_payment_initiation_https_url_valid(
        $value,
        $providerReference = null
    )
    {
        if (!is_string($value)
            || strlen($value) < 1
            || strlen($value) > 2048
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
        ) {
            return false;
        }
        $url = parse_url($value);
        if (!is_array($url)) {
            return false;
        }
        $queryValid = !array_key_exists('query', $url);
        if (!$queryValid
            && red_addon_payment_initiation_reference_valid(
                $providerReference
            )
        ) {
            $queryValid = $url['query']
                === 'token=' . rawurlencode($providerReference);
        }
        return ($url['scheme'] ?? null) === 'https'
            && is_string($url['host'] ?? null)
            && preg_match(
                '/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}'
                    . '[a-z0-9])?\.)+[a-z]{2,63}\z/D',
                $url['host']
            ) === 1
            && !array_key_exists('user', $url)
            && !array_key_exists('pass', $url)
            && !array_key_exists('port', $url)
            && !array_key_exists('fragment', $url)
            && $queryValid
            && is_string($url['path'] ?? null)
            && str_starts_with($url['path'], '/')
            && $url['path'] !== '/';
    }
}

if (!function_exists('red_addon_payment_initiation_hosted_get_form_valid')) {
    /**
     * Closed provider-hosted GET form (Wompi Web Checkout). The action is one
     * fixed provider URL and the fields are an exact, ordered, public-only
     * list: the integrity signature is a hash, and no private key, integrity
     * secret or event secret shape can pass the field patterns.
     */
    function red_addon_payment_initiation_hosted_get_form_valid($value)
    {
        if (!is_array($value)
            || !red_addon_payment_initiation_exact_keys($value, [
                'providerReference', 'action', 'fields',
            ])
            || !red_addon_payment_initiation_reference_valid(
                $value['providerReference'] ?? null
            )
            || ($value['action'] ?? null) !== 'https://checkout.wompi.co/p/'
            || !is_array($value['fields'] ?? null)
            || !array_is_list($value['fields'])
        ) {
            return false;
        }
        $required = [
            'public-key', 'currency', 'amount-in-cents', 'reference',
            'signature:integrity', 'redirect-url',
        ];
        $names = [];
        $fields = [];
        foreach ($value['fields'] as $field) {
            if (!is_array($field)
                || !array_is_list($field)
                || count($field) !== 2
                || !is_string($field[0])
                || !is_string($field[1])
            ) {
                return false;
            }
            $names[] = $field[0];
            $fields[$field[0]] = $field[1];
        }
        if ($names !== $required
            && $names !== array_merge($required, ['expiration-time'])
        ) {
            return false;
        }
        return preg_match(
            '/\Apub_(?:test|prod)_[A-Za-z0-9]{8,128}\z/D',
            $fields['public-key']
        ) === 1
            && $fields['currency'] === 'COP'
            && preg_match('/\A[1-9][0-9]{2,11}\z/D', $fields['amount-in-cents'])
                === 1
            && $fields['reference'] === $value['providerReference']
            && preg_match('/\Aord_[a-f0-9]{32}\z/D', $fields['reference'])
                === 1
            && preg_match('/\A[a-f0-9]{64}\z/D', $fields['signature:integrity'])
                === 1
            && red_addon_payment_initiation_https_url_valid(
                $fields['redirect-url']
            )
            && (!array_key_exists('expiration-time', $fields)
                || preg_match(
                    '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z\z/D',
                    $fields['expiration-time']
                ) === 1);
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
                    $value['checkoutUrl'] ?? null,
                    $value['providerReference'] ?? null
                )
            ) {
                $result['reason'] = 'hosted_redirect_invalid';
                return $result;
            }
        } elseif ($mode === 'hosted_get_form') {
            if (!red_addon_payment_initiation_hosted_get_form_valid($value)) {
                $result['reason'] = 'hosted_get_form_invalid';
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
