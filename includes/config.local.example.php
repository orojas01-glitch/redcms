<?php
/**
 * Copy this file to config.local.php on each server and fill in local values.
 * Do not commit config.local.php.
 */

return [
    'DBHOST' => 'localhost',
    'DBUSER' => 'database_user',
    'DBPASS' => 'database_password',
    'DBNAME' => 'database_name',
    'IPSTACK_ACCESS_KEY' => '',
    'LEGACY_MAIL_OWNER' => '',
    'PAYPAL_PDT_HOSTNAME' => 'www.paypal.com',
    'PAYPAL_PDT_AUTH_TOKEN' => '',
    'PAYPAL_CONFIRMATION_FROM_EMAIL' => '',
    'PAYPAL_CONFIRMATION_FROM_NAME' => 'RED-CMS',
    // Canonical HTTPS origin for a future core-owned public-mutation path.
    // Never derive this from Host or a request header.
    'PUBLIC_MUTATION_TRUSTED_ORIGIN' => '',
    // Supported Caddy/FrankenPHP deployments only. The endpoint additionally
    // requires RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY in process environment.
    'PUBLIC_MUTATION_ENDPOINT_ENABLED' => false,
    // Opaque references only. Secret values remain in their provider.
    'ADDON_SECRET_REFERENCES' => [],
    // Optional local-only values keyed by the exact opaque references above.
    // Never commit config.local.php or place secret values in tracked files.
    'ADDON_SECRET_VALUES' => [],
];
