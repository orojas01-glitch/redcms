# PayPal P5 Sealed Transport Contract

Status date: 2026-09-02.

## Outcome

External adapter `redcms.store-lite-paypal` `0.2.0` adds credential-transient
OAuth, Orders v2 create/capture, and webhook-verification orchestration behind
a closed transport interface. Its current implementation is a deterministic,
ordered, one-use double with no network primitive or provider contact.

The registered adapter remains non-operational and continues to return
`provider_transport_disabled` for every provider operation.

## Contract

- OAuth uses Basic client credentials, form body
  `grant_type=client_credentials`, and `/v1/oauth2/token`.
- Create uses bearer authorization, `/v2/checkout/orders`, the already reviewed
  immutable request body, and a deterministic `PayPal-Request-Id` derived from
  Store Lite idempotency evidence.
- Capture uses bearer authorization, the exact provider order capture path,
  `{}` body, and separate capture idempotency evidence.
- Webhook verification uses the five captured PayPal transmission fields,
  client-local webhook ID, and original raw event bytes embedded without
  re-serialization in `/v1/notifications/verify-webhook-signature`.
- Only exact HTTP/content-type/status and bounded provider projections are
  accepted.

## Containment

Transport evidence stores only operation names and request hashes after
credential/token redaction. Coordinator results contain neither client ID,
client secret, access token, raw webhook event, nor signature value. Foreign
certificate hosts fail before transport. Returned effect flags keep network,
provider contact, payment, webhook response, browser navigation, Store Lite
mutation, and retry authorization false.

## Evidence and next gate

The new sealed-transport test passes 13 assertions. The complete package suite
passes 83 assertions. This is not an end-to-end or provider-contact test.

Next is a real bounded Sandbox transport implementing the same interface with
TLS verification, connect/total timeouts, response-size limits, strict content
type/status handling, no redirect following, and contained error projection.
It must remain unreachable from the registered adapter until owner-authorized
Sandbox credentials and a one-attempt command gate are available.

Official contracts:

- <https://developer.paypal.com/api/rest/authentication/>
- <https://developer.paypal.com/api/rest/integration/orders-api/>
- <https://developer.paypal.com/api/rest/webhooks/rest/>
