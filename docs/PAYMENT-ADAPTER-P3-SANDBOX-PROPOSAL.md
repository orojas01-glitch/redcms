# Store Lite Payment Adapter P3 Sandbox Proposal

Status: P3 planning is approved. P3A through P3D are complete, and
P3E-8B3C3B closed the first real provider-contact rehearsal through one
read-only Stripe Sandbox GET. P3E-9A then completed as a pure external
non-executing Checkout-creation contract. P3E-9B synthetic-only package/core
integration is now complete. P3E-9C1 write-specific authorization and P3E-9C2
one-attempt claim recording are complete. P3E-9C3A transport-double
start/result, P3E-9C3B1 CLI command, and P3E-9C3B2 disposable apply rehearsal
are complete. P3E-9D0 pure real-POST preflight, external adapter P3E-9D1
through corrected `0.1.7`, and core P3E-9D2 response containment/identity are
complete. P3E-9D3A CLI-only dry-run-first command contract is complete;
P3E-9D3B now completes the disposable no-contact rehearsal with one contained
apply and zero provider effects. P3E-9D3 is complete. D4A external adapter
`0.1.8` is also complete without provider contact. D4B durable core execution
is complete through sealed in-memory acceptance. D4C operator/no-contact
rehearsal without real apply is next, and D4D remains one separately
authorized POST. Payment, webhook, and P4
deployment remain gated.
Stripe account access, sandbox creation, credential provisioning, outbound
network access, webhook forwarding, simulated payment, and deployment are not
authorized by this document. P0 through P2 remain complete.

P3A-1 implements the data-only manifest profile and its `server-signature`
route vocabulary. P3A-2 composes that profile with read-only evidence for
persisted Owner enable authority, the exact enabled Store Lite dependency,
the immutable adapter migration ledger, and migration-touched InnoDB tables in
one client database. P3A-3 refreshes that evidence, loads only the fixed
integrity-checked entry point, and requires exactly one declared adapter and
one declared server-event route registration. Its request-local registry is
reduced to counts and hashes and discarded; neither registered handler is
invoked or published. P3A-4 adds a closed, unlinked core ingress contract that
preserves explicit bounded raw transport material for a future adapter
verifier without reading a live request or parsing it. P3A-5 adds the separate
CLI-only atomic lifecycle runner: it proves exact stored configuration and
opaque secret-reference availability, recomputes the complete P3A plan under
locks, and commits only the reviewed state transition and bounded audit fact.
None of these slices exposes the route, invokes a handler, resolves secret
bytes, or contacts a provider. Store Lite 0.1.32 through 0.1.35 then complete
the provider-neutral P3B transition, history, transactional-service, and
lifecycle-rehearsal layers without adding an adapter or provider access.

## Outcome

P3 should prove one separately distributed Stripe Checkout adapter against one
new Stripe Sandbox and one fresh local client database. The proof must create no
live payment, use no live key, change no hosted installation, and retain no
credential, customer data, package, database, grant, or provider object outside
its explicitly reviewed evidence and cleanup boundary.

The target is a sandbox-quality integration proof, not production deployment.
P4 remains the only gate that can propose a real client, public webhook,
production secret, or live transaction.

## Current Prerequisite Audit

RED-CMS and Store Lite already provide useful foundations:

- opaque `config:` secret-reference settings and core-internal by-reference
  secret resolution;
- first-party manifest validation for `adapter` packages and declarative
  `outboundHosts`;
- owner-authorized install, enable, disable, re-enable, upgrade, and recovery;
- raw-body-capable supported-server ingress and replay-safe public mutation
  storage; and
- immutable Store Lite order snapshots whose hosted `stripe_checkout` orders
  begin with `PaymentStatus=pending`.

Those foundations do not yet authorize P3. Generic enablement profiles
deliberately refuse adapters, secret-bearing operational packages, outbound
hosts, and webhook-shaped server events. The completed narrow P3A runner can
enable only the exact reviewed adapter profile after full local evidence, but
it does not expose the declared route or provide a general outbound HTTP
executor. The separately distributed Store Lite 0.1.35 package now owns the
typed `commerce.orders` payment-event service, paid/refund/reversal transition
writer, matching append-only history vocabulary, and lifecycle rehearsal.
It accepts only an already-verified provider-neutral event and never receives
provider transport or credential material.
The existing browser public-mutation route is not a webhook endpoint: its
Origin, anonymous-subject, CSRF, and browser idempotency contract must not be
weakened or reused for Stripe.

