# Subscription Catalog Price Binding

Status: source and activation-file preparation only. Stripe, the hosted demo,
and live mode are unchanged.

## Closed binding

The prepared runtime accepts one server-local binding with exactly:

- Store Lite offer ID;
- Stripe Product and recurring Price IDs;
- uppercase currency and integer minor-unit amount;
- monthly or yearly billing period;
- `active=true`; and
- `livemode=false`.

The binding must match the currently loaded published and available Store Lite
offer before the provider attempt is claimed. Adapter `0.1.19` then sends
`line_items[0][price]` while retaining the existing dynamic Store Lite intent
metadata required by the signed webhook lifecycle. The browser never supplies
the Price, Product, amount, or interval.

The existing inline recurring Price contract remains accepted only by adapter
`0.1.18`. The catalog-bound public runtime requires `0.1.19`; package/version
drift fails closed.

## Configuration ownership

`SUBSCRIPTION_STRIPE_CATALOG_BINDING` belongs in ignored server-local
configuration. The environment-compatible JSON name is
`RED_SUBSCRIPTION_STRIPE_CATALOG_BINDING_JSON`. Product and Price IDs are not
credentials, but they remain server-owned configuration so the browser cannot
substitute another catalog item.

API and webhook secrets retain their existing owner-entered secret-reference
boundary. This work does not reveal, copy, rotate, or replace them.

## Verification

The focused tests prove exact array and JSON normalization, commercial drift
refusal, use of the configured Price ID, removal of inline `price_data`, one
sealed provider exchange, foreign-offer refusal, transient Checkout URL
handling, and compatibility with the existing subscription and webhook paths.

Provider catalog retrieval, deployment, Checkout navigation, test payment,
signed delivery, cancellation, live mode, refunds, and customer provisioning
remain separate operational evidence.
