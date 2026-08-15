# Store Lite Payment Adapter P1 Decision

Status: P1 selects Stripe Checkout's hosted full-page flow as the first
provider-specific adapter candidate for the provisional USD online-card pilot.
This is a design decision only. No Stripe account, API key, webhook endpoint,
outbound request, package, migration, checkout button, customer data, charge,
or order state is created by this document.

## Decision Scope

This decision applies only to the first future Store Lite payment-adapter
implementation for a small U.S./USD-style online catalog where the client wants
credit and debit cards, a hosted checkout page, and no monthly platform fee.
It is not a commitment for every RED-CMS client, a merchant-account selection,
or a payment deployment.

The existing Store Lite base package remains unchanged. Pay on receipt stays
available without any adapter.

## Decision

The first candidate is a separately distributed Stripe adapter using Stripe
Checkout's full-page hosted Checkout Sessions flow for one-time payments.

The later adapter may request a hosted Checkout Session only from an immutable
Store Lite order snapshot. It may never collect card details in RED-CMS,
recalculate a browser-submitted total, or mark an order paid from a browser
return. Payment state remains governed by the provider-neutral P0 contract in
[`PAYMENT-ADAPTER-DIRECTION.md`](PAYMENT-ADAPTER-DIRECTION.md).

This choice authorizes neither P2 implementation nor P3 sandbox configuration.
It authorizes only a no-network, no-secret design fixture to be proposed next.

## Why Stripe Checkout First

| Requirement | Decision evidence |
| --- | --- |
| Hosted payment entry | Stripe documents a full-page hosted Checkout UI in which customers enter payment details on Stripe's payment page. |
| Cards without a monthly platform fee | Stripe's standard U.S. pricing page states no setup or monthly fees and lists 2.9% + 30¢ for a successful domestic-card transaction. |
| A narrow first integration | Checkout Sessions gives the adapter one server-created hosted session rather than RED-CMS-hosted card fields. |
| Server-verified payment state | Stripe documents webhook signature verification using the raw event payload, the `Stripe-Signature` header, and an endpoint-specific secret; its timestamp is part of the signed payload. |
| Provider neutrality | Stripe-specific session and event names stay inside this optional adapter. Store Lite receives only the normalized P0 checkout and verified-event facts. |

The fee and product statements above were checked on 2026-08-15 and must be
verified again before a client opens an account or P3 uses test credentials.
They are processing costs, not a promise of universal availability, final
pricing, payout timing, tax treatment, or merchant approval.

## Alternatives Deliberately Deferred

| Provider or method | P1 outcome | Reason |
| --- | --- | --- |
| PayPal Checkout | Defer as a separate future wallet adapter | It remains valuable for clients whose customers expect PayPal or Venmo. Its current U.S. merchant pricing differs by product and payment method, so it should not be hidden behind a Stripe-shaped contract. |
| Square | Defer | It is a plausible future option for a client that needs a shared online and in-person/POS operation. The current Store Lite demo has no POS requirement. |
| Zelle, Venmo transfer, Nequi, or manual bank transfer | Not a first hosted-card adapter | These are client- and country-specific payment operations with different confirmation and reconciliation needs. They must not bypass server-verified order-state rules. |

A later PayPal adapter may offer PayPal and Venmo according to that client's
approved current terms. It must still pass the same P0 event, replay, refund,
reversal, secret, webhook, and client-isolation requirements. It is not a
fallback branch inside the Stripe adapter.

## Stripe-Specific P2 Design Constraints

The future P2 fixture must prove the Stripe adapter contract without network
access, a Stripe SDK, keys, a provider account, or a payment:

- accept only a current immutable Store Lite order in the selected installation;
- derive amount and USD currency from the server-side order snapshot;
- accept only one fixed `payment` mode and the hosted full-page checkout shape;
- create a closed adapter request and validate only an opaque Checkout Session
  reference plus an approved HTTPS redirect URL;
- allow a browser return to display status only, never to write payment or
  fulfillment state;
- normalize only predeclared provider outcomes into the P0 event vocabulary;
- require a raw-body signature-verification boundary before parsed webhook
  fields or order lookup are available in P3; and
- refuse amount, currency, order, provider, timestamp, signature, duplicate,
  replay, refund, reversal, configuration, and client-boundary mismatches with
  no partial transition.

P2 completed this fixture-level contract without hard-coding a secret, sending
a request to Stripe, adding a public route, persisting a payment attempt, or
altering the demo. Its exact boundary and refusal coverage are recorded in
[`PAYMENT-ADAPTER-P2-FIXTURE.md`](PAYMENT-ADAPTER-P2-FIXTURE.md). It remains a
fixture, not an integration.

## Later Sandbox And Deployment Gates

P3 can be proposed only after P2 passes. It will use Stripe test-mode
credentials stored as server-local client-specific secret references, one
disposable client database, and Stripe's signed webhook contract. Test events
must be verified against the raw body and endpoint-specific secret before
parsing. No live API key, live webhook secret, live transaction, or retained
client database may be used.

P4 remains a separate client deployment review. It requires the client's
merchant approval, current terms/fees, public HTTPS webhook ingress, secret
rotation, exact outbound-host configuration, order operations, retention,
backup, rollback, and an explicit production authorization.

## Sources Checked For This Decision

- [Stripe pricing](https://stripe.com/pricing)
- [Stripe Checkout documentation](https://docs.stripe.com/payments/checkout)
- [Stripe webhook verification documentation](https://docs.stripe.com/webhooks)
- [PayPal U.S. merchant fees](https://www.paypal.com/us/business/paypal-business-fees)
- [PayPal webhook verification documentation](https://developer.paypal.com/api/rest/webhooks/rest/)
- [Square U.S. fees](https://squareup.com/help/us/en/article/5068-what-are-square-s-fees)

## Explicit Exclusions

P1 does not add a Stripe dependency to RED-CMS, a core payment API, a Store
Lite payment table, a Store Lite checkout control, a provider account, a
merchant application, a secret, a network call, a webhook path, a provider
library, a charge, a refund, a payout, a customer payment method, or any
change to the hosted demo. It does not choose a provider for Events,
Appointments, Donations, Restaurant Ordering, Member Access, or a
non-U.S./non-USD client.
