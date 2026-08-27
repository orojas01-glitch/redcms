# Subscription Webhook Endpoint

Status: production-shaped and locally verified, but default-disabled and not
deployed or activated.

RED-CMS now reserves exactly:

`/addons/redcms/store-lite-stripe-checkout/provider-events`

The front controller enters the endpoint only when both server-local settings
are explicit:

- `SUBSCRIPTION_WEBHOOK_ENDPOINT_ENABLED` is `true` or `1`; and
- `SUBSCRIPTION_WEBHOOK_MODE` is `sandbox`.

Environment aliases use the `RED_` prefix. No live mode is accepted. With the
gate disabled, the exact path returns a generic `404` before database access,
body I/O, runtime loading, or secret resolution.

## Transport boundary

The enabled endpoint requires a query-free direct HTTPS `POST`, exact
`application/json`, an exact body length from 2 through 262,144 bytes, and one
printable `Stripe-Signature` projection. Transfer encoding and content encoding
are refused. Some supported servers expose both canonical `CONTENT_` values and
`HTTP_CONTENT_` aliases; aliases are accepted only when byte-for-byte identical
to the canonical value. Preflight precedes database access and the single
`php://input` read.

## Runtime and secret scope

Runtime assembly pins RED-CMS `5.1.0`, Store Lite `0.1.50`, and Stripe adapter
`0.1.15`. Both packages must be enabled/current with exact manifest and
inventory evidence. Core registers only that dependency pair and resolves only
the configured `stripe.webhook-secret` reference. The Stripe API key is outside
this request context.

The configured reference must be declared through the existing server-local
add-on secret-reference inventory, and its value must remain in the ignored
server configuration or environment value map. Neither value belongs in Git,
the database, logs, evidence, or responses.

## Responses

- accepted, recovered, replayed, and terminally refused deliveries: `200`
  with `{"ok":true}`;
- invalid signature: `400` with a stable generic error;
- wrong method: `405` plus `Allow: POST`;
- restartable delivery/runtime failure: `500`; and
- front-controller/database failure after the route is enabled: `503`.

Every response is JSON, `no-store`, `nosniff`, and exact-length. No event hash,
intent, customer data, request body, signature, or secret reaches the public
body.

## Verification

- 24 pure endpoint assertions cover exact targeting, direct-HTTPS facts,
  canonical alias agreement, body/header bounds, default-disabled behavior,
  response mapping, emitter rules, and pre-I/O ordering.
- The 16-assertion disposable launch rehearsal resolves only a process-local
  synthetic webhook secret, runs a real renewal plus replay through installed
  package/runtime/database boundaries, restores the environment, and removes
  the database, grant, and staged project while the primary remains unchanged.
- The disposable local TLS FrankenPHP proof sends actual `php://input`
  requests and passes `valid:200 invalid:400 get:405 query:404
  plaintext:refused`, then removes its server, certificate, secret, logs,
  responses, and staged root.

This evidence is not deployment or Stripe activation. `demo.red-sphere.com`
remains unchanged, and no real endpoint secret or provider event was used.
