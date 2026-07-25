<?php
/**
 * CMS-owned boundary for public Contact and administrator Login operations.
 *
 * Contact resolves the posted Form record through an active, scheduled Form
 * article, compiles the stored field definition, and validates the submitted
 * values before any session or mail effect. Login intentionally retains its
 * fixed compatibility contract.
 */

require_once __DIR__ . '/public_form_helpers.php';

if (!function_exists('red_public_form_operation_contracts')) {
    function red_public_form_operation_contracts()
    {
        static $contracts = null;
        if ($contracts !== null) {
            return $contracts;
        }

        $contracts = [
            'contact' => [
                'schemaVersion' => 2,
                'id' => 'contact',
                'form' => [
                    'resolver' => 'posted-active-contact-pair',
                    'formType' => 'Contact',
                    'articleComponent' => 'Form',
                    'aliasMatch' => 'exact-stored-alias',
                ],
                'request' => [
                    'method' => 'POST',
                    'endpoint' => '/bin/contact.php',
                    'payloadMode' => 'serialized-form',
                    'renderedNames' => 'definition-fields + Submit, alias, RecordID, MySpamTrap',
                    'submittedNames' => 'definition-fields + alias, RecordID, MySpamTrap',
                    'serverReadNames' => 'validated-definition-fields + RecordID, alias, MySpamTrap',
                    'csrfRequired' => false,
                    'authenticationRequired' => false,
                    'sessionGuard' => 'contact',
                    'maximumKeys' => 64,
                    'maximumBytes' => 65536,
                ],
                'validation' => [
                    'clientRequired' => 'stored-definition-required-fields',
                    'clientRules' => 'legacy-definition-driven-browser-rules',
                    'serverRequiredFieldsValidated' => true,
                    'serverRules' => [
                        'active-scheduled-paired-contact-form',
                        'exact-stored-alias',
                        'unique-safe-field-names',
                        'password-field-rejected',
                        'required-and-email-fields',
                        'choice-membership',
                        'arrays-only-for-checkboxes',
                        'bounded-field-and-payload-sizes',
                        'truthy-one-time-contact-session',
                        'nonempty-honeypot-suppresses-mail',
                    ],
                ],
                'response' => [
                    'success' => ['httpStatus' => 200, 'body' => 'rendered-message-html'],
                    'honeypot' => ['httpStatus' => 200, 'body' => 'rendered-message-html'],
                    'mailFailure' => ['httpStatus' => 200, 'body' => 'rendered-message-html'],
                    'guardFailure' => [
                        'httpStatus' => 302,
                        'location' => 'http://{current-host}',
                    ],
                    'browser' => [
                        'successPredicate' => 'ajax-http-success',
                        'localizedSuccess' => ['en' => 'Submitted!', 'sp' => 'Enviado!'],
                        'replaceForm' => true,
                        'reloadAfterFade' => false,
                        'transportErrorHandler' => false,
                    ],
                ],
                'effects' => [
                    'databaseReads' => ['paired RED_C_Form and RED_Articles by posted RecordID'],
                    'databaseWrites' => [],
                    'sessionReads' => ['contact'],
                    'sessionWrites' => ['unset contact'],
                    'external' => [
                        'PHPMailer send when the honeypot is empty',
                        'native mail fallback to the validated first stored recipient',
                    ],
                ],
            ],
            'login' => [
                'schemaVersion' => 1,
                'id' => 'login',
                'form' => [
                    'articleRecordId' => 966111194,
                    'formRecordId' => 884542279,
                    'formType' => 'Login',
                    'alias' => 'login',
                    'templateBytes' => 239,
                    'templateSha256' => '2609b17e4e14419ac0c2117cfb699db242b193089e409d1aa0f6391da19049b5',
                ],
                'request' => [
                    'method' => 'POST',
                    'endpoint' => '/bin/login.php',
                    'payloadMode' => 'data-string',
                    'renderedNames' => [
                        'username', 'password', 'Submit', 'alias', 'RecordID', 'MySpamTrap',
                    ],
                    'submittedNames' => ['username', 'password', 'alias', 'MySpamTrap'],
                    'serverReadNames' => ['username', 'password'],
                    'csrfRequired' => false,
                    'authenticationRequired' => false,
                    'sessionGuard' => '',
                ],
                'validation' => [
                    'clientRequired' => ['username', 'password'],
                    'clientRules' => [
                        'username' => 'non-empty',
                        'password' => 'non-empty',
                    ],
                    'serverRequiredFieldsValidated' => true,
                    'serverRules' => [
                        'trimmed-nonempty-username',
                        'username-at-most-255-bytes',
                        'nonempty-password',
                    ],
                ],
                'response' => [
                    'success' => ['httpStatus' => 200, 'body' => 'yes'],
                    'failure' => ['httpStatus' => 200, 'body' => 'no'],
                    'browser' => [
                        'successPredicate' => 'body-strict-equals-yes',
                        'localizedSuccess' => [
                            'en' => ['Success!', 'Loading Components...'],
                            'sp' => ['Exito!', 'Cargando componentes...'],
                        ],
                        'localizedFailure' => [
                            'en' => ['Error.', 'Please try again.'],
                            'sp' => ['Error.', 'Por favor intente de nuevo.'],
                        ],
                        'replaceForm' => true,
                        'reloadAfterFade' => true,
                        'transportErrorHandler' => false,
                    ],
                ],
                'effects' => [
                    'databaseReads' => [
                        'RED_Login_Attempts aggregate',
                        'RED_Admin by Username',
                        'INFORMATION_SCHEMA.COLUMNS during password upgrade',
                    ],
                    'databaseWrites' => [
                        'expired RED_Login_Attempts cleanup',
                        'failed-attempt insert or update',
                        'successful username failure clear',
                        'optional RED_Admin password hash upgrade',
                    ],
                    'sessionReads' => [],
                    'sessionWrites' => [
                        'session_regenerate_id',
                        'alias',
                        'AdminRecordID',
                        'AdminUsername',
                        'AdminType',
                        'AdminComponents',
                        'AdminTools',
                        'AdminPasswordFingerprint',
                    ],
                    'external' => [],
                ],
            ],
        ];

        return $contracts;
    }
}

