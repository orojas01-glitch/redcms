# RED-CMS Local Acceptance Suite

Date: 2026-08-08

## Purpose

`scripts/dev-acceptance.sh` creates and removes a disposable local database to prove that the checked-in installer and every migration still produce the expected RED-CMS schema.

This is a local development and controlled staging tool. It is not a HostGator deployment script and should not be uploaded into or run from the public web root.

`scripts/store-lite-browser-rehearsal.sh` is the separate opt-in integration
gate for the externally distributed Store Lite package. It stages a temporary
clean core plus package, uses one uniquely named disposable schema and scoped
grant, applies core and package migrations, creates only an acceptance fixture,
and drives desktop/mobile Chrome through a public homepage Product check plus
authenticated Products Add/Create and existing-target Edit/Save/reload. It then
verifies exact package and core audit facts, unchanged variable-product state,
zero browser/runtime errors, the
configured primary fingerprint, and removal of the temporary server, schema,
grant, and staged package. Its screenshots and JSON report remain in the
printed non-secret temporary evidence directory. This gate does not make Store
Lite part of the starter or authorize normal richer-package enablement.

Before it creates a database or grant, the runner executes
`scripts/clean-starter-boundary-self-test.php` and
the SEO, add-on trust, component-editor value, and display-only
component-editor renderer contract self-tests. Those dependency-free checks
require the
portable theme set, generic installer presets, server-local outbound-mail
configuration, deployment-neutral Apache rules, the shared SEO storage,
editor, validation, rendering, and cleanup contract, and a fail-closed
migration manifest with explicit reporting and transaction guards. They also
require closed, non-executing add-on discovery with exact path, compatibility,
dependency, route, settings, and SHA-256 inventory validation.
The 48-assertion trust fixture also validates optional administrator-tool,
administrator-form, and administrator-action contracts as data only: each
display contract references one provided tool, one already-requested
permission, bounded label/description/icon metadata, and the fixed `read-only`
mode. Each form contract binds a unique form id to one provided tool and
declared permission with fixed JSON/POST/CSRF policy and a bounded body. Each
action contract references one provided tool, one unique action, one
already-requested permission, bounded label/description metadata, and only
`POST` with required CSRF. Executable, writable, undeclared, duplicate,
ungranted, or weakened method/CSRF metadata fails without running package PHP.
The separate 22-assertion setting-value fixture validates normalized manifest
definitions, exact type-correct defaults, closed setting keys, required-value
reporting, bounded text, strict booleans and integers, declared selections,
credential-free HTTP/HTTPS URLs, email addresses, and opaque lowercase
`config:` secret references. Invalid input returns no ordinary values or
secret references. The fixture performs no database access, secret lookup,
authorization, administrator rendering, package execution, or lifecycle
change.
The separate 12-assertion settings-editor fixture validates the core-owned
authenticated request shape, strict ordinary scalar decoding, exact boolean
and integer forms, unknown/nested/secret submission refusal, escaped control
rendering, stale-plan binding, masked secret state, and no package markup or
disclosure. It performs no database access, package execution, secret lookup,
or lifecycle mutation.
The separate 8-assertion secret-resolution fixture validates bounded
server-local value sources, exact allowlist-required resolution, environment
and local conflict refusal, and an internal by-reference value boundary. It
returns no secret bytes in its fixed result and performs no database access,
request parsing, package execution, or lifecycle change.
The separate 18-assertion secret-reference availability fixture validates an
empty default, sorted local/environment declaration merging, deduplication,
bounded fail-closed syntax, exact package/configuration binding, deterministic
hashes, missing setting-key reporting, stale and forged evidence refusal, and
packages with no secret settings. Returned evidence contains no `config:`
identifier or secret value and the helper has no database or package execution
path.
The separate 21-assertion asset-plan fixture accepts only a trusted manifest
surface's package-owned CSS at `head` and JavaScript at `body-end`, creates
sorted checksum-versioned namespaced URLs, validates exact plan evidence, and
renders only escaped core-owned tags. Invalid package/surface/structure,
unsafe or duplicate paths, unsupported types, uppercase or invalid checksums,
wrong locations, stale plan hashes, and forged URLs return no partial plan or
markup. The helper performs no package-file read, HTTP delivery, response
injection, database access, package execution, or lifecycle change.
The separate 8-assertion immutable asset-endpoint fixture proves that the
reserved delivery path is handled before the ordinary bootstrap and can only
return exact enabled CSS/JavaScript bytes. It requires checksum and byte-length
revalidation, `GET`/`HEAD` behavior, fixed content/cache/safety headers,
no package execution or registry write, and generic fail-closed behavior for
noncanonical, stale, disabled, tampered, oversized, traversal, and unsupported
method requests. The suite then makes real FrankenPHP HTTP requests against an
attested temporary first-party fixture and verifies exact bytes, headers, no
session, and no package-PHP execution before removing that fixture.
The separate 11-assertion asset-injection fixture requires current trusted
catalog and enabled-registry evidence, public-only and public-plus-administrator
plans, exact escaped tag output, unambiguous document-boundary placement,
forged-plan refusal, no registrar execution or registry mutation by the
planner, disabled/integrity-drift refusal, invalid enabled-surface refusal, and
exact fixture cleanup. The full suite then makes real anonymous and signed-in
administrator HTTP requests against a temporary first-party package, verifies
public tags remain separate from administrator tags, and proves injection adds
no extra runtime registration before removing the package and administrator.
The 31-assertion disposable setting-storage/editor, atomic-writer, and secret-
replacement fixture requires
the exact empty seven-column table and installation foreign key, explicit
package-declared permission bindings, fresh binary grants, exact trusted
filesystem/registry identity, installed-disabled or enabled state, complete
typed target values, valid current stored rows, deterministic current/target
and plan hashes, immediate revocation, zero package execution, exact full
replacement, ordinary/secret column separation, one bounded audit fact,
no-op handling, stale-target refusal, server-local secret resolution,
initial missing-secret binding, unavailable-reference refusal, and exact
package/administrator/grant/filesystem cleanup.
Optional component-editor metadata is also validated as a fixed, data-only
schema: declared components and permissions must resolve exactly, field types
and constraints are allowlisted, and executable or storage-owned instructions
are rejected. Declaring that metadata remains an explicit activation blocker
until the core-owned editor and persistence lifecycle exists.
The separate 13-assertion value contract accepts only schema-declared scalar
fields, normalizes their closed types, rejects malformed or unknown input, and
returns no normalized payload on any error without executing package code or
opening a database.
The separate 20-assertion renderer contract maps those schemas to escaped,
core-owned controls, checks stable label/help/error relationships and fixed
field-type attributes, rejects forged state, and proves the fragment contains
no form, submit control, package markup, package execution, or database access.
It also validates a core-owned, value-free revision timeline, exact newest-first
metadata, current-state agreement, escaped actors, fixed status language, no
hash/value/action disclosure, and fail-closed stale or forged history.
The runner also executes a temporary first-party runtime fixture that proves
fixed-entrypoint integrity, exact manifest registration, dependency ordering,
and fail-closed output or registration ambiguity without enabling a package.
The separate 15-assertion typed-service fixture proves exact enabled
request-local ownership and manifest agreement, immutable request/result
objects, bounded JSON-compatible input/output, pre-handler refusal of invalid
values, explicit package error results, and containment of malformed returns,
output, exceptions, and output-buffer tampering without adding an HTTP,
administrator, session, or database surface.
The separate 20-assertion public-route fixture proves that core claims only an
exact unencoded static path from the enabled request-local registrar, permits
only public `GET` plus `csrf: not-applicable`, passes only a final request with
bounded query values, and emits only core-encoded JSON from a final result. It
also proves non-GET, invalid-query, member, unsafe-method, and placeholder
routes are refused before handler invocation; package output, exceptions,
buffer tampering, malformed results, and oversized responses fail closed; and
the front controller dispatches a claimed route before theme output. Current
enablement profiles remain unchanged and still reject route-bearing packages.
The separate 17-assertion public-mutation declaration-preflight fixture proves
that one closed static POST/CSRF declaration canonicalizes deterministically
into value-free evidence while declaration absence, route drift/placeholders,
executable metadata, reserved request names, weak policy, core-table claims,
malformed identities, and forged hashes fail closed. It also proves the helper
has no database, request-global, package-execution, or filesystem-read path.
It creates no route, handler, session/cookie, table, or enablement eligibility;
the current public-route fixture must still refuse unsafe methods before a
package handler. The supported-server rehearsal now proves the disposable
anonymous-subject, CSRF, rate/idempotency, transaction, response-redaction, and
exact-cleanup path; disablement and client deployment still precede any
production behavior change. The separate 18-assertion core-owned
subject-cookie lifecycle fixture proves ensure/resolve, transactional
rotation, fixed clearance, old-token and subject-bound CSRF invalidation,
malformed-input refusal, active-transaction refusal, and exact cleanup.
The separate 20-assertion Store Lite product-contract fixture locks the
package-owned simple-product and bounded variable-product shape before any
database is created. It covers integer minor-unit money, one installation
currency, explicit Size/Color variants, unique identifiers/SKUs/option tuples,
three option groups, sixteen values per group, and 128 variants per parent.
It performs no database, request, package, lifecycle, or runtime work.
The separate 21-assertion Store Lite cart-line contract fixture then consumes
only Gate 2A-normalized product-shaped arrays. It accepts only product,
quantity, and optional variant intent, derives SKU, option labels, integer
price/total, currency, stock evidence, and product-state SHA-256 from current
server product state, and returns no partial line for draft, unavailable,
currency-drifted, malformed, stale-variant, out-of-stock, unknown-field, or
invalid-quantity input. It loads no package or core runtime and opens no
database, request, session, cookie, route, or commerce service.
The separately distributed Store Lite 0.1.13 package has two additional
cross-repository disposable database gates. Its 38-assertion migration suite
applies the exact five manifest migrations and proves ten package-owned InnoDB
tables, numeric subject ownership with no core foreign key, exact cart-line
product/variant relationships, and value-free activity columns. Its 79-
assertion combined catalog/cart persistence suite proves caller transaction
ownership, simple and explicit-variant lines, additive quantity, server-derived
money, fresh/stale cart state, subject isolation, refusal without drift, forced
late-activity rollback, restrictive deletion, exact schema/grant cleanup, and
an unchanged configured-primary fingerprint. These suites run from the
separate package repository; the clean-core `dev-acceptance.sh` does not load
or execute Store Lite package code.
The separately distributed Store Lite 0.1.14 package adds one real-runner gate
inside the existing disposable browser rehearsal. It proves one accepted
simple-product add, exact idempotent replay, changed-command key conflict, one
accepted explicit variable-product variant, invalid-variant rollback, exact
two-cart/two-line/quantity-three package state, two package activity rows, two
core execution rows, and two value-free core audits. The same Google Chrome
desktop/mobile run retains 87/87 checks with empty console, page-error,
failed-request, and HTTP-error evidence. The configured primary fingerprint is
unchanged and the temporary schema, grant, server, and staged package are
removed.
The separate 20-assertion public-mutation form UI fixture runs before any
database is created. It requires a manifest-derived static POST action,
same-subject issued CSRF/idempotency evidence, only declared product/quantity/
optional-variant controls, escaped labels/options, unique form-control ids,
bounded number/select semantics including exact 128-choice acceptance and
129-choice refusal, one polite status region, and a no-script
notice. Missing, unknown, duplicate, malformed, out-of-range, cross-subject,
or post-composition-invalid models render no partial form or token. The helper
reads no request globals, database, package, filesystem, or front-controller
state and emits no output or headers.
The separate 12-assertion disposable form-bootstrap fixture validates the
complete presentation before issuance, proves absent-cookie subject issuance
and valid-cookie subject reuse, same-subject scoped CSRF/idempotency evidence,
simple and variable form composition, subject-token exclusion from markup,
forged-lifecycle cleanup refusal, caller-transaction refusal, and exact
compensation when idempotency storage becomes unavailable after CSRF issuance.
It finishes with zero subject, CSRF, and idempotency rows and creates no
endpoint, request-global read, package load, HTML/header emission, or client
state.
The separate 16-assertion administrator-tool form-preflight fixture proves one
closed JSON/CSRF form declaration, exact request-local tool ownership, fresh
binary package permission, deterministic actor-bound hashes, contract-drift
evidence, next-decision revocation, zero callback invocation or writes, and
exact administrator/role/grant cleanup. It creates no form schema, HTML,
request-body decoder, endpoint, route, or enablement path.
The separate 19-assertion administrator-tool form-renderer fixture proves the
optional closed field schema and core-owned disabled preview before any
database is created. It covers scalar fields, simple-product controls, option
groups with nested values, 128 bounded variants with exact option selections,
two-level depth refusal, duplicate and executable metadata refusal, escaped
copy, deterministic markup, schema-bound contract drift, and zero values,
authorization, package execution, request, CSRF, endpoint, or submission path.
The separate 26-assertion administrator-tool form JSON-adapter fixture proves
exact transport, canonical duplicate-refusing JSON, the closed request root,
fresh permission and manifest body limits before provider invocation, current-value
reload, stale-state refusal, complete nested submitted-value validation,
deterministic opaque evidence, public redaction, revocation, schema drift, zero
package mutation, and exact administrator/grant/table cleanup. The endpoint is
unlinked and validation-only; its adapter invokes no form writer and exposes no
Save control or Store Lite data.
The separate 27-assertion administrator-tool form-writer fixture proves exact
optional writer ownership and sorted package-table metadata, deterministic
value-free validation/version/table/actor/target-bound plans, caller-transaction
refusal, locked plan recreation, fresh grant and state checks, stale replay and
version/contract drift refusal, unchanged no-op behavior, exact postcondition
reload, and atomic value-free audit. It forces output, exception, buffer,
HTTP-state, false-return, incomplete-write, wrong-write, audit, and non-InnoDB
failures and requires exact package rollback and fixture cleanup. The internal
runner remains disconnected from the validation-only endpoint and is reached
by browser submissions only through the separately tested protected Save
bridge.
The separate 21-assertion administrator-tool form Save-bridge fixture proves
exact edit identity, fresh permission, enabled-version and writer/table
ownership, escaped editable scalar controls, bounded two-level collection
add/remove templates, canonical typed JSON, atomic `saved` and no-op
`unchanged` outcomes, stale replay refusal, next-decision revocation, value-free
public responses, protected endpoint source order, scoped responsive assets,
and exact administrator/grant/package/audit/table cleanup. The full HTTP suite
additionally proves POST/session/header-CSRF before exact edit identity or Save
body handling. Local Chrome desktop/mobile inspection also passes with zero
horizontal overflow, collection add/remove behavior, typed JSON/CSRF output,
`unchanged` and `saved` reload behavior, and zero console, page, or
failed-request errors.
The separate 27-assertion non-executing deployment-profile fixture proves one
closed client review packet with canonical HTTPS, pinned server versions,
fixed HMAC/trusted-origin sources, attestation-before-PHP route order, core
response/cookie ownership, host-only cookie policy, clean-starter isolation,
secret-shaped-field refusal, and disabled activation flags. It reads no
request, filesystem, database, package, secret, or response state.
The separate 18-assertion public-mutation response fixture proves that only
the fixed `accepted` / `unchanged` outcomes and five generic refusal envelopes
can be constructed. It requires exact JSON, content type, no-store, nosniff,
length, and POST-only method headers; maps replay to its original outcome; and
rejects forged headers, bodies, and inconsistent runner results. It has no
request parser, globals, cookie/session, database, package execution, response
emission, route, enablement, Store Lite, or client-data path.
The separate 14-assertion response-owner fixture proves that a valid deployment
profile can compose that closed envelope with no cookie, one issuance/clearance
descriptor, or ordered clear-then-set rotation descriptors. It rejects package
ownership, linked-dispatcher profiles, arbitrary headers, cookie-policy drift,
invalid lifecycle states, and any request/global/session/header mutation; it
does not emit a response or link the front controller.
The separate 17-assertion deployment-review fixture proves that a profile hash
binds only non-secret Caddy/FrankenPHP/TLS/proxy artifact hashes, process-
environment trusted-origin/HMAC provisioning and old-key-revocation evidence,
and fixed desktop/mobile browser evidence. It rejects secret values, artifact
placement in the starter, request-derived trust, unreviewed rotation, browser
errors/state changes, forged review hashes, and any deployment or dispatcher
path.
The separate installation-shaped HTTPS rehearsal must be run with Docker when
the pinned builder layers are available. It stages no client or starter data,
uses a generated external certificate, proves the attestation-before-PHP route
over HTTPS, rotates the process-environment HMAC key across a container
restart, and captures fixed desktop/mobile browser evidence with zero console
or network errors, no cookie, no opaque token, and no client-state change. Its
retained output is non-secret and outside the starter; its private key, secret
values, image, container, and build context are removed. It does not count as
a client deployment or a front-controller link.
The separate 17-assertion public-mutation live-data-preflight fixture proves
that a trusted `installed_disabled` package can be inspected only through
current migration, declared InnoDB-table, typed-setting, and opaque
secret-availability evidence plus exact core subject/CSRF/rate-limit/idempotency/
execution storage. It proves
missing tables, incomplete settings, missing opaque secret references, missing
core storage, and unsupported table engines remain value-free blockers;
unchanged evidence produces one deterministic plan; forged counts fail; and
plans disclose no table name or opaque reference. It also proves no request
global, transaction, runtime registration, lifecycle write, package execution,
or residual package/table/authorization/filesystem fixture survives. It still
cannot itself issue a subject, CSRF token, or idempotency key; resolve a secret;
dispatch a request; or enable the package.
The separate 15-assertion core-only rate-limit fixture proves exact InnoDB
storage, a client/declaration/subject-scoped 12-per-60-second cap, opaque scope
persistence, caller-owned transaction refusal, bounded expiry cleanup, subject
cascade cleanup, and no request-global, browser-response, package-load, or
runtime-registration path. It creates no public route or package fixture.
The separate 18-assertion core-only idempotency-key fixture proves exact InnoDB
storage, a hash-only client/declaration/subject scope, opaque 10-minute key
issuance and correct subject/scope resolution, no raw-key persistence,
caller-owned transaction refusal, bounded expiry cleanup, subject cascade
cleanup, and no request-global, browser-response, package-load, or
runtime-registration path. Its resolver is deliberately non-consuming until a
internal transaction runner can couple consumption to package state. It creates
no public route or package fixture. The separate disposable atomic-runner
  fixture proves keyed replay/conflict outcomes, rate claims, postcondition
  checks, and rollback of package/rate/ledger/audit state; it adds no HTTP,
  browser, dispatcher, response, or enablement path.
