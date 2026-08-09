# RED-CMS 5.0

RED-CMS is a lightweight PHP and MySQL content management system for structured, template-driven websites. Version 5.0 modernizes the legacy application while preserving its public URLs, existing database table names, and compatibility-first deployment model.

The current release adds a consistent administrator workspace, standard theme packages, visual page structures, reusable layouts, content version history, safer database migrations, and repeatable acceptance testing.

## Release Status

Current Version 5.1 and Store Lite milestone map:
[`docs/ADD-ON-PLATFORM-STATUS.md`](docs/ADD-ON-PLATFORM-STATUS.md).

RED-CMS 5.0 Bonsai and Milestone 5 are complete on `main`. The release
checkpoint was merged through [pull request #2](https://github.com/orojas01-glitch/redcms/pull/2)
on July 25, 2026.

Version 5.1 development includes per-page SEO compatibility with nullable
overrides, generated fallbacks, and constrained typed JSON-LD; non-executing
add-on trust validation; persisted Owner authorization; and per-client package
registry/migration-ledger storage with read-only drift reporting. A
server-local Owner-authorized installer can apply reviewed, checksum-verified
package migrations and always records the package as `installed_disabled`; it
never executes package PHP. A separate read-only Owner-authorized preflight can
inspect that disabled package's dependency, capability, and route readiness
without changing state or loading code. Front-controller page requests now bootstrap
only already-recorded `enabled` packages whose complete registry, dependency,
namespace, and integrity evidence remains current; the clean starter has none.
The existing `RED_Articles` placement parent now stores the full manifest
component id, reviewed package migrations may add only an exact foreign key to
its numeric `RecordID`, and public add-on dispatch verifies that persisted
parent against the enabled request-local owner. Package fields and business
records remain in separately installed package tables.
Manifest Version 1 can now validate optional data-only component editor
schemas with fixed field types, bounds, and declared permissions without
executing package code. Core can also normalize and validate submitted scalar
values against that schema, then render a display-only set of escaped,
accessible administrator controls from either an empty state or the validator's
exact result. The renderer opens no form, supplies no Save action, loads no
package or package data, and writes no database. Packages that declare an
editor now also have a fresh database-backed permission-decision prerequisite:
each fixed operation resolves to its exact manifest permission, and only that
per-client administrator grant passes. Owner and lifecycle grants do not imply
package access. The decision grants nothing and writes nothing. Packages remain
blocked from enablement. An enabled disposable fixture can now register one
exact package data loader per declared editor; core requires the view grant,
the persisted parent/runtime owner match, contained execution, and schema-valid
returned values before exposing a state hash. Core now exposes a CSRF-protected
existing-record editor for an already-enabled, already-persisted component;
the endpoint derives package and component ownership from current server-side
state and requires fresh exact view/edit grants. Core now also exposes one
protected package-component create form/endpoint only after current runtime,
manifest schema, loader/creator/table ownership, and fresh create permission
agree; it allocates the core parent identifier on the server. No component
delete endpoint exists. A separate activation-blocked
helper can now apply an existing package record update only after the exact
view and edit grants, current state hash, locked placement parent, enabled
runtime ownership, declared InnoDB tables, contained writer execution, and
reloaded postcondition all pass. The same writer now backs that operational
form, but does not create parent records, restore, delete, publicly place
content, or activate a package. Successful
changes now atomically retain core-owned baseline and saved snapshots in the
current client database. A separate read-only helper can list a bounded,
integrity-validated timeline and produce a deterministic restore plan only
after current view/restore grants, ownership, state, and target evidence pass;
an activation-blocked atomic restore helper now revalidates that exact plan
under the existing record lock, invokes only the registered writer, verifies
the reloaded target state, and commits one source-linked restore snapshot. Core
can now render the authorized, value-free timeline as a strictly display-only
administrator panel. It requires the newest revision to match the supplied
current state, escapes bounded metadata, and labels older states as requiring
a fresh restore check; malformed, stale, reordered, or value-bearing history
fails closed. No restore action, endpoint, audit workflow, or activation path
is exposed. A new
read-only creation preflight requires the exact create grant, enabled runtime
component/loader/creator ownership, an unused numeric parent id, an
active-theme layout, schema-valid package values, and declared InnoDB package
tables. It returns only a deterministic plan for an inactive, hidden, unrouted
parent shell and never invokes the creator or loader. A separate
activation-blocked atomic runner revalidates that exact plan under the add-on
lifecycle and theme locks, inserts the parent, invokes only the registered
creator, reloads through the registered loader, requires the exact normalized
postcondition, and commits initial core `create` plus package `baseline`
revisions together. Callback failures, partial writes, stale evidence, and
either ledger failure roll back. A separate read-only parent-state helper now
requires the exact view grant, enabled runtime binding, inactive shell,
package loader result, and current core revision. Its activation-blocked
atomic writer requires the exact edit grant and state hash, then changes only
the core-owned title, active-theme layout, and language under lifecycle/theme
serialization. It preserves hidden, inactive, unrouted placement, records one
core `save` revision, adds no revision for unchanged values, and rolls back a
revision or postcondition failure. An activation-blocked atomic delete runner
now revalidates the value-free plan under shared lifecycle/theme and exact
binding locks, records core and package `delete` snapshots, invokes only the
registered deleter, and removes the package row, SEO row, and inactive parent
together. Partial deletion, callback failure, stale evidence, or either ledger
failure rolls back; both immutable ledgers remain after success. Delete
controls/endpoints, restore action, uninstall/purge, and richer package
activation eligibility remain absent. A separate protected placement control
can publish the created component to either the unique language homepage or an
existing active Article target through the exact atomic placement runner.
Manifest-declared package settings now also have a non-executing typed value
contract: core validates one closed configuration object, applies only
type-correct non-secret defaults, and separates opaque `config:` secret
references without resolving or exposing secret material. Each installation
now includes an empty generic settings table plus a read-only write preflight
that binds exact installed package identity, current-state evidence, and fresh
case-sensitive package grants. The internal atomic writer recreates that plan
under lifecycle/package and row locks, replaces the complete configuration,
verifies the exact postcondition, and commits one value-free audit fact; every
late failure rolls back. No administrator form or endpoint, secret resolution,
package execution, or activation path is added.
A separate core-only current-setting read model now rechecks that same trusted
identity and lifecycle state, then filters each declared setting by its fresh
exact grant. It returns normalized stored/default/unset non-secret values only
to authorized administrators and a secret setting's configured state only; it
never returns an opaque secret reference. The model adds no administrator
screen or endpoint, persistence, package execution, secret lookup, or
activation eligibility.
Server-local configuration can now declare only the opaque `config:`
references that an operator has provisioned. Core validates that bounded
inventory and produces deterministic per-package availability evidence using
counts, missing setting keys, and SHA-256 fingerprints; the result contains no
reference identifier or secret value. This declaration is evidence only: it
does not resolve a secret, execute a package, or make settings-bearing packages
eligible for activation.
Trusted package manifests can now be reduced to a deterministic, namespaced
CSS/JavaScript asset plan. The plan accepts only `assets/*.css` for `head` and
`assets/*.js` for `body-end`, binds each URL to the package namespace and
declared SHA-256, and renders only core-owned escaped tags after its plan hash
revalidates. It does not read or serve a file, inject markup into a response,
execute package PHP, access a database, or change lifecycle. Immutable asset
delivery has a separate read-only preflight: it accepts only an exact
checksum-versioned reserved URL, revalidates the complete manifest inventory,
current enabled registry evidence, safe package path, and final file checksum,
then returns internal evidence only. It does not serve a byte, inject markup,
execute package PHP, or write state. The core-owned static endpoint is
separate from that preflight and from document injection. It reruns the
evidence before theme, session, or add-on runtime bootstrap and serves
only exact checksum-matching CSS/JavaScript bytes up to 4 MiB through
`GET`/`HEAD`, with fixed immutable-cache and `nosniff` headers. Invalid,
disabled, drifted, missing, noncanonical, and oversized assets return only a
generic fail-closed response; no response executes package PHP or injects a
document asset. Core-owned document injection is now implemented separately:
its planner re-discovers trusted manifests and current registry evidence without
loading `addon.php`, validates both surfaces for every enabled package, and adds only
public CSS/JavaScript tags to ordinary documents. When the existing signed-in
administrator overlay is present, it additionally adds that package's
administrator CSS/JavaScript tags. Tags are core-owned and escaped, appear only
at unambiguous document boundaries, and are omitted entirely on catalog,
registry, integrity, plan, or document-boundary failure.
Fresh isolated Adriana JSON-LD
verification and hosted Schema.org validation pass; production deployment
remains separate. Typed internal service invocation, exact static public
`GET` JSON routes, and display-only administrator tools have narrow core
boundaries. A separate non-executing administrator action preflight binds one
declared action's registered runtime owner, exact package grant, `POST`/CSRF
policy, fixed `once-per-target` idempotency, and numeric target into
deterministic evidence without invoking the action or writing state. A separate
internal atomic runner can now revalidate an exact state-aware plan under the
lifecycle and package locks, execute one contained registrar action, reload its
postcondition, and commit only package state plus a core-owned execution
ledger and value-free audit fact. A core-owned, unlinked administrator endpoint
now validates the current session and CSRF token itself, accepts only an exact
tool/action/positive-target request, derives the plan server-side, and returns
only bounded executed, unchanged, or refusal outcomes. It exposes no UI, form,
package values, package markup, or public route. A separate unlinked
administrator-form JSON endpoint now authenticates and verifies header CSRF
before body I/O, accepts only canonical bounded JSON, repeats the exact form
grant and current-value load, refuses stale state, validates the complete
nested value graph, and returns only a generic validation outcome. It invokes
no package writer and remains disconnected from the disabled preview. A
separate internal boundary now permits one optional exact registrar-bound form
writer with one to eight package-owned InnoDB tables. Its read-only write plan
binds the validation evidence, package version, table set, actor, and target.
The atomic runner recreates that plan under lifecycle/package locks, reloads
current state, refuses stale or substituted evidence, contains the trusted
writer, verifies the exact postcondition, and commits one value-free audit fact
with the package mutation. The validation endpoint still does not call it.
A separate core-owned edit endpoint now reloads one exact authorized
tool/form/positive target into escaped typed controls, while a distinct
authenticated header-CSRF JSON Save endpoint delegates only to that atomic
runner and returns a bounded value-free outcome. Core-owned target discovery
and Edit navigation are available only when a separately distributed enabled
package registers the one reviewed form-target loader; the clean starter
registers no Store Lite provider and contains no commerce data.
Operational writable route/tool actions, upgrade,
uninstall/purge, payment, member access, editorial workflow, notifications,
the broader role model, and social publishing integrations are not active
features.

## Highlights

- Polished, responsive administrator workspace
- Article, Banner, Form, FTP, Gallery, Other, and Video authoring
- Parent-backed Sections, Categories, and Subcategories
- Three-level top navigation
- Direct drag-and-drop component positioning
- Reusable Layout Builder with desktop and mobile maps
- Non-destructive content version history and restoration
- Local, provider-independent TinyMCE compatibility editor
- Standard theme contract with validation, preview, activation, and rollback
- Prepared database operations, CSRF enforcement, scoped permissions, and transactional writes
- Migration ledger, guarded backup/restore tools, and disposable acceptance testing
- Per-page canonical, Open Graph, X/Twitter, and constrained typed JSON-LD metadata
- Read-only add-on manifest, path, compatibility, dependency, and integrity validation
- Per-client Owner role and exact future add-on lifecycle capability grants
- Empty per-client add-on installation/migration registries with fail-closed reconciliation
- Owner-authorized server-local package installation that remains disabled and unloaded
- Deterministic read-only enablement preflight with dependency, namespace, and constrained activation-gate reporting
- Owner-authorized atomic enablement for constrained registration-only service,
  core-rendered default public component, and combined default-component plus
  registration-only-service profiles
- Core-owned public component renderer with unchanged title/summary output and
  optional bounded escaped label/value facts; package HTML remains forbidden
- Owner-authorized atomic disablement with enabled-dependent refusal and no
  package execution or data deletion
- Fail-closed request bootstrap and lookup context for already-enabled first-party packages
- Generic package-owned component parent relationship with read-only,
  fail-closed persisted-placement resolution
- Non-executing bounded component editor-schema validation and normalized lookup
- Fail-closed component editor value normalization with no package execution or writes
- Core-owned display-only component editor controls with escaped fixed markup
- Fresh exact component-editor package-permission decisions with no implicit Owner access
- Bounded enabled-package component data loading with validated values and a core-owned state hash
- Transactional existing-record package updates with stale-state refusal and rollback proof
- CSRF-protected existing-record component editor with server-derived package
  ownership and exact view/edit permission checks
- Immutable per-client package-value revision snapshots committed with updates
- Read-only validated revision history and deterministic restore preflight
- Atomic source-linked component revision restoration with stale-plan rollback
- Core-owned display-only component revision timeline with no value disclosure
  or restore action
- Read-only component deletion planning with exact grants, inactive shell,
  state/revision evidence, and a non-invoked registrar-bound deleter
- Read-only inactive component-creation planning with exact owner, grant,
  schema, identifier, theme-layout, and transaction-table gates
- Atomic inactive component creation with creator/loader containment, exact
  postcondition verification, dual initial revisions, and rollback proof
- Permission-enforced inactive parent metadata updates with stale-state
  refusal, exact shell preservation, core revisions, and rollback proof
- Read-only public-placement planning with exact view/publish grants, current
  parent/package evidence, unique destination ownership, active-theme position
  validation, deterministic hashing, and zero activation or writes
- Atomic exact-plan public placement with lifecycle/theme/source/target locks,
  seven-field parent mutation, unchanged package and destination postconditions,
  one core move revision, one bounded administrator audit fact, single-use
  refusal, and rollback proof
- Core-owned public-placement form and POST/CSRF endpoint with server-derived
  package/component ownership and numeric destination choices
- Internal typed add-on service invocation with exact enabled runtime
  ownership, immutable request/result objects, bounded JSON-compatible values,
  and containment of output, exceptions, buffer changes, and malformed results
- Core-owned public add-on route dispatch for exact static `GET` paths with
  public authentication, typed bounded query/result objects, JSON-only
  responses, and fail-closed package behavior
- Read-only public-mutation declaration and live-data preflights that bind one
  trusted installed-disabled package to value-free per-client migration,
  InnoDB-table, typed-setting, opaque-secret-availability, and core
  subject/CSRF/rate-limit/idempotency/execution storage evidence without
  dispatch, secret resolution, package execution, or state change
- Internal core-only anonymous-subject and CSRF storage with SHA-256-only
  persistence, a future host-only secure cookie descriptor, and declaration- and
  database-scoped expiry; no browser endpoint, header, session, package access,
  or Store Lite behavior
- Pure core-only public-mutation subject-cookie serializer. It accepts only the
  exact core-issued descriptor shape and constructs one fixed future host-only
  `Set-Cookie` value with `Max-Age=1800`, `Path=/`, `Secure`, `HttpOnly`, and
  `SameSite=Strict`, without `Domain` or `Expires`. It emits no header/cookie
  and reads no request/cookie/session, database, runtime, or package state; it
  creates no endpoint, browser flow, enablement change, Store Lite behavior, or
  client data
- Internal core-only fixed-window rate-limit storage and decision helper: 12
  requests per 60 seconds for one client database, declared package route, and
  opaque anonymous subject; no public dispatcher, package access, request-global
  reads, browser response, Store Lite state, or enablement change
- Internal core-only opaque idempotency-key storage and issue/resolve helper:
  one 10-minute SHA-256-only key for one client database, declared package
  route, and opaque anonymous subject; its issuer/resolver has no public
  dispatcher, browser access, Store Lite state, or enablement change
- Internal core-only atomic public-mutation runner with keyed replay evidence:
  it accepts only a trusted in-memory registrar binding plus typed command and
  opaque evidence from a future core dispatcher, then atomically verifies CSRF,
  idempotency, rate and server-derived state before committing declared package
  state, a bounded replay outcome, and a value-free audit fact. There is no
  endpoint, emitted response, browser cookie/header, public package execution, or
  Store Lite package enabled by this foundation
- Pure core-owned public-mutation response contract with exact JSON envelopes
  for `accepted` / `unchanged` and five bounded refusals. It computes only
  fixed no-store, nosniff, content-type, length, and POST-allow headers; it
  does not parse a request, emit a header/cookie/body, load a package, or add a
  public endpoint or Store Lite behavior
- Pure core-owned declared-form decoder for a future public mutation path. It
  accepts only one validated in-memory manifest declaration and canonical URL-encoded
  package-field bytes, returning sorted typed scalar fields or no values. It
  rejects duplicate, nested, unknown, malformed, noncanonical, or oversized
  input without reading HTTP globals, cookies, sessions, a database, runtime,
  or package code, and without creating an endpoint or Store Lite behavior
- Pure core-owned public-mutation HTTP request envelope for a future dispatcher.
  It validates explicit canonical HTTPS origin, exact static POST path, form
  content metadata, one opaque subject cookie, and fixed CSRF/idempotency
  headers before returning raw body bytes to the separate decoder. It reads no
  PHP request globals, database, runtime, or package code, and creates no
  endpoint, response, session, Store Lite behavior, or client state
- Core-only static public-mutation route selector that maps one exact
  un-decoded path to one current registrar-bound route, mutation handler, and
  state loader. It invokes no callback and reads no request global, database,
  or package file; it creates no front-controller claim, endpoint, response,
  browser behavior, Store Lite state, or enablement change
- Core-only non-routable public-mutation server request-facts adapter. It
  reads only the current method and raw target, resolves a canonical HTTPS
  origin from operating-system/local configuration rather than `Host` or a
  request-projected server value, and accepts header lines only through an
  upstream-attested complete fixed security-header capture. It rejects
  associative header maps and
  does not read a body stream, claim a route, invoke a handler, access a
  database, emit a response/cookie, or add a public endpoint, Store Lite
  behavior, or enablement change
- Optional operator-built Caddy/FrankenPHP public-mutation ingress attestation
  source and paired unlinked PHP HMAC verifier. The handler strips spoofed
  internal headers on every request and can sign only a bounded `/addons/`
  `POST` candidate's method, raw target, body length/hash, and fixed
  security-header subset. A separately runnable isolated Docker proof now
  builds the matching custom binary, confirms the registered module, and
  verifies Caddy-to-PHP body/capture behavior without a client installation.
  It is not a deployed client binary, root Caddyfile, default development-server
  change, dispatcher, endpoint, cookie flow, enablement change, or Store
  Lite/client-data path. Its per-installation HMAC key and deployment
  configuration remain external to the clean starter
- Unlinked core-owned public-mutation dispatcher composition. It accepts only
  explicit attested method/target/capture facts, selects one registrar-bound
  route, verifies the opaque subject and CSRF before decoding declared scalar
  fields, invokes the atomic runner, and returns only the fixed response model.
  It is not linked to `index.php`, emits no response or browser cookie, and
  adds no package, enablement, Store Lite, or client-data behavior
- Disposable Docker supported-server dispatcher rehearsal. It builds the
  pinned custom FrankenPHP/Caddy image, applies the current migrations to a
  fresh temporary MySQL database, and proves the real attested request path
  through the core dispatcher, atomic runner, and fixed emitter, including
  accepted/replay/refusal/conflict and exact ledger/audit/rate evidence. The
  fixture endpoint, `mysqli` extension, package marker, database, image,
  network, and build context exist only for the proof and are removed after it;
  no client installation, default server, browser cookie, enablement, or Store
  Lite data is changed
- Core-owned browser subject-cookie lifecycle bridge. Transactional `ensure`,
  `clear`, and `rotate` operations return only fixed host-only cookie
  descriptors, refuse malformed sources and active caller transactions, and
  invalidate the old subject and CSRF evidence on rotation. Disposable and
  supported-server proofs cover issuance, resolve-without-reissue, fixed
  clearance, replacement, and cleanup; the bridge is not linked to `index.php`
  and does not authorize a client deployment, package enablement, or Store Lite
  route
- Non-executing per-client public-mutation deployment profile validator. It
  accepts only an operator-owned review packet with one canonical HTTPS origin,
  pinned FrankenPHP/Caddy versions, the fixed process-environment HMAC key
  name, attestation-before-PHP route order, core response/cookie ownership,
  host-only cookie policy, and explicit client-isolation flags. It returns a
  deterministic non-secret profile hash and refuses starter-database reuse,
  request-derived trust, package/theme response ownership, policy drift, and
  all dispatcher/package/Store Lite activation flags; it reads no database,
  secret, filesystem, request, or client state
- Core-only non-routable public-mutation response emitter. It accepts only the
  existing exact fixed core envelopes, refuses to run after output starts,
  clears and sets only their no-store/nosniff JSON headers, and emits only the
  corresponding fixed bytes. It reads no request/cookie/session state,
  database, runtime, or package code and remains unlinked from `index.php`, so
  it creates no public endpoint, browser cookie, Store Lite behavior, or
  enablement change
- Core-owned non-emitting public-mutation response owner. It composes only an
  already-valid fixed core envelope with the lifecycle bridge's exact subject-
  cookie descriptors, rejects arbitrary headers, policy drift, and body token
  leakage, and returns a deterministic pre-link result. It reads no request,
  database, secret, package, or client state and remains outside `index.php`.
- Non-executing per-client deployment review validator. It binds a reviewed
  profile hash to non-secret Caddy/FrankenPHP/TLS/proxy artifact evidence,
  process-environment trusted-origin/HMAC provisioning and rotation facts, and
  bounded desktop/mobile browser evidence. It reads no deployment file or
  secret, changes no client state, and cannot link the dispatcher.
- Permission-scoped display-only administrator tools with data-only manifest
  contracts, fresh exact per-client grants, typed text view models, core-owned
  escaped rendering, and a protected POST/CSRF endpoint
- Non-executing administrator tool-action preflight with separate declared
  action contracts, exact runtime-owner and permission binding, fixed
  `POST`/CSRF evidence, numeric target validation, deterministic hashes, and
  no package action execution or state mutation
- Internal atomic administrator action runner with package-owned InnoDB table
  declarations, rollback-only target-state preflight, stale-plan/replay refusal,
  one-time per-client execution evidence, contained callbacks, exact
  postcondition verification, and a value-free audit fact
- Core-owned, unlinked administrator action endpoint with independent
  POST/session/CSRF validation, exact request fields, server-derived plans, and
  value-free bounded outcomes—without an administrator control or public route
- Core-owned operational administrator-form editor and Save bridge. One exact
  tool/form/positive-target request reloads current values after fresh package
  authorization and exact writer ownership, renders only escaped scalar and
  bounded nested-collection controls, and submits canonical JSON through an
  authenticated header-CSRF endpoint to the atomic form runner. Public Save
  outcomes are value-free. The separately distributed Store Lite package now
  supplies one bounded existing-product target loader in isolated acceptance;
  provider code, migrations, tables, and product data remain outside the
  starter
- Generic administrator-form runtime-setting resolution for declared,
  configured, non-secret per-client package scalars under an exact enabled
  request-local binding. Core injects an immutable typed value view into only
  that form's loader and writer and binds its opaque state hash into stale-form
  evidence. It exposes no route, caller-selected package lookup, setting write,
  or Store Lite behavior; see [Runtime Setting Resolver Direction](docs/RUNTIME-SETTING-RESOLVER-DIRECTION.md)
- Non-executing typed package-setting normalization with fail-closed defaults,
  exact missing/unknown reporting, and separate opaque secret references
- Empty per-client package-setting storage and deterministic read-only write
  preflight with explicit manifest permissions and fresh database grants
- Atomic complete-setting persistence with shared locks, exact postcondition,
  value-free audit, no-op handling, and rollback
- Core-only current-setting read models with per-setting fresh grants,
  authorized typed non-secret values, and masked secret configured state—no UI,
  endpoint, secret-reference disclosure, package execution, or mutation
- Non-executing server-local secret-reference availability evidence with no
  secret lookup, reference disclosure, database access, or activation change
- Deterministic namespaced CSS/JavaScript asset plans with hashed URLs and
  escaped tags, without filesystem serving or response injection
- Read-only immutable asset-delivery preflight with full integrity and
  enabled-registry revalidation, without execution or mutation
- Core-owned static immutable CSS/JavaScript endpoint with exact bytes,
  `GET`/`HEAD` boundaries, immutable caching, and no session or package-runtime
  bootstrap
- Core-owned public/admin document asset injection with current manifest and
  registry revalidation, exact boundary insertion, and no additional package-PHP
  execution
- Store Lite Gate 2A package contract fixture for simple products and bounded
  Size/Color variants: one installation currency, integer minor-unit money,
  unique option tuples, three option groups, sixteen values per group, and 128
  explicit variants, without Store Lite code, tables, routes, or starter state
- Store Lite Gate 2B pure server-authoritative cart-line contract: browser
  intent contains only product, integer quantity 1–100, and optional variant;
  the separate Store Lite 0.1.12 package derives SKU, option labels, integer
  unit price/total, currency, stock sufficiency, and product-state evidence
  from current normalized server data, with no cart table, route, cookie,
  runtime registration, or partial refusal line in the clean starter
- Store Lite Gate 2C internal package cart-persistence contract: the separate
  Store Lite 0.1.13 package owns numeric-subject carts, exact server-derived
  product/variant lines, fresh-state locking, caller-owned transactions, and
  value-free activity while core retains no Store Lite package, table, route,
  cookie, business data, or public cart behavior

See the [RED-CMS 5.1 add-on platform status map](docs/ADD-ON-PLATFORM-STATUS.md)
for the current milestone, remaining Store Lite gates, and later optional
package sequence.

## Portable Starter Distribution

This repository is the clean, reusable RED-CMS distribution. It ships with the
`starter-reference` theme as the default public theme and keeps
`legacy-bootstrap` only as the hard recovery renderer.

Client themes, client media, and client databases are intentionally excluded.
Site-specific installations must be backed up and distributed separately so a
clean release can never overwrite retained production content.

## Local Development

The verified local environment uses:

- PHP 8.5.8 through FrankenPHP
- MySQL 8.4 LTS
- MySQL at `127.0.0.1:3307`
- Portable starter at `http://127.0.0.1:8055/`
- Starter administrator at `http://127.0.0.1:8055/admin/`

From the repository root:

```bash
scripts/dev-mysql-status.sh
scripts/dev-mysql-start.sh
scripts/dev-php-server.sh
```

Check service state first and start only services that are stopped. Local credentials belong in the ignored `includes/config.local.php`; never commit that file.

Detailed setup notes:

- [Clean installation](INSTALL.md)
- [Local PHP runtime](docs/LOCAL-DEV-PHP.md)
- [Local database](docs/LOCAL-DEV-DATABASE.md)
- [Database migrations](docs/DATABASE-MIGRATIONS.md)

## Verification

Verify that the tracked package contains only portable starter defaults:

```bash
php scripts/clean-starter-boundary-self-test.php
```

Run PHP syntax checks:

```bash
scripts/dev-php-lint.sh
```

Run the theme and administrator contract suite:

```bash
php scripts/theme-contract-self-test.php
```

Run the non-executing add-on trust gate and isolated runtime-contract check:

```bash
php scripts/addon-trust-self-test.php
php scripts/addon-setting-values-self-test.php
php scripts/addon-secret-availability-self-test.php
php scripts/addon-asset-plan-self-test.php
php scripts/addon-component-editor-self-test.php
php scripts/addon-component-editor-renderer-self-test.php
php scripts/addon-admin-tool-form-renderer-self-test.php
php scripts/addon-runtime-self-test.php
php scripts/addon-service-invocation-self-test.php
php scripts/addon-validate.php --all
php scripts/admin-addon-owner.php --status
php scripts/addon-registry-status.php --all
php scripts/admin-addon-install.php --package=vendor.package --actor-admin=ID
php scripts/admin-addon-enable-preflight.php --package=vendor.package --actor-admin=ID
php scripts/admin-addon-enable.php --package=vendor.package --actor-admin=ID
php scripts/admin-addon-disable.php --package=vendor.package --actor-admin=ID
```

The dependency-free administrator-form schema/preview plus database-backed
setting storage, administrator-form preflight, current-value loading, JSON
validation, atomic form writing, the operational edit-and-Save bridge,
administrator-action preflight, and
immutable asset-endpoint fixtures run automatically in `scripts/dev-acceptance.sh`
against its uniquely named disposable database and FrankenPHP CLI. The endpoint
fixture verifies real HTTP headers and bytes plus checksum, traversal,
lifecycle, and integrity refusal without a session or package-PHP execution.

The supported-server public-mutation rehearsal is separate because it requires
Docker Desktop and a custom Caddy/FrankenPHP build:

```bash
scripts/frankenphp-public-mutation-dispatch-proof.sh
```

It uses only a fresh temporary MySQL database and a fixture-only endpoint. It
does not deploy the dispatcher, change the default local server, or touch any
client installation.

The installation-shaped HTTPS deployment rehearsal is a separate, later gate:

```bash
scripts/frankenphp-public-mutation-deployment-rehearsal.sh
```

It stages only the reviewed integration into a temporary Docker build context,
uses a generated localhost certificate outside the starter, restarts the
container with a second process-environment HMAC key, and captures fixed
Chrome desktop (`1440x1000`) and mobile (`390x844`) evidence. The retained
packet contains only non-secret hashes and boolean evidence outside the
starter; the private key, process secrets, container, image, and build context
are removed. Set `RED_DEPLOYMENT_REHEARSAL_OUTPUT` to choose an external
evidence directory. This is not an Adriana/client deployment and does not link
the dispatcher or front controller.

The install command is a dry run by default. Apply requires the exact database,
package, version, plan digest, SHA-256 from a separately verified backup, and
`installed_disabled` confirmations printed by the dry run. Package files are
deployed separately per client; the clean starter intentionally contains no
`addons/` directory. The enablement preflight is always read-only: it has no
apply mode, keeps `enableReady` false because it does not validate the
registrar, does not change the package's `installed_disabled` state, and
does not execute package PHP. It can identify a registration-only service,
a default public component, or a default public component with
registration-only services as declaratively eligible for later transition
validation. All three profiles exclude migrations, settings, routes, jobs,
public or administrator assets, administrator tools, adapters, and outbound
hosts. Core's escaped default component renderer is the complete
theme-compatibility contract for either component profile. Services are
registered into the request-local lookup context but are not automatically
invoked. Public route dispatch exists as a separate core boundary, but the
current enablement profiles still reject every package that declares routes.
The first route slice accepts only an exact static manifest path, public
authentication, `GET`, `csrf: not-applicable`, bounded query values, and a
typed JSON result. Member routes, unsafe methods, placeholders, package HTML,
and administrator routes remain non-dispatched. The separate enable command is
also dry-run first. It accepts only
those three constrained profiles and requires exact database, package,
version, plan, backup SHA-256, and installed-disabled confirmations before it
validates the fixed registrar and atomically records `enabled` plus its bounded
audit fact. Packages with any richer surface remain blocked behind their
explicit theme, settings, or live-data contract. The disable command is
likewise CLI-only and dry-run first. It requires the exact Owner
`addons.disable` capability, current
enabled package evidence, plan and nonzero backup checksums, and
`enabled`-state confirmation. Enable and disable transitions share one
database-wide lifecycle lock. Disablement refuses an enabled dependent, never
includes package PHP or runs migrations, and atomically returns the package to
`installed_disabled` with one bounded audit fact. Package code, migration
evidence, settings, and data remain in place, while later request bootstrap no
longer loads the disabled package. The
runtime-contract self-test executes only a temporary
first-party fixture outside the starter. It rechecks the fixed `addon.php`
checksum, requires exact manifest registration, orders required dependencies
first, and rejects output or registration ambiguity. The database-backed
acceptance suite additionally proves that request bootstrap ignores
uninstalled and disabled packages, loads only exact current enabled packages,
performs no registry write, and fails before execution on drift, missing code,
or disabled dependencies. The clean starter contains no package directory or
enabled package state.

Run the complete guarded acceptance lifecycle:

```bash
scripts/dev-acceptance.sh
```

The acceptance runner creates a uniquely named temporary database, refuses the configured primary database, exercises migrations and representative CMS operations, and removes only its exact temporary database, grant, server, media, and fixture artifacts.
It runs the clean-starter boundary check before creating any disposable
database.

## Documentation

- [Administrator Manual Introduction](docs/ADMIN-MANUAL-INTRODUCTION.md)
- [Roadmap](docs/ROADMAP.md)
- [Theme Author Guide](docs/THEME-AUTHOR-GUIDE.md)
- [Theme Activation Readiness](docs/THEME-ACTIVATION-READINESS.md)
- [Acceptance Suite](docs/ACCEPTANCE-SUITE.md)
- [Operational Form Boundary](docs/OPERATIONAL-FORM-BOUNDARY.md)
- [Member Access Direction](docs/MEMBER-ACCESS-DIRECTION.md)
- [Version 5.1 Add-On Contract](docs/ADD-ON-CONTRACT.md)
- [Public Mutation Boundary](docs/PUBLIC-MUTATION-BOUNDARY.md)
- [Store Lite Direction](docs/STORE-LITE-DIRECTION.md)
- [Store Lite Product Contract](docs/STORE-LITE-PRODUCT-CONTRACT.md)
- [Store Lite Cart-Line Contract](docs/STORE-LITE-CART-LINE-CONTRACT.md)
- [Store Lite Cart Persistence Contract](docs/STORE-LITE-CART-PERSISTENCE-CONTRACT.md)
- [Version 5.1 Direction](docs/VERSION-5.1-DIRECTION.md)
- [Security Notes](docs/SECURITY.md)

## Database And Release Safety

- Back up a retained database before migrations or release work.
- Test migrations first against a disposable restored copy.
- Never edit an applied migration.
- Preserve public URL and table-name compatibility unless a separate migration explicitly approves a change.
- Keep every client database, media archive, and rollback point outside the clean starter release.
- Review and merge release branches through pull requests; do not publish directly from an unverified dirty worktree.

## License

RED-CMS source headers identify the project as MIT-licensed. Bundled third-party libraries retain their own license terms, including the local TinyMCE compatibility editor.