if (!function_exists('red_public_form_operation_contract')) {
    function red_public_form_operation_contract($operation)
    {
        $operation = is_string($operation) ? $operation : '';
        $contracts = red_public_form_operation_contracts();
        if (!array_key_exists($operation, $contracts)) {
            throw new InvalidArgumentException('Unknown public Form operation.');
        }

        return $contracts[$operation];
    }
}

if (!function_exists('red_public_form_operation_assert_contract')) {
    function red_public_form_operation_assert_contract($operation, array $candidate)
    {
        if ($candidate !== red_public_form_operation_contract($operation)) {
            throw new InvalidArgumentException('Public Form operation contract drifted.');
        }

        return true;
    }
}

if (!function_exists('red_public_form_operation_contact_payload')) {
    function red_public_form_operation_contact_payload(array $payload)
    {
        if (count($payload) > 64) {
            throw new InvalidArgumentException('Public Contact payload has too many fields.');
        }

        foreach (['alias', 'RecordID', 'MySpamTrap'] as $controlName) {
            if (!array_key_exists($controlName, $payload)) {
                throw new InvalidArgumentException('Public Contact payload is missing a required control.');
            }
        }

        $prepared = [];
        $payloadBytes = 0;
        foreach ($payload as $name => $value) {
            if (!is_string($name) || strlen($name) === 0 || strlen($name) > 64) {
                throw new InvalidArgumentException('Public Contact payload contains an invalid field name.');
            }
            $payloadBytes += strlen($name);

            if (is_array($value)) {
                if ($value !== [] && array_keys($value) !== range(0, count($value) - 1)) {
                    throw new InvalidArgumentException('Public Contact payload arrays must be simple lists.');
                }
                if (count($value) > 50) {
                    throw new InvalidArgumentException('Public Contact payload array is too large.');
                }
                $prepared[$name] = [];
                foreach ($value as $item) {
                    if (!is_scalar($item)) {
                        throw new InvalidArgumentException('Public Contact payload arrays must contain scalar values.');
                    }
                    $item = (string) $item;
                    $payloadBytes += strlen($item);
                    $prepared[$name][] = $item;
                }
            } else {
                if (!is_scalar($value)) {
                    throw new InvalidArgumentException('Public Contact payload values must be scalar or checkbox lists.');
                }
                $value = (string) $value;
                $payloadBytes += strlen($value);
                $prepared[$name] = $value;
            }
        }

        if ($payloadBytes > 65536) {
            throw new InvalidArgumentException('Public Contact payload is too large.');
        }
        if (!is_string($prepared['alias'])
            || $prepared['alias'] === ''
            || strlen($prepared['alias']) > 255
            || preg_match('/[\r\n\0]/', $prepared['alias'])
        ) {
            throw new InvalidArgumentException('Public Contact alias is invalid.');
        }
        if (!is_string($prepared['RecordID'])
            || preg_match('/\A[1-9][0-9]{0,9}\z/', $prepared['RecordID']) !== 1
            || (int) $prepared['RecordID'] > 4294967295
        ) {
            throw new InvalidArgumentException('Public Contact Form RecordID is invalid.');
        }
        if (!is_string($prepared['MySpamTrap']) || strlen($prepared['MySpamTrap']) > 2048) {
            throw new InvalidArgumentException('Public Contact honeypot is invalid.');
        }

        return $prepared;
    }
}

