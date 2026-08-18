# P3E-9 Stripe Sandbox Checkout-Creation Frontier

Status: active staged boundary. P3E-8B3C3B proved one exact read-only Stripe
Sandbox request, and P3E-9A completed the pure external source contract.
P3E-9B synthetic-only package/core integration is next. This document does not
authorize a Stripe key, create a Checkout Session, make a payment, expose a
browser route, change `demo.red-sphere.com`, or enter P4 deployment review.

## Why A New Gate Is Required

The completed P3E-7 through P3E-8 evidence is deliberately bound to one
read-only resource-miss probe with `mutation=false`, `checkoutCreation=false`,
`payment=false`, `retry=false`, and `clientDeployment=false`. That evidence
must never be reinterpreted as authority for a `POST` request.

Checkout Session creation is the first provider-side mutation in this track.
It therefore requires a new operation identity, new non-secret plan evidence,
new one-attempt authorization and claim rows, a new immutable execution-start
identity, and a separately provisioned least-privilege sandbox credential.
None of the completed read-only authorization, claim, start, result, or audit
rows may be reused.

## Objective

Prove that one immutable synthetic USD Store Lite order can be projected into
one exact Stripe Sandbox Checkout Session without creating a payment or
changing any Store Lite order, payment, fulfillment, browser, hosted-demo, or
client state.

The first real write-capable proof is intentionally smaller than the complete
P3E outcome in the original sandbox proposal. It proves only creation of an
open, unpaid, non-live Checkout Session. Browser navigation, test-card entry,
signed webhook delivery, provider lookup, Store Lite payment transition,
refund, reversal, and P4 deployment remain later separately approved gates.

## Fixed Boundary

The eventual real sandbox rehearsal may use only:

- the dedicated blank `RED-CMS Store Lite Development` Stripe Sandbox;
- a newly created restricted sandbox key with Checkout Sessions `Write` and
  every unrelated resource set to `None`;
- one fresh disposable RED-CMS database;
- current clean core, separately distributed Store Lite, and the separately
  distributed Stripe Checkout adapter at exact reviewed commits and versions;
- one synthetic USD order with no real customer identity or contact data;
- the existing closed, server-derived P3E-1 request vocabulary;
- one `POST https://api.stripe.com/v1/checkout/sessions` attempt;
- one server-derived idempotency key bound to the exact order snapshot and
  attempt identity;
- an explicit minimum supported Session expiry, with recovery disabled; and
- bounded non-secret result evidence only.

The rehearsal must not use a broad sandbox secret key unless a later explicit
exception proves that the reviewed restricted key cannot perform the exact
operation. It must never use a live key, live mode, another Stripe project,
another client database, or a retained RED-CMS database.

## Request Contract

P3E-9 must reuse the already-reviewed P3E-1 planner and P3E-3 canonical form
codec instead of assembling new provider parameters inside core. Before any
transport can run, the plan must bind:

- package, version, registrar, operation, database, and client subject;
- the immutable Store Lite order id and complete snapshot SHA-256;
- `stripe_checkout`, lowercase `usd`, and the exact positive integer total;
- bounded existing order-line display facts only;
- one approved HTTPS success URL and one approved HTTPS cancel URL whose
  browser return has no transition authority;
- `mode=payment`, non-live operation, no recovery flow, and a Checkout Session
  expiry from 30 minutes through 24 hours after creation;
- one exact reviewed Stripe API version;
- the exact `api.stripe.com` host and `/v1/checkout/sessions` path;
- one canonical body SHA-256 and one internal idempotency relation; and
- one attempt, no automatic retry, no payment, no webhook, no Store Lite
  mutation, and no client deployment.

No email, name, address, phone number, raw customer field, card detail,
browser token, provider credential, Checkout URL, or unrelated metadata may
enter the plan, durable evidence, log, audit fact, or repository.

## Result Contract

Success is limited to a bounded projection proving all of the following:

- HTTP success from the exact reviewed provider endpoint;
- a Checkout Session reference with the sandbox `cs_test_` shape;
- `livemode=false`, `mode=payment`, `status=open`, and payment not completed;
- exact USD amount and exact internal order relation;
- the reviewed expiry and no recovery flow;
- a validated Stripe-hosted HTTPS URL that remains transient and undisclosed;
- the exact request-plan and snapshot hashes; and
- `payment=false`, `orderMutation=false`, `browserNavigation=false`,
  `webhook=false`, `retryAuthorized=false`, and `clientDeployment=false`.

