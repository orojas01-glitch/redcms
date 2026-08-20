# P3E-9C3A Checkout Transport-Double Runner

Status: complete in core. This gate records immutable execution start and
bounded result evidence around one final core-owned in-memory transport double.
It cannot contact Stripe or create a real Checkout Session.

## Boundary

The runner recomputes P3E-9C1, verifies the exact authorization row, derives
and verifies the exact P3E-9C2 claim row, and revalidates current Owner,
`addons.enable`, Store Lite `store.orders.manage`, adapter `0.1.5`, Store Lite
`0.1.35`, input, plan, and expiry evidence.

Apply performs two durable transactions:

1. commit one nonce-derived execution-start row and one value-free start audit;
2. invoke exactly one final `RED_Addon_Checkout_Mutation_Transport_Double`;
3. validate a closed no-network/no-provider outcome; and
4. commit one result row plus one value-free result audit.

The start commits before invocation. A start-audit failure rolls back before
the double runs. Once start commits, any double fault or result-recording
failure permanently spends the attempt; replay is refused and no retry is
authorized.

## Deliberate non-effects

The helper has no credential resolver, environment reader, package registrar,
adapter invocation, arbitrary callable, request bridge, DNS, TLS, HTTP, cURL,
Stripe hostname, Checkout Session, payment, webhook, browser navigation, Store
Lite mutation, retry, live mode, demo activation, client deployment, migration,
or new table. `executionPerformed=true` means only that the in-memory double
was called; network, provider contact/mutation, and Checkout creation remain
false in every bounded outcome.

P3E-9C3B must separately add a CLI-only dry-run-first operator command that can
invoke only this reviewed double. P3E-9D remains the separately approved first
real Sandbox Checkout Session.

## Acceptance

`scripts/addon-sandbox-checkout-mutation-transport-double-self-test.php` runs
only in a uniquely named disposable acceptance database. Its 36 assertions
prove exact authorization/claim prerequisites, zero-write planning,
start-before-invocation, exact start/result hashes, successful and fault
outcomes, replay refusal, missing evidence refusal, changed-start refusal,
start-audit rollback and recovery, permanent no-retry after result-audit
failure, absence of package/secret/network primitives, and exact cleanup.
