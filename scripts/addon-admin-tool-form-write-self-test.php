<?php
/**
 * Disposable checks for atomic administrator add-on form persistence.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/addon_admin_tool_form_write_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_admin_tool_form_write|rev_base)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Add-on administrator form writer refused non-disposable database: '
            . DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$actorId = 2147000964;
$targetId = 61;
$packageId = 'redcms.tool-form-write-fixture';
$toolId = $packageId . '/products';
$formId = $packageId . '/product-editor';
$permission = 'fixture.products.manage';
$table = 'RED_Addon_Admin_Form_Write_Fixture';
$auditConstraint = 'redcms_addon_form_write_audit_fail';
$calls = ['tool' => 0, 'loader' => 0, 'writer' => 0];
$writerMode = 'success';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_admin_form_write_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_admin_form_write_test_execute(
    $connection,
    $sql,
    $types = '',
    array $values = []
) {
    $statement = mysqli_prepare($connection, $sql);
    if (!$statement) {
        throw new RuntimeException('Could not prepare form-writer fixture SQL.');
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($statement, $types, ...$values);
    }
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Form-writer fixture SQL failed: ' . $error);
    }
    mysqli_stmt_close($statement);
}

function red_addon_admin_form_write_test_scalar(
    $connection,
    $sql,
    $types = '',
    array $values = []
) {
    $statement = mysqli_prepare($connection, $sql);
    if (!$statement) {
        throw new RuntimeException('Could not prepare form-writer scalar SQL.');
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($statement, $types, ...$values);
    }
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Form-writer scalar SQL failed: ' . $error);
    }
    $query = mysqli_stmt_get_result($statement);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    mysqli_stmt_close($statement);
    return $row[0] ?? null;
}

function red_addon_admin_form_write_test_manifest(
    $packageId,
    $toolId,
    $formId,
    $permission,
    $description = 'Edit one bounded product.'
) {
    return [
        'id' => $packageId,
        'provides' => [
            'components' => [],
            'services' => [],
            'adminTools' => [$toolId],
            'adapters' => [],
        ],
        'permissions' => [$permission],
        'adminToolContracts' => [[
            'tool' => $toolId,
            'label' => 'Products',
            'description' => 'Review products.',
            'icon' => 'products',
            'permission' => $permission,
            'mode' => 'read-only',
        ]],
        'adminToolFormContracts' => [[
            'tool' => $toolId,
            'form' => $formId,
            'label' => 'Product editor',
            'description' => $description,
            'permission' => $permission,
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/json',
            'maxBodyBytes' => 8192,
            'fields' => [
                [
                    'key' => 'id',
                    'label' => 'Identifier',
                    'type' => 'text',
                    'required' => true,
                    'minLength' => 1,
                    'maxLength' => 64,
                ],
                [
                    'key' => 'price-minor',
                    'label' => 'Price',
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 0,
                    'maximum' => 999999999,
                ],
                [
                    'key' => 'options',
                    'label' => 'Options',
                    'type' => 'collection',
                    'required' => false,
                    'itemLabel' => 'Option',
                    'minItems' => 0,
                    'maxItems' => 3,
                    'fields' => [
                        [
                            'key' => 'key',
                            'label' => 'Key',
                            'type' => 'text',
                            'required' => true,
                            'minLength' => 1,
                            'maxLength' => 32,
                        ],
                        [
                            'key' => 'values',
                            'label' => 'Values',
                            'type' => 'collection',
                            'required' => true,
                            'itemLabel' => 'Value',
                            'minItems' => 1,
                            'maxItems' => 16,
                            'fields' => [[
                                'key' => 'label',
                                'label' => 'Label',
                                'type' => 'text',
                                'required' => true,
                                'minLength' => 1,
                                'maxLength' => 80,
                            ]],
                        ],
                    ],
                ],
            ],
        ]],
        'routes' => [],
    ];
}

function red_addon_admin_form_write_test_values($connection, $targetId)
{
    $statement = mysqli_prepare(
        $connection,
        'SELECT ProductKey, PriceMinor, OptionsJSON
         FROM RED_Addon_Admin_Form_Write_Fixture
         WHERE RecordID=? LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 'i', $targetId);
    mysqli_stmt_execute($statement);
    $query = mysqli_stmt_get_result($statement);
    $row = $query ? mysqli_fetch_assoc($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    mysqli_stmt_close($statement);
    $options = is_string($row['OptionsJSON'] ?? null)
        ? json_decode($row['OptionsJSON'], true)
        : null;
    if (!is_array($row) || !is_array($options) || !array_is_list($options)) {
        throw new RuntimeException('Form-writer fixture product is unavailable.');
    }
    return [
        'id' => $row['ProductKey'],
        'price-minor' => (int) $row['PriceMinor'],
        'options' => $options,
    ];
}

function red_addon_admin_form_write_test_update(
    $connection,
    $targetId,
    array $values,
    $partial = false,
    $wrong = false
) {
    $price = (int) $values['price-minor'];
    if ($partial) {
        red_addon_admin_form_write_test_execute(
            $connection,
            'UPDATE RED_Addon_Admin_Form_Write_Fixture
             SET ProductKey=? WHERE RecordID=?',
            'si',
            [$values['id'], $targetId]
        );
        return;
    }
    if ($wrong) {
        $price++;
    }
    $options = json_encode(
        $values['options'],
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
    );
    red_addon_admin_form_write_test_execute(
        $connection,
        'UPDATE RED_Addon_Admin_Form_Write_Fixture
         SET ProductKey=?, PriceMinor=?, OptionsJSON=? WHERE RecordID=?',
        'sisi',
        [$values['id'], $price, $options, $targetId]
    );
}

function red_addon_admin_form_write_test_context(
    array $manifest,
    $packageId,
    $toolId,
    $formId,
    $table,
    $withWriter
) {
    global $calls, $writerMode;
    $registry = new RED_Addon_Runtime_Registry($packageId, $manifest);
    $registry->registerAdminTool(
        $toolId,
        static function () use (&$calls) {
            $calls['tool']++;
            throw new RuntimeException('Display tool must remain uninvoked.');
        }
    );
    $registry->registerAdminToolFormValueLoader(
        $formId,
        static function ($connection, $request) use (&$calls) {
            $calls['loader']++;
            return RED_Addon_Admin_Tool_Form_Values::current(
                red_addon_admin_form_write_test_values(
                    $connection,
                    $request->targetRecordId()
                )
            );
        }
    );
    if ($withWriter) {
        $registry->registerAdminToolFormWriter(
            $formId,
            static function ($connection, $request) use (&$calls, &$writerMode) {
                $calls['writer']++;
                if (!$request instanceof RED_Addon_Admin_Tool_Form_Write_Request) {
                    throw new RuntimeException('Writer request type is invalid.');
                }
                if ($request->runtimeSettings()->values() !== []
                    || !red_addon_valid_sha256(
                        $request->runtimeSettings()->stateSha256()
                    )
                ) {
                    throw new RuntimeException(
                        'Writer runtime settings are invalid.'
                    );
                }
                $values = $request->values();
                red_addon_admin_form_write_test_update(
                    $connection,
                    $request->targetRecordId(),
                    $values,
                    $writerMode === 'partial',
                    $writerMode === 'wrong'
                );
                if ($writerMode === 'throw') {
                    throw new RuntimeException('Forced writer failure.');
                }
                if ($writerMode === 'output') {
                    echo 'unexpected writer output';
                } elseif ($writerMode === 'buffer') {
                    ob_start();
                } elseif ($writerMode === 'http') {
                    http_response_code(418);
                }
                return $writerMode !== 'false';
            },
            [$table]
        );
    }
    $registry->assertComplete();
    return new RED_Addon_Runtime_Context(
        [$packageId],
        [$packageId => $registry]
    );
}

function red_addon_admin_form_write_test_encode(array $payload)
{
    return json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR
    );
}

function red_addon_admin_form_write_test_payload(
    $connection,
    $toolId,
    $formId,
    $targetId,
    $actorId,
    $price
) {
    $loaded = red_addon_admin_tool_form_load_values(
        $connection,
        $toolId,
        $formId,
        $targetId,
        $actorId
    );
    if (($loaded['loaded'] ?? false) !== true) {
        throw new RuntimeException('Could not load form-writer fixture values.');
    }
    $values = $loaded['values'];
    $values['price-minor'] = $price;
    return red_addon_admin_form_write_test_encode([
        'tool' => $toolId,
        'form' => $formId,
        'targetRecordId' => $targetId,
        'currentStateSha256' => $loaded['stateSha256'],
        'values' => $values,
    ]);
}

function red_addon_admin_form_write_test_fingerprint($connection, $targetId)
{
    return (string) red_addon_admin_form_write_test_scalar(
        $connection,
        "SELECT CONCAT_WS(':', ProductKey, PriceMinor, OptionsJSON)
         FROM RED_Addon_Admin_Form_Write_Fixture WHERE RecordID=?",
        'i',
        [$targetId]
    );
}

function red_addon_admin_form_write_test_cleanup(
    $connection,
    $packageId,
    $actorId,
    $auditConstraint
) {
    try {
        mysqli_query(
            $connection,
            'ALTER TABLE RED_Addon_Activity_Log DROP CHECK `'
                . $auditConstraint . '`'
        );
    } catch (Throwable $throwable) {
        // The disposable failure constraint is absent on ordinary paths.
    }
    try {
        red_addon_admin_form_write_test_execute(
            $connection,
            'DELETE FROM RED_Addon_Activity_Log WHERE PackageID=?',
            's',
            [$packageId]
        );
        red_addon_admin_form_write_test_execute(
            $connection,
            'DELETE FROM RED_Addon_Installations WHERE PackageID=?',
            's',
            [$packageId]
        );
        red_addon_admin_form_write_test_execute(
            $connection,
            'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
            'i',
            [$actorId]
        );
        red_addon_admin_form_write_test_execute(
            $connection,
            'DELETE FROM RED_Admin_Roles WHERE AdminRecordID=?',
            'i',
            [$actorId]
        );
        red_addon_admin_form_write_test_execute(
            $connection,
            'DELETE FROM RED_Admin WHERE RecordID=?',
            'i',
            [$actorId]
        );
        mysqli_query(
            $connection,
            'DROP TABLE IF EXISTS RED_Addon_Admin_Form_Write_Fixture'
        );
    } catch (Throwable $throwable) {
        error_log('Administrator form-writer fixture cleanup failed.');
    }
}

try {
    red_addon_admin_form_write_test_cleanup(
        $connection,
        $packageId,
        $actorId,
        $auditConstraint
    );
    red_addon_admin_form_write_test_execute(
        $connection,
        'CREATE TABLE RED_Addon_Admin_Form_Write_Fixture (
            RecordID INT NOT NULL PRIMARY KEY,
            ProductKey VARCHAR(64) NOT NULL,
            PriceMinor INT NOT NULL,
            OptionsJSON TEXT NOT NULL
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $options = json_encode(
        [[
            'key' => 'size',
            'values' => [['label' => 'Small'], ['label' => 'Large']],
        ]],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    red_addon_admin_form_write_test_execute(
        $connection,
        'INSERT INTO RED_Addon_Admin_Form_Write_Fixture
            (RecordID, ProductKey, PriceMinor, OptionsJSON)
         VALUES (?, \'shirt\', 2400, ?)',
        'is',
        [$targetId, $options]
    );
    $password = password_hash('AddonFormWrite-2026!', PASSWORD_DEFAULT);
    red_addon_admin_form_write_test_execute(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_addon_form_write', ?, 'Admin',
            'FormWrite', 'webmaster', '', '', 'form-write@example.test',
            'N', 'to', 'N', 'to')",
        'is',
        [$actorId, $password]
    );
    red_addon_admin_form_write_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Roles
            (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES (?, \'owner\', ?)',
        'ii',
        [$actorId, $actorId]
    );
    red_addon_admin_form_write_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities
            (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES (?, ?, ?)',
        'isi',
        [$actorId, $permission, $actorId]
    );
    red_addon_admin_form_write_test_execute(
        $connection,
        'INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType,
            ManifestSHA256, InventorySHA256, LifecycleState,
            InstalledByAdminRecordID, UpdatedByAdminRecordID
         ) VALUES (?, \'0.1.0\', \'component\', ?, ?, \'enabled\', ?, ?)',
        'sssii',
        [$packageId, str_repeat('a', 64), str_repeat('b', 64), $actorId, $actorId]
    );

    $manifest = red_addon_admin_form_write_test_manifest(
        $packageId,
        $toolId,
        $formId,
        $permission
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_admin_form_write_test_context(
            $manifest,
            $packageId,
            $toolId,
            $formId,
            $table,
            false
        );
    $rawPayload = red_addon_admin_form_write_test_payload(
        $connection,
        $toolId,
        $formId,
        $targetId,
        $actorId,
        2600
    );
    $validation = red_addon_admin_tool_form_submission_prepare(
        $connection,
        $rawPayload,
        $actorId
    );
    $missingWriter = red_addon_admin_tool_form_write_preflight(
        $connection,
        $rawPayload,
        $actorId
    );
    red_addon_admin_form_write_test_assert(
        $validation['prepared'] === true
            && empty($missingWriter['prepared'])
            && $missingWriter['reason'] === 'writer_unavailable'
            && $calls['writer'] === 0
            && red_addon_admin_form_write_test_fingerprint(
                $connection,
                $targetId
            ) === 'shirt:2400:' . $options,
        'validation remains read-only and missing exact writer registration refuses mutation'
    );

    foreach (
        [
            [$formId, ['RED_Addon_Installations']],
            [$formId, [$table, $table]],
            [$packageId . '/undeclared', [$table]],
        ] as [$candidateForm, $candidateTables]
    ) {
        $registry = new RED_Addon_Runtime_Registry($packageId, $manifest);
        $refused = false;
        try {
            $registry->registerAdminToolFormWriter(
                $candidateForm,
                static function () {
                    return true;
                },
                $candidateTables
            );
        } catch (Throwable $throwable) {
            $refused = true;
        }
        red_addon_admin_form_write_test_assert(
            $refused,
            'reserved, duplicate, and undeclared writer registrations fail closed'
        );
    }

    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_admin_form_write_test_context(
            $manifest,
            $packageId,
            $toolId,
            $formId,
            $table,
            true
        );
    red_addon_admin_form_write_test_assert(
        red_addon_runtime_owner('adminToolFormWriters', $formId) === $packageId
            && red_addon_admin_tool_form_write_tables($formId) === [$table],
        'one exact form writer owner carries one sorted package-table declaration'
    );

    $preflight = red_addon_admin_tool_form_write_preflight(
        $connection,
        $rawPayload,
        $actorId
    );
    $repeatPreflight = red_addon_admin_tool_form_write_preflight(
        $connection,
        $rawPayload,
        $actorId
    );
    red_addon_admin_form_write_test_assert(
        $preflight['authorized'] === true
            && $preflight['prepared'] === true
            && $preflight['packageVersion'] === '0.1.0'
            && $preflight['submissionPlanSha256']
                === $validation['planSha256']
            && red_addon_valid_sha256($preflight['planSha256'])
            && $preflight['reason'] === 'preflight_ready'
            && !array_key_exists('values', $preflight),
        'write preflight binds validation evidence, package version, tables, actor, and target without returning values'
    );
    red_addon_admin_form_write_test_assert(
        $repeatPreflight['planSha256'] === $preflight['planSha256'],
        'identical current state and writer binding produce deterministic write evidence'
    );

    $callsBeforeRefusal = $calls;
    $mismatch = red_addon_admin_tool_form_write(
        $connection,
        $rawPayload,
        $actorId,
        str_repeat('f', 64)
    );
    red_addon_admin_form_write_test_assert(
        empty($mismatch['executed'])
            && $mismatch['reason'] === 'plan_mismatch'
            && $calls['writer'] === $callsBeforeRefusal['writer'],
        'substituted write plan refuses before writer invocation'
    );

    mysqli_begin_transaction($connection);
    $nested = red_addon_admin_tool_form_write(
        $connection,
        $rawPayload,
        $actorId,
        $preflight['planSha256']
    );
    mysqli_rollback($connection);
    red_addon_admin_form_write_test_assert(
        empty($nested['executed'])
            && $nested['reason'] === 'transaction_already_active',
        'caller-owned transactions are refused'
    );

    red_addon_admin_form_write_test_execute(
        $connection,
        "UPDATE RED_Addon_Installations SET PackageVersion='0.1.1'
         WHERE PackageID=?",
        's',
        [$packageId]
    );
    $versionDrift = red_addon_admin_tool_form_write(
        $connection,
        $rawPayload,
        $actorId,
        $preflight['planSha256']
    );
    red_addon_admin_form_write_test_execute(
        $connection,
        "UPDATE RED_Addon_Installations SET PackageVersion='0.1.0'
         WHERE PackageID=?",
        's',
        [$packageId]
    );
    red_addon_admin_form_write_test_assert(
        empty($versionDrift['executed'])
            && $versionDrift['reason'] === 'plan_mismatch'
            && $calls['writer'] === $callsBeforeRefusal['writer'],
        'package-version drift invalidates previously prepared write evidence'
    );

    $executed = red_addon_admin_tool_form_write(
        $connection,
        $rawPayload,
        $actorId,
        $preflight['planSha256']
    );
    red_addon_admin_form_write_test_assert(
        $executed['executed'] === true
            && empty($executed['unchanged'])
            && $executed['writerInvoked'] === true
            && $executed['previousStateSha256']
                === $validation['currentStateSha256']
            && red_addon_valid_sha256($executed['stateSha256'])
            && $executed['reason'] === 'executed'
            && $calls['writer'] === 1,
        'locked runner invokes the exact writer once and verifies the reloaded state'
    );
    red_addon_admin_form_write_test_assert(
        str_starts_with(
            red_addon_admin_form_write_test_fingerprint($connection, $targetId),
            'shirt:2600:'
        )
            && (int) red_addon_admin_form_write_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID=? AND EventName='addon.form.saved'
                   AND ActorAdminRecordID=? AND Result='succeeded'
                   AND DetailCode='form_saved'",
                'si',
                [$packageId, $actorId]
            ) === 1,
        'package mutation and one value-free core audit fact commit together'
    );

    $replay = red_addon_admin_tool_form_write(
        $connection,
        $rawPayload,
        $actorId,
        $preflight['planSha256']
    );
    red_addon_admin_form_write_test_assert(
        empty($replay['executed'])
            && $replay['reason'] === 'state_conflict'
            && $calls['writer'] === 1,
        'a changed submission plan cannot be replayed after its state becomes stale'
    );

    $unchangedBody = red_addon_admin_form_write_test_payload(
        $connection,
        $toolId,
        $formId,
        $targetId,
        $actorId,
        2600
    );
    $unchangedPlan = red_addon_admin_tool_form_write_preflight(
        $connection,
        $unchangedBody,
        $actorId
    );
    $unchanged = red_addon_admin_tool_form_write(
        $connection,
        $unchangedBody,
        $actorId,
        $unchangedPlan['planSha256']
    );
    red_addon_admin_form_write_test_assert(
        $unchanged['unchanged'] === true
            && empty($unchanged['executed'])
            && empty($unchanged['writerInvoked'])
            && $calls['writer'] === 1,
        'unchanged normalized values roll back without writer or audit work'
    );

    $failureBody = red_addon_admin_form_write_test_payload(
        $connection,
        $toolId,
        $formId,
        $targetId,
        $actorId,
        2700
    );
    $failurePlan = red_addon_admin_tool_form_write_preflight(
        $connection,
        $failureBody,
        $actorId
    );
    $failureBaseline = red_addon_admin_form_write_test_fingerprint(
        $connection,
        $targetId
    );
    foreach (
        [
            'throw' => 'writer_failed',
            'output' => 'writer_failed',
            'buffer' => 'writer_failed',
            'http' => 'writer_failed',
            'false' => 'writer_failed',
            'partial' => 'postcondition_failed',
            'wrong' => 'postcondition_failed',
        ] as $mode => $expectedReason
    ) {
        $writerMode = $mode;
        $failed = red_addon_admin_tool_form_write(
            $connection,
            $failureBody,
            $actorId,
            $failurePlan['planSha256']
        );
        red_addon_admin_form_write_test_assert(
            empty($failed['executed'])
                && $failed['reason'] === $expectedReason
                && red_addon_admin_form_write_test_fingerprint(
                    $connection,
                    $targetId
                ) === $failureBaseline,
            'writer failure and incomplete or wrong postconditions roll back exactly'
        );
    }
    $writerMode = 'success';

    red_addon_admin_form_write_test_execute(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
        'i',
        [$actorId]
    );
    $revoked = red_addon_admin_tool_form_write(
        $connection,
        $failureBody,
        $actorId,
        $failurePlan['planSha256']
    );
    red_addon_admin_form_write_test_assert(
        empty($revoked['authorized'])
            && empty($revoked['writerInvoked'])
            && $revoked['reason'] === 'permission_denied'
            && red_addon_admin_form_write_test_fingerprint(
                $connection,
                $targetId
            ) === $failureBaseline,
        'fresh permission revocation refuses before writer invocation'
    );
    red_addon_admin_form_write_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities
            (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES (?, ?, ?)',
        'isi',
        [$actorId, $permission, $actorId]
    );

    red_addon_admin_form_write_test_execute(
        $connection,
        'DELETE FROM RED_Addon_Activity_Log WHERE PackageID=?',
        's',
        [$packageId]
    );
    red_addon_admin_form_write_test_execute(
        $connection,
        "ALTER TABLE RED_Addon_Activity_Log
         ADD CONSTRAINT `$auditConstraint`
         CHECK (EventName <> 'addon.form.saved')"
    );
    $auditFailed = red_addon_admin_tool_form_write(
        $connection,
        $failureBody,
        $actorId,
        $failurePlan['planSha256']
    );
    red_addon_admin_form_write_test_execute(
        $connection,
        "ALTER TABLE RED_Addon_Activity_Log
         DROP CHECK `$auditConstraint`"
    );
    red_addon_admin_form_write_test_assert(
        empty($auditFailed['executed'])
            && $auditFailed['reason'] === 'audit_failed'
            && red_addon_admin_form_write_test_fingerprint(
                $connection,
                $targetId
            ) === $failureBaseline,
        'forced core audit failure rolls package mutation back atomically'
    );

    red_addon_admin_form_write_test_execute(
        $connection,
        'ALTER TABLE RED_Addon_Admin_Form_Write_Fixture ENGINE=MyISAM'
    );
    $unsupported = red_addon_admin_tool_form_write_preflight(
        $connection,
        $failureBody,
        $actorId
    );
    red_addon_admin_form_write_test_execute(
        $connection,
        'ALTER TABLE RED_Addon_Admin_Form_Write_Fixture ENGINE=InnoDB'
    );
    red_addon_admin_form_write_test_assert(
        empty($unsupported['prepared'])
            && $unsupported['reason'] === 'transaction_unsupported',
        'non-InnoDB declared package storage refuses before mutation'
    );

    $driftedManifest = red_addon_admin_form_write_test_manifest(
        $packageId,
        $toolId,
        $formId,
        $permission,
        'Edit one bounded product with drift.'
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_admin_form_write_test_context(
            $driftedManifest,
            $packageId,
            $toolId,
            $formId,
            $table,
            true
        );
    $drifted = red_addon_admin_tool_form_write(
        $connection,
        $failureBody,
        $actorId,
        $failurePlan['planSha256']
    );
    red_addon_admin_form_write_test_assert(
        empty($drifted['executed'])
            && in_array($drifted['reason'], ['state_conflict', 'plan_mismatch'], true)
            && red_addon_admin_form_write_test_fingerprint(
                $connection,
                $targetId
            ) === $failureBaseline,
        'contract drift invalidates old state and write evidence without mutation'
    );

    $helperSource = (string) file_get_contents(
        $projectRoot . '/includes/addon_admin_tool_form_write_helpers.php'
    );
    $endpointSource = (string) file_get_contents(
        $projectRoot . '/admin/bin/validate_addon_tool_form.php'
    );
    red_addon_admin_form_write_test_assert(
        !str_contains($helperSource, '$_POST')
            && !str_contains($helperSource, '$_GET')
            && !str_contains($helperSource, '$_SESSION')
            && !str_contains($helperSource, 'php://input')
            && !str_contains(
                $endpointSource,
                'addon_admin_tool_form_write_helpers.php'
            )
            && !str_contains(
                $endpointSource,
                'red_addon_admin_tool_form_write('
            ),
        'atomic writer remains internal and disconnected from request globals, endpoint, and UI'
    );
} finally {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_addon_admin_form_write_test_cleanup(
        $connection,
        $packageId,
        $actorId,
        $auditConstraint
    );
    red_addon_admin_form_write_test_assert(
        (int) red_addon_admin_form_write_test_scalar(
            $connection,
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME='RED_Addon_Admin_Form_Write_Fixture'"
        ) === 0
            && (int) red_addon_admin_form_write_test_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Admin WHERE RecordID=' . (int) $actorId
            ) === 0
            && (int) red_addon_admin_form_write_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID='$packageId'"
            ) === 0,
        'administrator, package, audit, grant, constraint, and table fixtures are removed exactly'
    );
    $db->close();
}

fwrite(
    STDOUT,
    'Add-on administrator tool form writer self-test passed ('
        . $assertions . " assertions).\n"
);

?>