The separate 6-assertion public-mutation dispatcher fixture composes only
explicit method/target/capture facts with an in-memory registrar context. It
proves runtime-unavailable, non-POST, missing-attestation, incomplete-binding,
closed-result, and callback-isolation behavior; it has no database, request
global, response-emission, front-controller, browser, package, enablement,
Store Lite, or client-data path. The separate Docker-only supported-server
rehearsal now proves the complete disposable request path; the dispatcher
remains unlinked until the per-client deployment/trusted-origin/HMAC gate
passes; the core browser subject-cookie lifecycle and non-executing deployment
profile are separately proven but do not create a production endpoint.
After database migration, a separate disposable request fixture proves that
uninstalled and disabled packages remain unexecuted, enabled dependencies
register in order, core lookups resolve exact owners, and drift or missing code
fails before any package executes.
Another disposable database fixture proves the 160-character package
permission capacity, exact operation-to-permission resolution, non-Owner
package grants, no implicit Owner access, case-sensitive matching, immediate
revocation, read-only decisions, and exact actor/grant cleanup. Its grants are
test setup only; no operational grant-management workflow exists.
The separate administrator-tool fixture requires an enabled
request-local registrar, one valid data-only tool contract, and a fresh exact
case-sensitive package grant. It proves Owner/lifecycle authority does not
imply tool access, revocation applies on the next request, only granted tools
enter the core chooser, and handlers receive one final actor/tool request.
Core escapes the bounded text view model, including the optional closed
label/value fact list, and emits no package HTML, links, forms, scripts, or
write actions. Its separate optional form-target loader is
permission-scoped, runtime-setting-bound, capped at 25 unique positive numeric
records, and limited to bounded text facts plus a safe cursor; only core emits
the Edit buttons and protected POST. Output, exceptions, malformed results,
buffer or HTTP-state changes fail closed; the endpoint is protected by
administrator session and POST/CSRF checks; and all actor/role/grant fixtures
are removed exactly.
The separate 18-assertion administrator-action-preflight fixture requires one
separately declared action and exactly matching registered tool/action owners.
It proves a fresh exact action grant, no implicit Owner/lifecycle authority,
case-sensitive revocation, strict positive integer target validation,
deterministic contract/plan hashes, and plan change on target or contract
drift. It also requires one registrar-bound state loader and refuses core
ledger tables as package transaction metadata. Both package callbacks remain
uninvoked, the fixture tables remain unchanged across preflight, the preflight
itself has no endpoint, and all temporary administrator/role/grant rows are
removed exactly.
The separate 32-assertion action-runner and endpoint-bridge fixture requires the fixed
`once-per-target` contract, an enabled per-client installation, one declared
package table, and rollback-only state loading. It proves stale-plan and fresh
grant-revocation refusal before action invocation; atomic package change,
execution-ledger, and value-free audit commit; replay refusal before state
loading; and rollback after output, exceptions, malformed results, state
mismatch, or an unchanged action. It additionally proves strict endpoint input
parsing, server-derived plans, a current integer actor, bounded success/conflict
responses without package/state values, immediate grant revocation, and
contained package-output failure. The real HTTP acceptance pass proves the
unlinked endpoint rejects a non-POST request without a session, rejects a
signed-in POST without CSRF, and rejects malformed fields after CSRF validation.
It leaves no administrator, grant, installation, ledger, audit, or package-table
fixture behind.
The next 20-assertion disposable fixture requires exactly one loader for each
declared editor and refuses undeclared or duplicate loaders. It proves exact
view permission, enabled parent/runtime/manifest ownership, normalized returned
values, a stable core-owned state hash, no database writes, revocation and case
drift before invocation, disabled and drifted binding refusal, foreign-manifest
refusal, invalid-value rejection, output/exception/buffer containment, and
exact database/filesystem cleanup.
The separate 47-assertion operational-form/update/restore fixture permits at most one
writer for a declared editor, validates closed package-table metadata, and
requires InnoDB transaction support. It proves current view/edit grants,
locked enabled ownership, stale-state refusal, normalized writes, exact
reloaded postconditions, immutable baseline/save snapshots, unchanged no-op
behavior, rollback after a forced revision insert failure and after output,
exception, nested-buffer, false-return, and partial-write failures, preserved
core placement state, and exact database/filesystem cleanup. It opens no web
endpoint and adds no component creation, revision history UI, delete, audit
workflow, or activation path.
It also proves bounded newest-first history without value disclosure,
deterministic restore-plan hashes, exact current and target state evidence,
already-current/stale/revoked refusal, tampered-snapshot rejection, and zero
restore execution during preflight. The atomic runner then proves exact plan
matching under the locked enabled parent, registered-writer containment,
source-linked restore revisions, stale-plan single use, exact target reload,
and rollback on writer or restore-ledger failure.
The separate 85-assertion creation/preflight/runner/parent-metadata/public-placement/delete fixture
permits at most one
creator for a declared editor with closed package-table metadata. It requires
the exact create/view/edit/publish/delete grants, enabled manifest and runtime component/loader/creator
ownership, InnoDB transaction support, an unused numeric record id, one valid
active-theme layout, closed parent metadata, and normalized package values. It
proves a deterministic plan whose parent is inactive, hidden, and unrouted,
plus callback non-invocation, unchanged database fingerprints, disabled,
revoked, mismatched, invalid, unsupported-table, and existing-record refusal,
and exact cleanup. Preflight opens no transaction or endpoint, invokes no
creator or loader, reserves no id, and writes no parent or package row. The
atomic runner then proves exact plan matching, lifecycle/theme/installation
serialization, caller-owned transaction refusal, creator and loader
containment, exact parent/package postconditions, one core `create` plus one
package `baseline` revision, repeat refusal, and rollback after output,
exceptions, nested buffers, false returns, partial writes, and forced failure
of either revision ledger. The parent-metadata extension requires a fresh
exact view grant for read-only state and an exact edit grant before any package
callback on a write. It proves the closed inactive shell, package state, and
current core revision agree; rejects unknown activation metadata and
caller-owned transactions; adds no revision for unchanged values; rolls back a
forced core-ledger failure; changes only title/layout/language; preserves
hidden placement and package data; records one explicit-actor core `save`
revision; rejects the old state hash; and refuses a public/non-shell parent.
Its public-placement extension requires one unique active Article target,
exact source/target language agreement, and active-theme page-position support.
It proves publish revocation occurs before package loading, binds both current
states and closed derived placement values into one deterministic plan, writes
nothing, and refuses invalid, stale, cross-language, inactive-target, and
unsupported-position requests. Its atomic placement runner then proves
caller-transaction refusal, fresh publish reauthorization, destination-drift
refusal, rollback after forced `move`-revision or administrator-audit failure,
lifecycle/theme and source/target serialization, exact seven-field source
mutation, unchanged package and target postconditions, one explicit-actor
`move` revision plus one bounded `component.public_placed` fact, and single-use
plan refusal. Its core-owned form exposes only numeric placement choices,
current parent/package hashes, and CSRF while deriving ownership and the exact
plan again on the server.
Its delete extension proves deterministic value-free planning without deleter
execution, stale-plan and caller-transaction refusal, fresh delete grants, and
rollback after emitted output, exceptions, nested buffers, false returns,
partial deletion, or forced failure of either delete-revision insert. Success
removes the exact package and inactive parent rows, retains the two revision
ledgers with final `delete` snapshots, and refuses plan reuse.

