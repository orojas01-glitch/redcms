<?php
/**
 * Dependency-free checks for the pure public-mutation HTTP request envelope.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot .
    '/includes/addon_public_mutation_http_request_helpers.php';

$assertions = 0;

function red_addon_public_mutation_http_request_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_http_request_test_manifest()
{
    return [
        'id' => 'redcms.http-request-fixture',
        'routes' => [[
            'id' => 'redcms.http-request-fixture/cart-intent',
            'scope' => 'public',
            'path' => '/addons/redcms/http-request-fixture/cart-intent',
            'methods' => ['POST'],
            'authentication' => 'public',
            'csrf' => 'required',
        ]],
        'publicMutationContracts' => [[
            'route' => 'redcms.http-request-fixture/cart-intent',
            'mutation' => 'redcms.http-request-fixture/add-to-cart',
            'scope' => 'public',
            'authentication' => 'public',
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/x-www-form-urlencoded',
            'maxBodyBytes' => 128,
            'requestFields' => [
                [
                    'key' => 'product',
                    'type' => 'identifier',
                    'required' => true,
                    'minLength' => 1,
                    'maxLength' => 120,
                ],
                [
                    'key' => 'quantity',
                    'type' => 'positive-integer',
                    'required' => true,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
            'subject' => 'anonymous',
            'idempotency' => 'core-issued-key',
            'privacy' => 'no-store',
            'rateLimit' => 'required',
            'tables' => ['RED_Addon_Http_Request_Fixture_Carts'],
            'postcondition' => 'server-derived-state',
            'audit' => 'commerce.cart.item-added',
            'outcomes' => ['accepted', 'unchanged'],
        ]],
    ];
}

function red_addon_public_mutation_http_request_test_headers(
    $subjectToken,
    $csrfToken,
    $idempotencyKey,
    $body,
    $contentType = 'application/x-www-form-urlencoded',
    $includeLength = false
) {
    $headers = [
        ['name' => 'Origin', 'value' => 'https://store.example.test'],
        ['name' => 'Content-Type', 'value' => $contentType],
        [
            'name' => 'Cookie',
            'value' => 'theme=adriana; '
                . red_addon_public_mutation_subject_cookie_name()
                . '=' . $subjectToken . '; locale=es',
        ],
        [
            'name' => red_addon_public_mutation_http_request_csrf_header_name(),
            'value' => $csrfToken,
        ],
        [
            'name' => red_addon_public_mutation_http_request_idempotency_header_name(),
            'value' => $idempotencyKey,
        ],
    ];
    if ($includeLength) {
        array_splice($headers, 2, 0, [[
            'name' => 'Content-Length',
            'value' => (string) strlen($body),
        ]]);
    }
    return $headers;
}

function red_addon_public_mutation_http_request_test_refusal($result, $reason)
{
    return $result === [
        'valid' => false,
        'method' => '',
        'path' => '',
        'origin' => '',
        'body' => '',
        'csrfToken' => '',
        'idempotencyKey' => '',
        'subjectToken' => '',
        'reason' => $reason,
    ];
}

try {
    $manifest = red_addon_public_mutation_http_request_test_manifest();
    $routeId = 'redcms.http-request-fixture/cart-intent';
    $mutationId = 'redcms.http-request-fixture/add-to-cart';
    $path = '/addons/redcms/http-request-fixture/cart-intent';
    $origin = 'https://store.example.test';
    $subjectToken = str_repeat('a', 64);
    $csrfToken = str_repeat('b', 64);
    $idempotencyKey = str_repeat('c', 64);
    $body = 'product=SKU-42&quantity=2';

    red_addon_public_mutation_http_request_test_assert(
        red_addon_public_mutation_http_request_csrf_header_name()
            === 'X-RED-CMS-CSRF'
            && red_addon_public_mutation_http_request_idempotency_header_name()
                === 'Idempotency-Key',
        'the core owns the exact CSRF and idempotency header names'
    );
    red_addon_public_mutation_http_request_test_assert(
        red_addon_public_mutation_http_request_trusted_origin($origin) === $origin
            && red_addon_public_mutation_http_request_trusted_origin(
                'https://127.0.0.1:8443'
            ) === 'https://127.0.0.1:8443'
            && red_addon_public_mutation_http_request_trusted_origin(
                'https://[::1]:8443'
            ) === 'https://[::1]:8443',
        'only canonical HTTPS server origins are accepted'
    );
    foreach ([
        'http://store.example.test',
        'https://Store.example.test',
        'https://store.example.test/',
        'https://store.example.test:443',
        'https://user@store.example.test',
        'https://store.example.test?target=cart',
    ] as $invalidOrigin) {
        red_addon_public_mutation_http_request_test_assert(
            red_addon_public_mutation_http_request_trusted_origin($invalidOrigin)
                === '',
            'noncanonical trusted origins are unavailable to the request bridge'
        );
    }

    $contract = red_addon_public_mutation_http_request_contract(
        $manifest,
        $routeId,
        $mutationId
    );
    red_addon_public_mutation_http_request_test_assert(
        is_array($contract)
            && $contract['path'] === $path
            && $contract['maxBodyBytes'] === 128,
        'only one normalized static POST declaration supplies the envelope limit'
    );

    $headers = red_addon_public_mutation_http_request_test_headers(
        $subjectToken,
        $csrfToken,
        $idempotencyKey,
        $body
    );
    $normalized = red_addon_public_mutation_http_request_normalize(
        $manifest,
        $routeId,
        $mutationId,
        $origin,
        'POST',
        $path,
        $headers,
        $body
    );
    red_addon_public_mutation_http_request_test_assert(
        $normalized === [
            'valid' => true,
            'method' => 'POST',
            'path' => $path,
            'origin' => $origin,
            'body' => $body,
            'csrfToken' => $csrfToken,
            'idempotencyKey' => $idempotencyKey,
            'subjectToken' => $subjectToken,
            'reason' => 'normalized',
        ],
        'complete canonical transport yields only the final opaque core envelope'
    );

    $reordered = $headers;
    $reordered[0] = [
        'name' => 'idempotency-key',
        'value' => $idempotencyKey,
    ];
    $reordered[4] = ['name' => 'Origin', 'value' => $origin];
    red_addon_public_mutation_http_request_test_assert(
        red_addon_public_mutation_http_request_normalize(
            $manifest,
            $routeId,
            $mutationId,
            $origin,
            'POST',
            $path,
            $reordered,
            $body
        ) === $normalized,
        'critical header names are case-insensitive and their transport order is irrelevant'
    );

    $opaqueBody = 'price=1';
    red_addon_public_mutation_http_request_test_assert(
        red_addon_public_mutation_http_request_normalize(
            $manifest,
            $routeId,
            $mutationId,
            $origin,
            'POST',
            $path,
            red_addon_public_mutation_http_request_test_headers(
                $subjectToken,
                $csrfToken,
                $idempotencyKey,
                $opaqueBody
            ),
            $opaqueBody
        )['body'] === $opaqueBody,
        'the envelope deliberately leaves package-field validation to the separate decoder'
    );

    $method = red_addon_public_mutation_http_request_normalize(
        $manifest,
        $routeId,
        $mutationId,
        $origin,
        'GET',
        $path,
        $headers,
        $body
    );
    red_addon_public_mutation_http_request_test_assert(
        red_addon_public_mutation_http_request_test_refusal(
            $method,
            'method_not_allowed'
        ),
        'an exact static mutation path refuses every non-POST method without evidence'
    );
    foreach ([$path . '?retry=1', $path . '/', $path . '%2F'] as $invalidPath) {
        red_addon_public_mutation_http_request_test_assert(
            red_addon_public_mutation_http_request_test_refusal(
                red_addon_public_mutation_http_request_normalize(
                    $manifest,
                    $routeId,
                    $mutationId,
                    $origin,
                    'POST',
                    $invalidPath,
                    $headers,
                    $body
                ),
                'invalid_request'
            ),
            'only the exact unencoded declaration path can enter the envelope'
        );
    }

    $forged = $manifest;
    $forged['publicMutationContracts'][0]['path'] = '/forged';
    red_addon_public_mutation_http_request_test_assert(
        red_addon_public_mutation_http_request_test_refusal(
            red_addon_public_mutation_http_request_normalize(
                $forged,
                $routeId,
                $mutationId,
                $origin,
                'POST',
                $path,
                $headers,
                $body
            ),
            'runtime_unavailable'
        )
            && red_addon_public_mutation_http_request_test_refusal(
                red_addon_public_mutation_http_request_normalize(
                    $manifest,
                    $routeId,
                    $mutationId,
                    'http://store.example.test',
                    'POST',
                    $path,
                    $headers,
                    $body
                ),
                'runtime_unavailable'
            ),
        'forged declarations and untrusted server origin configuration expose no request evidence'
    );

    $wrongOrigin = $headers;
    $wrongOrigin[0]['value'] = 'https://attacker.example.test';
    red_addon_public_mutation_http_request_test_assert(
        red_addon_public_mutation_http_request_test_refusal(
            red_addon_public_mutation_http_request_normalize(
                $manifest,
                $routeId,
                $mutationId,
                $origin,
                'POST',
                $path,
                $wrongOrigin,
                $body
            ),
            'origin_invalid'
        ),
        'only the exact server-configured origin is accepted'
    );
    $duplicateOrigin = $headers;
    $duplicateOrigin[] = ['name' => 'Origin', 'value' => $origin];
    red_addon_public_mutation_http_request_test_assert(
        red_addon_public_mutation_http_request_test_refusal(
            red_addon_public_mutation_http_request_normalize(
                $manifest,
                $routeId,
                $mutationId,
                $origin,
                'POST',
                $path,
                $duplicateOrigin,
                $body
            ),
            'invalid_request'
        ),
        'ambiguous critical headers fail before any opaque evidence is returned'
    );

    foreach ([
        'application/x-www-form-urlencoded',
        'application/x-www-form-urlencoded;charset=UTF-8',
    ] as $contentType) {
        red_addon_public_mutation_http_request_test_assert(
            red_addon_public_mutation_http_request_normalize(
                $manifest,
                $routeId,
                $mutationId,
                $origin,
                'POST',
                $path,
                red_addon_public_mutation_http_request_test_headers(
                    $subjectToken,
                    $csrfToken,
                    $idempotencyKey,
                    $body,
                    $contentType
                ),
                $body
            ) === $normalized,
            'the only two canonical form content types retain one envelope'
        );
    }
    foreach ([
        'application/x-www-form-urlencoded; charset=UTF-8',
        'Application/X-Www-Form-Urlencoded',
        'application/json',
    ] as $contentType) {
        red_addon_public_mutation_http_request_test_assert(
            red_addon_public_mutation_http_request_test_refusal(
                red_addon_public_mutation_http_request_normalize(
                    $manifest,
                    $routeId,
                    $mutationId,
                    $origin,
                    'POST',
                    $path,
                    red_addon_public_mutation_http_request_test_headers(
                        $subjectToken,
                        $csrfToken,
                        $idempotencyKey,
                        $body,
                        $contentType
                    ),
                    $body
                ),
                'content_type_invalid'
            ),
            'noncanonical content metadata is refused before body decoding'
        );
    }

    $withLength = red_addon_public_mutation_http_request_test_headers(
        $subjectToken,
        $csrfToken,
        $idempotencyKey,
        $body,
        'application/x-www-form-urlencoded',
        true
    );
    red_addon_public_mutation_http_request_test_assert(
        red_addon_public_mutation_http_request_normalize(
            $manifest,
            $routeId,
            $mutationId,
            $origin,
            'POST',
            $path,
            $withLength,
            $body
        ) === $normalized,
        'an optional canonical content length must match the raw body exactly'
    );
    foreach (['0' . strlen($body), (string) (strlen($body) + 1)] as $length) {
        $invalidLength = $withLength;
        $invalidLength[2]['value'] = $length;
        red_addon_public_mutation_http_request_test_assert(
            red_addon_public_mutation_http_request_test_refusal(
                red_addon_public_mutation_http_request_normalize(
                    $manifest,
                    $routeId,
                    $mutationId,
                    $origin,
                    'POST',
                    $path,
                    $invalidLength,
                    $body
                ),
                'content_length_invalid'
            ),
            'noncanonical or mismatched content length exposes no request values'
        );
    }
    foreach (['Transfer-Encoding', 'Content-Encoding'] as $encodingHeader) {
        $encoded = $headers;
        $encoded[] = ['name' => $encodingHeader, 'value' => 'chunked'];
        red_addon_public_mutation_http_request_test_assert(
            red_addon_public_mutation_http_request_test_refusal(
                red_addon_public_mutation_http_request_normalize(
                    $manifest,
                    $routeId,
                    $mutationId,
                    $origin,
                    'POST',
                    $path,
                    $encoded,
                    $body
                ),
                'invalid_request'
            ),
            'encoded or transfer-framed body metadata has no public mutation path'
        );
    }

    $oversizedBody = str_repeat('x', 129);
    red_addon_public_mutation_http_request_test_assert(
        red_addon_public_mutation_http_request_test_refusal(
            red_addon_public_mutation_http_request_normalize(
                $manifest,
                $routeId,
                $mutationId,
                $origin,
                'POST',
                $path,
                $headers,
                $oversizedBody
            ),
            'body_too_large'
        ),
        'the declaration maximum rejects raw body overflow before package-field parsing'
    );

    $missingCookie = array_values(array_filter(
        $headers,
        static function ($header) {
            return strtolower($header['name']) !== 'cookie';
        }
    ));
    $duplicateCookie = $headers;
    $duplicateCookie[2]['value'] = red_addon_public_mutation_subject_cookie_name()
        . '=' . $subjectToken . '; '
        . red_addon_public_mutation_subject_cookie_name()
        . '=' . $subjectToken;
    $malformedSubject = $headers;
    $malformedSubject[2]['value'] = red_addon_public_mutation_subject_cookie_name()
        . '=' . str_repeat('A', 64);
    foreach ([$missingCookie, $duplicateCookie, $malformedSubject] as $badCookie) {
        red_addon_public_mutation_http_request_test_assert(
            red_addon_public_mutation_http_request_test_refusal(
                red_addon_public_mutation_http_request_normalize(
                    $manifest,
                    $routeId,
                    $mutationId,
                    $origin,
                    'POST',
                    $path,
                    $badCookie,
                    $body
                ),
                'subject_invalid'
            ),
            'missing, duplicate, or malformed opaque subject evidence is refused'
        );
    }

    foreach ([
        strtolower(red_addon_public_mutation_http_request_csrf_header_name()),
        strtolower(
            red_addon_public_mutation_http_request_idempotency_header_name()
        ),
    ] as $tokenHeader) {
        $missingToken = array_values(array_filter(
            $headers,
            static function ($header) use ($tokenHeader) {
                return strtolower($header['name']) !== $tokenHeader;
            }
        ));
        $reason = $tokenHeader === strtolower(
            red_addon_public_mutation_http_request_csrf_header_name()
        ) ? 'csrf_invalid' : 'idempotency_invalid';
        red_addon_public_mutation_http_request_test_assert(
            red_addon_public_mutation_http_request_test_refusal(
                red_addon_public_mutation_http_request_normalize(
                    $manifest,
                    $routeId,
                    $mutationId,
                    $origin,
                    'POST',
                    $path,
                    $missingToken,
                    $body
                ),
                $reason
            ),
            'each required opaque core header is independently mandatory'
        );
    }

    $source = file_get_contents(
        $projectRoot . '/includes/addon_public_mutation_http_request_helpers.php'
    );
    $frontController = file_get_contents($projectRoot . '/index.php');
    red_addon_public_mutation_http_request_test_assert(
        is_string($source)
            && is_string($frontController)
            && preg_match(
                '/\$_(?:SERVER|GET|POST|COOKIE|SESSION|REQUEST)\b/',
                $source
            ) !== 1
            && preg_match(
                '/\b(?:mysqli|header|http_response_code|setcookie|session_start|session_id|ob_start|ob_end_clean|ob_get_clean|file_get_contents|file_put_contents)\s*\(/',
                $source
            ) !== 1
            && strpos($source, 'php://') === false
            && strpos(
                $frontController,
                'addon_public_mutation_http_request_helpers.php'
            ) === false,
        'the envelope has no request-global, database, session, emission, filesystem, or front-controller path'
    );

    $serverBefore = $_SERVER;
    $getBefore = $_GET;
    $postBefore = $_POST;
    $cookieBefore = $_COOKIE;
    $requestBefore = $_REQUEST;
    $headersBefore = headers_list();
    $statusBefore = http_response_code();
    $bufferBefore = ob_get_level();
    red_addon_public_mutation_http_request_normalize(
        $manifest,
        $routeId,
        $mutationId,
        $origin,
        'POST',
        $path,
        $headers,
        $body
    );
    red_addon_public_mutation_http_request_test_assert(
        $_SERVER === $serverBefore
            && $_GET === $getBefore
            && $_POST === $postBefore
            && $_COOKIE === $cookieBefore
            && $_REQUEST === $requestBefore
            && headers_list() === $headersBefore
            && http_response_code() === $statusBefore
            && ob_get_level() === $bufferBefore,
        'normalization changes no PHP request-global, HTTP, session, or buffer state'
    );

    fwrite(
        STDOUT,
        'Public-mutation HTTP request self-test passed ('
            . $assertions . " assertions).\n"
    );
    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