if (!function_exists('red_public_form_operation_prepare_payload')) {
    function red_public_form_operation_prepare_payload($operation, array $payload)
    {
        if ($operation === 'contact') {
            return red_public_form_operation_contact_payload($payload);
        }

        $contract = red_public_form_operation_contract($operation);
        $expectedNames = $contract['request']['submittedNames'];
        if (array_keys($payload) !== $expectedNames) {
            throw new InvalidArgumentException('Public Form payload keys or order changed.');
        }

        $prepared = [];
        foreach ($expectedNames as $name) {
            $value = $payload[$name];
            if (!is_scalar($value)) {
                throw new InvalidArgumentException('Public Form payload values must be scalar.');
            }
            $prepared[$name] = (string) $value;
        }

        if ($prepared['alias'] !== $contract['form']['alias']) {
            throw new InvalidArgumentException('Public Form alias changed.');
        }

        return $prepared;
    }
}

if (!function_exists('red_public_form_operation_submission')) {
    function red_public_form_operation_submission($operation, array $request)
    {
        if (array_keys($request) !== ['method', 'endpoint', 'payload']) {
            throw new InvalidArgumentException('Public Form request envelope changed.');
        }

        $contract = red_public_form_operation_contract($operation);
        if ($request['method'] !== $contract['request']['method']
            || $request['endpoint'] !== $contract['request']['endpoint']
            || !is_array($request['payload'])
        ) {
            throw new InvalidArgumentException('Public Form method, endpoint, or payload changed.');
        }

        return [
            'operation' => $operation,
            'method' => $contract['request']['method'],
            'endpoint' => $contract['request']['endpoint'],
            'payload' => red_public_form_operation_prepare_payload($operation, $request['payload']),
        ];
    }
}

if (!function_exists('red_public_form_operation_client_errors')) {
    function red_public_form_operation_client_errors($operation, array $payload)
    {
        $payload = red_public_form_operation_prepare_payload($operation, $payload);
        if ($operation === 'contact') {
            return [];
        }

        $errors = [];
        if ($payload['username'] === '') {
            $errors[] = 'username';
        }
        if ($payload['password'] === '') {
            $errors[] = 'password';
        }

        return $errors;
    }
}

if (!function_exists('red_public_form_operation_server_errors')) {
    function red_public_form_operation_server_errors($operation, array $payload)
    {
        $payload = red_public_form_operation_prepare_payload($operation, $payload);
        if ($operation === 'contact') {
            return [];
        }

        $errors = [];
        $username = trim($payload['username']);
        if ($username === '' || strlen($username) > 255) {
            $errors[] = 'username';
        }
        if ($payload['password'] === '') {
            $errors[] = 'password';
        }

        return $errors;
    }
}

