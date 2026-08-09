# Store Lite Cart Mutation Integration Contract

Status: Gate 2D1 accepted on 2026-08-08. The separately distributed Store Lite
0.1.14 package declares and registers one Add-to-cart mutation against the
existing generic core atomic runner. RED-CMS still exposes no production
public-mutation endpoint or Add-to-cart form.

## Proven boundary

The package declares one public POST route and a closed form request containing
only product, integer quantity 1–100, and optional variant. Its runtime binding
supplies one route callback that refuses direct invocation, one mutation
handler, one state loader, and the exact eight package tables required by cart
persistence.

Core remains responsible for the anonymous subject, CSRF, idempotency, rate
limit, lifecycle/package locks, transaction, replay ledger, postcondition,
value-free audit, and generic response. Store Lite resolves every commercial
fact from current package storage and never reads request/cookie/session/server
globals, owns a transaction, emits output, or activates `commerce.cart`.

## Disposable acceptance

The isolated Store Lite rehearsal proves a simple-product add, exact replay,
changed-command idempotency conflict, exact variable-product variant add, and
unknown-variant rollback. The accepted mutations commit two carts, two lines,
total quantity three, two package activities, two core executions, and two
value-free core audits. The existing Google Chrome desktop/mobile suite passes
87 checks with no console, page, network, or HTTP errors. The configured
primary fingerprint remains unchanged and the temporary database, grant,
server, and staged package are removed.

## Deliberate exclusions

Gate 2D1 does not add a browser cookie, public identity/token bootstrap,
Add-to-cart HTML, cart reader, production route wiring, checkout, order,
inventory mutation, payment handling, or general `commerce.cart` service.

Gate 2D2 will define the core-owned browser bootstrap/form/server integration
and rendered success/refusal behavior before Add-to-cart can be exercised from
a public page.
