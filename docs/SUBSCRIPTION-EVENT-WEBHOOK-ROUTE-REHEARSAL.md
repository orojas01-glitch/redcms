# Subscription Event Webhook Route Rehearsal

Status: complete as a non-operational internal rehearsal for Stripe adapter
`0.1.14` and Store Lite `0.1.50`.

The rehearsal passes the exact manifest declaration
`redcms.store-lite-stripe-checkout/provider-events` through the core webhook
request boundary. That boundary requires public scope, `POST`,
`server-signature`, no CSRF, exact package ownership, and one request-local
secret setting. The handler accepts only `stripe.webhook-secret`; adding the
Stripe API-key setting prevents invocation.

Inside the handler, the synthetic endpoint secret is available only to the
adapter signature verifier and is cleared immediately after the restartable
delivery coordinator returns. The raw body then crosses the bounded adapter
decoder/projector path without entering the normal 16 KB typed payload. The
result contains only acknowledgement state, event type, hashes, and explicit
false external-effect flags.

Response mapping is closed:

- applied, recovered, and replayed receipts return a bounded `200`
  acknowledgement;
- a valid projected event whose lifecycle transition is terminally refused is
  journaled `refused` and acknowledged with `200`;
- invalid signatures return `400`; and
- restartable journal or projection failures return `500`.

The focused 26-assertion fixture covers exact manifest ownership, secret-scope
refusal, bad signatures, application, recovery, replay, terminal refusal,
private-data exclusion, and absence from `index.php` and runtime bootstrap.
The 15-assertion disposable database rehearsal forces receipt-completion
failure after Store Lite commits, receives `500`, retries through the same
internal boundary, completes the receipt with no second lifecycle write, then
replays it. Cleanup proves `database:0 grant:0 staged-project:0
primary:unchanged`.

The adapter registrar still exposes only its throwing non-operational
placeholder. No server request reader, public dispatcher, endpoint activation,
configured secret provisioning, network request, Stripe contact, payment,
browser action, live mode, or demo deployment is added.
