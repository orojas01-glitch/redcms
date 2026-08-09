# RED-CMS 5.1 Direction

Status: implementation in progress. Per-page SEO compatibility, including the
approved constrained JSON-LD core, non-executing add-on trust validation,
persisted Owner authorization, per-client registry/migration-ledger storage,
read-only reconciliation, guarded server-local installation into a disabled
state, and constrained Owner-authorized atomic enablement for registration-only
service, secret-capable registration-only service, core-rendered default public
component, and combined default-component plus registration-only-service
profiles are implemented. The secret-capable profile resolves only each
package's own server-local secret-reference settings through the typed service
request and rejects secret-bearing results before they leave core.
Owner-authorized non-executing atomic disablement with enabled-dependent
refusal is also implemented. Fresh isolated Adriana JSON-LD verification
and hosted Schema.org validation also pass. The separate Adriana production
backup, migration, launch verification, and rollback gate are complete without
copying client assets or data into the starter. Fixed add-on registration,
fail-closed page-request loading, and safe core-owned default dispatch for an
enabled manifest-declared component are implemented for already-recorded
enabled packages. Typed internal service invocation, exact static public
`GET` routes, and display-only permission-scoped administrator tools now have
separate fail-closed dispatch boundaries. A separate non-executing
administrator write-action preflight binds exact runtime owners, an
action-specific permission, `POST`/CSRF contract, fixed target idempotency,
and numeric target into a deterministic plan without callback execution or
mutation. A separate internal atomic action runner revalidates target-state
evidence under shared locks and commits only an exact contained action,
per-client ledger record, and value-free audit fact. A separate unlinked
core-owned administrator endpoint validates session and CSRF itself, accepts
only exact action identities, derives its plan server-side, and returns only
value-free bounded outcomes; it adds no UI or public route. The separately
documented public-mutation boundary now validates optional closed declaration
metadata and produces value-free deterministic preflight evidence for a future
static-POST anonymous path with CSRF, idempotency, rate-limit, transaction, and
response constraints; the internal transaction runner and replay ledger are now
implemented, and an unlinked core-owned dispatcher composes the explicit
request, route, subject/CSRF, form, runner, and fixed-response contracts. It is
not linked to the front controller and still emits no cookie/header or session
access, package behavior, or enablement change. A separate Docker-only
supported-server rehearsal now carries a secret-guarded fixture request through
the pinned custom FrankenPHP/Caddy binary, PHP verifier, dispatcher, atomic
runner, and fixed emitter against a fresh MySQL database, then removes every
temporary artifact. The rehearsal does not deploy a client or issue a browser
cookie. The separate
read-only live-data preflight now binds a trusted declaration from an
`installed_disabled` package to current per-client migration, table,
typed-setting, opaque secret-availability, and core
subject/CSRF/rate-limit/idempotency/execution storage evidence without enabling it or
loading package PHP. A separate
internal core foundation stores only SHA-256 digests of opaque anonymous-subject
and CSRF values in empty per-client tables and returns a future endpoint's
secure host-only cookie descriptor. A companion rate-limit foundation records
only an opaque subject relation, declaration/database scope hash, window facts,
and bounded count; it permits at most 12 requests per 60 seconds per client,
declared route, and subject. Neither provides a public endpoint, emits a
cookie/header, accesses a browser cookie/session, executes package code, or
changes activation. A companion idempotency-key foundation stores only an
opaque subject relation, declaration/database scope hash, SHA-256 key digest,
and expiry facts; its issuer/resolver can issue or resolve a 10-minute key but
does not consume it itself. The fifth empty core execution table records only
an idempotency-key relation, keyed HMAC command/state evidence, a bounded
outcome, and completion time. An internal atomic runner accepts only a current
trusted in-memory first-party registrar binding and typed evidence from a future
core dispatcher, then verifies CSRF, key, rate, declared tables, and server
postconditions before committing package state, replay evidence, and a
value-free anonymous audit fact together. It is not an endpoint, response
builder, browser bridge, Store Lite package, or database sandbox for arbitrary
PHP.
Adapters, operational writable route/tool actions, richer package-runtime
secret surfaces,
upgrades, uninstall/purge,
member access, publishing, payment, and integration controls remain inactive.
The Store Lite product and security boundary is defined. Gate 2A now fixes the
package-owned simple/variable Product record contract: three option groups,
sixteen values per group, 128 explicit variants, integer minor-unit money, one
installation currency, and fail-closed identifiers, SKUs, option tuples, and
parent/variant fields. Its dependency-free 20-assertion fixture covers a
banana-style simple product and a Size/Color shirt without adding commerce
tables, package files, or client state. The complete shape is documented in
`docs/STORE-LITE-PRODUCT-CONTRACT.md`. The first generic
component-persistence foundation now provides full
component-id storage, a narrowly guarded package-table relationship to the
numeric placement parent, and fail-closed read-only public binding resolution.
Optional data-only component editor schemas are also validated and normalized
without package execution. Submitted scalar values can now be validated and
normalized against those schemas with fail-closed empty output. Core can render
the schema and exact validator state as escaped, accessible administrator
controls. A core-owned authenticated existing-record form now supplies the
first Save path for already-enabled persisted components, with CSRF, fresh
exact view/edit grants, server-derived ownership, and stale-state protection.
Activation remains blocked. Core now also resolves each fixed editor operation
to its exact manifest permission and checks a fresh per-client administrator grant without
granting state or inferring Owner access. The bounded data-loader prerequisite
now requires that exact view grant, the enabled parent/runtime owner, contained
package execution, and schema-valid returned values before exposing a
core-owned state hash. The activation-blocked existing-record update helper
now requires exact view/edit grants, a current state hash, locked enabled
ownership, declared InnoDB package tables, contained writer execution, and an
exact reloaded postcondition before committing package-owned values. No
permission grant/revoke workflow, restore action, or create/delete endpoint is
implied by these prerequisites. Operational writable route/tool actions remain
inactive; the separate ordinary-settings editor is documented and accepted
below.
The first non-executing settings-value prerequisite now normalizes only valid
data-only definitions, requires exact type-correct non-secret defaults, and
validates one closed configuration object. Missing, unknown, nested, malformed,
loosely coerced, or oversized values fail with no normalized configuration.
Secret settings return only separate opaque lowercase `config:` references;
core does not resolve secret material. No database, permission, form, package
runtime, or activation path is added.
An additive per-client settings-storage prerequisite now provides one empty
generic table and a read-only write preflight. Operational definitions bind an
explicit permission already declared by the package. Preflight requires exact
filesystem and registry identity, installed-disabled or enabled state,
complete typed values, fresh case-sensitive database grants, valid current
stored rows, and deterministic current/target/plan hashes. It does not write,
resolve secrets, render controls, execute package code, or relax enablement.
The internal atomic setting writer refuses caller-owned transactions, holds
the shared lifecycle/package plus installation/setting row locks, recreates
the complete plan, replaces every typed setting, reloads the exact target
hash/count, and atomically records one value-free `addon.settings.updated`
fact. Exact no-ops add no audit. Stale plans, drift, postcondition/audit
failure, or injected late failure roll back. The writer itself exposes no
package UI, performs no secret lookup, and changes no activation eligibility;
the separate ordinary settings editor now delegates to this boundary.
The separate core-only current-setting read model rechecks exact trusted
package/registry identity and installed-disabled or enabled state, requires an
explicit declared permission for every operational setting, and makes fresh
case-sensitive decisions per setting. It returns authorized non-secret
stored/default/unset values and one deterministic visible-model hash; a secret
setting yields only its configured state, never its opaque reference. Invalid
storage, identity, lifecycle, or grant state returns no partial model. It does
not render an administrator screen, expose an endpoint, write a row, execute a
package, resolve a secret, or change activation eligibility.
The separate server-local availability boundary validates only a bounded list
of opaque `config:` references declared by the operator, revalidates the
complete typed configuration, and returns deterministic counts, missing
setting keys, and fingerprints. It returns no reference identifier or secret
value, reads no database or secret, executes no package, and changes no
  activation gate. Core-owned server-local secret resolution/reference
  replacement is accepted separately through an unlinked endpoint. The narrow
  secret-capable registration-only service profile now consumes only its own
  resolved references through the typed service request and rejects
  secret-bearing results. The ordinary core-owned
  settings editor is accepted separately and remains unlinked from
  administrator navigation.
