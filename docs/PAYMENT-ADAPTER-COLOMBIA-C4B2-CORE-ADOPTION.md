# Colombia C4B2 Consent, Transient Wire, And Core Adoption

Status: complete as a credential-free, no-contact external-package and exact
core-adoption gate. The separately distributed Wompi package is version
`0.1.2` at commit `fdbf88145c5858c313f6f2a3e50137e54801d683`.

No Wompi account/dashboard was accessed, no credential or personal value was
resolved from a client, no provider API request or transaction was made, no
retained database/client installation was migrated, and no demo/deployment
state changed.

## External package contract

Package `0.1.2` adds three dependency-free source/package pairs outside the
clean starter:

- `WompiContractConsentPresentation.php` models exactly two ordered Wompi-
  controlled HTTPS contract links and two separately named required controls.
  It returns no HTML/raw tokens and performs no browser/consent effect.
- `WompiContractConsentEvidence.php` binds separate presentation and acceptance
  facts for both contracts to order id, guest-subject hash, presentation/
  contract/token hashes, nonce, acceptance time, and an exact 15-minute window.
- `WompiNequiTransientWireRequestBuilder.php` reconstructs the existing closed
  transaction plan, validates injected synthetic email/phone/tokens against
  its hashes, requires Sandbox-only private/integrity value families, creates
  the exact Bearer header, signature, Nequi body, and POST request inside one
  pure call, then returns only bounded hashes/field names.

The actual integrity signature is SHA-256 of the raw integrity input. It is not
returned as evidence. The package uses a domain-separated integrity-input hash
and a second hash of the signature. Email/phone and their individual hashes,
raw tokens, private key, integrity secret, authorization header, signature,
body, and complete request are neither returned nor persisted.

Package registration still exposes only `contract.probe`. Every provider
operation returns `provider_transport_disabled`; no callback or reusable wire
request is published.

Official requirements were rechecked 2026-08-23 in Wompi's
[acceptance-token guide](https://docs.wompi.co/docs/colombia/tokens-de-aceptacion/),
[transactions guide](https://docs.wompi.co/docs/colombia/transacciones/),
[Nequi method guide](https://docs.wompi.co/docs/colombia/metodos-de-pago/#nequi),
and
[integrity-signature guide](https://docs.wompi.co/docs/colombia/widget-checkout-web/#paso-3-genera-una-firma-de-integridad).

## Exact core adoption

Core runtime helpers are unchanged. Only exact-package fixtures/rehearsal pins
now recognize package `0.1.2` at `fdbf881`:

- profile validation verifies all 14 payload hashes;
- disposable scripts refuse a different/dirty Wompi repository;
- discovery requires Store Lite `0.1.35` and Wompi `0.1.2`; and
- install/enable/two-client assertions require exact version `0.1.2`.

The manifest surface remains the same closed `store_lite_wompi_adapter_v1`:
one adapter, exact Store Lite dependency, one ordinary plus three secret-
reference settings, two migrations, one refusing event route, and only
`sandbox.wompi.co` as outbound host.

## Acceptance evidence

### External package

- 34 existing C2 transaction/event assertions;
- 70 package/current-core/integrity/registrar assertions;
- 29 C4B1 merchant-contract assertions;
- 49 C4B2 presentation/consent/wire assertions;
- 14 exact integrity files and ten source/package parity checks; and
- PHP lint, JSON, shell, links, credential, source-boundary, and diff checks.

### Core focused

- 46 exact published-package Wompi profile assertions;
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
`0.1.2`; immutable package evidence matched while database-bound evidence
differed. One-client rollback/disablement did not change the peer. All
disposable schemas/grants/stages were removed.

## C4B3 follow-up

C4B3 later completed in package `0.1.3` at `277760e` plus exact core adoption.
It adds strict contained transaction-create and transaction-lookup projections
bound to the C4B2 wire/create evidence without returning raw bodies/headers or
authorizing payment/order mutation. See
[`PAYMENT-ADAPTER-COLOMBIA-C4B3-CORE-ADOPTION.md`](PAYMENT-ADAPTER-COLOMBIA-C4B3-CORE-ADOPTION.md).

Later C4B gates separately own one-attempt authorization/claim/state, CLI
confirmation, transport doubles, and disposable no-contact rehearsals. C4C
remains the first separately owner-authorized account/credential/read-only
provider-contact gate.
