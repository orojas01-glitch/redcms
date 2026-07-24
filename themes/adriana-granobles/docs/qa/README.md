# Adriana Granobles theme QA

Verified locally on 2026-07-19 before activation. These artifacts exercise the
theme package only; they do not publish content, write to the database, or make
`adriana-granobles` the active theme.

## Isolated fixture preview

The fixture render is script-free, self-contained, and deterministic:

- 134,357 bytes
- SHA-256 `07812b5ed226e03c78e7be4f0db9f68002f110e19e7120e20746a8a814a6b800`
- zero database, session, or live-runtime reads/writes
- one `main`, one visible `h1`, no horizontal overflow
- no scripts, external stylesheet links, external requests, or broken images
- preview form has no method or action and retains its inert preview button

Evidence:

- `fixture-desktop.png`
- `fixture-mobile.png`
- `fixture-reduced-motion.png`

## Production-context harness

`production-harness.html` was generated through the package's production PHP
document, region, layout, and component views with synthetic CMS-prepared
contexts. It is not a public page and is not declared by `theme.json`.

The browser pass covered 1440 px desktop, 390 px mobile, and reduced motion:

- one `main`, one visible `h1`, and no horizontal overflow
- no console errors, failed requests, or external resources
- mouse and keyboard hero navigation, with exactly one active slide
- mobile menu and nested three-level navigation open successfully
- reduced-motion content remains visible with no transform
- the synthetic `#contact` submit event is not prevented, confirming the theme
  does not intercept RED-CMS operational form behavior

The harness deliberately uses two existing local Article images as hero
stand-ins. It does not assert migrated editorial content or live page parity.

Evidence:

- `production-desktop.png`
- `production-mobile-menu.png`
- `production-reduced-motion.png`

## RED-CMS regression status

The manifest, production-candidate, compatibility-preflight, PHP lint, JavaScript
syntax, theme contracts, layout extensibility, chooser inventory, activation,
active-theme CSS, readiness, region-context, route-fallback, and migration-status
checks pass. The full disposable `scripts/dev-acceptance.sh` lifecycle also passes
and cleans up its temporary server, database, grants, records, and uploads.

The approved `starter-reference` Website CSS edit and the documented 1.2.0
manifest are now reflected in their reviewed sentinels. All isolated starter
preview suites and the public Form-operation boundary pass alongside the Adriana
fixture, production harness, candidate validation, compatibility coverage, and
full RED-CMS acceptance run.

## Remaining activation gate

Before selecting this theme for a real site, clone the target database into a
disposable local runtime, run the compatibility preflight, render representative
assigned layouts with actual CMS content, and compare those routes to the 28-page
source inventory. Activation and content migration are intentionally outside this
package-building batch.
