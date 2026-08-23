# Colombia C1 Payment Initiation Contract

Status: complete and merged. C1 adds one dependency-free provider-neutral
normalizer and one CLI-only Wompi/Nequi contract fixture. It creates no
adapter package, runtime caller, database, credential, network request,
provider transaction, payment, order mutation, or deployment.

## Fixed Result Union

Core now recognizes exactly two payment-initiation result modes through
`includes/addon_payment_initiation_helpers.php`:

### `hosted_redirect`

The new generic canonical hosted value contains exactly:

- `providerReference`: one bounded opaque provider reference; and
- `checkoutUrl`: one validated absolute HTTPS URL.

The new normalizer wraps its input value with the mode and acceptance decision
but does not rename, add, remove, or reorder either value field. HTTP, credentials
in the URL, ports, query strings, fragments, empty paths, unknown fields, and
missing reference/URL values fail closed. The existing Stripe P2 fixture keeps
its provider-specific `checkoutSessionRef` result and no existing Stripe helper
or caller is changed or wired through the new normalizer in C1.

### `out_of_band_confirmation`

The new value contains exactly:

- `providerReference`: one bounded opaque provider reference;
- `state`: fixed `pending`; and
- `customerAction`: fixed provider-neutral
  `approve_in_provider_app`.

This mode cannot contain a checkout URL, paid state, provider-named action,
unknown field, or unknown mode. It is only a typed initiation result. C1 adds
no pending-order page, status endpoint, browser polling, package dispatch, or
Store Lite state transition.

## Offline Wompi/Nequi Fixture

`scripts/payment-adapter-colombia-c1-contract-self-test.php` proves 55 focused
assertions without configuration, database, package, secret resolution,
request globals, provider SDK, DNS, TLS, HTTP, webhook route, or browser.

Official Wompi event documentation was rechecked on 2026-08-22. It says the
signed `properties` list can vary and must be extracted in provider order, and
failed webhook delivery may retry after 30 minutes, 3 hours, and 24 hours.
C1 therefore resolves a bounded dynamic list and uses a 25-hour fixture window
instead of reusing Stripe's short event window. C2 must recheck these facts
before package implementation. See
<https://docs.wompi.co/docs/colombia/eventos/>.

The fixture models only:

- one enabled client-local Wompi Sandbox configuration;
- one Store Lite `awaiting_payment` order in integer `COP` minor units;
- synthetic example email and official Wompi Nequi Sandbox approval phone;
- explicit current privacy and personal-data acceptance facts;
- one public-key-setting availability fact and three distinct opaque secret
  references for private, integrity, and event-secret classes;
- a transient direct-API request whose personal, acceptance-token, signature,
  and secret-reference material is discarded after bounded hashes are made;
- one exact out-of-band pending initiation;
- one bounded provider-supplied `transaction.updated` property list resolved
  in declared order, a 25-hour retry-compatible timestamp window, and the
  event-secret checksum boundary;
- an exact cryptographic binding between the verified boundary and the parsed
  event later supplied to reconciliation;
- exact event/status-lookup agreement for provider reference, status, order
  reference, amount, currency, and method; and
- closed `APPROVED` to proposed `paid`, plus `DECLINED`/`ERROR` to proposed
  `failed`, with order mutation always false.

`PENDING`, event replay, changed event/boundary pairing, changed provider/order
reference, event/lookup disagreement, amount mismatch, currency mismatch,
stale timestamp, invalid checksum, unknown signed property, production event,
missing acceptance, disabled configuration, non-COP order, invalid phone, and
secret-reference reuse all fail closed.

## Data Boundary

The request planner internally constructs only the current direct-API shape:
integer amount, `COP`, synthetic customer email, `NEQUI` method and phone,
unique order reference, synthetic integrity signature, and the two current
acceptance-token inputs. Its returned evidence contains only provider/method/
environment identities, order/amount/currency facts, request and acceptance
hashes, secret-availability booleans, and false effect flags.

The initiation result and normalized reconciliation evidence contain no email,
phone, acceptance token, integrity signature, secret reference, event secret,
raw event, response body, response header, provider URL, or browser material.
The bounded paid outcome is evidence for a later Store Lite service; C1 cannot
apply it.

## Compatibility Evidence

Focused verification passes:

- Colombia C1 generic/Wompi contract fixture: 55 assertions;
- existing Store Lite Stripe P2 contract: 29 assertions;
- generic typed adapter invocation: 19 assertions; and
- payment-adapter P3A-1 profile: 26 assertions.

The helper is additive and has no current runtime caller. The separate Stripe
P2 fixture proves its exact existing provider-specific result independently;
Stripe authority, command, adapter, and deployment behavior therefore remain
unchanged. Stripe D4D remains owner-deferred.

## C2 Completion And C3 Boundary

C2 is complete in separately distributed package version `0.1.0` at commit
`e17a371`. It adopts the exact C1 union without modifying core, Store Lite, or
the Stripe adapter; declares only the Nequi/COP one-time method; defines one
client-local public setting plus three opaque secret-reference settings; and
passes 34 offline-contract plus 60 package assertions.

Generic discovery/registration passes, while the current Stripe-only core
payment profile deliberately refuses the three-secret/Wompi-host shape. C3 is
next and owns that profile extension plus disposable integration. C2 created
or used no provider account, real or Sandbox key, acceptance token, customer
phone/email, DNS, TLS, HTTP request, Wompi transaction, Nequi notification,
payment, webhook ingress, browser checkout, Store Lite order mutation, hosted-
demo change, client installation, or deployment. See
[`PAYMENT-ADAPTER-COLOMBIA-C2-PACKAGE.md`](PAYMENT-ADAPTER-COLOMBIA-C2-PACKAGE.md).
