# PayPal P0 Offline Foundation

Status date: 2026-09-01.

## Outcome

The first PayPal adapter slice exists in the separate
`redcms-store-lite-paypal` repository as package
`redcms.store-lite-paypal` `0.1.0`. It is a credential-free, no-network
foundation for one-time USD PayPal Orders v2 Checkout in Sandbox.

This slice does not install or enable the package, contact PayPal, access an
account, use a client ID or secret, create or capture an order, expose a
webhook, navigate a browser, record a payment, change a Store Lite order, alter
`demo.red-sphere.com`, or enable live mode.

## Closed contracts

- one immutable Store Lite order produces a deterministic `CAPTURE`-intent
  create-order plan for `api-m.sandbox.paypal.com/v2/checkout/orders`;
- `reference_id` and `custom_id` both bind the PayPal order to the internal
  Store Lite order ID, and the amount is server-derived USD;
- a create response is accepted only when it is `CREATED` and its exact
  Sandbox approval URL has one `token` query equal to the PayPal order ID;
- a capture plan targets only the same PayPal order, and its response is
  accepted only for one final `COMPLETED` capture with the original Store Lite
  order and amount;
- an already `SUCCESS`-verified `PAYMENT.CAPTURE.COMPLETED` event is projected
  to `paid` only when event, capture response, and provider lookup agree;
- event-evidence replay is refused; and
- all pure contract results retain false effect flags until later transport and
  Store Lite mutation gates explicitly authorize them.

## Package boundary

The manifest declares:

- adapter `redcms.store-lite-paypal/checkout`;
- exact Store Lite dependency `>=0.1.50 <1.0`;
- one client-local return origin and PayPal webhook ID;
- opaque secret references for the PayPal client ID and client secret;
- Sandbox server outbound host `api-m.sandbox.paypal.com`;
- one `server-signature` event-route declaration whose handler still refuses
  execution; and
- two integrity-bound evidence migrations containing hashes, bounded provider
  IDs, internal order identity, amount, currency, and state, but no raw body,
  headers, approval URL, credential value, or PayPal customer data.

The only callable adapter operation is `contract.probe`. All provider
operations fail with `provider_transport_disabled`. RED-CMS recognizes the
exact package as `store_lite_paypal_adapter_v1`, but validation remains
non-activating and preserves the database, registrar, ingress, and atomic
enablement blockers.

## Core compatibility change

The provider-neutral hosted-redirect validator previously rejected every URL
query. PayPal's approval URL requires `?token=<PayPal order ID>`. The core now
accepts only that single query when the token exactly equals the validated
provider reference. It continues to reject unrelated queries, mismatched
tokens, extra query parameters, fragments, user info, ports, non-HTTPS URLs,
and malformed hosts/paths.

This is additive and leaves the existing query-free Stripe and Wompi contracts
unchanged.

## Acceptance evidence

- create-order contract: 19 assertions;
- capture and verified-event contract: 20 assertions;
- package discovery, integrity, registrar, refusal, migration, and current-core
  boundary: 28 assertions; and
- RED-CMS provider-neutral initiation contract: 59 assertions.

All assertions use synthetic data. No OAuth, provider, database, payment,
webhook, browser, deployment, or Store Lite mutation occurs.

## Next gate

Run a disposable install/enable/disable and two-client isolation rehearsal.
Only after those pass should the owner enter a Sandbox client ID, client
secret, webhook ID, and return origin for server-side OAuth, create/capture,
signature verification, and one real Sandbox purchase acceptance.

Official provider contracts used for this slice:

- [PayPal REST authentication](https://developer.paypal.com/api/rest/authentication/)
- [PayPal Orders v2 integration](https://developer.paypal.com/api/rest/integration/orders-api/)
- [PayPal webhook verification](https://developer.paypal.com/api/rest/webhooks/rest/)
- [PayPal webhook event names](https://developer.paypal.com/api/rest/webhooks/event-names/)
