<?php

/**
 * Merge this key into the ignored server-local includes/config.local.php.
 * Do not replace that file and do not add API or webhook secrets here.
 */
return [
    'SUBSCRIPTION_STRIPE_CATALOG_BINDING' => [
        'offerId' => 'ai-assistant-foundation-monthly',
        'stripeProductId' => 'prod_VAdppdm2hxfXT7',
        'stripePriceId' => 'price_1UAIXQPzjg2rInjnX5CypQNL',
        'currency' => 'USD',
        'priceMinor' => 5900,
        'billingPeriod' => 'monthly',
        'active' => true,
        'livemode' => false,
    ],
];