The namespaced asset foundation is now complete through core-owned document
injection. CSS must be beneath `assets/` and load at `head`; JavaScript must be
beneath `assets/` and load at `body-end`. Core derives checksum-versioned
package URLs and renders only escaped tags after revalidating aggregate plan
evidence. Immutable delivery is handled by the separate static endpoint. The
injection planner re-discovers trusted manifests and current registry evidence
without loading `addon.php`, validates both surfaces for every enabled package,
and adds public tags at unambiguous document boundaries; it adds administrator
tags only when the existing signed-in overlay is present. Invalid catalog,
registry, integrity, plan, or document-boundary state emits no package markup
and changes no activation gate.
Successful updates
atomically retain core-owned baseline and saved package-value snapshots in the
current client database. The activation-blocked atomic restore runner now
revalidates the exact restore plan under the locked enabled parent, uses the
registered writer, verifies the reloaded target state, and commits a
source-linked restore revision or rolls back the package write.
Core now also provides a read-only component-creation preflight. It requires
the exact create grant, enabled manifest/runtime ownership, a registrar-bound
creator with declared InnoDB package tables, an unused numeric parent id, an
active-theme layout, and normalized package values. It returns a deterministic
inactive, hidden, unrouted parent-shell plan without invoking a loader or
creator and without reserving or writing anything. Its separate
activation-blocked atomic runner now revalidates that plan under lifecycle and
theme serialization, creates the parent and package row, verifies the exact
loader postcondition, and commits initial core/package revisions together.
Create/delete endpoints and activation eligibility remain absent. Audited
public placement is available only through the core-owned POST/CSRF control
and exact atomic plan. Atomic deletion is available only behind the exact
activation-blocked preflight plan. The activation-blocked parent
metadata prerequisite now provides a read-only state gate and atomic writer.
It requires exact view/edit grants, enabled ownership, a closed inactive shell,
current package-loader and core-revision evidence, an exact state hash, and
lifecycle/theme/installation/parent serialization. Only title, active-theme
layout, and language may change; the full shell and package state must remain
exact, and one core `save` revision commits with the update. Unchanged values
add no revision. No parent-metadata form, create/delete endpoint, delete
control, activation, or package-value mutation is exposed.

## Product Goal

Version 5.1 should first close the per-page SEO metadata compatibility gap
blocking the isolated Adriana launch. Later work may extend the 5.0 authoring
foundation with member access, formal publishing operations, clearer
accountability, and optional integrations. RED-CMS should remain a reusable
core that can be adapted to different client types through separately
installed components, services, and provider adapters. New capabilities
should be modular, permission-aware, migration-backed, auditable, and
removable without breaking public content.

## 1. Per-Page SEO Metadata Compatibility

The first implementation milestone is a generic, compatibility-preserving SEO
model for public routes:

- Store an exact SEO title independently from the visible page title.
- Support canonical, robots, Open Graph, X/Twitter, and typed JSON-LD data.
- Generate safe fallback values without requiring duplicate editor entry.
- Keep new storage nullable so upgraded installations retain existing output
  until an override is populated.
- Report imported, derived, skipped, invalid, and non-representable metadata.
- Validate generic fixtures and all 28 Adriana routes in the separate client
  installation without copying client data into the starter.

The complete evidence, field model, fallback rules, migration requirements,
and acceptance criteria are in
`docs/SEO-METADATA-COMPATIBILITY-REPORT.md`. The reported Adriana JSON-LD
inventory is classified in `docs/SEO-JSONLD-LAUNCH-DECISION.md`: use generated
relationships and constrained typed fields for visible content, normalize the
redundant homepage self-reference, exclude the visitor-invisible Course code
and rating, and do not add arbitrary custom JSON-LD. That generic contract, its
clean-starter acceptance gate, and the fresh isolated 28-route Adriana
verification now pass. All 28 public renders also pass the hosted Schema.org
Markup Validator with zero errors and zero warnings. The separate Adriana
production approval, deployment, smoke-test, and rollback gate is complete.

## 2. Members, Paid Access, And Protected Content

Private Sections and protected downloads should use a dedicated Member Access / Protected Content package with member identity, sessions, entitlements, and route enforcement. They must never rely on the administrator account table, a client-side hidden folder, or the stored `AccessLevel` value alone. A visitor may register, sign in, receive a manual entitlement, or purchase access through a payment adapter.

- Enforce access before protected content is queried or rendered.
- Keep public administrators and public members in separate identity stores.
- Grant access only from verified server-side payment events.
- Support time-limited, revocable, and renewable entitlements.
- Keep payment credentials outside RED-CMS.
- Begin with PayPal sandbox support; add Nequi after merchant onboarding and certified server-side callback testing.

This use of protected content is different from a public listing directory. The
detailed security and delivery model is in `docs/MEMBER-ACCESS-DIRECTION.md`.

## 3. Roles And Permissions

Replace the current component-access model with composable roles and scoped permissions while preserving a protected owner account.

Suggested starting roles:

- Owner
- Administrator
- Publisher
- Editor
- Contributor
- Member-support or billing operator

Permissions should describe actions such as view, create, edit own, edit any, review, approve, publish, manage users, manage payments, manage themes, and install tools. Scopes may later restrict access by Section or site.

