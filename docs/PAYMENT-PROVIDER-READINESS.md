# Store Lite Payment Provider Readiness

Status date: 2026-08-30.

Store Lite owns provider-neutral catalog, order, payment-event, and subscription
state. Each external provider remains a separately installed adapter with its
own client-local settings, secret references, event endpoint, replay evidence,
and release gate.

## Current matrix

| Provider track | Package | Current verified boundary | Not yet release-ready |
| --- | --- | --- | --- |
| Stripe Checkout | `redcms.store-lite-stripe-checkout` `0.1.20` candidate | Adapter `0.1.18` completed hosted subscription activation/cancellation in the isolated Stripe Sandbox. The `0.1.20` candidate binds one Store Lite offer to an existing non-live Stripe Product and Price and permits only that exact request through the bounded transport, with offline contract and sealed-operation verification. | One client-specific catalog-Price Sandbox Checkout and signed lifecycle, general live-mode release, client-owned production credentials, refunds/disputes, and ACH delayed-payment lifecycle. |
| Wompi / Nequi | `redcms.store-lite-wompi` `0.1.5` | Nequi/COP contract, package integrity, current Store Lite `0.1.50` discovery, disabled installation, atomic enablement, sealed transport, event verification, and a current 21-assertion two-client isolation rehearsal are verified offline. | The first merchant-account Sandbox request, Nequi transaction, signed event, and production activation require authorized Wompi account access. |
| PayPal Checkout | separate adapter reserved; no package yet | Provider-neutral Store Lite payment taxonomy and the core integration-adapter model already reserve PayPal. | Package manifest/profile, Orders v2 create/capture, server-side OAuth, button handoff, webhook verification/replay, refunds, subscriptions, Sandbox lifecycle, and client deployment. |

## Stripe bank payments

For a US Stripe merchant charging USD customers, ACH Direct Debit is a
reasonable extension because Stripe Checkout supports `us_bank_account` in
payment and subscription mode. It is not a switch we should label complete in
the current adapter: ACH is delayed and non-guaranteed, commonly taking several
business days, so Store Lite must keep the order or subscription pending until
the signed asynchronous success event arrives and must handle failure and
dispute events.

The engineering sequence is:

1. add an explicit per-client payment-method policy to the Stripe adapter;
2. preserve cards as the default and allow `us_bank_account` only for eligible
   US/USD client profiles;
3. extend the receipt/projector allowlist for delayed-payment success, failure,
   expiry, and dispute states;
4. prove that return-page navigation never marks an order paid;
5. run one Stripe Sandbox ACH success and one failure lifecycle; and
6. keep live ACH disabled until the client confirms account eligibility,
   mandate wording, fulfillment delay, refund, and dispute policy.

Official references:

- [Stripe ACH Direct Debit](https://docs.stripe.com/payments/ach-direct-debit)
- [Accept ACH with Checkout](https://docs.stripe.com/payments/ach-direct-debit/accept-a-payment)
- [Stripe payment-method support](https://docs.stripe.com/payments/payment-methods/payment-method-support)

## Wompi, Nequi, and Colombian bank payments

Nequi is provided through Wompi, so it should remain one Wompi adapter rather
than a separate Nequi credential integration. Wompi also documents PSE,
Bancolombia transfer, and cards, but version `0.1.5` deliberately implements
only one-time Nequi in COP. Expanding the payment-method enum before the Nequi
Sandbox lifecycle passes would weaken the existing closed contract.

The current external blocker is merchant-account access to the Wompi Sandbox
public/private keys, integrity secret, and event secret. The account owner must
enter those values privately. Once access exists, the next bounded gate is one
read-only Sandbox merchant-contract request, followed by one simulated Nequi
transaction and its signed `transaction.updated` event. PSE and Bancolombia
transfer should follow as separate adapter versions after Nequi is stable.

Official references:

- [Wompi environments and keys](https://docs.wompi.co/docs/colombia/ambientes-y-llaves/)
- [Wompi payment methods](https://docs.wompi.co/docs/colombia/metodos-de-pago/)
- [Wompi events](https://docs.wompi.co/docs/colombia/eventos/)

## PayPal

PayPal should be the next new adapter repository. The first version should be
one-time Checkout only and use server-side Orders v2 create/capture plus a
provider-signed webhook receipt. It must not reuse the legacy
`paypal_response.php` PDT compatibility route, because that route predates the
Store Lite payment lifecycle and cannot provide the new adapter's installation,
replay, isolation, or subscription guarantees.

Recommended gates:

1. package identity, settings, outbound hosts, callback route, and exact Store
   Lite dependency;
2. pure create-order and capture response contracts with idempotency;
3. client-side PayPal button handoff backed by core-owned server endpoints;
4. raw webhook preservation, signature verification, replay ledger, capture,
   refund, reversal, and dispute projections;
5. disposable install/enable/disable and two-client isolation;
6. PayPal Sandbox one-time purchase acceptance; and
7. a later, separate subscription-plan lifecycle using the existing Store Lite
   subscription offer and entitlement model.

Official references:

- [PayPal Standard Checkout](https://developer.paypal.com/checkout/)
- [PayPal Checkout integration](https://developer.paypal.com/studio/checkout/standard/integrate)
- [PayPal webhooks](https://developer.paypal.com/api/rest/webhooks/rest/)
- [PayPal subscriptions](https://developer.paypal.com/platforms/subscriptions/overview/)
