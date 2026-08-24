# RED-CMS 5.1 Direction

Status: RED-CMS 5.1.0 was released on 2026-08-15 as
[`v5.1.0`](https://github.com/orojas01-glitch/redcms/releases/tag/v5.1.0)
after its full disposable current-schema acceptance gate passed. Store Lite
0.1.31 completed the optional add-on model's basic-demo proof in its isolated
demo installation and remains outside the clean starter. Per-page SEO
compatibility, including the
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
enabled packages. Typed internal service and adapter invocation, exact static public
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
implemented, and the core-owned dispatcher composes the explicit request,
route, subject/CSRF, form, runner, and fixed-response contracts. A fail-closed
front-controller bridge now calls it only after the explicit local endpoint
flag, trusted HTTPS origin, and ingress HMAC key pass; core also owns raw-cookie
validation, one request-local page subject, fixed controller delivery, and the
closed response/cookie emitters. This adds no administrator-session access,
package enablement, Store Lite activation, or client deployment. A separate Docker-only
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
The optional post-release payment-adapter path now has all five closed P3A core
slices and all four separately distributed Store Lite P3B slices. P3A-1
recognizes only the exact adapter manifest and declaration-only
server-signature route. P3A-2 adds read-only Owner, same-database enabled Store
Lite, immutable migration-ledger, and InnoDB table evidence. P3A-3 refreshes
that evidence immediately before executing the fixed integrity-checked
registrar, requires exactly the declared adapter and route registrations, and
discards the request-local registry without invoking either handler or
publishing runtime. P3A-4 binds that evidence to a closed, unlinked exact-POST
transport contract and preserves bounded raw body plus complete signature
header bytes for a future provider verifier while exposing only value-free
metadata. It reads no live request, parses no JSON, verifies no signature,
invokes no handler, resolves no secret, opens no database/network path, and
publishes no route. P3A-5 adds the separate Owner-authorized, dry-run-first,
backup-bound atomic runner. It requires exact stored configuration and
value-free availability evidence for both opaque secret references, recomputes
the full P3A plan under locks, and commits only the exact enabled transition
plus one bounded audit fact. It still invokes no handler, resolves no secret
value, exposes no endpoint, or contacts a provider. Store Lite 0.1.32 through
0.1.35 then add the provider-neutral transition decision, append-only history
migration, transactional writer/service, and disposable lifecycle rehearsal.
P3A and P3B are complete. The separately distributed
`redcms.store-lite-stripe-checkout` adapter has completed P3C plus P3D-1 through
P3D-5 offline lifecycle and synthetic-secret bootstrap proof. Core P3D-6 now
adds the reusable typed adapter invocation boundary without changing the
external refusal-only handler. No provider access or client deployment exists.
Operational adapters, writable route/tool actions, richer package-runtime
secret surfaces, uninstall/purge,
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
Delete endpoints and activation eligibility remain absent. Core now exposes a
permission-scoped Add Content card and protected component-creation form; its
POST/CSRF Create endpoint allocates the unused numeric parent id server-side
and delegates only to the existing exact atomic runner. Audited public
placement is available only through the core-owned POST/CSRF control
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
lifecycle UI, uninstall, or purge command exists.

The separate server-local upgrade command is also dry-run first and requires
the exact persisted Owner `addons.upgrade` capability. It accepts only an
`installed_disabled` package and a strictly higher trusted same-type target;
historical migration ids/paths/checksums must be unchanged and current stored
setting definitions must remain compatible. Apply holds the lifecycle and
package locks, never includes `addon.php`, applies only pending package SQL,
and ends disabled. Since MySQL DDL can commit independently, a failure remains
visible and non-loadable as `upgrade_failed` with the old registry identity and
exact completed-migration ledger. Explicit resume revalidates the same target
and applies only remaining migrations before the target identity and bounded
completion audit commit atomically. This does not provide downgrade, uninstall,
purge, or arbitrary migration rollback.

The real external Store Lite 0.1.28 to 0.1.29 Release C2 rehearsal now proves
this contract with two append-only package-owned order-list indexes. It stages
the historical package from its exact Git commit, runs all current core
migrations in a bounded disposable database, preserves one real order plus all
five configured settings through a forced second-migration failure, and resumes
only the remaining migration. The result is 0.1.29 `installed_disabled`, with
the configured primary database, clean starter, hosted demo, and unrelated
client installations unchanged.

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
16a. Completed the first operational package-permission workflow as a
    server-local command rather than a web UI. It discovers only validated
    manifest permissions without loading package PHP, requires a fresh Owner
    plus exact database, actor, target, and dry-run plan confirmations, and
    atomically grants or revokes one permission with one bounded audit fact.
    It does not let a package grant itself, infer access from lifecycle
    authority, change package lifecycle or settings, or cross a client
    database boundary.
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
    console, page, or failed-request errors. No Store Lite package, migration,
    table, or product state is added to the starter.

74. Completed an isolated Store Lite existing-product browser rehearsal without
    changing normal package enablement. The runner stages the clean core and
    separately distributed Store Lite 0.1.8 package in a temporary project,
    applies all 46 core migrations and the package migration inventory to one
    fresh schema, records an acceptance-only enabled registry fixture, and
    grants only `store.products.manage` to a disposable administrator. Chrome
    at 1280x900 and 390x844 follows the visible Tools -> Products -> Edit path,
    persists a title, integer minor-unit price, and stock change through the
    protected Save bridge, and reloads that exact state. Forty-seven browser
    checks require the simple banana and variable T-shirt targets, permission-
    filtered Orders absence, no horizontal overflow, and zero console, page,
    request, or HTTP errors. Database verification requires one package
    `product.updated` event, one value-free core `addon.form.saved` audit fact,
    and an unchanged T-shirt graph. Cleanup removes the server, staged package,
    schema, and scoped grant and rechecks the retained primary fingerprint.
    This is acceptance evidence, not a production installer or an expansion of
    the currently allowed enablement profiles.

75. Implemented the separate target-free administrator-form Create bridge and
    proved it with Store Lite 0.1.9. A core-owned Add control appears only when
    one enabled package owns the tool, target/current loaders, initial loader,
    creator, permission, and declared InnoDB tables. The protected draft
    endpoint accepts exact tool/form identity only after administrator and
    header-CSRF verification. The protected JSON endpoint authenticates and
    verifies header CSRF before body I/O, then delegates the canonical
    initial-state-bound submission to the atomic creator. Store Lite derives
    one unavailable simple draft from installation currency, normalizes the
    completed simple or variable graph, inserts it inside the core transaction,
    and records `product.created`; core reloads the returned numeric target and
    records `addon.form.created` before commit. Desktop creates and reloads a
    third product, then completes the existing Save path; mobile verifies the
    three-record catalog and persisted state. Sixty-eight Chrome checks pass
    with no overflow or console, page, request, or HTTP errors. Focused core and
    package transaction tests also pass, and cleanup removes the temporary
    server, staged package, schema, and grant while preserving the configured
    primary fingerprint. The clean starter still contains no Store Lite code or
    business data, and normal richer-package enablement remains blocked.

76. Extended the core-owned default component renderer with an optional closed
    fact-card model. Existing title/summary output remains unchanged; a handler
    may additionally return at most twelve bounded plain-text label/value facts,
    which core escapes and renders as semantic description-list markup. Package
    HTML, links, controls, templates, output, and malformed facts remain refused
    through the existing static fallback. The separately distributed Store Lite
    0.1.10 package adds a pure presenter that re-normalizes one published simple
    or variable product and returns only title, summary, price, effective
    availability, and option-label facts. It has no database, request, runtime,
    cart, order, or mutation access. Placement persistence and the enabled
    Product component binding remain the next Store Lite gate.

77. Completed the separately distributed Store Lite 0.1.11 Product placement
    and runtime binding gate. One package-owned InnoDB relationship row binds
    one core `RED_Articles` parent to one existing package Product record using
    restrictive foreign keys. Exact loader/creator/writer/deleter callbacks
    participate only in the core-owned transaction, while the public handler
    reloads the complete normalized published product and returns only the
    closed presenter model. A fresh clean-core-plus-package rehearsal creates
    that relationship through the package callback and verifies the homepage
    fact card before login at 1280x900 and 390x844. All 80 Chrome checks pass,
    including semantic facts, server-derived price and availability, zero
    overflow, and zero console, page, request, or HTTP errors. Cleanup removes
    the staged server, schema, scoped grant, and project while preserving the
    configured primary fingerprint. This does not make the richer manifest
    normally enableable and does not add the still-missing user-facing Add
    component workflow, cart, order, route, or public mutation.

78. Implemented the generic core-owned Add component workflow and explicit
    language-homepage placement. Add Content advertises only components whose
    enabled request-local owner, manifest editor, loader, creator/table
    metadata, and fresh actor create grant all agree. The protected form is
    generated entirely by core, accepts only component/title/layout/language
    plus declared values, and allocates the unused positive content id on the
    server before delegating to the existing atomic inactive-creation runner.
    The separate placement plan now accepts either the unique active homepage
    for the component language or the existing unique active Article target;
    Homepage activation changes only `Sections`, `HomePosition`,
    `HomePositionOrder`, and `Active`, under the same lifecycle/theme/package/
    source/destination serialization and revision/audit postconditions. The
    Store Lite rehearsal starts with zero placements, follows Add Content ->
    Product -> Create component -> Homepage -> Place component, and then proves
    the public Product card at 1280x900 and 390x844. Eighty-seven Chrome checks,
    85 focused generic creation/article-placement assertions, exact create/
    baseline/move/audit evidence, and disposable cleanup pass. Store Lite
    remains separately distributed and not normally enableable; cart, orders,
    checkout, public mutation, and hosted payment remain later gates.

79. Completed Store Lite Gate 2B as a pure server-authoritative cart-line
    contract. The separately distributed Store Lite 0.1.12 resolver accepts
    only a public product identifier, integer quantity 1–100, and one required
    variant identifier for a variable product. The caller separately supplies
    the current complete server-loaded product and installation currency. The
    resolver repeats Gate 2A normalization, requires a published/available
    product and exact current sellable variant, and derives SKU, option labels,
    integer unit price/total, currency, stock evidence, and product-state
    SHA-256 without accepting browser-owned commercial values. Draft,
    unavailable, currency-drifted, malformed, mismatched, stale-variant,
    invalid-quantity, and insufficient-stock cases return no partial line. The
    package's 26 assertions and clean-core 21-assertion fixture pass without a
    database, route, request/session/cookie state, runtime service registration,
    cart persistence, inventory reservation, order, checkout, or enablement
    change. Package-owned cart/cart-line storage and atomic anonymous ownership
    remain the next Store Lite gate.

80. Completed Store Lite Gate 2C as internal package-owned cart persistence.
    The separately distributed Store Lite 0.1.13 package adds one unique cart
    per core-issued numeric anonymous-subject relation, exact product/optional-
    variant lines, and value-free before/after activity evidence. Raw subject
    tokens, cookies, requests, responses, CSRF values, and idempotency keys are
    not package state. An already-active core-owned transaction is required;
    the package locks current cart/line/product/variant rows, refuses stale cart
    state, repeats the server-authoritative resolver, verifies the full
    postcondition, records activity, and neither commits nor rolls back.
    Disposable migration and persistence suites prove exact table/foreign-key
    shape, simple and variable lines, additive quantity, server-derived money,
    subject isolation, refusal paths, forced late-audit rollback, deletion
    protection, cleanup, and unchanged retained-primary evidence. Core contains
    no Store Lite package or tables. Public dispatcher binding, Add-to-cart
    cookie/browser behavior, inventory mutation, orders, and checkout remain
    later gates.

81. Began Gate 2D2 with the generic core-owned form UI boundary. Given one
    validated public-mutation declaration, a bounded presentation model, a
    core form-instance id, and exact same-subject issued CSRF/idempotency
    results, core derives the static action and renders only escaped hidden
    identifiers, bounded integer inputs, declared identifier selects, one
    submit control, a polite status region, and a no-script notice. Opaque
    evidence stays in fetch-controller attributes rather than package form
    fields. The 20-assertion dependency-free fixture covers simple and variable
    shapes, accessibility, escaping, declaration drift, unknown/commercial
    fields, exact 128-choice acceptance and 129-choice refusal, malformed
    controls/options, cross-subject evidence, tampering, and
    zero request/database/emission/front-controller behavior. This slice does
    not load Store Lite, issue tokens/cookies, inject JavaScript, render a live
    Product control, link the dispatcher, or execute a public mutation. The
    separately distributed Store Lite field model and browser bootstrap remain
    the next Gate 2D2 steps.

82. Completed Store Lite Gate 2D2B in the separately distributed 0.1.15
    package. Its pure public cart-form presenter revalidates one complete
    product and returns only the declared product, quantity, and optional
    sellable-variant controls. Simple products and variable products with up
    to 128 explicit choices are supported; draft, unavailable, zero-stock,
    malformed, currency-drifted, or label-unsafe records fail closed. It emits
    no HTML, issues no browser authority, exposes no commercial facts, and is
    not yet invoked by the public Product component.

83. Completed Gate 2D2C as a core-owned form evidence bootstrap. Core validates
    the full declaration and presentation before ensuring or resolving an
    opaque subject, issuing one same-subject declaration/database-scoped CSRF
    token and idempotency key, and composing the existing form model. Exact
    compensation removes only evidence created by a failed attempt; forged
    lifecycle cleanup and caller-owned transactions are refused. The focused
    12-assertion disposable current-schema proof finishes with zero subject,
    CSRF, idempotency, schema, and grant residue. This remains unlinked from
    `index.php`: no package load, live Product control, JavaScript controller,
    response emission, or public mutation endpoint is added.

84. Completed Gate 2D2D1 as a core compatibility boundary for data-only
    component mutation presentations. The normalized public component model
    may now retain one exact route, mutation, submit label, and bounded field
    list alongside its existing display text and facts. Only hidden
    identifiers, positive-integer controls, and identifier selects with at
    most 128 unique choices are accepted; reserved commercial or authority
    names, extra keys, duplicate fields/options, unsafe labels, and forged
    selections invalidate the whole component view. The default renderer still
    emits display content only. No Store Lite package invocation, live form,
    browser evidence, controller, endpoint, cookie/header, or mutation is added
    by this subgate.

85. Completed Gate 2D2D2 after Store Lite 0.1.16 bound its pure mutation
    presentation to sellable Product component return models and core added the
    non-routable integration boundary. Core now requires exact component,
    route, mutation-handler, and state-loader ownership by one runtime package,
    derives a placement-bound form instance, bootstraps same-subject evidence,
    and returns escaped form markup plus the lifecycle descriptor for a later
    response owner. Display-only unavailable Products issue no evidence. The
    focused 12-assertion fresh current-schema proof invoked zero package
    callbacks and finished with zero schema/grant or subject/CSRF/idempotency
    residue. Request-global adaptation, cookie/header emission, script delivery,
    front-controller wiring, endpoint dispatch, and browser mutation remain
    Gate 2D2D3.

86. Completed Gate 2D2D3A as an unlinked core-owned browser controller. It
    validates the exact same-origin action, fixed header names, opaque evidence,
    POST encoding, and declared scalar form body before sending. The first
    submission captures and freezes one canonical command; only that exact body
    may retry with the same idempotency key after a transient network, rate, or
    availability failure. Opaque evidence is removed from DOM attributes after
    initialization and never enters cookie/storage/log/HTML sinks. A 19-
    assertion real-Chrome proof passed at `1440x1000` and `390x844`, covering
    accepted, unchanged retry, conflict, foreign-action, malformed-evidence,
    canonical `%7E` encoding, and zero page errors. A separate 9-assertion
    dependency-free source contract keeps the script unlinked. Response/cookie
    ownership and endpoint/front-controller activation remain Gate 2D2D3B;
    real Store Lite mutation QA remains Gate 2D2D3C.

87. Completed Gate 2D2D3B as the fail-closed supported-server endpoint and
    page-delivery bridge. `index.php` can now claim a reserved `/addons/`
    candidate before theme or session rendering only when an explicit local
    enablement flag, one canonical trusted HTTPS origin, and the process-
    environment ingress HMAC key all exist. The bridge composes the existing
    attested dispatcher and closed response emitter; unknown or unavailable
    mutation requests receive only generic bounded responses. Normal `GET`
    pages parse the raw subject-cookie header, coordinate one subject across at
    most 128 accepted component forms, append only core-rendered markup, deliver
    the fixed browser controller once, and emit only the fixed host-only cookie
    lifecycle after successful document assembly. Malformed duplicate cookies
    or request-local coordinator drift disable the form path. Focused evidence
    includes 10 endpoint assertions, 4 cookie-emitter assertions, 17 fresh-
    schema component/page assertions, the prior 19 real-Chrome controller
    assertions, and the complete disposable FrankenPHP/Caddy dispatch proof.
    The final tree also passed the complete 45-migration disposable acceptance
    suite with normalized schema signature
    `cb6e941861fc5ed74142f11b0f36536549a335f478b8214e613836f360501a3f`;
    the protected primary snapshot was unchanged and both temporary schemas and
    grants were independently confirmed at zero. The default starter flag
    remains false and no package, demo deployment,
    Store Lite data, or client database is activated. Real Store Lite desktop/
    mobile add-to-cart mutation QA remains Gate 2D2D3C.

88. Completed Gate 2D2D3C as the isolated supported-server Store Lite browser
    rehearsal. The opt-in runner pins external Store Lite 0.1.16 at revision
    `27b23dbfa6f84e966f9a5a69d290885dfa8bb604`, stages it with the clean core,
    derives the package migration order from the validated manifest, builds
    the reviewed FrankenPHP 1.12.4/Caddy 2.11.4 attestation module, and uses a
    fresh MySQL 8.4 container plus one-day localhost certificate. The existing
    administrator/product/component rehearsal passed 87 checks before real
    desktop and mobile Chrome passed 59 connected form, endpoint, cookie,
    controller, accessibility, responsive, accepted, exact-retry, conflict,
    invalid-input, simple-product, exact Size/Color variant, console, page, and
    network checks. The final isolated
    cart/line/quantity/package-activity/execution/core-audit state was exactly
    `4:4:6:4:4:4`; all app/database containers, network, image, build context,
    keys, certificate, and database were removed. Evidence remained outside
    the starter. The hosted demo and all client installations were unchanged.
    Gate 2D2 is complete; the next Store Lite milestone is a visible editable
    cart with view, quantity-update, and remove-line behavior.

89. Completed the generic read-only public-component subject
    context required by the Cart component. Core may resolve only an
    already-present anonymous subject and return its numeric internal ID to a
    package read model. It does not issue browser evidence, expose a cookie,
    create a cart, render Store Lite, add a mutation, or change a deployment.
    Package-owned cart presentation, quantity updates, and removal remained
    separate follow-up slices.

90. Completed a generic bounded public-component collection
    presentation. It accepts only one text label, one to twenty-four rows, and
    one to four text facts per row; core escapes and renders the semantic
    markup. It contains no Store Lite identifier, commercial calculation,
    package table, control, mutation, endpoint, or browser authority. The
    Cart component may use it for package-owned current cart lines.

91. Completed the placeable read-only Cart milestone with separately
    distributed Store Lite 0.1.19 at merged revision
    `e274c48b57b7c2f8ca33d1b13e554128275661be`. One package-owned placement
    stores only the core parent and bounded public title. The package asks the
    core read context for an already-present anonymous subject, loads only that
    subject's cart, applies its pure presenter, and returns the generic bounded
    collection model; a missing subject renders an empty Cart without issuing
    identity or writing state. The supported-server rehearsal created and
    placed Product and Cart components through the real administrator path,
    passed 100 administrator checks, then passed 76 desktop/mobile public checks
    proving empty and populated simple/Size-Color carts after real Add-to-cart
    requests. Exact final state remained `4:4:6:4:4:4`; all containers, network,
    image, build context, database, certificate, and secrets were removed. The
    hosted demo and every retained client installation remained unchanged.
    Quantity update and line removal are the next Store Lite gate.

92. Completed the generic data-only collection-row mutation-presentation
    prerequisite. One public collection row may retain one or two distinct
    closed presentations using the existing bounded identifier, integer, and
    select field vocabulary. Empty, overflowing, associative, duplicate,
    malformed, authority-bearing, or expanded row forms fail the complete
    component view closed. The default renderer still emits only escaped row
    titles and facts: it derives no form instance, issues no evidence, renders
    no control, invokes no package callback, opens no database, and changes no
    response or deployment. Separately distributed Store Lite 0.1.23 supplies
    the matching pure quantity/remove models; package read-model binding,
    core-owned evidence composition/rendering, and browser mutation QA remain
    separate follow-up gates.

93. Completed core-owned collection-row public-mutation form integration.
    Separately distributed Store Lite 0.1.24 supplies current server-derived
    line identity and exactly two pure presentations for each non-empty Cart
    row. Core revalidates the complete view, then independently verifies that
    the component, route, mutation handler, and state loader still belong to
    one enabled package before issuing evidence. Each row control receives a
    stable placement/row/form instance and separate CSRF/idempotency values,
    while every accepted form on the page reuses one request-local anonymous
    subject. The default renderer emits only validated escaped core form HTML;
    package callbacks are not reinvoked and package HTML, tokens, cookies,
    scripts, response ownership, and endpoint choice remain forbidden. A
    disabled page gate, invalid complete view, ownership drift, unavailable
    bootstrap, or form-cap overflow leaves the affected control unavailable.
    Real desktop/mobile quantity and remove dispatch, Cart refresh behavior,
    and visitor wording remain the next gate.

94. Completed editable-Cart desktop/mobile mutation acceptance with external
    Store Lite 0.1.24 at merged revision
    `c3dc7405d9e62c1112555503523c0c339e4b8fa8`. The fixed core browser controller
    now uses mutation-neutral completion/refusal language and refreshes only the
    current page 750 milliseconds after an accepted or unchanged response, so
    server-authoritative facts replace consumed forms without client-side
    commercial reconstruction. Desktop and mobile Chrome each added one real
    simple or exact Size/Color line, verified two accessible core-owned row
    controls and one bounded server-derived line handle, updated quantity,
    observed recalculated totals after refresh, removed the line, and returned
    to the empty Cart under the same anonymous subject. The proof passed the
    established 100 administrator checks and 147 public checks, exact
    `4:2:3:8:8:8` final state, clean browser/runtime gates, and complete Docker
    cleanup. No hosted or client installation changed. Minimum guest order and
    an immutable pay-on-receipt snapshot are the next Store Lite gate.

95. Added the first shared-host public-mutation compatibility gate. Core now
    supports an explicit `direct_php` ingress alongside the unchanged default
    `frankenphp_attested` profile. The direct adapter requires canonical
    configured/request origin agreement, direct HTTPS, exact POST/path,
    bounded content metadata and body, and closed subject/CSRF/idempotency
    values while ignoring Host and forwarding input. Its focused 22-assertion
    adapter and expanded endpoint fixtures pass without a database or package.
    This does not deploy or enable Store Lite: a real Apache/shared-host HTTP
    proof, direct-profile deployment review, and hosted browser verification
    remain before `demo.red-sphere.com` activation.

96. Completed the local real-Apache `direct_php` deployment gate. A disposable
    Apache 2.4.67 server projected direct HTTPS into PHP 8.5.8 FastCGI and
    passed canonical capture, forged Host/forwarding isolation, duplicate
    Origin/CSRF/cookie refusal, content-encoding refusal, Apache chunk
    normalization into the same measured body, and forwarded-HTTPS-over-HTTP
    refusal. Desktop `1440x1000` and mobile `390x844` browser evidence passed
    with HTTPS 200, zero console/network errors, no cookie or token leakage,
    and no client-state change. The expanded 41-assertion profile and
    27-assertion review validators accepted the generated non-secret packet for
    `orojas_demo_redsphere`. The proof opened no database, linked no dispatcher,
    loaded no Store Lite package, and removed its temporary Apache/FastCGI/TLS
    runtime. The separately approved hosted deployment and verification remain
    next; `demo.red-sphere.com` was not changed by this gate.

97. Promoted the runtime release identity from 5.0 to 5.1.0 after the hosted
    Store Lite preflight proved that normal non-executing discovery correctly
    refused the package's `>=5.1 <6.0` compatibility range. The trust fixture
    now proves default discovery accepts a trusted 5.1 package without the
    test-only version override while still never executing package PHP. The
    administrator signature, web manifest, clean-install heading, and release
    documentation use the 5.1 identity. No hosted installation, database,
    package lifecycle, dispatcher, or client state changed in this gate.

98. The approved basic `demo.red-sphere.com` deployment installed Store Lite
    `0.1.31` disabled with its ten migrations and no package content. The first
    hosted setting-storage check then failed closed because MySQL 5.7 reports
    unsigned integer display widths in `COLUMN_TYPE`, unlike the exact MySQL 8
    spelling used by seven core storage guards. The compatibility and
    permission-audit patches now preserve exact integer type and unsignedness
    while accepting either server representation; they passed core acceptance,
    merged in pull requests #116 and #118, and unblocked the hosted lifecycle.

99. Completed the demo-only Owner grant and administrator-surface path. Pull
    request #117 added the explicit server-local package-permission CLI; pull
    request #119 aligned installed add-on Product, Cart, Products, and Orders
    surfaces with the core administrator workspace. No grant or package state
    is shared with another client database.

100. Achieved the Store Lite v1 basic-demo target on `demo.red-sphere.com`.
    Store Lite 0.1.31 is enabled only for that installation with nine fictional
    dog products, including one exact nine-choice Size/Color scarf. Read-only
    closeout verified RED-CMS 5.1.0, nine public Add-to-cart controls, Product
    and Cart authoring, Products and Orders tools, the empty/current cart,
    pickup and delivery checkout, pay on receipt, 390-pixel responsive output,
    and clean browser logs. No new hosted order was submitted during closeout;
    real order creation, immutable snapshots, retry/conflict behavior, and
    pickup/delivery persistence remain covered by the isolated supported-server
    browser gate.

101. Pull request #120 completed the RED-CMS 5.1 Basic documentation boundary.
    The clean installer now seeds a concise core-only Instructions article with
    four reviewed administrator screenshots. It states that Store Lite and
    every business-specific capability are separate per-client packages; it
    does not migrate or overwrite an existing client's customized guide. See
    [`STORE-LITE-DEMO-CLOSEOUT-20260815.md`](STORE-LITE-DEMO-CLOSEOUT-20260815.md).

102. The optional post-release payment-adapter foundation now completes P3A.
    Five isolated core slices recognize the exact adapter profile, prove
    Owner/same-database Store Lite/migration/InnoDB readiness, validate the
    registration-only adapter and route shape, and preserve explicit bounded
    server-event raw bytes for a future verifier. The ingress helper accepts no
    ambient request state, keeps raw body and signature bytes out of ordinary
    object evidence, and performs no provider verification, JSON parsing,
    callback, secret, database, network, response, route, or lifecycle work.
    The final separate CLI-only runner adds exact stored-setting and opaque
    secret-reference availability evidence, repeats the complete plan under
    lifecycle and package locks, and atomically commits only the enabled state
    plus one value-free audit fact. It resolves no secret bytes, invokes no
    handler, links no route, and contacts no provider. No package or client was
    changed by this core batch.

103. The separately distributed Store Lite package completes P3B through
    version 0.1.35. Versions 0.1.32 through 0.1.35 respectively add the pure
    provider-neutral transition decision, append-only payment-event history
    migration, typed transactional writer/service, and disposable lifecycle
    rehearsal. The rehearsal covers upgrade, enable, apply, replay, refusal,
    disable/re-enable, rollback, two-client isolation, and exact cleanup. The
    At that point, P3C-1 became the next exact gate: a dependency-free external
    package foundation for
    `redcms.store-lite-stripe-checkout`, with identity and pure normalization
    contracts only. No repository creation, dependency, endpoint, credential,
    provider request, payment, or client deployment is authorized by this
    status update.

104. Added the P3D-6 reusable core-owned typed adapter invocation boundary.
    It requires exact enabled request-local ownership and manifest declaration,
    bounded immutable request/result objects, owning-package secret access, and
    containment of output, exceptions, buffer-stack changes, malformed results,
    and resolved-secret disclosure. The dependency-free 19-assertion fixture
    opens no database, route, browser, provider, network, Store Lite, payment,
    or client path. The external Stripe adapter remains separately distributed
    and refusal-only until its next reviewed adoption gate.

105. Added the P3E-7 core-owned provider-contact authorization boundary. It
    consumes only exact non-secret P3E-6 readiness/envelope evidence,
    revalidates the current database-backed Owner plus enabled adapter and
    same-database Store Lite state, then atomically records one nonce-bound
    immutable action and audit fact under locked authorization/package rows.
    Replay, expiry, revocation, subject mismatch, disabled dependency, and
    audit failure fail closed. It reuses the released schema and performs no
    credential resolution, environment read, network request, Stripe contact,
    Checkout, payment, webhook, Store Lite mutation, browser route, or client
    deployment. A later P3E-8 contact attempt remains separately gated.

106. Added P3E-8A as the non-networking atomic claim prerequisite for one
    future contact attempt. Core recomputes the exact P3E-7 decision, requires
    its immutable authorization row, and repeats current Owner, capability,
    adapter, and same-database Store Lite validation under lifecycle/package
    and transaction locks. It then commits one distinct nonce-derived claim
    row plus one value-free audit fact in the existing ledger. Replay, changed
    or missing authorization, expiry, revocation, disabled dependency, ledger
    drift, and audit failure fail closed; the claim does not extend expiry or
    authorize a retry. The 34-assertion disposable fixture executes no package
    handler and opens no credential, environment, DNS, TLS, HTTP, Stripe,
    Checkout, payment, webhook, Store Lite mutation, browser, client, or
    deployment path. The actual read-only sandbox request remains a separately
    approved P3E-8B gate.

107. Added P3E-8B2 as a sealed in-process execution rehearsal for the exact
    P3E-8A claim. Core revalidates the active authorization, claim, Owner,
    capability, adapter, and same-database Store Lite state, then commits an
    immutable execution-start row and bounded audit before registrar, secret,
    or handler access. A committed start permanently consumes the attempt.
    Core integrity-checks the registrar, resolves only `stripe.secret-key`,
    invokes one typed `provider-contact.read-only-probe-loopback` operation,
    and persists one closed result plus audit after rechecking the complete
    start row. The 32-assertion disposable fixture proves success, replay
    refusal, pre-start rollback, post-start no-retry behavior, scoped-secret
    isolation, bounded outcomes, forbidden-primitives absence, and exact
    cleanup. It adds no migration or table and opens no DNS, TLS, HTTP, Stripe,
    provider, Checkout, payment, webhook, Store Lite mutation, browser, client,
    or deployment path.

108. Added P3E-8B3B as the synthetic real-package execution gate. The shared
    authorization and claim validators accept only the legacy
    `0.1.1/disabled` profile or adapter `0.1.3/synthetic_only`; every other
    version/mode pair fails closed. Core commits a distinct synthetic-bound
    start hash before registrar, secret, or handler access, resolves exactly
    `stripe.secret-key`, and invokes the integrity-checked registered adapter's
    typed synthetic operation. Core then revalidates the closed in-memory
    result and persists one immutable outcome plus audit. The 33-assertion
    fixture passes beside the unchanged 33-assertion P3E-7, 34-assertion
    P3E-8A, and 32-assertion P3E-8B2 regressions, including explicit
    cross-profile runner refusal. No migration, DNS, TLS, HTTP,
    Stripe, provider transport, Checkout, payment, webhook, Store Lite
    mutation, browser, client, or deployment path is added.

109. Added P3E-8B3C2 as the exact core provider-operation runner gate. The
    shared evidence allow-list adds only adapter `0.1.4/provider_read_only`,
    while the historical loopback and synthetic runners refuse that profile.
    Core commits a distinct sandbox-operation start before registrar, secret,
    or handler access, resolves only `stripe.secret-key`, invokes the exact
    typed sandbox operation, validates the bounded result, and permanently
    refuses replay after any committed start. Missing or malformed output
    after handler invocation is conservatively recorded as possible
    network/provider contact. The 37-assertion disposable acceptance
    substitutes an integrity-checked in-memory handler, so no DNS, TLS, HTTP,
    cURL, Stripe,
    Checkout, payment, webhook, Store Lite mutation, public route, browser,
    client, or deployment path is exercised. One real restricted-key GET
    remains separately approved P3E-8B3C3 work.

110. Added P3E-8B3C3A as the server-local one-shot operator-command contract.
    Its default dry run revalidates exact Owner, package, dependency,
    authorization, claim, expiry, and value-free secret availability without
    execution. Apply requires the printed state and hashes, a nonzero backup,
    and literal sandbox-operation, target, restricted-test, one-attempt,
    no-retry, and no-mutation confirmations. The command accepts no key
    argument and contains exactly one B3C2 execution call site. The pure
    40-assertion contract adds no hostname, network primitive, request global,
    browser/public bridge, scheduler, automatic caller, provider contact,
    payment, Store Lite mutation, client activation, or deployment. The first
    real restricted-key GET remains separately approved P3E-8B3C3B work.

111. Completed P3E-8B3C3B as the first real provider-contact rehearsal. A
    dedicated blank RED-CMS Stripe sandbox and Checkout Sessions Read-only
    restricted key were isolated from every other project. The fresh staged
    core/Store Lite `0.1.35`/adapter `0.1.4` lifecycle committed new
    authorization and claim evidence, verified its pre-contact backup, and
    made one exact GET. Core recorded bounded `404 resource_miss_observed`
    evidence; Stripe logged one matching request; no retry, mutation, response
    body/header, credential, client state, or deployment escaped. Checksummed
    private evidence passed credential scanning and final cleanup returned
    database/grant/project/process zero with the configured primary unchanged.
    After evidence review, the operator explicitly expired the restricted key;
    it no longer appears in the active restricted-key list.

112. Defined P3E-9 as the separately gated Stripe Sandbox Checkout-creation
    frontier. The completed P3E-8 evidence remains permanently read-only and
    cannot authorize a POST. At that point, P3E-9A was the next gate and could
    add only pure non-executing request, response, expiry, and operation-profile
    contracts. Synthetic
    package/core integration, new mutation-specific authorization and claim
    evidence, the disposable operator rehearsal, one real Sandbox Session,
    credential expiration, Session expiration, simulated payment, webhook
    proof, browser flow, hosted-demo state, client deployment, and P4 remain
    distinct later approvals. This planning slice adds no PHP, migration,
    manifest, package, database, route, runtime, provider, or client change.

113. Completed P3E-9A in the separately distributed Stripe Checkout adapter.
    The dependency-free source-only contract reuses the retained P3E-1 planner,
    P3E-3 codec, and P3E-1 response gate; adds only bounded expiry and the new
    mutation-aware profile; rejects read-only-profile reuse; validates exact
    synthetic open/unpaid/non-live Session facts; and discards the Checkout
    URL. Its 53 focused and 921 aggregate assertions passed. Installable
    adapter `0.1.4` and its package subtree remain unchanged. P3E-9B synthetic
    package/core integration is next; credentials, network, provider mutation,
    Checkout creation, payment, webhook, browser, Store Lite, demo/client,
    deployment, and P4 paths remain gated.

114. Completed P3E-9B synthetic Checkout package/core integration. External
    adapter `0.1.5` adopts byte-identical P3E-9A source and adds only one
    synthetic operation. Core validates the exact package, closed checkout and
    policy, mutation-aware profile, contract hash, and deterministic plan;
    registers the integrity-checked handler; invokes it through the existing
    typed boundary with injected one-setting secret access; and accepts only
    bounded no-network/no-mutation facts. The 37-assertion core fixture uses no
    database and removes its exact temporary project. No authority ledger,
    credential resolver, provider request, Checkout Session, payment, webhook,
    browser, Store Lite, demo/client, or deployment path is added. P3E-9C1
    mutation-specific authorization was completed as the following gate.

115. Completed P3E-9C1 mutation-specific authorization recording. The core
    consumes the exact P3E-9B dry plan and revalidates current database-backed
    Owner, `addons.enable`, exact Store Lite `store.orders.manage`, adapter
    `0.1.5`, and Store Lite `0.1.35`. One at-most-fifteen-minute nonce permits
    one future attempt; apply atomically records one immutable authorization
    row and one value-free audit fact. Its 34-assertion disposable fixture
    proves zero-write planning, replay/expiry/revocation refusal, audit rollback
    and recovery, source isolation, and exact fixture cleanup. No claim,
    execution, secret access, network request, Checkout Session, payment,
    webhook, browser, Store Lite mutation, retry, demo/client, migration, or
    deployment path is added. P3E-9C2 one-attempt claim was completed as the
    following gate.

116. Completed P3E-9C2 mutation-attempt claim recording. Core recomputes the
    exact P3E-9C1 decision and requires its matching immutable authorization
    row under fresh Owner, `addons.enable`, Store Lite
    `store.orders.manage`, package, input, plan, and expiry checks. Apply
    atomically records one distinct nonce-bound claim and one value-free audit.
    Its 37-assertion disposable fixture proves zero-write planning,
    replay/missing/changed/tampered evidence refusal, both authority revocations,
    dependency and expiry refusal, audit rollback and recovery, source
    isolation, and exact cleanup. No execution start/result, secret access,
    package invocation, network request, Checkout Session, payment, webhook,
    browser, Store Lite mutation, retry, demo/client, migration, or deployment
    path is added. P3E-9C3A start/result was completed as the following gate.

117. Completed P3E-9C3A immutable transport-double start/result recording.
    Core recomputes P3E-9C1 and verifies the exact P3E-9C2 claim, commits start
    before one final in-memory double call, and records one bounded result.
    Its 36-assertion disposable fixture proves exact hashes, success/fault
    containment, start-audit rollback, replay refusal, and permanent no-retry
    after committed start. The runner has no arbitrary callable, credential,
    package invocation, network/provider request, real Checkout Session,
    payment, webhook, browser, Store Lite mutation, demo/client, migration, or
    deployment path. P3E-9C3B1 command contract was completed next.

118. Completed P3E-9C3B1 CLI-only dry-run-first operator command. Its 45 pure
    assertions require exact evidence/hash/backup/no-effect confirmations,
    one plan call, one final-double construction, and one C3A runner call.
    Default mode invokes nothing. The command accepts no credential and has no
    package, network, shell, browser, payment, webhook, Store Lite, client,
    migration, or deployment path. P3E-9C3B2 completed the following rehearsal.

119. Completed P3E-9C3B2 disposable operator apply rehearsal. Staged merged
    core and a fresh current-schema database proved dry run, incomplete-
    confirmation refusal, one exact final-double apply, replay refusal, four
    immutable lifecycle rows/audits, zero provider effects, and cleanup
    `database:0 grant:0 staged-project:0 primary:unchanged`. P3E-9C is complete.
    P3E-9D0 pure request preflight was completed next.

120. Completed P3E-9D0 pure real-POST preflight. Its 25 assertions bind exact
    adapter `0.1.5` synthetic evidence to one future Checkout Sessions POST,
    bounded expiry, deterministic USD lines, hash-only metadata, idempotency,
    and a canonical request hash while every credential/network/provider/
    business effect remains false. External adapter P3E-9D1 then completed
    through canonical-hash-compatible version `0.1.7`.

121. Completed P3E-9D2 core real-operation preflight containment. Core requires
    exact adapter `0.1.7` plus recomputed D0 evidence, invokes only
    `checkout.create-sandbox-real-post-preflight` with null secret access,
    accepts only the exact typed request and false-effect result, and derives
    deterministic non-persistent start/result identity hashes. Its 39 focused
    assertions contain changed evidence, malformed output, exceptions, output,
    and changed provider-operation identity with exact temporary-project
    cleanup. No credential, database row, execution start/result, network,
    Stripe Session, payment, webhook, browser, Store Lite mutation, retry,
    demo/client, or deployment path is added. P3E-9D3A was kept as a separate
    slice; the first real Sandbox POST remains gated.

122. Completed P3E-9D3A CLI-only real-operation preflight command contract.
    Dry run revalidates exact adapter `0.1.7`, source `0.1.5`, D0 request, D2
    plan, integrity, and deterministic start identities, then exits before the
    registrar or handler. Apply requires every printed hash and nine explicit
    no-effect confirmations, invokes D2 once with no secret access, and accepts
    only its non-persistent result identity. Its 68 assertions exclude
    configuration/database access, credentials, resolver, request/browser,
    network/provider, Checkout Session, payment, webhook, Store Lite mutation,
    retry, demo/client, and deployment paths. P3E-9D3B remained a separate
    cross-repository no-contact rehearsal; P3E-9D4 remains separately gated.

123. Completed P3E-9D3B disposable cross-repository no-contact rehearsal.
    Exact merged core `f93d191`, adapter `a441588` `0.1.7`, and Store Lite
    `f7de77e` `0.1.35` package bytes passed dry run, changed-plan-hash refusal,
    and one contained D2 apply under disabled URL streams/network functions and
    removed secret/proxy environment inputs. The only result was
    `request_contract_adopted` plus a non-persistent identity; every
    provider/business effect stayed false. Its 72 source assertions and
    operational evidence prove no configuration/database access and cleanup
    `staged-project:0 evidence:0 source-repositories:unchanged
    database:not-opened`. P3E-9D3 is complete. P3E-9D4 one real Sandbox
    Checkout Session POST remains separately unapproved and gated.

124. Completed documentation-only P3E-9D4 authorization planning. The merged
    adapter `0.1.7` remains preflight-only, and prior mutation evidence remains
    bound to earlier package/operation identities. At that planning stop, D4A
    was defined to add only the separately distributed provider-write
    operation with offline/loopback
    acceptance; D4B adds fresh authority, claim, durable start/result, and an
    in-memory core runner; D4C adds a dry-run-first command and network-disabled
    no-contact rehearsal without real apply; D4D alone may create one real
    Session after separate key-creation, storage, apply, and expiration
    approvals. Current official Stripe key, creation, 30-minute-through-24-hour
    expiry, idempotency, and separate Session-expiration facts were rechecked.
    This slice changes no PHP, package, database, key, account, network,
    runtime, hosted/client, or provider state.

125. Completed external adapter P3E-9D4A at separately distributed version
    `0.1.8`, merged as adapter commit `562b8a9`. The package adds only the exact
    `checkout.create-sandbox-real-post` provider-write operation, validates D1
    preflight before secret access, fixes one-use restricted-test transport,
    and retains only bounded non-secret outcome evidence. Focused D4A,
    aggregate adapter, and local TLS-loopback acceptance passed with package
    integrity and source/package parity. Core remains unchanged at this stop:
    it has no D4A caller, and no key, Stripe request, Session, payment,
    database, hosted/client, or deployment effect occurred. P3E-9D4B fresh
    durable core authority, claim, start/result, and in-memory acceptance is
    next.

126. Completed core P3E-9D4B1 fresh authority and claim. The pure 20-assertion
    evidence contract binds adapter `0.1.8` to exact D0/D2 identities. The
    30-assertion disposable lifecycle binds database, order snapshot, Owner,
    `addons.enable`, `store.orders.manage`, and value-free secret availability,
    then atomically records new D4-only authorization and claim rows plus
    audits. No execution start/result, registrar, handler, secret value,
    network, provider, Checkout Session, payment, Store Lite mutation, hosted/
    client, migration, or deployment effect exists. D4B2 durable start/result
    plus in-memory invocation remains next.

127. Completed core P3E-9D4B2 durable execution with 29 disposable-database
    assertions. Start and its value-free audit commit before registrar, scoped
    secret, or handler access; one final in-memory adapter handler returns only
    the bounded open/unpaid/non-live Session projection; the complete start row
    is rechecked before result and audit commit. Replay, rollback/recovery,
    permanent no-retry after result failure, fault/malformed containment,
    missing-secret indeterminate recording, scoped-secret isolation, and exact
    cleanup pass. Conservative provider-effect flags acknowledge what a real
    handler could have done, but the sealed fixture used no network and no
    Stripe request, real Session, payment, webhook, browser, Store Lite,
    hosted/client, migration, or deployment effect occurred. P3E-9D4C CLI and
    no-contact rehearsal is next.

128. Completed P3E-9D4C1 CLI-only real-POST operator command contract with 74
    pure source assertions. Default dry run exits before the single D4B2 apply
    call site. Apply requires exact database/package/Store Lite/actor/expiry,
    D0/D2/D4 identities, order, secret-availability, backup, operation, target,
    one attempt, three intended provider effects, and eight excluded effects.
    Output retains only the bounded Session reference after URL discard; every
    other result is consumed and no-retry. The source test opens no database,
    reads no configuration or secret, invokes no package/runner, and performs
    no network, provider, hosted/client, or deployment effect. P3E-9D4C2
    network-disabled no-contact rehearsal remains next.

129. Completed P3E-9D4C2 with 92 pure source assertions and one opt-in
    network-disabled cross-repository rehearsal. Exact core, discovery-valid
    adapter repair `44ed7b3`, and Store Lite `0.1.35` sources are staged; a
    runtime probe proves URL streams and common cURL/socket functions disabled;
    secret-value/proxy inputs are removed; and one fresh current-schema
    database records only package identity, opaque settings, Owner/permissions,
    D4 authorization, and claim. Dry run passes, incomplete and changed apply
    are refused, the fully confirmed apply is never invoked, and ledger
    evidence remains `2:2:0` with `real-apply:0 start-result:0
    provider-effects:0`. Database, grant, staged files, evidence, environment,
    source repositories, and primary cleanup pass exactly. P3E-9D4D remains
    the separately authorized first real Sandbox POST if resumed and is now
    owner-deferred as recorded below.

130. Recorded the Colombia C0 provider decision after the owner deferred
    Stripe P3E-9D4D on 2026-08-22. The separately distributed candidate is
    `redcms.store-lite-wompi`, with only customer-visible Nequi, `COP`, and
    one-time Store Lite guest orders in the initial scope. Direct Nequi
    Push/QR remains a later client-specific alternative. The dated official-
    provider review records separate Wompi Sandbox/production environments,
    asynchronous transaction status, lookup plus signed-event
    reconciliation, current customer-acceptance requirements, one client-local
    public setting plus three secret-reference classes, and personal-data
    minimization. The review also identifies the current hosted-URL-only
    initiation result as incompatible with direct Nequi Push. C1 was reserved
    for only the closed `hosted_redirect`/`out_of_band_confirmation` provider-
    neutral union plus a dependency-free offline fixture and is completed in
    item 131; C2 external package is completed in item 132 and C3 disposable
    integration remains later, while C4 Wompi Sandbox and C5 demo deployment
    require separate approvals. No PHP, package, manifest, migration,
    database, route, credential, account, network request, Wompi transaction,
    Nequi notification, payment, order transition, hosted-demo change, client
    data, or deployment is added by C0. See
    [`PAYMENT-ADAPTER-COLOMBIA-C0-DECISION.md`](PAYMENT-ADAPTER-COLOMBIA-C0-DECISION.md).

131. Completed Colombia C1 with one dependency-free provider-neutral payment-
    initiation helper and a 55-assertion CLI-only Wompi/Nequi fixture. The
    closed union defines a canonical ordered `hosted_redirect` reference/HTTPS-
    URL value returned unchanged and adds only URL-free
    `out_of_band_confirmation` with opaque reference, pending state, and
    generic provider-app action. Invalid/mixed
    shapes and any paid initiation fail closed. The fixture plans one COP/
    Nequi request, returns only hashes and false effects, excludes personal,
    acceptance, signature, and secret-reference material, verifies and binds
    bounded provider-ordered signed properties with retry-compatible timing,
    requires matching lookup evidence, refuses replay and identity/amount/
    currency/status mismatches, and emits only
    proposed paid/failed evidence with order mutation false. Existing Stripe
    P2 remains untouched; its regression plus typed-adapter and payment-profile
    regressions pass. The helper has no runtime caller; no configuration,
    database, package, manifest, migration,
    credential, network, provider transaction, notification, payment, webhook,
    browser, Store Lite mutation, hosted-demo change, client data, or
    deployment is added. C2 is completed in item 132. See
    [`PAYMENT-ADAPTER-COLOMBIA-C1-INITIATION-CONTRACT.md`](PAYMENT-ADAPTER-COLOMBIA-C1-INITIATION-CONTRACT.md).

132. Completed Colombia C2 in the new public separately distributed
    `redcms-store-lite-wompi` repository at exact commit `e17a371`. Package
    `redcms.store-lite-wompi` version `0.1.0` declares one Store Lite adapter,
    exact dependency, one unset public-key setting, three secret references,
    Sandbox-only Wompi host, one refusing event route, two unexecuted InnoDB
    evidence migrations, and nine integrity files. Its 34 offline-contract and
    60 package assertions prove hashed/self-fingerprinted COP/Nequi planning,
    exact C1 out-of-band PENDING projection, one-use sealed double, dynamic
    signed-event properties, retry-compatible checksum/lookup/replay handling,
    bounded non-mutating outcomes, generic discovery/registrar, source/package
    parity, forbidden-primitive and credential exclusion, and cleanup. The
    existing Stripe-only core payment profile deliberately refuses the package
    with only `outbound_host_invalid` and `setting_contract_invalid`; C3A
    closes the profile portion in item 133, while C3B was reserved for
    disposable installation and registrar proof. No core/Store Lite/Stripe
    file, database,
    migration
    execution, installation, enablement, setting or secret value, network,
    provider transaction, payment, webhook ingress, browser, order mutation,
    hosted-demo change, client data, or deployment effect occurred. See
    [`PAYMENT-ADAPTER-COLOMBIA-C2-PACKAGE.md`](PAYMENT-ADAPTER-COLOMBIA-C2-PACKAGE.md).

133. Completed Colombia C3A by extending only the dependency-free payment-
    adapter manifest-profile helper. Exact package id
    `redcms.store-lite-wompi` selects `store_lite_wompi_adapter_v1`; all
    existing fixtures retain the Stripe profile. Wompi requires the exact
    adapter, Store Lite dependency/range, one ordinary and three secret
    setting keys, two migration ids/paths, event route/path, and sole Sandbox
    host. Ordered/unique/disjoint normalized setting-key lists are included in
    the deterministic profile evidence and independently revalidated. The
    focused test passes 30 fixture and 41 exact published-package assertions,
    including all nine external hashes. Existing Stripe profile 26, registrar
    13, ingress 26, synthetic-checkout 37, and typed-adapter 19 assertions pass.
    All activation/runtime/state/package/secret/network/route effects remain
    false and the four downstream blockers remain. No database, Wompi package
    install, migration, registrar execution for Wompi, enablement, setting,
    secret, provider request, payment, browser, Store Lite mutation, hosted-
    demo change, client data, or deployment effect occurred. At C3A close, C3B
    remained the separate disposable database/migration/registrar proof. See
    [`PAYMENT-ADAPTER-COLOMBIA-C3A-CORE-PROFILE.md`](PAYMENT-ADAPTER-COLOMBIA-C3A-CORE-PROFILE.md).

134. Completed Colombia C3B by removing the registrar's final Stripe-only
    profile constant and adding exact Wompi profile/adapter/route validation.
    The dependency-free registrar suite passes 18 assertions and preserves the
    26-assertion Stripe ingress suite. A separate real-package rehearsal
    requires clean Store Lite `0.1.35` at `f7de77e` and Wompi `0.1.0` at
    `e17a371`, creates one fresh disposable database/grant, applies all 46 core
    migrations, records the exact Store Lite identity/11-migration enabled
    baseline, and guarded-installs Wompi as `installed_disabled`. Its 16
    assertions prove two applied migrations, two empty InnoDB tables, no
    settings, exact database evidence, registrar-only adapter/refusing-route
    registration without handler invocation or publication, repeat-install
    refusal, bounded audit, and registrar database non-mutation. Cleanup proves
    `database:0 grant:0 staged-project:0 primary:unchanged`. No Wompi
    enablement, credential value, provider contact, transaction, payment,
    browser, Store Lite order mutation, hosted-demo change, client data, or
    deployment effect occurred. At C3B close, C3C remained the atomic
    enablement plus two-client isolation gate. See
    [`PAYMENT-ADAPTER-COLOMBIA-C3B-DISPOSABLE-LIFECYCLE.md`](PAYMENT-ADAPTER-COLOMBIA-C3B-DISPOSABLE-LIFECYCLE.md).

135. Completed Colombia C3C1 by adding an exact Wompi body-signed ingress
    profile and atomic enablement while preserving Stripe. Stripe still
    requires content type/length plus `Stripe-Signature`; Wompi requires only
    content type/length because the checksum and signed properties remain in
    opaque JSON body bytes for later verification. The dependency-free ingress
    suite passes 31 assertions. Enablement dynamically signs the exact profile,
    keeps Stripe's two-secret rule, and requires three available opaque Wompi
    references. The current-schema disposable rehearsal applies all 46 core
    migrations, passes the existing 24-assertion Stripe atomic-enable test,
    then passes 17 exact Wompi assertions for four settings, redacted plan
    evidence, missing/stale refusal, injected transactional rollback, one
    enable/audit, empty evidence tables, and repeat refusal. Cleanup proves
    `database:0 grant:0 staged-project:0 primary:unchanged`. No real key or
    secret, provider contact, transaction, payment, event processing, runtime
    publication, browser, hosted-demo change, client data, or deployment
    occurs. At C3C1 close, C3C2 remained the separate two-client
    enable/disable isolation gate. See
    [`PAYMENT-ADAPTER-COLOMBIA-C3C1-ATOMIC-ENABLEMENT.md`](PAYMENT-ADAPTER-COLOMBIA-C3C1-ATOMIC-ENABLEMENT.md).

136. Completed Colombia C3C2 without changing core runtime behavior. One
    exact-package rehearsal creates two fresh client databases/grants, applies
    all 46 core migrations to each, and passes 21 assertions. Both clients
    independently install, configure, and atomically enable Wompi. Immutable
    contract/manifest/inventory hashes match while database, settings,
    availability, registration, ingress, and plan hashes differ. Neither
    client contains the other's marker/reference. Lifecycle locks coexist
    across databases and refuse same-database contention. Injected client-A
    disable failure rolls back without changing either fingerprint; successful
    A disable retains its settings/migrations/tables and empty evidence while
    B remains enabled and unchanged. Declarative runtime order excludes Wompi
    only from A; repeat disable refuses. Cleanup proves
    `databases:0 grants:0 staged-project:0 primary:unchanged`. No handler,
    secret resolution, provider contact, payment, runtime publication, browser,
    hosted-demo change, client data, or deployment occurs. Colombia C3 is
    complete. C4 Wompi Sandbox credentials/contact is next and separately
    owner-gated. See
    [`PAYMENT-ADAPTER-COLOMBIA-C3C2-TWO-CLIENT-ISOLATION.md`](PAYMENT-ADAPTER-COLOMBIA-C3C2-TWO-CLIENT-ISOLATION.md).

137. Completed Colombia C4A as a public-docs-only official Wompi readiness
    audit dated 2026-08-23. Current Wompi-owned sources establish disjoint
    Sandbox and Production hosts/value prefixes, owner-operated merchant
    identity and terms, public-key retrieval of two acceptance tokens plus two current
    contract links, explicit customer acceptance of both contracts, private-
    key Bearer transaction creation, COP/unique-reference/integrity/email/
    Nequi-phone requirements, asynchronous `PENDING` plus lookup/event
    finality, current Nequi test outcomes, and dynamic event checksums/retries.
    The audit confirms that the existing offline planner already binds both
    token hashes, contract hash, customer hashes, exact Sandbox host/path,
    setting/reference availability, event integrity, and lookup agreement. It
    also identifies hard blockers: no merchant-token retrieval operation,
    public two-contract acceptance UI, transient wire builder/signer,
    transaction transport, contained lookup parser, or operational event
    runner. Focused current-core checks and 34 external offline assertions pass;
    the full external package suite stops at its superseded C2 expectation that
    core rejects Wompi. At C4A close, C4B still needed to replace that package-
    owned assertion and remained the next credential-free/no-contact
    engineering gate. C4C
    account/read-only merchant retrieval, C4D one approved Sandbox
    transaction, C4E declined/event/rotation, and C5 deployment retain separate
    owner approvals. Public documentation retrieval is the only external read;
    no account, credential, provider API request, transaction, personal data,
    database, package, demo, or deployment effect occurs. See
    [`PAYMENT-ADAPTER-COLOMBIA-C4A-OFFICIAL-READINESS.md`](PAYMENT-ADAPTER-COLOMBIA-C4A-OFFICIAL-READINESS.md).

138. Completed Colombia C4B1 across the separate Wompi package and an exact
    non-runtime core adoption. External package `0.1.1` at
    `7e4f8cb337d746b5a483932108e5dbcd109d7d86` adds a pure hash-only Sandbox
    merchant-contract GET planner and a strict synthetic response gate for
    `presigned_acceptance` plus `presigned_personal_data_auth`. It returns only
    two distinct Wompi-controlled HTTPS links plus token/contract/response/
    projection hashes; malformed/reused tokens, wrong types, extra/missing
    fields, reused links, foreign/HTTP/credential/query/fragment URLs, changed
    plans, and changed projections fail closed. Raw tokens, final wire path,
    presentation, consent, persistence, transport, provider contact, payment,
    browser, order mutation, and retry remain false. The external suite passes
    34 existing offline, 64 package/current-core, and 29 C4B1 assertions with
    11 integrity files. Core runtime helpers are unchanged; exact-package pins
    now recognize `0.1.1`/`7e4f8cb`. Focused checks pass 43 profile, 18
    registrar, 31 ingress, and 22 clean-starter assertions. Disposable proofs
    pass 16 install/registrar, existing Stripe 24 plus Wompi 17 atomic-enable,
    and 21 two-client assertions with exact database/grant/stage cleanup and
    the configured primary unchanged. No account/dashboard, credential/
    personal value, provider API request, transaction, retained database/client
    migration, starter package copy, demo, or deployment effect occurs. At
    C4B1 close, C4B2 remained credential-free/no-contact for explicit two-contract
    presentation/consent evidence plus a transient server-side integrity/wire
    builder. See
    [`PAYMENT-ADAPTER-COLOMBIA-C4B1-CORE-ADOPTION.md`](PAYMENT-ADAPTER-COLOMBIA-C4B1-CORE-ADOPTION.md).

139. Completed Colombia C4B2 across separate Wompi package `0.1.2` at
    `fdbf88145c5858c313f6f2a3e50137e54801d683` and an exact non-runtime core
    adoption. One pure presentation model fixes exactly two ordered Wompi-
    controlled HTTPS links and two separately named required controls. Consent
    evidence binds separate presentation/acceptance facts to order, subject,
    presentation/contract/token hashes, nonce, and an exact 15-minute window.
    A transient Sandbox-only builder reconstructs the existing transaction
    plan, verifies injected synthetic email/phone/token values against its
    hashes, requires test private/integrity value families, constructs the exact
    Bearer header, integrity signature, Nequi body, and POST request inside one
    pure call, then returns only field names and redacted hashes. The actual
    signature is not returned: a domain-separated integrity-input hash plus a
    second hash of the signature prevent accidental signature disclosure.
    Email/phone and their individual hashes, raw tokens, keys, authorization,
    signature, body, and request are not returned/persisted. The external suite
    passes 34 C2, 70 package/current-core, 29 C4B1, and 49 C4B2 assertions with
    14 integrity files and ten source/package pairs. Core runtime helpers are
    unchanged; exact pins now recognize `0.1.2`/`fdbf881`. Focused checks pass
    46 profile, 18 registrar, 31 ingress, and 22 clean-starter assertions.
    Disposable proofs pass 16 install/registrar, existing Stripe 24 plus Wompi
    17 atomic-enable, and 21 two-client assertions with exact cleanup and the
    configured primary unchanged. No account/dashboard, client credential/
    personal resolution, provider API request, transaction, retained database/
    client migration, starter package copy, demo, or deployment effect occurs.
    C4B3 is next and remains credential-free/no-contact for transaction-create/
    lookup response containment. See
    [`PAYMENT-ADAPTER-COLOMBIA-C4B2-CORE-ADOPTION.md`](PAYMENT-ADAPTER-COLOMBIA-C4B2-CORE-ADOPTION.md).
140. Completed Colombia C4B3 across separate Wompi package `0.1.3` at
    `277760e6cd727fab6795524b654ab55c4597bfa2` and exact non-runtime core
    adoption. The dependency-free response-containment pair requires valid C2
    plan/C4B2 wire evidence for HTTP 201 create, exact id/reference/amount/COP/
    NEQUI/PENDING agreement, and bounded documented fields; optional personal/
    provider detail is validated then discarded. Lookup requires untampered
    create evidence, HTTP 200, and exact identity/reference/amount/currency/
    method agreement. PENDING/APPROVED/DECLINED/ERROR map only to proposed
    pending/paid/failed; VOIDED is refused. Raw response/header/personal detail,
    payment verification, event agreement, payment/order/provider mutation,
    and retry remain false. External checks pass 34 C2, 72 package/current-
    core, 29 C4B1, 49 C4B2, and 48 C4B3 assertions with 15 integrity files and
    eleven source/package pairs. Core runtime helpers remain unchanged; exact
    pins now recognize `0.1.3`/`277760e`. Focused checks pass 47 profile, 18
    registrar, 31 ingress, and 22 clean-starter assertions. Disposable checks
    pass 16 install/registrar, existing Stripe 24 plus Wompi 17 atomic-enable,
    and 21 two-client assertions with cleanup proving no database, grant, or
    staged project remains and the configured primary unchanged. C4B4 remains
    credential-free/no-contact for one-attempt authorization/claim/state; C4C
    remains separately owner-gated before account/credential/read-only provider
    contact. See
    [`PAYMENT-ADAPTER-COLOMBIA-C4B3-CORE-ADOPTION.md`](PAYMENT-ADAPTER-COLOMBIA-C4B3-CORE-ADOPTION.md).
141. Completed Colombia C4B4A across separate Wompi package `0.1.4` at
    `5f372b3a2e35723f638a03cf089deedc238c99a4` and exact non-runtime core
    adoption. The dependency-free contract binds exact C2 plan/C4B2 wire,
    client/database/actor/secret-availability/nonce hashes, fresh authority,
    enabled package/Store Lite facts, one attempt/no retry, and a maximum 15-
    minute window to sealed-double-only no-contact authorization. First-claim
    preparation requires a distinct nonce, attempt one, and empty prior-claim
    evidence, then sets remaining attempts to zero while explicitly keeping
    claim persistence, replay protection, and execution false. Exact C4B3
    create/lookup evidence projects only claim-prepared, pending-observed,
    approved-observed, or failed-observed state; payment verification, event
    agreement, provider/order mutation, and retry remain false. External checks
    pass 34 C2, 74 package/current-core, 29 C4B1, 49 C4B2, 48 C4B3, and 52
    C4B4A assertions with 16 integrity files and twelve source/package pairs.
    Core runtime helpers remain unchanged; exact pins now recognize `0.1.4`/
    `5f372b3`. Focused checks pass 48 profile, 18 registrar, 31 ingress, and 22
    clean-starter assertions. Disposable checks pass 16 install/registrar,
    existing Stripe 24 plus Wompi 17 atomic-enable, and 21 two-client assertions
    with exact cleanup and the configured primary unchanged. C4B4B remains
    credential-free/no-contact for atomic durable claim and replay protection;
    C4C remains separately owner-gated before account/credential/read-only
    provider contact. See
    [`PAYMENT-ADAPTER-COLOMBIA-C4B4A-CORE-ADOPTION.md`](PAYMENT-ADAPTER-COLOMBIA-C4B4A-CORE-ADOPTION.md).
142. Completed Colombia C4B4B in core without a new migration. The helper
    validates exact self-fingerprinted C4B4A authorization/claim evidence and
    revalidates canonical client database/scope/actor hashes, current Owner,
    `addons.enable`, exact `store.orders.manage` grant/declaration, enabled
    Wompi `0.1.4` and Store Lite `0.1.35`, and four setting-reference
    availability facts. Planning writes nothing. Apply locks lifecycle,
    package, actor, capabilities, installations, settings, and both nonce-
    derived action identities, then atomically inserts one authorization row,
    one claim row, and two value-free audits in the existing immutable action
    ledger. Replay, stale state, authority revocation, package disablement,
    wrong client scope, tampered evidence, nested transaction, and audit failure
    fail closed; injected claim-audit failure rolls back every new row and
    permits one clean recovery. The 24-assertion disposable rehearsal applies
    all 46 core migrations and cleans `database:0 grant:0 staged-project:0
    primary:unchanged`. Execution, package invocation, secret resolution,
    network/provider, payment/order, and retry effects remain false. C4B4C is
    next for a core-owned sealed transport-double start/result runner; C4C
    remains separately owner-gated. See
    [`PAYMENT-ADAPTER-COLOMBIA-C4B4B-DURABLE-CLAIM.md`](PAYMENT-ADAPTER-COLOMBIA-C4B4B-DURABLE-CLAIM.md).
143. Completed Colombia C4B4C in core without a migration, package handler, or
    secret resolver. The runner requires exact C4B4B authorization/claim rows,
    current client/Owner/order/package/settings state, and a bounded hash-only
    request. Under lifecycle/package and transaction locks, it commits one
    nonce-derived start row plus audit before invoking only the final core-owned
    sealed double. A second transaction verifies start and records one bounded
    result plus audit. Completed output retains only request/projection hashes;
    throwing or malformed output becomes indeterminate. Start-audit failure
    rolls back before invocation, while any post-start result/audit failure
    permanently spends the attempt. Replay refuses before another call. The
    38-assertion disposable rehearsal covers missing claim, success, replay,
    start rollback/recovery, permanent no-retry, fault, malformed output,
    changed-start refusal, forbidden primitives, all 46 migrations, and cleanup
    `database:0 grant:0 staged-project:0 primary:unchanged`. Execution means
    only the in-memory double ran; package invocation, secret resolution,
    network/Wompi, transaction creation, payment/order, and retry remain false.
    C4B4D then owns the CLI-only dry-run-first command and network-disabled
    disposable rehearsal; C4C remains separately owner-gated. See
    [`PAYMENT-ADAPTER-COLOMBIA-C4B4C-TRANSPORT-DOUBLE.md`](PAYMENT-ADAPTER-COLOMBIA-C4B4C-TRANSPORT-DOUBLE.md).
144. Completed Colombia C4B4D as a CLI-only, dry-run-first operator gate and
    network-disabled disposable rehearsal. The command accepts only one exact
    bounded authorization/claim JSON file, performs one C4B4C plan, and writes
    nothing by default. Apply requires exact database/package/enabled state,
    client/database/actor/order/plan/wire/authorization/claim/request/start
    identities, nonzero backup evidence, one attempt, no retry, disabled
    network, and explicit provider/transaction/payment/order denials before it
    constructs one final sealed double and calls the runner once. Its pure
    source contract passes 55 assertions. The disposable rehearsal disables
    URL streams plus common cURL/socket functions, clears proxy and secret-
    value environments, applies all 46 migrations, proves dry run, incomplete-
    confirmation refusal, one apply, replay refusal, four rows/four audits,
    empty Wompi attempt/event tables, and cleanup `database:0 grant:0 staged-
    project:0 evidence:0 environment:clear source-repositories:unchanged
    primary:unchanged`. No account, credential, provider contact, transaction,
    payment, order, demo, or deployment effect occurs. C4C then remains
    separately owner-gated. See
    [`PAYMENT-ADAPTER-COLOMBIA-C4B4D-OPERATOR-REHEARSAL.md`](PAYMENT-ADAPTER-COLOMBIA-C4B4D-OPERATOR-REHEARSAL.md).
145. Completed Colombia C4C1 without account/dashboard or provider contact.
    External Wompi `0.1.5` at `cc2ddd0` adds exactly one TLS-verified Sandbox
    merchant-contract GET, strict no-redirect/proxy/auth/retry bounds, response
    containment, and a sealed no-network double. Its full external suite passes
    321 assertions with 19 integrity files. Core pins only the reviewed
    `0.1.5`/commit pair, passes 51 profile assertions, and adds a 13-assertion
    disposable adoption rehearsal. After all 47 migrations it proves exact
    install-disabled/enable/registrar state, one sealed double, bounded links/
    hashes, empty Wompi business tables, and cleanup `database:0 grant:0 staged-
    project:0 primary:unchanged`. No real key value, DNS, TLS, HTTP, Wompi,
    transaction, payment, order, demo, or deployment effect occurs. C4C2 next
    owns the Owner-gated CLI/no-contact rehearsal; C4C3 remains the first real
    read-only provider contact. See
    [`PAYMENT-ADAPTER-COLOMBIA-C4C1-CORE-ADOPTION.md`](PAYMENT-ADAPTER-COLOMBIA-C4C1-CORE-ADOPTION.md).
146. Completed Colombia C4C2 as a CLI-only, dry-run-first merchant-read double
    gate. A new core preflight requires exact current-client Owner/order
    authority, enabled Store Lite `0.1.35` and Wompi `0.1.5`, one `pub_test_`
    setting, and three opaque references while returning hashes only and
    loading no package PHP. The 67-assertion command contract requires every
    printed identity/denial plus nonzero backup before apply. Apply loads only
    the five merchant-contract class files and invokes one sealed no-network
    double; package adapter, real cURL transport, secret resolution, and Wompi
    contact remain unreachable. The disposable rehearsal runs all 47
    migrations, proves dry run, incomplete refusal, one double apply, zero
    Wompi business/action/audit rows, and cleanup `database:0 grant:0 staged-
    project:0 primary:unchanged`. Durable attempt and replay protection remain
    explicitly false, so C4C3A must add them before C4C3B one owner-operated
    real GET. See
    [`PAYMENT-ADAPTER-COLOMBIA-C4C2-OPERATOR-REHEARSAL.md`](PAYMENT-ADAPTER-COLOMBIA-C4C2-OPERATOR-REHEARSAL.md).
147. Completed Colombia C4C3A as the durable, provider-double-only execution
    gate. Fresh 15-minute hash-only authorization binds current client, Owner,
    exact packages/settings, one nonce, one attempt/no retry, network disabled,
    and every real provider/business authorization false. Planning is zero-
    write. Apply commits one start row/audit before one final core provider
    double, then records one bounded result/audit; replay refuses before another
    invocation, while post-start failure remains permanently no-retry. The CLI
    defaults to dry run and requires every printed identity/hash/denial plus a
    nonzero backup hash. The disposable proof passes 32 durable and 78 command-
    contract assertions, one apply, two rows/two audits, replay refusal, empty
    Wompi business tables, all 47 migrations, and exact cleanup. No enrollment,
    account, tax selection, real key, network, Wompi request, transaction,
    payment, event, order, demo, or deployment occurs. C4C3B is owner-deferred
    and requires a new explicit authorization before exactly one real read-only
    GET. See
    [`PAYMENT-ADAPTER-COLOMBIA-C4C3A-DURABLE-PROVIDER-DOUBLE.md`](PAYMENT-ADAPTER-COLOMBIA-C4C3A-DURABLE-PROVIDER-DOUBLE.md).
148. Completed the generic add-on component destination search/completion
    stage after the separately merged route, inactive-component, and public-
    placement stages. Core rederives the write-disabled package preview and
    reconciles the immutable plan, Article route, public component, package
    baseline, revisions, audit, actor, target, and all checkpoint hashes before
    sending only `component.published` plus the numeric Article route and
    component ids through
    `content.index-sync`. Search success or contained failure advances the
    execution to terminal `completed` with only `succeeded` or `failed` stored.
    A notification/checkpoint gap is repairably retryable; completed replay
    sends no second notification. The focused current-schema rehearsal applies
    all 47 migrations and passes 132 assertions with exact database/grant/
    fixture cleanup and the configured primary unchanged. No endpoint, client
    package, retained database, provider, Wompi, payment, demo, or deployment
    effect occurs.
149. Completed the internal restartable destination coordinator. Core loads
    the exact package/plan execution once and begins at route, component,
    publication, or completion according to the first unfinished durable
    stage. It adds no outer transaction or lock; each stage retains preview
    rederivation, authorization, serialization, atomic writes, reconciliation,
    and compare-and-swap checkpoint ownership. The focused proof forces a
    terminal checkpoint failure after three committed stages, resumes directly
    at completion, repeats only the repairable Search refresh, suppresses all
    terminal-replay package/Search writes, refuses changed immutable input, and
    cleans the second coordinator fixture exactly. No endpoint, UI, allocation,
    scheduler, package-specific behavior, provider, Wompi, payment, or
    deployment effect occurs.

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
