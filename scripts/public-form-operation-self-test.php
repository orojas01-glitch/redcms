<?php
/**
 * Dependency-free tests for the operational Contact/Login boundary.
 * No database, session, HTTP request, network lookup, or mail transport runs.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/public_form_operation_helpers.php';
require_once $repositoryRoot . '/includes/public_render_helpers.php';
require_once $repositoryRoot . '/includes/legacy_component_helpers.php';

$assertions = 0;
function red_public_form_operation_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_public_form_operation_test_throws(callable $callback, $className, $message)
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        red_public_form_operation_test_assert(
            $throwable instanceof $className,
            $message . ' throws ' . $className
        );
        return;
    }
    red_public_form_operation_test_assert(false, $message . ' fails closed');
}

function red_public_form_operation_test_form($recordId, $articleRecordId, $alias, $definition)
{
    return [
        'recordId' => $recordId,
        'articleRecordId' => $articleRecordId,
        'articleComponent' => 'Form',
        'alias' => $alias,
        'formType' => 'Contact',
        'definition' => $definition,
        'subject' => 'Website contact',
        'submitter' => 'sender@example.test,RED CMS',
        'destinatary' => 'owner@example.test,Site Owner',
        'cc' => '',
        'bcc' => '',
    ];
}

function red_public_form_operation_test_dependencies($resolvedForm, array &$trace, $sendResult, $fallbackResult, $message)
{
    return [
        'fetchForm' => static function ($recordId) use ($resolvedForm, &$trace) {
            $trace[] = 'fetch:' . $recordId;
            return $resolvedForm;
        },
        'buildMessage' => static function ($form, $fields, $values) use (&$trace, $message) {
            $trace[] = 'build:' . $form['alias'] . ':' . count($fields) . ':' . count($values);
            return $message;
        },
        'consumeContactSession' => static function () use (&$trace) {
            $trace[] = 'consume';
            return true;
        },
        'sendMail' => static function ($form, $values, $body) use (&$trace, $sendResult, $message) {
            $trace[] = 'send:' . $form['alias'] . ':' . count($values) . ':' . (int) ($body === $message);
            return $sendResult;
        },
        'fallbackMail' => static function ($form, $values, $body) use (&$trace, $fallbackResult, $message) {
            $trace[] = 'fallback:' . $form['recipientMailboxes'][0]['email'] . ':' . (int) ($body === $message);
            return $fallbackResult;
        },
    ];
}

$originalDefinition =
    '#|question=|name=name|type=textfield|required=true|displayname=Enter your Full Name:|initialvalue=;' . "\r\n" .
    '#|question=|name=title|type=textfield|required=false|displayname=Enter your Title:|initialvalue=;' . "\r\n" .
    '#|question=|name=email|type=textfield|required=true|displayname=Enter your e-mail:|initialvalue=;' . "\r\n" .
    '#|question=|name=telephone|type=textfield|required=true|displayname=Enter your Telephone:|initialvalue=;' . "\r\n" .
    '#|question=|name=fax|type=textfield|required=false|displayname=Enter your Fax:|initialvalue=;' . "\r\n" .
    '#|question=|name=message|type=textarea|required=false|displayname=Enter your Message:|readonly=false|initialvalue=|cols=45|rows=5;' . "\r\n" .
    '#|question=|name=Submit|type=button|displayname=submit';
$alternateDefinition =
    '#|question=|name=reason|type=select|required=true|displayname=Motivo|value=Por favor seleccione^selected,--------^disabled,Clases de música,Canto,Eventos;' .
    '#|question=|name=name|type=textfield|required=true|displayname=Nombre|initialvalue=|autocomplete=name|placeholder=Tu nombre;' .
    '#|question=|name=email|type=textfield|required=true|displayname=Email|initialvalue=|inputtype=email|autocomplete=email|placeholder=tu@email.com;' .
    '#|question=|name=message|type=textarea|required=false|displayname=Mensaje|initialvalue=|cols=45|rows=5|readonly=false;' .
    '#|question=|name=Submit|type=button|displayname=Enviar mensaje;' .
    '#|type=paragraph|paragraph=Formulario local de respaldo.';
$richDefinition =
    '#|name=topics|type=checkbox|required=true|displayname=Topics|value=Email^email,SMS^sms;' .
    '#|name=attendance|type=radio|required=true|displayname=Attendance|value=Yes^yes,No^no;' .
    '#|name=plan|type=select|required=true|displayname=Plan|value=Choose^selected,Starter,Professional;' .
    '#|name=source|type=hidden|initialvalue=cms;' .
    '#|name=email|type=textfield|required=true|displayname=Email|inputtype=email|initialvalue=;' .
    '#|name=notes|type=textarea|required=false|displayname=Notes|initialvalue=|readonly=false;' .
    '#|name=Submit|type=button|displayname=Send';

$originalForm = red_public_form_operation_test_form(93039112, 459269660, 'contact', $originalDefinition);
$alternateForm = red_public_form_operation_test_form(3400000200, 3400000100, 'contact', $alternateDefinition);
$richForm = red_public_form_operation_test_form(3000000001, 3000000002, 'contact-rich', $richDefinition);

$originalPayload = [
    'name' => 'Codex QA',
    'title' => '',
    'email' => 'qa@example.com',
    'telephone' => '202-555-0147',
    'fax' => '',
    'message' => 'Boundary test',
    'alias' => 'contact',
    'RecordID' => '93039112',
    'MySpamTrap' => '',
];
$alternatePayload = [
    'reason' => 'Clases de música',
    'name' => 'Alternate QA',
    'email' => 'alternate@example.com',
    'message' => 'Hola',
    'alias' => 'contact',
    'RecordID' => '3400000200',
    'MySpamTrap' => '',
];
$richPayload = [
    'topics' => ['email', 'sms'],
    'attendance' => 'yes',
    'plan' => 'Starter',
    'source' => 'cms',
    'email' => 'person@example.com',
    'notes' => 'Please follow up.',
    'alias' => 'contact-rich',
    'RecordID' => '3000000001',
    'MySpamTrap' => '',
];
$contactRequest = [
    'method' => 'POST',
    'endpoint' => '/bin/contact.php',
    'payload' => $originalPayload,
];
$alternateRequest = [
    'method' => 'POST',
    'endpoint' => '/bin/contact.php',
    'payload' => $alternatePayload,
];
$loginPayload = [
    'username' => 'codex-boundary',
    'password' => 'not-a-live-secret',
    'alias' => 'login',
    'MySpamTrap' => '',
];
$loginRequest = [
    'method' => 'POST',
    'endpoint' => '/bin/login.php',
    'payload' => $loginPayload,
];

try {
    $contracts = red_public_form_operation_contracts();
    red_public_form_operation_test_assert(
        array_keys($contracts) === ['contact', 'login']
            && $contracts['contact']['schemaVersion'] === 2
            && $contracts['contact']['form']['resolver'] === 'posted-active-contact-pair'
            && $contracts['contact']['validation']['serverRequiredFieldsValidated']
            && $contracts['login']['schemaVersion'] === 1,
        'boundary owns dynamic Contact and fixed Login contracts'
    );
    red_public_form_operation_test_assert(
        $contracts['contact']['request']['endpoint'] === '/bin/contact.php'
            && $contracts['contact']['request']['sessionGuard'] === 'contact'
            && !$contracts['contact']['request']['csrfRequired']
            && !$contracts['contact']['request']['authenticationRequired']
            && $contracts['login']['request']['submittedNames'] === array_keys($loginPayload),
        'endpoint, one-time session, and Login payload compatibility remain explicit'
    );
    red_public_form_operation_test_assert(
        red_public_form_operation_assert_contract('contact', $contracts['contact'])
            && red_public_form_operation_assert_contract('login', $contracts['login']),
        'canonical contracts validate exactly'
    );
    red_public_form_operation_test_throws(
        static function () { red_public_form_operation_contract('register'); },
        InvalidArgumentException::class,
        'unscoped Register operation'
    );

    red_public_form_operation_test_assert(
        red_public_form_operation_submission('contact', $contactRequest)['payload'] === $originalPayload
            && red_public_form_operation_submission('contact', $alternateRequest)['payload'] === $alternatePayload
            && red_public_form_operation_submission('login', $loginRequest)['payload'] === $loginPayload,
        'both Contact shapes and fixed Login envelope prepare without data loss'
    );
    $badRecordRequest = $contactRequest;
    $badRecordRequest['payload']['RecordID'] = '93039112 OR 1';
    red_public_form_operation_test_throws(
        static function () use ($badRecordRequest) {
            red_public_form_operation_submission('contact', $badRecordRequest);
        },
        InvalidArgumentException::class,
        'malformed Contact RecordID'
    );
    $missingControl = $contactRequest;
    unset($missingControl['payload']['alias']);
    red_public_form_operation_test_throws(
        static function () use ($missingControl) {
            red_public_form_operation_submission('contact', $missingControl);
        },
        InvalidArgumentException::class,
        'missing Contact control'
    );
    $wrongMethod = $contactRequest;
    $wrongMethod['method'] = 'GET';
    red_public_form_operation_test_throws(
        static function () use ($wrongMethod) {
            red_public_form_operation_submission('contact', $wrongMethod);
        },
        InvalidArgumentException::class,
        'Contact method tampering'
    );

    $originalFields = red_public_contact_compile_fields($originalDefinition);
    $alternateFields = red_public_contact_compile_fields($alternateDefinition);
    $richFields = red_public_contact_compile_fields($richDefinition);
    red_public_form_operation_test_assert(
        count($originalFields) === 6
            && count($alternateFields) === 4
            && $alternateFields[0]['choices'] === ['Por favor seleccione', 'Clases de música', 'Canto', 'Eventos']
            && $alternateFields[0]['placeholderValue'] === 'Por favor seleccione'
            && count($richFields) === 6,
        'original, alternate, and full field definitions compile deterministically'
    );
    $disabledPlaceholderFields = red_public_contact_compile_fields(
        '#|name=choice|type=select|required=true|displayname=Choice|value=- Select -^disabled,Option'
    );
    red_public_form_operation_test_assert(
        $disabledPlaceholderFields[0]['choices'] === ['Option']
            && $disabledPlaceholderFields[0]['placeholderValue'] === null,
        'disabled first select option is excluded without turning the first selectable value into a placeholder'
    );

    foreach ([
        '#|name=Name|type=textfield|required=true|displayname=Name;#|name=name|type=textfield|required=false|displayname=Again' => 'duplicate field',
        '#|name=alias|type=textfield|required=true|displayname=Alias' => 'reserved field',
        '#|name=bad-name|type=textfield|required=true|displayname=Bad' => 'unsafe field name',
        '#|name=secret|type=password|required=true|displayname=Secret' => 'password field',
        '#|name=when|type=date|required=true|displayname=When' => 'unsupported field type',
        '#|name=missing_required|type=textfield|displayname=Missing' => 'malformed required flag',
    ] as $definition => $label) {
        red_public_form_operation_test_throws(
            static function () use ($definition) { red_public_contact_compile_fields($definition); },
            InvalidArgumentException::class,
            $label
        );
    }
    $tooManyRows = [];
    for ($index = 0; $index < 51; $index++) {
        $tooManyRows[] = '#|name=field_' . $index . '|type=textfield|required=false|displayname=Field';
    }
    red_public_form_operation_test_throws(
        static function () use ($tooManyRows) {
            red_public_contact_compile_fields(implode(';', $tooManyRows));
        },
        InvalidArgumentException::class,
        'too many input fields'
    );

    $normalizedRichForm = red_public_contact_form_config($richForm);
    $responseForm = $originalForm;
    $responseForm['formType'] = 'Response';
    $registerForm = $originalForm;
    $registerForm['formType'] = 'Register';
    $normalizedResponseForm = red_public_contact_form_config($responseForm, 'Response');
    $normalizedRegisterForm = red_public_contact_form_config($registerForm, 'Register');
    red_public_form_operation_test_assert(
        $normalizedResponseForm['formType'] === 'Response'
            && $normalizedRegisterForm['formType'] === 'Register'
            && red_public_form_submission_text(['email', 'sms']) === 'email, sms',
        'Response and Register reuse the validated operational definition and mailbox boundary'
    );
    $preparedRich = red_public_form_operation_prepare_payload('contact', $richPayload);
    $richValues = red_public_contact_validate_submission(
        $normalizedRichForm,
        $richFields,
        $preparedRich
    );
    red_public_form_operation_test_assert(
        $richValues['topics'] === ['email', 'sms']
            && $richValues['attendance'] === 'yes'
            && $richValues['source'] === 'cms'
            && $normalizedRichForm['recipientMailboxes'][0]['email'] === 'owner@example.test',
        'checkbox lists, choices, hidden values, and stored mailboxes normalize safely'
    );

    $invalidRichPayloads = [];
    $candidate = $richPayload;
    unset($candidate['topics']);
    $invalidRichPayloads['required checkbox'] = $candidate;
    $candidate = $richPayload;
    $candidate['attendance'] = 'maybe';
    $invalidRichPayloads['radio membership'] = $candidate;
    $candidate = $richPayload;
    $candidate['plan'] = 'Choose';
    $invalidRichPayloads['required select placeholder'] = $candidate;
    $candidate = $richPayload;
    $candidate['email'] = 'not-an-email';
    $invalidRichPayloads['email format'] = $candidate;
    $candidate = $richPayload;
    $candidate['source'] = 'tampered';
    $invalidRichPayloads['hidden value'] = $candidate;
    $candidate = $richPayload;
    $candidate['email'] = ['person@example.com'];
    $invalidRichPayloads['array on non-checkbox'] = $candidate;
    $candidate = $richPayload;
    $candidate['route'] = '/injected';
    $invalidRichPayloads['undefined field'] = $candidate;
    $candidate = $richPayload;
    $candidate['notes'] = str_repeat('x', 20001);
    $invalidRichPayloads['oversized field'] = $candidate;
    foreach ($invalidRichPayloads as $label => $candidate) {
        red_public_form_operation_test_throws(
            static function () use ($normalizedRichForm, $richFields, $candidate) {
                red_public_contact_validate_submission(
                    $normalizedRichForm,
                    $richFields,
                    red_public_form_operation_prepare_payload('contact', $candidate)
                );
            },
            InvalidArgumentException::class,
            $label
        );
    }

    $mailInjectionForm = $originalForm;
    $mailInjectionForm['submitter'] = "sender@example.test\r\nBcc: attacker@example.test";
    red_public_form_operation_test_throws(
        static function () use ($mailInjectionForm) { red_public_contact_form_config($mailInjectionForm); },
        InvalidArgumentException::class,
        'stored mail header injection'
    );
    red_public_form_operation_test_assert(
        red_public_contact_mailboxes('first@example.test,First;second@example.test,Second') === [
            ['email' => 'first@example.test', 'name' => 'First'],
            ['email' => 'second@example.test', 'name' => 'Second'],
        ]
            && red_public_contact_mailboxes('not-an-email') === null,
        'mailbox parser accepts only validated stored addresses'
    );

    $contactMessage = '<html><body>contact-message</body></html>';
    $trace = [];
    $result = red_public_form_operation_execute(
        'contact',
        $contactRequest,
        ['contactSession' => true, 'baseUrl' => 'example.test'],
        red_public_form_operation_test_dependencies($originalForm, $trace, true, false, $contactMessage)
    );
    red_public_form_operation_test_assert(
        $result === [
            'httpStatus' => 200,
            'body' => $contactMessage,
            'headers' => [],
            'browserOutcome' => 'contact-transport-success',
            'effectTrace' => ['fetch-form', 'build-message', 'consume-contact-session', 'phpmailer-send'],
        ]
            && $trace === [
                'fetch:93039112', 'build:contact:6:6', 'consume', 'send:contact:6:1',
            ],
        'original Contact executes lookup, validation, message, one-time session, and mail in order'
    );

    $alternateTrace = [];
    $alternateResult = red_public_form_operation_execute(
        'contact',
        $alternateRequest,
        ['contactSession' => true, 'baseUrl' => 'example.test'],
        red_public_form_operation_test_dependencies($alternateForm, $alternateTrace, true, false, $contactMessage)
    );
    red_public_form_operation_test_assert(
        $alternateResult['httpStatus'] === 200
            && $alternateTrace === [
                'fetch:3400000200', 'build:contact:4:4', 'consume', 'send:contact:4:1',
            ],
        'alternate Contact resolves and submits through the same dynamic boundary'
    );

    $aliasTrace = [];
    $aliasRequest = $contactRequest;
    $aliasRequest['payload']['alias'] = 'tampered';
    red_public_form_operation_test_throws(
        static function () use ($aliasRequest, $originalForm, &$aliasTrace, $contactMessage) {
            red_public_form_operation_execute(
                'contact',
                $aliasRequest,
                ['contactSession' => true, 'baseUrl' => 'example.test'],
                red_public_form_operation_test_dependencies($originalForm, $aliasTrace, true, false, $contactMessage)
            );
        },
        InvalidArgumentException::class,
        'posted alias tampering'
    );
    red_public_form_operation_test_assert(
        $aliasTrace === ['fetch:93039112'],
        'alias tampering fails before message, session, or mail effects'
    );

    foreach (['missing', 'wrong-type', 'inactive-or-expired'] as $label) {
        $lookupTrace = [];
        $lookupResult = red_public_form_operation_execute(
            'contact',
            $contactRequest,
            ['contactSession' => true, 'baseUrl' => 'example.test'],
            red_public_form_operation_test_dependencies(null, $lookupTrace, true, false, $contactMessage)
        );
        red_public_form_operation_test_assert(
            $lookupResult['httpStatus'] === 302
                && $lookupResult['headers'] === ['Location' => 'http://example.test']
                && $lookupTrace === ['fetch:93039112'],
            $label . ' Contact dependency outcome redirects without mutation'
        );
    }
    $invalidFetchTrace = [];
    red_public_form_operation_test_throws(
        static function () use ($contactRequest, &$invalidFetchTrace, $contactMessage) {
            red_public_form_operation_execute(
                'contact',
                $contactRequest,
                ['contactSession' => true, 'baseUrl' => 'example.test'],
                red_public_form_operation_test_dependencies(false, $invalidFetchTrace, true, false, $contactMessage)
            );
        },
        RuntimeException::class,
        'invalid resolver dependency type'
    );

    $guardTrace = [];
    $guardResult = red_public_form_operation_execute(
        'contact',
        $contactRequest,
        ['contactSession' => false, 'baseUrl' => 'example.test'],
        red_public_form_operation_test_dependencies($originalForm, $guardTrace, true, false, $contactMessage)
    );
    red_public_form_operation_test_assert(
        $guardResult['httpStatus'] === 302 && $guardTrace === [],
        'missing one-time Contact session redirects before every dependency'
    );

    $honeypotTrace = [];
    $honeypotRequest = $contactRequest;
    $honeypotRequest['payload']['name'] = '';
    $honeypotRequest['payload']['MySpamTrap'] = 'bot';
    $honeypotResult = red_public_form_operation_execute(
        'contact',
        $honeypotRequest,
        ['contactSession' => true, 'baseUrl' => 'example.test'],
        red_public_form_operation_test_dependencies($originalForm, $honeypotTrace, true, true, $contactMessage)
    );
    red_public_form_operation_test_assert(
        $honeypotResult['body'] === $contactMessage
            && end($honeypotResult['effectTrace']) === 'honeypot-suppress-mail'
            && $honeypotTrace === ['fetch:93039112', 'build:contact:6:6', 'consume'],
        'honeypot consumes one-time state and returns compatibility HTML without either mail transport'
    );

    $fallbackTrace = [];
    $fallbackResult = red_public_form_operation_execute(
        'contact',
        $contactRequest,
        ['contactSession' => true, 'baseUrl' => 'example.test'],
        red_public_form_operation_test_dependencies($originalForm, $fallbackTrace, false, false, $contactMessage)
    );
    red_public_form_operation_test_assert(
        $fallbackResult['httpStatus'] === 200
            && end($fallbackResult['effectTrace']) === 'native-mail-fallback'
            && $fallbackTrace === [
                'fetch:93039112', 'build:contact:6:6', 'consume',
                'send:contact:6:1', 'fallback:owner@example.test:1',
            ],
        'primary mail failure uses only the validated first stored recipient and preserves HTTP compatibility'
    );

    $loginCalls = [];
    $loginResult = red_public_form_operation_execute(
        'login',
        $loginRequest,
        [],
        ['authenticate' => static function ($username, $password) use (&$loginCalls) {
            $loginCalls[] = [$username, $password];
            return 'success';
        }]
    );
    red_public_form_operation_test_assert(
        $loginResult['body'] === 'yes'
            && $loginResult['effectTrace'] === ['authenticate-admin:success']
            && $loginCalls === [['codex-boundary', 'not-a-live-secret']],
        'Login success contract remains exact'
    );
    foreach (['invalid', 'unknown', 'throttled', 'unavailable'] as $outcome) {
        $loginFailure = red_public_form_operation_execute(
            'login',
            $loginRequest,
            [],
            ['authenticate' => static function () use ($outcome) { return $outcome; }]
        );
        red_public_form_operation_test_assert(
            $loginFailure['body'] === 'no' && $loginFailure['httpStatus'] === 200,
            'Login ' . $outcome . ' stays generic'
        );
    }
    $blankLogin = $loginPayload;
    $blankLogin['username'] = '';
    $blankLogin['password'] = '';
    $loginAuthCalls = 0;
    $blankResult = red_public_form_operation_execute(
        'login',
        ['method' => 'POST', 'endpoint' => '/bin/login.php', 'payload' => $blankLogin],
        [],
        ['authenticate' => static function () use (&$loginAuthCalls) {
            $loginAuthCalls++;
            return 'success';
        }]
    );
    red_public_form_operation_test_assert(
        $blankResult['body'] === 'no' && $loginAuthCalls === 0,
        'server-invalid Login credentials stop before authentication'
    );
    red_public_form_operation_test_throws(
        static function () use ($loginRequest) {
            red_public_form_operation_execute(
                'login',
                $loginRequest,
                [],
                ['authenticate' => static function () { return 'maybe'; }]
            );
        },
        RuntimeException::class,
        'unknown Login dependency outcome'
    );

    $report = red_public_form_operation_report();
    $encodedReport = json_encode($report, JSON_UNESCAPED_SLASHES);
    red_public_form_operation_test_assert(
        $report['schemaVersion'] === 2
            && $report['mode'] === 'live-operational-form-boundary'
            && array_keys($report['operations']) === ['contact', 'login']
            && $report['scope']['databaseWrites'] === 0
            && $report['scope']['mailSends'] === 0
            && is_string($encodedReport)
            && strpos($encodedReport, 'not-a-live-secret') === false
            && strpos($encodedReport, 'owner@example.test') === false,
        'redacted pure report records zero real effects'
    );

    $protectedHashes = [
        'class/class_forms.php' => '143e956e09fbb4d576224b32d66c5392cba1f4e30f882709e78e501e5cdc99a8',
        'bin/contact.php' => 'ae4b4bdcb134e6f3e1dc62fe62e5ea3ad91b978b4e05e32b8ba334c7cd17e7c2',
        'bin/login.php' => '337cddf44c4b7273ff394238af01f9fe91d4fe82216c956d2ba34189a5e8e1f4',
        'includes/legacy_component_helpers.php' => 'f1c733fc56c7425c9f86750b20b9080da06de8d313339e35e33c5913d24a6772',
        'includes/theme_preview_contact_helpers.php' => '0817b08fdddcd13d642db3358dbb4c3c39979a2d9e88e2916f77b4c629c751f4',
        'includes/theme_preview_home_helpers.php' => '34c78e9bc334aeff05a216a7ba42b1c7ddbd5c358f207bddd3388f7d9c46feb9',
        'includes/theme_preview_administration_helpers.php' => 'a7348694e0ef5caafce3b29da140214a7468e92fce56e23264a850427061f5c8',
        'includes/theme_preview_instructions_helpers.php' => '42f06a11e4bd995e15f6027ad6d03b79844143d57ec649324c0f209580c1948b',
        'includes/theme_preview_login_helpers.php' => '348ed2c8edc6f3131881dc6c9001497254c7caa7ff0c0529d3d1f696e7a6e711',
        'includes/theme_preview_selected_contact_helpers.php' => '5f14c5f4791f831d24b2516859bf8cf74809ecfce6ee760f17ca362b4b8ae278',
        'themes/starter-reference/components/form.php' => '9ac0e640bf5d2442365aa1ceb58ecf5697bf9d275c08228d758c2a4313647ec2',
        'themes/starter-reference/theme.json' => '1cf62b930eff39be6430e75d07cf29844e213dae8660870bbd4077cca09db033',
    ];
    foreach ($protectedHashes as $relativePath => $expectedHash) {
        red_public_form_operation_test_assert(
            hash_file('sha256', $repositoryRoot . '/' . $relativePath) === $expectedHash,
            'protected live/display-only source remains exact: ' . $relativePath
        );
    }

    $contactSource = file_get_contents($repositoryRoot . '/bin/contact.php');
    $responseSource = file_get_contents($repositoryRoot . '/bin/response.php');
    $registerSource = file_get_contents($repositoryRoot . '/bin/register.php');
    $publicHelperSource = file_get_contents($repositoryRoot . '/includes/public_form_helpers.php');
    $loginSource = file_get_contents($repositoryRoot . '/bin/login.php');
    $legacyFormRendererSource = file_get_contents($repositoryRoot . '/themes/legacy-bootstrap/components/form.php');
    $compatibilityFormRendererSource = file_get_contents($repositoryRoot . '/class/class_forms.php');
    red_public_form_operation_test_assert(
        is_string($contactSource)
            && strpos($contactSource, 'red_public_contact_fetch_record') !== false
            && strpos($contactSource, "require_once __DIR__ . '/phpmailer.php';") !== false
            && strpos($contactSource, "unset(\$_SESSION['contact'])") !== false
            && strpos($contactSource, 'getlocation(') === false
            && strpos($contactSource, 'orojas01@gmail.com') === false
            && strpos($contactSource, 'red_verify_csrf') === false
            && strpos($contactSource, 'red_require_admin') === false,
        'Contact adapter uses dynamic resolver, lowercase local mailer, no geolocation or fixed fallback'
    );
    red_public_form_operation_test_assert(
        is_string($publicHelperSource)
            && strpos($publicHelperSource, "f.FormType='Contact'") !== false
            && strpos($publicHelperSource, "a.Component='Form'") !== false
            && strpos($publicHelperSource, "a.Active='Y'") !== false
            && strpos($publicHelperSource, 'a.StartDate<=NOW()') !== false
            && strpos($publicHelperSource, '(YEAR(a.ExpDate)=0 OR a.ExpDate>NOW())') !== false,
        'Contact resolver source requires the active scheduled paired Contact relationship'
    );
    red_public_form_operation_test_assert(
        is_string($responseSource)
            && strpos($responseSource, "red_public_operational_form_fetch_record(\$db->connection, \$recordId, 'Response')") !== false
            && strpos($responseSource, 'red_public_contact_validate_submission') !== false
            && strpos($responseSource, 'redspheredevelopment@gmail.com') === false
            && strpos($responseSource, 'getlocation(') === false,
        'Response resolves an active pair, validates stored fields, and has no fixed fallback recipient'
    );
    red_public_form_operation_test_assert(
        is_string($registerSource)
            && strpos($registerSource, "red_public_operational_form_fetch_record(\$db->connection, \$recordId, 'Register')") !== false
            && strpos($registerSource, 'red_public_contact_validate_submission') !== false
            && strpos($registerSource, "'RED_Register_' . \$form['articleRecordId']") !== false
            && strpos($registerSource, 'red_public_form_insert_submission') !== false
            && strpos($registerSource, 'getlocation(') === false,
        'Register validates before writing only to its server-derived storage table'
    );
    red_public_form_operation_test_assert(
        strpos($publicHelperSource, 'function red_public_operational_form_fetch_record') !== false
            && strpos($publicHelperSource, 'f.FormType=?') !== false
            && substr_count($publicHelperSource, "a.Component='Form'") >= 2
            && substr_count($publicHelperSource, "a.Active='Y'") >= 2,
        'Response and Register share the active scheduled paired-Form resolver'
    );
    red_public_form_operation_test_assert(
        is_string($loginSource)
            && strpos($loginSource, 'includes/public_form_operation_helpers.php') !== false
            && strpos($loginSource, "'alias' => \$loginContract['form']['alias']") !== false
            && strpos($loginSource, "return 'success';") !== false
            && strpos($loginSource, 'session_regenerate_id(true)') !== false,
        'Login adapter remains anchored to its fixed compatibility boundary'
    );

    red_public_form_operation_test_assert(
        red_public_contact_compile_fields(
            '#|name=mixed_case|type=TextField|required=true|displayname=Mixed case'
        )[0]['type'] === 'textfield',
        'mixed-case expert field types normalize to the canonical public type'
    );
    red_public_form_operation_test_assert(
        red_legacy_public_form_template_fields(
            '#|name=destination|type=hidden|initialvalue=https://example.test/?a=b'
        )[0]['initialvalue'] === 'https://example.test/?a=b',
        'legacy field parsing preserves equals signs inside stored values'
    );

    if (!defined('language')) {
        define('language', 'en');
    }
    $_SERVER['REQUEST_URI'] = '/security-probe';
    $renderProbeDefinition = '#|question=Question <script>alert(0)</script>|name=class|type=TextField|required=true'
        .'|displayname=What\'s <script>alert(1)</script>|initialvalue="quoted" <b>value</b>;'
        .'#|question=|name=choice|type=select|required=true|displayname=Choose'
        .'|value=<script>alert(2)</script>^selected,Safe;'
        .'#|type=paragraph|paragraph=<script>alert(3)</script>;'
        .'#|question=|name=Submit|type=button|displayname=Send "now"';
    $redThemeFormContext = red_legacy_public_form_context_from_data([[
        'RecordID' => 700000001,
        'RefID' => '700000002',
        'Alias' => 'class',
        'Title' => 'Security probe',
        'FormType' => 'Other',
        'LongDesc' => $renderProbeDefinition,
    ]]);
    ob_start();
    include $repositoryRoot . '/themes/legacy-bootstrap/components/form.php';
    $renderProbeMarkup = ob_get_clean();
    red_public_form_operation_test_assert(
        strpos($renderProbeMarkup, 'name="class"') !== false
            && strpos($renderProbeMarkup, 'function checkform_class (formElement)') !== false
            && strpos($renderProbeMarkup, 'function checkform_class (class)') === false
            && strpos($renderProbeMarkup, 'var class=') === false
            && strpos($renderProbeMarkup, 'What&#039;s &lt;script&gt;alert(1)&lt;/script&gt;') !== false
            && strpos($renderProbeMarkup, 'value="&quot;quoted&quot; &lt;b&gt;value&lt;/b&gt;"') !== false,
        'mixed-case fields render and administrator text is escaped in public HTML'
    );
    red_public_form_operation_test_assert(
        strpos($renderProbeMarkup, '<script>alert(0)</script>') === false
            && strpos($renderProbeMarkup, '<script>alert(1)</script>') === false
            && strpos($renderProbeMarkup, '<script>alert(2)</script>') === false
            && strpos($renderProbeMarkup, '<script>alert(3)</script>') === false
            && strpos($renderProbeMarkup, '\\u0027s alert(1)') !== false,
        'public Form questions, choices, notes, and generated validation JavaScript resist stored markup'
    );
    foreach ([
        [$legacyFormRendererSource, "type=\"checkbox\" name=\"'.\$name.'[]\""],
        [$compatibilityFormRendererSource, "type=\"checkbox\" name=\"'.\$nameHtml.'[]\""],
    ] as [$rendererSource, $checkboxNameSource]) {
        red_public_form_operation_test_assert(
            is_string($rendererSource)
                && substr_count($rendererSource, $checkboxNameSource) === 2
                && strpos($rendererSource, 'querySelectorAll') !== false
                && strpos($rendererSource, 'Array.from') !== false,
            'every checkbox option renders as an array and grouped validation/serialization stays enabled'
        );
    }
    red_public_form_operation_test_assert(
        strpos($legacyFormRendererSource, '$preparePublicFormFields') !== false
            && strpos($legacyFormRendererSource, '$formJsLiteral') !== false
            && strpos($legacyFormRendererSource, 'red_public_display_text') !== false
            && strpos($legacyFormRendererSource, '.elements.namedItem(') !== false
            && strpos($legacyFormRendererSource, ' (formElement)') !== false,
        'standard-theme Form rendering normalizes fields and uses contextual HTML and JavaScript escaping'
    );
    red_public_form_operation_test_assert(
        strpos($compatibilityFormRendererSource, '$normalizedFormArray') !== false
            && strpos($compatibilityFormRendererSource, '$javascriptString') !== false
            && strpos($compatibilityFormRendererSource, 'red_public_display_text') !== false
            && strpos($compatibilityFormRendererSource, '.elements.namedItem(') !== false
            && strpos($compatibilityFormRendererSource, ' (formElement)') !== false,
        'fallback Form rendering keeps the same normalized and escaped field boundary'
    );

    printf("Operational Form boundary self-test passed: %d assertions.\n", $assertions);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . ' (after ' . $assertions . " assertions)\n");
    exit(1);
}

?>
