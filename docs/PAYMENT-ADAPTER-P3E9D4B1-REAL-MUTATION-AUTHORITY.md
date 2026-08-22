# P3E-9D4B1 Fresh Real-POST Authority And Claim

Status: complete dependency; P3E-9D4B2 durable execution is now also complete.

## Boundary

This first D4B slice recognizes only separately distributed adapter `0.1.8`,
Store Lite `0.1.35`, the exact D0 request, the recomputed D2 preflight plan and
start/result identities, one order snapshot, one current database, one
database-backed Owner, exact `addons.enable` and `store.orders.manage`
authority, one value-free `stripe.secret-key` availability hash, and the exact
provider operation `checkout.create-sandbox-real-post`.

It creates new D4-only action identities. P3E-9C authorization, claim, start,
and result rows use different prefixes, versions, operations, and state hashes
and are therefore ineligible.

Planning writes nothing. Authorization and claim each repeat the complete
decision under lifecycle, package, actor, permission, installation, and action
row locks. Each stage atomically commits one immutable
`RED_Addon_Admin_Action_Executions` row plus one value-free activity fact.
Replay, changed identity, expiry, package drift, database drift, secret-
availability drift, and authority revocation fail closed.

## Explicit Stop

This slice has no execution-start write, result write, package registration,
handler invocation, secret-value resolution, environment-value read, network
primitive, Stripe request, Checkout Session, payment, webhook, browser caller,
Store Lite mutation, retry, live mode, hosted/client action, migration, or new
table. The later P3E-9D4B2 slice separately adds start-before-access, one
in-memory invocation, and bounded result persistence.

## Acceptance

- The pure evidence contract passes 20 assertions for exact adapter `0.1.8`
  D0/D2 identity adoption, changed-version refusal, one-attempt authorization,
  fifteen-minute expiry, false-effect policy, and temporary package cleanup.
- The disposable-database lifecycle passes 30 assertions for non-executing
  discovery, exact package/database/Owner/permission/secret-reference state,
  zero-write planning, atomic authorization and claim, immutable state links,
  replay and authority-revocation refusal, forbidden execution/network
  primitives, and exact fixture cleanup.
- The complete acceptance runner creates a fresh current-schema database,
  applies all 46 migrations twice, preserves the disposable baseline exactly,
  and removes the acceptance database and grant. The separately created
  baseline database and grant were also removed after the run.
