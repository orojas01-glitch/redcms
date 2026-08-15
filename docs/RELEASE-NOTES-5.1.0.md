# RED-CMS 5.1.0 Release Notes

Status: release candidate verified on 2026-08-15. The formal Git tag and
GitHub release have not been created yet.

## Release Summary

RED-CMS 5.1.0 turns the Version 5.0 Bonsai foundation into a portable CMS
core that can support separately installed, per-client add-ons. It also adds
generic per-page SEO metadata, structured-data controls, safer package
lifecycle boundaries, and updated clean-core administrator instructions.

The starter remains a CMS, not a preconfigured store. Store Lite is the first
completed proof of the add-on model, but its package code, tables, products,
carts, orders, settings, secrets, media, permissions, and enabled state are not
included in this repository.

## Highlights

- Per-page SEO title, description, canonical, Open Graph, X/Twitter, and
  constrained typed JSON-LD overrides with generated compatibility fallbacks.
- Non-executing add-on manifest and inventory validation before package code is
  trusted or loaded.
- Owner-authorized install, upgrade/recovery, enable, disable, and re-enable
  boundaries with deterministic plans, checksum evidence, scoped migrations,
  and value-free audit records.
- Per-client package registry, settings, permissions, migration ledger,
  component revision history, and public-mutation evidence.
- Fail-closed runtime registration for declared components, typed services,
  administrator tools, assets, and exact public routes.
- Core-owned component editing, creation, placement, revision, restore, and
  deletion foundations with fresh permissions and transactional rollback.
- Core-owned anonymous public-mutation controls for subject cookies, CSRF,
  rate limits, idempotency, fixed responses, and supported-server ingress.
- Updated RED-CMS 5.1 Basic instructions and portable starter imagery.
- Portable Home/theme preview checks no longer depend on an omitted legacy
  branded Gallery image; tests use a tracked repository-confined 5.1 asset,
  while live preview still fails closed when client media is absent.
- Administrator theme-style isolation and other compatibility fixes accumulated
  after Version 5.0 Bonsai.

## Store Lite Proof

The separately distributed Store Lite 0.1.31 package proves that one RED-CMS
installation can opt into a catalog, bounded product variants, cart, guest
checkout, pickup or delivery, pay on receipt, and product/order tools without
adding those capabilities to every client. Its completed hosted proof is
documented in
[`STORE-LITE-DEMO-CLOSEOUT-20260815.md`](STORE-LITE-DEMO-CLOSEOUT-20260815.md).

Store Lite is optional and client-local. Hosted PayPal/card adapters, Events
Calendar, Appointments, Donations, Restaurant Ordering, and Member Access are
not part of the 5.1.0 clean-core release.

## Installation And Upgrade

- New installations start with an empty client-specific database, import
  `db-structure.sql`, create an ignored client-local configuration, and apply
  all pending migrations.
- Existing installations must back up and verify both database and media
  before migration. Never import `db-structure.sql` over an existing site.
- Each installation keeps its own code deployment, database, add-on registry,
  settings, secrets, media, permissions, backups, and rollback point.
- Add-on packages are deployed and migrated separately for only the client
  installation that approved them.

See [`../INSTALL.md`](../INSTALL.md),
[`DATABASE-MIGRATIONS.md`](DATABASE-MIGRATIONS.md), and
[`SECURITY.md`](SECURITY.md) before deployment.

## Verified Release Gate

The 2026-08-15 release-candidate run used a fresh temporary current-schema
baseline and a second uniquely named acceptance database. It verified:

- the 32-table clean installer and all 46 immutable migrations;
- a no-op migration rerun with zero pending and zero drifted files;
- the normalized 35-table InnoDB/utf8mb4 schema with SHA-256
  `0e75f9590094e9875c8df2aa83a8fe5646f2aad6931ed168a7ee935984f9f313`;
- clean-starter, SEO, add-on trust/lifecycle/runtime, authorization, settings,
  component, public-mutation, theme, authentication, CRUD/upload, Move Content,
  Section archive, and forced-rollback acceptance;
- the five canonical clean-install routes with a clean isolated runtime log;
  and
- exact cleanup: zero temporary databases and zero temporary grants.

Focused theme verification also passed the 43-assertion Home preview,
50-assertion activation-readiness report, 82-assertion Instructions preview,
and 276-assertion theme contract.

The retained historical local starter remained unchanged at 20 tables and
four Articles. No hosted installation or client database was used by this
release-candidate run.

## Formal Release Gate

Before calling 5.1.0 formally released:

1. merge the reviewed release-candidate documentation and acceptance-marker
   correction;
2. confirm `main` is clean and GitHub checks pass;
3. create the signed or annotated `v5.1.0` tag at the reviewed merge commit;
4. create the GitHub 5.1.0 release from these notes; and
5. independently verify the published tag and release assets.
