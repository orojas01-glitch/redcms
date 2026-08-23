# Colombia C4B4B Durable No-Contact Authorization And Claim

Status: complete in RED-CMS core as a credential-free, no-contact durable-
evidence gate. It consumes exact external Wompi package `0.1.4` C4B4A
authorization/claim evidence. No provider request, secret value, package
handler, payment, order mutation, demo, or deployment effect occurs.

## Durable boundary

`addon_payment_adapter_wompi_no_contact_claim_helpers.php` validates the exact
C4B4A authorization and first-claim shapes and their self-fingerprints. It then
revalidates against the current client database:

- canonical database, client-scope, and actor-subject hashes;
- current database-backed Owner plus exact `addons.enable` authority;
- exact `store.orders.manage` grant and Store Lite declaration;
- enabled/current Wompi `0.1.4` and Store Lite `0.1.35` installations;
- four configured Wompi settings as one ordinary value plus three opaque
  secret references, without resolving any secret value;
- the 15-minute maximum authorization window, one attempt, and no retry; and
- absence of both nonce-derived action rows.

Planning is read-only and derives deterministic durable authorization and claim
state hashes. Apply acquires the global lifecycle lock, exact Wompi package
lock, Owner/capability/package/setting/action-row locks, then recomputes the
entire plan.

One transaction inserts exactly:

1. `wompi-no-contact-authorize.{authorization_nonce_sha256}` plus one value-free
   audit; and
2. `wompi-no-contact-claim.{authorization_nonce_sha256}` plus one value-free
   audit.

Both rows use the existing immutable `RED_Addon_Admin_Action_Executions`
ledger. No migration or new table is added. A duplicate authorization identity,
authorization-only partial state, or existing claim refuses replay.

## Failure and recovery

Changed expected state, tampered claim, wrong database scope, revoked order
authority, disabled package, caller-owned transaction, stale package/settings,
or missing rows/tables fails before persistence. If either reservation, audit,
or commit fails, the transaction rolls back both action rows and both audits.
An injected claim-audit failure proves zero partial state and permits one clean
recovery.

After a successful commit, output reports `authorizationRecorded=true`,
`claimRecorded=true`, and `replayProtectionActive=true`. It still requires:

- `executionAuthorized=false`;
- `executionStarted=false`;
- `executionPerformed=false`;
- `secretResolution=false`;
- `networkAccess=false`;
- `providerContact=false`;
- `providerMutation=false`;
- `paymentVerified=false`;
- `orderMutation=false`; and
- `retryAuthorized=false`.

## Acceptance evidence

- 24 C4B4B disposable assertions pass after all 46 core migrations.
- Exact external Wompi `0.1.4` and Store Lite `0.1.35` package payloads are
  staged outside the clean starter.
- Zero-write planning, exact two-row/two-audit commit, replay refusal, injected
  rollback/recovery, authority revocation, package disablement, client-scope
  drift, tampered evidence, and nested-transaction refusal pass.
- Source scanning finds no request global, environment read, secret resolver,
  package invocation, socket, cURL, HTTP client, or response emission path.
- Cleanup passes
  `database:0 grant:0 staged-project:0 primary:unchanged`.

## Next boundary

C4B4C is next and remains credential-free/no-contact. It may add only a core-
owned sealed transport-double runner that requires these exact durable rows,
records immutable start/result evidence, invokes no package handler or real
network, and permanently consumes an indeterminate attempt. Later gates own the
dry-run-first CLI and disposable network-disabled rehearsal. C4C remains the
first separately owner-authorized account/credential/read-only provider-
contact gate.