The first additive role slice is now implemented specifically for the future
add-on lifecycle. `RED_Admin_Roles` and `RED_Admin_Capabilities` remain empty
after migration, preserving every legacy account. An explicit server-local
bootstrap may assign one protected Owner and the six fixed add-on lifecycle
capabilities in one audited transaction. Login and protected requests refresh
those grants from the current client database. Broader Administrator,
Publisher, Editor, Contributor, and scoped-content permissions remain future
work.

## 4. Publishing Workflow

Introduce an explicit lifecycle:

`Draft → In review → Approved → Scheduled/Published`

Publishing state should be independent from version history. Every transition needs permission checks, an actor, timestamp, optional note, and a stable revision reference. Rejection should return content to Draft without destroying the review record. Existing installations should continue to behave as immediate-publish sites until workflow is deliberately enabled.

## 5. Notifications And Reminders

Create an internal notification center before adding external channels. Useful events include review requests, approvals, rejections, scheduled publication, approaching expiration, failed publication, payment exceptions, and assigned follow-up work.

- Store notifications in RED-CMS with read/unread and resolved states.
- Allow per-user preferences and quiet periods.
- Deliver email only through a configured queue or transport.
- Deduplicate retryable events and keep delivery failures visible.

## 6. Ownership And Change Accountability

Content should record a responsible owner separately from the administrator who performed the most recent change. Version history and the administrator activity log already provide useful foundations, but 5.1 should make ownership, assignment, and change attribution visible in the workspace.

Each important record should answer:

- Who owns this content?
- Who last changed it?
- What changed?
- Which revision was reviewed and published?
- Who approved it?
- When is follow-up due?

## 7. Controlled Add-Ons And Social Publishing APIs

Add a controlled extension catalog rather than arbitrary uploaded PHP. A
package may provide placeable components, business services, administrator
tools, or external-provider adapters. It should declare its identifier,
version, compatibility range, permissions, settings schema, migrations,
background jobs, outbound hosts, dependencies, and uninstall behavior.

Optional future package examples are Store Lite, Events Calendar,
Appointments, Donations, and Restaurant Ordering, in that priority order if
separately approved. They are not core features or required Version 5.1
deliverables. Member Access / Protected Content is a separate cross-cutting
package required before private content is enabled. The full boundary is
defined in `docs/ADD-ON-CONTRACT.md`. The first optional package's component,
commerce-service, persistence, payment-adapter, lifecycle, and acceptance
contract is defined in `docs/STORE-LITE-DIRECTION.md`. Store Lite remains
separately distributed and is not bundled with the clean starter. The fixed
product/variant boundary is also recorded in
`docs/STORE-LITE-PRODUCT-CONTRACT.md`.

Social publishing should be an optional adapter layer:

- Connect accounts through provider-supported OAuth.
- Store tokens encrypted and outside public content.
- Use provider APIs and queues rather than browser automation.
- Preview the exact outgoing text and media.
- Require an authorized confirmation before publishing.
- Record provider response ids, failures, retries, and the initiating user.

Initial research can cover major providers, but implementation should begin with one well-supported API and a reusable adapter contract.

The first implementation should use trusted filesystem-deployed first-party
packages. Package discovery must not execute code, and the administrator must
not upload arbitrary PHP. Installation and activation remain separate,
owner-authorized actions scoped to one client database.

The read-only trust foundation now validates a closed `addon.json`, fixed
vendor/package paths, compatibility ranges, dependencies, reserved routes,
settings, outbound hosts, and an exact SHA-256 package inventory. It rejects
unsafe or incomplete packages without executing `addon.php`. Empty generic
installation and immutable migration-ledger tables now exist per client
database, and read-only reconciliation reports package identity drift, pending
or changed migrations, and missing code. Owner
authorization is persisted per client database, but no account is promoted
automatically.

The first lifecycle consumer is a server-local install command gated by the
exact persisted Owner `addons.install` capability. It dry-runs a deterministic
plan, requires exact target/plan/backup/disabled-state confirmations, rechecks
trust and required enabled dependencies under a database-scoped advisory lock,
applies checksum-verified `RED_Addon_*` migration SQL, records immutable
migration evidence and bounded audit events, and finishes
`installed_disabled`. It never includes `addon.php`. Because MySQL DDL can
commit implicitly, partial failures remain visible as `installation_failed`
with an explicit resumable ledger rather than a false rollback claim. No
lifecycle UI, upgrade, uninstall, or purge command exists.

The next lifecycle boundary is a separate server-local, read-only enablement
preflight gated by the exact persisted Owner `addons.enable` capability. It
accepts only an exact current `installed_disabled` package, reconciles the full
package registry, binds currently enabled dependency/package identities, and
reports provided-capability, route-id, and route-method ownership conflicts in
one deterministic plan. It has no apply mode, performs no database or audit
write, and never includes `addon.php`. The plan always reports activation,
state mutation, and package loading unavailable. Runtime registration is now
an available core contract. The read-only plan clears declarative theme,
settings, and live-data gates only for a registration-only service package, a
secret-capable registration-only service package, a default public component
package, or a default public component combined with registration-only
services. The secret-capable profile admits only secret-reference settings and
requires complete per-client storage plus server-local values; it still
excludes migrations, routes, jobs, assets, administrator tools/actions,
adapters, and outbound hosts. The other profiles exclude settings. Either
component profile clears theme compatibility only through core's
escaped default renderer. Packages with any richer surface retain exact
contract blockers. The specific registrar remains unexecuted until the
separate apply command revalidates it under the shared lifecycle lock and
target package lock. That command accepts only these profiles, requires exact
target, plan, backup, and disabled-state confirmations, then commits the state
compare-and-swap plus bounded audit fact atomically. It does not add service,
route, adapter, or administrator-tool dispatch or support richer package
surfaces.

Front-controller page requests, public or authenticated, now reconcile the
complete package catalog and per-client registry before executing any package
code. Only already-recorded `enabled`
packages with current identity, migration, dependency, namespace, path, and
checksum evidence may return their fixed registrars. Required dependencies
register first, disabled packages never execute, and the resulting handlers
are exposed through a core lookup context without being invoked automatically.
Any enabled drift or missing code fails before public rendering. Lifecycle
CLIs remain outside this request bootstrap.

The secret-capable service runtime is a narrow follow-on to that bootstrap.
Core builds a private package-bound access object only after enabled identity,
settings storage, complete typed rows, state hash, opaque-reference allowlist,
and server-local value inventory all pass. The typed
`RED_Addon_Service_Request::secret()` lookup returns only status while the
resolved bytes travel by internal reference. Core scans typed service results
and rejects secret disclosure; no secret bytes enter the runtime context,
preflight material, audit, response, or browser state. Missing or unavailable
configuration fails before the registrar executes.

The Owner-authorized disable command provides the reverse
`enabled` to `installed_disabled` transition. Its deterministic dry run binds
the exact current package, complete registry, and every other enabled package
without including package PHP. Apply serializes with enablement under one
database-wide lifecycle lock, takes the target package lock, refuses any
enabled required dependent, and requires exact target, plan, nonzero backup
SHA-256, and enabled-state confirmations. State and the bounded
`addon.disable.completed` audit fact commit atomically. Package code,
migrations, settings, media, and business data remain untouched, and later
request bootstrap excludes the disabled package.

