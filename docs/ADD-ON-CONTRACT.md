# RED-CMS Add-On Contract

Status: Version 5.1 trust validation, Owner authorization, read-only registry
reconciliation, and guarded server-local installation are implemented.
Installation applies reviewed migrations, records exact evidence, and always
finishes disabled without executing package PHP. A fixed runtime-registration
contract, fail-closed front-controller page-request bootstrap, constrained
atomic enablement, safe default component dispatch, and non-executing atomic
disablement are implemented and tested only with temporary first-party
fixtures. Component-editor schema/value validation, display-only rendering,
exact permissions, bounded enabled-package data loading, transactional
existing-record package updates, and immutable package-value revision
snapshots, validated history/preflight, and atomic restore execution are also
implemented as activation-blocked prerequisites. Read-only inactive component
creation planning and its activation-blocked atomic runner are implemented.
Permission-enforced inactive parent metadata, read-only delete planning, and
atomic inactive deletion with retained revision ledgers are implemented.
Typed internal services, exact static public `GET` routes, display-only
administrator tools, and non-executing administrator action preflight now have
separate fail-closed boundaries. Adapters, operational writable route/tool
actions, settings UI/endpoints, actual secret
lookup, and richer enablement remain blocked. RED-CMS does not
upgrade, uninstall, or purge packages through this contract yet.
The generic public-mutation boundary now has optional closed manifest metadata
and a value-free, non-executing declaration preflight. It has no dispatcher,
endpoint, package runtime, request/cookie/session state, database state, or
enablement effect.

## Implemented Trust Boundary

The first foundation batch provides:

- a closed data-only `addon.json` schema;
- fixed `addons/vendor/package` discovery beneath a server-owned root;
- path, manifest, RED-CMS/PHP compatibility, route, CSRF, settings, dependency,
  and outbound-host validation;
- an exact SHA-256 inventory for every package file except the self-referential
  manifest;
- required declaration and verification of the fixed `addon.php` entry point
  without including or executing it;
- rejection of traversal, absolute paths, symbolic links, undeclared files,
  missing files, checksum mismatches, dependency cycles, and incompatible
  required dependencies;
- normalized per-client Owner role and exact lifecycle capability grants;
- database-backed login and protected-request authorization refresh;
- one explicit, audited, server-local first-Owner bootstrap with exact target
  confirmations;
- empty per-client installation and immutable migration-ledger tables;
- empty bounded add-on lifecycle audit storage;
- deterministic manifest/inventory snapshots and fail-closed comparison of
  validated files with recorded state;
- a dry-run-first, Owner-authorized, server-local install command that applies
  reviewed package migrations, records failure/resume evidence, and remains
  disabled and unloaded;
- a page-request loader that reconciles all enabled evidence before execution,
  registers required dependencies first, and exposes exact handler ownership
  through a request-local lookup context; and
- adversarial dependency-free tests, installation/recovery tests, and a
  read-only CLI report plus disposable request-bootstrap acceptance.

The clean starter contains no `addons/` package directory and no registry,
migration, or add-on audit rows. A client or operator deploys trusted package
files separately. Discovery and registry reconciliation remain read-only; the
separate install command can apply reviewed migrations but cannot enable or
load the package. No Guest, Webmaster, or legacy Superadmin receives package
lifecycle authority automatically. An account receives it only after a
client-specific Owner row and exact grants are deliberately bootstrapped.

## Product Objective

RED-CMS should remain a lightweight reusable core that can be adapted to
different kinds of clients through optional packages. A client installation
receives only the components, services, and integrations it needs.

Core owns only the extension framework: package discovery, validation,
lifecycle, permissions, routing boundaries, and migration controls. Store Lite
and every other business package are separately distributed optional add-ons.
They are not core components, bundled starter features, or automatically
installed or enabled.

The clean starter distribution must remain generic. Client add-on state,
business records, members, orders, appointments, donations, media, settings,
secrets, and database migrations belong only to that client's separate
installation and database.

The current optional package examples, in priority order if their individual
work is later approved, are:

1. Store Lite
2. Events Calendar
3. Appointments
4. Donations
5. Restaurant Ordering

Member Access / Protected Content is a separate cross-cutting package. It is
required before RED-CMS can safely activate private Sections or protected
downloads, but it is not a public listing directory and is not one of the five
business verticals above.

These packages are examples of the kinds of client adaptation the contract
should eventually support. They are not core features or committed Version 5.1
deliverables. Per-page SEO metadata compatibility and the isolated Adriana
launch gate take implementation priority over this add-on track.

## Terms

### Component

A component is an author-placeable content type that can occupy a RED-CMS page
layout position. It owns a bounded editor, validated data, a safe rendering
context, revision behavior, and a public presentation.

Examples include Product, Event, Appointment Service, Donation Campaign, and
Protected Content Gate.

### Service Add-On

A service add-on owns business behavior that cannot safely live in a component
template. It may own database records, administrator workspaces, public
endpoints, background jobs, notifications, and audit events.

Examples include carts and orders, calendar queries, appointment availability,
member entitlements, and donation transactions.

### Integration Adapter

An adapter connects one service capability to an external provider. Provider
credentials, callbacks, retries, and response identifiers belong to the
adapter rather than to a component.

Examples include PayPal checkout, Google Calendar synchronization, and an
email delivery transport.

### Content Package

A content package is a product-facing bundle of one or more components,
services, and optional adapters. It gives an installer one coherent capability
without merging unrelated client verticals.

For example, Store Lite combines a Product component and a commerce service,
then optionally enables a PayPal adapter.

### Public Listing Directory

A directory in the listing sense is a searchable collection of people,
locations, providers, or other public records. It would be a future Listing
component plus a search/filter service.

It is unrelated to private folders or member-only content.

### Member Access / Protected Content

Member Access is an optional identity, session, entitlement, and route-
enforcement package. It may provide locked registration, login, access-gate,
and secure-download components, but its essential responsibility is to deny
unauthorized access before protected content is queried or rendered.

## Package Boundary

The proposed first-party package structure is:

```text
addons/
  redcms/
    store-lite/
      addon.json
      addon.php
      src/
      admin/
      public/
      templates/
      assets/
      migrations/
      tests/
```

- `addon.json` is data only. It cannot contain PHP, SQL text, callbacks, class
  names selected from the database, or executable template fragments.
- `addon.php` is the one fixed entry point. Core may load it only after the
  package path, manifest, compatibility, integrity, and installation state pass
  validation.
- Package paths must resolve beneath the discovered package root. Absolute
  paths, traversal, symlink escape, and remote includes fail validation.
- The SHA-256 inventory detects undeclared or changed files after deployment,
  but a self-declared checksum does not authenticate a publisher. This first
  filesystem-deployed phase relies on operator-reviewed package provenance;
  signed distribution would be a separate future trust layer.
- Version 5.1 should begin with trusted, separately distributed,
  filesystem-deployed first-party packages. "First-party" describes project
  provenance and maintenance; it does not mean built into core or included as
  an active starter feature.
- Core must not provide arbitrary PHP upload or browser-based package
  extraction.
- PHP add-ons run in the RED-CMS process and are not a security sandbox.
  Operator-reviewed provenance and manifest validation establish the current
  trust decision; they do not make untrusted PHP safe.

## Manifest Contract

Every package declares:

- Schema version
- Stable namespaced package identifier
- Display name, description, and semantic version
- RED-CMS and PHP compatibility ranges
- Package type and provided capabilities
- Required and optional package dependencies
- Requested administrator permissions
- Components, services, administrator tools, administrator actions, and adapters
- Optional data-only component editor schemas
- Settings schema, including which settings are secret references
- Ordered immutable migrations and their checksums
- Public and administrator route declarations
- Optional closed public-mutation declarations, separate from routes
- Background jobs and retry policy
- Exact outbound network hosts
- Public/admin assets and their loading locations
- Uninstall and data-retention behavior

An illustrative manifest shape is:

```json
{
  "$schema": "https://red-sphere.com/schemas/addon-manifest-v1.json",
  "schemaVersion": 1,
  "id": "redcms.store-lite",
  "name": "RED-CMS Store Lite",
  "description": "Optional product catalog, cart, and order capability.",
  "version": "0.1.0",
  "type": "content-package",
  "compatibility": {
    "cms": ">=5.1 <6.0",
    "php": ">=8.2"
  },
  "provides": {
    "components": ["redcms.store-lite/product"],
    "services": ["commerce.catalog", "commerce.cart", "commerce.orders"],
    "adminTools": ["redcms.store-lite/orders"],
    "adapters": []
  },
  "dependencies": {
    "required": [],
    "optional": [
      {
        "id": "redcms.paypal",
        "version": ">=0.1 <1.0"
      }
    ]
  },
  "permissions": [
    "store.products.manage",
    "store.orders.view",
    "store.orders.manage",
    "store.settings.manage"
  ],
  "adminToolContracts": [
    {
      "tool": "redcms.store-lite/orders",
      "label": "Orders",
      "description": "Review current Store Lite order status.",
      "icon": "orders",
      "permission": "store.orders.view",
      "mode": "read-only"
    }
  ],
  "adminToolActionContracts": [
    {
      "tool": "redcms.store-lite/orders",
      "action": "redcms.store-lite/mark-order-paid",
      "label": "Mark order paid",
      "description": "Preflight a manual payment transition for one order.",
      "permission": "store.orders.manage",
      "method": "POST",
      "csrf": "required",
      "idempotency": "once-per-target"
    }
  ],
  "componentEditors": [
    {
      "component": "redcms.store-lite/product",
      "label": "Product",
      "description": "Create and edit one placeable Store Lite product.",
      "icon": "package",
      "permissions": {
        "create": "store.products.manage",
        "view": "store.products.manage",
        "edit": "store.products.manage",
        "delete": "store.products.manage",
        "publish": "store.products.manage",
        "restore": "store.products.manage"
      },
      "fields": [
        {
          "key": "title",
          "label": "Title",
          "type": "text",
          "required": true,
          "maxLength": 200
        },
        {
          "key": "summary",
          "label": "Summary",
          "type": "textarea",
          "required": false,
          "maxLength": 2000
        },
        {
          "key": "price-minor",
          "label": "Price in minor currency units",
          "type": "integer",
          "required": true,
          "minimum": 0,
          "maximum": 2147483647
        },
        {
          "key": "availability",
          "label": "Availability",
          "type": "select",
          "required": true,
          "options": [
            {
              "value": "available",
              "label": "Available"
            },
            {
              "value": "unavailable",
              "label": "Unavailable"
            }
          ]
        }
      ]
    }
  ],
  "settings": [],
  "migrations": [],
  "routes": [],
  "publicMutationContracts": [],
  "jobs": [],
  "outboundHosts": [],
  "assets": {
    "public": [],
    "admin": []
  },
  "integrity": {
    "entrypoint": "addon.php",
    "files": [
      {
        "path": "addon.php",
        "sha256": "0000000000000000000000000000000000000000000000000000000000000000"
      }
    ]
  },
  "uninstall": {
    "defaultDataAction": "retain",
    "allowExplicitPurge": true
  }
}
```

The zero checksum above is illustrative and must be replaced by the exact
SHA-256 of the deployed `addon.php`.

Manifest setting definitions are data only. Their fixed types are `text`,
`boolean`, `integer`, `select`, `url`, `email`, and `secret-reference`.
Defaults are optional, must match the exact declared non-secret type, and a
null default means that no configured default exists. Select choices are
non-empty and closed. A secret setting cannot contain a default or secret
material; its eventual configured value is only an opaque lowercase
`config:` reference to server-local configuration.

`red_addon_settings_schema()` returns only the normalized definitions when the
complete settings array passes. `red_addon_settings_validate_values()` accepts
one closed object, rejects unknown or nested values, control bytes, loose
boolean/integer coercion, out-of-range integers, undeclared selections,
malformed URLs/emails, and invalid secret references, and reports missing
non-default settings exactly. On success it returns non-secret values and
secret-reference identifiers in separate maps. It never resolves a secret,
reads or writes a database, authorizes an actor, renders a form, executes
package PHP, or changes package lifecycle state. Per-client storage,
permissioned editing, and activation readiness are later contracts.

Operational setting definitions may add one `permission` that must exactly
match a permission declared by the package. The clean installer provides an
empty per-client `RED_Addon_Settings` table keyed by package and setting. It
stores normalized ordinary scalar JSON separately from opaque `config:`
identifiers and restricts package deletion through the installation foreign
key. `red_addon_setting_write_preflight()` is read only: it binds the exact
validated filesystem package to the recorded version, type, manifest hash,
inventory hash, and installed-disabled or enabled state; revalidates the full
target configuration; requires fresh binary grants for every setting; rejects
unknown or malformed current rows; and returns only deterministic current,
target, and plan hashes. It never returns setting values, writes storage,
resolves secrets, renders controls, invokes package code, or changes lifecycle
state.

`red_addon_setting_write()` is that internal atomic writer. It accepts the
complete configuration plus the exact preflight plan SHA-256, refuses a
caller-owned transaction, acquires the shared lifecycle and exact package
locks, then locks the installation and setting rows. Inside one transaction it
recreates the plan and grants, compares the digest, replaces every normalized
row, reloads the exact target hash/count, and records one
`addon.settings.updated` / `settings_updated` fact containing no values or
secret references. Exact state returns `unchanged` without a duplicate audit.
Stale plans, drift, replacement/postcondition/audit failure, or injected late
failure roll back completely. It resolves no secret, executes no package code,
renders no form, exposes no endpoint, and does not change activation.

`red_addon_setting_read_model()` is the separate core-only current-setting
reader. It accepts only a trusted complete package plus numeric current actor,
rechecks the exact installed package identity and an `installed_disabled` or
`enabled` lifecycle, and requires an explicit package-declared permission for
every setting. Each setting receives a fresh binary grant decision; only the
authorized subset is returned. Non-secret entries retain their normalized
typed stored value or trusted manifest default, with an explicit `stored`,
`default`, or `unset` source. Secret-reference entries disclose only the
boolean configured state and never a `config:` identifier. The deterministic
model hash covers only the returned subset. Missing grants, unknown/malformed
stored rows, identity/lifecycle drift, or encoding failures return no partial
model. This helper renders no control, exposes no endpoint, persists nothing,
executes no package, resolves no secret, and changes no lifecycle or
enablement gate.

`red_addon_secret_reference_declarations()` reads only the operator's bounded
server-local list of opaque `config:` identifiers from
`ADDON_SECRET_REFERENCES` and `RED_ADDON_SECRET_REFERENCES`; neither source
contains secret values. `red_addon_secret_availability_evidence()` revalidates
the exact typed configuration and binds the package id, configuration,
declaration inventory, and per-setting availability into deterministic hashes.
It returns counts, missing setting keys, and fingerprints but no `config:`
identifier or secret value. Invalid, changed, or forged declarations fail
closed. This is non-executing availability evidence, not secret resolution: it
reads no database or secret, invokes no package, renders no control, persists
nothing, and does not change lifecycle or activation eligibility.

`red_addon_asset_plan()` is the separate non-executing package-asset
prerequisite. It accepts a trusted manifest's exact `public` or `admin` asset
array and produces a sorted, SHA-256-bound plan only for package-owned
`assets/*.css` files at `head` and `assets/*.js` files at `body-end`. Every
planned URL uses the reserved `/_red/addons/<vendor>/<package>/...` namespace
and includes the declared checksum as its immutable version. Duplicate paths,
unknown fields, unsafe paths, unsupported file types, invalid checksums, and
location/type mismatches produce no partial plan. The plan's core-owned HTML
renderer revalidates every row and its aggregate hash before emitting escaped
`link` or deferred `script` tags. This helper reads no package file, serves no
HTTP response, injects no response markup, accesses no database, executes no
package PHP, and changes no lifecycle or activation gate. Immutable delivery
is a separate contract; core-owned document injection is specified below.

`red_addon_asset_delivery_preflight()` is the next read-only prerequisite. It
claims only an exact, checksum-versioned URL in the reserved
`/_red/addons/<vendor>/<package>/assets/...` namespace. Before returning any
internal delivery evidence, core revalidates the complete package manifest and
inventory without loading `addon.php`, requires current `enabled` registry
evidence, recreates both public and administrator asset plans, rejects
noncanonical or stale versions, and resolves the declared file through the
same no-symlink package-file guard used by integrity validation. It then checks
the current file length and SHA-256. A successful result identifies only the
exact CSS or JavaScript type, location, content type, byte length, internal
path, checksum, and declared surface; it contains no file bytes and is not an
HTTP response. The helper writes no database or lifecycle state, emits no
header or markup, and does not execute package PHP. The static delivery
endpoint must re-run this preflight for its current request and must never
accept a filesystem path from a caller.

