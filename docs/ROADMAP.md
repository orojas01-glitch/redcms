# RED-CMS Roadmap

## Version 5.0 — Bonsai

Milestones 1 through 5 are complete on `main` through pull request #2.
Version 5.0 delivers the compatibility-first PHP/MySQL modernization,
administrator security and transaction boundaries, the polished authoring
workspace, reusable standard themes, visual layouts and Layout Builder,
drag-and-drop placement, content history, structured navigation, media tools,
and guarded acceptance testing.

The GitHub distribution is a clean starter installation. Client themes,
databases, and media are separate deliverables.

## Version 5.1 Direction

Planned work includes:

- Per-page SEO metadata compatibility for the Adriana launch
- Member Access / Protected Content for private Sections and account lifecycle
- Payment-assisted access, including regional provider integrations
- Expanded roles and permissions
- Draft, review, approval, and publish workflow
- Notifications and reminders
- Content ownership and change attribution
- Optional installable tools
- Social publishing APIs
- Optional first-login guided tour

These items are product direction, not active Version 5.0 features. Each
requires its own security, data-migration, privacy, accessibility, and rollback
design before implementation.

### Launch Priority: Per-Page SEO Metadata

Per-page SEO metadata compatibility is the first Version 5.1 implementation
milestone and a launch dependency for the isolated 28-page Adriana migration.
The work must provide nullable SEO overrides, safe generated fallbacks,
canonical URLs, complete Open Graph and X/Twitter metadata, typed JSON-LD,
migration reporting, and compatibility-preserving public rendering.

The generic RED-CMS acceptance fixtures and the client-isolated Adriana
28-route verification have passed. The client QA applied 28 SEO records without
missing owners or conflicts, reproduced an unchanged idempotent dry run, passed
56 desktop/mobile route checks and 28 legacy redirects, and matched the exact
28-URL sitemap. All 28 unauthenticated public renders also passed the hosted
Schema.org Markup Validator with zero errors and zero warnings. Production
deployment remains separate.

The 87 explicitly reported JSON-LD property occurrences are now classified:
84 should be emitted through generated relationships or constrained typed
fields, one redundant homepage self-reference should be normalized away, and
the visitor-invisible Course code and rating should remain explicit
exclusions. The constrained generic fields pass clean-starter acceptance, and
the fresh isolated 28-route Adriana JSON-LD QA and hosted Schema.org validation
also pass. Production deployment remains before this priority can close. See
[`SEO-METADATA-COMPATIBILITY-REPORT.md`](SEO-METADATA-COMPATIBILITY-REPORT.md)
for the confirmed cause, proposed model, migration requirements, and
acceptance criteria, and
[`SEO-JSONLD-LAUNCH-DECISION.md`](SEO-JSONLD-LAUNCH-DECISION.md) for the
property classification and implementation boundary.

### Adaptable Add-On Platform

RED-CMS should support separately installed client capabilities rather than
bundle every business vertical into the core. The following packages are
optional future examples, in priority order if separately approved:

1. Store Lite
2. Events Calendar
3. Appointments
4. Donations
5. Restaurant Ordering

Member Access / Protected Content is a cross-cutting security package required
before private Sections or protected downloads can become operational. It is
not a public listing-directory component. Public business or location
directories would be a separate future Listing component and search service.

See [`ADD-ON-CONTRACT.md`](ADD-ON-CONTRACT.md) for the package types, manifest,
runtime registration, lifecycle, permission, migration, theme, client
isolation, example-package, and acceptance contracts. These packages are not
core features or committed Version 5.1 scope; none is active in RED-CMS 5.0.

### Add-On Trust And Authorization Foundation

The extension-framework foundation is implemented without activating any
package. It adds a closed manifest schema, safe two-level filesystem discovery,
exact file-integrity verification, compatibility and dependency preflight,
reserved route/CSRF validation, database-backed Owner authorization, empty
per-client installation/migration registries, bounded lifecycle audit storage,
read-only registry reconciliation, and one server-local Owner-authorized
installation command. Discovery and installation never include `addon.php`, and
there is no web install or enable control.

Each client database has empty normalized role/capability tables. No legacy
account is promoted automatically. A server operator can perform one explicit,
audited first-Owner bootstrap with exact database and username confirmations;
protected sessions then refresh the six bounded lifecycle grants from that
client database. The clean starter ships no Owner assignment and no client
add-on directory.

The registry records a stable package id, version, type, raw-manifest hash,
deterministic file-inventory hash, lifecycle state, actor ids, and immutable
migration id/path/checksum evidence. The starter tables remain empty. A
read-only CLI reports valid discovery, pending migrations, recorded drift,
missing code, and the intentionally unavailable runtime without changing the
database.

The guarded install CLI dry-runs an exact database/package/migration plan,
requires explicit database, version, plan, disabled-state, and separately
verified-backup SHA-256 confirmations, takes a database-scoped package lock,
revalidates trust and required enabled dependencies, applies only reviewed
namespaced package SQL, records immutable migration evidence, and finishes
`installed_disabled`.
Recoverable partial MySQL DDL failure is recorded as `installation_failed` and
requires an exact reviewed resume.

Enablement and runtime registration, upgrades, disable/uninstall/purge, Member
Access, Store Lite, and the other optional verticals remain later reviewed
batches.

### Version 5.1 Compatibility Work

- The authenticated page-layout ellipsis menu now resets inherited `details`
  and `summary` spacing, borders, backgrounds, and minimum height inside the
  core-owned editor workspace. Active themes can style public disclosure
  elements without changing the administrator card geometry.
- The Version 5.1 core now contains position-`0` Article and Other controls
  inside the structured Hidden content tray while preserving the retained
  float wrapper for non-structured compatibility. The structured hidden grid
  remains active, all six supported component presentations stay contained at
  desktop and mobile widths, and public rendering is unchanged. See
  [`BUG-POSITION-0-HIDDEN-CONTENT-LAYOUT.md`](BUG-POSITION-0-HIDDEN-CONTENT-LAYOUT.md)
  for reproduction evidence, cause, repair boundary, and verification.
- Per-page SEO metadata is the first Version 5.1 implementation priority. The
  Version 5.1 core now provides nullable page-owner metadata, canonical URLs,
  complete Open Graph and X/Twitter output, typed JSON-LD, a guarded migration
  reporter/importer, and client-isolated browser QA. The Adriana 28-route QA
  passes. The unsupported JSON-LD inventory is classified, and its constrained
  generic implementation passes clean-starter acceptance. Fresh isolated
  verification and hosted Schema.org validation also pass; production
  deployment remains. See
  [`SEO-METADATA-COMPATIBILITY-REPORT.md`](SEO-METADATA-COMPATIBILITY-REPORT.md)
  for evidence, the proposed 5.1 model, migration requirements, and acceptance
  criteria, and
  [`SEO-JSONLD-LAUNCH-DECISION.md`](SEO-JSONLD-LAUNCH-DECISION.md) for the
  approved property-level boundary.
