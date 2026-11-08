# Wompi Webhook Endpoint

Status: production-shaped and locally verified, but default-disabled and not
deployed or activated. Sandbox only.

RED-CMS now reserves exactly:

`/addons/redcms/store-lite-wompi/provider-events`

It follows the same pattern as the Stripe
[subscription webhook endpoint](SUBSCRIPTION-WEBHOOK-ENDPOINT.md). The front
controller enters it only when both server-local settings are explicit:

- `WOMPI_WEBHOOK_ENDPOINT_ENABLED` is `true` or `1`; and
- `WOMPI_WEBHOOK_MODE` is `sandbox`.

Environment aliases use the `RED_` prefix. No production mode exists yet.
With the gate disabled, the exact path returns a generic `404` before database
access, body I/O, runtime loading, or secret resolution.

## Transport boundary

The enabled endpoint requires a query-free direct HTTPS `POST`,
`application/json` (optionally `charset=utf-8`), and an `X-Event-Checksum`
header of 64 hex characters. A declared body length must be between 1 and
65,536 bytes; a chunked body is read with a 65,537-byte bound and refused above
65,536. Conflicting length/transfer metadata and every content encoding are
refused. These checks run before the database is opened.

## Decision

Runtime assembly pins RED-CMS `5.1.0`, Store Lite `0.1.53` and Wompi adapter
`0.4.0`. Both packages must be enabled/current with exact manifest and
inventory evidence. Only the `wompi.event-secret` reference is resolved; the
private and integrity keys are outside this request.

Inside the registered webhook boundary, the Wompi package's W3 coordinator:

1. verifies the event checksum with the events secret (and the header, when
   present) and the event age;
2. confirms it with one bounded `GET https://sandbox.wompi.co/v1/transactions/{id}`
   (package W4 transport, no credential);
3. reads the Store Lite order with `commerce.orders/payment.order.read` and
   reconciles reference, amount, currency and payment method; and
4. applies a final status with `commerce.orders/payment.event.apply`.

Store Lite's own event-evidence history makes redelivery idempotent, so the
endpoint keeps no separate replay journal.

## Responses

- applied, replayed, unchanged, pending, unknown order and permanently
  refused events: `200` with `{"ok":true}`;
- a forged or malformed event: `400`;
- wrong method: `405` plus `Allow: POST`;
- lookup failure, lookup disagreement, order read failure, storage failure or
  a missing/invalid secret: `500`, so Wompi retries (about 30 min, 3 h and
  24 h later); and
- front-controller/database failure after the route is enabled: `503`.

Every response is JSON, `no-store`, `nosniff` and exact-length. No event,
transaction id, customer data, checksum or secret reaches the public body.

## Turning it on in Sandbox (operator steps, not done)

These steps change a real server and contact Wompi, so each needs Oscar's
explicit go-ahead:

1. Install and enable Store Lite `0.1.53` and Wompi `0.4.0`.
2. Declare the `wompi.event-secret` reference in the server-local add-on
   secret-reference inventory and put the Sandbox events secret
   (`test_events_…`) only in the ignored server configuration or environment.
3. Set `RED_WOMPI_WEBHOOK_ENDPOINT_ENABLED=1` and
   `RED_WOMPI_WEBHOOK_MODE=sandbox`.
4. In the Wompi Sandbox dashboard, set the events URL to
   `https://<site>/addons/redcms/store-lite-wompi/provider-events`.

## Verification

`scripts/addon-wompi-webhook-endpoint-self-test.php` (44 assertions, 45 with
`RED_WOMPI_PACKAGE_ROOT` pointing at the Wompi package) covers exact
targeting, default-disabled and sandbox-only gates, transport refusals,
capture, response mapping, secret scope, the Store Lite read/apply callables,
coordinator status mapping, and gate-before-database ordering in `index.php`.
With the package present it also runs a signed synthetic `APPROVED` event
through the real coordinator to an applied `paid` result.

Not yet verified: a disposable database rehearsal with both packages enabled,
and a real Sandbox delivery. No endpoint activation, secret provisioning,
Wompi contact, payment or deployment occurred.
