# Store Lite Payment Adapter P2 Fixture

Status: P2 is a completed non-network contract fixture for a future optional
Stripe Checkout adapter. It does not create a Stripe account, use a provider
SDK, read a secret, make a network request, expose a webhook, persist an
attempt, alter an order, add a checkout control, or change the hosted demo.

## Scope

The fixture in
[`scripts/store-lite-stripe-checkout-contract-self-test.php`](../scripts/store-lite-stripe-checkout-contract-self-test.php)
is CLI-only and self-contained. It models the boundary a separately
distributed Stripe adapter must preserve when a later client-specific sandbox
gate is approved. It runs before the disposable-database portion of
[`scripts/dev-acceptance.sh`](../scripts/dev-acceptance.sh).

It has no database, filesystem, request-global, session, package lifecycle,
runtime, provider credential, provider SDK, or network path. Its synthetic
`demo-red-sphere` identifier is an in-memory fixture label, not the hosted
installation, a database name, a credential, or authorization to change that
client.

## Closed Checkout Boundary

The contract accepts only a selected-client immutable order snapshot with a
closed identity, `awaiting_payment` state, integer minor-unit amount, `USD`,
snapshot hash, and idempotency hash. The fixture derives a request plan with
only the order identity, server-derived amount/currency, fixed `payment` mode,
fixed `hosted_full_page` shape, opaque idempotency relation, and one approved
HTTPS status-return URL.

No browser amount, currency, customer field, order state, checkout URL, or
provider response is accepted at that point. The plan itself cannot create a
Checkout Session. A separate synthetic-response validator accepts only an
opaque `cs_test_…` session reference and matching canonical
`https://checkout.stripe.com/c/pay/…` URL, with no query, fragment, userinfo,
port, redirect, or alternate host. P3 must revalidate the then-current
provider response shape before it can make a real sandbox request.

## Event Boundary

The fixture separates a raw-body signature boundary from parsed event facts.
Because P2 deliberately has no credential, it models only the output of an
already-verified boundary: a raw-body SHA-256 and bounded receipt time. It
does not pretend to perform cryptographic verification. P3 must implement and
prove raw-body signature verification with one client-local test secret before
parsing an event or looking up an order.

After that boundary, the fixture accepts only the selected client, current
opaque session reference, exact immutable order/amount/currency, a bounded
timestamp window, one unseen event reference, and these predeclared Stripe
event types:

| Provider event | Normalized P0 outcome | Eligible immutable order state |
| --- | --- | --- |
| `checkout.session.completed` | `paid` | `awaiting_payment` |
| `checkout.session.async_payment_failed` | `failed` | `awaiting_payment` |
| `checkout.session.expired` | `expired` | `awaiting_payment` |
| `charge.refunded` | `refund_confirmed` | `paid` |
| `charge.dispute.created` | `reversal_reported` | `paid` |

Normalization returns a bounded event proposal only. It cannot transition an
order, trigger fulfillment, persist a replay record, or send a provider
response. A browser return is limited to `complete`, `cancelled`, or `unknown`
status display; it cannot claim `paid` or alter order/fulfillment state.

## Refusal Coverage

The fixture fails closed with no partial plan, response, or normalized event
for invalid configuration/client/order, non-USD or mismatched currency,
non-payment/embedded checkout shape, invalid provider redirect, unverified
signature boundary, provider/order/amount/currency/client mismatch, stale
timestamp, replayed event, unknown outcome, and ineligible refund/reversal.

It passed as part of the normal acceptance runner and adds no table, migration,
route, asset, settings record, package directory, provider configuration,
order, customer, or business data to the clean starter.

## Next Gate

P3 remains separately gated. It requires an explicit proposal and approval for
one disposable client-only Stripe test environment, server-local test-secret
reference, raw-body cryptographic verification, sandbox provider lookup where
needed, lifecycle proof, rollback, and exact cleanup. It does not authorize a
live credential, live transaction, other client, or production deployment.