`includes/addon_asset_endpoint_helpers.php` now provides that core-owned
static delivery boundary. `index.php` claims the reserved namespace before
theme bootstrap, session startup, or enabled-package runtime registration. For
each claimed request it opens only the current client's database connection,
reruns the preflight, and accepts only `GET` or `HEAD` after the package is
still `enabled_current`. A successful response serves at most 4 MiB of the
exact preflighted CSS or JavaScript bytes after a final in-memory byte-length
and SHA-256 comparison. Core emits the fixed matching content type,
`Cache-Control: public, max-age=31536000, immutable`, `nosniff`,
`Accept-Ranges: none`, and the exact content length. `HEAD` has the same
metadata with no body.

Malformed, noncanonical, stale, undeclared, disabled, drifted, missing, or
oversized assets return only a generic `404` with `no-store`; registry storage
unavailability returns a generic `503`; and a valid asset requested with any
method other than `GET` or `HEAD` returns a generic `405` with the fixed
`Allow` header. The endpoint exposes no preflight reason, internal path,
manifest, registry row, session, package output, package PHP, mutation, or
markup injection. Admin-surface assets are static package files, not a
separate authorization boundary.

`includes/addon_asset_injection_helpers.php` provides the separate core-owned
document boundary. After the pre-existing request runtime bootstrap,
`index.php` re-discovers trusted manifests and current registry evidence without
loading `addon.php`, then revalidates both asset surfaces for every enabled
package. An ordinary document receives only public CSS at `head` and public
JavaScript at `body-end`; an existing nonempty administrator session alias also
selects the administrator counterparts. Core captures document-start and
document-end phases and emits escaped tags only immediately before exactly one
matching closing boundary. Any catalog, registry, integrity, plan, or document
boundary failure leaves package markup out of the response. The planner does
not start a session, invoke a registrar, write state, or relax activation; the
endpoint independently revalidates any later browser request for the static
bytes.

The exact Version 1 schema is
`docs/addon-manifest.schema.json`; the read-only PHP validation contract is
`includes/addon_manifest_helpers.php`. This remains a trust-inspection contract,
not an active loader contract.

`componentEditors` is optional in Manifest Version 1 so existing render-only
component packages remain compatible. When present, each editor must target
one identifier already declared by `provides.components`, bind all six
lifecycle operations to permissions already declared by the package, and use
only the fixed field types and bounds in the published schema. The data cannot
name a table, column, class, callback, template, SQL fragment, or persistence
handler. `red_addon_component_editor_schema()` returns only the normalized
data-only definition.

`red_addon_component_editor_validate_values()` is the next non-executing
prerequisite. It resolves an exact valid editor schema from the supplied
manifest data, then accepts one object of submitted scalar values. Operational
callers must use the package's already trust-validated catalog manifest.
Unknown fields, arrays, invalid UTF-8 or controls, non-canonical integers,
values outside declared bounds, unlisted select choices, malformed
URLs/email/dates/datetimes/media references, and missing required values fail
closed. On any error it returns no normalized values. This helper does not
authorize an actor, render a form, execute the registrar, load package data,
select a table or callback, or write state.

`red_addon_component_editor_ui_render()` is the separate display-only
administrator prerequisite. It accepts the validated manifest plus either no
value state or the exact closed result shape returned by the value validator.
Core maps the fixed types to fixed inputs, selects, and textareas; escapes all
package-declared copy, choices, and values; provides stable labels, help,
required state, and fixed error messages; and fails closed on forged or
ambiguous state. The fragment contains no form, action, Save control, script,
style, package template, rejected raw input, authorization decision, package
data lookup, or persistence behavior. Callers rendering more than one instance
must provide a distinct safe id prefix.

`red_addon_component_revision_ui_render()` is the separate display-only
revision-timeline boundary. Its history argument must have the exact
value-free shape produced by `red_addon_component_revision_history()`, remain
strictly newest-first, and have a newest state hash equal to the supplied
current core-owned hash. Core revalidates every closed metadata field, escapes
all displayed actor and timestamp text, and discloses neither stored values nor
state hashes. The newest row is current; an older equal state is marked as
matching current; every other older row is marked as requiring a fresh restore
check. Empty, stale, reordered, malformed, duplicate, or value-bearing input
fails closed. The fragment contains no form, button, link, script, style,
package template, restore preflight, restore action, authorization lookup,
package execution, endpoint, or write.

`registerComponentDataDeleter()` and
`red_addon_component_editor_delete_preflight()` define the read-only deletion
plan boundary. A declared editor may bind at most one optional deleter with one
to eight package-owned `RED_Addon_*` transaction tables. Before returning a
plan, core requires the exact delete grant before package loading, then the
view grant, enabled manifest/component/loader/deleter ownership, inactive
hidden unrouted parent shell, caller-supplied current parent and package state
hashes, current core revision evidence, latest integrity-valid package
revision, and InnoDB support for every future transaction table. The plan is
deterministic and contains no package values. Preflight does not invoke the
deleter, lock a row, open a transaction, record a revision or audit event,
delete data, render a control, expose an endpoint, or authorize public
placement. The trusted first-party callback is registration evidence for a
separate atomic runner, not executable preflight code or a PHP sandbox.

`red_addon_component_editor_delete_values()` is that activation-blocked atomic
runner. It refuses caller-owned transactions and revalidates the caller's exact
plan under the shared lifecycle lock, theme-contract lock, enabled-installation
row lock, and exact parent binding. Before mutation, core reloads and hashes the
current values and inserts duplicate-preserving `delete` snapshots into both
the package and core revision ledgers using the explicit administrator actor.
It then invokes only the registered deleter with fixed identity context,
requires zero matching rows in every declared package table, removes article
SEO metadata, and deletes exactly one inactive hidden parent. A stale plan,
revoked grant, output, exception, changed buffers, false return, partial
deletion, lost transaction, revision failure, or postcondition failure rolls
back all data and attempted evidence. Success deliberately retains both
immutable ledgers. The runner exposes no endpoint, form, control, audit event,
media deletion, uninstall/purge, public placement, or activation authority.

`red_addon_component_editor_permission_decision()` is the next database-backed
authorization prerequisite. It resolves one of the six fixed editor operations
to the exact permission already present in the normalized schema, then requires
an exact case-sensitive `RED_Admin_Capabilities` row for that administrator in
the current client database. Owner role, Webmaster/legacy Superadmin type,
add-on lifecycle capabilities, and unrelated package grants do not imply
access. The lookup is fresh so revocation applies on the next decision. It
creates no grant, role, endpoint, form, audit event, package state, or business
write and does not replace protected-session, CSRF, enabled-package,
transaction, revision, or data-loader checks.

`registerComponentDataLoader()` and
`red_addon_component_editor_load_values()` provide the bounded current-value
prerequisite. A registrar may bind exactly one loader only for a component
that declares editor metadata. Core invokes it only after the exact view grant,
the numeric persisted parent, enabled installation, request-local component
owner, manifest package id, and data-loader owner agree. The loader receives
only the current database connection plus the exact component id and numeric
content record id. Output, exceptions, altered output buffers, foreign owners,
disabled or drifted parents, unknown fields, and invalid values fail closed.
Core revalidates the complete returned object and exposes its normalized values
with a package/component/record-bound SHA-256 state hash. This trusted
first-party in-process callback is not a PHP sandbox; the contract requires a
read-only loader. Core exposes no editor endpoint and performs no package,
content, authorization, lifecycle, revision, or audit write.

`registerComponentDataWriter()` and
`red_addon_component_editor_update_values()` provide the activation-blocked
existing-record update prerequisite. A registrar may bind at most one writer
for a declared editor and must list one to eight package-owned `RED_Addon_*`
transaction tables. Core requires those tables and `RED_Articles` to be
InnoDB, locks the exact enabled placement parent, requires current view and
edit grants, reloads and compares the current state hash, and passes only the
normalized submitted values plus bounded identity context. It contains output,
exceptions, and altered buffers, requires a strict true return, reloads the
saved values through the bounded loader, and commits only when the complete
postcondition matches. Otherwise it rolls back. Before the writer runs, core
records the current normalized values as a baseline or checkpoint when needed;
after the exact postcondition passes, it records the saved values. Both
immutable snapshots and the package write commit or roll back together in the
per-client `RED_Addon_Component_Revisions` ledger. An identical submission is
a successful no-op and creates no revision. This trusted first-party
callback is not a sandbox and must not issue transaction controls, DDL, or
writes outside its declared tables. Core exposes no endpoint or form and adds
no create, parent-metadata write, revision history UI, restore, delete, audit
workflow, or activation eligibility.