## Current Coverage

The current compatibility foundation performs these checks through one command:

1. Capture a non-secret primary-database isolation snapshot and normalized schema manifest.
2. Generate a unique database name beginning with `redcms_acceptance_`.
3. Refuse the configured primary database, unsafe names, or any database that already exists.
4. Create the disposable database and grant the existing application database account access only to it.
5. Import `db-structure.sql`.
6. Verify 32 InnoDB tables, utf8mb4 collations, an empty migration ledger, sanitized administrator seed placeholders, empty administrator role/capability tables, and empty add-on installation/migration/audit/action-execution/public-mutation subject/CSRF/rate-limit/idempotency/execution storage.
7. Apply every checked-in migration and require the ledger count to match the migration-file count.
8. Run migrations again and require `No pending migrations.` plus zero checksum drift. Then run the 16-assertion Owner authorization lifecycle, 11-assertion component-editor package-permission lifecycle, 16-assertion administrator-tool form-preflight lifecycle, 20-assertion bounded component data-loader lifecycle, 47-assertion operational form, transactional component-update, history, restore-preflight, and atomic-restore lifecycle, 85-assertion component-creation preflight/atomic-runner/parent-metadata/public-placement-preflight/atomic-placement/atomic-delete lifecycle, 23-assertion add-on registry and immutable asset-delivery-preflight lifecycle, 8-assertion static immutable asset-endpoint lifecycle, 11-assertion request-bootstrap lifecycle, 20-assertion safe component-persistence/dispatch lifecycle, 19-assertion install lifecycle, 23-assertion read-only enablement-preflight lifecycle, 23-assertion atomic-enable lifecycle, and 18-assertion atomic-disable lifecycle. Administrator-tool form preflight requires closed declarative metadata, exact request-local tool ownership, fresh binary permission, fixed POST/CSRF/JSON/body bounds, deterministic value-free evidence, zero callbacks or writes, and exact cleanup. Component-editor permission checks require 160-character storage, exact case-sensitive manifest permission resolution, explicit non-Owner grants, no implicit Owner access, fresh revocation, zero package execution or state mutation, and exact cleanup. Component data loading requires exact declared registration, view permission, enabled persisted and runtime ownership, exact runtime-manifest identity, closed returned values and state hash, contained failures, zero writes, and exact cleanup. Component history/preflight requires bounded validated metadata, exact restore permission, current and target state evidence, deterministic plans, and zero restore execution. Atomic restore requires that exact plan under the locked enabled binding, the same registered writer and InnoDB tables, exact target reload, one source-linked immutable restore revision, stale-plan refusal, caller-owned transaction refusal, and rollback on writer or ledger failure. Component creation preflight requires exact create permission and enabled runtime ownership, closed inactive hidden parent metadata, normalized package values, an unused numeric id, active-theme layout, InnoDB package tables, deterministic evidence, callback non-invocation, and zero writes. Parent metadata requires exact view/edit permissions, an inactive hidden unrouted shell, current package and core-revision evidence, a caller state hash, lifecycle/theme/installation/parent serialization, title/layout/language-only postconditions, package-state preservation, unchanged no-op behavior, core revision rollback, and exact cleanup. Public-placement preflight requires the exact publish grant before package loading, the current parent/package hashes, one unique active Article destination, exact language agreement, active-theme position support, deterministic source/target/placement evidence, and zero activation or writes. Atomic placement revalidates that plan under lifecycle/theme/source/target locks, changes only seven derived parent fields, preserves package and destination state, commits one core move revision plus one bounded audit fact, refuses reuse, and rolls back permission, drift, transaction, postcondition, revision, or audit failure. Its core-owned form exposes only numeric placement choices, current parent/package hashes, and CSRF while package, component, manifest, grants, target ownership, and the exact plan remain server-derived. Component updates require exact writer ownership and package-table metadata, current view/edit grants, locked enabled binding, current state hash, normalized values, InnoDB transaction support, caller-owned transaction refusal, exact reloaded postconditions, atomic baseline/save revision snapshots, rollback on every contained or revision-ledger failure, preserved core placement state, and exact cleanup. Component persistence/dispatch requires non-executing discovery, disabled non-execution, enabled request-local registration, full manifest component-id storage, a package-owned table with only the exact numeric article-parent foreign key, orphan refusal, read-only parent/runtime-owner agreement, fixed non-executable placement data, core-owned escaped default title/summary and optional fact markup, static fail-closed fallbacks for emitted output, malformed view models, handler exceptions, and output-buffer tampering, inactive non-rendering, unchanged legacy contexts, and exact cleanup. Enablement preflight requires exact Owner authority, current installed-disabled evidence, deterministic plans, dependency and namespace checks, declarative readiness for constrained registration-only service, core-rendered default public component, and combined default-component plus registration-only-service profiles, explicit richer-surface blockers including declarative component-editor metadata, zero state mutation or package execution, CLI-only boundaries, drift refusal, and exact cleanup. Atomic enablement separately requires exact Owner authority and plan evidence, registrar validation under the shared lifecycle lock and package lock, atomic compare-and-swap state plus audit, lifecycle reach from newly enabled standalone and combined default components to the safe core renderer, injected-failure rollback, later registration of every declared combined-package identifier, repeat refusal, and exact cleanup. Atomic disablement requires exact Owner authority, deterministic current-registry and enabled-dependent evidence, cross-connection lifecycle-lock exclusion, stale-plan refusal, atomic state/audit rollback and commit, zero package or migration execution, removal of both combined-package component and service registrations from later requests, repeat refusal, and exact cleanup. Then run the 38-assertion disposable SEO lifecycle, 29-assertion content-revision lifecycle, 21-assertion page-layout distribution lifecycle, and 36-assertion custom-layout lifecycle with their existing rollback and cleanup requirements.
   The 17-assertion public-mutation live-data preflight uses a temporary
   trusted installed-disabled package only in the disposable database. It
   proves current migration, declared InnoDB-table, typed-setting, and opaque
   secret-availability evidence can be inspected through counts and hashes,
   together with exact generic subject/CSRF/rate-limit/idempotency/execution storage; missing or unsupported
   evidence remains a value-free blocker; and no route, package execution,
   lifecycle write, or fixture survives. The separate 19-assertion core-only
   anonymous-subject/CSRF test creates no package fixture. It proves the exact
   empty storage shape, SHA-256-only persistence, opaque distinct subjects,
   host-only secure cookie descriptor, declaration/database scope, forgery and
   cross-subject refusal, expiry, cascading bounded cleanup, and zero remaining
   subject/CSRF rows. The separate 15-assertion rate-limit fixture proves the
   exact empty InnoDB shape, fixed 12-per-60-second client/declaration/subject
   cap, opaque scope persistence, caller-owned transaction refusal, bounded
   cleanup, subject cascade, and zero remaining rate rows. The separate
   18-assertion idempotency-key test proves exact hash-only storage, one
   10-minute opaque key per active subject/declaration scope, correct
   subject/scope resolution without raw-key persistence, caller-owned
   transaction refusal, bounded cleanup, subject cascade, and a deliberately
   non-consuming resolver. None of these core-only tests exercises an HTTP
   endpoint or package handler. The separate disposable atomic-runner fixture
   requires the exact completed-execution ledger, a temporary table and trusted
   in-memory binding, then proves replay/conflict behavior, fixed rate-budget
   ordering, postcondition checks, contained callback failure, and complete
   rollback/cleanup without a route, response, browser state, package files, or
   Store Lite data.

   The atomic administrator-action runner and protected endpoint bridge additionally prove the fixed
   `once-per-target` contract, declared package-owned InnoDB table metadata,
   rollback-only target-state preflight, exact-plan/revocation/replay refusal,
   contained action and loader behavior, postcondition verification, atomic
   per-client execution evidence plus value-free audit, rollback on output,
   exception, malformed result, or mismatch, and exact fixture cleanup. The
   core endpoint independently enforces POST/session/CSRF, exact fields, and
   value-free bounded results; it adds no browser action surface.
