# P3E-9D4D Diagnostic Recovery

Status: local offline diagnostic implementation complete on 2026-08-24. A
new provider attempt remains separately authorization-gated.

The first D4D operational attempt created a Checkout-Sessions-Write-only
restricted key in the dedicated `RED-CMS Store Lite Development` Sandbox and
passed the exact dry-run confirmation gate. The one-shot process did not
return a bounded created result. Stripe's authoritative Sandbox API activity
showed zero requests, the key remained `Last used —`, and no Checkout Session,
payment, webhook, browser navigation, Store Lite mutation, live-mode action,
or deployment occurred. The unused key was then expired, and all temporary
secret files, databases, grants, and runner files were removed.

## Bounded failure stage

The adapter operation and core runner now preserve only one closed non-secret
failure stage:

- `none`;
- `preflight_refused`;
- `transport_exchange_failed`;
- `exchange_invariant_failed`;
- `response_decode_failed`;
- `response_acceptance_failed`;
- `core_invocation_failed`; or
- `adapter_invocation_failed`.

The value contains no credential, reference, URL, header, body, request ID,
provider object, customer field, network error text, or retry authority. Core
includes it in the already-bounded outcome hash and the server-local CLI prints
it after execution. Created outcomes require `none`; every other provider-side
outcome remains conservative, attempt-consuming, and no-retry.

## Acceptance

- Stripe adapter aggregate suite passes, including 89 D4A assertions.
- RED-CMS focused D4C1 command checks pass 74 assertions.
- Full RED-CMS acceptance passes against a fresh disposable current-schema
  primary and acceptance database, followed by exact database/grant cleanup.
- The retained starter, hosted demo, Store Lite current main, `.codex/`, and
  credentials workbook remain unchanged.

## Next gate

A future D4D recovery attempt requires a new explicit authorization, a new
least-privilege restricted Sandbox key, a fresh disposable database and
authorization/claim, review of the dry run, exactly one apply, review of the
bounded failure stage plus Stripe request log, and key expiration. It does not
authorize a payment, webhook, browser checkout, client installation, live
mode, or deployment.