if (!function_exists('red_public_contact_form_record_id')) {
    function red_public_contact_form_record_id($value, $label)
    {
        if (is_int($value)) {
            $recordId = $value;
        } elseif (is_string($value) && preg_match('/\A[1-9][0-9]{0,9}\z/', $value) === 1) {
            $recordId = (int) $value;
        } else {
            throw new InvalidArgumentException('Public Contact ' . $label . ' is invalid.');
        }

        if ($recordId <= 0 || $recordId > 4294967295) {
            throw new InvalidArgumentException('Public Contact ' . $label . ' is out of range.');
        }

        return $recordId;
    }
}

if (!function_exists('red_public_contact_form_config')) {
    function red_public_contact_form_config(array $form, $expectedFormType = 'Contact')
    {
        $expectedFormType = is_scalar($expectedFormType) ? (string) $expectedFormType : '';
        if (!in_array($expectedFormType, ['Contact', 'Response', 'Register'], true)) {
            throw new InvalidArgumentException('Public operational Form type is invalid.');
        }

        $expectedNames = [
            'recordId', 'articleRecordId', 'articleComponent', 'alias', 'formType',
            'definition', 'subject', 'submitter', 'destinatary', 'cc', 'bcc',
        ];
        if (array_keys($form) !== $expectedNames) {
            throw new InvalidArgumentException('Public Contact Form resolver returned an invalid shape.');
        }

        $recordId = red_public_contact_form_record_id($form['recordId'], 'record id');
        $articleRecordId = red_public_contact_form_record_id($form['articleRecordId'], 'article record id');
        foreach (['alias', 'formType', 'articleComponent', 'definition', 'subject', 'submitter', 'destinatary', 'cc', 'bcc'] as $name) {
            if (!is_string($form[$name])) {
                throw new InvalidArgumentException('Public Contact Form configuration is not textual.');
            }
        }
        if ($form['formType'] !== $expectedFormType || $form['articleComponent'] !== 'Form') {
            throw new InvalidArgumentException('Public Contact Form relationship is invalid.');
        }
        if ($form['alias'] === ''
            || strlen($form['alias']) > 255
            || preg_match('/[\r\n\0]/', $form['alias'])
        ) {
            throw new InvalidArgumentException('Public Contact stored alias is invalid.');
        }
        if ($form['definition'] === '' || strlen($form['definition']) > 65535) {
            throw new InvalidArgumentException('Public Contact field definition is invalid.');
        }
        if (strlen($form['subject']) > 255 || preg_match('/[\r\n\0]/', $form['subject'])) {
            throw new InvalidArgumentException('Public Contact mail subject is invalid.');
        }

        $fromMailboxes = red_public_contact_mailboxes($form['submitter']);
        $recipientMailboxes = red_public_contact_mailboxes($form['destinatary']);
        $ccMailboxes = red_public_contact_mailboxes($form['cc']);
        $bccMailboxes = red_public_contact_mailboxes($form['bcc']);
        if (!is_array($fromMailboxes)
            || count($fromMailboxes) !== 1
            || !is_array($recipientMailboxes)
            || count($recipientMailboxes) === 0
            || !is_array($ccMailboxes)
            || !is_array($bccMailboxes)
        ) {
            throw new InvalidArgumentException('Public Contact mail configuration is invalid.');
        }

        return [
            'recordId' => $recordId,
            'articleRecordId' => $articleRecordId,
            'articleComponent' => 'Form',
            'alias' => $form['alias'],
            'formType' => $expectedFormType,
            'definition' => $form['definition'],
            'subject' => $form['subject'],
            'fromMailbox' => $fromMailboxes[0],
            'recipientMailboxes' => $recipientMailboxes,
            'ccMailboxes' => $ccMailboxes,
            'bccMailboxes' => $bccMailboxes,
        ];
    }
}