P3 must therefore close the following sub-gates in order.

## P3A — Core Payment-Adapter Profile

Add a narrow, non-network core profile that can recognize one trusted
first-party payment adapter without enabling it automatically. It must require:

- package `type: adapter` and exactly one declared payment adapter;
- one exact enabled Store Lite dependency in the same client database;
- no components, editor forms, administrator tools/actions, assets, jobs, or
  browser public-mutation contracts;
- only reviewed ordinary settings plus required secret-reference settings with
  no secret default;
- only package-owned InnoDB tables and immutable migrations;
- one exact outbound host, `api.stripe.com`; and
- one separately declared server-event route that cannot be selected by the
  browser public-mutation dispatcher.

Preflight must remain non-executing. Enablement must stay CLI-only,
Owner-authorized, dry-run first, backup-bound, database-bound, and registration
only. It must not resolve a secret, invoke the adapter, open a network
connection, or expose a webhook during install or enable.

The five implementation slices together complete only the closed P3A gate.
`includes/addon_payment_adapter_preflight_helpers.php` validates the
manifest surface. The separate database preflight consumes the established
generic enablement plan, requires the target to be current and
`installed_disabled`, requires `redcms.store-lite` to be current and enabled
in the same selected database, verifies exact migration-ledger evidence,
derives only bounded package-table names from the guarded immutable migration
files, and requires every exact table to exist as InnoDB. It returns only
counts and hashes. The registration-only validator reruns that database
preflight immediately before loading the fixed registrar, requires the exact
manifest registration shape, and returns no callback or registry. The
separate `includes/addon_payment_adapter_server_event_ingress_helpers.php`
binds that evidence to one exact static `POST`, an explicit complete canonical
capture of `Content-Type`, `Content-Length`, and `Stripe-Signature`, an exact
1-to-65,536-byte raw body, and an explicit receipt time. Verification material
is transient and excluded from JSON, debug output, object casts, cloning, and
serialization. The helper does not read PHP request globals, parse JSON,
resolve a secret, verify a provider signature, invoke a callback, access a
database, emit a response, publish runtime, or contact Stripe. The
`server-signature` route remains non-routable and cannot be selected by either
current public dispatcher. The separate
`includes/addon_payment_adapter_enable_helpers.php` and
`scripts/admin-payment-adapter-enable.php` compose fresh database, registrar,
ingress, stored-setting, and opaque-secret-availability evidence. Apply repeats
the exact plan under lifecycle and package locks and atomically commits only
`enabled` plus the value-free `payment_adapter_enabled` audit fact. It is
Owner-authorized, dry-run first, exact-confirmation and backup bound, refuses
stale plans, and executes no registered handler, secret-value resolution,
route publication, response, or network request. This completes P3A without
installing an adapter or changing a client.

## P3B — Store Lite Payment-Event Service

Status: Complete in the separately distributed Store Lite package. Version
0.1.32 adds the pure transition decision, 0.1.33 adds one append-only history
migration, 0.1.34 adds the typed transactional writer/service, and 0.1.35 adds
the disposable lifecycle rehearsal. The proof covers install-disabled,
upgrade, enable, apply, replay, refusal, disable/re-enable, rollback, two-client
isolation, and exact database/grant/project/process cleanup. It creates no
adapter, credential, webhook, provider object, request, payment, or client
deployment.

Upgrade the separately distributed Store Lite package through append-only
migrations and a typed internal service. Store Lite—not the adapter—must own
the order transition transaction.

The service accepts only the P0 normalized event vocabulary and an exact
current immutable order snapshot. It must lock and recheck order identity,
payment method, amount, currency, current payment/order/fulfillment state, and
event replay evidence before applying one permitted transition. It must append
bounded history in the same transaction. It must never receive a Stripe key,
signature, raw payload, Checkout URL, customer payment method, or provider
error.

The migration must expand the current `order.created`-only history constraint
without editing any applied migration. `paid`, `refund_confirmed`, and
`reversal_reported` must remain distinct. A reversal blocks automatic
fulfillment; it does not invent a successful refund or silently cancel an
order.

## P3C — Separately Distributed Stripe Adapter

Create the adapter outside the clean RED-CMS starter and outside the Store Lite
base package. Its fixed package identity is
`redcms.store-lite-stripe-checkout`; its fixed repository name is
`redcms-store-lite-stripe-checkout`. The repository does not exist merely
because this document fixes those names: repository creation and visibility
remain a separately approved external action.