9. Verify the final 34-table InnoDB/utf8mb4 state, canonical clean-installer row counts including zero SEO, administrator authorization, add-on installation, setting, migration, lifecycle-audit, action-execution, public-mutation subject/CSRF/rate-limit/idempotency/execution, and add-on component revision rows, and zero Form, Gallery, area, layout, and component relationship errors, then run the 14-assertion two-connection theme-contract suite against the disposable database. That suite proves database-scoped locking, reentrancy, cross-connection exclusion, exception-safe release, effective-theme agreement, safe inactive upload placeholders, and reserved active/previous theme rows.
10. Compare a normalized table/column/index manifest with the configured primary schema while ignoring data and auto-increment counters.
11. Confirm the primary isolation snapshot is unchanged.
12. Select an unused high local port while explicitly reserving port `8055` for the normal development site.
13. Start FrankenPHP against the disposable database and wait for an HTTP 200 readiness response.
14. Require HTTP 200, a nontrivial response size, route-specific content markers, and no PHP/runtime error markers for `/`, `/contacto/`, `/administracion/`, `/administracion/instructions`, and the clean-installer seed `/administracion/test-vimeo`.
15. Insert one disposable plaintext-password Webmaster and require matching generic HTTP 200 `no` responses for wrong-password and unknown-user logins.
16. Require a successful login to upgrade the legacy password to bcrypt, clear only that username's failed attempts, and preserve the upgraded hash on a second login.
17. Require the authenticated homepage overlay, a valid 64-character CSRF token, the same token in a protected Administrator Users form, HTTP 403 `csrf` for a protected write without the token, an unlinked administrator-action endpoint that rejects non-POST without a session, rejects signed-in missing-CSRF POST, and rejects malformed fields after CSRF validation, plus an unlinked administrator-form JSON endpoint that rejects non-POST without a session, verifies header CSRF before body validation, rejects alternate content types, and rejects an invalid closed body.
18. Require logout to redirect to `/` and make the old session return HTTP 403 `no` on a protected endpoint.
19. Log in again with the upgraded password, delete the temporary administrator directly, and require the still-open session to return HTTP 403 `no` because its database-backed fingerprint can no longer be validated.
20. Remove and verify zero temporary administrator, failed-login, and activity-audit rows. Login/logout intentionally remain outside the minimal activity-audit event allowlist.
21. Insert and log in one narrowly assigned Guest with only Article component `#100` and Move Content tool `#1`.
22. Require the Guest to render the assigned Article form and Move Content tool with their expected form/CSRF markers.
23. Require HTTP 403 `no` for a valid-CSRF site-layout write, Administrator Users, unassigned Video component `#107`, and unassigned Filter Areas tool `#2`.
24. Compare full checksums for all 34 tables before and after the allowed/denied permission requests and require no data changes.
25. Require Guest logout and account deletion to independently invalidate the formerly allowed Article render, then remove and verify zero Guest administrator, throttle, and activity rows.
26. Insert and authenticate one separate disposable Webmaster plus one disposable active Section for the Section-delete lifecycle, upgrade its fixture password to bcrypt, and extract its valid CSRF token.
27. Create one active and one already-inactive Article through the real protected endpoint, both assigned to that Section, and require exact relationship/count state.
28. Render **Move Content** and require its selectable Article, destination fields, CSRF token, and Update control inside one form. Move the active Article Section → Home and require exact `HomePosition=1`/`SectionPosition=0` plus public Home rendering; move it Home → Section and require exact `HomePosition=0`/`SectionPosition=2` plus public Section rendering; reject undeclared destination position `99` with unchanged state. Then render the protected Section editor and require its exact two-Article archive confirmation plus matching CSRF token.
29. Submit the Section delete without CSRF; require HTTP 403 `csrf` and unchanged Section/Article active-state counts.
30. Submit the protected delete; require legacy response `yes`, exact `X-RED-Archived-Articles: 2`, and atomic state of zero Sections plus two preserved inactive Articles and zero active Articles for that alias.
31. Require both rows in the authenticated **Inactive Articles** panel and require the deleted route to return the active-theme HTTP 404 without its old content.
32. Remove and verify zero Section-delete administrator, Section, Article, failed-login, and activity-audit artifacts.
33. Insert and authenticate one separate disposable Webmaster for the Article lifecycle, upgrade its fixture password to bcrypt, and extract its valid CSRF token.
34. Capture a filename/SHA-256 manifest of every pre-existing `images/articles` file, generate a valid 68-byte PNG, and upload it under a database-specific filename longer than the legacy 50-character limit through protected `post_file.php` before metadata exists. Require HTTP 200, the exact stored name and source/stored/served hashes, plus one complete inactive strict-schema placeholder at hidden position `0`.
35. Create that Article through `insert_content.php`; require the placeholder to be promoted to a renderable manifest position, the first long filename to remain attached, the exact saved fields, canonical count increase, and valid Article-component, active-section, and layout relationships.
36. Upload a second long filename through the existing-Article path, require both database fields/files/hashes, then render the protected editor with both names, saved metadata, form ID, and matching CSRF token; require the new public route to render its route-specific title and body marker.
37. Update the Article through `update_content.php`; require both image names and all relationships to remain, the updated editor and public route to render, and the former alias to stop rendering. Delete through `delete_label.php` and require the canonical four-Article count, zero fixture IDs/aliases, and absence from the deleted public route.
38. Remove only the two acceptance-owned images and generated source, require the full pre-existing Article media manifest to match, and verify zero Article-lifecycle administrator, Article, failed-login, activity-audit, or file artifacts. General Article CRUD intentionally remains outside the minimal Administrator Users activity-audit allowlist.
39. Insert and authenticate one separate disposable Webmaster for the Form lifecycle, upgrade its fixture password to bcrypt, and extract its valid CSRF token.
40. Create one Contact Form through `insert_form.php`; require the exact `RED_Articles` parent shell, exact `RED_C_Form` child, canonical count increases, and valid `RefID`, subtype `#102`, active-section, and layout relationships.
41. Render the paired protected Form editor with its parent/child IDs, saved values, form ID, and matching CSRF token, then require the new public route to render its exact title and generated field marker without PHP/runtime warnings.
42. Update both Form rows through `update_form.php`; require exact new database values, preserved parent/child/component/area relationships, updated editor, updated public route, and absence from the former alias.
43. Delete the child and parent through the paired `delete_label.php` `T=form` transaction; require the canonical four-Article/two-Form counts, zero fixture IDs/aliases, and absence from the deleted public route.
44. Remove and verify zero Form-lifecycle administrator, parent Article, child Form, failed-login, and activity-audit rows. General Form CRUD intentionally remains outside the minimal Administrator Users activity-audit allowlist.
45. Insert and authenticate one separate disposable Webmaster for the Gallery lifecycle, upgrade its fixture password to bcrypt, and extract its valid CSRF token.
46. Create one Video Gallery through `insert_gallery.php`; require the exact `RED_Articles` parent shell, exact `RED_C_Gallery` child, canonical count increases, and valid `RefID`, subtype `#107`, active-section, and layout relationships.
47. Render the paired protected Gallery editor with its parent/child IDs, saved values, form ID, and matching CSRF token, then require the new public route to render its exact title and generated YouTube embed without PHP/runtime warnings.
48. Update both Gallery rows through `update_gallery.php`; require exact new database values, preserved parent/child/component/area relationships, updated editor, updated public route, and absence from the former alias.
49. Delete the child and parent through the paired `delete_label.php` `T=gal` transaction; require the canonical four-Article/one-Gallery counts, zero fixture IDs/aliases, and absence from the deleted public route.
50. Remove and verify zero Gallery-lifecycle administrator, parent Article, child Gallery, failed-login, and activity-audit rows. General Gallery CRUD intentionally remains outside the minimal Administrator Users activity-audit allowlist.
51. Capture a filename/SHA-256 manifest of every pre-existing `images/gallery` file, reserve one database-specific acceptance filename, and refuse a pre-existing file, symbolic link, or non-file at that exact path.
52. Insert and authenticate a separate disposable Webmaster, then create one subtype `#106` Gallery parent/child metadata fixture through `insert_gallery.php` before uploading any media.
53. Generate a valid 68-byte 1×1 PNG, upload it through the protected multipart `post_file.php` endpoint with the session CSRF header, and require the exact database-specific stored name plus matching source/stored/served SHA-256 values.
54. Require the protected Gallery editor and public route to render the stored filename and caption without PHP/runtime warnings.
55. Delete the paired metadata through `delete_label.php`, remove only the exact acceptance-owned image, require the public route to stop rendering it, and require the complete pre-existing Gallery media manifest to match its starting state.
56. Remove and verify zero upload-lifecycle administrator, parent Article, child Gallery, failed-login, activity-audit, generated-source, and uploaded-file artifacts.
57. Insert and authenticate one final disposable Webmaster, then create an exact subtype `#107` Video Gallery parent/child fixture through the real protected endpoint.
58. Capture checksums for all 34 application tables, install a `BEFORE UPDATE` trigger only in the disposable database, and submit a real protected Gallery update whose parent write is attempted before the trigger rejects the later child write.
59. Require HTTP 200 with the legacy response `no`, remove the trigger, require the complete 34-table checksum snapshot to match, require the exact initial parent/child values to remain, and require zero updated aliases.
60. Submit the same protected update after trigger removal and require `yes` plus exact updated parent/child values, then delete the pair through `delete_label.php` and restore canonical counts.
61. Remove and verify zero rollback-lifecycle administrator, parent Article, child Gallery, failed-login, activity-audit, and trigger artifacts. An injected failure immediately after trigger creation must also remove the trigger, fixtures, isolated server, scoped grant, and database.
62. Scan the isolated server log for PHP/runtime error markers.
63. Stop the isolated server, clean authentication/permission/Section-delete/Article/Form/Gallery/upload/rollback fixtures, remove the temporary response/cookie directory, revoke and verify removal of the temporary grant, and drop the disposable database through an exit trap on success, failure, `INT`, or `TERM`.

Representative behavior coverage is complete through the generic Version 5.1
SEO, read-only add-on trust, persisted Owner authorization, read-only registry
and immutable asset-delivery preflight/static endpoint,
guarded disabled-install/recovery, read-only enablement-preflight, atomic
constrained service/default-component/combined-profile enablement, and
non-destructive atomic disablement foundations, fail-closed enabled-package
request bootstrap, plus
Milestone 5 content-version, direct page-structure, and custom Layout Builder
foundations.
The latest complete 2026-08-08 run passed the
22-assertion clean starter boundary, 92-assertion SEO contract, 17-assertion SEO
metadata migration contract, 48-assertion add-on trust contract, 22-assertion
add-on setting-value contract, 8-assertion add-on secret-resolution
contract, 18-assertion add-on secret-reference availability contract,
21-assertion add-on asset-plan contract, 20-assertion Store Lite
product-contract, 17-assertion
public-mutation declaration contract, 13-assertion add-on component-editor value
contract, 20-assertion display-only component-editor renderer contract,
19-assertion administrator-tool form schema/preview contract,
17-assertion non-activating runtime contract, 15-assertion typed-service
contract, and 20-assertion public-route contract, imported the 32-table installer,
applied all 45 migrations to the expected 34-table schema with zero pending or
drifted files, and completed the 16-assertion Owner authorization,
11-assertion component-editor package-permission authorization,
31-assertion add-on setting storage/editor, authorization preflight, atomic
writer, and secret replacement,
13-assertion permission-scoped add-on setting read model,
18-assertion permission-scoped administrator-tool dispatch,
16-assertion non-executing administrator-form preflight,
25-assertion permission-scoped administrator-form current-value loader with
typed runtime-setting injection and configuration-bound state evidence,
26-assertion validation-only administrator-form JSON adapter,
27-assertion atomic administrator-form writer,
21-assertion operational administrator-form edit-and-Save bridge,
18-assertion non-executing administrator-action preflight,
32-assertion atomic internal administrator-action runner and protected endpoint bridge,
20-assertion bounded component-editor data-loader lifecycle,
47-assertion operational component form, transactional component-editor update, history, restore-preflight,
and atomic-restore lifecycle,
85-assertion component-creation preflight/atomic-runner/parent-metadata/public-placement-preflight/atomic-placement/atomic-delete lifecycle,
23-assertion add-on registry and immutable asset-delivery preflight, 8-assertion
static immutable asset endpoint, 11-assertion core-owned public/admin asset
injection, 19-assertion disabled
installation/recovery, 23-assertion read-only enablement preflight,
17-assertion read-only public-mutation live-data preflight, 19-assertion
core-only anonymous-subject/CSRF foundation, 7-assertion pure subject-cookie
serializer, 15-assertion core-only fixed-window rate-limit foundation,
18-assertion core-only opaque idempotency-key foundation, 18-assertion
core-owned browser subject-cookie lifecycle,
27-assertion non-executing per-client deployment profile,
21-assertion atomic core-only public-mutation transaction runner, 18-assertion
bounded public-mutation response contract, 8-assertion closed core response
emitter, 24-assertion declared
public-mutation form decoder, 37-assertion pure HTTP request envelope,
19-assertion private static mutation-route selector, 16-assertion
non-routable server request-facts adapter, 12-assertion optional
FrankenPHP-ingress PHP verifier, 6-assertion unlinked public-mutation
dispatcher composition,
23-assertion atomic enablement, 11-assertion enabled-package request bootstrap,
18-assertion atomic disablement, 17-assertion safe component
persistence/dispatch,
38-assertion SEO database, 29-assertion content-revision, 21-assertion
layout-distribution, and 36-assertion custom-layout lifecycles. The normalized
schema matched signature
`cb6e941861fc5ed74142f11b0f36536549a335f478b8214e613836f360501a3f`.
It also passed every authentication, permission, Move Content, Section archive,
Article upload/CRUD, Form CRUD, Gallery CRUD/upload, and forced-rollback
lifecycle with clean logs, preserved the tracked empty-media manifests, and
removed its fixtures, server, databases, grants, authorization rows, registry
rows, and add-on audit rows.

