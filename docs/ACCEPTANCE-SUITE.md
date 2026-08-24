# RED-CMS Local Acceptance Suite

Date: 2026-08-15

## Purpose

`scripts/dev-acceptance.sh` creates and removes a disposable local database to prove that the checked-in installer and every migration still produce the expected RED-CMS schema.

This is a local development and controlled staging tool. It is not a HostGator deployment script and should not be uploaded into or run from the public web root.

`scripts/store-lite-upgrade-rehearsal.sh` is the separate opt-in real-package
upgrade gate. It extracts Store Lite 0.1.28 and merged 0.1.29 from their exact
external Git commits, stages both payloads separately, and creates one bounded
disposable database and scoped application grant. After all 46 core migrations
and all eight historical package migrations, its 14 assertions preserve five
settings and one real order, force the second of two append-only order-list
index migrations to fail, require non-loadable `upgrade_failed` with the old
identity, resume only that remaining migration, and finish 0.1.29 disabled.
Cleanup requires database, grant, and staged-project counts of zero plus an
unchanged configured-primary boundary. It never enables the package, deploys
the hosted demo, or touches a client installation.

`scripts/store-lite-two-client-isolation-rehearsal.sh` is the separate opt-in
Release C3 isolation gate. It requires the clean external Store Lite 0.1.29
repository at merged revision
`96ae2b2986b6805b33b44f21cf454bd18a67a470`, stages clean core plus the package,
and creates two fresh databases with separate scoped grants. Its 14 assertions
install all 46 core and 10 package migrations in both clients, enable both with
exact registrar evidence, retain distinct USD/pickup and COP/delivery settings
and products, prove product mutation isolation in both directions, and prove
one client's disable/re-enable lifecycle never unloads the other. Cleanup
requires `databases:0 grants:0 staged-project:0 primary:unchanged`; primary
protection uses a schema-neutral full database dump hash and does not migrate
the configured primary. The gate does not deploy the hosted demo or use client
data.

The hosted Store Lite closeout is a deployment review, not a replacement for
disposable acceptance. On 2026-08-15, read-only desktop and 390-by-844 browser
inspection of `demo.red-sphere.com` confirmed RED-CMS 5.1.0, nine published
products and Add-to-cart controls, the exact nine-choice Size/Color scarf,
Product and Cart authoring, Products and Orders tools, the Checkout empty-cart
state, no horizontal overflow, and no browser warnings or errors. The earlier
same-day hosted mutation session covered a simple product, an exact scarf
variant, quantity recalculation, pickup, delivery, and pay on receipt. No new
hosted order or guest personal data was submitted during closeout. The complete
scope and isolation decision are recorded in
[`STORE-LITE-DEMO-CLOSEOUT-20260815.md`](STORE-LITE-DEMO-CLOSEOUT-20260815.md).

`scripts/store-lite-browser-rehearsal.sh` is the separate opt-in integration
gate for the externally distributed Store Lite package. It stages a temporary
clean core plus package, uses one uniquely named disposable schema and scoped
grant, applies core and package migrations, creates only an acceptance fixture,
and drives desktop/mobile Chrome through public homepage Product and empty Cart
checks plus authenticated Product/Cart component creation/placement and Products
Add/Create and existing-target Edit/Save/reload. It then verifies exact package
and core revision/audit facts, unchanged variable-product state,
zero browser/runtime errors, the
configured primary fingerprint, and removal of the temporary server, schema,
grant, and staged package. Its screenshots and JSON report remain in the
printed non-secret temporary evidence directory. This gate does not make Store
Lite part of the starter or authorize normal richer-package enablement.

`scripts/store-lite-public-mutation-rehearsal.sh` is the separate Docker-only
supported-server Cart and guest-checkout integration. It requires the clean
external Store Lite 0.1.29 repository at merged revision
`96ae2b2986b6805b33b44f21cf454bd18a67a470`,
stages no retained add-on or
local configuration, builds the reviewed custom FrankenPHP/Caddy attestation
module, and creates fresh MySQL, HTTPS certificate, HMAC key, and trusted-origin
state. It first reruns the established administrator/component browser path,
then drives the real public Product Add-to-cart, Cart quantity/removal, and
pickup/delivery guest-checkout forms in desktop/mobile Chrome. Accepted and
unchanged commands announce a generic result before the fixed controller
refreshes only the current page.
Acceptance requires one host-only Secure/HttpOnly/Strict subject cookie,
core-issued CSRF/idempotency evidence, accepted mutation, exact retry replay,
changed-command conflict, invalid-quantity refusal, accessible controls/status,
one verified line handle, recalculated totals, a closed twelve-field checkout,
runtime-ready fulfillment/payment choices, immutable server-derived order and
line snapshots, return to empty Cart, no overflow or browser errors, exact
Store Lite/core database facts, and removal
of every temporary container, network, image, database, certificate, secret,
and staged project. Only non-secret screenshots and JSON reports remain in the
printed external temporary evidence directory. It does not change the hosted
demo or authorize a client deployment.

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
The separate 19-assertion public-mutation declaration-preflight fixture proves
that one closed static POST/CSRF declaration canonicalizes deterministically
into value-free evidence while declaration absence, route drift/placeholders,
executable metadata, reserved request names, weak policy, core-table claims,
malformed identities, forged hashes, and a seventeenth request field or
transaction table fail closed. It also proves the helper
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
The separate Store Lite Stripe Checkout P2 contract fixture runs alongside
those pure Store Lite checks before any disposable database is created. It
accepts only a selected-client immutable USD order snapshot and derives a
closed hosted-checkout plan; validates only an opaque session reference and
canonical Stripe HTTPS redirect; requires an already-verified raw-body boundary
before parsed event normalization; refuses configuration, client, provider,
order, amount, currency, timestamp, replay, refund/reversal, and browser-return
shortcuts; and returns no order transition. It has no database, filesystem,
request-global, package, lifecycle, provider SDK, credential, or network path.
The separate 26-assertion P3A-1 payment-adapter profile fixture validates one
closed adapter package, one exact Store Lite dependency, two secret-reference
declarations, bounded null-default ordinary settings and migrations, one
`server-signature` POST route, and only `api.stripe.com`. It proves that the
route is not a browser public mutation, existing activation profiles still
reject the adapter, all richer or mismatched surfaces fail closed, the profile
hash is deterministic, and no package, secret, database, request, route,
runtime, or network execution occurs.
The separate 18-assertion P3A-2/P3A-3 disposable-database fixture requires one
persisted Owner with the exact enable grant, one current installed-disabled
adapter, the exact current enabled Store Lite dependency in the same selected
database, immutable migration-ledger agreement, and one migration-touched
InnoDB table. It proves deterministic count/hash evidence and zero writes or
package execution; refuses missing Owner authority, disabled Store Lite,
ledger drift, a missing table, a non-InnoDB table, and tampered evidence; and
then refreshes that evidence and validates exactly one adapter plus one route
registration. It proves both callbacks remain uninvoked and the database
fingerprint remains unchanged before removing the administrator, role, grant,
registry rows, table, package files, database, and database grant exactly. The
separate 13-assertion dependency-free P3A-3 fixture proves invalid evidence
fails before package loading; exact evidence is deterministic and value-free;
callbacks are never returned or invoked; entrypoint/registrar output, missing
or duplicate registrations, and tampered evidence fail closed without output
leakage; and the core validator contains no request, setting, secret, network,
or handler-invocation path.
The separate 26-assertion dependency-free P3A-4 fixture binds that exact
registrar evidence to one closed static `POST` ingress shape. It proves
deterministic readiness, exact canonical header/body/receipt capture, exact
unmodified by-reference verification material, and value-free JSON, debug,
object-cast, and hash evidence. It proves cloning and serialization refusal;
accepts opaque invalid JSON and an opaque signature only to demonstrate that
core performs neither parsing nor provider verification; and refuses tampered
plans, wrong methods, query targets, missing/reordered/extra headers,
noncanonical content metadata, mismatched/empty/oversized bodies, control bytes,
and invalid receipt time. Both registered callbacks remain uninvoked, and a
source check proves the helper has no PHP-global request, JSON parser,
database, secret, network, or handler path. This does not expose an endpoint or
perform enablement itself.
The separate 24-assertion P3A-5 disposable-database fixture composes fresh
P3A-2 database, P3A-3 registrar, P3A-4 ingress, exact stored-setting, and
value-free opaque-secret-availability evidence. It proves deterministic dry
runs with no database change or handler marker; refuses missing declarations,
tampered evidence, stale plans, configuration drift, revoked authority, and a
disabled Store Lite dependency; rolls back audit and injected post-state
failures; atomically commits exactly one enabled state and bounded audit fact;
refuses replay; enforces the CLI-only exact target/plan/nonzero-backup guards;
and removes the administrator, grants, settings, registry rows, audit, table,
package files, handler markers, disposable database, and schema grant exactly.
Source checks prove the runner has no request-global, raw-body, provider,
secret-resolution, response, network, or handler-invocation path.
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
The separate 17-assertion rich-field fixture proves twelve mixed declared
fields, the sixteen-field ceiling, closed string formats, component-model
normalization, declaration-order composition, escaped text/email/telephone/
textarea markup, select-backed conditions, canonical Unicode/space/empty-value
decoding, independent execution revalidation, typed-command preservation of
kebab-case and optional empty values, rejection of the nested service grammar,
and uniform no-partial-data refusal. It creates no database, package
installation, route activation, or client state.
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
The separate 41-assertion non-executing deployment-profile fixture proves the
closed attested and direct client packet shapes with canonical HTTPS, pinned
server versions, profile-specific trust and route order, core response/cookie
ownership, host-only cookie policy, clean-starter isolation, safe shared-host
database naming, secret-shaped-field refusal, and disabled activation flags. It reads no
request, filesystem, database, package, secret, or response state.
The separate 22-assertion direct-ingress fixture proves the explicit
shared-host profile accepts only direct server-owned HTTPS and a fixed bounded
PHP projection, ignores Host/forwarded values, rejects malformed combined
origin/cookie/CSRF/idempotency evidence, alternate content metadata,
transfer/content encodings, foreign methods/paths, and declared/actual body
length drift, and changes no request, session, buffer, or response state. The
endpoint fixture separately proves `direct_php` is explicit, needs no
attestation key, disables page forms over HTTP, and leaves
`frankenphp_attested` as the default. These dependency-free checks do not prove
a particular shared host. The separate real Apache/FastCGI proof establishes
the reviewed local projection; the hosted environment remains a separate gate.
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
The separate 27-assertion deployment-review fixture proves that a profile hash
binds only the matching non-secret attested or direct server/artifact/trust
facts and fixed desktop/mobile browser evidence. It rejects secret values,
artifact placement in the starter, request-derived trust, unreviewed rotation
or direct projection, browser errors/state changes, forged review hashes, and
any deployment or dispatcher path.
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
The separate 13-assertion operational enablement-preflight fixture composes
that evidence for every mutation in one bounded operational content package.
It requires exact component/editor and administrator tool/contract coverage,
bounded forms referencing declared tools, current migrations, complete
non-secret settings, one-to-one public POST routes/mutations, InnoDB package
tables, deterministic hashes, and an installed-disabled registry state. Secret
or network surfaces, missing settings, unsupported engines, incomplete
coverage, and forged counts fail closed. The fixture proves no package
entrypoint, registrar, request, runtime, transaction, lifecycle, or audit path
and leaves atomic enablement explicitly unimplemented before exact
package/table/administrator/filesystem cleanup.
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
test setup only. A separate 15-assertion fixture now proves the operational
server-local Owner grant/revoke workflow, atomic audit, stale-plan refusal,
non-executing discovery, and exact cleanup.
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
The separate 113-assertion destination/creation/parent-metadata/public-placement/delete fixture
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
of either revision ledger. Its destination execution extension proves typed
preview rederivation, atomic route/revision/audit/checkpoint creation, exact
route replay, and stale preview/plan refusal. It then forces the separately
committed inactive component to survive a failed component checkpoint, proves
the ledger remains at `route_created`, and retries to `component_created` with
the same route and exactly one parent row, package row, core `create` revision,
package `baseline` revision, creator invocation, and composite state hash.
Exact component replay invokes no creator, while changed package values or
parent/revision drift fail closed. The parent-metadata extension requires a fresh
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
8. Run migrations again and require `No pending migrations.` plus zero checksum drift. Then run the 16-assertion Owner authorization lifecycle, 11-assertion component-editor package-permission lifecycle, 16-assertion administrator-tool form-preflight lifecycle, 20-assertion bounded component data-loader lifecycle, 47-assertion operational form, transactional component-update, history, restore-preflight, and atomic-restore lifecycle, 113-assertion destination/preflight/checkpoint/route-stage/component-stage/component-creation/parent-metadata/public-placement/atomic-delete lifecycle, 23-assertion add-on registry and immutable asset-delivery-preflight lifecycle, 8-assertion static immutable asset-endpoint lifecycle, 11-assertion request-bootstrap lifecycle, 20-assertion safe component-persistence/dispatch lifecycle, 19-assertion install lifecycle, 23-assertion read-only enablement-preflight lifecycle, 23-assertion atomic-enable lifecycle, and 18-assertion atomic-disable lifecycle. Administrator-tool form preflight requires closed declarative metadata, exact request-local tool ownership, fresh binary permission, fixed POST/CSRF/JSON/body bounds, deterministic value-free evidence, zero callbacks or writes, and exact cleanup. Component-editor permission checks require 160-character storage, exact case-sensitive manifest permission resolution, explicit non-Owner grants, no implicit Owner access, fresh revocation, zero package execution or state mutation, and exact cleanup. Component data loading requires exact declared registration, view permission, enabled persisted and runtime ownership, exact runtime-manifest identity, closed returned values and state hash, contained failures, zero writes, and exact cleanup. Component history/preflight requires bounded validated metadata, exact restore permission, current and target state evidence, deterministic plans, and zero restore execution. Atomic restore requires that exact plan under the locked enabled binding, the same registered writer and InnoDB tables, exact target reload, one source-linked immutable restore revision, stale-plan refusal, caller-owned transaction refusal, and rollback on writer or ledger failure. Component creation preflight requires exact create permission and enabled runtime ownership, closed inactive hidden parent metadata, normalized package values, an unused numeric id, active-theme layout, InnoDB package tables, deterministic evidence, callback non-invocation, and zero writes. Destination checkpointing requires exact plan reservation, unique route/component identifiers, actor binding, forward-only compare-and-swap stage hashes, deterministic replay, malformed-stage refusal, and zero content/package/search writes. The route stage requires package-owned typed preview rederivation before reservation and under locks, exact route postconditions, atomic Article/create-revision/article-created-audit/checkpoint evidence, deterministic replay, stale-preview refusal, late-failure rollback, and planned retry evidence. The component stage requires the exact retained route checkpoint, separately committed inactive parent/package and dual initial revisions, lifecycle/theme/installation/execution serialization, original-plan reconstruction, composite state hashing, crash-gap reconciliation without a second creator callback, deterministic replay, and closed refusal of preview, plan, route, component, actor, or revision drift. Parent metadata requires exact view/edit permissions, an inactive hidden unrouted shell, current package and core-revision evidence, a caller state hash, lifecycle/theme/installation/parent serialization, title/layout/language-only postconditions, package-state preservation, unchanged no-op behavior, core revision rollback, and exact cleanup. Public-placement preflight requires the exact publish grant before package loading, the current parent/package hashes, one unique active Article destination, exact language agreement, active-theme position support, deterministic source/target/placement evidence, and zero activation or writes. Atomic placement revalidates that plan under lifecycle/theme/source/target locks, changes only seven derived parent fields, preserves package and destination state, commits one core move revision plus one bounded audit fact, refuses reuse, and rolls back permission, drift, transaction, postcondition, revision, or audit failure. Its core-owned form exposes only numeric placement choices, current parent/package hashes, and CSRF while package, component, manifest, grants, target ownership, and the exact plan remain server-derived. Component updates require exact writer ownership and package-table metadata, current view/edit grants, locked enabled binding, current state hash, normalized values, InnoDB transaction support, caller-owned transaction refusal, exact reloaded postconditions, atomic baseline/save revision snapshots, rollback on every contained or revision-ledger failure, preserved core placement state, and exact cleanup. Component persistence/dispatch requires non-executing discovery, disabled non-execution, enabled request-local registration, full manifest component-id storage, a package-owned table with only the exact numeric article-parent foreign key, orphan refusal, read-only parent/runtime-owner agreement, fixed non-executable placement data, core-owned escaped default title/summary and optional fact markup, static fail-closed fallbacks for emitted output, malformed view models, handler exceptions, and output-buffer tampering, inactive non-rendering, unchanged legacy contexts, and exact cleanup. Enablement preflight requires exact Owner authority, current installed-disabled evidence, deterministic plans, dependency and namespace checks, declarative readiness for constrained registration-only service, core-rendered default public component, and combined default-component plus registration-only-service profiles, explicit richer-surface blockers including declarative component-editor metadata, zero state mutation or package execution, CLI-only boundaries, drift refusal, and exact cleanup. Atomic enablement separately requires exact Owner authority and plan evidence, registrar validation under the shared lifecycle lock and package lock, atomic compare-and-swap state plus audit, lifecycle reach from newly enabled standalone and combined default components to the safe core renderer, injected-failure rollback, later registration of every declared combined-package identifier, repeat refusal, and exact cleanup. Atomic disablement requires exact Owner authority, deterministic current-registry and enabled-dependent evidence, cross-connection lifecycle-lock exclusion, stale-plan refusal, atomic state/audit rollback and commit, zero package or migration execution, removal of both combined-package component and service registrations from later requests, repeat refusal, and exact cleanup. Then run the 38-assertion disposable SEO lifecycle, 29-assertion content-revision lifecycle, 21-assertion page-layout distribution lifecycle, and 36-assertion custom-layout lifecycle with their existing rollback and cleanup requirements.
   The same disposable sequence now also runs the separate 24-assertion P3A-5
   atomic payment-adapter enablement lifecycle before the generic atomic
   enablement test. It requires fresh P3A-2 through P3A-4 evidence, exact
   stored settings, value-free availability for both opaque secret references,
   exact Owner authority and plan evidence, shared lifecycle and package locks,
   transactional state/audit commit, rollback injection, replay refusal, no
   handler/secret/network path, and exact cleanup.
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
9. Verify the final 36-table InnoDB/utf8mb4 state, canonical clean-installer row counts including zero SEO, administrator authorization, add-on installation, setting, migration, lifecycle-audit, package-permission audit, action-execution, destination-execution, public-mutation subject/CSRF/rate-limit/idempotency/execution, and add-on component revision rows, and zero Form, Gallery, area, layout, and component relationship errors, then run the 14-assertion two-connection theme-contract suite against the disposable database. That suite proves database-scoped locking, reentrancy, cross-connection exclusion, exception-safe release, effective-theme agreement, safe inactive upload placeholders, and reserved active/previous theme rows.
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
24. Compare full checksums for all 36 tables before and after the allowed/denied permission requests and require no data changes.
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
58. Capture checksums for all 35 application tables, install a `BEFORE UPDATE` trigger only in the disposable database, and submit a real protected Gallery update whose parent write is attempted before the trigger rejects the later child write.
59. Require HTTP 200 with the legacy response `no`, remove the trigger, require the complete 36-table checksum snapshot to match, require the exact initial parent/child values to remain, and require zero updated aliases.
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