Discovery, validation, display-only rendering, permission decisions, bounded
data loading, existing-record updates, and revision snapshots do not authorize
activation.
`red_addon_component_revision_history()` and
`red_addon_component_revision_restore_preflight()` add read-only prerequisites:
bounded newest-first metadata only after the exact view grant and enabled
binding, plus an integrity-validated target snapshot and deterministic plan
only after the exact restore grant and current state hash. They invoke no
writer, apply no restore, expose no endpoint, and write no audit or lifecycle
state.
`red_addon_component_revision_restore_values()` is the separate atomic apply
prerequisite. It locks the exact enabled placement parent, re-runs the complete
restore preflight, requires the caller's exact current-state and plan hashes,
invokes only the registrar-bound writer with the integrity-validated target
values, reloads the target postcondition, and commits one immutable `restore`
snapshot linked to the source revision. Stale or substituted evidence, revoked
view/restore grants, binding drift, writer/output/buffer failures, incomplete
writes, lost transactions, and revision-ledger failures roll back. It opens no
endpoint, form, history UI, audit workflow, activation path, create path,
parent-metadata mutation, or delete behavior.
`registerComponentDataCreator()` and
`red_addon_component_editor_create_preflight()` add a separate read-only
creation prerequisite. A registrar may bind at most one optional creator for a
declared editor and must declare one to eight package-owned `RED_Addon_*`
transaction tables. Core requires the exact create grant, enabled installation,
exact runtime manifest and component/loader/creator owners, an unused numeric
record id with no parent/revision/SEO evidence, an active-theme layout, closed
title/layout/language parent metadata, fully normalized package values, and
InnoDB support for every future transaction table. It returns a deterministic
hash over an inactive, hidden, unrouted core parent shell and the package plan.
It does not invoke the creator or loader, reserve an id, open a transaction,
or write state. The callback is registration evidence for the separate trusted
first-party atomic runner, not an executable preflight hook or a PHP sandbox.
`red_addon_component_editor_create_values()` revalidates that exact plan under
the database-wide add-on lifecycle lock, the active-theme contract lock, and
the enabled installation row lock. It inserts only the planned inactive hidden
parent, invokes the creator with bounded identity context and normalized
values, reloads through the registered loader, and requires the complete
parent and package postconditions. The parent, package row, core-owned
`create` content revision, and package-value `baseline` revision commit in one
transaction. Caller-owned transactions, stale evidence, callback output,
exceptions, buffer changes, false returns, partial writes, postcondition
mismatch, and either ledger failure fail closed. The runner remains
activation-blocked and exposes no form, route, audit event, parent-metadata
editor, public placement, delete, or activation eligibility.
`red_addon_component_editor_parent_state()` and
`red_addon_component_editor_parent_update()` implement the separate
activation-blocked parent-metadata prerequisite. Read-only state requires the
exact view grant, enabled manifest/runtime/persisted binding, a closed inactive
hidden unrouted shell, a schema-valid package loader result, and a current core
revision whose hash matches the parent snapshot. The writer first requires the
exact edit grant and caller state hash without invoking package code, then
serializes lifecycle and theme changes, locks the enabled installation and
parent, and rechecks every condition. It may update only `Title`, `Layout`, and
`Language`; the entire remaining parent shell and package state must match the
pre-write values. One explicit-actor core `save` revision commits with a
change, while an identical submission adds no revision. Unsupported parent
state, stale hashes, revoked grants, caller-owned transactions, postcondition
failure, lost transactions, and revision failure roll back. These helpers
expose no form, route, public placement, activation, delete, audit event, or
package-value write.
`red_addon_component_editor_publish_preflight()` implements the next read-only
public-placement prerequisite. It requires the exact publish grant before
package loading, then reuses the exact view-authorized inactive parent,
enabled runtime/binding, package-state, and current core-revision evidence.
The caller supplies only numeric source and target ids, a page position and
order, plus the current parent/package hashes. Core derives one uniquely owned,
active `Article` destination route, its hierarchy, alias, layout, and language;
requires source/destination language agreement; and validates the resulting
candidate against the active theme's page-position contract. The deterministic
plan binds the exact actor, package, component, source state, destination state,
and closed placement values. It invokes no package writer, opens no transaction
or endpoint, and writes or activates nothing.
`red_addon_component_editor_publish_values()` implements the separate atomic
runner behind that exact plan. It refuses caller-owned transactions, acquires
the database-wide add-on lifecycle and active-theme locks, then locks the
enabled installation, exact inactive source parent, and numeric destination
page. The complete preflight and plan hash are revalidated under those locks.
Core updates only `Sections`, `Categories`, `SubCategories`, `Article`,
`PagePosition`, `PagePositionOrder`, and `Active`; no package writer or
mutating package callback runs.
The complete source row, unchanged package state, unchanged destination state,
fresh publish grant, one explicit-actor core `move` revision, and one bounded
`component.public_placed` administrator audit fact are required postconditions.
Stale or reused plans, destination drift, revoked grants, transaction loss,
unsupported placement, row mismatch, revision failure, and audit failure roll
back. The core-owned authenticated POST/CSRF control exposes only numeric
destination choices and current parent/package hashes; core derives package,
component, manifest, grant, target ownership, and exact plan evidence again.
Enablement preflight continues to report
`component_editor_contract_required` while richer component-editor packages
remain outside the admitted enablement profiles; completing this operational
control does not implicitly enable any package.

## Core Registry And Execution

Add-on discovery and add-on activation are separate.

1. Core discovers package directories from a fixed server-owned root.
2. Core validates the manifest, package path, compatibility, integrity, and
   dependency graph without executing the package.
3. Installation records the package inventory in the current client database
   and applies only that package's reviewed migrations.
4. The package remains disabled after installation.
5. An authorized owner enables the package only after a compatibility and
   dependency preflight passes.
6. On later requests, core loads only enabled packages whose code and recorded
   integrity still match.

Database rows must never choose an arbitrary file, class, method, template, or
callback. Core invokes fixed interfaces returned by the validated package
entry point.

The existing Article, Form, Gallery, and Other runtime remains intact. Add-on
registration must be additive and must not reinterpret legacy
`RED_Components` rows as executable packages.

The current registry foundation creates empty `RED_Addon_Installations` and
`RED_Addon_Migrations` tables in each client database. Installation rows are
keyed by the stable package id and record version, type, raw-manifest SHA-256,
deterministic declared-file inventory SHA-256, lifecycle state, actor ids, and
timestamps. Migration rows are keyed by package id plus immutable migration id,
uniquely bind each migration path, and record its declared checksum, actor,
timestamp, and execution duration.

`includes/addon_registry_helpers.php` and
`scripts/addon-registry-status.php` reconcile those rows with packages that
have already passed the non-executing trust gate. They report uninstalled
discovery, pending migrations, identity/checksum drift, orphaned migrations,
missing deployed code, and whether recorded enabled state has exact current
evidence eligible for request loading. These helpers perform no registry write,
package SQL execution, or `addon.php` inclusion.

`includes/addon_install_helpers.php` and
`scripts/admin-addon-install.php` implement the separate install transition.
The command:

- is CLI-only and dry-runs by default;
- requires the database-backed Owner role plus `addons.install`;
- locks the package id within the current client database;
- revalidates the complete package catalog, exact package inventory, migration
  checksums, and required installed/current/enabled dependencies;
- requires exact database, package, version, plan SHA-256, nonzero verified
  backup SHA-256 supplied from the separate backup procedure, and
  `installed_disabled` confirmations before apply;
- limits each migration file to 2 MiB, refuses privilege/database/user/plugin,
  routine/trigger/event, file-I/O, explicit transaction, registry-table,
  core-table, and obvious unnamespaced-table SQL, and permits package-owned
  `RED_Addon_*` table work only;
- records each completed migration by immutable id, path, and checksum;
- writes bounded start/completion/failure audit facts without SQL, paths,
  secrets, settings, or request bodies; and
- never includes `addon.php`, never enables runtime, and always completes as
  `installed_disabled`.

The SQL checks are defense-in-depth for operator-reviewed first-party packages,
not a parser or sandbox for untrusted SQL. MySQL DDL may commit implicitly, so
the runner does not claim transaction rollback for an already applied DDL
statement. It records `installation_failed`, preserves the exact completed
ledger, and requires `--resume-failed` plus a newly reviewed plan to continue.

