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
    // Opt-in authenticated SMTP. Keep credentials server-local; never in the DB.
    // smtp mode refuses failure fallback to PHP mail(). See docs/SMTP-DELIVERY.md.
    'MAIL_TRANSPORT' => 'mail',
    'SMTP_HOST' => '',
    'SMTP_PORT' => 587,
    'SMTP_SECURITY' => 'starttls', // starttls/587 or smtps/465, verified TLS only
    'SMTP_USERNAME' => '',
    'SMTP_PASSWORD' => '',
    'SMTP_FROM_EMAIL' => '',
    'SMTP_FROM_NAME' => '',
    // Review docs/CSP-HARDENING.md. Start report-only on the isolated demo;
    // enforce only after checking that installation's themes/integrations.
    'CSP_MODE' => 'off', // off | report-only | enforce
    'CSP_SOURCES' => [], // directive => array of exact approved HTTPS origins
    'CSP_STRICT_REPORT' => false, // report remaining inline-script usage locally
    // Visitor country for display choices only (e.g. a Colombia-only payment
    // option); never for authorization. off | cloudflare (CF-IPCountry; only
    // when all traffic goes through Cloudflare) | server-variable (web-server
    // GeoIP module: GEOIP_COUNTRY_CODE / MM_COUNTRY_CODE, or the name below).
    'VISITOR_COUNTRY_SOURCE' => 'off',
    'VISITOR_COUNTRY_SERVER_VARIABLE' => '',
    // Canonical HTTPS origin for a future core-owned public-mutation path.
    // Never derive this from Host or a request header.
    'PUBLIC_MUTATION_TRUSTED_ORIGIN' => '',
    // Supported values: frankenphp_attested (default) or direct_php. The
    // direct profile must be selected explicitly and requires direct HTTPS.
    'PUBLIC_MUTATION_INGRESS_PROFILE' => 'frankenphp_attested',
    // The attested profile additionally requires
    // RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY in process environment.
    'PUBLIC_MUTATION_ENDPOINT_ENABLED' => false,
    // Opaque references only. Secret values remain in their provider.
    'ADDON_SECRET_REFERENCES' => [],
    // Optional local-only values keyed by the exact opaque references above.
    // Never commit config.local.php or place secret values in tracked files.
    'ADDON_SECRET_VALUES' => [],
];
