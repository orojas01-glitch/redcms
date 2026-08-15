# Store Lite Demo Release Closeout

Date: 2026-08-15

## Decision

The Store Lite v1 basic-demo target is achieved on the isolated
`demo.red-sphere.com` RED-CMS installation. The hosted site runs RED-CMS
5.1.0 with the separately deployed Store Lite 0.1.31 package. Store Lite is
not part of the clean starter and no unrelated client installation or database
is included in this decision.

This closes the basic catalog, cart, guest-checkout, pay-on-receipt, and
administrator-tool target. Hosted payment adapters are not part of this
closeout.

## Hosted Evidence

Read-only desktop and mobile verification on 2026-08-15 confirmed:

- the public homepage returns nine published dog products and nine core-owned
  Add-to-cart controls;
- the variable Classic Dog Scarf exposes the exact nine combinations formed by
  Small, Medium, and Large with Red, Blue, and White;
- the public Checkout route renders an empty-cart state with server-derived
  item count, currency, and total;
- the earlier same-day hosted mutation session added a simple product and an
  exact scarf variant, recalculated totals after quantity changes, and exposed
  both pickup and delivery plus pay on receipt;
- Add Content exposes the separately installed Product and Cart component
  types;
- Tools exposes the separately authorized Products and Orders workspaces;
- the administrator signature reports RED-CMS 5.1.0 and the installed core
  Instructions summary identifies Store Lite as an optional add-on;
- the Checkout route at 390 by 844 CSS pixels has no horizontal overflow; and
- the inspected public, Checkout, and administrator surfaces produced no
  browser console warnings or errors.

The closeout inspection did not submit a new hosted order, enter guest contact
or delivery data, change a product, or invoke an administrator writer. Real
order creation, immutable order snapshots, pickup/delivery behavior,
idempotency, retry/conflict handling, and desktop/mobile checkout already pass
the isolated supported-server Store Lite browser gate recorded in
`ACCEPTANCE-SUITE.md`.

## Isolation And Rollback Boundary

- Only `demo.red-sphere.com` and its `orojas_demo_redsphere` database are in
  scope for the hosted Store Lite deployment.
- The clean GitHub starter contains contracts and acceptance tooling but no
  Store Lite package directory, package tables, products, carts, orders,
  settings, secrets, media, grants, or enabled installation row.
- Other clients retain separate code, databases, package state, settings,
  media, backups, and rollback points.
- Disabling Store Lite retains its package data and migration evidence;
  disable is not uninstall or purge.
- The pre-deployment filesystem/database backup and the server-local
  configuration rollback remain the recovery boundary for the demo.

## What Comes Later

PayPal or card checkout requires a separate provider-neutral payment-adapter
contract, server-verified payment events, secret provisioning, webhook replay
protection, and its own hosted acceptance. Events Calendar, Appointments,
Donations, Restaurant Ordering, and Member Access remain independent future
packages or tracks.