if (!function_exists('red_public_contact_choice_values')) {
    function red_public_contact_choice_values($fieldType, $source)
    {
        if (!is_string($source) || $source === '' || strlen($source) > 8192) {
            throw new InvalidArgumentException('Public Contact choice definition is invalid.');
        }

        $items = explode(',', $source);
        if (count($items) > 50) {
            throw new InvalidArgumentException('Public Contact choice definition has too many options.');
        }

        $choices = [];
        $placeholderValue = null;
        foreach ($items as $itemIndex => $item) {
            $item = trim($item);
            $disabled = false;
            if ($fieldType === 'checkbox') {
                $item = explode('*', $item, 2)[0];
                $parts = explode('^', $item, 2);
                $value = count($parts) === 2 ? $parts[1] : $parts[0];
            } elseif ($fieldType === 'radio') {
                $item = explode('|', $item, 2)[0];
                $parts = explode('^', $item, 2);
                $value = count($parts) === 2 ? $parts[1] : $parts[0];
            } else {
                $parts = explode('^', $item, 2);
                $value = $parts[0];
                if (count($parts) === 2) {
                    if ($parts[1] === 'disabled') {
                        $disabled = true;
                    } elseif ($parts[1] !== 'selected') {
                        throw new InvalidArgumentException('Public Contact select option marker is invalid.');
                    }
                }
            }

            $value = trim($value);
            if ($value === '' || strlen($value) > 512 || preg_match('/[\r\n\0]/', $value)) {
                throw new InvalidArgumentException('Public Contact choice value is invalid.');
            }
            if ($disabled) {
                continue;
            }
            if (isset($choices[$value])) {
                throw new InvalidArgumentException('Public Contact choice values must be unique.');
            }
            $choices[$value] = true;
            if ($fieldType === 'select' && $itemIndex === 0) {
                $placeholderValue = $value;
            }
        }

        if ($choices === []) {
            throw new InvalidArgumentException('Public Contact choice field has no selectable values.');
        }

        return [
            'choices' => array_keys($choices),
            'placeholderValue' => $placeholderValue,
        ];
    }
}

if (!function_exists('red_public_contact_compile_fields')) {
    function red_public_contact_compile_fields($definition)
    {
        if (!is_string($definition) || $definition === '' || strlen($definition) > 65535) {
            throw new InvalidArgumentException('Public Contact field definition is invalid.');
        }

        $rows = red_public_form_parse_definition($definition);
        if (count($rows) > 100) {
            throw new InvalidArgumentException('Public Contact field definition has too many rows.');
        }

        $fields = [];
        $seen = [];
        $reserved = [
            'alias' => true,
            'recordid' => true,
            'myspamtrap' => true,
            'submit' => true,
            'csrf_token' => true,
            'updatedate' => true,
        ];
        $allowedTypes = ['textfield', 'textarea', 'checkbox', 'radio', 'select', 'hidden'];
        foreach ($rows as $row) {
            $type = strtolower(trim((string) ($row['type'] ?? '')));
            if (in_array($type, ['button', 'paragraph'], true)) {
                continue;
            }
            if ($type === 'password') {
                throw new InvalidArgumentException('Public Contact password fields are not allowed.');
            }
            if (!in_array($type, $allowedTypes, true)) {
                throw new InvalidArgumentException('Public Contact field type is unsupported.');
            }

            $name = red_public_form_identifier($row['name'] ?? '');
            $nameKey = strtolower((string) $name);
            if ($name === null || isset($reserved[$nameKey]) || isset($seen[$nameKey])) {
                throw new InvalidArgumentException('Public Contact field names must be safe, unique, and unreserved.');
            }
            $seen[$nameKey] = true;

            $requiredSource = array_key_exists('required', $row)
                ? strtolower(trim((string) $row['required']))
                : ($type === 'hidden' ? 'false' : '');
            if (!in_array($requiredSource, ['true', 'false'], true)) {
                throw new InvalidArgumentException('Public Contact required setting is invalid.');
            }
            $required = $requiredSource === 'true';

            $label = trim((string) ($row['displayname'] ?? $name));
            if ($label === '' || strlen($label) > 255 || strpos($label, "\0") !== false) {
                throw new InvalidArgumentException('Public Contact field label is invalid.');
            }

            $inputType = 'text';
            if ($type === 'textfield') {
                $inputType = strtolower(trim((string) ($row['inputtype'] ?? 'text')));
                if (!in_array($inputType, ['text', 'email', 'tel', 'url', 'search'], true)) {
                    throw new InvalidArgumentException('Public Contact text input type is invalid.');
                }
            }

            $choices = [];
            $placeholderValue = null;
            if (in_array($type, ['checkbox', 'radio', 'select'], true)) {
                $choiceDefinition = red_public_contact_choice_values(
                    $type,
                    (string) ($row['value'] ?? '')
                );
                $choices = $choiceDefinition['choices'];
                $placeholderValue = $choiceDefinition['placeholderValue'];
            }

            $initialValue = '';
            if ($type === 'hidden') {
                $initialValue = (string) ($row['initialvalue'] ?? '');
                if (strlen($initialValue) > 2048
                    || strpos($initialValue, "\0") !== false
                    || $initialValue === 'referral'
                ) {
                    throw new InvalidArgumentException('Public Contact hidden field value is invalid.');
                }
            }

            $fields[] = [
                'name' => $name,
                'type' => $type,
                'required' => $required,
                'label' => $label,
                'inputType' => $inputType,
                'choices' => $choices,
                'initialValue' => $initialValue,
                'placeholderValue' => $type === 'select' && $required ? $placeholderValue : null,
            ];
            if (count($fields) > 50) {
                throw new InvalidArgumentException('Public Contact field definition has too many inputs.');
            }
        }

        if ($fields === []) {
            throw new InvalidArgumentException('Public Contact field definition has no inputs.');
        }

        return $fields;
    }
}

