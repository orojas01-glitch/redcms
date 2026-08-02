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

The first generic persistence foundation is implemented without adding a
package or business table to core. `RED_Articles` stores the full validated
component id, reviewed package migrations may declare only an exact foreign
key to its numeric `RecordID`, and production public dispatch resolves that
parent read-only against both persisted enabled state and the request-local
runtime owner. Missing parents, component drift, disabled state, alternate
core references, and orphan package records fail closed. Activation-blocked
existing-record updates and immutable revision snapshots are now implemented;
atomic restore execution is also implemented behind the exact read-only plan.
Creation now has a separate read-only preflight that validates an inactive,
hidden core parent shell plus schema-valid package values and returns a
deterministic plan without invoking package code or writing state. Its
activation-blocked atomic runner is now also implemented: it revalidates the
plan under lifecycle/theme serialization, creates the parent and package row,
reloads the exact postcondition, and commits initial core/package revisions in
one transaction. Permission-enforced inactive parent metadata and the
display-only value-free revision timeline are completed boundaries below;
read-only delete planning and atomic inactive deletion are also complete;
public placement, restore UI actions, and operational editor/delete endpoints
remain later isolated batches.

The editor-schema prerequisite is implemented as non-executing manifest data.
A package may optionally declare one bounded editor schema per provided
component, six already-requested lifecycle permissions, and only fixed
text/textarea/integer/boolean/select/URL/email/date/datetime/media-reference
field types with closed constraints. Unknown or executable-looking fields,
undeclared components or permissions, duplicate keys/options, and invalid
bounds fail validation. A normalized lookup is available, but enablement
preflight deliberately reports `component_editor_contract_required`; no
operational form, write handler, table selector, transaction, revision, or
package data-loading endpoint is activated.

The next non-writing prerequisite is also implemented: core validates one
submitted scalar-value object against that normalized schema and returns
values only when the complete object passes. It rejects unknown/nested fields,
invalid text encodings or controls, non-canonical or out-of-range numbers,
closed-choice violations, malformed locator and temporal values, and missing
required fields. This helper neither authorizes a user nor renders, loads, or
writes package data, so the same activation blocker remains in force.

The display-only editor-renderer prerequisite is now implemented. Core maps
the fixed schema types to namespaced administrator controls, escapes every
package-declared label, help string, option, and validated value, and exposes
stable required, help, and core-owned error relationships. It accepts only an
empty state or the exact closed validator result and fails closed on forged
state. The fragment deliberately contains no form, Save control, authorization
decision, package code or data load, database write, or activation change.

The first package-permission prerequisite is now implemented without adding a
grant workflow. The per-client capability column matches the existing
160-character manifest limit, each of the six editor operations resolves to
its exact declared permission, and a fresh case-sensitive database lookup
requires that administrator's exact grant. Owner and lifecycle grants do not
imply package access. The decision is read-only and does not activate a
package, execute code, open an endpoint, or write content.

The bounded package data-loader prerequisite is now implemented without
opening an editor endpoint. An enabled package registrar must bind exactly one
loader for each declared component editor. Core requires the exact view grant,
the current enabled placement/runtime owner, contained loader execution, and a
complete schema-valid returned value object. It exposes normalized values plus
a core-owned state hash for later stale-write and revision checks, but performs
no content, package, authorization, lifecycle, or audit write.

