<?php
/**
 * Dependency-free checks for target-free administrator create submissions.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot .
    '/includes/addon_admin_tool_form_create_helpers.php';

$assertions = 0;
$toolId = 'redcms.create-submission/products';
$formId = 'redcms.create-submission/product-editor';
$stateSha256 = hash('sha256', 'initial-state');

function red_addon_create_submission_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_create_submission_json(array $value)
{
    return json_encode(
        $value,
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR
    );
}

try {
    $body = red_addon_create_submission_json([
        'tool' => $toolId,
        'form' => $formId,
        'initialStateSha256' => $stateSha256,
        'values' => [
            'id' => 'shirt',
            'title' => 'Shirt',
            'price-minor' => 2500,
        ],
    ]);
    red_addon_create_submission_assert(
        red_addon_admin_tool_form_create_submission_decode($body) === [
            'tool' => $toolId,
            'form' => $formId,
            'initialStateSha256' => $stateSha256,
            'values' => [
                'id' => 'shirt',
                'title' => 'Shirt',
                'price-minor' => 2500,
            ],
        ],
        'one canonical target-free create body decodes exactly'
    );

    $editShape = red_addon_create_submission_json([
        'tool' => $toolId,
        'form' => $formId,
        'targetRecordId' => 41,
        'currentStateSha256' => $stateSha256,
        'values' => [],
    ]);
    red_addon_create_submission_assert(
        red_addon_admin_tool_form_create_submission_decode($editShape) === null,
        'edit identity and current-state evidence cannot enter creation'
    );

    $extra = json_decode($body, true, 12, JSON_THROW_ON_ERROR);
    $extra['package'] = 'redcms.create-submission';
    $wrongState = json_decode($body, true, 12, JSON_THROW_ON_ERROR);
    $wrongState['initialStateSha256'] = 'not-a-hash';
    red_addon_create_submission_assert(
        red_addon_admin_tool_form_create_submission_decode(
            red_addon_create_submission_json($extra)
        ) === null
            && red_addon_admin_tool_form_create_submission_decode(
                red_addon_create_submission_json($wrongState)
            ) === null
            && red_addon_admin_tool_form_create_submission_decode(
                "\n" . $body
            ) === null,
        'unknown identity, malformed state, and noncanonical JSON fail closed'
    );

    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, $body);
    rewind($stream);
    $transport = red_addon_admin_tool_form_submission_read_body(
        'application/json',
        (string) strlen($body),
        $stream
    );
    fclose($stream);
    red_addon_create_submission_assert(
        ($transport['valid'] ?? false) === true
            && ($transport['rawBody'] ?? '') === $body,
        'the existing exact JSON transport boundary is reusable for creation'
    );

    $plan = [
        'package' => 'redcms.create-submission',
        'tool' => $toolId,
        'form' => $formId,
        'actorRecordId' => 41,
        'permission' => 'store.products.manage',
        'contractSha256' => hash('sha256', 'contract'),
        'runtimeSettingsSha256' => hash('sha256', 'settings'),
        'initialStateSha256' => $stateSha256,
        'submittedValuesSha256' => hash('sha256', 'values'),
    ];
    $planSha256 =
        red_addon_admin_tool_form_create_submission_plan_hash($plan);
    red_addon_create_submission_assert(
        red_addon_valid_sha256($planSha256)
            && hash_equals(
                $planSha256,
                red_addon_admin_tool_form_create_submission_plan_hash($plan)
            )
            && !hash_equals(
                $planSha256,
                red_addon_admin_tool_form_create_submission_plan_hash(
                    array_merge($plan, ['actorRecordId' => 42])
                )
            )
            && !array_key_exists('targetRecordId', $plan),
        'creation plan evidence is deterministic and actor-bound without a target id'
    );

    red_addon_create_submission_assert(
        red_addon_admin_tool_form_create_submission_public_result([
            'prepared' => true,
        ]) === [
            'httpStatus' => 200,
            'body' => ['ok' => true, 'status' => 'validated'],
        ]
            && red_addon_admin_tool_form_create_submission_public_result([
                'reason' => 'state_conflict',
            ]) === [
                'httpStatus' => 409,
                'body' => ['ok' => false, 'reason' => 'state_conflict'],
            ],
        'public results expose only bounded validation status and reason'
    );

    $settings = new RED_Addon_Admin_Tool_Form_Runtime_Settings(
        ['store.currency' => 'USD'],
        hash('sha256', 'create-runtime-settings')
    );
    $request = new RED_Addon_Admin_Tool_Form_Create_Request(
        'redcms.create-submission',
        '1.0.0',
        $toolId,
        $formId,
        41,
        $stateSha256,
        $planSha256,
        ['id' => 'shirt'],
        $settings
    );
    red_addon_create_submission_assert(
        $request->package() === 'redcms.create-submission'
            && $request->packageVersion() === '1.0.0'
            && $request->tool() === $toolId
            && $request->form() === $formId
            && $request->actorRecordId() === 41
            && $request->initialStateSha256() === $stateSha256
            && $request->planSha256() === $planSha256
            && $request->values() === ['id' => 'shirt']
            && $request->runtimeSettings() === $settings,
        'creator receives one immutable server-derived typed request'
    );

    $created = RED_Addon_Admin_Tool_Form_Created_Record::created(43);
    $invalidRecordRefused = false;
    try {
        RED_Addon_Admin_Tool_Form_Created_Record::created(0);
    } catch (InvalidArgumentException $exception) {
        $invalidRecordRefused = true;
    }
    red_addon_create_submission_assert(
        $created->recordId() === 43 && $invalidRecordRefused,
        'creator results contain exactly one bounded positive record id'
    );

    $source = (string) file_get_contents(
        $projectRoot .
            '/includes/addon_admin_tool_form_create_submission_helpers.php'
    );
    red_addon_create_submission_assert(
        !str_contains($source, '$_POST')
            && !str_contains($source, '$_GET')
            && !str_contains($source, '$_SESSION')
            && !str_contains($source, 'adminToolFormCreators')
            && !str_contains($source, 'begin_transaction')
            && !str_contains($source, 'insert_id'),
        'the adapter has no request globals, creator lookup, transaction, or record allocation'
    );
    $createSource = (string) file_get_contents(
        $projectRoot . '/includes/addon_admin_tool_form_create_helpers.php'
    );
    red_addon_create_submission_assert(
        !str_contains($createSource, '$_POST')
            && !str_contains($createSource, '$_GET')
            && !str_contains($createSource, '$_SESSION')
            && !str_contains($createSource, 'adminToolFormWriters')
            && !str_contains($createSource, 'insert_id')
            && !is_file(
                $projectRoot . '/admin/bin/create_addon_tool_form.php'
            ),
        'the atomic runner accepts no request globals, writer fallback, caller id allocation, or endpoint'
    );

    printf(
        "Add-on administrator form create-submission self-test passed (%d assertions).\n",
        $assertions
    );
} catch (Throwable $throwable) {
    fwrite(
        STDERR,
        $throwable->getMessage() . ' (after ' . $assertions . " assertions)\n"
    );
    exit(1);
}