## Delivery Order

1. Completed: implement the generic per-page SEO storage, editor, rendering,
   fallback, validation, and migration-reporting contracts.
2. Completed: pass the SEO acceptance suite using generic clean-starter
   fixtures.
3. Completed: import and verify the constrained JSON-LD values for all 28
   Adriana routes in a fresh separate installation and database.
4. Completed: submit only the 28 public rendered routes to the hosted
   Schema.org Markup Validator; all passed with zero errors and zero warnings.
5. Completed: complete the separately approved Adriana production backup,
   migration, smoke-test, and rollback operation without copying its theme,
   data, media, metadata, or settings into the starter.
6. Completed: implement fixed add-on runtime registration and fail-closed
   request bootstrap without bundling or activating a client package in the
   clean starter.
7. Completed foundation: resolve declarative theme, settings, and live-data
   readiness for constrained registration-only service and core-rendered
   default public component profiles while leaving all package registrars and
   richer surfaces blocked and unexecuted.
8. Completed: implement the Owner-authorized atomic enable transition and
   rollback proof for those constrained profiles, including lifecycle reach to
   the safe default component renderer, without bundling Store Lite or another
   client-facing package.
9. Completed: implement the Owner-authorized atomic disable transition with a
   shared lifecycle lock, enabled-dependent refusal, rollback proof, and
   later-request unload behavior without package execution or data deletion.
10. Completed direction: define Store Lite as a separate optional
    Product-component plus commerce-service package, including client-owned
    storage, payment-adapter isolation, lifecycle behavior, and acceptance
    gates. Implementation remains blocked until the required generic richer
    package contracts pass with disposable fixtures.
11. Completed foundation: accept a constrained default public component plus
    registration-only services as one package, with non-executing preflight,
    atomic Owner enablement, later request registration and default rendering,
    non-executing disablement, and exact disposable cleanup. Persistence,
    editor, routes, administrator tools, settings, and
    live-data behavior remain blocked.
12. Completed persistence foundation: preserve `RED_Articles` as the single
    placement parent, store the full manifest component id, permit package
    tables only an exact numeric parent foreign key, and require an enabled
    runtime-owner match before production public dispatch. Later prerequisites
    now add transactional existing-record updates and immutable revision
    snapshots, validated history/preflight, and atomic restore execution;
    component creation, parent-metadata editing, history UI, and delete
    behavior remain separate batches.
13. Completed editor-schema prerequisite: validate optional data-only
    component editor declarations against provided components, six
    already-requested permissions, and fixed field types and bounds. Expose a
    normalized read-only lookup while keeping enablement blocked until the
    separate component creation, parent-metadata, history UI, delete, and
    operational editor contracts exist.
14. Completed editor-value prerequisite: normalize one submitted scalar-value
    object only after the exact component schema resolves, reject unknown,
    nested, malformed, non-canonical, or out-of-bounds values, and return no
    normalized payload on any error. Keep authorization, administrator
    rendering, package execution, persistence, revisions, and activation out
    of this batch.
15. Completed display-only editor-renderer prerequisite: map the fixed schema
    types to core-owned escaped administrator controls, render only an empty
    state or an exact validator result, expose stable labels, help, and
    core-owned errors, and fail closed on forged state. Keep the renderer
    outside a form with no Save action, authorization decision, package data
    load, package execution, database write, or activation change.
16. Completed editor-permission prerequisite: align the per-client capability
    column with the 160-character manifest permission limit, resolve only the
    six fixed editor operations, and require a fresh, exact, case-sensitive
    administrator grant. Do not infer package access from Owner or lifecycle
    grants, add a grant-management UI, execute package code, write package
    state, or change activation eligibility.
17. Completed component data-loader prerequisite: require an enabled package
    to register exactly one loader for each declared editor, then enforce the
    exact view grant and current placement/runtime owner before invocation.
    Contain output and failures, revalidate the complete returned value object,
    and expose a core-owned state hash without adding an endpoint, form, write,
    revision, audit event, or activation eligibility.
18. Completed existing-record update prerequisite: allow at most one
    registrar-bound writer per declared editor, require its package-owned
    transaction tables to be InnoDB, lock the exact enabled placement parent,
    recheck view/edit grants and the current state hash, pass only normalized
    values, contain callback failures, reload the saved values, and roll back
    unless the complete postcondition matches. Keep component creation,
    parent-metadata writes, restore execution/history UI, delete, audit workflow,
    web forms/endpoints, and activation eligibility out of this batch.
19. Completed revision-snapshot prerequisite: store immutable normalized
    baseline/checkpoint and saved package values with exact package, component,
    content record, actor, revision number, and state hash in a core-owned
    per-client ledger. Commit snapshots and package writes together, add no
    revision for unchanged values, and roll back the package write when ledger
    insertion fails. Keep history UI, restore, delete, operational endpoints,
    audit workflow, and activation eligibility separate.
20. Completed read-only revision-history and restore-preflight prerequisite:
    require the exact enabled binding and view grant for a bounded validated
    newest-first timeline, then require the exact restore grant, current state
    hash, and integrity-valid target snapshot for a deterministic plan. Invoke
    no writer and keep restore execution, history UI, endpoints, audit, and
    activation eligibility separate.
21. Completed atomic restore-runner prerequisite: revalidate the exact current
    state, target revision, permissions, enabled binding, and deterministic
    plan under the locked parent; invoke only the registered writer with the
    validated target values; require the exact reloaded postcondition; and
    commit one immutable source-linked restore revision in the same transaction.
    Roll back stale plans, revoked grants, writer/postcondition failures, and
    revision-ledger failures. Keep history UI, forms/endpoints, audit workflow,
    activation eligibility, component creation, parent metadata, and delete
    behavior separate.
22. Completed read-only component-creation preflight prerequisite: allow at
    most one optional registrar-bound creator per declared editor with closed
    package-table metadata; require the exact create grant, enabled
    manifest/component/loader/creator ownership, InnoDB transaction support,
    an unused numeric record id, a valid active-theme layout, and fully
    normalized package values; and return a deterministic plan whose core
    parent shell is fixed inactive, hidden, and unrouted. Invoke no package
    callback, reserve no id, and write no state. Keep the atomic creation
    runner, parent-metadata editing, public placement, forms/endpoints, audit,
    history UI, delete, and activation eligibility separate.
