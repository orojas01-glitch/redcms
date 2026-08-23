# P3E-9D4C1 Real-POST Operator Command

Status: complete as a CLI-only source contract. P3E-9D4C2 network-disabled
no-contact rehearsal remains next. No real apply occurred.

## Command Boundary

`scripts/admin-sandbox-checkout-real-post-execute.php` is CLI-only and defaults
to dry run. It accepts one absolute bounded JSON evidence file containing only
the exact synthetic plan, input, D0 preflight, D2 preflight outcome, and D4B1
prepared authorization objects.

The command recomputes the complete D4B execution plan and prints exact
database, package, Store Lite, actor, expiry, preflight, input, request, order,
authorization, claim, start, secret-availability, operation, and target facts.
Apply additionally requires one nonzero backup hash, one attempt, and explicit
confirmation that provider contact, provider mutation, and Checkout creation
are authorized while payment, webhook, browser navigation, Store Lite
mutation, Session expiration, retry, live mode, and client deployment are not.

Default dry run exits before the command's single
`red_addon_checkout_real_mutation_execute()` call site. It resolves no secret
value, invokes no registrar or handler, writes no start/result, and performs no
network or provider request.

## Apply Result

The apply path can accept only the exact bounded
`checkout_session_created` result: one open, unpaid, non-live Session reference
with the Checkout URL already discarded. Every other result reports the
attempt consumed and no retry authorized. The command cannot print a Checkout
URL, response body, headers, request id, credential, or provider object.

## Acceptance

`scripts/addon-sandbox-checkout-real-post-operator-command-self-test.php`
passes 74 pure assertions. It proves the complete confirmation vocabulary,
exact versions/operation/target, three intended effects, eight excluded
effects, one plan and one execution call site, dry-run ordering, bounded
success/failure wording, exact evidence shape/size, credential exclusions,
absence of network/shell/secret/runtime primitives from command source, and
absence of public or browser bridges. The test opens no database, reads no
configuration, resolves no secret, executes no package, and invokes no runner.

P3E-9D4C2 must separately stage exact merged core, adapter, and Store Lite
sources in a fresh disposable current-schema database, exercise dry run and
changed/incomplete confirmation refusal with provider networking disabled, and
prove exact cleanup. It must never run the command's real `--apply`.
