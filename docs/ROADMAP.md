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
Schema.org Markup Validator with zero errors and zero warnings. The separate
Adriana production backup, migration, public and administrator smoke tests,
rollback verification, and post-launch hardening are complete. Client data,
theme, media, configuration, and deployment evidence remain outside the clean
starter repository.

The 87 explicitly reported JSON-LD property occurrences are now classified:
84 should be emitted through generated relationships or constrained typed
fields, one redundant homepage self-reference should be normalized away, and
the visitor-invisible Course code and rating should remain explicit
exclusions. The constrained generic fields pass clean-starter acceptance, and
the fresh isolated 28-route Adriana JSON-LD QA and hosted Schema.org validation
also pass. The isolated production deployment is complete, so this launch
priority is closed. See
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

See [`ADD-ON-CONTRACT.md`](ADD-ON-CONTRACT.md) for the generic package types,
manifest, runtime registration, lifecycle, permission, migration, theme,
client-isolation, and acceptance contracts. The first optional package is
specified in [`STORE-LITE-DIRECTION.md`](STORE-LITE-DIRECTION.md), including
its component/service split, data ownership, payment boundary, lifecycle, and
release gates. These packages are not core features or bundled starter
capabilities.

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
and missing code without changing the database.

The guarded install CLI dry-runs an exact database/package/migration plan,
requires explicit database, version, plan, disabled-state, and separately
verified-backup SHA-256 confirmations, takes a database-scoped package lock,
revalidates trust and required enabled dependencies, applies only reviewed
namespaced package SQL, records immutable migration evidence, and finishes
`installed_disabled`.
Recoverable partial MySQL DDL failure is recorded as `installation_failed` and
requires an exact reviewed resume.

The fixed runtime-registration contract and front-controller page-request bootstrap are
implemented. They load only already-recorded `enabled` packages after complete
catalog, registry, dependency, namespace, and integrity reconciliation, expose
registered handlers through a core lookup context, and fail before rendering
when enabled evidence is unsafe. An enabled manifest-declared component can
now receive only a bounded placement context and return a text-only view model
that core escapes into its default public renderer; all other handler types
remain non-dispatched. The clean starter has no package directory or enabled
package state.

The read-only enablement plan now resolves declarative theme, settings, and
live-data gates for three deliberately constrained profiles: a
registration-only service package, a core-rendered default public component
package, and a default public component combined with registration-only
services. All exclude migrations, settings, routes, jobs, public or
administrator assets, administrator tools, adapters, and outbound hosts. Any
richer package remains blocked behind its explicit contracts. The separate
Owner-authorized enable command revalidates that exact plan under the shared
lifecycle lock and target package lock, validates the fixed registrar, and
atomically records `enabled` with its bounded audit fact. Safe default
component dispatch is implemented; services remain lookup-only and are not
automatically invoked. The Owner-authorized disable command serializes with
enablement, refuses enabled dependents, and atomically returns a package to
`installed_disabled` without executing package PHP or deleting package code,
migrations, settings, media, or business data. Service, route, adapter, and
administrator-tool dispatch, upgrades, uninstall/purge, Member Access, Store
Lite, and the other optional verticals remain later reviewed batches.

The Store Lite product and security direction is now defined without adding
commerce behavior or data to core. Its generic component-plus-service
registration shape is accepted, but the complete Store Lite manifest remains
blocked. Generic persistence, editor, typed-service invocation, route,
administrator-tool, settings, asset, and live-data contracts must be
implemented and accepted with disposable fixtures before the separately
distributed package can be enabled.

### Version 5.1 Compatibility Work

- Site-wide Analytics, Tag Manager, Jotform, consent, and similar client
  integrations currently require theme-file edits because the legacy
  `Website_Header` setting is a visible theme-header region rather than the
  document `<head>`. Version 5.1 should add database-managed, revision-backed
  Global Integration records with explicit `head`, `body-start`, and `body-end`
  placements, guarded administrator controls, CSP compatibility, audit history,
  theme-independent rendering, and conflict-refusing client migration. See
  [`BUG-DATABASE-MANAGED-GLOBAL-INTEGRATION-SLOTS.md`](BUG-DATABASE-MANAGED-GLOBAL-INTEGRATION-SLOTS.md)
  for the proposed model, security boundary, migration behavior, and acceptance
  criteria.
- The Adriana production replacement exposed a Contact-form migration and mail
  transport gap: the approved package preserved unrelated sender/recipient
  values, the browser success state does not prove delivery, and the current
  PHPMailer path relies on unauthenticated native `mail()`. The client has an
  administrator-only recovery path, while the generic 5.1 work requires guarded
  routing migration, private authenticated SMTP configuration, truthful
  delivery states, visitor Reply-To handling, and privacy-safe diagnostics. See
  [`BUG-CONTACT-FORM-MAIL-ROUTING-AND-TRANSPORT.md`](BUG-CONTACT-FORM-MAIL-ROUTING-AND-TRANSPORT.md)
  for evidence, the immediate correction, repair boundary, and acceptance
  criteria.
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
  verification, hosted Schema.org validation, and the separate Adriana
  production launch also pass. See
  [`SEO-METADATA-COMPATIBILITY-REPORT.md`](SEO-METADATA-COMPATIBILITY-REPORT.md)
  for evidence, the proposed 5.1 model, migration requirements, and acceptance
  criteria, and
  [`SEO-JSONLD-LAUNCH-DECISION.md`](SEO-JSONLD-LAUNCH-DECISION.md) for the
  approved property-level boundary.