### P3C-1 — Dependency-Free Package Foundation

The first P3C slice may create only the external package skeleton, its exact
data-only manifest identity, and dependency-free pure contracts that normalize
a reviewed checkout response and an already-signature-verified provider event
into the closed P0/P2 vocabulary. Fixtures must reject extra, malformed,
oversized, mismatched, secret-bearing, or browser-derived input without loading
core or Store Lite runtime code.

P3C-1 adds no migration, package registrar, route, webhook verifier, secret
reference, HTTP client, Stripe SDK, outbound connection, database writer,
Store Lite service invocation, browser return handler, client installation, or
payment. Those capabilities require later separately reviewed P3C slices.

The adapter owns only provider-specific state, including:

- an opaque checkout-attempt reference and internal idempotency relation;
- an opaque provider event reference and replay result;
- bounded provider outcome, amount, currency, timestamps, and evidence hashes;
  and
- value-free lifecycle and processing audit facts.

It must never persist a raw body, signature, API/webhook secret, Checkout URL,
card or wallet detail, provider token, unredacted provider error, or browser
query. Its migration and tables belong only to the adapter and one client
database.

The outbound client must permit only HTTPS to `api.stripe.com`, refuse
redirects and alternate hosts, use a pinned reviewed Stripe API version, send
one server-derived idempotency key on Checkout Session creation, enforce
bounded request/response sizes and timeouts, and return only the closed P0/P2
facts. Whether to use Stripe's official PHP library or a smaller reviewed
first-party HTTP client is a separate dependency and hosting review inside
P3C; no package may be downloaded merely by approving this proposal.

The webhook verifier must receive the exact raw bytes and complete
`Stripe-Signature` value from the new server-event ingress. It verifies the
endpoint-specific secret and bounded timestamp before JSON parsing, event
lookup, database access, or package service invocation. Valid events are not
assumed to arrive once or in order. Any event capable of proposing payment or
fulfillment change must be reconciled with a server-side Checkout Session
retrieval and exact sandbox/client/order/amount/currency evidence.

## P3D — Offline Lifecycle And Failure Rehearsal

Before contacting Stripe, stage clean current core, the reviewed Store Lite
upgrade, and the adapter in a temporary project with one fresh database.
Dependency-free and disposable-database fixtures must prove:

- install-disabled and enable preflight with no secret access or network;
- exact secret-reference declaration/availability with no value disclosure;
- adapter registration, invocation ownership, and Store Lite service binding;
- checkout idempotency, timeout, retry, malformed/oversized response, redirect,
  wrong-host, wrong-client, amount/currency/order mismatch, and replay refusal;
- raw-body verification ordering and malformed/duplicate signature refusal;
- paid, failed, expired, refund, and reversal state-machine behavior;
- duplicate and out-of-order event handling with one transition at most;
- disable stops checkout and event processing while retaining evidence;
- re-enable reproduces exact registration without changing credentials or
  business rows; and
- rollback plus exact database, grant, package, file, process, and log cleanup.

No real key or provider endpoint is allowed in P3D.

## P3E — Stripe Sandbox Proof

P3E begins only after P3A through P3D are merged and independently verified.
Use a newly created Stripe Sandbox rather than shared test mode because Stripe
currently recommends Sandboxes for isolated settings and access. Create the
sandbox from scratch; do not copy a live business configuration.

Use a sandbox-only restricted key when its permissions can create and retrieve
the required Checkout Session objects. If a restricted key cannot support the
reviewed flow, a time-bounded sandbox secret key requires a separate explicit
exception. Use a distinct endpoint-specific sandbox webhook secret. The key
and secret must be provisioned only as server-local values behind two opaque
`config:` references; they must never be pasted into chat, committed, written
to a migration/manifest/database, rendered, logged, or placed in browser code.

The preferred local webhook proof uses Stripe CLI forwarding to a local
supported-server endpoint, so P3 needs no public DNS or production ingress.
CLI authentication, installation, and sandbox access are separate user-
approved actions. A Dashboard-created public endpoint is out of scope.

Use only synthetic customer/order data and Stripe test payment methods. The
proof must demonstrate:

1. one exact USD Store Lite order creates one hosted `mode=payment` Checkout
   Session with the server-derived amount and internal idempotency relation;
2. retry returns the same commercial attempt and never creates another RED-CMS
   order;
