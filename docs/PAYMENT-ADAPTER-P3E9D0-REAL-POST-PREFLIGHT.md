# P3E-9D0 Real Sandbox Checkout POST Preflight

Status: complete as a pure non-executing core contract. This gate defines one
future Stripe Sandbox Checkout Session create request. It opens no database,
resolves no key, and performs no network request.

The preflight accepts only exact adapter `0.1.5` P3E-9B synthetic-plan evidence
and the mutation-aware restricted-test-write profile. It produces:

- `POST https://api.stripe.com/v1/checkout/sessions`;
- `application/x-www-form-urlencoded` form fields;
- `mode=payment`, exact success/cancel URLs, bounded `expires_at`, and exact
  order reference;
- deterministic USD line items with integer minor-unit amounts and quantities;
- only order-snapshot and input hashes in metadata;
- one `redcms-checkout-<sha256>` idempotency key; and
- one canonical request SHA-256.

Credential value, authorization header, response body/header, network,
provider contact/mutation, real Checkout creation, payment, webhook, browser,
Store Lite mutation, retry, live mode, and client deployment remain false.
Read-only profiles and any changed input are refused.

Current Stripe documentation confirms that Checkout Session creation is
`POST /v1/checkout/sessions`, custom expiry is 30 minutes through 24 hours,
all API v1 POST requests accept idempotency keys up to 255 characters, and
restricted keys should follow least privilege:

- <https://docs.stripe.com/api/checkout/sessions/create>
- <https://docs.stripe.com/api/idempotent_requests>
- <https://docs.stripe.com/keys-best-practices>

`scripts/addon-sandbox-checkout-real-post-preflight-self-test.php` passes 25
pure assertions and scans out credential, network, request-global, shell,
secret-resolution, package-registration, and adapter-invocation primitives.

P3E-9D1 is complete in canonical-hash-compatible external adapter `0.1.7`.
P3E-9D2 is complete as core response containment plus non-persistent
real-operation start/result identities. P3E-9D3 must add and rehearse a
one-shot command. P3E-9D4 remains the separately approved single real Sandbox
POST.