The complete 2026-08-14 run used a fresh temporary current-schema baseline so
the retained older local starter remained untouched. It imported the 32-table
installer, applied and no-op reran all 46 migrations, produced the expected
35-table InnoDB/utf8mb4 schema, and passed the new 15-assertion non-executing
Owner package-permission grant/revoke lifecycle alongside every existing
authorization, settings, lifecycle, public-mutation, HTTP, CRUD, media,
theme-contract, and forced-rollback gate. The normalized schema signature was
`0e75f9590094e9875c8df2aa83a8fe5646f2aad6931ed168a7ee935984f9f313`.
The isolated server log was clean, the configured-primary snapshot was
unchanged, and an independent post-run query found zero temporary acceptance
or baseline databases and zero scoped grants.

The complete 2026-08-15 release-candidate run repeated that boundary after the
RED-CMS 5.1 Basic instructions update. It corrected the canonical Instructions
route assertion from the retired repeated `id="instructions"` marker to the
new guide root `id="guide-overview"`, then passed all dependency-free,
database, migration, authorization, add-on lifecycle/runtime, SEO, theme,
authentication, CRUD/upload, Move Content, Section archive, and forced-
rollback checks. The 32-table installer, all 46 migrations, 35-table final
schema, normalized signature
`0e75f9590094e9875c8df2aa83a8fe5646f2aad6931ed168a7ee935984f9f313`,
five canonical routes, and clean isolated runtime log passed. Exact cleanup
left zero temporary databases and zero temporary grants; the retained older
starter remained unchanged at 20 tables and four Articles. No hosted
installation or client database was used.

Separate final theme checks removed the self-test dependency on the omitted
legacy branded `layout-02.png`. The portable Home fixture now uses the tracked
repository-confined `v51-workspace.jpg`, while the live client-media path still
fails closed when absent. Home preview passed 43 assertions, activation
readiness 50, Instructions preview 82, and the theme contract 276.

The complete 2026-08-08 run passed the
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
39-assertion add-on setting storage/editor, authorization preflight, atomic
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
17-assertion read-only public-mutation live-data preflight, 13-assertion
read-only operational-package enablement preflight, 19-assertion
core-only anonymous-subject/CSRF foundation, 7-assertion pure subject-cookie
serializer, 15-assertion core-only fixed-window rate-limit foundation,
18-assertion core-only opaque idempotency-key foundation, 18-assertion
core-owned browser subject-cookie lifecycle,
41-assertion non-executing per-client deployment profile,
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

On 2026-08-10, the collection-row mutation-presentation tree reran the complete
suite against a fresh temporary primary built from the tracked installer plus
all 45 current migrations, because the retained local starter is an older
protected snapshot. The new 11-assertion data-only component/row presentation
contract passed alongside every existing migration, authorization,
transaction, endpoint, CRUD, media, theme, and rollback gate. The normalized
schema signature remained
`cb6e941861fc5ed74142f11b0f36536549a335f478b8214e613836f360501a3f`,
the temporary primary isolation snapshot was unchanged, the isolated server
log was clean, and independent post-run checks found zero matching temporary
schemas and grants. No retained database or hosted installation was migrated.

On 2026-08-10, the core collection-row form-integration tree reran the complete
suite against another fresh temporary primary built from the tracked installer
plus all 45 current migrations. The expanded 22-assertion disposable fixture
proved malformed and foreign row refusal before evidence, stable row/form
instances, separate quantity/remove CSRF and idempotency issuance, one shared
page subject, exact core-rendered actions and controls, display-only fallback
when the page gate is disabled, global form counting, and no package callback
during integration. Every existing migration, authorization, transaction,
endpoint, CRUD, media, theme, and rollback gate also passed. The normalized
32-table schema signature remained
`cb6e941861fc5ed74142f11b0f36536549a335f478b8214e613836f360501a3f`,
the isolated PHP server log was clean, and cleanup removed the acceptance
database, its grant, the temporary primary, and its grant. No retained database,
hosted installation, or client data was changed.

