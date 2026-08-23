# Colombia Payment Adapter C0 Decision

Status: C0 decision and C1 offline initiation contract are complete; provider-
package implementation and provider contact have not started. The owner
deferred Stripe P3E-9D4D on 2026-08-22 without cancelling or widening any
completed Stripe evidence.

## Decision

The first Colombia payment candidate is a separately distributed optional
adapter with package identity `redcms.store-lite-wompi`. Its first and only
customer-visible method is **Pay with Nequi**, its only currency is `COP`, and
its first commercial scope is one-time Store Lite guest orders.

Wompi is the provider and Nequi is the initial payment method. Store Lite and
RED-CMS core must not contain Wompi or Nequi field names, endpoints,
credentials, statuses, customer-data rules, or provider-specific tables. The
adapter is not bundled, installed, enabled, configured, or contacted by
installing RED-CMS or Store Lite.

The candidate integration shape is Wompi's direct Nequi transaction API, which
initiates approval in the customer's Nequi app. It is not represented as a
hosted checkout URL.

Direct Nequi Push or QR integration remains a valid later candidate for a
client whose merchant contract, certification, pricing, or operating workflow
requires it. C0 does not claim that Wompi is always preferable for every
Colombian client.

## Why Wompi/Nequi Is First

- Wompi exposes Nequi as one asynchronous transaction method and can later
  support other Colombian methods through the same provider package without
  changing Store Lite's provider-neutral order contract.
- The current Wompi Sandbox supplies isolated test keys, a separate API base,
  deterministic Nequi approval/decline numbers, transaction lookup, and
  `transaction.updated` events.
- Direct Nequi Push and dynamic QR are more specialized integrations. Current
  Nequi material requires merchant enablement, QA credentials, certification
  evidence, status reconciliation, and reversal handling before production.
- Selecting Wompi does not authorize adding cards or PSE. Each additional
  customer-visible method requires its own later decision and acceptance
  extension.

## Required Provider-Neutral Contract Extension

The current payment-adapter v1 checkout-creation contract requires every
successful initiation to return both an opaque provider reference and an
absolute HTTPS hosted-checkout URL. Direct Wompi/Nequi does not fit that shape:
it creates an asynchronous transaction and asks the customer to approve it in
the Nequi app.

C1 therefore defines a closed additive initiation-mode union before any Wompi
package exists:

- `hosted_redirect`: preserves the existing Stripe-compatible reference plus
  HTTPS URL semantics without modifying or wiring existing Stripe helpers; or
- `out_of_band_confirmation`: requires an opaque provider reference, no URL,
  a pending state, and the provider-neutral customer action
  `approve_in_provider_app`.

An out-of-band initiation leaves the browser on a RED-CMS-owned pending-order
surface. It cannot poll Wompi from the browser, expose the provider reference,
or imply payment. Any later browser status refresh must query only a bounded
RED-CMS endpoint; provider lookup and event reconciliation remain server-side.

Wompi also documents Widget, Web Checkout, and payment-link surfaces. C0 does
not select them because the reviewed material does not establish a Nequi-only
hosted result that satisfies the current Store Lite attempt/reference
contract. This decision must be rechecked if the provider adds a suitable
method-locked hosted flow.

## Current Provider Facts To Recheck

These facts were checked against official provider documentation on
2026-08-22 and must be checked again immediately before implementation,
Sandbox contact, and production review:

- Wompi separates Sandbox and production by both API base and key family. The
  current Sandbox base is `https://sandbox.wompi.co/v1`.
- One API transaction is created through `POST /v1/transactions`; current
  direct-API requirements include integer `amount_in_cents`, `COP`, unique
  merchant reference, customer email, payment-method data, integrity evidence,
  and the current `acceptance_token` and `accept_personal_auth` values after
  explicit customer acceptance.
- The Nequi method requires a ten-digit Colombian Nequi phone number and begins
  asynchronously. A newly created transaction is not proof of payment.
- Final transaction outcomes include `APPROVED`, `DECLINED`, and `ERROR`;
  current state can be reconciled with `GET /v1/transactions/{id}` and the
  `transaction.updated` event.
- Event authenticity depends on the documented ordered-property/timestamp/
  event-secret SHA-256 checksum. The event secret is distinct from public,
  private, and integrity keys, and Sandbox and production event URLs must stay
  separate.
- Current Wompi policy requires the customer to see and explicitly accept the
  current privacy/data-treatment contracts before transaction creation. A
  stored checkbox without the provider's current tokens and document versions
  is insufficient.
- Current Sandbox Nequi fixtures use `3991111111` for approval and
  `3992222222` for decline. They are test data, never defaults or production
  customer values.

Official references:

- Wompi environments and keys: <https://docs.wompi.co/docs/colombia/ambientes-y-llaves/>
- Wompi transactions: <https://docs.wompi.co/docs/colombia/transacciones/>
- Wompi payment methods: <https://docs.wompi.co/docs/colombia/metodos-de-pago/>
- Wompi acceptance tokens: <https://docs.wompi.co/docs/colombia/tokens-de-aceptacion/>
- Wompi events: <https://docs.wompi.co/docs/colombia/eventos/>
- Wompi Sandbox data: <https://docs.wompi.co/docs/colombia/datos-de-prueba-en-sandbox/>
- Wompi Widget and Web Checkout: <https://docs.wompi.co/docs/colombia/widget-checkout-web/>
- Nequi business APIs: <https://www.nequi.com.co/negocios/apis>
- Nequi Push payments: <https://www.nequi.com.co/negocios/pago-en-linea>
- Nequi integration and certification: <https://www.nequi.com.co/negocios/como-te-integras>

