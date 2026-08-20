# P3E-9C2 Sandbox Checkout Mutation Claim

Status: complete in core. This gate consumes one exact P3E-9C1 authorization
into one immutable attempt claim. It does not start or execute the attempt.

## Boundary

P3E-9C2 recomputes the complete P3E-9C1 decision and requires:

- the exact non-executing P3E-9B synthetic plan and input hashes;
- adapter `redcms.store-lite-stripe-checkout` `0.1.5` and Store Lite `0.1.35`
  enabled and integrity-current in the same database;
- a fresh database-backed Owner with exact `addons.enable` and Store Lite
  `store.orders.manage` grants;
- the still-active at-most-fifteen-minute authorization envelope; and
- one exact immutable P3E-9C1 authorization row whose plan, authorization,
  Owner-subject, state, actor, action, and nonce facts all match.

Dry planning writes nothing. Apply repeats the decision under lifecycle,
package, actor, permission, installation, authorization-row, and claim-row
locks. It then atomically writes exactly:

1. one distinct nonce-derived claim in
   `RED_Addon_Admin_Action_Executions`; and
2. one value-free `sandbox_checkout_mutation_attempt_claimed` audit fact.

The claim row binds the synthetic plan, P3E-9C1 authorization hash and state,
claim state, numeric actor, and fixed target. A second claim is refused. Audit
failure rolls back the claim reservation so one clean claim remains possible.

## Deliberate non-effects

This gate has no execution start, result, operator command, secret resolution,
environment reader, package invocation, request bridge, DNS, TLS, HTTP, cURL,
Stripe hostname, Checkout Session, payment, webhook, browser navigation, Store
Lite mutation, retry, live mode, demo activation, client deployment, migration,
or new table.

P3E-9C3A now separately adds immutable start/result evidence around only a
core-owned transport double. P3E-9C3B must add the dry-run-first operator
rehearsal. P3E-9D remains the separately approved first real Stripe Sandbox
Checkout Session. See
[`PAYMENT-ADAPTER-P3E9C3A-TRANSPORT-DOUBLE-RUNNER.md`](PAYMENT-ADAPTER-P3E9C3A-TRANSPORT-DOUBLE-RUNNER.md).

## Acceptance

`scripts/addon-sandbox-checkout-mutation-claim-self-test.php` runs only
against a uniquely named disposable acceptance database. Its 37 assertions
prove:

- non-executing discovery of the exact two packages;
- exact P3E-9C1 authorization before claim planning;
- zero-write planning and one atomic claim/audit commit;
- exact stored authorization and claim hashes;
- replay, missing/changed/tampered authorization, changed expected hash,
  expiry, lifecycle-authority revocation, order-authority revocation, and
  disabled Store Lite refusal;
- audit-failure rollback followed by one clean claim;
- read-only-profile and forbidden execution/secret/network primitive refusal;
  and
- exact claim, authorization, audit, package, actor, table, and file cleanup.

The complete development acceptance runner owns creation and removal of its
fresh current-schema database. It must prove the configured primary is
unchanged and the temporary database and grant are absent after cleanup.