On 2026-08-09, the final Gate 2D2D3B tree reran the complete suite against a
new 45-migration protected disposable baseline and a separate uniquely named
acceptance database. The new 10-assertion endpoint, 4-assertion cookie emitter,
and 17-assertion component/page coordinator checks passed alongside every
existing migration, HTTP, authorization, CRUD, media, transaction, rollback,
theme-contract, and schema-signature gate. The configured disposable-primary
snapshot remained unchanged; independent post-run checks found zero temporary
schemas and zero grants.

A separate generated-fixture Chrome inspection rendered the new component
editor's valid and field-error states at 1512×900 and 390×844. Both viewports
had zero console errors, failed requests, missing labels, clipped controls,
forms, or submit buttons. This proves the display-only markup and responsive
containment only; an operational authorization and write flow does not yet
exist.
The component revision-history fixture was also rendered from the actual core
helper at 1512×900 and 390×844. Both viewports had zero console errors,
horizontal overflow, forms, buttons, or links; all three fixed history states
and three revision rows remained present. This proves the display-only visual
contract only and does not open a restore action or operational endpoint.

## Requirements

- The documented local MySQL service must be running.
- `includes/config.local.php` must point to the normal local application database.
- The configured application database account must already exist.
- A local MySQL administrative account must be able to create databases, grant/revoke access, and create/drop the suite's disposable trigger.
- The documented FrankenPHP runtime and `curl` must be available.
- Docker Desktop is required only for the separate supported-server dispatcher
  rehearsal; that proof creates its own temporary MySQL container and does not
  use the local application database.

The local default administrative account is `root` with an empty password. Override it without placing the password on the command line:

```bash
RED_ACCEPTANCE_DB_ADMIN_USER=local_admin \
RED_ACCEPTANCE_DB_ADMIN_PASS='local-admin-password' \
scripts/dev-acceptance.sh
```

The script writes administrative credentials only to a mode-`0600` temporary MySQL options file and removes it during cleanup.

## Run

From the project root:

```bash
scripts/dev-acceptance.sh
```

A successful run ends with messages similar to:

```text
Acceptance database, Store Lite product/variant and server-authoritative cart-line contracts, Owner authorization, add-on setting values/editor, secret-reference availability, asset planning, storage/write preflight/atomic writer, permission-scoped current-setting read model, administrator-tool form schema/preview/planning/current-value loading/JSON validation/atomic writer, component data loading, transactional updates, immutable revision snapshots, atomic revision restore, component creation, parent metadata, atomic public placement, atomic deletion, add-on registry reconciliation/asset-delivery preflight, static immutable asset endpoint, enabled add-on request bootstrap, disabled add-on installation/recovery, read-only add-on enablement/public-mutation live-data preflight/anonymous subject and CSRF/fixed-window rate-limit/opaque idempotency-key/atomic-runner/bounded-response/declared-form/form-UI/component-presentation/component-form-integration/browser-controller/HTTP-envelope/route-selector foundations, atomic add-on enablement/disablement, theme-contract serialization, public runtime, authentication, permission, Move Content, Section archive/delete, Article upload/CRUD, Form CRUD, Gallery CRUD, Gallery upload, and forced transaction rollback checks passed.
Cleanup complete: stopped the isolated server and removed database/grant redcms_acceptance_....
```

The command must return a nonzero status if installation, migration, schema, relationship, primary-isolation, runtime behavior, transaction rollback, or cleanup checks fail.

The supported-server public-mutation rehearsal is intentionally separate from
the normal PHP/MySQL suite because it builds a custom Caddy/FrankenPHP binary
and requires Docker Desktop:

```bash
scripts/frankenphp-public-mutation-dispatch-proof.sh
```

It creates a fresh MySQL `8.4` container, applies the current migrations, and
uses a secret-guarded fixture-only bootstrap path to exercise one real attested
`POST` through Caddy, the PHP verifier, the core dispatcher, atomic runner, and
fixed response emitter. It then checks accepted/replay, forged-header
replacement, `GET` refusal, withheld-attestation refusal, idempotency conflict,
and exact fixture ledger/audit/rate state before removing its temporary
containers, network, image, database, package marker, and build context.

## Safety Boundaries

- Database names must match `redcms_acceptance_[A-Za-z0-9_]+`.
- The configured primary database name is always refused.
- A pre-existing disposable-looking database is refused and is not deleted.
- The cleanup trap drops a database only after this exact process successfully created it.
- Port `8055` is always refused; generated ports are checked for listeners before use.
- The isolated server receives the disposable database name through environment configuration and is stopped before database/grant removal.
- Route checks require content markers, not HTTP status alone.
- Theme-contract serialization uses two disposable-database connections, writes no content, requires a blocked connection's callback not to run, verifies exception-safe mutex release, and proves forged generic Advanced/logo writes leave the paired theme rows unchanged.
- The theme contract self-test requires the core-owned page-layout ellipsis menu to reset active-theme `details` and `summary` element styles while retaining its 32-by-30-pixel desktop geometry.
- The theme contract self-test requires structured position-`0` Article and Other cards to omit their retained legacy float wrappers, establishes a scoped editor flow context, and keeps the Hidden content grid out of the later visible-position block override.
- Authentication uses only hard-coded disposable fixture credentials inside the disposable database; it never reads or changes a real administrator password.
- The cookie jar and every authentication response live inside the suite's temporary response directory, which cleanup removes and verifies absent.
- Authentication fixture cleanup is safe to rerun and executes before grant/database removal after successful and injected-failure paths.
- Guest permission checks use only disposable account data and target the disposable database. The potentially mutating layout request uses a valid CSRF token but must be denied by the Guest role before any write.
- Owner authorization acceptance runs only in the uniquely named disposable database. It requires empty default storage, a manager-only one-time bootstrap under a database advisory lock, the exact six fixed capabilities, one allowlisted audit row, database-backed session refresh, refusal of a second Owner, refusal of Owner demotion/deletion, transactional rollback after an injected audit failure, CLI confirmation guards, and exact cleanup.
- Add-on registry and immutable asset-delivery-preflight acceptance run only in the uniquely named disposable database and execute no package PHP or SQL. They require empty default storage, deterministic identity snapshots, exact Owner capability mapping, pending/checksum/version/missing-code failure reports, enabled/current load eligibility, immutable migration identity, protected ledger ownership, exact public/admin CSS/JavaScript evidence, canonical checksum-version refusal, disabled and whole-package-integrity refusal, no output or registry write, and exact cleanup.
- Static immutable asset-endpoint acceptance runs only in the uniquely named disposable database. Its disposable fixture proves pre-bootstrap dispatch, exact `GET`/`HEAD` delivery, fixed immutable/safety headers, generic `404`/`405`/`503` refusals, no session or package execution, no registry write, and no partial response. The live HTTP fixture creates an exact marker-bound first-party package beneath `addons/` only for the acceptance run, checks checksum/length/header evidence and stale, traversal, disabled, and tampered refusal, then removes the package and marker before cleanup.
- Add-on component-editor value acceptance runs before database creation. It requires an exact validated component schema, object-shaped scalar input, closed field keys and types, canonical integer/boolean/select values, bounded valid UTF-8 text, narrow URL/email/date/datetime/media references, null handling for omitted optional fields, fail-closed empty normalized output on every error, and no package execution, authorization, rendering, or state access.
- Store Lite product-contract acceptance runs before database creation. It requires the package-owned simple/variable split, one installation currency, integer minor-unit pricing, bounded identifiers/SKUs/text, explicit complete variant option tuples, unique variant identities, and the fixed three-group/16-value/128-variant limits. Invalid parent/child field mixing, unknown fields, duplicate tuples, stale option values, floats, and partial normalized results fail closed. It performs no database, request, package, lifecycle, or runtime work.
- Store Lite cart-line contract acceptance runs immediately after the product contract and before database creation. It permits only a lowercase public product reference, integer quantity 1–100, and one required current variant for variable products. Current normalized server product state is the sole source of SKU, option labels, integer unit price and total, currency, stock sufficiency, and product-state evidence. Browser-owned commercial fields, draft/unavailable/mismatched/currency-drifted/malformed products, missing/stale/unavailable variants, invalid quantities, and insufficient tracked stock return no partial line. It creates no database, package load, request/session/cookie state, route, runtime service, cart, order, or enablement path.
- Add-on administrator-form runtime-setting declaration acceptance runs before
  database creation. It requires each optional non-empty bounded declaration to
  name only package-declared, non-secret settings with no non-null manifest
  default, preserves the normalized exact key list for the selected form, and
  rejects unknown, secret, defaulted, or duplicate keys. It never opens a
  database, resolves a value, invokes package code, creates an endpoint, or
  changes enablement.
- Add-on administrator-form creation-registration acceptance runs before
  database creation. It requires a non-empty form schema plus closed creation
  metadata, then exactly one initial-value loader and one atomic creator for the
  declared form. The creator names one through eight package-owned transaction
  tables. Missing, duplicate, undeclared, empty-table, and reserved-table
  registrations fail closed. Exact handlers, table metadata, and package
  ownership enter only the in-memory request context; no callback is invoked,
  no transaction starts, and no endpoint or browser control exists.
- Add-on administrator-form initial-value acceptance runs before database
  creation. It requires the complete closed draft graph while allowing only
  required scalar fields to begin empty. Missing or extra keys, coercion,
  invalid select values, bounds, collection, node, and body-size violations
  fail closed, while the ordinary edit/save validator remains strict. The
  typed request exposes only exact tool/form identity and immutable runtime
  settings; deterministic draft state uses no synthetic record id. No provider,
  authorization, database, transaction, endpoint, or browser control is used.
- Add-on administrator-form create-submission acceptance runs before database
  creation and again inside the disposable current/initial-value lifecycle. It
  accepts only canonical JSON with exact tool/form identity, target-free initial
  state, and complete values; edit state, numeric targets, package identity,
  unknown keys, malformed hashes, and noncanonical bodies fail closed. Fresh
  permission, current draft/configuration state, manifest body limits, and
  strict required-field validation precede deterministic actor-bound plan
  evidence. Source and runtime checks prove the preparation helper has no
  creator lookup or invocation, transaction, record allocation, request global,
  or database write. A separate protected endpoint must authenticate and verify
  header CSRF before body I/O and delegate only the canonical body.
- Add-on administrator-form atomic-creation acceptance runs only in the
  uniquely named disposable current/initial-value lifecycle. It requires the
  exact actor/version/contract/configuration/values/table-bound plan, refuses a
  caller-owned transaction, serializes lifecycle/package/installation state,
  invokes one exact creator with a final typed request, accepts only one typed
  positive record id, reloads that id through the exact value-loader owner, and
  requires complete value equality before one value-free
  `addon.form.created` audit fact commits. Wrong plans, creator output,
  malformed results, partial writes, wrong postconditions, and provider
  failures roll back package and audit rows. Cleanup removes successful
  fixtures and proves zero disposable schema and grant artifacts. No request
  global, caller-selected id, writer fallback, or retained installation
  participates. The protected endpoint returns the new numeric target only
  after this lifecycle succeeds.
