# AI Assistant Subscription Catalog Readiness Kit

Prepared: 2026-08-30 America/New_York

This credential-free kit prepares the existing Stripe Sandbox catalog Product
for a future Store Lite subscription demonstration. It does not alter Stripe,
`demo.red-sphere.com`, `red-sphere.com`, any database, or any secret.

## Target

- Store Lite offer: `ai-assistant-foundation-monthly`
- Stripe Sandbox Product: `prod_VAdppdm2hxfXT7`
- Stripe Sandbox recurring Price: `price_1UAIXQPzjg2rInjnX5CypQNL`
- Amount: USD 59.00 per month
- Intended reference host: `demo.red-sphere.com`

## Contents

- `stripe-sandbox-facts.json` — observed non-secret Stripe configuration.
- `store-lite-offer.json` — provider-neutral Product and offer definitions.
- `config.local.catalog-binding.php` — merge-only local configuration fragment.
- `ACTIVATION-CHECKLIST.md` — the future controlled implementation sequence.
- `ROLLBACK.md` — the restore and disable path.

The deployable source candidates live on the rollback branches:

- core: `codex/subscription-catalog-price-runtime`
- Stripe adapter: `codex/stripe-catalog-price-binding`

Store Lite remains version `0.1.50`. The prepared adapter candidate is
`0.1.19`; the currently hosted adapter remains unchanged until a separately
approved release and deployment.
