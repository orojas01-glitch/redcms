# Add-on Webhook Request Boundary

RED-CMS 5.1 now contains an unlinked internal request boundary for future
server-signed add-on webhooks. It exists separately from the ordinary typed
service and adapter payload contract so webhook support does not raise that
contract's 16 KB limit.

The boundary accepts only server-supplied values:

- one manifest-declared public route with `POST`, `server-signature`, and no
  CSRF contract;
- an untouched UTF-8 raw body between 2 and 262,144 bytes;
- one printable signature header up to 4,096 bytes;
- one bounded server receipt timestamp; and
- exactly one request-local secret setting for the owning package.

The package handler receives a dedicated request object. Its result remains
subject to the normal 16 KB data limit and is rejected if it contains the raw
body, signature header, or any scoped secret value. Output-buffer changes,
exceptions, invalid result types, wrong ownership, expanded secret scope, and
manifest drift are contained.

The 13-assertion fixture joins this core boundary to the separately installed
Stripe adapter `0.1.12` signature-envelope verifier using a 20 KB synthetic
event and process-local synthetic signing secret. It also proves the ordinary
adapter request still rejects a 20 KB payload.

The later subscription delivery fixture now joins the exact adapter `0.1.14`
manifest declaration and one-secret request scope to the restartable event
coordinator. Applied, recovered, replayed, and terminally refused deliveries
return only bounded acknowledgement and hash evidence. Invalid signatures use
`400`; restartable journal/projection failures use `500`. Expanded API-key
scope is refused before handler invocation.

This helper is absent from `index.php`, runtime bootstrap, and every public
route dispatcher. It reads no request globals, resolves no configured secret,
emits no response, and performs no network, Stripe, database, payment, browser,
or deployment action.

## Current boundary

The non-operational package-handler rehearsal is complete. The actual adapter
registrar still retains its throwing placeholder, and neither `index.php` nor
runtime bootstrap references the composed handler. Linking a real request
reader, activating an endpoint, provisioning a configured webhook secret, or
deploying to a client each remains separately authorized work.