On 2026-08-10, the separate H2D supported-server rehearsal pinned the clean
external Store Lite 0.1.24 repository at merged revision
`c3dc7405d9e62c1112555503523c0c339e4b8fa8`. The established administrator path
passed 100 checks. The expanded public path passed 147 desktop/mobile checks
through real add, exact replay/conflict/refusal, current-line quantity update,
same-page refresh with recalculated facts, line removal, and return to empty
Cart under one unchanged subject. Screenshots showed usable controls without
desktop or mobile overflow; console, page, failed-request, and unexpected-HTTP
error lists were empty. Exact final cart/line/quantity/package-activity/
execution/core-audit state was `4:2:3:8:8:8`. Cleanup removed the disposable
app, database, network, image, certificate, secrets, and staged project. No
hosted or client installation changed. The complete core suite then reran
against a fresh temporary current-schema primary. The 10-assertion controller
source contract and 22-assertion component/form integration fixture passed
beside every existing migration, authorization, transaction, endpoint, CRUD,
media, theme, and rollback gate. The normalized 32-table signature remained
`cb6e941861fc5ed74142f11b0f36536549a335f478b8214e613836f360501a3f`,
the isolated PHP log was clean, and exact acceptance and temporary-primary
database/grant cleanup passed. The retained historical starter was not
migrated.

On 2026-08-12, the Release C3 guest-checkout supported-server rehearsal pinned
the clean external Store Lite 0.1.29 repository at merged revision
`96ae2b2986b6805b33b44f21cf454bd18a67a470`. The established 100-check
administrator path and expanded 268-check public path passed. Desktop pickup
and mobile delivery each used one closed twelve-field core-rendered form,
pay-on-receipt, the existing anonymous subject, exact retry replay, and
changed-request conflict refusal. The package persisted two immutable
server-derived orders, two lines, two line options, and two initial status
events with one pickup, one delivery, one 700-minor-unit delivery fee, and
combined total 4497. Exact final cart/mutation state was `2:2:3:10:12:12` and
order state was `2:2:2:2:1:1:700:4497`. Screenshots had no horizontal overflow;
console, page, request, and unexpected-HTTP error lists were empty. Cleanup
removed the disposable app, database, network, image, certificate, secrets,
and staged project. No hosted demo, retained primary, client installation, or
client data changed. Direct screenshot review confirmed the administrator list
and editor plus the public simple/variable Cart and pickup/delivery checkout
surfaces remained usable at 1280x900 and 390x844; their basic-theme visual
polish is intentionally deferred from this functional release gate.

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

The real Apache direct-PHP projection proof is also separate from the normal
PHP/MySQL suite because it binds temporary localhost HTTP, HTTPS, and FastCGI
ports and launches the host Apache runtime:

```bash
scripts/apache-public-mutation-direct-ingress-proof.sh
```

It stages only the exact direct-ingress PHP dependencies in a temporary server
root, generates one short-lived localhost certificate, and exercises Apache
2.4/PHP FastCGI without opening a database or loading a package. It proves the
canonical direct-HTTPS capture, ignored Host/forwarding values, duplicate
Origin/CSRF/cookie and content-encoding refusals, Apache chunk normalization
into the same bounded measured body, and forwarded-HTTPS-over-HTTP refusal.
Desktop `1440x1000` and mobile `390x844` browser evidence must pass with zero
console/network errors, no cookie or opaque token, and no client-state change.
The run builds a validated non-secret direct deployment-review packet outside
the starter, then removes its Apache/FastCGI/TLS runtime and private key.

The connected Store Lite supported-server browser rehearsal is also opt-in and
requires Docker Desktop plus the external pinned package:

```bash
scripts/store-lite-public-mutation-rehearsal.sh
```

It composes the same reviewed attestation binary with the real clean-starter
front controller and Store Lite package over temporary localhost HTTPS and
fresh MySQL. The default endpoint remains disabled everywhere outside that
process environment.

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
- Add-on package-permission mutation acceptance runs only in the uniquely named disposable database and a temporary integrity-valid package. Its 16 assertions require a MySQL 5.7/8-compatible unsigned-column migration guard, non-executing manifest discovery, a fresh Owner, exact declared permission and target checks, deterministic zero-write planning, stale-plan and caller-transaction refusal, serialized atomic grant and revoke, one permission-specific actor/target audit fact per change, immediate revocation, no package callback execution, and exact administrator/grant/audit/filesystem cleanup.
- Add-on administrator-tool form-preflight acceptance runs only in the uniquely named disposable database and an in-memory trusted runtime context. It requires one closed manifest form declaration, exact request-local display-tool ownership, fresh binary package permission independent of Owner/lifecycle authority, fixed POST/CSRF/JSON/body limits, deterministic actor-bound contract and plan hashes, contract-drift evidence, malformed and forged-contract refusal, next-decision revocation, zero package callback invocation or database mutation, and exact administrator, role, and grant cleanup. It reads no request body/global, consumes no CSRF token, renders no HTML, and creates no endpoint or enablement path.
- Add-on administrator-tool form-renderer acceptance runs before database creation. It requires the closed scalar and collection vocabulary, simple and variable-product coverage, option-group/value and variant/selection nesting, exact 3/16/128 collection bounds, the two-level and 200-field ceilings, escaped deterministic disabled markup, schema-sensitive contract evidence, and fail-closed absent, executable, duplicate, oversized, over-deep, or malformed schemas. It loads no current values, makes no permission decision, invokes no package callback, emits no form/name/submit control, and reads no request, CSRF, database, or endpoint state.
- Add-on administrator-tool form current/initial-value acceptance runs only in the uniquely named disposable database with one temporary package-owned InnoDB table and an in-memory trusted runtime context. It requires exact current and initial loader owners, fresh binary package permission independent of Owner/lifecycle authority, declared typed runtime settings, complete simple/variable current values, complete target-free draft values with blank required scalars, deterministic target/contract-bound current state and target-free contract/configuration-bound draft state, escaped disabled nameless current-value markup, next-decision revocation, invalid/extra/oversized value refusal, output/exception/buffer containment, unchanged package data, and exact administrator/grant/table cleanup. Neither provider receives actor or request/session data, the initial loader receives no target id, and the boundary resolves no creator and creates no editable control, request parser, CSRF operation, endpoint, transaction, or write.
- Add-on administrator-tool form JSON-adapter acceptance runs only in the uniquely named disposable database with one temporary package-owned InnoDB table and an in-memory trusted runtime context. It requires exact JSON transport and canonical length, a 256 KiB global ceiling, duplicate/unknown/mistyped/noncanonical root refusal, the exact tool/form/target/current-state/values root, fresh binary form permission, the manifest body limit before value loading, exact current-value reload, stale-state and contract-drift refusal, complete nested submitted-value validation, deterministic actor/contract/target/state/value-bound evidence, generic public redaction, next-decision revocation, unchanged package data, and exact administrator/grant/table cleanup. Source-order checks require endpoint authentication plus header CSRF before body I/O and prove the adapter helper has no request globals or writer registration. The endpoint remains unlinked and validation-only and invokes no writer.
- Add-on administrator-tool form-writer acceptance runs only in the uniquely named disposable database with one temporary package-owned table, enabled installation, explicit package grant, and in-memory trusted runtime context. It requires one optional exact writer owner, one to eight sorted declared package tables, reserved/duplicate/undeclared registration refusal, deterministic value-free validation/package-version/table/actor/target-bound write plans, caller-transaction refusal, lifecycle/package/installation serialization, locked full preparation, exact typed writer request, stale replay plus permission/version/contract drift refusal, unchanged no-op suppression, complete postcondition reload, and one value-free `addon.form.saved` audit fact committed with the package mutation. Forced output, exception, buffer, HTTP-state, false-return, partial-write, wrong-write, audit, and non-InnoDB failures must leave the package row exact. Source checks require no request globals and prove the validation endpoint does not load or invoke the writer. Cleanup removes the administrator, role, grant, installation, audit, constraint, and package table exactly.
- Add-on administrator-tool form Save-bridge acceptance runs only in the uniquely named disposable database with one temporary package-owned InnoDB table, enabled installation, explicit package grant, and in-memory trusted runtime context. It requires exact tool/form/canonical-positive-target edit identity, fresh permission and enabled-version checks, exact loader/writer/table ownership, complete current values, escaped core-only editable scalar and two-level collection markup, bounded add/remove templates, typed canonical JSON with header CSRF, an atomic value-free `saved` result, no-op `unchanged`, stale replay and next-decision revocation refusal, and exact administrator/role/grant/package/audit/table cleanup. Source and real HTTP checks require POST, database-backed session, and header CSRF before edit parsing or Save body I/O; public responses disclose no values, state/plan evidence, package identity, or tables. Local Chrome captures at 1280px and 390px verify no horizontal overflow, the collection interaction path, typed Save/reload states, and zero console, page, or failed-request errors.
- The Store Lite browser rehearsal is a separate opt-in cross-repository gate.
  It requires an integrity-valid Store Lite package outside the starter, all 46
  core migrations plus the exact seven-package-migration inventory in a fresh
  schema, one acceptance-only enabled registry fixture, only the exact Products
  capability, a simple banana and bounded variable T-shirt, zero initial
  Product or Cart placements, the visible authenticated Add Content -> Product/
  Cart -> Create component -> Homepage -> Place component paths, the resulting
  unauthenticated public product facts and empty Cart, and the visible authenticated Tools ->
  Products -> Add/Create and Edit/Save paths at 1280x900 and 390x844,
  exact persisted integer-money/stock/title changes, one package update event,
  one value-free core form-saved audit fact, an unchanged T-shirt graph, exact
  semantic price and availability facts with no overflow, zero
  console/page/request/HTTP/runtime errors, and exact server/schema/grant/staged-
  package cleanup with an unchanged retained-primary fingerprint. Final
  database evidence requires the exact package Product and Cart relationships,
  active homepage-only core fields, two core `create`, package `baseline`, core
  `move`, and value-free `component.public_placed` audit facts.
- The Store Lite supported-server public-mutation browser rehearsal is a
  separate Docker-only cross-repository gate. It requires Store Lite 0.1.29 at
  revision `96ae2b2986b6805b33b44f21cf454bd18a67a470`, all 10 manifest-ordered package
  migrations, the reviewed custom FrankenPHP/Caddy module, fresh MySQL and
  localhost TLS, temporary process-only endpoint/origin/HMAC configuration,
  the 100-check administrator Product/Cart browser proof, and 268 real desktop/
  mobile public-mutation/Cart/checkout checks including empty state, a simple
  product, an exact Size/Color variant, quantity/removal, one closed checkout
  form, pickup, delivery, pay on receipt, retry/conflict behavior, immutable
  order snapshots, and same-page refresh. Final cart, line, quantity, package
  activity, execution, and core audit counts must be exactly `2:2:3:10:12:12`;
  order, line, option, history, pickup, delivery, fee, and total facts must be
  exactly `2:2:2:2:1:1:700:4497`;
  no container, network, image, database, certificate, secret, staged package,
  or client state may remain.