`includes/addon_enable_preflight_helpers.php` and
`scripts/admin-addon-enable-preflight.php` implement the next read-only
enablement inspection boundary. The command:

- is CLI-only and exposes no `--apply` path;
- requires the database-backed Owner role plus `addons.enable`;
- accepts only an exact `installed_disabled_current` package with matching
  code, manifest, inventory, and migration evidence;
- fails closed if any recorded package is missing, invalid, or drifted;
- requires every declared dependency to be installed, compatible, current,
  and recorded enabled;
- detects provided-component, service, administrator-tool, adapter, route-id,
  and overlapping route-method ownership conflicts against currently enabled
  packages;
- inventories runtime declarations and binds the current database, actor,
  target package, enabled-package snapshots, dependencies, gates, and blockers
  into one deterministic plan SHA-256; and
- never writes registry or audit state, never includes `addon.php`, and never
  loads package code.

A valid diagnostic plan is deliberately not an activation authorization.
`enableReady`, `activationSupported`, `stateMutation`, and `runtimeLoad` remain
false. Runtime registration is reported available. Declarative theme,
settings, and live-data gates may clear only for these constrained profiles:

- `registration_only_service`: at least one declared service and no component;
- `default_public_component`: at least one declared component and no service,
  with theme compatibility supplied only by core's escaped default renderer;
- `default_public_component_with_services`: at least one component and one
  service, with the same core-owned default component renderer and no automatic
  service invocation.

All three profiles exclude migrations, settings, routes, jobs, public or
administrator assets, administrator tools, adapters, and outbound hosts.
Every richer surface remains explicitly blocked. The package registrar remains
unexecuted. The separate CLI-only enable command must revalidate and execute
that registrar before its state change. It accepts only these profiles, takes
the database-wide lifecycle lock and package lock, requires exact target,
plan, backup, and disabled-state confirmations, then commits its
compare-and-swap state change and bounded success audit fact in one
transaction. No package can move to `enabled` through this preflight command.

`includes/addon_disable_helpers.php` and
`scripts/admin-addon-disable.php` implement the reverse non-destructive
transition. Its dry run requires the database-backed Owner role plus
`addons.disable`, exact current `enabled` package and registry evidence, and a
deterministic inventory of every other enabled package. It reports and refuses
any enabled package whose required dependency list names the target. Apply
recreates that exact plan under the shared lifecycle lock and target package
lock, requires exact target, plan, nonzero backup SHA-256, and enabled-state
confirmations, then atomically records `installed_disabled` plus
`addon.disable.completed`. It never includes package PHP, invokes a registrar,
runs migrations, removes package files, or deletes package settings, media,
migration evidence, or business data.

`includes/addon_runtime_helpers.php` establishes the executable registration
contract and `index.php` connects it only to front-controller page requests,
public or authenticated, not to a
lifecycle transition or lifecycle CLI:

- core may include only the fixed, real, non-symlinked `addon.php` whose
  checksum still matches the validated manifest immediately before inclusion;
- the entry point must return exactly one registrar callable; neither inclusion
  nor registrar invocation may emit output, and the registrar must return null;
- registration accepts only manifest-declared component, service,
  administrator-tool, administrator-action, adapter, and route identifiers;
- each declared component editor requires exactly one data loader and may bind
  at most one creator and one existing-record writer with closed package-table
  metadata; creator invocation is limited to the exact activation-blocked
  atomic plan runner;
- every declared runtime identifier must register exactly once;
- required enabled dependencies are ordered before their dependents;
- every enabled registry row, package identity, migration ledger, dependency,
  capability namespace, and route namespace is reconciled before the first
  package executes; and
- missing, changed, incomplete, duplicated, or undeclared runtime evidence
  fails closed.

The contract still assumes operator-reviewed first-party PHP and is not a
sandbox. `scripts/addon-runtime-self-test.php`,
`scripts/addon-service-invocation-self-test.php`, and
`scripts/addon-request-bootstrap-self-test.php` execute only temporary fixtures
outside the clean starter. Uninstalled and disabled packages remain
unexecuted. Current enabled registrars run once in dependency order. Core
invokes only an enabled manifest-declared component through its fixed public
placement context and core-owned default renderer. A declared component data
loader may be invoked only through the exact permission/binding/schema gate
above, and a declared writer only through the transaction/state/postcondition
gate above. Services dispatch only through the typed internal boundary below.
Exact static public `GET` routes may dispatch only through the core JSON
boundary below. A declared read-only administrator tool may dispatch only
through the fresh-permission/core-renderer boundary below. A declared
administrator action may only establish non-executing preflight evidence
through the separate action boundary below; adapters remain non-dispatched.
Request failure returns
a generic temporary-unavailability response while detailed evidence remains in
the server log. Owner-authorized enablement is a separate reviewed lifecycle
step. It must revalidate the approved plan and registrar under the shared
lifecycle lock and target package lock before its atomic state transition.

`includes/addon_service_helpers.php` is the only generic core-to-package
service invocation boundary. It resolves the exact request-local owner and
manifest declaration, constructs a final request object from one bounded
operation id and JSON-compatible input, and accepts only a final result object.
It rejects floating-point values in favor of explicit string representations,
bounds depth, nodes, keys, strings, and encoded size, and contains package
output, exceptions, output-buffer changes, and malformed results. It supplies
no database connection, HTTP request, session, administrator authority, or
automatic invocation.

## Component Contract

A placeable add-on component must provide:

- A stable namespaced component id
- Human label, description, and administrator icon
- Permission requirements for create, view, edit, delete, publish, and restore
- A validated create/edit field schema
- Server-side normalization and validation
- Transactional persistence
- A parent relationship to the RED-CMS page-placement record
- A bounded public data loader
- A non-executable rendering context
- An accessible default public view
- Content-revision history, restore, export, and import behavior
- Delete/deactivate behavior and dependent-media rules
- Theme compatibility and CSS isolation metadata

The existing `RED_Articles` record may continue to own route, placement,
layout, visibility, and ordering fields. Specialized records should use a
package-owned table with an explicit numeric relationship to the parent
article. Parent and child writes must share one transaction.

The generic parent-relationship foundation persists the complete validated
component id in `RED_Articles.Component` and permits a reviewed package
migration to declare only an exact foreign key to
`RED_Articles(RecordID)`. The package SQL guard continues to reject inserts,
updates, deletes, schema changes, alternate-column references, and every other
core-table access. Before production public dispatch, core resolves the
numeric parent read-only and requires its component id, the enabled
installation state, and the request-local runtime owner to agree exactly.
Core does not select a package table or store specialized fields.

The data-only create/edit field schema, submitted-value normalization,
display-only administrator rendering, exact permission decisions, and bounded
current-value loading prerequisites are implemented. The loader executes only
from the enabled registrar owner, returns no values until core revalidates the
complete schema, and produces a core-owned state hash. Existing package values
may now update through the transaction and postcondition gate above while the
core parent remains locked and unchanged, with immutable baseline and saved
snapshots committed in the same transaction. An exact validated revision may
also be restored atomically through the same writer boundary with a
source-linked restore snapshot. This foundation can now atomically execute the
exact read-only creation plan for an inactive hidden parent and package row,
including both initial revisions. The separate core-owned parent boundary can
now read and atomically update only title, active-theme layout, and language
while the record remains an inactive hidden unrouted shell, with exact grants,
stale-state refusal, package-state preservation, and one core revision. It does
not open an operational form or endpoint, choose public placement, provide a
restore action, export/import, delete, or add an audit workflow. Those editor and
lifecycle contracts remain separate reviewed batches.

Add-on components must not be implemented by adding another hard-coded switch
to `class_content.php`. The add-on registry is the only new dispatcher.

The first public-dispatch slice passes an enabled component only a fixed,
non-executable placement context (`component`, numeric `recordId` and
`position`, plus bounded `layout` and `article` strings). The registered
handler returns exactly a text-only `title` and `summary` view model; core
escapes and renders that model using the accessible default view. Handler
output, malformed view models, and handler failures render only the static
unavailable-content fallback and never fall through to legacy component
rendering. This is a foundation for future component persistence and editor
contracts, not permission to add Store Lite data or templates to core.

## Theme And CSS Boundary

- Business rules, permissions, database access, forms, endpoints, and secrets
  remain core/add-on responsibilities, never theme responsibilities.
- Every component supplies an accessible default view so a theme does not have
  to implement every possible add-on.
