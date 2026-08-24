# Colombia C4B4D Dry-Run Operator Command and No-Contact Rehearsal

Status: complete in RED-CMS core as a credential-free, no-contact operator
gate. It adds no migration, package handler, secret resolver, network client,
provider request, transaction, payment, order mutation, demo, or deployment.

## CLI boundary

`scripts/admin-wompi-no-contact-transport-double-execute.php` is CLI-only and
accepts one absolute regular JSON file no larger than 64 KiB. The file must
contain exactly the C4B4A `authorization` and `claim` objects. Core discovers
the exact Wompi `0.1.4` package, revalidates the C4B4B durable rows and current
client/Owner/order/package/setting state, and derives the C4B4C request and
start hashes.

Dry run is the default and writes nothing. It prints only bounded identities
and the required confirmations. Apply requires exact database, package,
version, enabled state, client/database/actor/order/plan/wire/authorization/
claim/request/start hashes, one nonzero backup hash, the fixed sealed-double
operation and target, one attempt, no retry, network disabled, and explicit
provider-contact, provider-mutation, transaction, payment, and order-mutation
denials.

After all confirmations match, the command constructs exactly one final
`RED_Addon_Wompi_No_Contact_Transport_Double` and calls the C4B4C runner once.
Only `sealed_double_completed` with every real effect false succeeds. Any
post-start failure leaves the attempt consumed and authorizes no retry.

The command contains no credential argument or literal, environment-secret
lookup, package registrar/handler, arbitrary callable, Wompi hostname, HTTP,
cURL, socket, request global, shell, delay, or browser/public bridge.

## Network-disabled disposable rehearsal

`scripts/wompi-payment-adapter-c4b4d-rehearsal.sh` stages exact clean core,
Store Lite `0.1.35`, and Wompi `0.1.4` into a temporary project. It creates a
fresh database and grant, applies all 46 core migrations, and records only a
synthetic Owner, exact capabilities, value-free setting references, enabled
packages, and bounded durable evidence.

The PHP runtime disables URL streams plus common cURL, socket, and stream-
socket functions. Proxy and common Wompi/RED-CMS secret-value environment
variables are removed for every PHP process. A dedicated probe must prove the
runtime is disabled before any fixture or command runs.

The rehearsal proves:

- the pure command source contract passes 55 assertions without a database;
- default dry run preserves the two authorization/claim rows and two audits;
- incomplete apply is refused before start;
- one fully confirmed apply invokes only the in-memory double and produces
  exact authorization/claim/start/result rows plus four value-free audits;
- Wompi payment-attempt and event-receipt tables remain empty;
- replay is refused before a second invocation; and
- cleanup passes `database:0 grant:0 staged-project:0 evidence:0
  environment:clear source-repositories:unchanged primary:unchanged`.

## Explicit non-effects

No real credential or personal value is resolved. No DNS, TLS, HTTP, Wompi
merchant/transaction/event request, provider mutation, transaction creation,
payment verification/application, event agreement, order mutation, retry,
client installation, retained database, demo change, or deployment occurs.

## Next boundary

C4C1 has since implemented and adopted the read-only transport without contact.
C4C2 now owns the CLI/no-contact gate, and C4C3 remains the first separately
confirmed owner account/Sandbox-value/read-only provider request. C4D one
approved Sandbox Nequi transaction, C4E declined/event/rotation evidence, and
C5 deployment remain separate later approvals. See
[`PAYMENT-ADAPTER-COLOMBIA-C4C1-CORE-ADOPTION.md`](PAYMENT-ADAPTER-COLOMBIA-C4C1-CORE-ADOPTION.md).
