# Store Lite Payment Provider Readiness

Status date: 2026-09-02.

Store Lite owns provider-neutral catalog, order, payment-event, and subscription
state. Each external provider remains a separately installed adapter with its
own client-local settings, secret references, event endpoint, replay evidence,
and release gate.

## Current matrix

| Provider track | Package | Current verified boundary | Not yet release-ready |
| --- | --- | --- | --- |
| Stripe Checkout | `redcms.store-lite-stripe-checkout` `0.1.20` | The configured $59/month catalog Price completed a hosted `demo.red-sphere.com` Sandbox lifecycle: Checkout creation returned 200; completed Checkout and paid invoice deliveries returned 200; Store Lite reached `active/active`; immediate cancellation returned 200 and reached `canceled/revoked`. | General live-mode release, client-owned production credentials, per-client Sandbox acceptance, refunds/disputes, and ACH delayed-payment lifecycle. |
| Wompi / Nequi | `redcms.store-lite-wompi` `0.1.5` | Nequi/COP contract, package integrity, current Store Lite `0.1.50` discovery, disabled installation, atomic enablement, sealed transport, event verification, and a current 21-assertion two-client isolation rehearsal are verified offline. | The first merchant-account Sandbox request, Nequi transaction, signed event, and production activation require authorized Wompi account access. |
| PayPal Checkout | `redcms.store-lite-paypal` `0.1.0` offline foundation | Package contracts pass 67 assertions; exact profile 39; registrar 10; non-routable ingress 13; disposable install 16; atomic enablement 17; two-client isolation 21. Orders v2 planning, approval redirects, verified paid-event projection/replay refusal, exact package/core surfaces, migrations, rollback, independent client lifecycle/load order, and cleanup are verified without provider contact. | Server-side OAuth and create/capture transports, button/endpoints, webhook verification transport, Store Lite payment mutation, refunds/reversals/disputes, Sandbox lifecycle, subscriptions, and client deployment. |

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

PayPal now has a separate `redcms.store-lite-paypal` `0.1.0` repository. Its
first offline foundation is one-time USD Checkout only and closes pure Orders
v2 create/capture and verified-event projection contracts. It must not reuse
the legacy `paypal_response.php` PDT compatibility route, because that route
predates the Store Lite payment lifecycle and cannot provide the new adapter's
installation, replay, isolation, or subscription guarantees.

Current gates:

1. **Complete offline:** package identity, settings, Sandbox API host, future
   callback route, exact Store Lite dependency, and integrity inventory;
2. **Complete offline:** pure create-order, approval-redirect, capture-response,
   and already-verified paid-event contracts with idempotency/replay evidence;
3. **Complete offline:** exact RED-CMS core profile adoption and registration-
   only package validation with no handler invocation or publication;
4. **Complete offline:** disposable disabled installation, exact migrations,
   non-routable ingress capture, forced rollback, and atomic enablement;
5. **Complete offline:** independent two-client enable/disable, lifecycle-lock,
   evidence-hash, setting/reference, and runtime-order isolation;
6. **Next:** server-side OAuth/create/capture transport and a PayPal button handoff
   backed by core-owned endpoints;
7. raw webhook preservation, signature verification, replay ledger, capture,
   refund, reversal, and dispute projections;
8. PayPal Sandbox one-time purchase acceptance; and
9. a later, separate subscription-plan lifecycle using the Store Lite
   subscription offer and entitlement model.

Official references:

- [PayPal REST authentication](https://developer.paypal.com/api/rest/authentication/)
- [PayPal Orders v2 integration](https://developer.paypal.com/api/rest/integration/orders-api/)
- [PayPal webhooks](https://developer.paypal.com/api/rest/webhooks/rest/)
- [PayPal webhook event names](https://developer.paypal.com/api/rest/webhooks/event-names/)
- [PayPal subscriptions](https://developer.paypal.com/platforms/subscriptions/overview/)
