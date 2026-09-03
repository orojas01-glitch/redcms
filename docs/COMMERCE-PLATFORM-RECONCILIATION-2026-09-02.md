# Red Sphere commerce platform reconciliation

Status date: 2026-09-03 (catalog/configurator snapshot verified 2026-09-02)  
Scope: local source, package, release, and acceptance reconciliation only

This record closes the platform work that must precede the separate
`commerce.red-sphere.com` implementation. It does not deploy a site, modify
HostGator, create DNS or TLS state, activate a public route, contact Stripe,
change Stripe Tax, alter a live Stripe object, or process a payment.

## Confirmed project decisions

- HostGator now has a website entry for `commerce.red-sphere.com`. It is an
  external destination only; this reconciliation did not inspect or change it.
- The commerce installation will have its own directory, Git history, database,
  database user, administrator, package state, media, settings, secrets,
  webhook destination, backups, manifest, and rollback point.
- The public seller name is **Red Sphere Design Studio Inc.**
- The existing configurator request/review action remains available for every
  configuration.
- `Help me choose` is an evaluation state and blocks **Pay now**.
- The messaging-number question must be explicit. If the client does not have
  a compatible number to connect, **Dedicated Messaging Number** is required.
- Intended expiry policy: seven days for a sales-assisted cart, 24 hours for a
  configurator-created cart, and 30 minutes for a Stripe Checkout Session.
- Customer-facing terms and conditions will be supplied later. Their absence
  blocks final copy approval and live readiness, not local foundation work.
- Automatic tax remains off. Head-office location, registrations, and product
  tax classification require qualified tax advice and separate approval.

## Reconciled release baseline

Client kit `0.1.12` now identifies RED-CMS core `5.1.1` and pins this exact
package set:

| Source | Version | Commit | Current evidence boundary |
| --- | --- | --- | --- |
| RED-CMS core | `5.1.1` | minimum `cee2062fe0d5d2b292169ea076c0e03bdf1173ae` | Current add-on/client-kit baseline with PayPal provider-only evidence reconciled; no commerce route added. |
| Store Lite | `0.1.51` | `57a948929142efb417285d2dbd76b5b3478b7738` | Legacy behavior regression-tested; new commerce review-cart foundation verified offline and in disposable MySQL. |
| Stripe adapter | `0.1.21` | `35a7b4bcb1dba1cf94e3d51ea50658b57ef09874` | Legacy Sandbox evidence retained as historical evidence; new multi-line commerce path verified offline and in disposable MySQL only. |
| Wompi adapter | `0.1.5` | `cc2ddd03ab54f663a089f7d059d802180e555d15` | Optional and account-blocked; the exact Store Lite `0.1.51` two-client pairing passed offline. |

The former kit label `5.1.0` was stale relative to the runtime constant and
5.1.1 maintenance documentation. The kit now declares `5.1.1` and tests that
the runtime constant and release notes agree. No release tag or shared branch
history was rewritten.

PayPal remains refused as a client-kit selection. Provider-only Sandbox order
and subscription objects are verified, but runtime transport, Store Lite
mutation, signed webhooks, and client deployment are incomplete.

## Store Lite 0.1.51 result

The package adds four separate InnoDB tables for commerce review carts, lines,
share-token hashes, and transition evidence. It does not alter the existing
storefront cart/order tables.

The new pure contracts:

- derive setup, recurring monthly, amount-due-today, and future-renewal totals
  from trusted server terms;
- explicitly keep tax `not_configured`;
- reject browser-owned totals and provider identifiers;
- accept only bounded lines and quantities;
- store only a SHA-256 of a 256-bit opaque share token;
- constrain `draft`, `shared`, `checkout_pending`, `paid`, `expired`,
  `canceled`, and `payment_failed`; and
- keep payment separate from onboarding, so `paid` starts onboarding as
  `pending` and never claims automatic technical provisioning.

No administrator screen, public route, persistence service, handoff endpoint,
or provider call is wired by this package release.

## Stripe adapter 0.1.21 result

The adapter adds:

- server-owned `rs_ai_...` lookup-key resolution with active Product/Price,
  catalog-family, offer, amount, currency, interval, and mode checks;