- Add-on component-editor data-loader acceptance runs only in the uniquely named disposable database and temporary first-party package. It requires exact declared registration, current view permission, enabled placement/runtime/manifest ownership, exact runtime-manifest identity, complete normalized returned values, a record-bound state hash, pre-invocation revocation/case/drift/disabled refusal, foreign and same-id forged-manifest refusal, invalid-value and output/exception/buffer containment, unchanged database fingerprints, and zero package, parent, administrator, grant, table, or filesystem fixtures.
- Add-on component-editor update acceptance runs only in the uniquely named disposable database and temporary first-party package. It requires a core-owned operational form with only the numeric parent id, current state hash, CSRF token, and schema fields; server-derived package/component identity; fresh exact view/edit grants; fail-closed rendering after revocation; declared-editor-only writer registration; one exact writer owner; closed package-table metadata; InnoDB refusal before invocation; enabled locked parent/runtime/manifest ownership; normalized values; exact saved-value reload; immutable core-owned baseline/save snapshots; bounded validated history metadata; deterministic read-only restore preflight; exact plan matching; atomic source-linked restore execution; unchanged no-op behavior; stale/revoked/drifted/disabled/forged/tampered refusal; rollback after update or restore revision-ledger failure, emitted output, exceptions, nested buffers, false returns, and incomplete writes; unchanged core placement state; exact restored target state and revision timeline; and zero package, parent, revision, administrator, grant, table, or filesystem fixtures.
- Add-on component-creation preflight/runner, parent-metadata, public-placement preflight/runner, and atomic-delete acceptance runs only in the uniquely named disposable database. It requires declared-editor-only creator/deleter registration, exact create/view/edit/delete/publish permissions, enabled manifest and runtime component/loader/creator/deleter ownership, closed package-table metadata, InnoDB refusal before callback invocation, an unused numeric record id, one active-theme layout, normalized parent and package values, an inactive hidden unrouted parent plan, deterministic hashing, disabled/revoked/mismatched/invalid/existing-record refusal, and zero creator/deleter invocation during preflight. The atomic creation runner requires exact plan matching under lifecycle/theme/installation serialization, rejects caller-owned transactions, contains creator/loader failures, verifies exact parent and package postconditions, commits one core `create` and one package `baseline` revision, refuses reuse, and rolls back output/exception/nested-buffer/false/partial-write and both forced-ledger failures. Parent state and writes require fresh exact view/edit grants, enabled binding, inactive shell and current revision evidence, stale-state refusal, title/layout/language-only mutation, unchanged package data, one core `save` revision, no-op suppression, and rollback on forced ledger failure. Public-placement planning binds exact parent/package state to either the unique active language homepage or one unique active Article route, requires language agreement and active-theme position support, derives closed destination-specific placement values, and proves deterministic zero-write behavior with revoked, stale, cross-language, inactive-target, and unsupported-position refusals. Its atomic runner revalidates under lifecycle/theme/installation/source/destination locks, refuses caller transactions, revoked grants, destination drift, and plan reuse, changes only the four homepage or seven Article parent fields, preserves package and destination state, commits one core `move` revision and value-free placement audit, and rolls back revision or audit failure. Delete planning binds exact parent/package hashes, the latest validated package revision, declared InnoDB tables, and deterministic value-free evidence without invoking the deleter or writing state. The atomic delete runner revalidates under lifecycle/theme/installation/parent locks, contains the deleter, rejects partial deletion, records both final `delete` snapshots, removes package/SEO/parent rows together, retains both ledgers, refuses reuse, and rolls back callback or ledger failures. Exact cleanup leaves zero administrator, grant, package, parent, revision, SEO, or table fixtures.
- Add-on request-bootstrap acceptance runs only in the uniquely named disposable database and uses temporary first-party packages outside the clean starter. It proves uninstalled and disabled packages never execute, enabled dependencies register first, exact handlers and owners remain lookup-only, lifecycle CLIs do not request-load packages, bootstrap writes no registry or audit state, drift and missing dependencies/code fail before execution, and every package/database/filesystem fixture is removed.
- Typed add-on adapter invocation acceptance runs before database creation with
  one in-memory manifest, registry, context, and synthetic package-bound secret
  access object. Its 19 assertions prove missing ownership refusal, bounded
  request validation before callbacks, exact typed success, explicit adapter
  failure, malformed result, output, exception, and buffer-stack containment,
  private owning-package secret consumption, undeclared-setting refusal, and
  data/error secret-disclosure rejection. It opens no database, route, browser,
  provider, network, Store Lite, payment, client, or deployment path.
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
- Atomic public-mutation runner acceptance runs only in the uniquely named disposable database with one temporary InnoDB table and in-memory trusted runtime context. It proves one declared handler/state-loader binding, typed field refusal, CSRF-before-rate ordering, exact replay/conflict outcomes, fixed-rate ordering, server-derived postconditions, keyed replay evidence, a value-free anonymous audit fact, and declared runtime-setting injection into both callbacks. Runtime-setting checks require only the exact selected configured non-secret value, withhold a coexisting secret reference, refuse missing or malformed selected values before rate/replay/package work, bind configuration drift to an idempotency conflict, and preserve an empty-object/no-setting-row path for declarations without runtime settings. Output, exception, and rollback failures remain contained, with exact database/runtime/constraint/table cleanup. It creates no package files, browser state, dispatcher, response, route execution, enablement profile, Store Lite data, or client artifact.
- Public-mutation dispatcher acceptance runs before database creation and uses
  only explicit transport fixtures plus an in-memory registrar context. It
  proves one closed dispatcher/capture result, runtime-unavailable behavior,
  non-POST refusal, missing-attestation refusal, incomplete-binding refusal,
  and zero package callback or HTTP-state changes. The dispatcher remains
  unlinked from `index.php`; the supported-server disposable rehearsal is
  complete. The core subject-cookie lifecycle is now proven independently;
  the non-executing deployment profile and response-owner composition are
  also proven; the local direct Apache review now passes, while an exact hosted
  deployment review remains required before linking it there.
- Public-mutation deployment-profile acceptance is dependency-free and creates
  no database, package, request, browser, route, or client fixture. It accepts
  only one non-secret operator review packet with a separate client database,
  canonical HTTPS origin, either pinned FrankenPHP/Caddy attestation facts or
  pinned Apache/PHP direct facts, matching trust and route order, core response/
  cookie ownership, host-only cookie policy, explicit isolation, and disabled
  activation flags. It rejects starter-database reuse, request-
  derived trust, version/route/policy drift, secret-shaped fields, and any
  dispatcher/package/Store Lite activation without loading a profile or
  changing response, filesystem, database, or client state.
