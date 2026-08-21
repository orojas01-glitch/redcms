# P3E-9C3B1 Transport-Double Operator Command

Status: complete as a CLI-only source contract. The command defaults to dry
run and can invoke only the final core-owned P3E-9C3A in-memory double.

The command requires one absolute bounded JSON evidence file containing only
the exact `input` and `prepared` objects. Apply additionally requires exact
database, package `0.1.5`, enabled state, plan, input, authorization,
authorization-state, claim-state, execution-start, nonzero-backup, operation,
target, one-attempt, no-retry, no-network, no-provider-mutation, and no-real-
Checkout-creation confirmations.

Dry run revalidates evidence and prints the confirmations without constructing
or invoking the double. Apply contains exactly one plan call, one final-double
construction, and one C3A runner call. Only the bounded
`transport_double_completed` result succeeds. Every other post-start result
keeps the attempt consumed with no retry.

The command accepts no credential argument or value and contains no secret
resolver, package registrar/handler, arbitrary callable, DNS, TLS, HTTP, cURL,
Stripe hostname, request body, shell execution, browser bridge, payment,
webhook, Store Lite mutation, client deployment, migration, or table path.

`scripts/addon-sandbox-checkout-transport-operator-command-self-test.php`
passes 45 pure source assertions and opens no database. P3E-9C3B2 now
separately proves dry-run, confirmation-refusal, one apply, replay refusal, and
exact project/database/grant cleanup. P3E-9D remains the separately approved
first real Sandbox Checkout Session. See
[`PAYMENT-ADAPTER-P3E9C3B2-OPERATOR-REHEARSAL.md`](PAYMENT-ADAPTER-P3E9C3B2-OPERATOR-REHEARSAL.md).
