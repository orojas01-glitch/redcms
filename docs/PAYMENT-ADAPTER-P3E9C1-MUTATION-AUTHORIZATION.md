# P3E-9C1 Sandbox Checkout Mutation Authorization

Status: complete in core. This gate records authority for one future Stripe
Sandbox Checkout-creation attempt. It does not claim or execute that attempt.

## Boundary

P3E-9C1 consumes only the exact non-executing P3E-9B synthetic plan for
adapter `redcms.store-lite-stripe-checkout` `0.1.5`. The authorization is
valid for at most fifteen minutes and binds:

- one cryptographic nonce and one maximum attempt;
- the exact synthetic plan and input hashes;
- the current database-backed Owner subject;
- fresh `addons.enable` lifecycle authority;
- fresh exact `store.orders.manage` package authority declared by enabled
  Store Lite `0.1.35`;
- the exact enabled, integrity-current adapter and Store Lite packages; and
- future restricted-test-write, provider-mutation, and Checkout-creation
  intent while payment, webhook, live mode, retry, and client deployment stay
  false.

Dry planning writes nothing. Apply repeats the complete decision under the
existing lifecycle, package, actor, permission, and installation locks, then
atomically writes exactly:

1. one nonce-derived row in `RED_Addon_Admin_Action_Executions`; and
2. one value-free `sandbox_checkout_mutation_authorized` activity fact.

The immutable row binds the synthetic-plan, authorization, Owner-subject, and
authorization-state hashes. A changed envelope cannot reuse the nonce. Audit
failure rolls back the nonce reservation, while successful replay is refused.

## Deliberate non-effects

This gate has no claim, execution-start, result, operator command, secret
resolution, environment reader, package invocation, request bridge, DNS, TLS,
HTTP, cURL, Stripe hostname, Checkout Session, payment, webhook, browser
navigation, Store Lite mutation, retry, live-mode, demo activation, client
deployment, migration, or new table.

P3E-8 read-only provider-contact evidence cannot be widened or reused. P3E-9C2
must separately consume the new authorization into one attempt claim. P3E-9C3
must separately prove start/result and a dry-run-first operator rehearsal with
a transport double. P3E-9D remains the separately approved first real Sandbox
Checkout Session.

## Acceptance

`scripts/addon-sandbox-checkout-mutation-authorization-self-test.php` runs
only against a uniquely named disposable acceptance database. Its 34
assertions prove:

- non-executing discovery of exact Store Lite `0.1.35` and adapter `0.1.5`;
- acceptance of the exact P3E-9B dry plan and refusal of the read-only profile;
- fresh Owner, lifecycle, and exact Store Lite permission checks;
- zero-write planning and atomic authorization/audit persistence;
- exact stored hashes with no credential values;
- replay, changed-envelope, expiry, overlong-window, and permission-revocation
  refusal;
- audit-failure rollback followed by one clean recovery;
- absence of package, secret, network, public-mutation, and provider effects;
  and
- exact fixture row, table, package, actor, and file cleanup.

The complete development acceptance runner owns creation and removal of its
fresh current-schema database. It must also prove the configured primary is
unchanged and the temporary database and grant are absent after cleanup.