- Public-mutation deployment-review acceptance is dependency-free and creates
  no database, package, request, browser, route, or client fixture. It binds
  the profile hash to the exact attested or direct server/TLS/artifact/trust
  facts outside the starter and bounded desktop/mobile HTTPS browser evidence.
  The direct form requires Apache/PHP/SAPI plus configuration/runtime/
  certificate/projection hashes and verified direct-HTTPS/ignored-forwarding
  facts. It rejects secret values, unreviewed server/TLS/trust/browser facts,
  forged review hashes, file loading,
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
- Real Apache direct-PHP deployment acceptance is localhost-server- and
  browser-dependent but database-free. It requires Apache 2.4 with PHP 8.2-8.5
  under an accepted SAPI, direct TLS ownership, an exact canonical origin,
  closed PHP request projection, duplicate/encoded refusal, Apache chunk
  normalization into the measured body, ignored Host/forwarding values, and
  fixed desktop/mobile evidence. It must emit only a validated non-secret
  review outside the starter and remove its server root, FastCGI process,
  private key, and TLS directory. It cannot link the dispatcher, load a
  package, enable Store Lite, access a database, or change client state.
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
- Public-mutation browser-controller source acceptance is dependency-free. Its
  11 assertions require the exact form/status selectors, fixed evidence/content
  headers,
  same-origin/no-store/redirect-error fetch policy, one WeakMap-held frozen
  command body, immediate DOM evidence removal, no cookie/storage/log/dynamic-
  code/HTML-sink/external-URL path, and continued absence from the front
  controller and theme adapters. It also requires bounded rich-value encoding,
  validated select-backed conditional controls, mutation-neutral status copy
  and a fixed delayed `window.location.reload()` with no assignable or external
  navigation target. The separate 26-assertion Playwright self-test uses an
  intercepted local origin and real Chrome at `1440x1000` and `390x844`; it
  proves canonical body/header delivery, accepted completion, exact-body retry,
  conflict closure, rich UTF-8/empty-value encoding, conditional required and
  visible control updates, invalid foreign configuration refusal, DOM evidence
  removal, frozen controls, generic status copy, accepted/unchanged same-page
  refresh, and zero page errors.
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
  migrated disposable database. Its 22 assertions require duplicate raw-cookie
  refusal without evidence, one issued subject and one controller delivery for
  the first accepted page form, same-subject reuse for later top-level and row
  forms, strict request-local coordinator validation, malformed/foreign row
  refusal before evidence, stable row/form instances, exact quantity/remove
  actions and fields, display-only fallback, global form counting, and exact
  evidence/schema/grant cleanup.
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
- Atomic payment-adapter enablement acceptance is a separate 24-assertion lifecycle in the uniquely named disposable database and a temporary exact-profile fixture outside the clean starter. It recomputes database, registrar, ingress, stored-setting, and value-free secret-availability evidence; requires exact Owner authority, enabled same-database Store Lite, immutable migration and InnoDB evidence, two available opaque secret references, and CLI target/plan/nonzero-backup confirmations; proves stale-plan, drift, authority, dependency, audit, and injected late-failure refusal; commits exactly one state transition and audit fact; invokes no handler or secret resolver; and removes every fixture, database, and grant exactly.
- Provider-contact P3E-7 authorization acceptance is a separate 33-assertion lifecycle in the uniquely named disposable database. It requires exact closed P3E-6 readiness/envelope shapes, current trusted enabled adapter 0.1.1 and same-database Store Lite, a database-backed Owner with the exact enable grant, a core-derived client/actor subject hash, an active at-most-15-minute UTC window, and all mutation/payment/live/client permissions false. Dry run writes nothing. Apply locks Owner and package state, commits one nonce-derived immutable administrator-action row and one value-free audit fact, refuses original or changed-envelope nonce replay, and rolls the nonce reservation back on audit failure. Expiry, revocation, subject mismatch, disabled dependency, plan tampering, package execution, credential/environment/network primitives, and all provider, Checkout, payment, webhook, Store Lite mutation, browser, and client paths remain refused. Cleanup leaves zero fixture rows/files and exact `database:0 grant:0` evidence without adding a migration or table.
- Provider-contact P3E-8A claim acceptance is a separate 34-assertion lifecycle in the uniquely named disposable database. It first proves the unchanged 33-assertion P3E-7 authorization, then requires that exact immutable row and a still-active envelope under fresh Owner, capability, adapter, and same-database Store Lite validation. Dry run writes nothing. Apply locks the same authority/package state and atomically commits one distinct nonce-derived attempt-claim row plus one value-free audit fact. Replay, changed or missing authorization, expiry, authority revocation, disabled dependency, altered ledger evidence, and audit failure fail closed; audit failure leaves the claim available for its one real attempt. The helper executes no package code and contains no credential, environment, request-global, DNS, TLS, HTTP, provider, Checkout, payment, webhook, Store Lite mutation, browser, client, or deployment primitive. Cleanup removes every fixture row/file without adding a migration or table.
- Provider-contact P3E-8B2 loopback execution acceptance is a separate 32-assertion lifecycle in the uniquely named disposable database. It requires the exact current P3E-7 authorization and P3E-8A claim, current Owner/capability/adapter/same-database Store Lite state, and value-free secret availability. Apply commits an immutable execution-start row and audit before registrar, secret, or handler access; then integrity-checks the registrar, resolves exactly `stripe.secret-key`, invokes one contained typed loopback-only operation, and records one closed bounded result plus audit after verifying the complete start row. Success, replay refusal, missing-secret indeterminate outcome, start-audit rollback, outcome-audit rollback with permanent no-retry, scoped-secret isolation, authorization-without-claim refusal, forbidden network/provider primitives, and exact cleanup are required. No migration, table, provider contact, payment, webhook, Store Lite mutation, public route, browser, client, or deployment is created.
- Provider-contact P3E-8B3B synthetic-package execution acceptance is a separate 33-assertion lifecycle in the uniquely named disposable database. The shared evidence gate permits only historical adapter `0.1.1/disabled` or current adapter `0.1.3/synthetic_only`; all other version/mode pairs fail closed, and the historical loopback runner refuses the synthetic profile before a start can commit. The synthetic runner commits its operation/target-bound immutable start and audit before package or secret access, resolves exactly `stripe.secret-key`, invokes the registered typed synthetic operation, validates a closed in-memory resource-miss outcome with network/provider/retry/mutation false, and records one immutable result plus audit. Replay, missing secret, start-audit rollback, outcome-audit rollback with permanent no-retry, authorization-without-claim, malformed results, forbidden network/provider primitives, and exact cleanup are required. It runs beside the unchanged P3E-7, P3E-8A, and P3E-8B2 fixtures and adds no migration, table, provider contact, payment, webhook, Store Lite mutation, public route, browser, client, or deployment.
- Provider-contact P3E-8B3C2 provider-operation runner acceptance is a separate 37-assertion lifecycle in the uniquely named disposable database. The shared evidence gate adds only adapter `0.1.4/provider_read_only`; mismatched version/mode pairs fail closed, and both historical runners refuse the provider profile before a start can commit. The provider runner commits its operation/target-bound immutable start and audit before package or secret access, resolves exactly `stripe.secret-key`, and invokes the exact sandbox operation against an integrity-checked in-memory handler. It validates a closed resource-miss outcome with network/provider true as handler evidence, while source and call-count checks prove that the fixture performs no DNS, TLS, HTTP, cURL, or Stripe contact. Missing secret, malformed post-invocation output, start-audit rollback, outcome-audit rollback, replay, authorization without claim, and exact cleanup retain permanent no-retry behavior. No migration, table, real provider request, payment, webhook, Store Lite mutation, public route, browser, client, or deployment is added.
- Provider-contact P3E-8B3C3A operator-command acceptance is a pure 40-assertion source contract. It requires CLI-only loading of the reviewed B3C2 helper; exact database/package/version/state, plan/authorization/claim/start/secret-availability/backup hashes; exact sandbox operation/target and restricted-test mode; one attempt; no retry; no mutation; and explicit `--apply`. Default dry run, a single execution call site, success limited to the bounded 404 resource miss, consumed-attempt wording for every other result, no key argument or literal, forbidden network/shell/request primitives, and absence of browser/public bridges are required. The test opens no database, resolves no secret, executes no package, and performs no provider contact. The first real restricted-key GET remains B3C3B.
- Provider-contact P3E-8B3C3B is a separately authorized operational rehearsal, not an automated acceptance fixture. It stages merged core, Store Lite `0.1.35`, and adapter `0.1.4` into a fresh disposable database; verifies a pre-contact backup; and consumes fresh ten-minute authorization/claim evidence through one server-local apply. The exact GET to the synthetic missing Checkout Session must return bounded `404 resource_miss_observed` evidence, appear exactly once in the dedicated Stripe sandbox log, retain no response body/header or credential, authorize no retry/mutation, and finish with checksum/permission/credential scans plus exact database/grant/project/process cleanup and unchanged primary evidence. The completed rehearsal satisfied those conditions; after evidence review, the operator explicitly expired the restricted key and it no longer appears in the active restricted-key list.
- P3E-9 Sandbox Checkout-creation frontier acceptance is documentation-only. It required README, roadmap, security, Version 5.1 direction, the P3 proposal, and the canonical status graphic to record P3E-9A as the initial pure non-executing gate; refuse reuse of mutation-disabled P3E-8 evidence; separate synthetic integration, new one-attempt mutation authority, and one real Sandbox Session; and keep credentials, provider POSTs, payment, webhook, browser checkout, demo/client state, and P4 absent. The changed-file credential scan and `git diff --check` passed with no PHP, migration, manifest, package, database, route, or runtime changes.
- External P3E-9A acceptance is a dependency-free source fixture in the separately distributed adapter, not a core runtime fixture. Its 53 focused and 921 aggregate assertions require exact mutation-aware profile facts, bounded 30-minute-through-24-hour expiry, canonical request hashing, read-only-profile refusal, synthetic open/unpaid/non-live response validation, Checkout-URL removal, and every current execution effect false. The merged adapter commit leaves version `0.1.4` and its complete installable package subtree unchanged. Core adds no source, test runner, package, credential, network path, database, route, or runtime behavior through this status mirror; P3E-9B remains separate.
- P3E-9B synthetic Checkout integration is split across external adapter `0.1.5` and one dependency-free core runner. Adapter P3E-9B1 adopts byte-identical P3E-9A source, adds only `checkout.create-sandbox-synthetic`, and passes 60 focused plus 995 aggregate adapter assertions. Core P3E-9B2 acceptance requires exact integrity-current package `0.1.5`, closed USD checkout arithmetic, bounded same-origin/expiry policy, mutation-aware profile, deterministic input/plan hashes, read-only-profile refusal, one injected package-owned secret-access object with one setting, contained typed invocation, exact bounded result facts, malformed/throwing/output handler containment, and exact temporary-project cleanup. Its 37 assertions open no database and add no authority ledger, credential resolver, network, Stripe, Checkout Session, payment, webhook, browser, Store Lite, demo/client, or deployment path.
- P3E-9C1 mutation-authorization acceptance is a separate 34-assertion lifecycle in the uniquely named disposable database. It requires the exact non-executing P3E-9B plan, enabled integrity-current adapter `0.1.5`, enabled Store Lite `0.1.35` declaring `store.orders.manage`, a fresh database-backed Owner with exact `addons.enable` and `store.orders.manage` grants, one nonce, one maximum attempt, and an at-most-fifteen-minute UTC window. Dry planning writes nothing. Apply repeats the decision under lifecycle, package, actor, permission, and installation locks, then atomically commits one nonce-derived immutable administrator-action row and one value-free audit fact. Replay, changed evidence, expiry, overlong windows, permission revocation, package drift, and audit failure fail closed; audit failure rolls back the nonce for one clean recovery. The helper has no claim, execution-start/result, operator command, secret resolver, environment reader, package invocation, network/provider primitive, Checkout Session, payment, webhook, browser, Store Lite mutation, retry, client, migration, or table path. Cleanup removes every fixture row, table, package, actor, and file; the full runner separately proves exact temporary database/grant cleanup and unchanged configured primary.
- P3E-9C2 mutation-claim acceptance is a separate 37-assertion lifecycle in the uniquely named disposable database. It recomputes the exact P3E-9C1 decision under fresh Owner, `addons.enable`, Store Lite `store.orders.manage`, adapter `0.1.5`, Store Lite `0.1.35`, input, plan, and expiry checks; requires the matching immutable authorization row; and proves dry planning writes nothing. Apply locks current authority/package state plus the authorization and missing claim rows, then atomically commits one distinct nonce-derived claim and one value-free audit. Replay, missing/changed/tampered authorization, changed expected hash, expiry, both capability revocations, disabled Store Lite, and audit failure fail closed; audit failure preserves one clean claim. The helper has no execution start/result, operator command, secret resolver, environment reader, package invocation, network/provider primitive, Checkout Session, payment, webhook, browser, Store Lite mutation, retry, client, migration, or table path. Cleanup removes every claim, authorization, audit, package, actor, table, and file fixture; full acceptance separately proves temporary database/grant cleanup and unchanged configured primary.
- P3E-9C3A transport-double acceptance is a separate 36-assertion lifecycle in the uniquely named disposable database. It requires exact P3E-9C1 authorization and P3E-9C2 claim rows under fresh authority/package/expiry checks, proves zero-write planning, commits immutable start plus audit before one final core-owned in-memory double call, validates a closed no-network/no-provider outcome, and commits one immutable result plus audit. Replay, missing evidence, changed start hash, start-audit rollback, transport fault, result-audit rollback, and permanent post-start no-retry are required. `executionPerformed` means only the double ran; credential access, package invocation, network, provider contact/mutation, real Checkout creation, payment, webhook, browser, Store Lite mutation, retry, client, migration, and new-table effects remain false. Cleanup removes all fixture artifacts exactly.
- P3E-9C3B1 operator-command acceptance is a pure 45-assertion source contract. It requires CLI-only loading of the reviewed C3A helper; exact database/package/version/state, plan/input/authorization/authorization-state/claim/start/backup hashes; exact double operation/target; one attempt; and explicit no-retry/no-network/no-provider-mutation/no-real-Checkout confirmations plus `--apply`. Default dry run, one plan call, one final-double construction, one runner call, exact bounded-success wording, consumed-attempt wording for every other result, no credential argument/literal, forbidden network/shell/request/package primitives, and absence of browser/public bridges are required. It opens no database and performs no execution; C3B2 disposable apply rehearsal remains separate.
- P3E-9C3B2 is a separate operational rehearsal. It stages merged core, builds exact temporary adapter `0.1.5` and Store Lite `0.1.35` fixtures, creates a fresh current-schema disposable database, and generates current authorization/claim/start evidence. It requires dry run with no start/result, incomplete-apply refusal with no write, one exact fully confirmed in-memory-double apply, immutable four-row/four-audit evidence, replay refusal, zero package-handler/data/provider effects, and cleanup `database:0 grant:0 staged-project:0 primary:unchanged`. It uses no credential or network and does not authorize P3E-9D.
- P3E-9D0 real-POST preflight acceptance is a pure 25-assertion contract. It consumes only exact adapter `0.1.5` synthetic-plan and mutation-aware input evidence; emits the exact Stripe Checkout Sessions POST target, form encoding, payment mode, bounded expiry, order reference, deterministic USD line items, hash-only metadata, bounded idempotency key, and canonical request hash; and refuses read-only or changed input. Credential/header, database, package invocation, network, provider, real Session, payment, webhook, browser, Store Lite, retry, live, and client effects remain false. The source scan excludes secret, transport, request-global, shell, package-registration, and adapter-invocation primitives.
- P3E-9D2 core real-operation preflight acceptance is a pure 39-assertion contract. It requires discovery-valid adapter `0.1.7`, exact `0.1.5` synthetic source evidence, the complete recomputed D0 preflight, canonical input/request hashes, and distinct preflight/provider operation identities. Planning executes no package and derives only a deterministic start-identity hash. Core then integrity-checks the registrar and invokes only `checkout.create-sandbox-real-post-preflight` with null secret access, accepts only the exact reconstructed typed request and false-effect outcome, and derives a separate result-identity hash. Changed package/input/preflight/identity evidence and malformed, throwing, output-emitting, or changed-operation handlers fail closed. No credential, database, authorization/claim/start/result row, network, Stripe, real Session, payment, webhook, browser, Store Lite, retry, live, client, or deployment effect occurs; the exact temporary project is removed.
- P3E-9D3A operator-command acceptance is a pure 68-assertion source contract. It requires CLI-only loading of the dependency-free D2 helper; one absolute bounded evidence file with exact input, preflight, and synthetic-plan objects; exact adapter/source/integrity/plan/input/contract/request/start identities; distinct preflight/provider operations; one attempt; nine explicit no-effect confirmations; and `--apply`. Default dry run exits before registrar/handler invocation. Apply contains one D2 call and accepts only `request_contract_adopted` plus the non-persistent result identity. Credential arguments and literals, configuration/database access, secret resolution, package runtime helpers, request globals, network/shell/provider primitives, retries, and browser/public bridges are absent.
- P3E-9D3B no-contact rehearsal acceptance has a pure 72-assertion source contract plus one opt-in cross-repository run. It stages exact committed core `f93d191`, adapter `a441588` `0.1.7`, and Store Lite `f7de77e` `0.1.35` package bytes; prepares only non-secret D0/D2 evidence; proves default dry run, changed-plan-hash refusal before invocation, one exact contained apply, one non-persistent result identity, and every provider/business effect false. PHP URL streams and common cURL/socket functions are disabled, common secret/proxy environment inputs are removed, and no configuration or database is opened. Credential scans and staged/source fingerprints pass, followed by cleanup `staged-project:0 evidence:0 source-repositories:unchanged database:not-opened`. D4 remains separately authorized.
- P3E-9D4 authorization-plan acceptance is documentation-only. It splits the first real Checkout creation into D4A separately distributed provider-write operation with offline/loopback acceptance, D4B fresh authority/claim/durable-start/result core runner with in-memory acceptance, D4C CLI command plus network-disabled no-contact rehearsal that never runs real apply, and D4D separately authorized key creation/storage, dry-run review, one real apply, and key expiration. It requires the current official Stripe key, Checkout creation, expiry, idempotency, and Session-expiration facts to be rechecked; keeps every prior authorization/claim/start/result row ineligible; and adds no PHP, migration, manifest, package, database, key, account, network, route, runtime, hosted-demo, client, or provider effect.
- P3E-9D4A external provider-write acceptance is complete in separately distributed adapter `0.1.8` at merged commit `562b8a9`. Its focused 89 assertions and aggregate 1,172 assertions require D1 preflight validation before secret access, exact one-use restricted-test transport, fixed Stripe Sandbox host/path, bounded TLS/time/header/body behavior, secret and response redaction, closed created/indeterminate outcomes, and source/package parity. A separate 11-assertion local TLS-loopback rehearsal passed with `process:0 temp:0 credential:absent provider:untouched`; all 19 package integrity hashes matched and migrations remained unchanged. No core caller, real key, DNS, external TLS, HTTP, Stripe request, Checkout Session, payment, database, Store Lite instance, browser, hosted/client, or deployment effect occurred. D4B remains separate.
- P3E-9D4B1 fresh real-POST authority acceptance has a pure 20-assertion evidence contract plus a 30-assertion lifecycle in a uniquely named disposable database. It binds only adapter `0.1.8`, Store Lite `0.1.35`, exact D0/D2 identities, database, order snapshot, Owner, exact lifecycle/order permissions, value-free secret availability, one nonce, one attempt, and a fifteen-minute maximum window. Planning writes nothing; authorization and claim each atomically commit one new D4-only row plus one value-free audit. Pre-D4 rows, replay, changed evidence, expiry, and revoked authority fail closed. Package registration, handler invocation, secret-value resolution, execution start/result, network, Stripe, Checkout creation, payment, webhook, browser, Store Lite mutation, retry, live mode, migration, hosted/client, and deployment effects remain absent; D4B2 remains separate.
- P3E-9D4B2 durable real-POST execution acceptance is a separate 29-assertion lifecycle in the uniquely named disposable database. It requires the exact D4B1 authorization and claim, commits immutable start plus value-free audit before registrar, scoped secret, or handler access, invokes one final in-memory adapter handler, validates only the bounded open/unpaid/non-live Session projection, rechecks the complete start row, and commits one immutable result plus audit. Replay, changed start, start-audit rollback/recovery, result-audit failure with permanent no-retry, throwing/malformed handler containment, missing-secret indeterminate recording without invocation, scoped webhook-secret refusal, credential/source redaction, forbidden network primitives, and exact cleanup are required. Conservative provider-effect fields become true after handler invocation even though the sealed fixture performs no network. No DNS, TLS, HTTP, Stripe request, real Session, payment, webhook, browser, Store Lite mutation, retry, live mode, hosted/client, migration, or deployment effect occurs. D4C is implemented through separate later gates.
- P3E-9D4C1 real-POST operator-command acceptance is a pure 74-assertion source contract. It requires CLI-only loading of reviewed configuration/database/D4B helpers; one exact five-object bounded evidence file; database, package, Store Lite, actor, expiry, preflight, input, request, order, authorization, claim, start, secret-availability, backup, operation, target, and one-attempt confirmations; three explicit intended provider effects; and eight explicit exclusions plus `--apply`. Default dry run exits before the single D4B2 execution call site. Success prints only the bounded open/unpaid/non-live Session reference after URL discard; every other result is consumed and no-retry. Credential arguments/literals, network/shell/secret/runtime primitives, and browser/public bridges are absent from command source. The test opens no database, configuration, secret, package, runner, network, provider, hosted/client, or deployment effect. D4C2 remains separate.
- P3E-9D4C2 no-contact rehearsal acceptance has a pure 92-assertion source contract plus one opt-in operational run. It stages exact merged core, discovery-valid adapter `0.1.8` repair `44ed7b3`, and Store Lite `0.1.35`; proves its temporary PHP configuration actually disables URL streams and common cURL/socket functions; removes secret-value and proxy inputs; creates one fresh current-schema database; records only exact package identities, opaque settings, Owner/permissions, fresh D4 authorization and claim; and emits bounded five-object evidence. Default dry run passes, incomplete apply and one changed request hash are refused, and the fully confirmed apply set is never invoked. Ledger evidence remains `2:2:0`, with `real-apply:0 start-result:0 provider-effects:0`. Credential scans, staged/source fingerprints, primary isolation, and cleanup pass `database:0 grant:0 staged-project:0 evidence:0 environment:clear source-repositories:unchanged primary:unchanged`. No secret value, package handler, DNS, TLS, HTTP, Stripe request, Checkout Session, payment, webhook, browser, Store Lite mutation, retry, live mode, hosted/client, retained-database migration, or deployment effect occurs. D4D remains separately approval-gated.
- Colombia C0 provider-decision acceptance is documentation-only. It records Stripe D4D as owner-deferred without cancelling or widening its evidence; selects the separately distributed `redcms.store-lite-wompi` candidate with only customer-visible Nequi, `COP`, and one-time Store Lite guest orders; preserves direct Nequi Push/QR as a later client-specific alternative; identifies that direct Nequi Push does not satisfy the current hosted-URL-only initiation result; reserves C1 for a closed additive `hosted_redirect`/`out_of_band_confirmation` union with the hosted shape unchanged; maps immutable order identity/amount/currency and asynchronous Wompi outcomes into the provider-neutral boundary; separates one client-local public-key setting from private/integrity/event secret references and keeps Sandbox/production environments disjoint; requires current provider acceptance documents and explicit customer acceptance; keeps personal data out of payment evidence; and defines C1 through C5 with C1 offline-only and C4/C5 separately approval-gated. It adds no PHP, manifest, package, migration, database, route, credential, provider account, HTTP request, Wompi transaction, Nequi notification, payment, order transition, hosted-demo change, client data, or deployment. Official provider facts are dated and must be rechecked before implementation and every external gate; changed-file link, credential, stale-status, and `git diff --check` reviews must pass.
- Colombia C1 payment-initiation acceptance is a dependency-free core helper plus a CLI-only 55-assertion fixture. The helper accepts exactly `hosted_redirect` with a canonical ordered provider-reference/HTTPS-URL value returned unchanged or URL-free `out_of_band_confirmation` with opaque reference, fixed pending state, and generic provider-app action; HTTP, credential/port/query/fragment URL parts, missing/extra/mixed fields, paid initiation, provider-named action/mode, and malformed references fail closed. It does not modify or wire the existing Stripe-specific result. The fixture plans one exact COP/Nequi request, exposes only request/acceptance hashes and false effect flags, keeps synthetic email/phone/tokens/signature and all opaque secret references out of returned evidence, resolves a bounded provider-supplied signed-property list in declared order, uses a 25-hour retry-compatible timestamp window, verifies the checksum boundary, binds later parsed event to that boundary, requires event/lookup/provider/order/amount/currency/method agreement, refuses replay and mismatches, and normalizes only APPROVED to proposed paid or DECLINED/ERROR to proposed failed with order mutation false. Existing 29-assertion Stripe P2, 19-assertion typed-adapter, and 26-assertion payment-profile regressions pass. The helper has no runtime caller; no configuration, database, package, manifest, migration, secret resolution, request global, provider SDK, DNS, TLS, HTTP, Wompi transaction, Nequi notification, payment, webhook route, browser, Store Lite mutation, hosted-demo change, client data, or deployment is added. C2 remains a separate offline external-package gate.
- Colombia C2 external-package acceptance is recorded against public repository `orojas01-glitch/redcms-store-lite-wompi` commit `e17a371d73f286f5586deae88ad2c73d2f233651`. Version `0.1.0` declares one adapter, exact Store Lite dependency, one unset client-local public setting, three secret references without defaults, Sandbox-only Wompi host, one refusing server-signature event route, two unexecuted InnoDB evidence migrations, and nine exact integrity files. Its 34 offline-contract assertions require hashed COP/Nequi request evidence plus deterministic plan fingerprint, the exact C1 URL-free PENDING projection, one-use changed-plan/replay-refusing sealed double, bounded dynamic signed properties, 25-hour retry-compatible event window, checksum, replay and event/lookup agreement, and only non-mutating paid/failed evidence. Its 60 package assertions require generic discovery/integrity/source parity, contained registrar, fixed false-effect `contract.probe`, unsupported provider operation, refusing route, bounded migrations without personal/credential/body/header/URL columns, forbidden-primitive and credential scans, exact cleanup, and the deliberate current-core refusal `outbound_host_invalid` plus `setting_contract_invalid`. All PHP lint, JSON, shell, internal-link, source-boundary, local/remote commit, and clean-worktree checks pass. No core/Store Lite/Stripe file, database, migration execution, installation, enablement, runtime publication, real setting, secret value, provider account, DNS, TLS, HTTP, Wompi transaction, Nequi notification, payment, webhook ingress, browser, order mutation, hosted-demo change, client data, or deployment effect occurs. C3 remains a separate offline core-profile and disposable-lifecycle gate.
- Colombia C3A core-profile acceptance extends only `includes/addon_payment_adapter_preflight_helpers.php`. Exact package id `redcms.store-lite-wompi` selects `store_lite_wompi_adapter_v1`; every existing Stripe fixture retains `store_lite_stripe_checkout_adapter_v1`. Wompi validation requires exact adapter, Store Lite dependency/range, one ordinary and three secret setting keys, two migration ids/paths in order, event route/path, and sole Sandbox host. Normalized setting-key lists are included in the deterministic profile hash and independently rechecked as ordered, unique, disjoint, count-consistent valid identifiers; non-array input also fails closed. The test passes 30 fixture assertions and 41 assertions against published Wompi `0.1.0`, including all nine payload hashes, and refuses changed identity/adapter/dependency/range/settings/migrations/route/host/permission/mutation surfaces. Existing Stripe profile 26, registrar 13, ingress 26, synthetic-checkout 37, and typed-adapter 19 assertions pass. The helper remains free of request, environment, database, secret-value, and network primitives; activation/state/runtime/package/secret/network/route effects remain false and all four downstream blockers remain. No database, package install, migration, registrar execution for Wompi, enablement, setting, secret, provider request, payment, browser, Store Lite mutation, hosted-demo change, client data, or deployment effect occurs. At C3A close, C3B remained a separate disposable database/migration/registrar gate.
- Colombia C3B acceptance extends only the registration-only payment-adapter helper plus its existing fast fixture, and adds a separate exact real-package disposable rehearsal. Exact Wompi package identity selects and fingerprints `store_lite_wompi_adapter_v1`; final evidence requires its exact adapter and provider-event route and cannot be relabeled as Stripe. The dependency-free Stripe-plus-Wompi registrar suite passes 18 assertions while Stripe server-event ingress remains 26. The rehearsal refuses dirty/different external repositories, requires Store Lite `0.1.35` at `f7de77e` and Wompi `0.1.0` at `e17a371`, stages both outside the starter, creates one fresh database/grant, applies all 46 core migrations, records the already-proven Store Lite identity/11-migration enabled baseline, and executes the guarded two-migration Wompi install. Its 16 assertions require `installed_disabled`, two empty InnoDB tables, no settings, exact database hashes/counts, registrar-only adapter/refusing-route registration without handler invocation/publication/network/route exposure, repeat-install refusal, bounded audit evidence, and registrar database non-mutation. Cleanup proves `database:0 grant:0 staged-project:0 primary:unchanged`. No Wompi enablement, real setting/secret, provider request, transaction, Nequi notification, payment, order mutation, browser, hosted-demo change, client data, external package copy into core, or deployment effect occurs. C3C remains a separate offline atomic enablement and two-client isolation gate.
- Colombia C3C1 acceptance adds only exact Wompi body-signed ingress and atomic-enablement profile support, then reuses the C3B disposable harness. Stripe retains the canonical `Content-Type`/`Content-Length`/`Stripe-Signature` capture; exact Wompi requires only content type/length because checksum and signed properties are in the JSON body. Core preserves opaque bytes without parsing or verification, reports no header signature, and refuses an extra Stripe header. The fast ingress suite passes 31 assertions. Enablement preserves Stripe's two-secret rule and requires exactly three available opaque references for Wompi. After all 46 core migrations, the rehearsal passes the existing 24-assertion Stripe atomic-enable test and a 17-assertion exact Wompi test for four client-local settings, three value-free declarations, redacted plan hashes, missing-secret/stale-plan refusal, injected compare-and-swap rollback, one atomic enable/audit, empty evidence tables, and repeat refusal. Cleanup proves `database:0 grant:0 staged-project:0 primary:unchanged`. The broad suite's dependency-free phase passed; its database phase was not accepted because the retained configured starter lacks the current `RED_Admin_Roles` primary-snapshot baseline, and that starter was not migrated. No real public key or secret value, provider contact, transaction, payment, event processing, order mutation, runtime publication, browser, hosted-demo change, client data, external package copy into core, or deployment occurs. At C3C1 close, C3C2 remained a separate two-client enable/disable isolation gate.
- Colombia C3C2 acceptance adds no core runtime behavior. Its exact-package shell creates two fresh client databases/grants, applies all 46 core migrations to each, and runs 21 assertions through separate MySQL connections. Both clients independently install Wompi disabled, store distinct synthetic public values plus three distinct opaque references, produce value-free availability declarations, and atomically enable. Immutable contract/manifest/inventory hashes match; database, setting, availability, registration, ingress, and plan hashes differ. Neither database contains the other client's marker/reference. Per-database lifecycle locks can coexist while a second same-database lock is refused. An injected client-A disable failure rolls back A and changes neither client fingerprint. Successful A disable retains its four settings, two migrations, two tables, and empty evidence while B remains enabled and byte-for-byte unchanged; declarative runtime order excludes Wompi only from A and repeat disable refuses. No registrar/handler execution, secret resolution, provider contact, payment, or runtime publication occurs. Cleanup proves `databases:0 grants:0 staged-project:0 primary:unchanged`. Colombia C3 is complete; C4 Sandbox credentials/contact remains separately owner-gated.
- Colombia C4A acceptance is documentation-only and uses current Wompi-owned sources retrieved 2026-08-23. It records separate Sandbox/Production hosts and four value prefixes; owner-controlled merchant registration, identity, and terms; public-key retrieval of two current acceptance tokens plus contract permalinks; explicit user acceptance of both contracts; private-key Bearer transaction POST; COP, unique reference, integrity signature, email, and Nequi phone requirements; current approved/declined test numbers; asynchronous PENDING plus lookup/event finality; body/header checksum alternatives; dynamic signed properties; and current event retries. It confirms the offline planner already requires both token hashes, contract hash, customer email/phone hashes, exact Sandbox host/path, four setting/reference availability facts, dynamic event verification, and lookup agreement. Focused current-core checks pass 55 C1, 41 Wompi profile, 18 registrar, 31 ingress, and 22 clean-starter assertions; the external package passes 34 offline assertions, while its full package suite stops at the historical C2 expectation that core rejects Wompi, which C3A intentionally superseded. C4B must replace that stale package-owned assertion. Other hard blockers are no merchant-token retrieval operation, public two-contract acceptance UI, transient wire builder/signer, transaction transport, contained lookup parser, or operational event runner. C4B is therefore a credential-free/no-contact preflight gate; C4C account/read-only merchant GET, C4D one approved transaction, C4E declined/event/rotation, and C5 deployment each require separate owner approval. Public documentation retrieval is the only external read; no account, credential, provider API request, transaction, personal data, database, package, demo, or deployment effect occurs.
- Colombia C4B1 acceptance publishes separate external Wompi package `0.1.1` at `7e4f8cb337d746b5a483932108e5dbcd109d7d86` and changes no core runtime helper. Its pure request planner accepts only client-local public-key availability plus SHA-256 and fixes Sandbox `GET /v1/merchants/{public_key}` without constructing a final path. Its synthetic response gate accepts only the current `presigned_acceptance`/`presigned_personal_data_auth` projection, requires distinct bounded tokens, exact types, and distinct Wompi-controlled HTTPS contract links without credentials/query/fragment, and returns only ordered links plus token/contract/response/projection hashes. Raw tokens, presentation, consent, persistence, network/provider effects, payment, browser, order mutation, and retry remain false. The external suite passes 34 existing offline, 64 package/current-core, and 29 C4B1 assertions with 11 integrity files and seven source/package pairs. Core exact-package adoption passes 43 profile, 18 registrar, 31 ingress, and 22 clean-starter assertions. Disposable adoption passes 16 install/registrar, existing Stripe 24 plus Wompi 17 atomic-enable, and 21 two-client assertions; cleanup proves `database:0 grant:0 staged-project:0 primary:unchanged` or `databases:0 grants:0 staged-project:0 primary:unchanged` as applicable. No account/dashboard, credential/personal value, provider API request, transaction, retained database/client migration, package copy into the starter, demo, or deployment effect occurs. C4B2 remains a credential-free/no-contact consent plus transient wire/signature gate.
- Colombia C4B2 acceptance publishes separate Wompi package `0.1.2` at `fdbf88145c5858c313f6f2a3e50137e54801d683` and changes no core runtime helper. Its pure presentation model fixes exactly two ordered Wompi-controlled HTTPS links plus two separately named required controls with no HTML/token/browser/consent effect. Consent evidence binds four separate presentation/acceptance facts to order, subject hash, presentation/contract/token hashes, nonce, and an exact 15-minute window. Its transient Sandbox-only wire preflight reconstructs the existing closed transaction plan, verifies synthetic email/phone/token hashes, requires test private/integrity value families, creates the exact Bearer header, integrity signature, Nequi body, and POST request inside one pure call, then returns only field names and domain-separated/double-hashed evidence. Actual signature, email/phone and their individual hashes, raw tokens, keys, header, body, and request are not returned or persisted. The external suite passes 34 C2, 70 package/current-core, 29 C4B1, and 49 C4B2 assertions with 14 integrity files and ten source/package pairs. Core adoption passes 46 profile, 18 registrar, 31 ingress, and 22 clean-starter assertions. Disposable adoption passes 16 install/registrar, existing Stripe 24 plus Wompi 17 atomic-enable, and 21 two-client assertions with exact cleanup and the configured primary unchanged. No account/dashboard, client credential/personal value resolution, provider API request, transaction, retained database/client migration, starter package copy, demo, or deployment effect occurs. C4B3 remains credential-free/no-contact transaction-create/lookup response containment.
- Colombia C4B3 acceptance publishes separate Wompi package `0.1.3` at `277760e6cd727fab6795524b654ab55c4597bfa2` and changes no core runtime helper. Strict create containment requires valid C2 plan/C4B2 wire evidence, HTTP 201, exact id/reference/amount/COP/NEQUI/PENDING agreement, and a bounded documented data object; optional email/merchant/payment-method/time/status-message detail is validated then discarded. Strict lookup requires untampered create evidence, HTTP 200, and exact identity/reference/amount/currency/method agreement; PENDING, APPROVED, DECLINED, and ERROR map only to proposed pending/paid/failed, while VOIDED is refused. Raw body/header/personal/provider detail, payment verification, event agreement, payment/order/provider mutation, and retry remain false. The external suite passes 34 C2, 72 package/current-core, 29 C4B1, 49 C4B2, and 48 C4B3 assertions with 15 integrity files and eleven source/package pairs. Core adoption passes 47 profile, 18 registrar, 31 ingress, and 22 clean-starter assertions. Disposable adoption passes 16 install/registrar, existing Stripe 24 plus Wompi 17 atomic-enable, and 21 two-client assertions with exact cleanup and the configured primary unchanged. No account/dashboard, client credential/personal value resolution, provider API request, transaction, retained database/client migration, starter package copy, demo, or deployment effect occurs. C4B4 remains credential-free/no-contact one-attempt authorization/claim/state.
- Colombia C4B4A acceptance publishes separate Wompi package `0.1.4` at `5f372b3a2e35723f638a03cf089deedc238c99a4` and changes no core runtime helper. Its pure sealed-double-only authorization binds exact C2 plan/C4B2 wire, client/database/actor/secret-availability/nonce hashes, fresh authority, enabled package/Store Lite facts, one attempt/no retry, explicit network/provider/order denial, and a maximum 15-minute window. First-claim preparation requires a distinct nonce, attempt one, and empty prior-claim evidence, sets remaining attempts to zero, and requires claim persistence, replay protection, and execution to remain false. Exact C4B3 create/lookup evidence projects only claim-prepared, pending-observed, approved-observed, or failed-observed state; payment verification, event agreement, provider/order mutation, and retry remain false. The external suite passes 34 C2, 74 package/current-core, 29 C4B1, 49 C4B2, 48 C4B3, and 52 C4B4A assertions with 16 integrity files and twelve source/package pairs. Core adoption passes 48 profile, 18 registrar, 31 ingress, and 22 clean-starter assertions. Disposable adoption passes 16 install/registrar, existing Stripe 24 plus Wompi 17 atomic-enable, and 21 two-client assertions with exact cleanup and the configured primary unchanged. No account/dashboard, client credential/personal value resolution, provider API request, transaction, retained database/client migration, starter package copy, demo, or deployment effect occurs. C4B4B remains credential-free/no-contact atomic durable claim/replay protection.
- Colombia C4B4B acceptance adds one core helper and no migration. It validates exact C4B4A authorization/claim evidence and revalidates canonical client/database/actor/setting hashes, current Owner, `addons.enable`, exact `store.orders.manage` grant/declaration, enabled Wompi `0.1.4` and Store Lite `0.1.35`, and absence of both nonce-derived action rows. Planning is zero-write. Under lifecycle/package/actor/capability/installation/setting/action locks, one transaction records authorization plus claim and two value-free audits in the existing immutable ledger. Its 24-assertion disposable rehearsal proves exact commit, replay refusal, stale confirmation, authority revocation, package disablement, client-scope drift, tampered evidence, nested-transaction refusal, claim-audit rollback of all new rows/audits, one clean recovery, forbidden network/secret/package-invocation primitives, and cleanup `database:0 grant:0 staged-project:0 primary:unchanged`. Execution, package handler, secret resolution, network/provider, payment/order, and retry effects remain false. C4B4C remains credential-free/no-contact sealed transport-double start/result execution.
- Colombia C4B4C acceptance adds one core-owned final sealed double and durable start/result helper with no migration, package handler, or secret resolver. It requires exact C4B4B authorization/claim rows and current client/Owner/order/package/settings state. Planning builds only a hash-bound request and writes nothing. Apply commits one nonce-derived start plus audit before exactly one in-memory double call, then validates and records one bounded completed or indeterminate result plus audit. Its 38-assertion disposable rehearsal proves missing-claim refusal, exact four-row/four-audit success, replay refusal before a second call, start-audit rollback/recovery, permanent no-retry after result-audit failure, throwing/malformed outcome containment, changed-start refusal, forbidden request/environment/secret/package/network/shell/delay primitives, and cleanup `database:0 grant:0 staged-project:0 primary:unchanged`. `executionPerformed` means only the in-memory double ran; network/provider contact/mutation, transaction creation, payment verification/application, event agreement, order mutation, and retry remain false. C4B4D separately owns the credential-free/no-contact CLI plus network-disabled rehearsal.
- Colombia C4B4D acceptance adds a CLI-only command, pure 55-assertion source contract, runtime network probe, disposable evidence fixture, and network-disabled rehearsal with no migration or package change. Default dry run revalidates exact C4B4A authorization/claim evidence, C4B4B durable rows, and C4B4C request/start identities while writing nothing. Apply requires every bounded database/package/state, client/database/actor/order/plan/wire/authorization/claim/request/start, backup, one-attempt/no-retry, network-disabled, and provider/transaction/payment/order-denial confirmation before it may construct one final sealed double and call the runner once. The rehearsal disables URL streams plus common cURL/socket functions, removes proxy and secret-value environments, stages exact core plus Store Lite `0.1.35` and Wompi `0.1.4`, applies all 46 migrations to a fresh database, proves dry run preserves two rows/two audits, incomplete apply refusal, one apply producing four rows/four audits, empty Wompi attempt/event tables, replay refusal, and cleanup `database:0 grant:0 staged-project:0 evidence:0 environment:clear source-repositories:unchanged primary:unchanged`. No account/dashboard, real credential/personal value, provider request, transaction, payment, order, retained database/client migration, starter package copy, demo, or deployment effect occurs. C4C then owns the separately gated account/read-only provider ladder.
- Colombia C4C1 acceptance publishes external Wompi `0.1.5` at `cc2ddd03ab54f663a089f7d059d802180e555d15` with one read-only Sandbox merchant-contract operation, TLS/no-redirect/no-proxy/no-auth/no-retry constraints, strict size/time ceilings, contained links/hashes, and a sealed no-network double. The external suite passes 321 assertions with 19 exact integrity files; the real transport is not invoked. Core profile adoption passes 51 assertions. Its 13-assertion disposable rehearsal stages exact Store Lite `0.1.35` plus Wompi `0.1.5`, applies all 47 migrations, installs disabled then enables with a synthetic public key and three opaque references, verifies registrar/probe state, invokes only the sealed double, retains no raw key/token/body/header, keeps Wompi attempt/event tables empty, and cleans `database:0 grant:0 staged-project:0 primary:unchanged`. No account/dashboard, real key value, DNS, TLS, HTTP, Wompi request, transaction, payment, order, retained database/client migration, starter package copy, demo, or deployment effect occurs. C4C2 remains the CLI/no-contact gate before C4C3 one owner-confirmed real read-only GET.
- Colombia C4C2 acceptance adds a hash-only current-client preflight, CLI-only command, 67-assertion source contract, read-only state checker, and network-disabled operator rehearsal. Default dry run validates exact current database/Owner/order/package/settings plus public-key/reference/merchant/preflight hashes while loading no package PHP and writing nothing. Fully confirmed apply loads only five reviewed merchant class files and invokes one sealed no-network double; it cannot register/invoke the package adapter or construct the real cURL transport. The fresh database applies all 47 migrations and exact Store Lite `0.1.35` plus Wompi `0.1.5`. Dry run, incomplete-confirmation refusal, one double apply, no raw public key output, and Wompi attempt/event/action/audit counts `0:0:0:0` pass. Cleanup is `database:0 grant:0 staged-project:0 primary:unchanged`. Durable attempt, replay protection, real provider contact, provider mutation, transaction, payment, event, order, retry, account/dashboard, retained database/client migration, starter package copy, demo, and deployment remain false. C4C3A must add durable real-target execution before C4C3B one owner-confirmed GET.
- Atomic add-on disablement acceptance runs only in the uniquely named disposable database and uses temporary validated first-party packages outside the clean starter. It requires exact Owner `addons.disable` authority, deterministic current-registry evidence, an exact enabled-dependent blocker, database-wide lifecycle-lock exclusion across connections, stale-plan refusal, audit and post-state-update injected-failure rollback, an atomic `installed_disabled` state and bounded audit commit, zero registrar or migration execution, exclusion of both combined-package component and service registrations from later request bootstrap, dependent-first unblocking, repeat refusal, CLI-only confirmations, and exact cleanup.
- Add-on upgrade/recovery acceptance runs only in the uniquely named disposable database and a temporary first-party package outside the starter. Its 24 assertions require exact Owner `addons.upgrade` authority, a strictly higher same-type target, disabled starting state, deterministic non-executing planning, explicit historical-migration checksum-drift refusal, compatible stored setting definitions, stale-plan and start-audit refusal, a forced mid-upgrade migration failure, explicit non-loadable `upgrade_failed` reporting, preserved old identity/data/settings, remaining-only resume planning, completion-audit rollback, zero-pending final recovery, exact target identity/ledger/audit postconditions, CLI backup/current-target/state confirmations, repeat refusal, and exact fixture cleanup.
- The separate real Store Lite upgrade rehearsal stages historical 0.1.28 and current 0.1.29 package payloads outside the starter. Its 14 assertions require all eight historical migration paths and checksums unchanged, five compatible stored settings, one preserved real order, an exact two-migration target plan, a forced failure after the fulfillment-status index, non-loadable old-identity `upgrade_failed`, remaining-only payment-status index recovery, exact 0.1.29 disabled registry/ledger/audit evidence, repeat refusal, all 46 core migrations, and cleanup `database:0 grant:0 staged-project:0 primary:unchanged`.
- The separate Store Lite Release C3 isolation rehearsal stages current clean core plus external Store Lite 0.1.29 and creates two fresh databases. Its 14 assertions require database-bound install and enable plans, all 46 core and 10 package migrations per client, distinct USD/pickup and COP/delivery settings and products, no cross-client product reads, unchanged opposite-client fingerprints after mutation in both directions, client-local disable/re-enable runtime behavior, identical package registrar evidence, and cleanup `databases:0 grants:0 staged-project:0 primary:unchanged` using a schema-neutral full configured-primary hash.
- The hosted Store Lite basic-demo closeout is read-only release evidence for `demo.red-sphere.com`, not an automated acceptance fixture. It requires RED-CMS 5.1.0, the separately installed Store Lite 0.1.31 package, nine public products and controls, the exact nine-choice Size/Color scarf, Product and Cart authoring, Products and Orders tools, responsive Checkout output, and clean browser logs. It submits no new order or guest personal data and authorizes no other client installation or database.
- Operational add-on lifecycle acceptance runs only in a uniquely named disposable database and a temporary generic content package outside the clean starter. Its 21 assertions prove non-executing discovery; exact migration, setting, table, and data evidence; deterministic planning; stale-plan and forced-registrar refusal; incomplete-registry refusal; registrar-time MyISAM drift refusal before lifecycle mutation; audit rollback; successful exact registrar validation; later request bootstrap; non-executing disable; settings, migration, code, and business-row preservation; disabled bootstrap exclusion; identical-evidence re-enable; exactly two enable and one disable audits; CLI-only confirmations; and exact database/grant/package cleanup.
- Read-only public-utility acceptance begins with the dependency-free profile
  fixture. It requires a cross-cutting package with bounded services,
  package-owned migrations, exact static public `GET` routes, and immutable
  public assets; refuses unsafe/placeholder routes, mutations, administrator
  assets, and wrong package types; proves exact service/route registrar shape;
  and verifies that the front controller classifies declared GET routes before
  public mutations. The separate Site Search local rehearsal then uses one
  fresh disposable database for all 46 core migrations, exact package
  integrity, dry-run-first install/enable, profile-specific registrar
  validation, atomic derived-index replacement, 200 bounded JSON, short-query
  empty success, 405 method refusal, checksum assets, and desktop/mobile AJAX,
  Escape, console/network, and overflow checks. The package rehearsal is not a
  clean-core fixture and must remove its staged package, database, and grant.
  A separate 30-assertion real-package rehearsal proves post-commit
  create/update/deactivate/restore/move/delete refreshes, removal of ineligible
  rows, public-URL replacement, invalid-notification refusal, active/inactive
  hierarchy repair, future-start and expiry transitions, advisory-lock
  concurrency refusal, and exact Article/index/hierarchy cleanup in another
  fresh disposable database.
  The separate seven-assertion scale rehearsal inserts exactly 50,000
  disposable Article sources, rebuilds 50,001 derived rows atomically in
  18,726.82 ms, samples 20 real searches at 125.19 ms local p95 and 128.62 ms
  maximum, then removes every scale source and returns the index to its exact
  one-document baseline. These timings are local development evidence, not a
  production SLA or supported-hosting/browser measurement.
  The separate 16-assertion two-package rehearsal validates Store Lite 0.1.36
  at 39/39 files and Site Search 0.1.3 at 9/9 files, installs and enables both,
  indexes and finds one real placed Product, refreshes changed text, removes an
  unavailable Product, removes stale provider rows on Store Lite disable,
  restores them after re-enable, and cleans its Article/product/placement/index
  fixtures exactly. No price, currency, stock, availability value, SKU, cart,
  order, payment, customer, administrator, setting, secret, or database
  identity crosses the typed provider result.