23. Completed atomic component-creation runner prerequisite: reject
    caller-owned transactions, serialize with add-on lifecycle and theme
    changes, lock the exact enabled installation, and revalidate the caller's
    complete plan. Insert only the planned inactive hidden parent, invoke only
    the registered creator with bounded identity context and normalized values,
    reload through the registered loader, and require exact parent/package
    postconditions. Commit the parent, package row, core `create` revision, and
    package `baseline` revision together. Roll back stale plans, creator output,
    exceptions, buffer changes, false returns, partial writes, postcondition
    mismatch, and either ledger failure. Keep forms/endpoints, parent-metadata
    editing, public placement, delete, audit workflow, and activation
    eligibility separate.
24. Completed permission-enforced parent-metadata prerequisite: expose one
    read-only state only after the exact view grant, enabled manifest/runtime
    binding, inactive hidden unrouted shell, valid package-loader result, and
    current core revision all agree. Require the exact edit grant and caller
    state hash before an atomic write; serialize lifecycle and theme changes,
    lock the installation and parent, recheck every condition, change only
    title, active-theme layout, and language, preserve the full shell and
    package state, and commit one core `save` revision. Add no revision for an
    unchanged submission and roll back stale, revoked, unsupported-state,
    postcondition, or revision failure. Keep UI/endpoints, public placement,
    activation, delete, audit workflow, and package-value writes separate.
25. Completed display-only component revision-history UI prerequisite: accept
    only the bounded value-free history already returned after the exact view
    grant and enabled binding, require its newest state to match the caller's
    current core-owned hash, escape fixed metadata, and distinguish current,
    matching, and restore-check-required entries. Fail closed on empty, stale,
    reordered, malformed, or value-bearing input. Expose no form, button,
    link, hash, package value, restore preflight, restore action, endpoint,
    audit write, public placement, or activation behavior.
26. Completed read-only component-delete preflight prerequisite: allow at most
    one optional registrar-bound deleter for a declared editor with one to eight
    package-owned transaction tables; require exact view/delete grants, enabled
    ownership, the inactive hidden unrouted parent shell, caller-supplied parent
    and package state hashes, current core revision evidence, the latest
    integrity-valid package revision, and InnoDB support; and return a
    deterministic value-free plan. Invoke no deleter, open no transaction, and
    keep the atomic runner, endpoint, form, control, audit event, public
    placement, activation, uninstall, and purge behavior separate.
27. Completed activation-blocked atomic component-delete runner: reject
    caller-owned transactions, revalidate the exact value-free plan under the
    shared lifecycle/theme and enabled-binding locks, capture forced duplicate
    package/core `delete` snapshots, invoke only the registered deleter, verify
    every declared package table has no row for the parent, then remove SEO and
    the exact inactive hidden parent. Roll back parent, SEO, package data, and
    both attempted revisions after stale evidence, callback containment,
    partial deletion, transaction loss, ledger failure, or postcondition
    failure. Retain both immutable ledgers after success and expose no endpoint,
    form, control, audit event, media deletion, uninstall/purge, public
    placement, or activation behavior.
28. Completed operational existing-record editor: expose an authenticated,
    CSRF-protected core form only after the persisted parent, enabled package,
    request-local runtime owner, manifest schema, loader, and fresh exact
    view/edit grants agree. Accept only the numeric core record id, current
    state hash, and schema field values; derive package and component identity
    again server-side; and save only through the existing atomic writer. Keep
    creation/deletion controls, restore actions, public placement, activation,
    grant management, and audit workflow separate.
29. Completed public-route prerequisite: dispatch only an exact unencoded
    static path from an enabled request-local registrar; permit public `GET`
    with `csrf: not-applicable`; pass bounded typed query data; accept a typed
    result; and emit only core-owned JSON. Member, unsafe-method, placeholder,
    administrator, HTML, redirect, upload, session, server, and database
    surfaces remain closed, and current enablement profiles still reject all
    route-bearing packages.
30. Completed display-only administrator-tool prerequisite: map one provided
    tool to one declared permission through closed manifest metadata, require
    the exact enabled registrar owner and a fresh case-sensitive per-client
    grant, pass only the tool id and numeric actor in a final request, and
    accept only bounded plain text for core-owned escaped rendering. Protect
    the endpoint with administrator session plus POST/CSRF checks. Owner,
    lifecycle and legacy grants imply nothing; package HTML, links, forms,
    actions, writes, sessions, request globals, database connections, uploads,
    redirects, arbitrary headers, grant management, and tool-bearing package
    enablement remain closed.
31. Completed non-executing setting-value prerequisite: normalize only a valid
    data-only settings schema; require exact type-correct non-secret defaults;
    validate one closed object with strict scalar types, bounds, choices,
    locator formats, and exact missing/unknown reporting; and return opaque
    lowercase `config:` secret references separately from ordinary values.
    Resolve no secret, access no database, authorize no actor, render no form,
    execute no package, persist no setting, and change no activation gate.
32. Completed per-client settings storage and read-only write preflight:
    install only an empty generic table; bind each operational setting to an
    explicit package-declared permission; require fresh exact grants, trusted
    installed package identity, supported lifecycle state, complete typed
    values, valid current rows, and deterministic state/plan hashes. Persist
    no value, resolve no secret, invoke no package, render no form, and change
    no lifecycle or activation state.
33. Completed internal atomic settings persistence: refuse caller-owned
    transactions; serialize with lifecycle/package and row locks; recreate and
    compare the exact plan; replace the complete normalized configuration;
    reload its target hash/count; and atomically record one bounded value-free
    audit fact. Treat exact state as a no-op and roll back on all drift, write,
    postcondition, audit, or injected failures. Add no UI, endpoint, secret
    resolution, package execution, or activation eligibility.
34. Completed non-executing secret-reference availability evidence: merge and
    validate bounded server-local opaque-reference declarations; revalidate the
    complete typed package configuration; bind package, configuration, and
    declaration state into deterministic hashes; and report only counts and
    missing setting keys. Return no reference identifier or secret value, read
    no database or secret, execute no package, and change no activation gate.
35. Completed core-only authorized current-setting read model: recheck trusted
    package/registry identity and supported lifecycle state; require an exact
    fresh package grant per declared operational setting; return only each
    authorized non-secret stored/default/unset typed value and a visible-model
    hash; and report only configured state for secret-reference settings. Fail
    closed without a partial model on storage, schema, identity, lifecycle, or
    grant drift. Add no UI, endpoint, write, package execution, secret lookup,
    reference disclosure, or activation eligibility.
36. Completed non-executing namespaced asset planning: accept only a trusted
    manifest surface's package-owned CSS/JavaScript declarations; require
    safe `assets/` paths, checksums, and type/location pairing; derive
    checksum-versioned package URLs; bind exact sorted rows into a plan hash;
    and render only escaped core-owned tags after revalidation. Read no package
    file, serve no response, inject no document markup, execute no package,
    access no database, or change lifecycle/activation.
37. Completed read-only immutable asset-delivery preflight: claim only an exact
    checksum-versioned reserved CSS/JavaScript URL; revalidate the complete
    manifest inventory, enabled/current registry state, recreated surface plan,
    no-symlink package containment, current length, and SHA-256; and return
    internal delivery evidence only. Serve no byte, emit no header or markup,
    execute no package PHP, write no state, and add no injection or activation
    path.