## Provider-Neutral Mapping

| Store Lite or core fact | Wompi adapter projection | Boundary |
| --- | --- | --- |
| Immutable Store Lite order identity | Unique Wompi merchant reference | Adapter stores only the bounded reference relation. |
| Server-derived order total | `amount_in_cents` | Exact integer `COP` amount must match on creation and every verified result. |
| Installation currency | `COP` | Any non-COP installation or order fails before secret or provider access. |
| Existing guest email and phone | Required transaction/customer facts | Read transiently from the locked order snapshot; do not duplicate raw values into payment-attempt evidence or audits. |
| Selected checkout method | `NEQUI` | No card, PSE, subscription, QR, payout, or tokenized source is included. |
| Provider transaction id | Opaque payment-attempt reference | Never grants paid state by itself. |
| Direct Nequi Push initiation | `out_of_band_confirmation` | Returns no checkout URL; browser remains on the RED-CMS pending-order surface. |
| `PENDING` | Pending normalized attempt | No Store Lite paid or fulfillment transition. |
| Verified `APPROVED` | Proposed normalized `paid` event | Store Lite still rechecks order, reference, amount, currency, replay, and current state. |
| Verified `DECLINED` or `ERROR` | Proposed normalized failure event | Never becomes paid; retry policy remains a later closed gate. |

Browser return, dashboard display, customer screenshot, email receipt, a
successful creation response, or `PENDING` status has no payment authority.
Only a server-verified final result may propose a transition through the
existing Store Lite payment-event service.

## Secrets And Personal Data

The adapter will require one client-specific non-secret Wompi public-key
setting plus separately scoped opaque references for the private key,
integrity key, and event secret. The public key must never be a clean-starter
default or shared between clients. No private, integrity, or event-secret value
may enter a manifest, migration, fixture, database row, command argument,
browser response, audit, evidence file, or repository.

The Nequi phone number and customer email are personal data already governed
by the client installation's order and retention policy. The adapter may use
them only for the exact current transaction request. It must not copy them
into payment history, provider-event evidence, diagnostic output, or
idempotency material. Provider payloads remain in memory only long enough to
validate and project closed facts.

## Colombia Acceptance Ladder

1. **C0 — complete decision:** record the candidate, provider facts, fixed
   scope, privacy boundary, exclusions, and separate approval gates. No
   provider account, package, runtime, or data change.
2. **C1 — complete, initiation-mode contract and offline fixture:** add the closed
   `hosted_redirect`/`out_of_band_confirmation` union without changing the
   existing hosted shape; then prove one COP/Nequi request model, integrity-key
   availability by opaque reference, a transient current-acceptance-token/
   contract model, asynchronous outcomes, event-checksum verification, lookup
   reconciliation, replay and mismatch refusal, and response redaction with no
   package, credential, database, or network.
3. **C2 — next, external adapter package:** create a separately versioned, disabled-
   by-default `redcms.store-lite-wompi` package with exact manifest,
   configuration, secret-reference, outbound-host, migration, registrar, and
   transport-double acceptance. It remains outside the core starter and Store
   Lite repository.
4. **C3 — disposable core/Store Lite integration:** prove installation,
   enable/disable, one pending attempt, verified normalized outcomes,
   idempotency, reconciliation, rollback, two-client isolation, and exact
   cleanup without Wompi contact.
5. **C4 — separately authorized Wompi Sandbox:** only after owner-operated
   merchant registration and credential entry, prove bounded approved and
   declined Nequi test transactions, signed event and lookup agreement,
   secret isolation, credential rotation/removal, and exact cleanup. This gate
   is not authorized by C0.
6. **C5 — separately approved demo deployment:** review HTTPS webhook ingress,
   retention, customer acceptance UI, order operations, backups, rollback,
   browser behavior, and client-specific configuration before enabling the
   method on `demo.red-sphere.com`. Production remains a later client-specific
   decision.

## C1 Completed Scope

C1 adds only the provider-neutral initiation-mode type/validation extension,
a dependency-free non-network contract fixture, and governing documentation.
The canonical `hosted_redirect` input value must be returned unchanged, while
existing Stripe helpers and callers remain untouched. Unknown/mixed modes or a
URL on `out_of_band_confirmation` must fail closed.
C1 does not create the adapter package, manifest, migration, database row,
public checkout control, webhook route, provider account, credential, token,
HTTP request, Wompi transaction, Nequi notification, payment, order
transition, hosted-demo change, or client deployment.

The fixture must keep transport behind a sealed double and prove that
credentials and personal data cannot appear in normalized output. Direct
Nequi, Wompi cards/PSE, subscriptions, refunds, reversals, retries, browser
return, public webhook ingress, Sandbox contact, and production are excluded.

C1 passes 55 focused assertions in
[`PAYMENT-ADAPTER-COLOMBIA-C1-INITIATION-CONTRACT.md`](PAYMENT-ADAPTER-COLOMBIA-C1-INITIATION-CONTRACT.md).
The existing hosted value remains unchanged, the new out-of-band value is
closed and URL-free, and no runtime caller or provider effect is added.

## Open Commercial Gates

Before C4, the owner must confirm current merchant eligibility, legal entity,
fees and taxes, settlement destination/timing, refund and reversal support,
transaction limits, support obligations, data-processing terms, and Sandbox
access. C0 makes no pricing, eligibility, settlement, certification, or
production-readiness claim.