- The separate real Store Lite operational lifecycle rehearsal stages current clean core plus externally distributed Store Lite 0.1.28 in a temporary supported-server project. Its 10 assertions run all 46 core and 8 package migrations, fingerprint all 15 package tables and seeded product/cart rows, require exact real registrar evidence, prove Product/Cart/create-guest-order registration after enable, prove disabled bootstrap exclusion and data preservation, reproduce the registrar hash on re-enable, record exactly two enable and one disable audits, and remove the staged project and disposable database/grant. It does not alter the Store Lite source repository, clean starter, hosted demo, or any client installation.
- A full-table checksum comparison makes HTTP 403 alone insufficient: every allowed/denied permission request must also leave all 36 tables unchanged.
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
- Rollback acceptance requires the legacy `no` response, removal of the trigger, identical pre/post checksums across all 36 tables, exact initial parent/child values, a successful control update after trigger removal, canonical count restoration, and zero targeted database/trigger artifacts.
- Cleanup failure converts an otherwise successful run into a failure.
- Cleanup re-reads the application account's grants after revoke/drop and fails if the disposable database grant is still present.
- The primary database is re-read after cleanup and must match its starting isolation snapshot.

The acceptance suite does not alter production or replace the separate backup, deployment, migration, and rollback procedures required for HostGator.