38. Completed core-owned static immutable asset endpoint: intercept the
    reserved namespace before theme, session, or package runtime bootstrap;
    rerun current-request preflight; serve only checksum-matching CSS/JavaScript
    bytes up to 4 MiB through `GET`/`HEAD` with fixed immutable-cache and
    `nosniff` headers; and return generic `404`/`503`/`405` responses without
    paths, preflight evidence, package output, execution, mutation, or markup
    injection.
39. Completed core-owned public/admin document injection: after the existing
    request runtime bootstrap, re-discover trusted manifests and current
    registry evidence without loading package PHP; revalidate both asset
    surfaces for every enabled package; add public CSS at `head` and public
    JavaScript at `body-end`; add administrator counterparts only when the
    existing signed-in overlay is present; and emit no package tags for any
    catalog, registry, integrity, plan, or document-boundary failure. The
    planner does not invoke a registrar, write state, or relax activation.
40. Completed non-executing administrator tool write-action preflight:
    validate a separate data-only action contract that maps one registered
    tool to one unique action, explicit permission, `POST`, and required CSRF;
    require matching enabled request-local tool/action owners, a fresh exact
    package grant, and one positive integer target record; and return only
    deterministic contract and plan hashes. It invokes no package callback,
    reads no package record, starts no transaction, writes no state, renders no
    form, exposes no endpoint, and does not alter enablement eligibility.
41. Completed internal atomic administrator tool action runner: require fixed
    `once-per-target` idempotency, one declared package-table set and read-only
    state loader, form an opaque exact state-aware plan in a rollback-only
    transaction, revalidate it under lifecycle/package locks, reserve the
    per-client action/target ledger key before callback invocation, contain the
    action and state reload, require an exact changed postcondition, and commit
    only the package change, ledger row, and value-free audit fact together.
    It accepts no request/session data.
42. Completed protected unlinked administrator action endpoint: require POST,
    a current database-backed administrator session, and CSRF before parsing
    only a declared tool, action, and canonical positive target; derive the
    state-aware plan only on the server; invoke only the scoped core bridge;
    return no package, actor, target, plan, or state values; and leave all
    administrator controls, forms, and public routes absent.
43. Completed core-owned ordinary add-on settings editor and endpoint:
    discover only validated data-only manifests; require an authenticated
    administrator and current CSRF; render escaped core-owned controls for
    exact package-declared permissions; bind a fresh plan hash; decode strict
    ordinary scalar values; preserve opaque secret-reference rows without
    disclosure; and delegate only to the existing atomic writer. Stale plans,
    invalid/nested/unknown/secret-bearing submissions, permission drift,
    storage drift, and writer failures fail closed. The endpoint remains
    unlinked from administrator navigation, executes no package PHP, resolves
    no secret, and changes no lifecycle or enablement state.
43. Defined the generic public-mutation boundary: reserve any future
    static-POST anonymous add-on write for a separately declared and
    core-owned CSRF, scalar-validation, rate/idempotency, transaction, and
    value-free-response path. This documentation-only contract adds no route,
    manifest field, cookie/session, handler, ledger, package, or enablement
    behavior.
44. Completed the optional closed `publicMutationContracts` declaration and
    value-free deterministic preflight: it binds one static public POST/CSRF
    route to fixed scalar, anonymous, idempotency, privacy, rate-limit,
    package-table, postcondition, audit, and outcome metadata; it invokes no
    package code and adds no dispatcher, endpoint, emitted cookie/header or
    session access, database access, ledger, package behavior, or enablement
    change.
45. Completed a separate read-only public-mutation live-data preflight: it
    joins current trusted `installed_disabled` package evidence with the
    declared migration, package-table, typed-setting, and opaque
    secret-availability state for one client, returning only hashes and
    counts. It can attest exact core subject/CSRF/rate-limit/idempotency/execution
    storage but does not itself issue values, dispatch a request, resolve a
    secret, execute package code, change lifecycle, or write package state.
46. Completed the internal core-only anonymous-subject and CSRF foundation:
    two empty per-client tables retain only SHA-256 digests of random 256-bit
    values; a future host-only `Secure`, `HttpOnly`, `SameSite=Strict` cookie
    descriptor expires after 30 minutes, and declaration/database-scoped CSRF
    values expire after 10 minutes. There is no public dispatcher or endpoint,
    emitted cookie/header, browser cookie/session access, package execution,
    Store Lite package, or activation
    change.
47. Completed the internal core-only fixed-window rate-limit foundation: one
    empty per-client core table stores only an opaque subject relation, a
    declaration/database SHA-256 scope, window/expiry facts, and bounded count.
    An internal core claim permits at most 12 requests per 60 seconds for one
    client, declared route, and subject; it rejects caller-owned transactions
    and unavailable storage. There is no request parser, dispatcher, emitted
    cookie/header, package execution, package mutation,
    Store Lite package, or activation change.
48. Completed the internal core-only opaque idempotency-key foundation: one
    empty per-client core table stores only an opaque subject relation, a
    declaration/database SHA-256 scope, a SHA-256 key digest, and expiry facts.
    The internal issuer/resolver handles only a 10-minute key for one client,
    declared route, and subject; it refuses caller-owned issuance transactions.
    Its issuer/resolver does not itself consume a key or return a replay result.
49. Completed the internal core-only atomic public-mutation transaction runner:
    one fifth empty per-client ledger holds only the idempotency-key relation,
    keyed HMAC command/state evidence, a bounded `accepted` / `unchanged`
    outcome, and completion time. A current trusted first-party runtime binding
    can be revalidated under lifecycle/package locks; subject, CSRF, key, rate,
    declared InnoDB tables, and server-derived postconditions are then committed
    with package state and one value-free anonymous audit fact. Exact replay,
    conflicting commands, output/exception/rollback failures, drift, and audit
    failure refuse or roll back. There remains no public dispatcher, response
    emitter, browser behavior, richer enablement profile, package fixture, or Store Lite
    package.
50. Completed the pure core-only public-mutation response contract: it maps
    only the fixed `accepted` / `unchanged` outcomes and five generic refusal
    cases to exact no-store/nosniff JSON envelopes with a closed header set.
    A replay retains only the original outcome. The model reads no request,
    cookie, session, database, or package state and emits no HTTP response; it
    creates no route, dispatcher, enablement profile, package fixture, or Store
    Lite behavior.
51. Completed the pure core-only declared-form decoder: one validated in-memory
    manifest declaration plus canonical URL-encoded package fields yields only
    a sorted typed scalar map or no values. Duplicate, nested, unknown,
    malformed, noncanonical, out-of-bounds, and oversized input fails closed;
    there is no HTTP metadata, request-global, cookie/session, database,
    runtime/package, route, response, enablement, package fixture, or Store
    Lite behavior.