- A theme may style stable namespaced hooks.
- Optional theme overrides require an explicit declared component contract and
  compatibility preflight.
- Public styles must be scoped beneath a namespaced add-on root such as
  `.red-addon--store-lite`.
- Add-ons must not ship unscoped element selectors that can alter the
  authenticated administrator interface.
- Administrator controls remain inside core-owned isolated workspace styles.

## Service And Route Contract

A service add-on may register bounded routes through a core router. It must
declare:

- HTTP methods and authentication requirements
- CSRF policy
- Request and response limits
- Permission requirements
- Rate limits where relevant
- Transaction and idempotency behavior
- Cache and privacy behavior
- Audit events
- External host access

Packages must not create arbitrary root PHP endpoints or edit `index.php` or
`admin/mainnav.php`. Public URLs already owned by a client remain unchanged.
New package endpoints use a reserved core namespace unless a separately
reviewed compatibility route maps an existing URL.

`includes/addon_public_route_helpers.php` is the only implemented public route
boundary. This first slice resolves an exact manifest path from the enabled
request-local registrar and requires `scope: public`, `authentication: public`,
`GET`, and `csrf: not-applicable`. The path must be static and unencoded. Core
supplies a final request containing only the declared route id, method, exact
path, and bounded JSON-compatible query values. The handler must return a final
result; core constructs the JSON body and fixed security/cache headers. Package
output, exceptions, output-buffer changes, malformed results, and oversized
responses produce only a generic temporary-unavailability response.

This does not dispatch member routes, unsafe methods, placeholder routes,
administrator routes, HTML, redirects, uploads, files, sessions, server
variables, database connections, or arbitrary headers. It also does not make a
route-bearing package eligible for enablement: all current enablement profiles
continue to reject routes until richer package lifecycle gates are reviewed.

## Public Mutation Declaration Boundary

[PUBLIC-MUTATION-BOUNDARY.md](PUBLIC-MUTATION-BOUNDARY.md) defines the generic
core-owned path for a narrowly declared static public POST mutation. The
internal transaction runner and pure bounded response contract are implemented,
but no public dispatcher, response emission, or enablement profile can reach
them.
The optional `publicMutationContracts` field now validates only closed,
data-only metadata: one already-declared static public POST/CSRF route, a
unique mutation identity, two bounded scalar field shapes, fixed anonymous,
CSRF, idempotency, privacy, rate-limit, and postcondition policies, package
table declarations, a value-free audit category, and the fixed `accepted` /
`unchanged` outcome vocabulary.

`includes/addon_public_mutation_preflight_helpers.php` converts one validated
declaration into deterministic hashes and value-free counts only. It never
loads a package, reads request/cookie/session state, reads a database, verifies
tables, creates identity/CSRF/idempotency material, starts a transaction, or
invokes a handler. Unknown/executable metadata, reserved core request names,
route drift, weak policies, duplicate identities, and core add-on tables fail
closed before package PHP is loaded.

`includes/addon_public_mutation_live_data_helpers.php` is a separate,
read-only client-scoped preflight. For one current trusted
`installed_disabled` package, it joins that declaration with existing
migration-ledger, declared InnoDB-table, typed-setting, and opaque
secret-availability evidence. Its valid plan exposes only identities, counts,
blocker codes, and SHA-256 fingerprints; it never returns a table name,
setting value, reference, or secret. A cleared data-evidence gate is not an
activation authorization: `enableReady`, `activationSupported`, and
`requestDispatch` remain false, and the helper does not issue anonymous/CSRF/
idempotency material, resolve a secret, execute package code, write state, or
relax route-bearing enablement.

`includes/addon_public_mutation_subject_helpers.php` is a separate internal
core-only foundation, not a package API. The clean starter's empty
`RED_Addon_Public_Mutation_Subjects`,
`RED_Addon_Public_Mutation_CSRF_Tokens`,
`RED_Addon_Public_Mutation_Rate_Limits`,
`RED_Addon_Public_Mutation_Idempotency_Keys`, and
`RED_Addon_Public_Mutation_Executions` are reserved core tables, so a
manifest cannot declare them as package-owned. The subject and CSRF tables
retain SHA-256 digests of random 256-bit values only. A future core endpoint may
use the returned host-only secure cookie descriptor and declaration/database-
scoped CSRF value; no current endpoint emits it, reads a browser cookie/session,
or exposes either raw value to package code. Subject expiry is 30 minutes and
CSRF expiry is 10 minutes.

`includes/addon_public_mutation_rate_limit_helpers.php` separately retains one
short-lived fixed-window row per client database, validated declaration, and
opaque anonymous subject. The row contains only the subject record relation, a
SHA-256 scope, the window/expiry facts, and a bounded count. Its internal claim
allows at most 12 requests per 60 seconds, refuses caller-owned transactions,
and fails closed when the exact InnoDB storage shape is unavailable. Its
transaction-only primitive is used by the internal runner. Neither form
receives a browser request nor loads package code; the standalone result is only
for a future core dispatcher.

`includes/addon_public_mutation_idempotency_helpers.php` separately retains one
short-lived key row per client database, validated declaration, and opaque
anonymous subject. The row contains only the subject relation, SHA-256 scope,
SHA-256 key digest, and creation/expiry facts. Its internal issuer returns a
fresh 256-bit opaque key with a fixed 10-minute lifetime; its resolver proves
only that the active subject and declaration match. It refuses issuance inside a
caller-owned transaction and fails closed when exact core storage is unavailable.
It does not consume a key or record a replay result itself: those actions are
reserved to the separate atomic transaction runner.

`includes/addon_public_mutation_execution_helpers.php` is the internal
core-owned runner. It accepts no HTTP request and constructs no public response.
It requires a current trusted enabled runtime binding for both a declared
mutation handler and its state loader, locks lifecycle/package state, verifies
the opaque subject, CSRF, idempotency key, rate decision, and declared InnoDB
tables, then commits only the package change, keyed HMAC command/state replay
evidence, and one value-free audit fact. Exact replays return a bounded stored
outcome without calling package code; changed commands are refused. It passes a
core-supplied active connection to reviewed first-party PHP, not a database
sandbox. Package code must not commit, roll back, use globals, emit output, or
write outside its declared tables. The empty clean-starter ledger stores no raw
token, request, route, package, cart, order, secret, or client business value.

`includes/addon_public_mutation_response_helpers.php` is a dependency-free,
non-emitting core response model. It turns only the runner's fixed
`accepted` / `unchanged` outcomes and a closed refusal map into exact JSON
envelopes. Those envelopes contain fixed `Content-Type`, `Cache-Control:
no-store`, `X-Content-Type-Options: nosniff`, `Content-Length`, and, only for
method refusal, `Allow: POST` headers. They expose no package, route, mutation,
subject, token, key, replay flag, state, cart, order, plan, secret, or internal
failure detail. The helper has no request-global, cookie, session, database,
package-load, header/cookie emission, or lifecycle path. A future core
dispatcher must still select and emit only a valid envelope after all request
validation and transaction work complete.

The current routes schema and addon_public_route_helpers.php remain public
GET-only. This contract does not add a dispatcher or endpoint, emitted
cookie/header or session access, browser form, route eligibility, package
fixture, or Store Lite behavior. It leaves legacy
public form operations unchanged. Each later live request stage remains a
separate disposable-fixture and richer enablement review.

## Administrator Tool Contract

An optional `adminToolContracts` entry maps one identifier from
`provides.adminTools` to one permission already present in `permissions`, plus
a bounded label, description, icon token, and the fixed `read-only` mode. The
manifest validator rejects duplicate, undeclared, ungranted, executable, or
writable metadata without loading package PHP. A provided tool without this
data-only mapping remains registered but cannot enter the chooser or dispatch.

`includes/addon_admin_tool_helpers.php` resolves the exact enabled
request-local owner, manifest contract, and registrar callback. It performs a
fresh case-sensitive lookup of the exact package permission in the current
client database; Owner, Webmaster, legacy Superadmin, lifecycle grants, legacy
`AdminTools`, and unrelated package grants confer no access. Revocation applies
on the next catalog or dispatch decision.

The handler receives one final request containing only the tool id and numeric
administrator record id. It must return one final result containing bounded
plain-text title, description, and label/value facts. Core escapes and renders
that model. Package output, exceptions, buffer/HTTP-state changes, malformed or
oversized results fail closed. `admin/bin/view_addon_tool.php` is POST-only,
requires a current protected administrator session and CSRF token, bootstraps
the enabled registrar, and invokes only this dispatcher.