- Add-on administrator-form Create browser acceptance stages the clean core and
  Store Lite package only in a fresh disposable installation. Authenticated
  desktop Chrome opens the core-owned Add product draft, verifies installation
  currency plus unavailable/draft defaults, submits one complete simple product,
  reloads the allocated target, and then exercises the existing Save path.
  Mobile Chrome verifies the three-record catalog, responsive controls, and
  persisted values. The run requires zero console/page/network/HTTP errors,
  Store and core create/update activity facts, no horizontal overflow, unchanged
  variable-product data, exact primary fingerprint preservation, and zero
  disposable schema/grant/staged-package residue.
- Add-on setting-value acceptance runs before database creation. It requires
  exact normalized definitions, defaults matching the declared non-secret
  type, one closed object, strict scalar types, bounded UTF-8 text, declared
  selections, credential-free HTTP/HTTPS URLs, valid email addresses, exact
  missing-setting reporting, and separate opaque lowercase `config:` secret
  references. Unknown, nested, malformed, out-of-range, loosely coerced, raw
  secret-looking, or schema-invalid input returns no normalized configuration.
  It performs no database access, secret resolution, authorization, rendering,
  package execution, or lifecycle mutation.
- Add-on secret-reference availability acceptance runs before database
  creation. It requires exact bounded server-local declarations, deterministic
  local/environment merging, complete typed configuration revalidation,
  package/configuration/declaration-bound hashes, exact missing setting keys,
  stale or forged declaration refusal, and no reference identifier or secret
  value in returned evidence. It performs no database access, secret lookup,
  package execution, authorization, rendering, persistence, or lifecycle
  mutation.
- Add-on asset-plan acceptance runs before database creation. It requires one
  trusted manifest surface, package-owned CSS/JavaScript paths, exact
  type/location pairing, checksum-versioned namespaced URLs, sorted
  deterministic plan evidence, exact plan revalidation before tag rendering,
  and no partial output for invalid, duplicate, stale, or forged data. It
  performs no package-file read, HTTP delivery, response injection, database
  access, package execution, or lifecycle mutation.
- Add-on core-owned asset-injection acceptance runs only in the uniquely named
  disposable database and temporary first-party package. It requires current
  trusted catalog and enabled-registry reconciliation, both-surface validation
  for every enabled package, public-only versus administrator-overlay plans,
  escaped tag output at exactly one document boundary, no partial markup for
  forged, disabled, drifted, invalid, or ambiguous state, no planner registrar
  invocation or registry mutation, real anonymous and signed-in HTTP documents,
  and exact package/administrator/filesystem cleanup.
- Add-on setting storage/editor/preflight/atomic-writer/secret-replacement acceptance runs only in the uniquely
  named disposable database. It requires the exact empty generic schema and
  restrictive installation foreign key, explicit package-declared permission
  bindings, fresh case-sensitive grants, exact trusted package and registry
  identity, supported lifecycle state, complete typed target values, valid
  current rows, deterministic value-free hashes, next-decision revocation, no
  package execution, exact full replacement, and exact cleanup. Atomic
  replacement additionally requires shared locks, exact plan comparison,
  complete rows, separate ordinary/secret columns, server-local resolution,
  initial missing-secret binding, unavailable-reference refusal, exact
  postcondition reload, one value-free audit fact, no-op handling, and stale
  or injected failure refusal without mutation.
- Add-on secret-resolution acceptance runs before database creation. It requires
  bounded ignored-local and operating-system value sources, exact lowercase
  `config:` references, duplicate/conflict refusal, allowlist-required
  resolution, and a fixed result with no secret bytes. The helper performs no
  database access, request parsing, package execution, logging, rendering, or
  lifecycle change.
- Add-on setting-read-model acceptance runs only in the uniquely named
  disposable database and one temporary trusted package. It requires exact
  installed identity and supported lifecycle evidence, a declared permission
  for every setting, fresh per-setting binary grants, valid stored values, and
  deterministic authorized models. It proves stored/default/unset typed values
  appear only in the authorized non-secret subset; secret entries disclose only
  configured state; revocation, case changes, stored-value drift, and registry
  identity drift return no partial result; no reader call writes a row, audit,
  grant, or package marker; and all fixture rows/files are removed.
- Add-on component-editor renderer acceptance runs before database creation. It requires core-owned escaped markup for every fixed schema type, stable namespaced labels/help/errors, scoped border-box control sizing, no rejected-value reflection, fail-closed malformed state, and no form, submit control, authorization, package execution, package data access, or database state. Its revision-timeline checks additionally require exact value-free newest-first metadata, newest/current hash agreement, escaped actor and timestamp copy, fixed current/matching/restore-check-required states, responsive containment, no value or hash disclosure, no action markup, and fail-closed empty, stale, reordered, or value-bearing input.
- Add-on component-editor permission acceptance runs only in the uniquely named disposable database. It requires the full manifest permission capacity, exact operation mapping, explicit package grants independent of Owner/lifecycle authority, binary case matching, next-decision revocation, read-only state fingerprints, and zero residual administrator, role, or grant fixtures.
- Add-on administrator-tool form-preflight acceptance runs only in the uniquely named disposable database and an in-memory trusted runtime context. It requires one closed manifest form declaration, exact request-local display-tool ownership, fresh binary package permission independent of Owner/lifecycle authority, fixed POST/CSRF/JSON/body limits, deterministic actor-bound contract and plan hashes, contract-drift evidence, malformed and forged-contract refusal, next-decision revocation, zero package callback invocation or database mutation, and exact administrator, role, and grant cleanup. It reads no request body/global, consumes no CSRF token, renders no HTML, and creates no endpoint or enablement path.
- Add-on administrator-tool form-renderer acceptance runs before database creation. It requires the closed scalar and collection vocabulary, simple and variable-product coverage, option-group/value and variant/selection nesting, exact 3/16/128 collection bounds, the two-level and 200-field ceilings, escaped deterministic disabled markup, schema-sensitive contract evidence, and fail-closed absent, executable, duplicate, oversized, over-deep, or malformed schemas. It loads no current values, makes no permission decision, invokes no package callback, emits no form/name/submit control, and reads no request, CSRF, database, or endpoint state.
- Add-on administrator-tool form current/initial-value acceptance runs only in the uniquely named disposable database with one temporary package-owned InnoDB table and an in-memory trusted runtime context. It requires exact current and initial loader owners, fresh binary package permission independent of Owner/lifecycle authority, declared typed runtime settings, complete simple/variable current values, complete target-free draft values with blank required scalars, deterministic target/contract-bound current state and target-free contract/configuration-bound draft state, escaped disabled nameless current-value markup, next-decision revocation, invalid/extra/oversized value refusal, output/exception/buffer containment, unchanged package data, and exact administrator/grant/table cleanup. Neither provider receives actor or request/session data, the initial loader receives no target id, and the boundary resolves no creator and creates no editable control, request parser, CSRF operation, endpoint, transaction, or write.
- Add-on administrator-tool form JSON-adapter acceptance runs only in the uniquely named disposable database with one temporary package-owned InnoDB table and an in-memory trusted runtime context. It requires exact JSON transport and canonical length, a 256 KiB global ceiling, duplicate/unknown/mistyped/noncanonical root refusal, the exact tool/form/target/current-state/values root, fresh binary form permission, the manifest body limit before value loading, exact current-value reload, stale-state and contract-drift refusal, complete nested submitted-value validation, deterministic actor/contract/target/state/value-bound evidence, generic public redaction, next-decision revocation, unchanged package data, and exact administrator/grant/table cleanup. Source-order checks require endpoint authentication plus header CSRF before body I/O and prove the adapter helper has no request globals or writer registration. The endpoint remains unlinked and validation-only and invokes no writer.
- Add-on administrator-tool form-writer acceptance runs only in the uniquely named disposable database with one temporary package-owned table, enabled installation, explicit package grant, and in-memory trusted runtime context. It requires one optional exact writer owner, one to eight sorted declared package tables, reserved/duplicate/undeclared registration refusal, deterministic value-free validation/package-version/table/actor/target-bound write plans, caller-transaction refusal, lifecycle/package/installation serialization, locked full preparation, exact typed writer request, stale replay plus permission/version/contract drift refusal, unchanged no-op suppression, complete postcondition reload, and one value-free `addon.form.saved` audit fact committed with the package mutation. Forced output, exception, buffer, HTTP-state, false-return, partial-write, wrong-write, audit, and non-InnoDB failures must leave the package row exact. Source checks require no request globals and prove the validation endpoint does not load or invoke the writer. Cleanup removes the administrator, role, grant, installation, audit, constraint, and package table exactly.
- Add-on administrator-tool form Save-bridge acceptance runs only in the uniquely named disposable database with one temporary package-owned InnoDB table, enabled installation, explicit package grant, and in-memory trusted runtime context. It requires exact tool/form/canonical-positive-target edit identity, fresh permission and enabled-version checks, exact loader/writer/table ownership, complete current values, escaped core-only editable scalar and two-level collection markup, bounded add/remove templates, typed canonical JSON with header CSRF, an atomic value-free `saved` result, no-op `unchanged`, stale replay and next-decision revocation refusal, and exact administrator/role/grant/package/audit/table cleanup. Source and real HTTP checks require POST, database-backed session, and header CSRF before edit parsing or Save body I/O; public responses disclose no values, state/plan evidence, package identity, or tables. Local Chrome captures at 1280px and 390px verify no horizontal overflow, the collection interaction path, typed Save/reload states, and zero console, page, or failed-request errors.
- The Store Lite browser rehearsal is a separate opt-in cross-repository gate.
  It requires an integrity-valid Store Lite package outside the starter, all 45
  core migrations plus the exact five-package-migration inventory in a fresh
  schema, one acceptance-only enabled registry fixture, only the exact Products
  capability, a simple banana and bounded variable T-shirt, zero initial
  Product placements, the visible authenticated Add Content -> Product ->
  Create component -> Homepage -> Place component path, the resulting
  unauthenticated public fact card, and the visible authenticated Tools ->
  Products -> Add/Create and Edit/Save paths at 1280x900 and 390x844,
  exact persisted integer-money/stock/title changes, one package update event,
  one value-free core form-saved audit fact, an unchanged T-shirt graph, exact
  semantic price and availability facts with no overflow, zero
  console/page/request/HTTP/runtime errors, and exact server/schema/grant/staged-
  package cleanup with an unchanged retained-primary fingerprint. Final
  database evidence requires the exact package Product relationship, active
  homepage-only core fields, one core `create`, package `baseline`, core
  `move`, and value-free `component.public_placed` audit fact.
