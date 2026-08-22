# P3E-9D2 Core Real-Operation Preflight Runner

Status: complete as a dependency-free, non-persistent core containment gate.
It recognizes exact external adapter `0.1.7`, invokes only the adapter's
non-executing real-POST preflight operation, and derives deterministic
start/result identities without starting or recording an execution.

## Inputs

The runner requires all of the following to agree exactly:

- discovery-valid, integrity-current adapter
  `redcms.store-lite-stripe-checkout` `0.1.7`;
- the existing adapter id and Store Lite `>=0.1.35 <1.0` dependency;
- exact P3E-9B synthetic source-plan evidence for adapter `0.1.5`;
- the closed mutation-aware Checkout input and canonical input SHA-256;
- the complete recomputed P3E-9D0 preflight, including exact provider form
  fields and request SHA-256; and
- the exact adapter operation
  `checkout.create-sandbox-real-post-preflight` and future provider operation
  `checkout.create-sandbox-real-post` as distinct identities.

The raw D0 form-field map remains inside core because provider-style bracketed
keys are outside the typed adapter payload vocabulary. Core removes only that
map from the adapter input. The adapter independently reconstructs it and
returns the reviewed fields as a bounded list of name/value pairs.

## Contained Invocation

Core integrity-checks and registers the package, selects the one declared
adapter handler, and invokes only the preflight operation through the existing
typed boundary. The invocation receives `null` secret access. No secret
reference or credential value is resolved, and no authorization header can be
constructed.

The result must exactly match every D0 request fact, adapter/source version,
operation identity, contract/request hash, and false-effect field. Output,
exceptions, malformed data, changed provider operation, extra fields, secret
material, or altered hashes produce no accepted partial outcome.

## Identity Boundary

Planning derives one deterministic start-identity SHA-256 from exact package,
integrity, input, request, operation, and one-attempt facts. A successful
contained preflight invocation derives a separate result-identity SHA-256 from
that start identity and the bounded adapter outcome.

These are identity contracts only:

- `executionStarted=false`;
- `resultRecorded=false`;
- `executionPerformed=false`;
- `executionReady=false`; and
- no authorization, claim, start, result, audit, migration, or database row is
  created or reused.

The earlier P3E-9C transport-double rows remain historical and cannot be
reinterpreted as real-operation execution evidence.

## Explicit Stop

P3E-9D2 adds no credential access, resolver, database, CLI command, request
global, route, browser bridge, DNS, TLS, HTTP, cURL, Stripe SDK, provider
contact/mutation, Checkout Session, payment, webhook, Store Lite mutation,
retry, live mode, demo change, client deployment, or P4 work. The actual
provider operation remains uninvoked and unsupported by this core gate.

[`scripts/addon-sandbox-checkout-real-operation-self-test.php`](../scripts/addon-sandbox-checkout-real-operation-self-test.php)
passes 39 focused assertions covering exact planning, canonical D0 adoption,
identity stability, changed-evidence refusal, typed invocation, malformed and
fault containment, no secret access, source exclusions, and exact temporary
project cleanup.

A separate local cross-repository proof copied the exact merged adapter
`0.1.7` package at `a441588193cc1e32f707dd03e7d5caa6f2c49e1a`, prepared its
real P3E-9A contract, and passed the D0 -> D2 plan/invocation/result path. The
temporary project and proof script were removed afterward.

P3E-9D3A now separately supplies the CLI-only, dry-run-first command around
these identities. P3E-9D3B remains the disposable cross-repository no-contact
rehearsal. P3E-9D4 remains the separately approved single real Stripe Sandbox
POST. See
[`PAYMENT-ADAPTER-P3E9D3A-OPERATOR-COMMAND.md`](PAYMENT-ADAPTER-P3E9D3A-OPERATOR-COMMAND.md).
