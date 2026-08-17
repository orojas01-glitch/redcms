# P3E-8B2 Provider-Contact Loopback Execution

Status: core can consume one exact P3E-8A claim through a sealed in-process
loopback rehearsal. It does not contact Stripe or any other provider.

## Boundary

P3E-8B2 repeats the current P3E-7 authorization and P3E-8A claim checks while
the original at-most-15-minute window is active. It requires:

- exact package `redcms.store-lite-stripe-checkout` version `0.1.1`;
- current trusted code and enabled same-database adapter and Store Lite state;
- a current database-backed Owner with `addons.enable`;
- the unchanged plan, authorization, claim, nonce, and secret-availability
  SHA-256 evidence;
- the exact immutable P3E-7 authorization and P3E-8A claim rows; and
- no prior execution-start row for the claim.

The only allowed operation is
`provider-contact.read-only-probe-loopback`, with `contactTarget=loopback`.
The handler is an in-memory acceptance double. Core exposes no HTTP, DNS,
socket, cURL, TLS, Stripe SDK, provider hostname, browser, public route, or
client-deployment primitive in this gate.

## Start-before-execution rule

Core holds the shared lifecycle and package locks and revalidates current
authority, package, dependency, claim, and value-free secret availability in
one InnoDB transaction. Before registrar execution or secret resolution, it
commits:

- one nonce-derived `provider-contact-attempt-start` row in the existing
  immutable administrator-action ledger; and
- one bounded `provider_contact_execution_started` audit fact.

Audit or commit failure rolls back the start and executes nothing. Once the
start commits, the claim is permanently consumed. Registrar failure, missing
secret material, malformed adapter output, process interruption, or outcome
audit failure never authorizes a retry.

## Scoped secret and adapter call

After the durable start, core integrity-checks the already trusted package
registrar. Runtime secret access is restricted to exactly
`stripe.secret-key`; `stripe.webhook-secret` and every unrelated secret are
unavailable to the request. The secret value and its hash are excluded from
plans, ledgers, audits, adapter results, and acceptance output.

Core invokes the typed registered-adapter boundary directly. It contains PHP
output, exceptions, buffer-stack changes, malformed results, and resolved
secret disclosure. The input contains only the closed loopback target and
exact plan/claim/start hashes plus the previously reviewed contact plan.

## Bounded outcome

A conforming loopback response is reduced to a closed result containing a
status classification, numeric status code, response byte count, one transport
evidence SHA-256, and fixed false flags for response body, response headers,
credential disclosure, retry, mutation, network access, and provider contact.
Failures become `indeterminate` without reopening the attempt.

Core locks and verifies the complete immutable start row before recording one
nonce-derived `provider-contact-attempt-result` row and one bounded outcome
audit fact. An outcome-audit failure rolls back the result row, but the durable
start remains and prevents replay.

## Acceptance

The disposable current-schema fixture passes 32 assertions. It proves:

- discovery performs no registrar execution;
- the exact authorization and claim are required and revalidated;
- dry run remains value-free and non-executing;
- one loopback 404 is classified as `resource_miss_observed`;
- only `stripe.secret-key` is readable by the handler;
- start/result rows contain the exact bounded hashes and actor;
- replay is refused after success and after a post-start failure;
- missing secret material after start produces an indeterminate, non-retryable
  result;
- start-audit failure rolls back before execution;
- outcome-audit failure cannot restore or repeat the attempt;
- authorization without a claim is refused;
- forbidden network/provider primitives are absent; and
- all fixture files, rows, databases, and grants are removed exactly.

The clean starter, 46 migrations, and 35-table schema remain unchanged. This
gate adds no migration, table, package, seed, provider account, endpoint,
Store Lite business mutation, payment, webhook, browser checkout, client
activation, or deployment.

## Next stop

Any P3E-8B3 provider-target transport must be separately approved. It must
reuse the durable start/no-retry rule, restrict credentials to a reviewed test
key, make at most one exact read-only sandbox request, discard body and
sensitive headers, and persist only the bounded outcome. Live credentials,
Checkout creation, payment capture, webhooks, Store Lite mutation, public
routes, browser checkout, and client deployment remain unauthorized.