- Add-on component-editor data-loader acceptance runs only in the uniquely named disposable database and temporary first-party package. It requires exact declared registration, current view permission, enabled placement/runtime/manifest ownership, exact runtime-manifest identity, complete normalized returned values, a record-bound state hash, pre-invocation revocation/case/drift/disabled refusal, foreign and same-id forged-manifest refusal, invalid-value and output/exception/buffer containment, unchanged database fingerprints, and zero package, parent, administrator, grant, table, or filesystem fixtures.
- Add-on component-editor update acceptance runs only in the uniquely named disposable database and temporary first-party package. It requires a core-owned operational form with only the numeric parent id, current state hash, CSRF token, and schema fields; server-derived package/component identity; fresh exact view/edit grants; fail-closed rendering after revocation; declared-editor-only writer registration; one exact writer owner; closed package-table metadata; InnoDB refusal before invocation; enabled locked parent/runtime/manifest ownership; normalized values; exact saved-value reload; immutable core-owned baseline/save snapshots; bounded validated history metadata; deterministic read-only restore preflight; exact plan matching; atomic source-linked restore execution; unchanged no-op behavior; stale/revoked/drifted/disabled/forged/tampered refusal; rollback after update or restore revision-ledger failure, emitted output, exceptions, nested buffers, false returns, and incomplete writes; unchanged core placement state; exact restored target state and revision timeline; and zero package, parent, revision, administrator, grant, table, or filesystem fixtures.
- Add-on component-creation preflight/runner, parent-metadata, public-placement preflight/runner, and atomic-delete acceptance runs only in the uniquely named disposable database. It requires declared-editor-only creator/deleter registration, exact create/view/edit/delete/publish permissions, enabled manifest and runtime component/loader/creator/deleter ownership, closed package-table metadata, InnoDB refusal before callback invocation, an unused numeric record id, one active-theme layout, normalized parent and package values, an inactive hidden unrouted parent plan, deterministic hashing, disabled/revoked/mismatched/invalid/existing-record refusal, and zero creator/deleter invocation during preflight. The atomic creation runner requires exact plan matching under lifecycle/theme/installation serialization, rejects caller-owned transactions, contains creator/loader failures, verifies exact parent and package postconditions, commits one core `create` and one package `baseline` revision, refuses reuse, and rolls back output/exception/nested-buffer/false/partial-write and both forced-ledger failures. Parent state and writes require fresh exact view/edit grants, enabled binding, inactive shell and current revision evidence, stale-state refusal, title/layout/language-only mutation, unchanged package data, one core `save` revision, no-op suppression, and rollback on forced ledger failure. Public-placement planning binds exact parent/package state to either the unique active language homepage or one unique active Article route, requires language agreement and active-theme position support, derives closed destination-specific placement values, and proves deterministic zero-write behavior with revoked, stale, cross-language, inactive-target, and unsupported-position refusals. Its atomic runner revalidates under lifecycle/theme/installation/source/destination locks, refuses caller transactions, revoked grants, destination drift, and plan reuse, changes only the four homepage or seven Article parent fields, preserves package and destination state, commits one core `move` revision and value-free placement audit, and rolls back revision or audit failure. Delete planning binds exact parent/package hashes, the latest validated package revision, declared InnoDB tables, and deterministic value-free evidence without invoking the deleter or writing state. The atomic delete runner revalidates under lifecycle/theme/installation/parent locks, contains the deleter, rejects partial deletion, records both final `delete` snapshots, removes package/SEO/parent rows together, retains both ledgers, refuses reuse, and rolls back callback or ledger failures. Exact cleanup leaves zero administrator, grant, package, parent, revision, SEO, or table fixtures.
- Add-on request-bootstrap acceptance runs only in the uniquely named disposable database and uses temporary first-party packages outside the clean starter. It proves uninstalled and disabled packages never execute, enabled dependencies register first, exact handlers and owners remain lookup-only, lifecycle CLIs do not request-load packages, bootstrap writes no registry or audit state, drift and missing dependencies/code fail before execution, and every package/database/filesystem fixture is removed.
- Add-on runtime-secret acceptance runs before database creation and in the
  uniquely named disposable database. The dependency-free fixture proves the
  private package-bound access object, by-reference lookup, serialization and
  debug redaction, safe service results, secret-disclosure refusal, and bounded
  nested-data scanning. The disposable fixture proves the
  `registration_only_service_with_secrets` profile: complete per-client
  settings and server-local values are required, missing configuration blocks
  before registrar execution, Owner preflight evidence is value-free, atomic
  enablement succeeds only after exact revalidation, the service receives only
  its own resolved setting, no secret appears in serialized output, and all
  package, setting, administrator, grant, environment, and database fixtures
  are removed.
- Add-on install acceptance runs only in the uniquely named disposable database and uses a temporary validated first-party fixture outside the clean starter. It proves exact Owner authorization and dependency state, stale-plan and audit fail-closed behavior before SQL, resumable partial DDL, immutable migration evidence, bounded audit data, disabled/unloaded completion, local-only confirmations, and zero residual package, SQL, authorization, audit, or code-execution artifacts.
- Add-on enablement-preflight acceptance runs only in the uniquely named disposable database and uses temporary validated packages outside the clean starter. It requires exact Owner `addons.enable` authority, exact installed-disabled/current registry evidence, deterministic client-bound plans, required enabled dependencies, capability and route conflict reporting, registration-only service, secret-capable registration-only service with value-free readiness evidence, core-rendered default component, and combined default-component plus registration-only-service profiles that clear their declarative gates, exact richer-surface theme/settings/live-data/component-editor blockers, no apply path, identical pre/post registry and authorization fingerprints, no package execution during preflight, and exact cleanup.
- Public-mutation live-data-preflight acceptance runs only in the uniquely named disposable database and uses one temporary trusted installed-disabled package outside the clean starter. It requires exact Owner enable authority, a current closed declaration, applied package migration evidence, declared InnoDB tables, complete typed settings, opaque secret-reference availability, and exact core subject/CSRF/rate-limit/idempotency/execution storage; it proves deterministic value-free hashes/counts, missing-table/setting/secret and unsupported-engine blockers, no table or reference disclosure, forged-plan refusal, zero request/transaction/runtime-registration/package-execution path, unchanged preflight state, and exact cleanup. It has no enable or dispatch path.
- Public-mutation rate-limit acceptance runs only in the uniquely named disposable database and creates no package fixture. It requires exact InnoDB storage, a hash-only client/declaration/subject scope, an enforced 12-per-60-second fixed window, bounded collision/contention handling, caller-owned transaction refusal, bounded expiry cleanup, subject cascade cleanup, and no request-global, browser-response, package-load, or runtime-registration path. It has no HTTP, package-data, or enablement path.
- Public-mutation idempotency-key acceptance runs only in the uniquely named disposable database and creates no package fixture. It requires exact InnoDB storage, a hash-only client/declaration/subject scope, a 10-minute core-issued opaque key, correct subject/scope resolution, no raw-key persistence, caller-owned transaction refusal, bounded expiry cleanup, subject cascade cleanup, and no request-global, browser-response, package-load, or runtime-registration path. The resolver itself remains non-consuming; the separate internal runner is its only consumer. It has no HTTP, package-data, or enablement path.
- Atomic public-mutation runner acceptance runs only in the uniquely named disposable database with one temporary InnoDB table and in-memory trusted runtime context. It proves one declared handler/state-loader binding, typed field refusal, CSRF-before-rate ordering, exact replay/conflict outcomes, fixed-rate ordering, server-derived postconditions, keyed replay evidence, a value-free anonymous audit fact, contained output/exception/rollback failures, and exact database/runtime/constraint/table cleanup. It creates no package files, browser state, dispatcher, response, route execution, enablement profile, Store Lite data, or client artifact.
- Public-mutation dispatcher acceptance runs before database creation and uses
  only explicit transport fixtures plus an in-memory registrar context. It
  proves one closed dispatcher/capture result, runtime-unavailable behavior,
  non-POST refusal, missing-attestation refusal, incomplete-binding refusal,
  and zero package callback or HTTP-state changes. The dispatcher remains
  unlinked from `index.php`; the supported-server disposable rehearsal is
  complete. The core subject-cookie lifecycle is now proven independently;
  the non-executing deployment profile and response-owner composition are
  also proven; production deployment review remains required before linking
  it.
- Public-mutation deployment-profile acceptance is dependency-free and creates
  no database, package, request, browser, route, or client fixture. It accepts
  only one non-secret operator review packet with a separate client database,
  canonical HTTPS origin, pinned FrankenPHP/Caddy versions, fixed
  HMAC/trusted-origin sources, attestation-before-PHP route order, core
  response/cookie ownership, host-only cookie policy, explicit isolation, and
  disabled activation flags. It rejects starter-database reuse, request-
  derived trust, version/route/policy drift, secret-shaped fields, and any
  dispatcher/package/Store Lite activation without loading a profile or
  changing response, filesystem, database, or client state.
- Public-mutation deployment-review acceptance is dependency-free and creates
  no database, package, request, browser, route, or client fixture. It binds
  the profile hash to pinned server/TLS/proxy facts, non-secret Caddyfile/
  binary/certificate hashes outside the starter, process-environment
  trusted-origin/HMAC sources with verified old-key revocation, and bounded
  desktop/mobile HTTPS browser evidence. It rejects secret values, unreviewed
  proxy/TLS/rotation/browser facts, forged review hashes, file loading,
  browser sessions, deployment, response emission, and dispatcher linking.
- Public-mutation installation-shaped HTTPS rehearsal acceptance is a Docker-
  and browser-dependent gate. It requires a temporary custom FrankenPHP/Caddy
  binary, explicit TLS certificate files outside the starter, process-
  environment trusted-origin/HMAC values, attestation-before-`php_server`
  ordering, a restart-based old-key absence proof, and fixed `1440x1000` /
  `390x844` HTTPS evidence. It must retain only non-secret hashes and boolean
  evidence outside the starter and remove the private key, secret values,
  image, container, and build context. It cannot touch a client database,
  install a package, link the dispatcher, or exercise Store Lite.
- Public-mutation response acceptance is dependency-free and creates no
  database, package, request, browser, route, or client fixture. It requires
  only the fixed `accepted` / `unchanged` envelopes and generic invalid-request,
  method-not-allowed, request-conflict, rate-limited, and temporary-unavailable
  refusals; exact JSON and no-store/nosniff/content-type/length/POST-allow
  headers; replay redaction; forged-envelope refusal; and no request-global,
  cookie/session, database, package-execution, response-emission, or lifecycle
  path.
- Public-mutation declared-form acceptance is dependency-free and creates no
  database, package, request, browser, route, or client fixture. It accepts
  only a validated normalized declaration plus canonical raw URL-encoded package
  fields, returns only sorted typed scalar fields, and refuses duplicate,
  nested, unknown, noncanonical, malformed, missing, or oversized input with
  no partial values. It has no PHP request-global, cookie/session, database,
  runtime/package-load, response-emission, endpoint, or lifecycle path.
- Public-mutation form UI acceptance is dependency-free and creates no
  database, package, request, browser, route, or client fixture. It derives the
  action and controls only from one declaration, requires same-subject issued
  CSRF/idempotency shapes, escapes simple/variant labels and options, and
  refuses unknown commercial fields, malformed controls, invalid selections,
  cross-subject evidence, and tampered models without partial markup or token
  output. It has no request-global, database, package-load, emission,
  controller, front-controller, or lifecycle path.
- Public-component mutation-presentation acceptance is dependency-free and
  uses only one in-memory registrar fixture. It accepts exact simple and
  variable-product descriptions, retains 128 choices and refuses 129, rejects
  reserved commercial/authority keys, extra values, duplicate fields/options,
  unsafe labels, and forged selections, and replaces an invalid combined view
  with the static unavailable fallback. It additionally proves the current
  renderer emits existing product facts but no form, action, field value,
  token, controller, endpoint, response state, package markup, or client data.
- Public-component form-integration acceptance runs only in the uniquely named
  disposable current-schema database with an in-memory registrar fixture. It
  requires exact component/route/mutation/state-loader ownership, a
  placement-derived instance, silent core-rendered simple and variable forms,
  issued then resolved same-subject evidence, subject-cookie exclusion from
  markup, display-only zero-write behavior, malformed/foreign ownership
  refusal before issuance, tampered-markup refusal, and zero package callback
  invocation. Cleanup leaves zero subject, CSRF, idempotency, schema, or grant
  residue and the helper has no request-global, emission, package-load, or
  endpoint path.
- Public-mutation browser-controller source acceptance is dependency-free. It
  requires the exact form/status selectors, fixed evidence/content headers,
  same-origin/no-store/redirect-error fetch policy, one WeakMap-held frozen
  command body, immediate DOM evidence removal, no cookie/storage/log/dynamic-
  code/HTML-sink/external-URL path, and continued absence from the front
  controller and theme adapters. The separate Playwright self-test uses an
  intercepted local origin and real Chrome at `1440x1000` and `390x844`; it
  proves canonical body/header delivery, accepted completion, exact-body retry,
  conflict closure, invalid foreign configuration refusal, DOM evidence
  removal, frozen controls, generic status copy, and zero page errors.
