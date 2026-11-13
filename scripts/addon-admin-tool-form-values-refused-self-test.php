<?php
/**
 * Dependency-free checks for add-on form refusals that carry
 * administrator-fixable problems instead of a generic failure.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/addon_admin_tool_form_create_helpers.php';
require_once $projectRoot . '/includes/addon_admin_tool_form_endpoint_helpers.php';

$assertions = 0;

function red_addon_values_refused_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$refused = new RED_Addon_Admin_Tool_Form_Values_Refused([
    ['field' => 'sku', 'message' => "SKU is required.\nUse CAFE-500G."],
    ['field' => 'Bad Key', 'message' => 'dropped: invalid field key'],
    ['field' => 'price-minor', 'message' => ''],
    ['field' => 'price-minor', 'message' => str_repeat('x', 400)],
    'not-an-array',
]);
$problems = $refused->problems();
red_addon_values_refused_assert(
    count($problems) === 2
        && $problems[0] === [
            'field' => 'sku',
            'message' => 'SKU is required. Use CAFE-500G.',
        ]
        && $problems[1]['field'] === 'price-minor'
        && mb_strlen($problems[1]['message'], 'UTF-8')
            === RED_Addon_Admin_Tool_Form_Values_Refused::MAX_MESSAGE_LENGTH
        && $refused instanceof RuntimeException,
    'problems are bounded, single-line, and keyed by valid field keys'
);
$many = [];
for ($index = 0; $index < 20; $index++) {
    $many[] = ['field' => 'sku', 'message' => 'Problem ' . $index];
}
red_addon_values_refused_assert(
    count((new RED_Addon_Admin_Tool_Form_Values_Refused($many))->problems())
        === RED_Addon_Admin_Tool_Form_Values_Refused::MAX_PROBLEMS,
    'at most eight problems are kept'
);

$settings = new RED_Addon_Admin_Tool_Form_Runtime_Settings(
    ['store.currency' => 'COP'],
    hash('sha256', 'refusal-runtime-settings')
);
$createRequest = new RED_Addon_Admin_Tool_Form_Create_Request(
    'redcms.refusal-fixture',
    '1.0.0',
    'redcms.refusal-fixture/products',
    'redcms.refusal-fixture/product-editor',
    41,
    hash('sha256', 'initial'),
    hash('sha256', 'plan'),
    ['sku' => ''],
    $settings
);
$createProblems = ['stale'];
$created = red_addon_admin_tool_form_create_invoke(
    static function () {
        throw new RED_Addon_Admin_Tool_Form_Values_Refused([
            ['field' => 'sku', 'message' => 'SKU is required.'],
        ]);
    },
    null,
    $createRequest,
    $createProblems
);
red_addon_values_refused_assert(
    $created === null
        && $createProblems === [
            ['field' => 'sku', 'message' => 'SKU is required.'],
        ],
    'creator refusal returns no record and hands its problems to core'
);
$genericProblems = ['stale'];
$created = red_addon_admin_tool_form_create_invoke(
    static function () {
        throw new RuntimeException('internal detail must not leak');
    },
    null,
    $createRequest,
    $genericProblems
);
red_addon_values_refused_assert(
    $created === null && $genericProblems === [],
    'any other creator failure carries no problems'
);
$created = red_addon_admin_tool_form_create_invoke(
    static function () {
        return RED_Addon_Admin_Tool_Form_Created_Record::created(7);
    },
    null,
    $createRequest
);
red_addon_values_refused_assert(
    $created instanceof RED_Addon_Admin_Tool_Form_Created_Record
        && $created->recordId() === 7,
    'existing three-argument invocation still works'
);

$writeRequest = new RED_Addon_Admin_Tool_Form_Write_Request(
    'redcms.refusal-fixture',
    '1.0.0',
    'redcms.refusal-fixture/products',
    'redcms.refusal-fixture/product-editor',
    41,
    5,
    hash('sha256', 'previous'),
    hash('sha256', 'plan'),
    ['sku' => ''],
    $settings
);
$writeProblems = null;
$written = red_addon_admin_tool_form_write_invoke(
    static function () {
        throw new RED_Addon_Admin_Tool_Form_Values_Refused([
            ['field' => 'price-minor', 'message' => 'Price is required.'],
        ]);
    },
    null,
    $writeRequest,
    $writeProblems
);
red_addon_values_refused_assert(
    $written === false
        && $writeProblems === [
            ['field' => 'price-minor', 'message' => 'Price is required.'],
        ],
    'writer refusal fails the save and hands its problems to core'
);

$createFailure = red_addon_admin_tool_form_create_failure(
    'values_refused',
    [['field' => 'sku', 'message' => 'SKU is required.']]
);
$saveFailure = red_addon_admin_tool_form_save_failure(
    'values_refused',
    [['field' => 'sku', 'message' => 'SKU is required.']]
);
$leakFailure = red_addon_admin_tool_form_create_failure(
    'creator_failed',
    [['field' => 'sku', 'message' => 'must not be exposed']]
);
red_addon_values_refused_assert(
    $createFailure === [
        'httpStatus' => 422,
        'body' => [
            'ok' => false,
            'reason' => 'invalid_values',
            'problems' => [['field' => 'sku', 'message' => 'SKU is required.']],
        ],
    ]
        && $saveFailure['httpStatus'] === 422
        && $saveFailure['body']['reason'] === 'invalid_values'
        && $saveFailure['body']['problems'] === $createFailure['body']['problems']
        && $leakFailure === [
            'httpStatus' => 422,
            'body' => ['ok' => false, 'reason' => 'create_failed'],
        ]
        && red_addon_admin_tool_form_create_failure('creator_failed') === $leakFailure,
    'only values_refused exposes problems; other outcomes are unchanged'
);

$script = (string) file_get_contents(
    $projectRoot . '/admin/assets/js/addon-admin-tool-form.js'
);
red_addon_values_refused_assert(
    str_contains($script, 'error.problems = Array.isArray(data.problems)')
        && str_contains($script, "reason === 'invalid_values' && messages.length")
        && !str_contains($script, 'innerHTML = messages'),
    'the form shows refusal messages as text'
);

printf(
    "Add-on administrator form values-refused self-test passed (%d assertions).\n",
    $assertions
);
