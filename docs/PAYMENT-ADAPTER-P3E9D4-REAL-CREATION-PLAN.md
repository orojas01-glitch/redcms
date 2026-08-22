# P3E-9D4 Real Sandbox Checkout Creation Plan

Status: planning complete; D4A and D4B are implemented without provider
execution, and D4C is next. D4 is split into four review stops so the first
real Stripe `POST` remains the final operational step.

## Why D4 Is Not One Coding Step

Before D4A, merged adapter `0.1.7` exposed only
`checkout.create-sandbox-real-post-preflight`. D4A then added the exact
provider operation in separately distributed `0.1.8`, and D4B added the
durable core runner. No CLI, public, or browser caller exists yet, and the
production transport remains uninvoked.

The completed mutation authorization, claim, and transport-double start/result
evidence is bound to earlier adapter, operation, and false-effect identities.
It cannot be widened or reinterpreted for a later write-capable adapter. A real
provider mutation therefore needs a new package identity, fresh authority,
fresh one-attempt claim, durable start before contact, bounded result after
contact, and a new operator command.

## Fixed Outcome

The entire D4 track may prove only one result:

- one exact `POST https://api.stripe.com/v1/checkout/sessions`;
- one synthetic USD order with no real customer or contact data;
- one open, unpaid, non-live `mode=payment` Checkout Session;
- the exact amount, currency, order reference, expiry, and idempotency relation;
- no payment, webhook, browser navigation, Store Lite transition, retry,
  hosted-demo change, client deployment, or live-mode action; and
- bounded non-secret evidence that discards the Checkout URL, response body,
  response headers, request id, provider object, key, and customer fields.

The Session uses the minimum supported 30-minute expiry and no recovery flow.
Creating the Session does not authorize opening its URL or manually expiring
it. Stripe Session expiration is a second provider-side POST and remains a
separate approval.

## D4A — External Provider-Write Operation

Status: complete in separately distributed adapter `0.1.8`, merged as
`562b8a9`; no Stripe contact occurred.

The separately distributed adapter advances from `0.1.7` to one new reviewed
version and adds only the exact operation
`checkout.create-sandbox-real-post`.

The operation must reuse the existing package-owned request planner, canonical
form codec, creation contract, response decoder/gate, and one-use execution
discipline. The new provider transport must fix the host and path, require
HTTPS with peer/host verification, disable proxy and redirects, cap connection,
total, header, and body sizes, consume the restricted test key once, and erase
all in-memory key/header/body material after projection.

D4A acceptance uses source contracts, sealed doubles, and the existing local
TLS loopback path only. It cannot access the Stripe account, receive a real
key, contact `api.stripe.com`, or create a Session. The installable package
remains outside the clean core starter and has no core caller at this stop.

## D4B — Core Durable Mutation Runner

Status: complete. D4B1 supplies fresh authorization/claim and D4B2 supplies
durable start/result plus in-memory invocation. No CLI, public caller, or real
provider request exists at this stop.

Core adds a D4-specific runner bound to the exact new adapter version, Store
Lite `0.1.35`, D0 request, D2 identities, order snapshot, database, actor,
permission, secret-availability evidence, and provider operation.

It must create fresh D4 authorization and claim evidence; the earlier C1/C2
rows are ineligible. Apply must:

1. recheck Owner and package state under locks;
2. commit immutable execution-start plus value-free audit before registrar,
   secret, handler, or network access;
3. resolve only `stripe.secret-key` for the owning package;
4. invoke the exact typed provider operation at most once;
5. conservatively treat any post-start fault or ambiguous transport as an
   indeterminate consumed attempt with no retry; and
6. commit only the closed bounded result and value-free audit after rechecking
   the complete start row.

D4B acceptance uses an integrity-current temporary package with a final
in-memory handler. It proves durable start/result, replay refusal, rollback,
secret isolation, malformed/fault containment, and permanent no-retry without
DNS, TLS, HTTP, Stripe contact, or Checkout creation. No public or browser
caller is added.