52. Completed the pure core-only HTTP request-envelope normalizer: one
    server-configured canonical HTTPS origin, exact static POST path, complete
    header list, and raw body yields opaque subject/CSRF/idempotency evidence
    only after same-origin, canonical content metadata, fixed token, and
    body-size validation. It has no PHP-global, endpoint, response, session,
    database/runtime/package, route, enablement, fixture, or Store Lite path.
53. Completed the private core-only static mutation-route selector: one exact
    un-decoded path can bind only to a current registrar-owned public route,
    mutation handler, and state loader. Ambiguous or incomplete bindings fail
    closed without package invocation or owner disclosure. It has no request
    globals, runtime bootstrap, database, front-controller route, response,
    browser state, enablement, fixture, or Store Lite path.
54. Completed the non-routable core-only server request-facts adapter: it
    resolves a canonical HTTPS origin only from operating-system/local
    configuration, reads only the current method/raw target, and requires a
    later server integration to attest one complete fixed security-header capture.
    It rejects associative header maps and does not read a body stream, claim
    a route, invoke a package, emit a response/cookie, create browser state,
    or alter enablement, fixture, Store Lite, or client state.
55. Completed the core-only non-routable public-mutation response emitter: it
    accepts only the existing exact fixed core envelopes, refuses to run after
    output starts, clears and sets only their fixed no-store/nosniff JSON
    headers, and emits only their fixed bytes. It reads no request/cookie/
    session state, database, runtime, or package code and is not linked to the
    front controller, so it creates no endpoint, browser cookie, enablement,
    fixture, Store Lite, or client-state path.
56. Completed the pure core-only public-mutation subject-cookie serializer: it
    accepts only the exact issuer descriptor shape and constructs one fixed
    future host-only value with a 30-minute `Max-Age`, `Path=/`, `Secure`,
    `HttpOnly`, and `SameSite=Strict`, without `Domain` or `Expires`. It emits
    no cookie/header and reads no request/cookie/session, database, runtime,
    or package state; it has no front-controller endpoint, browser
    issuance/rotation, enablement, fixture, Store Lite, or client-state path.
57. Completed the optional non-routable Caddy/FrankenPHP ingress-attestation
    source and paired unlinked PHP verifier. The handler strips client-supplied
    internal capture headers on every request and conditionally HMAC-signs only
    bounded `/addons/` POST method/target, body length/hash, and fixed
    security-header facts. It has no compiled binary, active Caddyfile, default
    server change, linked endpoint, cookie issuance, package invocation,
    enablement, fixture, Store Lite, or client-state path; per-client
    deployment and production browser review remain later gates.
58. Completed the unlinked core-owned public-mutation dispatcher composition:
    explicit method/target/capture facts select one registrar-bound route,
    require attested POST transport, verify the opaque subject and CSRF before
    decoding declared scalar fields, invoke the existing atomic runner, and
    return only the fixed response model. Its focused fixture proves runtime,
    method, transport, binding, and callback isolation without linking the
    front controller, emitting a response/cookie, changing enablement, or
    adding Store Lite/client state. The supported-server rehearsal and the
    separate core lifecycle bridge are complete; the non-executing deployment
    profile and response-owner composition are complete, while production
    deployment review remains.
59. Completed the disposable supported-server dispatcher rehearsal: a temporary
    pinned FrankenPHP/Caddy image (with `mysqli` added only to that proof image)
    and fresh MySQL database carried a secret-guarded fixture request through
    the real attester, PHP verifier, core dispatcher, atomic runner, and fixed
    emitter. Accepted/replay, forged-header replacement, `GET` refusal,
    withheld-attestation refusal, idempotency conflict, and exact execution,
    activity, subject, CSRF, idempotency, and rate evidence passed before all
    containers, network, image, database, package marker, and build context
    were removed. It remains a test-only rehearsal; the core-owned
    subject-cookie lifecycle and non-executing deployment profile are now
    proven, while trusted-origin/HMAC provisioning, production deployment, and
    browser evidence precede any front-controller link or Store Lite
    enablement.
60. Completed the core-owned browser subject-cookie lifecycle bridge. Its
    transactional `ensure`, `clear`, and `rotate` operations return only fixed
    host-only cookie descriptors, refuse malformed input and active caller
    transactions, and expire the old subject and its CSRF evidence before
    committing a distinct replacement. The 18-assertion disposable fixture and
    supported-server HTTP proof cover issuance, resolve-without-reissue,
    fixed clearance, old-token refusal, malformed input, and cleanup. This
    does not emit a production header, link the front controller, or authorize
    a client deployment; production deployment remains the next gate.
61. Completed the non-executing per-client public-mutation deployment profile.
    One operator-owned packet now validates a separate client database and
    canonical HTTPS origin, pinned FrankenPHP/Caddy versions, fixed
    process-environment HMAC and trusted-origin sources, attestation-before-
    PHP route order, core response/cookie ownership, the fixed host-only
    subject-cookie policy, clean-starter isolation, and disabled dispatcher,
    package, and Store Lite flags. It returns only a deterministic non-secret
    hash, refuses request-derived trust, policy/version/route drift, secret-
    shaped fields, and starter-database reuse, and does not load or apply a
    deployment. Production deployment and browser review remain before any
    front-controller link.
62. Completed the core-owned non-emitting response-owner composer. It binds a
    valid deployment profile to the fixed response envelope and zero, one, or
    ordered clear-then-set subject-cookie descriptors. Arbitrary headers,
    package/theme ownership, linked-dispatcher profiles, cookie-policy drift,
    invalid lifecycle state, and opaque cookie tokens in the response body
    fail closed. The 14-assertion fixture proves no request/global/session/
    header mutation; the composer remains outside the front controller.
63. Completed the non-executing per-client deployment-review packet. It binds
    the deployment-profile hash to non-secret Caddy/FrankenPHP/TLS/proxy
    artifact hashes, process-environment trusted-origin/HMAC provisioning and
    old-key-revocation evidence, and fixed desktop/mobile browser results. The
    17-assertion fixture rejects secret values, starter-resident artifacts,
    request-derived trust, browser errors/state changes, forged review hashes,
    deployment, and dispatcher linking. Actual deployment and browser capture
    remain the next gate.
64. Added the installation-shaped HTTPS deployment rehearsal command. It
    stages only the reviewed integration, uses an external generated
    certificate, proves process-environment HMAC replacement across restart,
    and captures fixed desktop/mobile browser evidence into a non-secret packet
    outside the starter. A successful Docker/browser run and client-specific
    review remain required; it does not touch Adriana or link the dispatcher.
