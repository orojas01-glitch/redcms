# Subscription Catalog Price Binding

Status: $59/month catalog Price lifecycle verified on
`demo.red-sphere.com` in Stripe Sandbox; live mode remains unauthorized.

## Closed binding

The prepared runtime accepts one server-local binding with exactly:

- Store Lite offer ID;
- Stripe Product and recurring Price IDs;
- uppercase currency and integer minor-unit amount;
- monthly or yearly billing period;
- `active=true`; and
- `livemode=false`.

The binding must match the currently loaded published and available Store Lite
offer before the provider attempt is claimed. Adapter `0.1.20` then sends
`line_items[0][price]` while retaining the existing dynamic Store Lite intent
metadata required by the signed webhook lifecycle. The browser never supplies
the Price, Product, amount, or interval.

The existing inline recurring Price contract remains accepted by the earlier
adapter path. The catalog-bound public runtime requires `0.1.20`; package and
version drift fail closed. The transport refuses any request that mixes a
catalog Price with inline recurring Price data.

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

The demo acceptance additionally proved hosted Checkout, a paid $59 Sandbox
invoice, 200 responses for the signed completed-Checkout and paid-invoice
deliveries, `active/active` Store Lite state, and an immediate cancellation
with a 200 deletion delivery and `canceled/revoked` state. The public demo
offer remains available. Production credentials, live mode, refunds,
disputes, and customer provisioning remain separate release gates.