An optional `adminToolActionContracts` entry is separate data-only metadata for
one bounded administrative transition. It maps one provided tool to one unique
action id, a bounded label and description, one explicitly declared permission,
only `POST` with `csrf: required`, and the fixed
`idempotency: once-per-target` policy. The manifest validator rejects
undeclared tools, duplicate or tool-equal action ids, ungranted permissions,
executable fields, other methods, weaker CSRF declarations, and weaker or
ambiguous idempotency declarations without loading package PHP.

Each declared action must have exactly one registrar-bound
`registerAdminToolAction()` handler and one
`registerAdminToolActionStateLoader()` handler. The action registration also
declares one to eight existing package-owned InnoDB `RED_Addon_*` tables; core
rejects core ledger names and undeclared, duplicate, malformed, or non-InnoDB
tables. The state loader receives a final target request plus the connection,
must return a final bounded target-state object for that same numeric target,
and must be read-only. This remains trusted first-party PHP, not a sandbox:
reviewed package code must not commit, roll back, alter output buffers, or
write outside its declared action contract.

`includes/addon_admin_tool_action_preflight_helpers.php` is a separate
read-only gate. It requires the exact enabled request-local tool and action
owners to agree, recreates the data-only contract, performs a fresh
case-sensitive permission check in the current client database, accepts only a
positive integer target record id, and returns deterministic contract and plan
SHA-256 values. It reads no package record and writes no state. It does not
start a transaction, invoke either package callback, accept request/session
globals, render a form, or expose an endpoint. The declared CSRF policy is
evidence for a later protected endpoint; it is not an authorization substitute
and no CSRF token is consumed here.

`includes/addon_admin_tool_action_execution_helpers.php` now provides the
separate internal atomic runner. It first recreates a state-aware preflight in
a rollback-only transaction, returning no target values—only exact contract,
metadata-plan, previous-state, and execution-plan hashes. It then acquires the
shared lifecycle and package locks, locks the enabled installation, recreates
the exact plan and fresh grant, reserves the `(package, action, target)` ledger
key, invokes only the registrar-bound action, reloads the exact target state,
and commits the package mutation, immutable execution evidence, and one
value-free `addon.action.completed` audit fact together. A changed result must
produce the exact declared state; an unchanged result rolls its reservation
back and consumes no target slot. Replays, stale plans, grant revocation,
runtime/lifecycle drift, output, exceptions, transaction loss, malformed
results, and failed postconditions roll back or refuse before package action
execution.

The runner is deliberately not an endpoint and consumes no request or session
state. `admin/bin/run_addon_tool_action.php` is the separate core-owned,
POST-only boundary: it validates the current administrator session and CSRF
token before parsing exactly `tool`, `action`, and canonical positive
`targetRecordId` inputs (plus the consumed CSRF field). It derives the
state-aware plan itself, calls the runner with that in-memory evidence, and
returns only bounded `executed` or `unchanged` outcomes, or a generic refusal.
It never returns package, actor, target, plan, or state values; a replay is a
bounded conflict. The manifest's POST/CSRF policy remains evidence, not a token
check. Display-only tool dispatch and the new endpoint expose no package HTML,
links, forms, buttons, scripts, styles, action control, public route, or
enablement eligibility. Existing enablement profiles still reject packages
that provide administrator tools, so this generic core boundary does not
activate Store Lite or any richer package.

## Data, Migration, And Client Isolation

- Every add-on installation and migration ledger is scoped to one client
  database.
- Add-on tables use stable `RED_Addon_*` names that remain within MySQL's
  identifier limit.
- Applied migration files are immutable and checksum-verified.
- Installation and upgrades run first against a disposable restored database.
- The configured primary database is refused by automated acceptance tooling.
- Add-on media uses a package-owned client media root, not a shared starter
  fixture directory.
- Non-secret settings may be stored per installation. Secrets remain in
  environment variables or server-local ignored configuration.
- The implemented value prerequisite accepts only opaque `config:` identifiers
  for secret references and never reads or returns the referenced secret.
- Server-local availability declarations contain only those opaque identifiers;
  the evidence result contains only counts, missing setting keys, and hashes.
  Secret values remain outside RED-CMS and are not read through this boundary.
- Client exports never replace `db-structure.sql`.
- The starter may contain empty generic add-on registry tables after their
  migration is approved, but it contains no enabled client package, client
  setting, or business record.

## Dependencies

- Dependencies use exact package ids and compatible version ranges.
- Circular dependencies fail validation.
- Installing a package does not silently install or enable another package.
- Required dependencies must be installed, compatible, current, and enabled
  before the dependent package can be installed, and must still pass those
  checks before later enablement.
- Optional adapters remain disabled until explicitly configured.
- A package cannot be disabled or removed while another enabled package
  requires it.

Shared capabilities should be extracted only after a second real package proves
the abstraction. Store Lite must not become a speculative universal business
engine, and later verticals must not duplicate security-critical payment or
identity behavior.

## Permissions And Accountability

Package installation, migration, enablement, disablement, and data purge are
owner-level actions. The Version 5.1 role model must provide composable package
permissions rather than extend the legacy comma-separated component list.

The first authorization slice stores one additive `owner` role in
`RED_Admin_Roles` and individual grants in `RED_Admin_Capabilities`. It does not
reinterpret `AdminType`. The fixed grant vocabulary is `addons.install`,
`addons.enable`, `addons.disable`, `addons.upgrade`, `addons.uninstall`, and
`addons.purge`. Both the Owner role and the exact grant are required. Unknown
database values are ignored, revoked grants disappear on the next protected
session refresh, and Administrator Users cannot demote or delete the protected
Owner.

Each package declares its permissions, but core owns authorization enforcement.
An add-on cannot grant permissions to itself. The per-client capability column
matches the manifest's 160-character permission limit. A package editor
operation requires its exact fresh grant; Owner and lifecycle grants confer no
daily package access. The first decision helper is read-only, and no
administrator grant-management UI or package-driven grant path exists yet.

Important lifecycle, settings, order, payment, appointment, entitlement, and
donation changes require an actor, timestamp, target identifier, result, and
bounded audit event. Logs must not store secrets, complete request bodies,
payment credentials, passwords, or protected content.

## Lifecycle

The lifecycle is:

`Discovered → Validated → Installed/Disabled ⇄ Enabled`

Upgrades and uninstall remain later explicit transitions.

- **Validate:** read-only checks; no package execution or database writes.
- **Install:** apply reviewed migrations and record the installed version;
  remain disabled.
- **Enable:** run dependency, permission, theme, route, settings, and live-data
  preflight, then persist the enabled state atomically.
- **Disable:** stop new execution without deleting package data. Refuse when
  required by another enabled package or when active public assignments would
  become unsafe without an approved fallback. The first implementation
  enforces enabled-dependent refusal; persisted add-on assignments remain
  outside the supported minimal profile because declarative editor metadata
  does not yet provide delete, public placement, or an
  operational endpoint, even though inactive creation and parent-metadata
  prerequisites are implemented.
- **Upgrade:** back up, validate compatibility, test migrations against a
  disposable copy, apply immutable migrations, and verify postconditions.
- **Uninstall:** disable first. Retain data by default.
- **Purge data:** a separate owner-confirmed destructive action with an exact
  table/media inventory and verified backup.

Removing files is an operator deployment action in the first implementation;
the administrator does not delete executable package code.

## Failure Behavior

- Invalid or incompatible packages remain unexecuted.
- Missing code for an enabled package fails closed and produces an
  owner-visible diagnostic.
- Payment, entitlement, appointment, and donation state never changes because
  a public template failed to render.
- Public fallbacks must not expose protected data, executable errors, absolute
  paths, SQL, secrets, or administrator controls.
- Disabling an add-on must not silently delete or rewrite its content.

## Optional Future Content Package Examples

### 1. Store Lite

Store Lite is the first complete proof of the extension contract.
Its product, security, persistence, service, payment-adapter, lifecycle, and
acceptance boundary is defined in
[`STORE-LITE-DIRECTION.md`](STORE-LITE-DIRECTION.md). That direction is
the staged implementation boundary, but Store Lite remains a separately
distributed optional package and is not present in the clean starter.

Initial scope:

- Product component
- Simple product catalog
- One cart per visitor/session
- Orders and order line items
- One currency per installation
- Hosted checkout through a separately configured payment adapter
- Server-verified, replay-safe payment events
- Order status and bounded audit history
- Guest checkout, with optional Member Access integration later

