<?php
/**
 * Dependency-free checks for the portable RED-CMS starter boundary.
 *
 * This script opens no database, session, network connection, or mail transport.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
$assertions = 0;

function red_clean_starter_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_clean_starter_test_source($repositoryRoot, $relativePath)
{
    $source = file_get_contents($repositoryRoot . '/' . $relativePath);
    if (!is_string($source)) {
        throw new RuntimeException('Could not read starter source: ' . $relativePath);
    }

    return $source;
}

try {
    $themeDirectories = array_values(array_filter(
        scandir($repositoryRoot . '/themes') ?: [],
        static function ($entry) use ($repositoryRoot) {
            return $entry !== '.'
                && $entry !== '..'
                && is_dir($repositoryRoot . '/themes/' . $entry);
        }
    ));
    sort($themeDirectories);
    red_clean_starter_test_assert(
        $themeDirectories === ['legacy-bootstrap', 'starter-reference'],
        'only the portable starter and hard-recovery themes ship'
    );

    foreach (['images/articles', 'images/gallery'] as $mediaDirectory) {
        $mediaIgnore = $repositoryRoot . '/' . $mediaDirectory . '/.gitignore';
        $mediaIgnoreSource = is_file($mediaIgnore)
            ? str_replace(["\r\n", "\r"], "\n", trim((string) file_get_contents($mediaIgnore)))
            : '';
        red_clean_starter_test_assert(
            is_dir($repositoryRoot . '/' . $mediaDirectory)
                && is_file($mediaIgnore)
                && $mediaIgnoreSource === "*\n!.gitignore",
            $mediaDirectory . ' ships empty and ignores installation-owned uploads'
        );
    }

    red_clean_starter_test_assert(
        !file_exists($repositoryRoot . '/addons'),
        'portable starter ships no client add-on package directory or executable package code'
    );

    $gitignore = red_clean_starter_test_source($repositoryRoot, '.gitignore');
    red_clean_starter_test_assert(
        preg_match('~^includes/config\.local\.php$~m', $gitignore) === 1,
        'server-local configuration remains excluded from releases'
    );

    $installer = red_clean_starter_test_source($repositoryRoot, 'db-structure.sql');
    foreach ([
        'touchingheart',
        'touching heart',
        'kids on the run',
        'roland kalt',
        'formbuy1',
        'continue to pay',
    ] as $clientMarker) {
        red_clean_starter_test_assert(
            stripos($installer, $clientMarker) === false,
            'installer excludes retained client or payment marker: ' . $clientMarker
        );
    }
    red_clean_starter_test_assert(
        str_contains($installer, "System_Active_Theme','starter-reference'")
            && str_contains($installer, "System_Previous_Theme','legacy-bootstrap'")
            && str_contains($installer, 'Thank you. Your response has been received.')
            && str_contains($installer, 'Thank you. Your registration has been received.'),
        'installer retains generic starter theme state and Form presets'
    );
    red_clean_starter_test_assert(
        str_contains($installer, 'CREATE TABLE `RED_Admin_Roles`')
            && str_contains($installer, 'CREATE TABLE `RED_Admin_Capabilities`')
            && !preg_match('/INSERT\\s+INTO\\s+`?RED_Admin_(?:Roles|Capabilities)`?/i', $installer),
        'starter ships empty Owner authorization tables with no assigned role or capability'
    );
    red_clean_starter_test_assert(
        str_contains($installer, 'CREATE TABLE `RED_Addon_Installations`')
            && str_contains($installer, 'CREATE TABLE `RED_Addon_Migrations`')
            && str_contains($installer, 'CREATE TABLE `RED_Addon_Activity_Log`')
            && preg_match(
                '/`Component`\\s+varchar\\(160\\).*NOT NULL/i',
                $installer
            ) === 1
            && !preg_match(
                '/INSERT\\s+INTO\\s+`?RED_Addon_(?:Installations|Migrations|Activity_Log)`?/i',
                $installer
            ),
        'starter ships empty add-on registry and full component-id storage with no package state, migration history, or lifecycle event'
    );

    $productionFiles = array_merge(
        glob($repositoryRoot . '/bin/*.php') ?: [],
        glob($repositoryRoot . '/includes/*.php') ?: [],
        [$repositoryRoot . '/db-structure.sql', $repositoryRoot . '/bin/MailHandler.ashx']
    );
    $thirdPartyMailFiles = array_fill_keys([
        'bin/DSNConfigurator.php',
        'bin/Exception.php',
        'bin/OAuth.php',
        'bin/OAuthTokenProvider.php',
        'bin/POP3.php',
        'bin/SMTP.php',
        'bin/phpmailer.php',
        'includes/config.local.php',
    ], true);
    $unexpectedEmails = [];
    foreach ($productionFiles as $absolutePath) {
        $relativePath = ltrim(str_replace($repositoryRoot, '', $absolutePath), '/');
        if (isset($thirdPartyMailFiles[$relativePath])) {
            continue;
        }
        $source = file_get_contents($absolutePath);
        if (!is_string($source)) {
            continue;
        }
        preg_match_all(
            '~[A-Z0-9._%+\-]+@([A-Z0-9.\-]+\.[A-Z]{2,})~i',
            $source,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as $match) {
            $domain = strtolower($match[1]);
            if (!in_array($domain, ['example.com', 'example.invalid', 'example.test'], true)) {
                $unexpectedEmails[] = $relativePath . ':' . $match[0];
            }
        }
    }
    red_clean_starter_test_assert(
        $unexpectedEmails === [],
        'runtime and installer sources contain no real recipient addresses'
            . ($unexpectedEmails ? ': ' . implode(', ', $unexpectedEmails) : '')
    );

    $runtimeConfig = red_clean_starter_test_source($repositoryRoot, 'includes/runtime_config_helpers.php');
    $configExample = red_clean_starter_test_source($repositoryRoot, 'includes/config.local.example.php');
    red_clean_starter_test_assert(
        str_contains($runtimeConfig, "getenv(\$envKey)")
            && str_contains($runtimeConfig, "config.local.php")
            && str_contains($configExample, "'LEGACY_MAIL_OWNER' => ''")
            && str_contains($configExample, "'PAYPAL_CONFIRMATION_FROM_EMAIL' => ''"),
        'outbound legacy mail settings stay server-local and default disabled'
    );
    require_once $repositoryRoot . '/includes/runtime_config_helpers.php';
    putenv('RED_CLEAN_STARTER_TEST_VALUE=environment-value');
    red_clean_starter_test_assert(
        red_config_value(
            'CLEAN_STARTER_TEST_VALUE',
            ['RED_CLEAN_STARTER_TEST_VALUE'],
            'default-value'
        ) === 'environment-value',
        'environment configuration remains available without loading the application or database'
    );
    putenv('RED_CLEAN_STARTER_TEST_VALUE');

    $legacyMail = red_clean_starter_test_source($repositoryRoot, 'bin/MailHandler.php');
    $legacyMailAlias = red_clean_starter_test_source($repositoryRoot, 'bat/MailHandler.php');
    $legacyAsp = red_clean_starter_test_source($repositoryRoot, 'bin/MailHandler.ashx');
    red_clean_starter_test_assert(
        str_contains($legacyMail, "red_config_value('LEGACY_MAIL_OWNER'")
            && !str_contains($legacyMail, 'red_legacy_mail_post("owner_email")')
            && str_contains($legacyMailAlias, "../bin/MailHandler.php")
            && !str_contains($legacyMailAlias, 'mail(')
            && str_contains($legacyAsp, 'StatusCode = 410')
            && !str_contains($legacyAsp, 'SmtpClient'),
        'legacy mail paths preserve their URLs while failing closed without server configuration'
    );

    $paypalEndpoint = red_clean_starter_test_source($repositoryRoot, 'bin/paypal_response.php');
    $paypalHelpers = red_clean_starter_test_source($repositoryRoot, 'includes/public_paypal_helpers.php');
    red_clean_starter_test_assert(
        str_contains($paypalEndpoint, 'RED_PAYPAL_CONFIRMATION_FROM_EMAIL')
            && str_contains($paypalEndpoint, 'FILTER_VALIDATE_EMAIL')
            && str_contains($paypalHelpers, 'Payment confirmation')
            && !str_contains($paypalHelpers, 'AddBCC')
            && !preg_match('~(?<!->)\bmail\s*\(~', $paypalHelpers),
        'PayPal confirmation mail is opt-in, generic, and has no fixed BCC or fallback'
    );
    require_once $repositoryRoot . '/includes/public_paypal_helpers.php';
    red_clean_starter_test_assert(
        red_public_paypal_parse_pdt("SUCCESS\nitem_name=Starter%20Access\n") === [
            'item_name' => 'Starter Access',
        ]
            && str_contains(
                red_public_paypal_confirmation_body('<Item>', '10.00', 'txn-1'),
                '&lt;Item&gt;'
            )
            && !red_public_paypal_send_confirmation(
                'payer@example.com',
                'Starter',
                'Customer',
                '<p>Confirmation</p>'
            ),
        'PayPal parsing escapes provider values and invokes no mail without a configured sender'
    );

    $apacheConfig = red_clean_starter_test_source($repositoryRoot, '.htaccess');
    red_clean_starter_test_assert(
        stripos($apacheConfig, 'demo.red-sphere.com') === false
            && stripos($apacheConfig, 'cpanel-generated') === false
            && stripos($apacheConfig, 'application/x-httpd-ea-php') === false,
        'tracked Apache rules contain no client domain or host-specific PHP handler'
    );

    $readme = red_clean_starter_test_source($repositoryRoot, 'README.md');
    $security = red_clean_starter_test_source($repositoryRoot, 'docs/SECURITY.md');
    red_clean_starter_test_assert(
        stripos($readme, 'under review') === false
            && str_contains($readme, 'pull request #2')
            && str_contains($security, 'Portable Starter Data'),
        'release and security documentation describe the merged portable baseline'
    );

    printf("Clean starter boundary self-test passed: %d assertions.\n", $assertions);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . ' (after ' . $assertions . " assertions)\n");
    exit(1);
}

?>
