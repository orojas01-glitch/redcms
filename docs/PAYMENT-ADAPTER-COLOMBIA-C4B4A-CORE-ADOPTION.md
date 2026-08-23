# Colombia C4B4A Pure No-Contact Attempt Contract And Core Adoption

Status: complete as a credential-free, no-contact external-package and exact
core-adoption gate. The separately distributed Wompi package is version
`0.1.4` at commit `5f372b3a2e35723f638a03cf089deedc238c99a4`.

No Wompi account/dashboard was accessed, no credential or personal value was
resolved from a client, no provider API request or transaction was made, no
retained database/client installation was migrated, and no demo/deployment
state changed.

## External package contract

Package `0.1.4` adds one dependency-free source/package pair outside the clean
starter: `WompiNoContactAttemptContract.php`.

Its authorization projection requires exact valid C2 plan/C4B2 wire evidence,
client/database/actor/secret-availability/nonce hashes, a maximum 15-minute
window, fresh Owner and order-authority facts, enabled package/Store Lite facts,
one-attempt/no-retry confirmation, and explicit network/provider/order denial.
It fixes only operation `checkout.create-sandbox-no-contact` and transport
`sealed_double_only`.

Its claim projection accepts only the first attempt, a distinct claim nonce,
an empty prior-claim evidence list, and the exact current authorization. It
sets remaining attempts to zero but deliberately requires
`claimPersisted=false`, `replayProtectionActive=false`, and
`executionAuthorized=false`. This pure contract cannot impersonate durable
replay protection.

Its state projection accepts claim-only evidence or exact C4B3 create/lookup
evidence. States are limited to `claim_prepared`, `pending_observed`,
`approved_observed`, and `failed_observed`. Even APPROVED remains proposed paid
evidence; payment verification, event agreement, provider/order mutation, and
retry remain false.

Package registration still exposes only `contract.probe`. Every provider
operation returns `provider_transport_disabled`; no callback, database writer,
transport, or reusable request is published.

## Exact core adoption

Core runtime helpers are unchanged. Only exact-package fixtures/rehearsal pins
now recognize package `0.1.4` at `5f372b3`:

- profile validation verifies all 16 payload hashes;
- disposable scripts refuse a different or dirty Wompi repository;
- discovery requires Store Lite `0.1.35` and Wompi `0.1.4`; and
- install/enable/two-client assertions require exact version `0.1.4`.

The manifest surface remains the same closed `store_lite_wompi_adapter_v1`:
one adapter, exact Store Lite dependency, one ordinary plus three secret-
reference settings, two migrations, one refusing event route, and only
`sandbox.wompi.co` as outbound host.

## Acceptance evidence

### External package

- 34 existing C2 transaction/event assertions;
- 74 package/current-core/integrity/registrar assertions;
- 29 C4B1 merchant-contract assertions;
- 49 C4B2 presentation/consent/wire assertions;
- 48 C4B3 create/lookup containment assertions;
- 52 C4B4A no-contact-attempt assertions;
- 16 exact integrity files and twelve source/package parity checks; and
- PHP lint, credential, source-boundary, and diff checks.

### Core focused

- 48 exact published-package Wompi profile assertions;
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
`0.1.4`; immutable package evidence matched while database-bound evidence
differed. One-client rollback/disablement did not change the peer. All
disposable schemas, grants, and stages were removed.

## C4B4B follow-up

C4B4B later completed in core using the existing immutable administrator-
action ledger. It atomically records the exact authorization and one claim plus
two audits, refuses replay, and leaves execution unavailable. See
[`PAYMENT-ADAPTER-COLOMBIA-C4B4B-DURABLE-CLAIM.md`](PAYMENT-ADAPTER-COLOMBIA-C4B4B-DURABLE-CLAIM.md).

C4B4C separately owns the sealed transport-double runner. Later gates own the
dry-run-first CLI and disposable no-contact rehearsal. C4C remains the first
separately owner-authorized account/credential/read-only provider-contact gate.
