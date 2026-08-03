# RED-CMS 5.1 Direction

Status: implementation in progress. Per-page SEO compatibility, including the
approved constrained JSON-LD core, non-executing add-on trust validation,
persisted Owner authorization, per-client registry/migration-ledger storage,
read-only reconciliation, guarded server-local installation into a disabled
state, and constrained Owner-authorized atomic enablement for registration-only
service, core-rendered default public component, and combined default-component
plus registration-only-service profiles are implemented.
Owner-authorized non-executing atomic disablement with enabled-dependent
refusal is also implemented. Fresh isolated Adriana JSON-LD verification
and hosted Schema.org validation also pass. The separate Adriana production
backup, migration, launch verification, and rollback gate are complete without
copying client assets or data into the starter. Fixed add-on registration,
fail-closed page-request loading, and safe core-owned default dispatch for an
enabled manifest-declared component are implemented for already-recorded
enabled packages. Typed internal service invocation, exact static public
`GET` routes, and display-only permission-scoped administrator tools now have
separate fail-closed dispatch boundaries. Adapters, writable route/tool
actions, atomic settings writes, a static package-asset endpoint/injection,
upgrades, uninstall/purge,
member access, publishing, payment, and integration controls remain inactive.
The Store Lite product and security boundary is defined. The first generic
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
implied by these prerequisites. Writable route/tool actions, settings
UI/endpoints, and package asset loading are not operational.
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
failure, or injected late failure roll back. No settings UI/endpoint or secret
lookup is added.
The separate server-local availability boundary validates only a bounded list
of opaque `config:` references declared by the operator, revalidates the
complete typed configuration, and returns deterministic counts, missing
setting keys, and fingerprints. It returns no reference identifier or secret
value, reads no database or secret, executes no package, and changes no
activation gate. Actual secret lookup, settings UI/endpoints, and package asset
loading remain unavailable.
The first namespaced asset prerequisite now reduces one trusted manifest
surface to a deterministic CSS/JavaScript plan. CSS must be beneath `assets/`
and load at `head`; JavaScript must be beneath `assets/` and load at
`body-end`. Core derives checksum-versioned package URLs and renders only
escaped tags after revalidating the aggregate plan hash. It reads no package
file, serves no response, injects no document markup, executes no package,
opens no database, and changes no activation gate. Immutable delivery and
public/admin injection remain unavailable.
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
separately distributed and is not bundled with the clean starter.

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
default public component package, or a default public component combined with
registration-only services. All exclude migrations, settings, routes, jobs,
public or administrator assets, administrator tools, adapters, and outbound
hosts. Either component profile clears theme compatibility only through core's
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
    editor, routes, administrator tools, settings, the static asset
    endpoint/injection, and live-data behavior remain blocked.
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
35. Completed non-executing namespaced asset planning: accept only a trusted
    manifest surface's package-owned CSS/JavaScript declarations; require
    safe `assets/` paths, checksums, and type/location pairing; derive
    checksum-versioned package URLs; bind exact sorted rows into a plan hash;
    and render only escaped core-owned tags after revalidation. Read no package
    file, serve no response, inject no document markup, execute no package,
    access no database, or change lifecycle/activation.
36. Completed read-only immutable asset-delivery preflight: claim only an exact
    checksum-versioned reserved CSS/JavaScript URL; revalidate the complete
    manifest inventory, enabled/current registry state, recreated surface plan,
    no-symlink package containment, current length, and SHA-256; and return
    internal delivery evidence only. Serve no byte, emit no header or markup,
    execute no package PHP, write no state, and add no injection or activation
    path. The static endpoint and public/admin injection remain separate.

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