65. Completed the core-owned server-local secret resolution and reference
    replacement boundary: validate bounded ignored-local and operating-system
    value inventories separately from the explicit opaque-reference allowlist;
    resolve only a declared reference through an internal by-reference value;
    and accept exact authenticated secret-setting maps through an unlinked
    endpoint that revalidates trusted package identity, current grants, stale
    plans, and complete typed configuration before delegating to the locked
    atomic settings writer. Initial missing-secret binding, replacement,
    unavailable-reference refusal, no-op behavior, value-free audit detail,
    and cleanup are proven. It returns no secret bytes, executes no package
    PHP, changes no lifecycle or enablement state, and does not make Store Lite
    ready; richer package-runtime secret surfaces and a polished secret UI
    remain later gates.

66. Completed the secret-capable registration-only service profile. Its
    preflight requires a service-only manifest with secret-reference settings,
    complete per-client storage, exact installed/registry identity, and
    allowlisted server-local values, while returning only counts, hashes, and
    readiness. Owner enablement revalidates those facts and the registrar under
    the shared lifecycle lock. Enabled package services receive only their own
    resolved settings through `RED_Addon_Service_Request::secret()`; core
    rejects secret-bearing result keys or strings and keeps the access object
    out of context snapshots, plans, audits, responses, and browser state.
    Dependency-free and disposable bootstrap fixtures plus the full 45-
    migration acceptance suite passed, with temporary databases and grants
    removed. Store Lite remains blocked behind richer persistence, editor,
    route, asset, administrator, and commerce contracts.

67. Completed the Store Lite Gate 2A product/variant contract fixture. The
    package-owned record boundary now distinguishes a simple product with one
    sellable SKU/price from a variable parent with explicit complete option
    tuples. It fixes one installation currency, integer minor-unit money,
    bounded text and identifiers, three option groups, sixteen values per
    group, and 128 variants per parent. The dependency-free 20-assertion
    fixture rejects floats, unknown fields, mismatched currencies, duplicate
    identities/SKUs/tuples, stale option values, and partial normalized output.
    The future cart declaration also carries only an optional public variant
    reference; no route, package, table, migration, or Store Lite state was
    added to core.

68. Completed the non-executing operational administrator-form declaration
    and read-only plan. The closed manifest metadata binds one provided tool to
    a unique form id, exact package permission, `POST`, required CSRF policy,
    fixed JSON encoding, and a body limit no larger than 256 KiB. The plan
    requires current request-local tool ownership and a fresh exact grant, then
    returns deterministic value-free evidence without invoking a package
    callback, reading a body or request/session global, consuming CSRF,
    rendering HTML, starting a transaction, writing state, exposing an
    endpoint, changing enablement eligibility, or adding Store Lite behavior.

69. Completed the bounded administrator-form field schema and core-owned
    display-only preview. The closed vocabulary reuses scalar editor controls
    and permits at most two collection levels, 128 rows per collection, 32
    fields per row, and 200 fields across the schema. It can represent a simple
    product or option groups, values, variants, and exact option selections
    without admitting arbitrary JSON schema, conditions, package templates,
    HTML, JavaScript, or callbacks. Core renders only escaped disabled controls
    and collection templates; it loads no values, performs no authorization,
    creates no `form`, names, submit control, endpoint, request parser, CSRF
    operation, package execution, database access, or Store Lite behavior.

70. Completed permission-scoped administrator-form current-value loading. A
    schema-bearing form now requires one exact registrar-bound loader. Core
    repeats current enabled ownership and fresh binary package-permission
    checks, gives trusted first-party package code only the current-client
    connection plus tool/form/positive numeric target identity, validates one
    complete closed nested typed graph, contains output/HTTP-state failures,
    and binds the normalized values to package/tool/form/target/contract
    SHA-256 evidence. Only that exact result may populate escaped disabled
    nameless core controls and current collection rows. No administrator
    identity reaches the provider, and no editable control, request body, CSRF
    operation, endpoint, write, Store Lite package, table, or state is added.

71. Completed the validation-only administrator-form JSON adapter. The
    unlinked core endpoint requires POST, an authenticated current
    administrator, and header CSRF before it opens the request-body stream. It
    accepts only exact `application/json`, canonical content length, the global
    256 KiB ceiling, and one canonical closed root containing tool, form,
    positive target, current-state SHA-256, and values. Core applies the
    manifest body limit before value-provider invocation, repeats fresh form permission
    and current-value checks, refuses stale state, validates the complete
    nested value graph, and derives opaque values and actor/contract/target/
    state-bound plan hashes. It returns only a bounded generic validation or
    refusal envelope. No writer registration, package mutation, editable
    control, Save action, Store Lite package, table, or state is added.

72. Completed optional administrator-form writer registration and the internal
    atomic stale-state runner. Only a schema-bearing declared form may register
    one writer, and that writer must declare one to eight package-owned InnoDB
    tables; reserved, duplicate, undeclared, and nontransactional storage fails
    closed. The value-free write plan binds the validation plan, package
    version, sorted table set, actor, target, permission, contract, current
    state, and submitted-values evidence. The runner recreates it under shared
    lifecycle and package locks, repeats fresh grant/current-value checks,
    refuses stale, replayed, substituted, version-drifted, and contract-drifted
    evidence, contains one immutable typed writer request, reloads the exact
    postcondition, and commits one value-free `addon.form.saved` audit fact in
    the same transaction. Unchanged submissions invoke no writer or audit.
    The validation endpoint remains disconnected, and no editable renderer,
    Save control, Store Lite package, migration, table, or state is added.

73. Implemented the core-owned operational administrator-form editor and Save
    bridge. The POST-only edit endpoint requires a current administrator and
    header CSRF before accepting exactly one tool, form, and positive target;
    it repeats exact enabled writer/table ownership plus fresh form permission,
    reloads the complete current value graph, and renders only escaped core
    scalar and bounded two-level collection controls. The core controller
    preserves integer, boolean, nullable, object, and ordered-list types,
    enforces manifest collection bounds, and sends canonical JSON with header
    CSRF to a separate POST-only Save endpoint. That endpoint delegates only to
    the accepted atomic runner and returns `saved`, `unchanged`, or a bounded
    value-free refusal before the editor reloads current state. The 21 focused
    assertions, real authenticated HTTP guards, full 45-migration disposable
    acceptance, exact cleanup, and local Chrome desktop/mobile rendered-browser
    inspection pass. The 1280px and 390px checks show zero horizontal overflow,
    successful collection add/remove plus typed Save/reload behavior, and zero
    console, page, or failed-request errors. No Store Lite navigation, target provider, package, migration,
    table, or product state is added.

Each phase requires its own migration, rollback path, relevant authorization
tests, disposable-database acceptance coverage, and desktop/mobile
administrator verification.

After the SEO launch gate, later tracks can be approved independently: roles
and publishing workflow, Member Access, notifications, social adapters, or the
add-on platform. The five example vertical packages remain optional. If that
track is approved, its order is Store Lite, Events Calendar, Appointments,
Donations, and Restaurant Ordering. If private folders are scheduled for
activation, Member Access must pass its route-enforcement and leakage gates
before the administrator exposes an operational private setting.