if (!function_exists('red_public_contact_validate_submission')) {
    function red_public_contact_validate_submission(array $form, array $fields, array $payload, $validateSemantics = true)
    {
        if ((int) $payload['RecordID'] !== $form['recordId']
            || !hash_equals($form['alias'], $payload['alias'])
        ) {
            throw new InvalidArgumentException('Public Contact Form identity does not match the stored form.');
        }

        $fieldMap = [];
        foreach ($fields as $field) {
            $fieldMap[$field['name']] = $field;
        }
        foreach ($payload as $name => $value) {
            if (in_array($name, ['alias', 'RecordID', 'MySpamTrap'], true)) {
                continue;
            }
            if (!isset($fieldMap[$name])) {
                throw new InvalidArgumentException('Public Contact payload contains an undefined field.');
            }
        }

        $values = [];
        $totalBytes = 0;
        foreach ($fields as $field) {
            $name = $field['name'];
            $present = array_key_exists($name, $payload);
            $rawValue = $present ? $payload[$name] : null;

            if ($field['type'] === 'checkbox') {
                if (!$present) {
                    $submitted = [];
                } elseif (is_array($rawValue)) {
                    $submitted = $rawValue;
                } elseif (is_string($rawValue)) {
                    $submitted = [$rawValue];
                } else {
                    throw new InvalidArgumentException('Public Contact checkbox value is invalid.');
                }

                if (count($submitted) > count($field['choices'])) {
                    throw new InvalidArgumentException('Public Contact checkbox selection is invalid.');
                }
                $selected = [];
                foreach ($submitted as $choice) {
                    if (!is_string($choice)
                        || strlen($choice) > 512
                        || !in_array($choice, $field['choices'], true)
                        || isset($selected[$choice])
                    ) {
                        throw new InvalidArgumentException('Public Contact checkbox choice is invalid.');
                    }
                    $selected[$choice] = true;
                    $totalBytes += strlen($choice);
                }
                if ($validateSemantics && $field['required'] && $selected === []) {
                    throw new InvalidArgumentException('Public Contact required checkbox is empty.');
                }
                $values[$name] = array_keys($selected);
                continue;
            }

            if (is_array($rawValue)) {
                throw new InvalidArgumentException('Public Contact arrays are allowed only for checkboxes.');
            }
            if (!$present) {
                $value = '';
            } elseif (is_string($rawValue)) {
                $value = $rawValue;
            } else {
                throw new InvalidArgumentException('Public Contact field value is invalid.');
            }

            $maximumBytes = $field['type'] === 'textarea' ? 20000 : 2048;
            if (in_array($field['type'], ['radio', 'select'], true)) {
                $maximumBytes = 512;
            }
            if (strlen($value) > $maximumBytes || strpos($value, "\0") !== false) {
                throw new InvalidArgumentException('Public Contact field value is too large or invalid.');
            }
            $totalBytes += strlen($value);

            if ($field['type'] === 'hidden') {
                if (!$present || !hash_equals($field['initialValue'], $value)) {
                    throw new InvalidArgumentException('Public Contact hidden field was changed.');
                }
            } elseif (in_array($field['type'], ['radio', 'select'], true)) {
                if ($value !== '' && !in_array($value, $field['choices'], true)) {
                    throw new InvalidArgumentException('Public Contact choice is not defined.');
                }
                if ($validateSemantics
                    && $field['type'] === 'select'
                    && $field['required']
                    && $field['placeholderValue'] !== null
                    && hash_equals($field['placeholderValue'], $value)
                ) {
                    throw new InvalidArgumentException('Public Contact required select is still on its placeholder.');
                }
            }

            if ($validateSemantics && $field['required'] && trim($value) === '') {
                throw new InvalidArgumentException('Public Contact required field is empty.');
            }
            if ($validateSemantics
                && $value !== ''
                && ($field['inputType'] === 'email' || strtolower($name) === 'email')
                && filter_var(trim($value), FILTER_VALIDATE_EMAIL) === false
            ) {
                throw new InvalidArgumentException('Public Contact email field is invalid.');
            }

            $values[$name] = $value;
        }

        if ($totalBytes > 65536) {
            throw new InvalidArgumentException('Public Contact normalized values are too large.');
        }

        return $values;
    }
}

