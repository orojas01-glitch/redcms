# P3E-8B3C2 Provider-Operation Runner

Status: core can consume one exact authorization and claim through adapter
`0.1.4/provider_read_only`, but acceptance substitutes an integrity-checked
in-memory handler. No Stripe request is made by this gate.

## Exact profile

The shared P3E-7/P3E-8A evidence validator now accepts only three closed
profiles:

- historical P3E-8B2: adapter `0.1.1` with runtime provider transport
  `disabled`;
- P3E-8B3B: adapter `0.1.3` with runtime provider transport
  `synthetic_only`; or
- P3E-8B3C2: adapter `0.1.4` with runtime provider transport
  `provider_read_only`.

Changed versions, modes, plan fields, hashes, package identity, Store Lite
dependency state, Owner authority, capability, authorization, claim, expiry,
or value-free secret availability fail closed. The historical loopback and
synthetic runners independently refuse the new provider-read-only profile.

## Durable one-attempt runner

The provider runner has distinct start and outcome hashes. Before registrar,
secret, or handler access, it commits the existing nonce-derived execution
start identity and one bounded audit fact. The start binds:

- package, version, actor, plan, authorization, claim, nonce, and value-free
  secret-availability hashes;
- operation `provider-contact.read-only-probe-sandbox`;
- `contactTarget=stripe-sandbox`;
- maximum attempts one; and
- retry authorization false.

Start-audit failure rolls back before execution. Once the start commits,
missing secret material, registrar or handler refusal, malformed output,
interruption, result-ledger failure, or outcome-audit failure cannot authorize
replay.

## Scoped invocation and bounded evidence

Core integrity-checks the current package registrar, resolves exactly
`stripe.secret-key`, leaves `stripe.webhook-secret` unavailable, and invokes
only the exact sandbox probe operation. Its input contains only the exact
target, complete contact plan, plan hash, claim hash, and start hash.

Core accepts only the closed adapter result: classified status, numeric HTTP
status, bounded byte count, transport-evidence SHA-256, and explicit false
body, header, credential, retry, and mutation flags. A valid provider result
must report network access, provider contact, and execution true. Once the
trusted handler has been invoked, missing or malformed output is recorded
conservatively as possible network/provider contact with an indeterminate
outcome; it never restores the attempt.

Only the bounded result hash and audit classification are persisted. Response
bodies, response headers, credentials, credential hashes, provider messages,
and exception detail are never stored or returned.

## In-memory-only acceptance

The 37-assertion disposable current-schema fixture installs an isolated
`0.1.4` package
whose integrity-checked registrar provides an in-memory handler for the exact
operation. That handler receives the scoped synthetic restricted-key value and
returns bounded 404 resource-miss evidence without DNS, TLS, HTTP, cURL, or
Stripe contact.

Acceptance proves:

- the exact three-profile shared allow-list and mismatch refusal;
- current Owner, capability, package, Store Lite, authorization, claim, expiry,
  and secret-declaration revalidation;
- no registrar, secret-value, or handler access during planning;
- pre-start refusal by the historical loopback and synthetic runners;
- start-before-registrar ordering and one scoped secret resolution;
- exact typed input and bounded successful outcome;
- conservative indeterminate evidence after malformed post-invocation output;
- permanent replay refusal after success and every post-start failure;
- transactional start-audit and outcome-audit behavior;
- absence of network/provider primitives from the core runner; and
- exact fixture cleanup.

The clean starter, schema, migration count, Store Lite business rows, public
mutation ledger, payment state, browser experience, client installations, and
deployment state remain unchanged.

## Next stop

P3E-8B3C3A now adds the explicit server-local, dry-run-first operator command.
The first real request remains B3C3B and may use the adapter `0.1.4` handler
only with one restricted-test key. It must retain the exact Owner
authorization, claim, durable start, scoped-secret, read-only GET, bounded
evidence, and permanent no-retry rules.

Live keys, Checkout creation, payment capture, refunds, webhooks, Store Lite
mutation, public routes, browser checkout, scheduled work, automatic retry,
client activation, and deployment remain unauthorized.
