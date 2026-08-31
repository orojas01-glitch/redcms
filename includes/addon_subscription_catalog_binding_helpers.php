<?php
/** Pure server-local mapping from one Store Lite offer to one Stripe Price. */

if (!function_exists('red_addon_subscription_catalog_binding_normalize')) {
    function red_addon_subscription_catalog_binding_normalize($value)
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 8, JSON_THROW_ON_ERROR);
            } catch (Throwable $throwable) {
                return null;
            }
        }
        if (!is_array($value) || array_is_list($value)) {
            return null;
        }
        $expected = [
            'offerId', 'stripeProductId', 'stripePriceId', 'currency',
            'priceMinor', 'billingPeriod', 'active', 'livemode',
        ];
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($keys !== $expected
            || !is_string($value['offerId'] ?? null)
            || preg_match(
                '/\A[a-z0-9][a-z0-9._-]{0,63}\z/D',
                $value['offerId']
            ) !== 1
            || !is_string($value['stripeProductId'] ?? null)
            || preg_match(
                '/\Aprod_[A-Za-z0-9]{8,128}\z/D',
                $value['stripeProductId']
            ) !== 1
            || !is_string($value['stripePriceId'] ?? null)
            || preg_match(
                '/\Aprice_[A-Za-z0-9]{8,128}\z/D',
                $value['stripePriceId']
            ) !== 1
            || !is_string($value['currency'] ?? null)
            || preg_match('/\A[A-Z]{3}\z/D', $value['currency']) !== 1
            || !is_int($value['priceMinor'] ?? null)
            || $value['priceMinor'] < 0
            || $value['priceMinor'] > 999999999
            || !in_array(
                $value['billingPeriod'] ?? null,
                ['monthly', 'yearly'],
                true
            )
            || ($value['active'] ?? null) !== true
            || ($value['livemode'] ?? null) !== false
        ) {
            return null;
        }
        return [
            'offerId' => $value['offerId'],
            'stripeProductId' => $value['stripeProductId'],
            'stripePriceId' => $value['stripePriceId'],
            'currency' => $value['currency'],
            'priceMinor' => $value['priceMinor'],
            'billingPeriod' => $value['billingPeriod'],
            'active' => true,
            'livemode' => false,
        ];
    }
}

if (!function_exists('red_addon_subscription_catalog_binding_matches_offer')) {
    function red_addon_subscription_catalog_binding_matches_offer(
        $binding,
        $offer
    ) {
        $normalized =
            red_addon_subscription_catalog_binding_normalize($binding);
        return is_array($normalized)
            && is_array($offer)
            && ($normalized['offerId'] ?? null) === ($offer['id'] ?? null)
            && ($normalized['currency'] ?? null)
                === ($offer['currency'] ?? null)
            && ($normalized['priceMinor'] ?? null)
                === ($offer['priceMinor'] ?? null)
            && ($normalized['billingPeriod'] ?? null)
                === ($offer['billingPeriod'] ?? null)
            && ($offer['state'] ?? null) === 'published'
            && ($offer['availability'] ?? null) === 'available';
    }
}

?>