if (!function_exists('red_public_form_operation_assert_dependencies')) {
    function red_public_form_operation_assert_dependencies(array $dependencies, array $expectedNames)
    {
        if (array_keys($dependencies) !== $expectedNames) {
            throw new InvalidArgumentException('Public Form operation dependencies changed.');
        }
        foreach ($dependencies as $dependency) {
            if (!is_callable($dependency)) {
                throw new InvalidArgumentException('Public Form operation dependency is not callable.');
            }
        }

        return true;
    }
}

if (!function_exists('red_public_form_operation_result')) {
    function red_public_form_operation_result($httpStatus, $body, array $headers, $browserOutcome, array $effectTrace)
    {
        return [
            'httpStatus' => (int) $httpStatus,
            'body' => (string) $body,
            'headers' => $headers,
            'browserOutcome' => (string) $browserOutcome,
            'effectTrace' => $effectTrace,
        ];
    }
}

if (!function_exists('red_public_form_operation_execute_contact')) {
    function red_public_form_operation_execute_contact(array $submission, array $state, array $dependencies)
    {
        if (array_keys($state) !== ['contactSession', 'baseUrl']
            || !is_bool($state['contactSession'])
            || !is_string($state['baseUrl'])
            || strlen($state['baseUrl']) > 255
            || preg_match('/\A[A-Za-z0-9.-]+(?::[0-9]{1,5})?\z/', $state['baseUrl']) !== 1
        ) {
            throw new InvalidArgumentException('Public Contact operation state changed.');
        }
        red_public_form_operation_assert_dependencies(
            $dependencies,
            ['fetchForm', 'buildMessage', 'consumeContactSession', 'sendMail', 'fallbackMail']
        );

        $redirect = static function ($baseUrl, array $trace) {
            return red_public_form_operation_result(
                302,
                '',
                ['Location' => 'http://' . $baseUrl],
                'redirect-home',
                $trace
            );
        };
        if (!$state['contactSession']) {
            return $redirect($state['baseUrl'], []);
        }

        $trace = ['fetch-form'];
        $resolvedForm = $dependencies['fetchForm']((int) $submission['payload']['RecordID']);
        if ($resolvedForm === null) {
            return $redirect($state['baseUrl'], $trace);
        }
        if (!is_array($resolvedForm)) {
            throw new RuntimeException('Public Contact fetch dependency returned an invalid result.');
        }

        $form = red_public_contact_form_config($resolvedForm);
        $fields = red_public_contact_compile_fields($form['definition']);
        $isHoneypot = $submission['payload']['MySpamTrap'] !== '';
        $values = red_public_contact_validate_submission(
            $form,
            $fields,
            $submission['payload'],
            !$isHoneypot
        );

        $message = $dependencies['buildMessage']($form, $fields, $values);
        $trace[] = 'build-message';
        if (!is_string($message) || $message === '') {
            throw new RuntimeException('Public Contact message dependency returned an invalid result.');
        }

        $consumed = $dependencies['consumeContactSession']();
        $trace[] = 'consume-contact-session';
        if ($consumed !== true) {
            throw new RuntimeException('Public Contact session dependency returned an invalid result.');
        }

        if ($isHoneypot) {
            $trace[] = 'honeypot-suppress-mail';
            return red_public_form_operation_result(
                200,
                $message,
                [],
                'contact-transport-success',
                $trace
            );
        }

        $sent = $dependencies['sendMail']($form, $values, $message);
        $trace[] = 'phpmailer-send';
        if (!is_bool($sent)) {
            throw new RuntimeException('Public Contact mail dependency returned an invalid result.');
        }
        if (!$sent) {
            $fallback = $dependencies['fallbackMail']($form, $values, $message);
            $trace[] = 'native-mail-fallback';
            if (!is_bool($fallback)) {
                throw new RuntimeException('Public Contact fallback dependency returned an invalid result.');
            }
        }

        return red_public_form_operation_result(
            200,
            $message,
            [],
            'contact-transport-success',
            $trace
        );
    }
}