3. browser success/cancel returns display status only;
4. a valid signed sandbox event plus server-side Session retrieval proposes and
   applies exactly one eligible Store Lite transition;
5. wrong signature, stale timestamp, wrong sandbox/client/order, amount or
   currency mismatch, duplicate event, reordered event, and unsupported event
   fail closed;
6. refund and reversal simulations retain separate bounded evidence and obey
   the Store Lite state machine;
7. disabling the adapter stops new sessions and event processing without
   deleting existing evidence; and
8. key rotation/refusal and webhook-secret rotation are rehearsed without
   disclosing either value.

### Current P3E Frontier

P3E-8B3C3B completed only the first read-only provider-contact prerequisite.
Its mutation-disabled authorization, claim, start, result, and audit evidence
cannot authorize Checkout creation. P3E-9A has now adopted only pure
non-executing request, response, expiry, and operation-profile contracts around
the already-reviewed P3E-1/P3E-3 source. It accesses no database, resolves no
credential, invokes no package, opens no connection, creates no Checkout
Session, and changes no hosted or client state. P3E-9B then completed
synthetic-only package/core integration with cross-profile refusal. P3E-9C1
then records one new nonce-bound mutation authorization plus one value-free
audit after fresh Owner, lifecycle, Store Lite permission, and package checks.
P3E-9C2 then consumes only that new authorization into one attempt claim
rather than reusing or widening completed P3E-8 read-only evidence. It performs
no execution. P3E-9C3A then records start/result around only a final core-owned
transport double. P3E-9C3B1 adds its dry-run-first CLI command, and P3E-9C3B2
completes the disposable apply rehearsal. P3E-9D is the next separate gate.

The staged P3E-9 boundary and the later separate approvals for synthetic
integration, mutation-specific authority, one real Sandbox Session, key
expiration, Session expiration, simulated payment, webhook proof, and P4 are
defined in
[`PAYMENT-ADAPTER-P3E9-SANDBOX-CHECKOUT-CREATION-FRONTIER.md`](PAYMENT-ADAPTER-P3E9-SANDBOX-CHECKOUT-CREATION-FRONTIER.md).

Stripe documents that API idempotency keys can be removed after at least 24
hours and that webhook deliveries may be retried or arrive out of order.
Therefore the adapter's client-local replay ledger remains authoritative even
when Stripe returns an idempotent response.

## Evidence And Cleanup

The review packet may retain only bounded non-secret evidence:

- commit, package, manifest, migration, schema, and configuration hashes;
- sandbox identifier hash, API version, permitted host, and test/live-mode
  refusal facts;
- opaque provider object/event hashes and normalized outcome counts;
- exact database/table/migration/row counts before and after; and
- focused, full acceptance, browser, lifecycle, rollback, and cleanup results.

It must not retain request/response bodies, signatures, keys, Checkout URLs,
customer fields, payment method details, provider object dumps, or unredacted
logs.

At closeout, revoke or rotate the sandbox key, remove the local webhook
forwarder configuration, stop every temporary process, drop only the exact
disposable database, revoke only its grant, remove staged package copies, and
confirm the configured primary and every client installation are unchanged.
Deleting the Stripe Sandbox is a separate explicit user confirmation because
it removes provider-side test evidence.

## Approval Stops

P3 planning does not cross these stops. Separate approval is required before:

1. implementing each P3A, P3B, P3C, or P3D code batch;
2. adding or downloading a third-party dependency or Stripe CLI;
3. signing in to Stripe or creating a Sandbox;
4. provisioning any sandbox key or webhook secret;
5. making the first outbound Stripe request;
6. completing the first simulated Checkout payment;
7. rotating/revoking a credential or deleting the Sandbox; and
8. proposing any P4 hosted or production work.

## Sources Rechecked

Official Stripe documentation was rechecked on 2026-08-15:

- [Testing use cases and Sandbox comparison](https://docs.stripe.com/testing-use-cases)
- [Sandboxes](https://docs.stripe.com/sandboxes)
- [API keys and restricted keys](https://docs.stripe.com/keys)
- [Secret-key management](https://docs.stripe.com/keys-best-practices)
- [Idempotent requests](https://docs.stripe.com/api/idempotent_requests)
- [Webhook verification and delivery behavior](https://docs.stripe.com/webhooks)
- [Checkout fulfillment and server-side Session retrieval](https://docs.stripe.com/checkout/fulfillment)

Provider behavior, API versions, permissions, terms, and fees must be checked
again immediately before P3E and P4.