The Checkout URL, response body, response headers, request identifier,
credential, customer/payment fields, provider error, and complete provider
object must be discarded. Only opaque bounded hashes and closed status facts
may reach the private evidence packet.

Any timeout, transport ambiguity, malformed response, provider `4xx` or `5xx`,
identity mismatch, wrong amount/currency, live-mode result, or outcome-storage
failure permanently consumes that rehearsal attempt. The first gate must not
retry automatically or with a different idempotency key. A later
reconciliation or exact same-key retry design requires its own review before
implementation, even though Stripe supports idempotent POST replay.

## Provider-Side Cleanup Reality

Stripe exposes no Checkout Session delete endpoint. The creation request must
set the shortest reviewed automatic expiry, currently 30 minutes, and must
disable recovery. The URL is never opened or disclosed, so no visitor can
complete the Session through RED-CMS.

Manually expiring an open Session uses a second provider-side `POST` and is not
authorized by the first one-POST rehearsal. It may be proposed as a separate
cleanup action after the creation evidence is reviewed. Once separately
approved, expiring the restricted key after local evidence capture revokes
further API access but does not remove the Sandbox Session history. Deleting
the entire Sandbox remains a separate explicit destructive decision.

## Implementation Stops

P3E-9 is divided so that every risk increase has a separate review:

1. **P3E-9A — complete.** The external source adds only pure request,
   response, expiry, and operation-profile validation around the existing
   P3E-1/P3E-3 contracts. Adapter `0.1.4` and its installable package remain
   unchanged; no database, credential, package runtime, network, Stripe
   account, or client state was used.
2. **P3E-9B — next.** Register and invoke one
   checkout-creation-shaped operation against an integrity-checked in-memory
   handler. Prove new evidence cannot reuse the read-only P3E-8 profile. No
   DNS, TLS, HTTP, Stripe contact, Checkout Session, or business mutation.
3. **P3E-9C — disposable authorization and operator rehearsal.** Add a new
   one-attempt mutation-specific authorization, claim, start, result, and
   dry-run-first operator command. Acceptance substitutes a transport double
   and finishes with exact database/grant/project cleanup. No real key or
   provider request.
4. **P3E-9D — one real Sandbox creation.** Only after P3E-9A through P3E-9C
   merge and pass independently may an operator request separate approval to
   create the restricted key and issue one exact Sandbox POST. Key expiration,
   Session expiration, test payment, webhook proof, and Sandbox deletion remain
   distinct explicit actions.

P4 remains blocked until later P3E gates prove the hosted browser return,
signed event verification, server-side Session reconciliation, idempotent
commercial-attempt behavior, Store Lite transition, disable/re-enable,
rotation, rollback, and cleanup requirements from the approved P3 proposal.

## Acceptance For This Planning Slice

This documentation-only slice is complete when:

- the canonical status graphic shows P3C and P3D complete, the first P3E
  contact and P3E-9A complete, P3E-9B next, and P4 gated;
- README, roadmap, security, acceptance, Version 5.1 direction, and the P3
  proposal agree on the frontier and exclusions;
- no PHP, migration, manifest, package, database, credential, route, runtime,
  demo, client, or provider state changes;
- `git diff --check` passes; and
- a credential-pattern scan of the changed files is clean.

## Official Provider Facts Rechecked

Stripe documentation was rechecked on 2026-08-18. The design relies on these
current provider facts:

- restricted keys can be configured per resource as `None`, `Read`, or
  `Write` and should follow least privilege;
- Checkout Session creation is `POST /v1/checkout/sessions`;
- `expires_at` may be 30 minutes through 24 hours after creation;
- an open Session may be expired through a separate
  `POST /v1/checkout/sessions/:id/expire`; and
- Stripe API v1 POST idempotency retains the first endpoint result for an
  idempotency key and rejects reuse with changed parameters.

Provider behavior, permissions, API version, and Dashboard labels must be
rechecked immediately before P3E-9D.

Sources:

- [Stripe API keys](https://docs.stripe.com/keys)
- [Stripe key-management best practices](https://docs.stripe.com/keys-best-practices)
- [Create a Checkout Session](https://docs.stripe.com/api/checkout/sessions/create)
- [Expire a Checkout Session](https://docs.stripe.com/api/checkout/sessions/expire)
- [Stripe API v1 idempotent requests](https://docs.stripe.com/api/idempotent_requests)
