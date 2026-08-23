# Colombia C4B1 Merchant-Contract Preflight And Core Adoption

Status: complete as a credential-free, no-contact external-package and exact
core-adoption gate. The separately distributed Wompi package is version
`0.1.1` at commit `7e4f8cb337d746b5a483932108e5dbcd109d7d86`.

No Wompi account/dashboard was accessed, no credential or personal value was
entered or viewed, no provider API request or transaction was made, no retained
database or client installation was migrated, and no demo or deployment state
changed.

## External package contract

Package `0.1.1` adds two dependency-free source/package pairs outside the clean
RED-CMS starter:

- `WompiMerchantContractRequestPlanner.php` accepts only client-local public-
  key availability plus its SHA-256. It fixes Sandbox host
  `sandbox.wompi.co`, `GET`, path template
  `/v1/merchants/{public_key}`, and a future response ceiling while constructing
  no final path.
- `WompiMerchantContractResponseGate.php` accepts only a pre-contained
  synthetic projection of `presigned_acceptance` and
  `presigned_personal_data_auth`. It returns exactly two distinct Wompi-
  controlled HTTPS contract links plus token/contract/response/projection
  hashes. Raw tokens are never returned or retained.

The gate refuses wrong/missing/extra fields, incorrect provider types,
malformed or reused tokens, reused contract links, non-Wompi hosts, HTTP,
URL credentials, ports other than 443, query strings, fragments, changed
plans, and changed projections. Contract presentation and customer consent
remain false/required. No adapter transport operation is exposed.

The official response shape and explicit two-contract acceptance requirement
were rechecked 2026-08-23 in
[Wompi tokens de aceptación](https://docs.wompi.co/docs/colombia/tokens-de-aceptacion/).

## Exact core adoption

Core runtime helpers are unchanged. Only exact-package tests/rehearsal pins now
recognize published package `0.1.1` at `7e4f8cb`:

- the profile fixture expects version `0.1.1` and verifies all 11 payload
  hashes;
- C3B/C3C1/C3C2 rehearsals refuse any different or dirty Wompi repository;
- disposable discovery requires Store Lite `0.1.35` and Wompi `0.1.1`;
- install and enable assertions require the exact `0.1.1` result; and
- historical C2/C3 evidence remains historical and is not rewritten.

The external manifest surface remains the existing closed
`store_lite_wompi_adapter_v1`: one adapter, exact Store Lite dependency, one
ordinary plus three secret-reference settings, two migrations, one refusing
event route, and only `sandbox.wompi.co` as outbound host.

## Acceptance evidence

### External package

- 34 existing offline transaction/event assertions;
- 64 current package/core-profile/integrity/registrar assertions;
- 29 merchant-contract preflight assertions;
- 11 exact integrity files and seven source/package parity checks; and
- PHP lint, JSON, shell, links, credential, source-boundary, and diff checks.

### Core focused

- 43 exact published-package Wompi profile assertions;
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

Both fresh client databases independently installed, configured, and enabled
the exact package. Their database-bound hashes/settings/references differed,
immutable package hashes matched, one-client rollback/disablement did not
change the peer, and both databases/grants were removed.

## C4B2 boundary

C4B2 is next and remains credential-free/no-contact. It may add only explicit
two-contract presentation/consent evidence and a transient server-side Nequi
wire/signature builder. It must keep raw email/phone/tokens/signature/secret
values out of plans, logs, evidence, responses, databases, and browser state;
must expose no HTTP transport; and must keep provider contact, transaction,
payment, retry, event publication, order mutation, demo, and deployment false.

C4C remains the first possible owner-operated account/credential/read-only
merchant GET gate and still requires separate explicit authorization after all
C4B engineering gates are merged and reviewed.
