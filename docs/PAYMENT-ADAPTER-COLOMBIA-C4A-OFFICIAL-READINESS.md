# Colombia C4A Official Wompi Sandbox Readiness Audit

Status: complete as a documentation-only, public-docs-only gate. Official
provider facts were rechecked on 2026-08-23. Public Wompi documentation was
retrieved over HTTPS. No Wompi account or merchant dashboard was opened or
accessed, no credential was entered or viewed, no Wompi API endpoint was
called, and no transaction, payment, database, package, demo, or deployment
state changed.

## Official sources reviewed

Only current Wompi-owned sources govern this audit:

- [Ambientes y llaves](https://docs.wompi.co/docs/colombia/ambientes-y-llaves/)
- [Tokens de aceptación](https://docs.wompi.co/docs/colombia/tokens-de-aceptacion/)
- [Transacciones](https://docs.wompi.co/docs/colombia/transacciones/)
- [Widget & Checkout Web — firma de integridad](https://docs.wompi.co/docs/colombia/widget-checkout-web/#paso-3-genera-una-firma-de-integridad)
- [Métodos de pago — Nequi](https://docs.wompi.co/docs/colombia/metodos-de-pago/#nequi)
- [Datos de prueba en Sandbox](https://docs.wompi.co/docs/colombia/datos-de-prueba-en-sandbox/#nequi)
- [Eventos](https://docs.wompi.co/docs/colombia/eventos/)
- [Cómo crear una cuenta](https://www.wompi.co/es/co/ayuda/como-crear-cuenta)
- [Reglamento Comercios](https://www.wompi.co/es/co/reglamento-comercios/)

Provider documentation, terms, dashboard behavior, test values, endpoints, and
account requirements may change. They must be rechecked immediately before
every future network or account gate.

## Current official contract

### Account ownership

Wompi's current onboarding distinguishes natural-person and legal-entity
merchants. It requests identity documentation and an unprotected RUT, requires
review/acceptance of data authorization plus terms, and requires an eligible
Bancolombia or Nequi destination account. Account registration, identity
verification, terms acceptance, and any commercial decision belong to the
owner. RED-CMS must never automate or impersonate these acts.

### Separate environments and four integration values

Wompi currently documents completely separate Sandbox and Production APIs:

```text
Sandbox:    https://sandbox.wompi.co/v1
Production: https://production.wompi.co/v1
```

The four Sandbox value prefixes are currently:

```text
pub_test_
prv_test_
test_integrity_
test_events_
```

Production uses separate `pub_prod_`, `prv_prod_`, `prod_integrity_`, and
`prod_events_` values. C4 must fail closed on any production prefix or host.

### Acceptance tokens and user consent

Before `POST /v1/transactions`, the current Wompi contract requires two
presigned tokens:

- `acceptance_token`; and
- `accept_personal_auth`.

They are retrieved with the public-key request
`GET /v1/merchants/:llave_publica_de_comercio`. The response also supplies the
two current contract `permalink` values. Wompi requires the user to be shown
both contracts and explicitly accept each one before the two tokens are sent.

Tokens, contract links, and acceptance evidence are current provider state;
they must not be hard-coded into RED-CMS or treated as permanent content.

### Nequi transaction request

The current API creates a transaction with `POST /v1/transactions`, using the
private key as the Bearer authorization value. Required transaction data
includes both acceptance tokens, integer `amount_in_cents`, `COP`, customer
email, unique reference, integrity signature, and payment method:

```json
{
  "type": "NEQUI",
  "phone_number": "<10-digit Colombian number>"
}
```

The integrity signature is the lowercase SHA-256 digest of the exact
concatenation, without separators, in this order:

```text
reference + amount_in_cents + currency + integrity_secret
```

If an expiration time is supplied, it is inserted between currency and the
integrity secret. Wompi recommends generating this signature on the server,
never in the browser. C4B must keep the integrity value and raw concatenation
transient and out of plans, logs, evidence, and responses.

The current Sandbox-only Nequi numbers are:

```text
3991111111 -> APPROVED
3992222222 -> DECLINED
other      -> ERROR
```

These are provider test values, not RED-CMS defaults. They may appear only in
an explicitly authorized Sandbox fixture or operator confirmation and must be
rechecked immediately before use.

A newly created transaction is asynchronous and begins `PENDING`. Final status
must be established through a later `GET /v1/transactions/{id}` and/or a
verified `transaction.updated` event. A successful creation response is not
proof of payment.

### Event integrity and retries

Wompi currently supplies the event checksum both in HTTP header
`X-Event-Checksum` and in body field `signature.checksum`; the documentation
allows validation against either. C3C1 deliberately selects the body checksum
and preserves the body bytes unchanged.

The checksum is SHA-256 over the values named by `signature.properties` in
their supplied order, followed by `timestamp` and the event secret. Wompi warns
that the property list can vary, matching the dynamic-property C2 verifier.

The current events guide says non-200 delivery is retried at approximately 30
minutes, 3 hours, and 24 hours, up to three retries. The package's 25-hour
window remains compatible but must be rechecked at the event-execution gate.

## Compatibility audit

| Official requirement | Current RED-CMS/Wompi state | C4 result |
| --- | --- | --- |
| Sandbox host and `/v1/transactions` | Closed C2 planner requires exact values | Ready offline |
| Public/private/integrity/event values | Manifest has one client-local public setting plus three secret references | Ready offline |
| Both current acceptance tokens | Planner requires separate token hashes and contract hash | Ready offline, no retrieval |
| Customer email and Nequi phone | Planner accepts hashes only | Ready offline, no wire values |
| Dynamic event properties and body checksum | Pure verifier supports bounded dynamic properties and event secret | Ready offline |
| Pending initiation plus final lookup/event agreement | Response and event gates require exact identity/amount/currency/method agreement | Ready offline |
| Current-core external package regression | 34 offline assertions pass; the full package suite stops at its historical expectation that core rejects Wompi, which C3A intentionally superseded | **C4B blocker** |
| `GET /merchants/{public_key}` | No provider-contact operation or response containment exists | **C4B blocker** |
| Present two current contracts and record explicit acceptance | No Store Lite public acceptance UI or submission contract exists | **C4B blocker** |
| Transient raw email/phone/tokens | No bounded wire-request builder exists | **C4B blocker** |
| Private-key Bearer and integrity signature | Values remain unresolved; no transient signer exists | **C4B blocker** |
| `POST /transactions` | Adapter supports only `contract.probe`; all provider operations refuse | **C4B blocker** |
| Polling final lookup | No Wompi transport or contained lookup parser exists | **C4B blocker** |
| Operational webhook response | Route handler deliberately refuses; no event persistence/response runner exists | Later C4 event blocker |

The audit therefore authorizes no credential entry and no provider contact.
The external package failure is a stale test expectation, not evidence of a
runtime or provider failure. C4B must update that package-owned regression and
restore a fully green current-core/package test before adding transport
contracts.

## Credential custody mapping

Each RED-CMS client installation must use its own Wompi merchant/environment
and database-local references:

| Wompi value | RED-CMS contract | Storage rule |
| --- | --- | --- |
| Sandbox public key | `wompi.public-key` | Client database; never shared with another client |
| Sandbox private key | `wompi.private-key` | Database stores only opaque reference; value stays server-local outside Git/webroot/evidence |
| Sandbox integrity secret | `wompi.integrity-key` | Same reference-only rule |
| Sandbox event secret | `wompi.event-secret` | Same reference-only rule |

Even though the public key is not secret, it is client identity and must remain
out of generic starter defaults and cross-client evidence. Production values
must never be entered during a Sandbox gate.

## Revised C4 ladder

### C4A — complete: official contract audit

This document. Public documentation read only; no account or provider API
effect.

### C4B — next: no-contact transport preflight

C4B may add only separately reviewable, dependency-free contracts for:

1. current acceptance-token/permalink retrieval planning;
2. two-contract presentation and explicit-acceptance evidence;
3. a transient Nequi wire-request builder containing both tokens, exact
   email/phone, unique reference, COP amount, and integrity signature;
4. strict Sandbox-prefix/host/path enforcement;
5. contained merchant, transaction-create, and lookup response projections;
6. a one-attempt authorization/claim/state machine;
7. dry-run-first CLI confirmations and redacted evidence; and
8. transport doubles plus no-contact disposable rehearsals; and
9. replacement of the external package's superseded C2 core-refusal assertion
   with current-core compatibility coverage.

C4B must keep every network/provider/payment effect false. It must not require
the owner to enter a credential.

### C4C — owner-operated account and read-only provider proof

Only after C4B is merged, the owner may register or sign into Wompi, complete
current merchant/terms requirements, and enter four Sandbox values into one
isolated non-production RED-CMS installation. A separately authorized first
provider contact should be the bounded public-key merchant GET used to retrieve
current contract links/tokens. It creates no transaction.

### C4D — one approved Sandbox Nequi transaction

Requires a new explicit authorization after C4C evidence review. Use only the
current official approved test number, a synthetic test customer, a unique
reference, the current two contracts/tokens, one claim, one POST, bounded
polling, redacted evidence, and exact cleanup. No automatic retry.

### C4E — declined/error, event, rotation, and removal

Separately prove declined/error behavior, signed event plus lookup agreement,
replay/idempotency, credential rotation/removal, and cleanup. HTTPS webhook
publication and dashboard event-URL registration remain separately reviewed.

### C5 — demo deployment

Only after C4 is complete: public acceptance UX, operational HTTPS ingress,
retention, order transitions, browser behavior, backup, rollback, and explicit
deployment approval for `demo.red-sphere.com`.

## C4A acceptance

- official facts are dated and directly linked;
- all current C1/C2 alignments and C4 blockers are explicit;
- the stale external-package core-refusal assertion is recorded for C4B;
- Sandbox and Production remain disjoint;
- four values remain client-local with three secret values reference-only;
- owner account/terms acts are not automated;
- C4B is no-contact and credential-free;
- every later network/transaction/event/deployment step has its own approval;
- no personal data, token, key, secret, provider response, or account evidence
  is stored in this repository; and
- internal links, credential scans, project-boundary scans, and
  `git diff --check` pass.