if (!function_exists('red_public_form_operation_execute_login')) {
    function red_public_form_operation_execute_login(array $submission, array $state, array $dependencies)
    {
        if ($state !== []) {
            throw new InvalidArgumentException('Public Login operation accepts no caller state.');
        }
        red_public_form_operation_assert_dependencies($dependencies, ['authenticate']);

        if (red_public_form_operation_server_errors('login', $submission['payload']) !== []) {
            return red_public_form_operation_result(200, 'no', [], 'login-failure-reload', []);
        }

        $outcome = $dependencies['authenticate'](
            trim($submission['payload']['username']),
            $submission['payload']['password']
        );
        if (!in_array($outcome, ['success', 'invalid', 'unknown', 'throttled', 'unavailable'], true)) {
            throw new RuntimeException('Public Login authentication dependency returned an invalid result.');
        }
        $success = $outcome === 'success';

        return red_public_form_operation_result(
            200,
            $success ? 'yes' : 'no',
            [],
            $success ? 'login-success-reload' : 'login-failure-reload',
            ['authenticate-admin:' . $outcome]
        );
    }
}

if (!function_exists('red_public_form_operation_execute')) {
    function red_public_form_operation_execute($operation, array $request, array $state, array $dependencies)
    {
        $submission = red_public_form_operation_submission($operation, $request);
        if ($operation === 'contact') {
            return red_public_form_operation_execute_contact($submission, $state, $dependencies);
        }
        if ($operation === 'login') {
            return red_public_form_operation_execute_login($submission, $state, $dependencies);
        }

        throw new InvalidArgumentException('Unknown public Form operation.');
    }
}

if (!function_exists('red_public_form_operation_report')) {
    function red_public_form_operation_report()
    {
        $contracts = red_public_form_operation_contracts();
        $operations = [];
        foreach ($contracts as $id => $contract) {
            $operations[$id] = [
                'formType' => $contract['form']['formType'],
                'endpoint' => $contract['request']['endpoint'],
                'payloadMode' => $contract['request']['payloadMode'],
                'submittedNames' => $contract['request']['submittedNames'],
                'clientRequired' => $contract['validation']['clientRequired'],
                'serverRequiredFieldsValidated' => $contract['validation']['serverRequiredFieldsValidated'],
                'csrfRequired' => $contract['request']['csrfRequired'],
                'authenticationRequired' => $contract['request']['authenticationRequired'],
            ];
        }

        return [
            'schemaVersion' => 2,
            'mode' => 'live-operational-form-boundary',
            'operations' => $operations,
            'scope' => [
                'databaseReads' => 0,
                'databaseWrites' => 0,
                'filesystemReads' => 0,
                'filesystemWrites' => 0,
                'sessionReads' => 0,
                'sessionWrites' => 0,
                'networkRequests' => 0,
                'mailSends' => 0,
                'liveEndpointConnections' => 2,
                'themeConnections' => 0,
                'liveRuntimeChanges' => 0,
            ],
        ];
    }
}

?>