Initial exclusions:

- Stored card data
- Marketplace/multi-vendor behavior
- Automatic tax calculation
- Complex shipping fulfillment
- Product variants and subscriptions
- Restaurant modifiers, pickup windows, and table service

### 2. Events Calendar

Initial scope:

- Event component
- Start/end date, time zone, location, description, and status
- Accessible list and calendar views
- Filtering by date and category
- Calendar-feed/export capability
- Optional external-calendar adapter later

Ticketing, appointments, and paid registration remain separate packages.

### 3. Appointments

Initial scope:

- Appointment Service component
- Staff/resource availability
- Time-zone-aware bookable slots
- Conflict-safe reservation transaction
- Confirmation, cancellation, and bounded reminder events
- Optional payment and calendar adapters

Appointments may reuse a proven date/time presentation capability from Events
Calendar, but it must not depend on event content semantics.

### 4. Donations

Initial scope:

- Donation Campaign component
- Fixed and custom contribution amounts
- Hosted payment through an adapter
- Server-verified donation transactions
- Donor confirmation and administrator reporting
- Refund/reversal state and audit history

Donations do not require a product catalog or cart and must not be represented
as Store Lite orders merely to reuse payment code.

### 5. Restaurant Ordering

Initial scope:

- Menu Item component
- Menus, categories, item availability, and modifier groups
- Restaurant service hours and order-acceptance windows
- Pickup ordering with requested fulfillment time
- Customer cart, order confirmation, and status
- Administrator order queue and bounded status history
- Pay-on-pickup support and an optional hosted-payment adapter

Restaurant Ordering may reuse proven catalog, cart, order, and payment
capabilities through explicit service contracts. It remains a separate package
because menu modifiers, preparation availability, fulfillment timing, and
restaurant operations do not belong in Store Lite.

Initial exclusions:

- Multi-restaurant marketplace behavior
- Third-party courier dispatch
- Point-of-sale or kitchen-display synchronization
- Table reservations and appointment scheduling
- Complex delivery-zone and driver management

## Member Access / Protected Content Package

This package is the required boundary for planned private folders and
member-only Sections.

It owns:

- Separate public member identities
- Registration, verification, login/logout, reset, and revocable sessions
- Section entitlements
- Access checks before route content queries and rendering
- Protected-content gate and optional teaser experience
- Secure download authorization
- Manual, free, purchased, renewable, and revocable grants
- Search, cache, sitemap, preview, and structured-data leakage prevention

`RED_Sections.AccessLevel` alone never activates privacy. The administrator must
not offer an operational private state until Member Access is installed,
enabled, configured, and proven by its security and leakage acceptance suite.

## Acceptance Gate

Every package must prove, in a disposable isolated installation:

- Manifest, path, compatibility, integrity, and dependency validation
- Zero execution during discovery and validation
- Installation leaves the package disabled
- Request bootstrap ignores uninstalled/disabled packages and fails before
  rendering on unsafe enabled evidence
- Migration apply, checksum, upgrade, rollback/failure, and cleanup behavior
- Owner-only lifecycle actions and scoped package permissions
- CSRF, request validation, prepared operations, and transactional writes
- Exact parent/child component relationships
- Public rendering through the active theme and hard recovery path
- Desktop and mobile administrator behavior
- Accessible labels, keyboard behavior, errors, and public output
- Namespaced public/admin CSS with no cross-boundary leakage
- Disablement with retained data
- Uninstall and separately confirmed purge behavior
- Client database, media, configuration, and secret isolation
- No change to the configured primary database during automated acceptance
- No client data in the clean starter distribution

Payments additionally require signature verification, idempotency, amount and
currency verification, duplicate/replay tests, refund/reversal tests, and proof
that browser redirects cannot mark an order, entitlement, or donation paid.

Protected content additionally requires proof that unauthorized body content
never enters public HTML, caches, feeds, sitemaps, previews, logs, error
responses, or structured data.

## Initial Delivery Sequence

1. Complete the Version 5.1 per-page SEO metadata compatibility work and the
   isolated Adriana launch acceptance gate. The compatibility work is
   implemented; launch QA remains an isolated client decision.
2. Approve this architecture contract as a separate future implementation
   track. The contract is now documented.
3. Add the manifest schema, filesystem discovery, read-only validation, and
   dependency preflight. This non-executing trust foundation is implemented.
4. Implement the persisted Owner role and package-lifecycle permissions
   required by that approved track. This authorization foundation is
   implemented without adding a package lifecycle endpoint.
5. Add per-client installed/enabled state and immutable migration tracking.
   Empty storage, read-only fail-closed reconciliation, bounded lifecycle audit,
   and guarded installation/migration execution into `installed_disabled` are
   implemented. A separate Owner-authorized read-only enablement preflight now
   proves exact installed-disabled state, dependency evidence, enabled-package
   identity, and capability/route collision reporting without executing code or
   mutating state. Fixed runtime registration and fail-closed front-controller
   page-request bootstrap and safe enabled-component public dispatch are
   implemented without bundling a package or business data. The read-only plan
   now clears declarative gates only for registration-only service,
   core-rendered default public component, and combined default-component plus
   registration-only-service profiles. The registrar-validating atomic
   `enabled` transition for those constrained profiles is implemented. The
   component profiles add no operational editor form, persistence, package
   assets, business data, or client package. Services are callable only through
   the separate typed boundary with exact runtime ownership and final bounded
   request/result objects.
   Non-executing, data-retaining atomic disablement is also implemented with
   enabled-dependent refusal and later-request unload proof. Every richer
   package surface and every later lifecycle transition remain separate
   reviewed batches.
6. Define the Store Lite package, data, service, payment, lifecycle, and
   acceptance boundary without adding commerce behavior to core. This
   direction is documented in `docs/STORE-LITE-DIRECTION.md`.
7. Implement generic combined default-component plus registration-only-service
   activation. This constrained profile is implemented with disposable
   preflight, enablement, runtime-render, disablement, and cleanup evidence.
8. Implement the remaining generic persistence, editor, route,
   administrator-tool, settings, asset, and live-data contracts as separate
   disposable-fixture batches. The component parent relationship, full
   component-id storage, package-table foreign-key allowance, and read-only
   public binding resolver, declarative field schema, and fail-closed submitted
   value normalization are implemented. Core-owned display-only editor
   rendering, exact permission decisions, bounded enabled-package data loading,
   transactional existing-record package updates, and immutable package-value
   revision snapshots, validated history/preflight, and atomic restore
   execution are also implemented. Data-only setting definitions now have
   type-correct defaults plus closed value and secret-reference normalization;
   empty per-client storage, exact permissioned write preflight, atomic
   internal persistence, and a core-only per-setting authorized read model plus
   non-executing server-local availability evidence are implemented.
   Deterministic namespaced CSS/JavaScript asset planning,
   read-only immutable delivery preflight, and the core-owned static immutable
   delivery endpoint and core-owned public/admin document injection are also
   implemented. Data-only administrator action contracts and their exact
   runtime-owner/permission/numeric-target preflight are also implemented,
   without action execution, UI, or endpoint; editing UI, actual secret lookup,
   and activation readiness remain separate. Read-only inactive component-creation
   preflight and its atomic runner plus permission-enforced inactive
   parent-metadata writes and the display-only value-free revision timeline are
   implemented. Read-only delete planning and its atomic inactive runner are
   also implemented; restore UI actions, an operational editor endpoint, audit
   workflow, and public placement/activation remain blocked.
9. Implement and distribute Store Lite separately as the first complete
   optional component plus service package.
10. If private folders are scheduled for activation, implement and pass Member
   Access before exposing an operational private setting.
11. Implement Events Calendar as the second independent proof that a new
   component no longer requires core dispatcher edits.
12. Implement Appointments.
13. Implement Donations.
14. Implement Restaurant Ordering.

Each implementation remains a separate reviewed batch with its own rollback
path, migration evidence, acceptance fixtures, and client-specific activation
decision.

## Non-Goals

The first add-on platform will not provide:

- Arbitrary uploaded PHP
- A global unbounded hook/event system
- Direct database-selected classes, files, methods, or templates
- Automatic enablement
- Business-vertical packages bundled into the core or activated by the starter
- Automatic changes to a retained primary database
- One monolithic schema for stores, events, appointments, donations, and
  restaurant ordering
- Theme-owned authorization, payments, identity, or persistence
- Client business data or enabled add-ons in the clean starter