The existing-record package update prerequisite is now implemented without an
administrator endpoint or activation change. A registrar may bind at most one
writer per declared editor and must list the package-owned transaction tables.
Core requires those tables and the placement parent to be InnoDB, locks the
exact enabled parent, checks the current view and edit grants plus state hash,
passes only normalized schema values, contains writer output and failures,
reloads the saved values, and commits only when the complete postcondition
matches. Stale state, revoked grants, drift, disabled ownership, unsupported
tables, exceptions, output, buffer changes, false returns, and partial writes
fail closed with rollback. No create path, parent-metadata update, operational
form, revision history UI, restore, delete, audit workflow, or activation
eligibility is added. Successful updates atomically retain immutable baseline
and saved normalized-value snapshots in a core-owned per-client ledger;
identical submissions add no revision, and a ledger failure rolls back the
package write.
Bounded revision history and restore preflight are now also implemented as
read-only helpers. They require current view/restore grants, exact enabled
ownership, the current state hash, and a fully revalidated target snapshot;
they return metadata and a deterministic plan but execute no restore.
The separate core-owned history renderer accepts only that value-free,
newest-first result plus the current state hash. It escapes bounded metadata,
marks non-current entries as requiring a fresh restore check, and fails closed
on stale, reordered, malformed, or value-bearing input. It renders no form,
link, button, package markup, hash, value, or restore action.
The first delete-contract prerequisite is now also implemented as read-only
planning. One optional registrar-bound deleter declares only package-owned
transaction tables. Core requires fresh view/delete grants, the inactive hidden
unrouted shell, exact parent and package state hashes, the latest validated
package revision, enabled runtime ownership, and InnoDB support before returning
a deterministic plan. It never invokes the deleter or opens a transaction,
endpoint, form, delete action, audit event, public placement, or activation path.
The separate activation-blocked atomic delete runner revalidates that exact
plan under lifecycle/theme/installation/parent locks, records
duplicate-preserving package and core `delete` snapshots before mutation,
invokes only the registrar-bound deleter, and requires every declared package
row to be absent before deleting SEO metadata and the inactive parent. All rows
and attempted revisions roll back on stale evidence, callback output or
failure, partial deletion, lost transaction, ledger failure, or any failed
postcondition. Successful deletion retains both immutable revision ledgers and
adds no endpoint, control, audit event, media deletion, uninstall, purge,
public placement, or activation behavior.
The separate activation-blocked restore runner rechecks that plan under the
locked enabled parent, uses only the registered writer and target snapshot,
requires the exact reloaded target state, and commits a source-linked restore
revision in the same transaction. Stale plans, revoked grants, writer failures,
postcondition failures, and revision-ledger failures roll back.

The read-only component-creation preflight is now implemented. An enabled
registrar may optionally bind one creator per declared editor with one to eight
package-owned transaction tables. Core requires the exact create grant,
manifest and runtime component/loader/creator ownership, InnoDB core and
package tables, an unused numeric record id with no parent/revision/SEO
evidence, a valid active-theme layout, closed parent metadata, and fully
normalized package values. The returned hash binds an inactive, hidden,
unrouted parent shell to the complete normalized plan. The preflight itself
invokes no loader or creator, reserves no id, and writes no state.

The separate atomic creation runner is now implemented behind that exact plan.
It serializes with add-on lifecycle and theme changes, locks the enabled
installation, rechecks the complete plan, inserts only the inactive hidden
core parent, invokes the registered creator with normalized values, reloads
through the registered loader, and requires both the parent and package
postconditions. The parent, package row, core `create` revision, and package
`baseline` revision commit together. Stale plans, caller-owned transactions,
callback output/exceptions/buffer changes/false returns, partial writes,
postcondition mismatches, and either revision-ledger failure roll back. It
opens no form or endpoint and does not edit parent metadata, choose public
placement, activate content, delete, or write an audit event.

The permission-enforced parent-metadata prerequisite is now implemented as a
separate activation-blocked boundary. Read-only state requires the exact view
grant, enabled manifest/runtime/binding, the closed inactive hidden unrouted
shell, a valid package loader result, and current core revision evidence. The
atomic writer additionally requires the exact edit grant and caller state
hash, serializes with lifecycle and theme changes, locks the enabled
installation and parent, and rechecks every condition. It changes only title,
active-theme layout, and language, requires the exact full parent and unchanged
package postconditions, and commits one core `save` revision. Invalid,
revoked, stale, public/placed, caller-owned-transaction, postcondition, and
revision failures leave the parent and package unchanged. No UI, endpoint,
public placement, activation, delete, audit, or package-value write is added.

The Store Lite product and security direction is now defined without adding
commerce behavior or data to core. Its generic component-plus-service
registration shape is accepted, but the complete Store Lite manifest remains
blocked. The generic numeric parent relationship, public binding resolver, and
declarative editor-schema, submitted-value validation, and activation-blocked
existing-record package updates and immutable revision snapshots are
implemented; component-creation planning and its atomic inactive runner are
implemented, and the activation-blocked parent-metadata writer plus atomic
inactive delete runner are implemented, while typed-service invocation, route,
administrator-tool, settings, asset, live-data, and richer package persistence
contracts must still be implemented and accepted with disposable fixtures
before the separately distributed package can be enabled.

The maintained [add-on platform status map](ADD-ON-PLATFORM-STATUS.md) shows
the completed foundation, current reviewed slice, remaining Store Lite gates,
and later optional vertical packages without changing their scope.

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
