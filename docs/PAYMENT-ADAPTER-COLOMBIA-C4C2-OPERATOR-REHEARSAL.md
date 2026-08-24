# Colombia C4C2 Merchant-Read Double Operator Rehearsal

Status: complete as a core-owned, dry-run-first, no-contact operator gate. It
does not authorize or invoke the real Wompi transport. No account/dashboard,
real key value, DNS, TLS, HTTP, provider contact, transaction, payment, order,
demo, or deployment effect occurred.

## Current-client preflight

`addon_payment_adapter_wompi_merchant_read_preflight_helpers.php` requires the
current client database, Owner `addons.enable`, exact `store.orders.manage`,
enabled Store Lite `0.1.35`, enabled Wompi `0.1.5`, one valid client-local
`pub_test_` setting, and exactly three opaque secret references. It returns
only database/client/actor/public-key/setting/reference/merchant-plan/preflight
hashes. The public key value and references are not returned; secret values are
never resolved.

The helper reconstructs the exact C4B1 hash-only merchant plan without loading
package PHP. It has no environment, secret, registrar, package handler, network,
provider, write, or response-emission path.

## CLI boundary

`admin-wompi-merchant-read-double-execute.php` is CLI-only and defaults to dry
run. Dry run loads no package file or handler and writes nothing. Apply requires
exact database, package/version/state, all printed hashes, a nonzero backup
hash, operation/target, one attempt, no retry, network disabled, and explicit
real-provider/provider-mutation/transaction/payment/order denials.

Only after every confirmation matches does apply load the five exact reviewed
merchant-contract class files and construct one
`WompiMerchantContractTransportDouble`. It never loads the package adapter,
registers a handler, resolves a secret, or constructs the real cURL transport.
The bounded result contains only contract/transport hashes and all real effects
false.

This double gate deliberately records:

```text
Durable attempt consumed: no
Replay protection active: no
Real provider contact authorized: no
```

It therefore cannot authorize C4C3 provider contact. Durable one-shot start/
result evidence remains mandatory before the real GET.

## Acceptance

- 67 pure source assertions cover CLI-only/default-dry-run behavior, exact
  confirmations, one double, no cURL/handler/secret/transaction path, and no
  browser bridge.
- A fresh disposable database applies all 47 core migrations, exact Store Lite
  `0.1.35`, and Wompi `0.1.5`.
- Rehearsal proves one dry run, incomplete-confirmation refusal, one sealed-
  double apply, no raw public key in output, and Wompi attempt/event/action/
  audit business counts `0:0:0:0`.
- Networking is disabled at runtime and proxy/secret-value environments are
  removed.
- Cleanup passes `database:0 grant:0 staged-project:0 primary:unchanged`.

## Next boundary

C4C3A must add the durable one-shot provider execution runner, dry-run-first
real-target CLI, start-before-contact/result containment, and a no-contact
provider-double rehearsal. C4C3B then remains the owner-operated account/
isolated Sandbox-value/exactly-one-GET step. C4D transaction creation requires
another later authorization.