- Public-mutation HTTP request-envelope acceptance is dependency-free and
  creates no database, package, request-global, browser, route, or client
  fixture. It accepts only one validated declaration, configured canonical HTTPS
  origin, exact static POST target, bounded raw form body, and complete header
  list; it rejects origin, path, method, content metadata/length, framing,
  duplicate headers, opaque subject-cookie, CSRF, and idempotency drift without
  releasing partial raw values. It does not parse package fields, issue/resolve
  browser evidence, access runtime/package code, emit a response, or create an
  endpoint, session, lifecycle, enablement, Store Lite, or client-state path.
- Public-mutation static route-selector acceptance is dependency-free and uses
  only in-memory registrar fixtures. It proves exact un-decoded static-path
  selection, query-bearing known-path reservation for later normalization,
  required route/mutation/state-loader ownership, zero callback invocation,
  and fail-closed ambiguous or incomplete binding refusal. It creates no
  request-global adapter, database, package file, front-controller claim,
  endpoint, response, browser state, lifecycle, enablement, Store Lite, or
  client-state path.
- Public-mutation server request-facts adapter acceptance is dependency-free
  and creates no database, package, request, browser, route, or client
  fixture. It requires a canonical HTTPS origin from operating-system/local
  configuration only, ignores poisoned `Host`/request-projected server values,
  preserves one explicit complete ordered security-header capture and raw body,
  rejects associative/incomplete/reordered/duplicate header captures and
  malformed transport facts, and changes no request/session/response state.
  It has no generic PHP-header fallback, body-stream reader, package/runtime
  path, front-controller claim, endpoint, response emission, browser cookie,
  lifecycle, enablement, Store Lite, or client-state path.
- Optional FrankenPHP ingress-verifier acceptance is dependency-free and
  creates no database, package, browser, route, or client fixture. It accepts
  only a fixed HMAC-signed Caddy capture from one process-environment key,
  rechecks the current method/raw target and body length/SHA-256 before it
  hands explicit facts to the existing adapter, and refuses forged signatures,
  tokens, bodies, targets, duplicate/forbidden signed headers, or invalid key
  shape without returning partial facts. One fixed Go/Caddy JSON-and-HMAC
  fixture proves PHP verifier compatibility. Its invalid-current-capture path
  reads no body and changes no request/session/response/buffer state. It has no front
  controller, endpoint, cookie, package/runtime, database, lifecycle,
  enablement, Store Lite, or client-state path. The separate
  `scripts/frankenphp-public-mutation-ingress-self-test.sh` Go gate verifies
  Caddy header stripping, bounded attestation, downstream body preservation,
  duplicate/encoded/unknown/oversized withholding, and no handler response;
  it is intentionally not part of the PHP/MySQL suite because it requires Go.
  The separate Docker-only
  `scripts/frankenphp-public-mutation-custom-binary-proof.sh` builds a
  temporary FrankenPHP/Caddy binary, confirms module registration and
  Caddyfile adaptation, and sends bounded requests through Caddy and the real
  unlinked PHP verifier. It proves valid body preservation, spoofed-header
  replacement, and spoofed/duplicate/encoded withholding without a database,
  package, browser, endpoint, or client fixture. The workflow runs it for the
  relevant pull requests; it remains a generic proof rather than a client
  deployment or dispatcher authorization.
- The separate Docker-only
  `scripts/frankenphp-public-mutation-dispatch-proof.sh` stages only reviewed
  core helpers plus the operational endpoint bridge and a disposable fixture
  front controller, builds the same pinned
  FrankenPHP/Caddy binary, adds `mysqli` only to the proof image, and exercises
  the complete attested dispatcher path against a fresh MySQL database. It
  proves accepted/replay/refusal/conflict behavior and exact execution,
  activity, subject, CSRF, idempotency, and rate-limit evidence. Its temporary
  bootstrap also proves real HTTP subject-cookie issuance, resolve-without-
  reissue, rotation with fixed deletion plus replacement, old-token refusal,
  and clearance. Its endpoint, bootstrap secret, package marker, database,
  image, network, and context are removed on success or failure; it does not
  alter the default server, a client installation, production browser state,
  enablement, or Store Lite.
- Public-mutation endpoint acceptance is dependency-free and creates no
  database, package, browser, client, or server fixture. Its 10 assertions
  require the conjunctive enablement/origin/HMAC gate, reserved unencoded
  namespace candidacy, non-POST selection behavior, generic disabled and
  malformed refusal, exact dispatcher composition, closed-result validation,
  and no response emission during pure dispatch tests.
- Public-mutation subject-cookie emitter acceptance is dependency-free. Its 4
  assertions prove exact issuance, resolve-without-header, clearance, and
  clear-before-set rotation values from the already validated lifecycle, while
  refusing invalid input. Package and theme code cannot supply header names,
  attributes, or arbitrary cookie values.
- Public-component form-integration acceptance runs only against a freshly
  migrated disposable database. Its 17 assertions now also require duplicate
  raw-cookie refusal without evidence, one issued subject and one controller
  delivery for the first accepted page form, same-subject reuse for later
  forms, strict request-local coordinator validation, display-only fallback,
  and exact evidence/schema/grant cleanup.
- Public-mutation response-emitter acceptance is dependency-free and creates no
  database, package, request, browser, route, or client fixture. It accepts
  only exact fixed response-contract envelopes, proves the closed no-store/
  nosniff JSON header vocabulary, emits only the matching fixed bytes and
  status through an output buffer, and refuses forged headers, length drift, or
  unreviewed statuses before changing response state. It has no request-global,
  browser-cookie, session, database/runtime/package, front-controller, route,
  endpoint, lifecycle, enablement, Store Lite, or client-state path.
- Public-mutation response-owner acceptance is dependency-free and creates no
  database, package, request, browser, route, or client fixture. It requires a
  valid non-executing deployment profile and exact core response envelope,
  composes only fixed lifecycle cookie descriptors, proves issuance, clear,
  resolve, and ordered rotation, and rejects arbitrary headers, package/theme
  ownership, linked-dispatcher profiles, cookie-policy drift, malformed
  lifecycle state, body token leakage, and any response/global/session state
  change. It remains non-emitting and unlinked from the front controller.
- Public-mutation subject-cookie serialization acceptance is dependency-free
  and creates no database, package, request, browser, route, or client
  fixture. It accepts only the exact core-issued descriptor shape and produces
  one fixed host-only future cookie value with a 30-minute `Max-Age`,
  `Path=/`, `Secure`, `HttpOnly`, and `SameSite=Strict`, no `Domain` or
  `Expires`; it refuses forged descriptors, policy drift, token drift, domain
  injection, and max-age drift. It emits no header/cookie and changes no
  request/session/response/buffer state. The separate disposable lifecycle
  fixture owns database-backed ensure/clear/rotate proof. The serializer remains
  pure even though the separate core emitter and gated front-controller bridge
  now consume its validated lifecycle; no client endpoint, package enablement,
  Store Lite data, or client state is created by these fixtures.
- Atomic add-on enablement acceptance runs only in the uniquely named disposable database and uses temporary validated first-party packages outside the clean starter. It requires exact Owner authority, a stale-plan refusal before execution, registrar-failure refusal, audit and post-state-update injected-failure rollback, atomic enabled-state and bounded-audit commits for all three constrained profiles, lifecycle reach from standalone and combined default components to the safe core renderer, later runtime registration of every combined-package component and service identifier, repeat refusal, CLI-only confirmations, and exact cleanup.
- Atomic add-on disablement acceptance runs only in the uniquely named disposable database and uses temporary validated first-party packages outside the clean starter. It requires exact Owner `addons.disable` authority, deterministic current-registry evidence, an exact enabled-dependent blocker, database-wide lifecycle-lock exclusion across connections, stale-plan refusal, audit and post-state-update injected-failure rollback, an atomic `installed_disabled` state and bounded audit commit, zero registrar or migration execution, exclusion of both combined-package component and service registrations from later request bootstrap, dependent-first unblocking, repeat refusal, CLI-only confirmations, and exact cleanup.
- A full-table checksum comparison makes HTTP 403 alone insufficient: every allowed/denied permission request must also leave all 34 tables unchanged.
- The Move Content lifecycle requires one valid browser-parsable tool form, exact source/destination placement changes, real protected endpoint responses, matching public rendering after each move, destination-layout refusal for undeclared positions, and transaction-preserved state after refusal. Moving between contexts clears only the source position column; unrelated placements remain intact.
- The Section-delete lifecycle uses a disposable Webmaster, Section, and two Articles only inside the disposable database. It requires count-aware confirmation, CSRF refusal with unchanged state, one transaction that archives every related Article before deleting the Section, exact response reporting, recovery through **Inactive Articles**, an active-theme 404 at the old route, and zero targeted artifacts. Form/Gallery child rows and media are deliberately left attached to their preserved parent Articles.
- The Article lifecycle uses a hard-coded disposable Webmaster, Article ID, aliases, and body markers only inside the disposable database. Create, update, and delete must pass through the real protected CSRF endpoints; direct SQL is used only to install and remove the temporary administrator and as a cleanup backstop.
- Article acceptance requires a long-name upload before metadata, a complete inactive hidden placeholder, safe promotion to a renderable position on save, a second long-name edit upload, matching source/stored/served hashes, exact saved values, both filenames in protected editors, registered component/section/layout relationships, route-specific public markers, canonical count restoration, an unchanged pre-existing Article media manifest, and zero targeted Article/admin/throttle/activity/file artifacts.
- The Form lifecycle uses Contact subtype `#102` so it can verify the two-table parent/child transaction without creating a registration table or uploading files. Its definition deliberately omits a trailing empty-row delimiter so the legacy public parser renders without undefined-key warnings.
- Form acceptance requires exact parent and child values, numeric `RefID` coupling, registered subtype/section/layout relationships, paired editor IDs and CSRF, generated public fields, canonical count restoration, and zero targeted Form/Article/admin/throttle/activity artifacts.
- The Gallery lifecycle uses Video subtype `#107` so it can verify the two-table parent/child transaction and public component renderer without creating image files or contacting an external video service.
- Gallery acceptance requires exact parent and child values, numeric `RefID` coupling, registered subtype/section/layout relationships, paired editor IDs and CSRF, generated YouTube embed markers, canonical count restoration, and zero targeted Gallery/Article/admin/throttle/activity artifacts.
- The upload lifecycle uses Gallery subtype `#106` and a database-specific filename under `images/gallery`. It refuses to overwrite any existing path and will remove only the exact regular file whose name is returned by the protected upload endpoint.
- Upload acceptance requires a valid generated PNG, matching source/stored/served hashes, exact database persistence, protected-editor and public-route markers, paired metadata deletion, zero targeted database/file artifacts, and an unchanged manifest for every pre-existing Gallery image.
- The forced-rollback lifecycle uses hard-coded disposable IDs and Video subtype `#107`. Its trigger is created by the administrative test connection only after schema comparison and exists only in the uniquely named disposable database; the protected endpoint still runs under the normal application account.
- Rollback acceptance requires the legacy `no` response, removal of the trigger, identical pre/post checksums across all 34 tables, exact initial parent/child values, a successful control update after trigger removal, canonical count restoration, and zero targeted database/trigger artifacts.
- Cleanup failure converts an otherwise successful run into a failure.
- Cleanup re-reads the application account's grants after revoke/drop and fails if the disposable database grant is still present.
- The primary database is re-read after cleanup and must match its starting isolation snapshot.

The acceptance suite does not alter production or replace the separate backup, deployment, migration, and rollback procedures required for HostGator.
