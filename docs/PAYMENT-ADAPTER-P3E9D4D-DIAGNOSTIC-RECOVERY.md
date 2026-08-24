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
- The restartable D4C2 rehearsal stages preserved Store Lite `0.1.35` by
  commit while permitting current Store Lite main to advance independently.
- Full RED-CMS acceptance passes against a fresh disposable current-schema
  primary and acceptance database, followed by exact database/grant cleanup.
- The retained starter, hosted demo, Store Lite current main, `.codex/`, and
  credentials workbook remain unchanged.

## Next gate

The permanent server-local harness is:

```text
scripts/sandbox-checkout-real-post-diagnostic-recovery.sh \
  --secret-values-file=/private/tmp/redcms-stripe-d4d-recovery-secret-values.json \
  --confirm-provider-recovery=yes
```

Provider mode is the default. It requires the exact mode-`0600` regular secret
inventory, one fresh disposable database, every D4 confirmation, and one
attempt. It preserves the command exit status while clearing the secret,
validates only the bounded outcome and failure stage, checks the durable
`4:4:2` ledger, prints no Session reference or raw provider material, and then
removes the database, grant, staged project, evidence, and secret file.

The server-local command also reports a transient closed adapter invocation
reason and error code. Reasons are limited to core/adapter lifecycle vocabulary;
adapter errors are limited to the four `real_post_*_refused`/`real_post_failed`
codes already emitted by the package. These diagnostics are not added to the
bounded provider outcome, result hash, audit row, database, package payload, or
browser response.

The 2026-08-24 provider-mode harness completed `indeterminate` at
`adapter_invocation_failed`; Stripe showed the restricted key as unused and no
POST in the Sandbox log. The same merged sources reproduced the failure in
network-disabled mode with transient diagnostics
`adapter-reason=adapter_error` and
`adapter-error=real_post_preflight_refused`. The remaining defect is therefore
inside the core-to-adapter real-POST preflight contract, before credentialed
transport or Stripe contact.

`RED_D4D_RECOVERY_NETWORK_MODE=offline` is the acceptance-only mode. It
disables URL streams and common cURL/socket functions for the apply runtime,
proves the complete durable recovery lifecycle with a synthetic restricted-key
shape, and must end `indeterminate` at `adapter_invocation_failed` without DNS,
TLS, HTTP, or Stripe contact. Provider mode does not retry and does not expire
the dashboard key; key expiration remains a separately confirmed owner action.

A future D4D recovery attempt requires a new explicit authorization, a new
least-privilege restricted Sandbox key, a fresh disposable database and
authorization/claim, review of the dry run, exactly one apply, review of the
bounded failure stage plus Stripe request log, and key expiration. It does not
authorize a payment, webhook, browser checkout, client installation, live
mode, or deployment.
