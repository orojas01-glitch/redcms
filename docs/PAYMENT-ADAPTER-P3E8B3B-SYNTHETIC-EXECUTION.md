# P3E-8B3B Synthetic Package Execution

Status: core can consume one exact authorization and claim through the real
adapter `0.1.3` synthetic-only typed operation. It does not construct the
adapter's provider-capable transport or contact Stripe.

## Exact profiles

The shared P3E-7/P3E-8A evidence validator now accepts only two closed
profiles:

- historical P3E-8B2: adapter `0.1.1` with runtime provider transport
  `disabled`; or
- P3E-8B3B: adapter `0.1.3` with runtime provider transport
  `synthetic_only`.

Package `0.1.2`, an `enabled` provider transport, changed fields, or any other
version/mode combination fails closed. Both profiles still require the same
current database-backed Owner, `addons.enable` capability, enabled
same-database Store Lite dependency, trusted package identity, active
at-most-15-minute window, and immutable authorization and claim rows.

## Durable start and no retry

The synthetic runner has its own start-state and outcome hashes. Before
registrar, secret, or handler access, it commits the existing nonce-derived
execution-start ledger identity plus one bounded audit fact. The start binds:

- package, version, actor, plan, authorization, claim, nonce, and value-free
  secret-availability hashes;
- operation `provider-contact.read-only-probe-synthetic`;
- `contactTarget=synthetic-package`;
- maximum attempts one; and
- retry authorization false.

Start-audit failure rolls back before execution. Once start commits, missing
secret material, registrar/handler refusal, malformed output, interruption,
result-ledger failure, or outcome-audit failure cannot authorize replay.

## Scoped real-package invocation

Core integrity-checks and executes the current package registrar only after
the durable start. Runtime secret access resolves exactly
`stripe.secret-key`; `stripe.webhook-secret` is intentionally absent. Core
then invokes the registered adapter through the contained typed boundary with
only the exact target, contact plan, plan hash, claim hash, and start hash.

Adapter `0.1.3` validates the complete `synthetic_only` plan and restricted-
test key shape, emits fixed in-memory 404 evidence, and projects it through
its bounded outcome gate. The returned result must contain the exact closed
shape with `networkAccess=false`, `providerContact=false`, no body, no headers,
no credential, no retry, and no mutation.

Core validates that shape again, locks and verifies the complete immutable
start row, then records one bounded result row and one
`provider_contact_synthetic_resource_miss` audit fact. Indeterminate failures
remain non-retryable.

## Acceptance

The disposable current-schema fixture passes 33 assertions after the
unchanged P3E-7, P3E-8A, and P3E-8B2 regressions. It proves:

- exact `0.1.3` / `synthetic_only` authorization and claim;
- pre-start refusal by the historical loopback runner;
- value-free dry run and zero package execution before apply;
- start-before-registrar ordering;
- runtime access scoped to exactly `stripe.secret-key`;
- one contained synthetic typed invocation and bounded resource miss;
- no credential disclosure;
- exact start/result ledger and audit hashes;
- replay refusal after success and every post-start failure;
- start-audit rollback before execution;
- outcome-audit rollback without restoring the attempt;
- authorization without claim refusal;
- absence of network/provider primitives; and
- exact fixture cleanup.

The legacy 33-assertion P3E-7, 34-assertion P3E-8A, and 32-assertion P3E-8B2
fixtures remain unchanged and pass beside the new profile.

The clean starter, 46 migrations, and 35-table schema remain unchanged. This
gate adds no migration, table, core endpoint, provider account, Store Lite
business mutation, Checkout creation, payment, webhook, browser flow, client
activation, or deployment.

## Next stop

Any P3E-8B3C real sandbox request requires separate approval. It must use a
new exact package/profile identity, preserve the durable start and permanent
no-retry rule, call at most one restricted-key read-only GET, discard body and
sensitive headers, and persist only the bounded outcome. Live credentials,
Checkout creation, payment capture, webhooks, Store Lite mutation, public
routes, browser checkout, and client deployment remain unauthorized.
