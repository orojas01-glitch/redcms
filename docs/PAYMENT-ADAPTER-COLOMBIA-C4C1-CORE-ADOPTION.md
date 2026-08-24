# Colombia C4C1 Read-Only Merchant Transport And Core Adoption

Status: complete as an external-package implementation plus exact no-contact
core-adoption gate. The separately distributed Wompi package is version
`0.1.5` at commit `cc2ddd03ab54f663a089f7d059d802180e555d15`.

The package now owns one real read-only Sandbox merchant-contract transport,
but it was not invoked. No account/dashboard, key value, DNS, TLS, HTTP request,
provider contact, transaction, payment, order mutation, demo, or deployment
effect occurred.

## External package

The only new provider operation is
`merchant.acceptance-contracts.retrieve-sandbox`. It accepts the existing C4B1
hash-only plan plus one transient matching `pub_test_` value. The transport is
fixed to one TLS-verified `GET` to
`https://sandbox.wompi.co/v1/merchants/{public_key}`, with no redirects, proxy,
authorization header, production host, persistence, delay, or retry. Connection,
total-time, header, and body ceilings are fixed.

Strict response containment clears the public key, response body/headers, and
both raw acceptance tokens. Only two Wompi-controlled HTTPS contract links,
hash identities, HTTP status, byte count, and false mutation/payment/order/retry
facts survive.

External PR [#5](https://github.com/orojas01-glitch/redcms-store-lite-wompi/pull/5)
passes 321 assertions across C2, package/current-core, C4B1/B2/B3/B4A, and
C4C1. The real transport is proved only through a sealed no-network double.

## Exact core adoption

Core profile acceptance now pins external package `0.1.5` and all 19 integrity
files. The existing C3B disposable harness accepts only the historical
`0.1.4`/`5f372b3` pair or the reviewed `0.1.5`/`cc2ddd0` pair; arbitrary
version/revision overrides fail closed.

The C4C1 disposable rehearsal stages exact Store Lite `0.1.35` and Wompi
`0.1.5`, applies all 47 core migrations, installs Wompi disabled, records one
synthetic client-local public key plus three opaque references, enables the
package, verifies contained registrar/probe output, and invokes only the sealed
merchant-read double. Its 13 assertions prove bounded contract/hash output,
no raw key/token/body/header, empty Wompi attempt/event tables, and every real
provider/business effect false. Cleanup passes
`database:0 grant:0 staged-project:0 primary:unchanged`.

## Next boundary

C4C2 may add the core-owned Owner/database/package/setting/evidence/one-attempt
CLI and a disposable no-contact rehearsal. C4C3 remains the first real Wompi
contact: owner dashboard readiness, isolated Sandbox references, and exactly
one confirmed merchant GET. C4D transaction creation requires another later
authorization.
