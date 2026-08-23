# Colombia C4B4C Sealed No-Contact Transport-Double Runner

Status: complete in RED-CMS core as a credential-free, no-contact execution-
evidence gate. It consumes exact C4B4B durable authorization and claim rows,
invokes only a final core-owned in-memory double, and records immutable start/
result evidence. No package handler, secret value, network, Wompi request,
payment, order mutation, demo, or deployment effect occurs.

## Start-before-invocation boundary

`addon_payment_adapter_wompi_transport_double_helpers.php` revalidates exact
C4B4A evidence plus current client, Owner, order permission, package, setting-
reference, durable authorization, and durable claim state. Planning writes
nothing and builds one hash-only request for operation
`checkout.create-sandbox-no-contact-double` with transport
`core_sealed_double`.

Apply acquires the lifecycle and Wompi package locks, repeats current authority
and durable-row validation under transaction locks, then commits one
`wompi-no-contact-start.{authorization_nonce_sha256}` row plus one value-free
audit before the double can run. Start-audit failure rolls back and the double
is not invoked.

## Final sealed double and bounded outcome

Only `RED_Addon_Wompi_No_Contact_Transport_Double` is accepted; an arbitrary
callable or package adapter is not. Its input contains only order/amount/COP and
hash identities. The completed outcome contains only request/projection hashes
and fixed false-effect facts. No response body/header, credential, personal
data, provider reference, or reusable wire value is returned.

After exactly one invocation, a second transaction verifies the immutable start
row and records `wompi-no-contact-result.{authorization_nonce_sha256}` plus one
value-free result audit. A throwing or malformed double is normalized to
`sealed_double_indeterminate` with all network/provider/business effects false.

If result reservation, audit, or commit fails after start, start remains
durable and the attempt is permanently spent. Replay refuses before a second
double invocation; retry remains false.

## Acceptance evidence

- 38 C4B4C disposable assertions pass after all 46 core migrations.
- Missing durable claim, changed expected start, and replay refuse before
  double invocation.
- Successful execution produces exact authorization/claim/start/result rows
  plus four value-free audits.
- Start-audit failure rolls back before invocation and permits one clean
  recovery.
- Result-audit failure preserves the spent start, records no result, and
  permanently refuses retry.
- Throwing and malformed doubles each record one bounded indeterminate result.
- Source scanning finds no request global, environment read, secret resolver,
  package registration/invocation, Wompi host, socket, cURL, HTTP client, shell,
  delay, or response-emission path.
- Cleanup passes
  `database:0 grant:0 staged-project:0 primary:unchanged`.

## Explicit non-effects

`executionPerformed=true` means only that the final in-memory double was called.
Every bounded result requires network access, provider contact/mutation,
transaction creation, payment verification/application, event agreement, order
mutation, and retry to remain false.

## Next boundary

C4B4D is now complete and remains credential-free/no-contact. It adds only a
CLI-only, dry-run-first operator command with exact evidence/confirmation gates
and a disposable rehearsal whose runtime disables network primitives and
invokes only this sealed double. C4C remains the first separately owner-
authorized account/credential/read-only provider-contact gate. See
[`PAYMENT-ADAPTER-COLOMBIA-C4B4D-OPERATOR-REHEARSAL.md`](PAYMENT-ADAPTER-COLOMBIA-C4B4D-OPERATOR-REHEARSAL.md).