## D4C — Operator Command And No-Contact Rehearsal

Status: in progress. D4C1 CLI-only command contract passes 74 assertions;
D4C2 network-disabled no-contact rehearsal remains next. No real apply ran.

The CLI-only command defaults to dry run and requires exact database, actor,
package/version/state, backup, plan/request/order, authorization/claim/start,
secret-availability, operation/target, one-attempt, and no-payment/no-webhook/
no-browser/no-order-mutation/no-retry confirmations.

The cross-repository rehearsal stages exact merged core, Store Lite, and
adapter commits in a fresh current-schema disposable database. It proves dry
run, incomplete and changed-confirmation refusal, source/configuration/key
scans, and exact database/grant/project cleanup with provider networking
disabled. It does not run the command's real `--apply`; the first production-
transport apply is reserved for D4D.

## D4D — One Real Sandbox POST

D4D is an operational rehearsal, not an automated test or ordinary code PR.
It may start only after D4A–D4C are merged and reverified.

Separate explicit user approvals are required for:

1. opening the dedicated blank Stripe Sandbox and creating a new restricted
   sandbox key with Checkout Sessions `Write` and unrelated resources `None`;
2. storing the one-time-visible key only in the approved local secret provider
   and recording only its opaque reference in the disposable RED-CMS database;
3. running the final reviewed dry run and reviewing every printed identity;
4. invoking `--apply`, which commits start and issues the one POST; and
5. expiring the restricted key after bounded evidence review.

The apply authorization does not include payment, opening the Checkout URL,
manual Session expiration, webhook forwarding, provider retrieval, Store Lite
mutation, hosted-demo activation, another client, live mode, or deployment.

The operator must confirm exactly one request in Stripe's Sandbox request log,
retain only bounded non-secret result hashes/facts, and clean the disposable
database, grant, staged project, process, local key reference, and evidence
files. The configured primary and every hosted/client installation must remain
unchanged.

## Failure And Cleanup Rules

- Any timeout, connection ambiguity, malformed response, provider error,
  identity mismatch, live-mode result, or result-storage failure consumes the
  attempt permanently.
- No automatic retry or alternate idempotency key is allowed.
- The Checkout URL, bodies, headers, request id, key, and provider object must
  never enter repositories, logs, audits, evidence packets, or chat.
- Key expiration and manual Session expiration are separate effects.
- Sandbox deletion remains a separate destructive decision.

## Provider Facts Rechecked 2026-08-22

Official Stripe documentation currently states:

- restricted keys are created with resource-specific `None`, `Read`, or
  `Write` permissions and should follow least privilege;
- Checkout Session creation is `POST /v1/checkout/sessions`;
- `expires_at` may be 30 minutes through 24 hours after creation;
- POST idempotency retains the first endpoint result and compares parameters
  when the same key is reused; and
- expiring an open Checkout Session is a separate
  `POST /v1/checkout/sessions/:id/expire`.

These facts and Dashboard labels must be rechecked immediately before D4A and
again immediately before D4D.

Sources:

- [Stripe API keys](https://docs.stripe.com/keys)
- [Stripe key-management best practices](https://docs.stripe.com/keys-best-practices)
- [Create a Checkout Session](https://docs.stripe.com/api/checkout/sessions/create)
- [Stripe API v1 idempotent requests](https://docs.stripe.com/api/idempotent_requests)
- [Expire a Checkout Session](https://docs.stripe.com/api/checkout/sessions/expire)

## Planning-Slice Acceptance

This planning slice was complete when README, roadmap, security, acceptance,
Version 5.1 direction, the P3 proposal, the P3E-9 frontier, and the canonical
status graphic all showed D4A next, D4D as the first provider effect, and P4
gated; `git diff --check` and a changed-file credential scan passed; and no PHP,
migration, manifest, package, database, key, account, network, route, runtime,
hosted-demo, client, or provider state changes.
