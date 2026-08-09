<?php
/** Dependency-free checks for the core-internal secret resolver boundary. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/addon_secret_resolution_helpers.php';

$assertions = 0;

function red_addon_secret_resolution_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

try {
    $reference = 'config:redcms.fixture.api-key';
    $secondReference = 'config:redcms.fixture.webhook';
    $declarations = red_addon_secret_reference_declarations(
        [$reference, $secondReference],
        ''
    );
    $inventory = red_addon_secret_value_inventory(
        [$reference => 'local-fixture-secret'],
        json_encode([
            $secondReference => 'environment-fixture-secret',
        ], JSON_UNESCAPED_SLASHES)
    );
    red_addon_secret_resolution_test_assert(
        !empty($declarations['valid'])
            && !empty($inventory['valid'])
            && $inventory['valueCount'] === 2
            && $inventory['totalBytes'] === strlen('local-fixture-secret')
                + strlen('environment-fixture-secret'),
        'explicit declarations and separate local/environment inventories merge deterministically'
    );

    $resolvedValue = null;
    $resolved = red_addon_secret_resolve(
        $reference,
        $declarations,
        $inventory,
        $resolvedValue
    );
    red_addon_secret_resolution_test_assert(
        !empty($resolved['valid'])
            && !empty($resolved['resolved'])
            && $resolved['reason'] === 'resolved'
            && $resolvedValue === 'local-fixture-secret'
            && !array_key_exists('value', $resolved),
        'a declared reference resolves only through an internal by-reference value'
    );

    $environmentResolvedValue = null;
    $environmentResolved = red_addon_secret_resolve(
        $secondReference,
        $declarations,
        $inventory,
        $environmentResolvedValue
    );
    red_addon_secret_resolution_test_assert(
        !empty($environmentResolved['resolved'])
            && $environmentResolvedValue === 'environment-fixture-secret',
        'environment-provided values resolve through the same exact reference contract'
    );

    $undeclared = red_addon_secret_resolve(
        'config:redcms.fixture.missing',
        $declarations,
        $inventory
    );
    red_addon_secret_resolution_test_assert(
        empty($undeclared['valid'])
            && $undeclared['reason'] === 'reference_not_declared',
        'undeclared references are refused before value inventory lookup'
    );

    $missingValueDeclarations = red_addon_secret_reference_declarations(
        ['config:redcms.fixture.missing'],
        ''
    );
    $missingValue = red_addon_secret_resolve(
        'config:redcms.fixture.missing',
        $missingValueDeclarations,
        $inventory
    );
    red_addon_secret_resolution_test_assert(
        empty($missingValue['valid'])
            && $missingValue['reason'] === 'secret_unavailable',
        'declared but unprovisioned references fail closed'
    );

    $conflict = red_addon_secret_value_inventory(
        [$reference => 'local-fixture-secret'],
        json_encode([$reference => 'different-secret'])
    );
    red_addon_secret_resolution_test_assert(
        empty($conflict['valid'])
            && in_array('duplicate_value_conflict', $conflict['errors'], true),
        'conflicting local and environment values fail closed'
    );

    $invalidJson = red_addon_secret_value_inventory([], '[]');
    $invalidControl = red_addon_secret_value_inventory(
        [$reference => "bad\0value"],
        ''
    );
    red_addon_secret_resolution_test_assert(
        empty($invalidJson['valid'])
            && empty($invalidControl['valid']),
        'non-object environment values and NUL-bearing secrets are refused'
    );

    $source = file_get_contents(
        dirname(__DIR__) . '/includes/addon_secret_resolution_helpers.php'
    );
    red_addon_secret_resolution_test_assert(
        is_string($source)
            && !str_contains($source, 'mysqli_')
            && !str_contains($source, '$_POST')
            && !str_contains($source, 'addon.php')
            && !str_contains($source, 'eval('),
        'the resolver is database-free, request-free, and non-executing'
    );

    echo 'Add-on secret resolution self-test passed (' . $assertions
        . " assertions).\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
