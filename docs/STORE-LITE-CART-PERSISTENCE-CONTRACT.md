# Store Lite Cart Persistence Contract

Status: Gate 2C accepted on 2026-08-08 and implemented by the separately
distributed Store Lite 0.1.13 package. RED-CMS contains only this mirrored
contract and rehearsal inventory; the clean starter contains no Store Lite
package code, cart tables, migration rows, settings, or business data.

## Core/package boundary

Core will eventually authenticate a public mutation, resolve a core-issued
anonymous subject, consume CSRF and idempotency evidence, and own one InnoDB
transaction. Only then may it pass the positive numeric `SubjectRecordID`,
installation currency, closed product/quantity/optional-variant intent, and
expected cart-state SHA-256 to Store Lite.

Store Lite never receives or stores a raw subject token, cookie, session,
request, response, CSRF value, or idempotency key. One unique package cart is
owned by one numeric subject relation. There is deliberately no foreign key to
core's expiring anonymous-subject infrastructure: core lifecycle cleanup must
not silently delete package business state.

## Package state and transaction rules

The package migration creates three namespaced InnoDB tables:

- carts: numeric subject relation, currency, and timestamps;
- cart lines: restrictive product/optional-variant references, line identity,
  quantity, server-derived integer price/total/currency, and product-state
  evidence; and
- value-free activity: created/updated event, numeric relations, line identity,
  and different before/after cart-state hashes.

The package operation requires an already-active caller transaction and never
begins, commits, or rolls back. It locks the cart, its lines, the product, and
the selected variant; compares fresh expected state; reruns the 0.1.12
server-authoritative resolver; writes or increments one exact line; reloads and
verifies the complete cart; and appends one activity fact. Any result other than
`created` or `updated` requires core to roll back.

The package refuses stale competing state, unknown commercial fields,
unavailable products or variants, insufficient stock, quantity overflow,
relationship conflict, partial write, postcondition drift, and late activity
failure. Product and variant deletion is restrictive; only explicit cart
deletion cascades its lines.

## Still disconnected

Gate 2C does not register `commerce.cart`, declare a Store Lite public-mutation
contract, add an Add-to-cart control, issue/read a cookie, connect the core
dispatcher, reserve inventory, or create an order, checkout, payment, tax,
shipping, fulfillment, merge, abandonment, or purge flow.

The next gate is the explicit core-to-package public-mutation integration and
desktop/mobile browser path. It must preserve the current origin, subject,
CSRF, rate, idempotency, response-owner, deployment-profile, and rollback
contracts and remain isolated to one disposable client installation before any
real deployment.

## Evidence

The package's disposable migration suite proves five ordered migrations,
exactly ten package tables, the numeric ownership/no-core-FK boundary, exact
cart-line relationships, and value-free activity columns. Its transactional
persistence suite proves simple and variant lines, additive quantities,
server-derived money, fresh/stale state, subject isolation, stock and unknown-
field refusal, late-audit rollback, deletion protection, exact cleanup, and an
unchanged configured-primary fingerprint.
