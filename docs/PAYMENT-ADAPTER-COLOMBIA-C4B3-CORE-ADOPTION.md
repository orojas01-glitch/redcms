# Colombia C4B3 Transaction Response Containment And Core Adoption

Status: complete as a credential-free, no-contact external-package and exact
core-adoption gate. The separately distributed Wompi package is version
`0.1.3` at commit `277760e6cd727fab6795524b654ab55c4597bfa2`.

No Wompi account/dashboard was accessed, no credential or personal value was
resolved from a client, no provider API request or transaction was made, no
retained database/client installation was migrated, and no demo/deployment
state changed.

## External response contract

Package `0.1.3` adds one dependency-free source/package pair outside the clean
starter: `WompiTransactionResponseContainment.php`.

Create containment requires a valid C2 transaction plan, valid self-
fingerprinted C4B2 wire evidence, exact HTTP 201, and one bounded documented
`data` projection. The id, reference, amount, COP currency, NEQUI method, and
PENDING state must agree with the plan/wire. Documented email, merchant,
payment-method, creation-time, and status-message values are validated and
discarded; only their sorted field names remain.

Lookup containment requires valid untampered create evidence, exact HTTP 200,
and exact identity/reference/amount/currency/method agreement. PENDING maps to
proposed pending, APPROVED to proposed paid, and DECLINED/ERROR to proposed
failed. `VOIDED` is outside the initial Nequi scope. Every valid and refused
result keeps payment verification, signed-event agreement, payment application,
Store Lite mutation authority, provider mutation, and retry authorization
false.

Package registration still exposes only `contract.probe`. Every provider
operation returns `provider_transport_disabled`; no callback, HTTP client, or
reusable response is published.

Official response shapes were rechecked 2026-08-23 in Wompi's
[transactions guide](https://docs.wompi.co/docs/colombia/transacciones/) and
[Nequi method guide](https://docs.wompi.co/docs/colombia/metodos-de-pago/#nequi).

## Exact core adoption

Core runtime helpers are unchanged. Only exact-package fixtures/rehearsal pins
now recognize package `0.1.3` at `277760e`:

- profile validation verifies all 15 payload hashes;
- disposable scripts refuse a different or dirty Wompi repository;
- discovery requires Store Lite `0.1.35` and Wompi `0.1.3`; and
- install/enable/two-client assertions require exact version `0.1.3`.

The manifest surface remains the same closed `store_lite_wompi_adapter_v1`:
one adapter, exact Store Lite dependency, one ordinary plus three secret-
reference settings, two migrations, one refusing event route, and only
`sandbox.wompi.co` as outbound host.

## Acceptance evidence

### External package

- 34 existing C2 transaction/event assertions;
- 72 package/current-core/integrity/registrar assertions;
- 29 C4B1 merchant-contract assertions;
- 49 C4B2 presentation/consent/wire assertions;
- 48 C4B3 create/lookup containment assertions;
- 15 exact integrity files and eleven source/package parity checks; and
- PHP lint, JSON, credential, source-boundary, and diff checks.

### Core focused

- 47 exact published-package Wompi profile assertions;
- 18 registrar assertions;
- 31 server-event ingress assertions; and
- 22 clean-starter boundary assertions.

### Disposable lifecycle

- 16 install/database/registrar assertions with cleanup
  `database:0 grant:0 staged-project:0 primary:unchanged`;
- existing 24 Stripe plus 17 Wompi atomic-enable assertions with the same exact
  cleanup; and
- 21 two-client enable/disable isolation assertions with cleanup
  `databases:0 grants:0 staged-project:0 primary:unchanged`.

Both fresh clients independently installed/configured/enabled exact package
`0.1.3`; immutable package evidence matched while database-bound evidence
differed. One-client rollback/disablement did not change the peer. All
disposable schemas, grants, and stages were removed.

## C4B4A follow-up

C4B4A later completed in package `0.1.4` at `5f372b3` plus exact core
adoption. It adds pure sealed-double-only authorization, first-claim
preparation, and observed-state projections while keeping claim persistence,
replay protection, and execution false. See
[`PAYMENT-ADAPTER-COLOMBIA-C4B4A-CORE-ADOPTION.md`](PAYMENT-ADAPTER-COLOMBIA-C4B4A-CORE-ADOPTION.md).

C4B4B separately owns durable authorization/claim and replay protection. Later
no-contact gates own the runner, CLI command, and rehearsal. C4C remains the
first separately owner-authorized account/credential/read-only provider-
contact gate.
