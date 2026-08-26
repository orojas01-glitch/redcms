<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/includes/addon_subscription_checkout_public_response_helpers.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};
$execution = static fn (bool $replayed = false): array => [
    'completed' => !$replayed,
    'replayed' => $replayed,
    'outcome' => $replayed ? 'unchanged' : 'accepted',
    'route' => 'redcms.store-lite/subscription-intent',
    'mutation' => 'redcms.store-lite/create-subscription-intent',
    'reason' => $replayed ? 'replayed' : 'completed',
];
$coordinator = static function (
    int $subjectRecordId,
    string $offerId,
    string $suffix
): array {
    $session = 'cs_test_SubscriptionPublicResponse' . $suffix;
    $result = red_addon_subscription_checkout_result();
    return array_replace($result, [
        'valid' => true,
        'ready' => true,
        'status' => 'synthetic_redirect_ready',
        'subjectRecordId' => $subjectRecordId,
        'offerId' => $offerId,
        'intentReference' => 'sint_' . str_repeat(strtolower($suffix[0]), 32),
        'contractSha256' => hash('sha256', 'contract-' . $suffix),
        'requestSha256' => hash('sha256', 'request-' . $suffix),
        'responseEvidenceSha256' => hash('sha256', 'response-' . $suffix),
        'resultSha256' => hash('sha256', 'result-' . $suffix),
        'checkoutSessionRefSha256' => hash('sha256', $session),
        'checkoutUrl' => 'https://checkout.stripe.com/c/pay/' . $session
            . '#synthetic-' . strtolower($suffix),
        'expiresAtEpoch' => 1787632200,
        'httpStatus' => 303,
        'cacheControl' => 'no-store',
        'navigationMode' => 'location.assign',
        'transientOnly' => true,
        'responseEmission' => false,
        'networkAccess' => false,
        'providerContact' => false,
        'providerMutation' => false,
        'checkoutCreation' => false,
        'subscriptionCreation' => false,
        'browserNavigation' => false,
        'reason' => 'synthetic_redirect_ready',
        'errors' => [],
    ]);
};

try {
    $source = (string) file_get_contents(
        dirname(__DIR__)
            . '/includes/addon_subscription_checkout_public_response_helpers.php'
    );
    $assert(
        preg_match(
            '/\$_(?:GET|POST|COOKIE|SERVER|SESSION)|\b(?:mysqli|PDO|curl|fsockopen|getenv|setcookie)\b/',
            $source
        ) !== 1,
        'handoff reads no request, cookie, session, database, secret, or network state'
    );

    $clientA = $coordinator(101, 'membership-monthly', 'A123456789012345');
    $responseA = red_addon_subscription_checkout_public_response(
        $execution(),
        101,
        'membership-monthly',
        $clientA
    );
    $assert(
        red_addon_subscription_checkout_public_response_valid($responseA),
        'completed subscription intent produces one closed AJAX handoff'
    );
    $payloadA = json_decode($responseA['body'], true, 8, JSON_THROW_ON_ERROR);
    $assert(
        $responseA['httpStatus'] === 200
            && $responseA['headers']['Cache-Control'] === 'no-store'
            && $payloadA === [
                'ok' => true,
                'outcome' => 'subscription_checkout_ready',
                'checkoutUrl' => $clientA['checkoutUrl'],
                'navigationMode' => 'location.assign',
            ],
        'browser receives only the exact transient navigation vocabulary'
    );
    $assert(
        red_addon_subscription_checkout_public_response(
            $execution(true),
            101,
            'membership-monthly',
            $clientA
        )['valid'] === true,
        'exact public-mutation replay may reproduce the same handoff'
    );

    $assert(
        red_addon_subscription_checkout_public_response(
            $execution(),
            102,
            'membership-monthly',
            $clientA
        )['valid'] === false
            && red_addon_subscription_checkout_public_response(
                $execution(),
                101,
                'membership-yearly',
                $clientA
            )['valid'] === false,
        'subject and offer drift fail before browser authorization'
    );
    $foreign = $clientA;
    $foreign['checkoutUrl'] = str_replace(
        'checkout.stripe.com',
        'evil.example.test',
        $foreign['checkoutUrl']
    );
    $assert(
        red_addon_subscription_checkout_public_response(
            $execution(),
            101,
            'membership-monthly',
            $foreign
        )['valid'] === false,
        'foreign Checkout origin is refused independently'
    );
    $forgedResponse = $responseA;
    $forgedResponse['checkoutUrl'] = $foreign['checkoutUrl'];
    $forgedResponse['body'] = json_encode([
        'ok' => true,
        'outcome' => 'subscription_checkout_ready',
        'checkoutUrl' => $foreign['checkoutUrl'],
        'navigationMode' => 'location.assign',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $forgedResponse['headers']['Content-Length'] = (string) strlen(
        $forgedResponse['body']
    );
    $assert(
        !red_addon_subscription_checkout_public_response_valid(
            $forgedResponse
        ),
        'emitter validation independently refuses a forged response origin'
    );
    $effectful = $clientA;
    $effectful['providerContact'] = true;
    $assert(
        red_addon_subscription_checkout_public_response(
            $execution(),
            101,
            'membership-monthly',
            $effectful
        )['valid'] === false,
        'a claimed provider effect cannot enter the offline handoff'
    );
    $wrongExecution = $execution();
    $wrongExecution['mutation'] = 'redcms.store-lite/create-order';
    $assert(
        red_addon_subscription_checkout_public_response(
            $wrongExecution,
            101,
            'membership-monthly',
            $clientA
        )['valid'] === false,
        'non-subscription public mutation cannot receive a Checkout handoff'
    );

    $clientB = $coordinator(202, 'membership-yearly', 'B123456789012345');
    $responseB = red_addon_subscription_checkout_public_response(
        $execution(),
        202,
        'membership-yearly',
        $clientB
    );
    $assert(
        red_addon_subscription_checkout_public_response_valid($responseB)
            && $responseA['authorizationSha256']
                !== $responseB['authorizationSha256']
            && !str_contains($responseA['body'], $clientB['checkoutUrl'])
            && !str_contains($responseB['body'], $clientA['checkoutUrl']),
        'two client-local contexts produce distinct non-crossing handoffs'
    );
    $assert(
        !str_contains($responseA['body'], 'intentReference')
            && !str_contains($responseA['body'], 'subjectRecordId')
            && !str_contains($responseA['body'], 'Sha256'),
        'internal subject and hash evidence never enters the browser body'
    );

    echo 'Subscription Checkout public response passed '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