- one hosted `mode=subscription` Checkout contract that includes recurring
  monthly Prices and one-time setup Prices in the initial Checkout;
- a 30-minute Session expiry, deterministic idempotency, no
  `payment_method_types`, and no automatic tax;
- `StripeClient` construction pinned to API `2026-08-26.dahlia` and restricted
  test keys only;
- official-SDK signature verification with a five-minute tolerance;
- an eleven-event Checkout/invoice/subscription allowlist;
- paid-invoice authority instead of success-page authority; and
- separate hash-only Checkout-attempt and event-receipt tables.

`stripe/stripe-php` `21.3.1` is pinned in the package's Composer manifest and
lockfile. `vendor/` is intentionally absent; the isolated commerce build must
perform the locked installation and load its autoloader. The package's declared
webhook route remains intentionally non-operational until the commerce project
adds the database transaction and runtime controller.

## Acceptance evidence

- New Store Lite contracts: 65 focused assertions.
- Store Lite package foundation: 29 assertions.
- Store Lite migration: 79 assertions against a disposable database, including
  all 25 package tables and the four new commerce tables.
- Existing Store Lite catalog/cart persistence: 129 assertions; subscription
  and payment persistence also passed; disposable databases/grants were
  removed and the configured database remained unchanged.
- Store Lite `0.1.51` plus Wompi `0.1.5`: 21 two-client isolation assertions
  passed offline; both disposable databases/grants and the staged project were
  removed, and the configured database remained unchanged.
- Complete Stripe adapter source suite: all legacy and new tests passed.
- New Stripe commerce contracts: 78 focused assertions.
- Stripe commerce migration: core SQL guard accepted it; a disposable database
  created exactly two InnoDB tables with the required unique indexes, then was
  removed with the configured database table count unchanged.
- Composer installer checksum matched the official published checksum;
  Composer locked `stripe/stripe-php` `v21.3.1` and reported no security
  advisories. The exact-version warning is intentional for a release artifact.
- Secret-pattern review found no credential values. Dedicated scanners were not
  installed locally, so their absence remains a recorded tooling limitation.

## Verified external/read-only context

The live Red Sphere catalog review found 15 active AI Assistant Products and 21
active Prices (12 setup and 9 monthly), with `rs_ai_...` lookup keys and
`catalog_family=red_sphere_ai_assistant`. Product tax codes were unset, Price
tax behavior was unspecified, and automatic Stripe Tax was off. No active
Payment Link was found for this catalog. Those observations were read-only and
must be refreshed in the isolated project before any mapping is accepted.

The live configurator assets matched the inspected local React source and the
existing request endpoint, CSRF flow, messaging selection, capability IDs,
Additional Language quantity, contact fields, comments, and no-payment copy
were preserved. No configurator or production file was changed here.

## Messaging choice contract for the separate project

The client must make one required choice before checkout eligibility is tested:

1. **Use my compatible existing number** — requires an explicit ownership and
   provider-compatibility acknowledgement. If compatibility is unresolved,
   Pay now is unavailable and request review remains available.
2. **Add a dedicated messaging number** — includes the existing Dedicated
   Messaging Number catalog item and its authoritative setup/monthly Prices.
   This is mandatory when the client says they do not have a number to connect.
3. **Help me choose** — creates an evaluation-required reason, withholds or
   disables Pay now, explains why, and leaves request review available.

The browser may submit only the choice and selected item IDs/quantities. It may
not decide eligibility, add/remove the dedicated-number charge silently, or
supply amounts, Price IDs, lookup keys, descriptions, or totals.

## Ready boundary

The platform is ready to start a separate, isolated local project for:

- clean RED-CMS `5.1.1` installation;
- Store Lite `0.1.51` and Stripe adapter `0.1.21` installation in disabled
  state;
- a dedicated local database/user and rollback snapshot;
- Red Sphere theme implementation;
- sales-assisted cart administration and opaque review links;
- authenticated configurator handoff contracts; and
- Stripe test-mode catalog mapping, Checkout, and webhook integration.

It is not ready for HostGator upload, DNS/TLS changes, a public production
route, live keys, live Checkout, live webhooks, live catalog mutation, Stripe
Tax, final legal copy, or a real payment. Each remains a later approval gate.
