# RED-CMS 5.1

RED-CMS is a lightweight PHP and MySQL content management system for structured, template-driven websites. Version 5.1 extends the completed Version 5.0 Bonsai modernization with per-page SEO compatibility and an optional, per-client add-on platform while preserving public URLs, existing database table names, and the compatibility-first deployment model.

The current release adds a consistent administrator workspace, standard theme packages, visual page structures, reusable layouts, content version history, safer database migrations, and repeatable acceptance testing.

## Release Status

RED-CMS 5.1.0 was formally released on 2026-08-15 as
[`v5.1.0`](https://github.com/orojas01-glitch/redcms/releases/tag/v5.1.0).
The clean installer, all 46 migrations, normalized schema, canonical routes,
add-on and CMS lifecycles, forced rollback, runtime log, and exact disposable
database/grant cleanup passed against a fresh temporary current-schema
baseline. See [RED-CMS 5.1.0 Release Notes](docs/RELEASE-NOTES-5.1.0.md).

The separately distributed Store Lite 0.1.31 basic-demo proof is complete on
its isolated demo installation. Store Lite remains optional and is not bundled
with the clean starter.

Current Version 5.1 and Store Lite milestone map:
[`docs/ADD-ON-PLATFORM-STATUS.md`](docs/ADD-ON-PLATFORM-STATUS.md).

The optional post-release payment-adapter track has completed P3A and the
separately distributed Store Lite package has completed P3B through version
0.1.35. Core can
recognize the closed Stripe Checkout adapter manifest, refresh exact
Owner/same-database Store Lite/migration/InnoDB evidence, and validate one
adapter plus one non-routable server-event registration. The temporary registry
is discarded without invoking a handler. A separate closed ingress contract
can then bind explicit exact `POST` transport facts, preserve at most 65,536
unmodified raw body bytes and the complete signature header for a future
adapter verifier, and expose only value-free metadata. It reads no PHP request
global, parses no JSON, verifies no provider signature, exposes no route,
resolves no secret, and contacts no provider. A separate CLI-only P3A-5 runner
now requires exact stored configuration and value-free availability evidence
for both opaque secret references, recomputes the complete plan under lifecycle
and package locks, and atomically records only the reviewed adapter's
`installed_disabled` to `enabled` transition plus one bounded audit fact. It
still invokes no registered handler, resolves no secret value, publishes no
route, and opens no network connection. Store Lite now owns the
provider-neutral payment-event transition decision, append-only history
migration, transactional writer/service, and disposable lifecycle rehearsal.
P3C-1, the dependency-free foundation of the separately distributed Stripe
Checkout adapter, has now completed external P3C packaging plus P3D-1 through
P3D-5 offline installation, enablement, runtime ownership, and synthetic-secret
bootstrap proofs. Core P3D-6 now adds the reusable typed adapter invocation
boundary, while the external Stripe handler remains refusal-only. No provider
request, public event dispatch, payment, or client deployment is bundled or
activated. P3E-7 now adds a core-owned CLI-only Owner revalidation and atomic
one-time nonce authorization boundary for the exact external P3E-6 evidence.
It reuses the immutable administrator-action ledger, commits one value-free
audit fact, and still performs no credential resolution, network request,
provider contact, Checkout creation, payment, webhook, Store Lite mutation, or
client deployment. See
[P3E-7 Provider-Contact Authorization](docs/PAYMENT-ADAPTER-P3E7-AUTHORIZATION.md).
P3E-8A now adds the separate atomic one-attempt claim. It requires that exact
P3E-7 row, repeats current Owner and enabled same-database package validation,
and commits one nonce-bound claim row plus one value-free audit fact. The claim
is not reusable and does not extend the authorization expiry. It still resolves
no credential, invokes no package handler, and contacts no provider. See
[P3E-8A Provider-Contact Attempt Claim](docs/PAYMENT-ADAPTER-P3E8A-CLAIM.md).
P3E-8B2 now consumes that exact claim only through a sealed in-process
loopback rehearsal. It commits an immutable execution-start marker before any
registrar, secret, or handler call; restricts runtime access to exactly
`stripe.secret-key`; invokes one typed loopback operation; and records only a
bounded result. Once start commits, failure never authorizes retry. The gate
opens no network connection and contacts no provider. See
[P3E-8B2 Provider-Contact Loopback Execution](docs/PAYMENT-ADAPTER-P3E8B2-LOOPBACK-EXECUTION.md).
P3E-8B3B now adds the matching real-package integration without network
contact. The shared evidence gate accepts only the legacy `0.1.1/disabled`
profile or adapter `0.1.3/synthetic_only`. After the same immutable start
commit, core resolves only `stripe.secret-key` and invokes the registered
`provider-contact.read-only-probe-synthetic` operation. Both adapter and core
validate the bounded in-memory result with network, provider, retry, and
mutation false. See
[P3E-8B3B Synthetic Package Execution](docs/PAYMENT-ADAPTER-P3E8B3B-SYNTHETIC-EXECUTION.md).
P3E-8B3C2 now adds the exact core runner for adapter
`0.1.4/provider_read_only`. It preserves the immutable start, scoped-secret,
bounded-outcome, and permanent no-retry rules, while acceptance substitutes an
integrity-checked in-memory handler. No DNS, HTTP, Stripe request, public
caller, payment mutation, browser flow, client activation, or deployment is
performed by this gate. See
[P3E-8B3C2 Provider-Operation Runner](docs/PAYMENT-ADAPTER-P3E8B3C2-PROVIDER-RUNNER.md).
P3E-8B3C3A now adds the matching server-local operator command. It defaults
to a value-free dry run and requires exact database, package, state, evidence,
backup, operation, target, restricted-test, one-attempt, no-retry, and
no-mutation confirmations before its single B3C2 call site can run. The
40-assertion command contract performs no provider contact; the first real
restricted-key GET remains separately gated B3C3B work. See
[P3E-8B3C3A Server-Local Operator Command](docs/PAYMENT-ADAPTER-P3E8B3C3A-OPERATOR-COMMAND.md).
P3E-8B3C3B has now completed the separately authorized real sandbox rehearsal:
one exact restricted-key GET returned the expected bounded `404` resource miss,
Stripe Workbench showed one matching request, and local evidence/cleanup checks
passed with no credential retention, retry, mutation, client state, or
deployment. See
[P3E-8B3C3B Restricted-Key Sandbox Execution](docs/PAYMENT-ADAPTER-P3E8B3C3B-SANDBOX-EXECUTION.md).
The next frontier is P3E-9A: a pure non-executing Checkout-creation contract.
The completed read-only authorization cannot be widened or reused because it
binds mutation and Checkout creation false. Real write credentials, Checkout
Session creation, payment, webhook, browser checkout, hosted-demo changes,
client deployment, and P4 remain separately gated. See
[P3E-9 Sandbox Checkout-Creation Frontier](docs/PAYMENT-ADAPTER-P3E9-SANDBOX-CHECKOUT-CREATION-FRONTIER.md).
P3E-9A has now completed in the separately distributed adapter as a pure
source-only contract. Its 53 focused and 921 aggregate assertions prove
bounded expiry, read-only-profile refusal, synthetic open/unpaid response
validation, and Checkout-URL removal while leaving installable adapter `0.1.4`
unchanged. At that point, P3E-9B synthetic-only package/core integration was
next; no key, network request, Checkout Session, payment, webhook, browser
flow, client state, or deployment was authorized. See
[P3E-9A Contract Adoption](docs/PAYMENT-ADAPTER-P3E9A-CONTRACT-ADOPTION.md).
External adapter P3E-9B1 now advances the package to `0.1.5` with only
`checkout.create-sandbox-synthetic`. Core P3E-9B2 adds the matching
non-persistent runner: it validates the exact package and P3E-9A input,
integrity-checks the registrar, invokes only the synthetic operation through
the typed adapter boundary, and accepts only bounded no-network/no-mutation
facts. It has no database, authority ledger, credential resolver, route, CLI,
or request-bootstrap caller. P3E-9B is therefore complete. Core P3E-9C1 now
adds a distinct, nonce-bound, at-most-fifteen-minute authorization for one
future Sandbox Checkout-creation attempt. Fresh database-backed Owner,
`addons.enable`, exact Store Lite `store.orders.manage`, and exact enabled
package state are revalidated before one immutable authorization row and one
value-free audit fact commit atomically. Core P3E-9C2 now consumes only that
exact persisted authorization into one distinct nonce-bound claim plus one
value-free audit under fresh authority and package-state checks. It performs
no execution start, secret resolution, network request, Checkout creation,
payment, webhook, or Store Lite mutation. P3E-9C3A now adds immutable start and
bounded result evidence around one final core-owned in-memory transport double.
It commits start before invocation and permanently refuses retry afterward,
while network, provider mutation, real Checkout creation, payment, webhook,
and Store Lite mutation remain false. P3E-9C3B1 now adds the CLI-only,
dry-run-first command contract pinned to that final double; its 45 assertions
prove exact confirmations and absence of credentials, network, package
handlers, or public bridges. P3E-9C3B2 has now rehearsed dry run, confirmation
refusal, one exact in-memory-double apply, replay refusal, and exact cleanup.
P3E-9C is complete. P3E-9D0 defines the pure real-POST request contract without
a database, key, or network. External adapter P3E-9D1 is complete through
canonical-hash-compatible version `0.1.7`. Core P3E-9D2 now invokes only its
non-executing preflight operation, strictly contains the typed response, and
derives distinct start/result identity hashes while execution remains false.
P3E-9D3A now adds the CLI-only, dry-run-first operator command with exact
identity and no-effect confirmations. P3E-9D3B now completes the disposable
cross-repository no-contact rehearsal with exact committed core, adapter, and
Store Lite package sources. P3E-9D3 is complete; the real Sandbox POST remains
separately gated. External adapter P3E-9D4A is now complete at `0.1.8`: it adds
the exact uninvoked provider-write operation with offline and local-loopback
acceptance, but Stripe was not contacted. D4B durable core execution is now
complete through fresh authority/claim, start-before-access, one sealed
in-memory invocation, bounded result, and permanent no-retry acceptance. D4C
CLI/no-contact rehearsal is next, and D4D remains one separately authorized
real Sandbox POST. See
[P3E-9B2 Synthetic Core Runner](docs/PAYMENT-ADAPTER-P3E9B2-SYNTHETIC-CORE-RUNNER.md)
and
[P3E-9C1 Mutation Authorization](docs/PAYMENT-ADAPTER-P3E9C1-MUTATION-AUTHORIZATION.md)
and
[P3E-9C2 Mutation Claim](docs/PAYMENT-ADAPTER-P3E9C2-MUTATION-CLAIM.md)
and
[P3E-9C3A Transport-Double Runner](docs/PAYMENT-ADAPTER-P3E9C3A-TRANSPORT-DOUBLE-RUNNER.md)
and
[P3E-9C3B1 Operator Command](docs/PAYMENT-ADAPTER-P3E9C3B1-OPERATOR-COMMAND.md)
and
[P3E-9C3B2 Operator Rehearsal](docs/PAYMENT-ADAPTER-P3E9C3B2-OPERATOR-REHEARSAL.md)
and
[P3E-9D0 Real POST Preflight](docs/PAYMENT-ADAPTER-P3E9D0-REAL-POST-PREFLIGHT.md)
and
[P3E-9D2 Core Preflight Runner](docs/PAYMENT-ADAPTER-P3E9D2-CORE-PREFLIGHT-RUNNER.md)
and
[P3E-9D3A Operator Command](docs/PAYMENT-ADAPTER-P3E9D3A-OPERATOR-COMMAND.md)
and
[P3E-9D3B No-Contact Rehearsal](docs/PAYMENT-ADAPTER-P3E9D3B-NO-CONTACT-REHEARSAL.md)
and
[P3E-9D4 Real Creation Plan](docs/PAYMENT-ADAPTER-P3E9D4-REAL-CREATION-PLAN.md).

D4B was internally review-gated. P3E-9D4B1 supplies fresh adapter-`0.1.8`
authorization and one-attempt claim. P3E-9D4B2 now adds durable start/result,
scoped secret resolution, and one sealed in-memory handler invocation; no
network or provider request occurred. See
[P3E-9D4B1 Authority And Claim](docs/PAYMENT-ADAPTER-P3E9D4B1-REAL-MUTATION-AUTHORITY.md)
and
[P3E-9D4B2 Durable Execution](docs/PAYMENT-ADAPTER-P3E9D4B2-DURABLE-EXECUTION.md).

RED-CMS 5.0 Bonsai and Milestone 5 are complete on `main`. The release
checkpoint was merged through [pull request #2](https://github.com/orojas01-glitch/redcms/pull/2)
on July 25, 2026.

Version 5.1 development includes per-page SEO compatibility with nullable
overrides, generated fallbacks, and constrained typed JSON-LD; non-executing
add-on trust validation; persisted Owner authorization; and per-client package
registry/migration-ledger storage with read-only drift reporting. A
server-local Owner-authorized installer can apply reviewed, checksum-verified
package migrations and always records the package as `installed_disabled`; it
never executes package PHP. A separate dry-run-first Owner-authorized upgrade
command accepts only a disabled package at a strictly higher trusted version,
preserves its append-only migration ledger and compatible stored settings,
and recovers explicitly from partial MySQL DDL without loading package PHP.
A separate read-only Owner-authorized preflight can
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
Operational writable route/tool actions, uninstall/purge, payment, member
access, editorial workflow, notifications,
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
- Internal typed add-on adapter invocation with exact enabled runtime
  ownership, package-bound secret access, bounded request/result objects, and
  containment of output, exceptions, buffer changes, malformed results, and
  secret disclosure
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
- Pure core-owned public-mutation form UI composition. It derives one static
  action and only declared hidden identifier, bounded positive-integer, and
  identifier-select controls with at most 128 choices from a validated
  declaration, a bounded package presentation model, and same-subject issued
  CSRF/idempotency result shapes.
  Core escapes all markup; the opaque values are fetch-controller attributes,
  not package form fields. It reads no request/database/package state, emits
  no output/header, and is not linked from the front controller
- Core-owned public-mutation form evidence bootstrap. It validates the entire
  declaration and package presentation before ensuring one opaque browser
  subject and issuing same-subject CSRF/idempotency evidence, then composes the
  existing pure form model. Any partial issuance is exactly compensated; it
  reads no request/session/cookie globals, loads no package code, emits no
  header or HTML, and remains unlinked from the front controller
- Core-owned public-component mutation-presentation boundary. An enabled
  component may now return one exact data-only route/mutation/label/field model
  beside its existing title, summary, and optional facts. Core accepts only
  hidden identifiers, bounded positive integers, and identifier selects capped
  at 128 unique choices; reserved commercial/authority keys, extra values,
  malformed labels, duplicates, and forged selections fail the entire view.
  The current renderer deliberately emits only the existing product display:
  it creates no form, evidence, endpoint, script, response state, or Store Lite
  behavior
- Core-owned public-component form integration. Given one already returned
  component view, exact placement context, explicit database connection, and
  optional subject-cookie value, core revalidates component/route/mutation/
  state-loader ownership, derives the placement-bound form instance, bootstraps
  evidence, and returns escaped markup plus a lifecycle descriptor. It invokes
  no package callback, reads no request global, emits no output/header/cookie,
  and reads no request global or package callback. The generic component
  renderer now invokes it only inside the core-owned request-local page
  coordinator when the supported endpoint gate is active
- Core-owned public-mutation browser controller. It validates one
  same-origin form configuration, removes opaque evidence from DOM attributes,
  captures and freezes one canonical command per idempotency key, and permits
  only exact-body retry after transient network, rate, or availability failure.
  It accepts only the closed JSON response vocabulary and writes generic text
  through `textContent`; it uses no storage, cookies, logs, dynamic code, HTML
  sinks, or external URL. Core delivers it once, and only when at least one
  mutation form was accepted on an explicitly enabled supported-server page
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
- Read-only operational content-package enablement preflight. It accepts only
  exact component/editor and administrator tool/contract coverage, bounded
  forms referencing declared tools, current package migrations, fully
  configured non-secret settings, one-to-one public POST route/mutation
  declarations, InnoDB package tables, and the existing core
  subject/CSRF/rate/idempotency/transaction evidence. It returns only bounded
  counts and hashes, keeps `enableReady` and `activationSupported` false, never
  includes `addon.php`, and leaves registrar validation plus atomic lifecycle
  transition to a separately reviewed gate
- Owner-authorized operational content-package lifecycle. The dry-run-first
  enable command consumes the exact preflight evidence under the lifecycle and
  package locks, executes the trusted registrar without invoking any registered
  handler, requires an exact manifest-to-registry match, and verifies every
  registrar-bound transaction table is present and InnoDB. It then commits the
  `enabled` compare-and-swap and bounded audit fact in one transaction. The
  existing non-executing disable command preserves code, migrations, settings,
  and business data; re-enable must reproduce the same registrar evidence
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
- Explicit shared-host `direct_php` public-mutation ingress adapter. It
  requires the server-local enable flag, canonical HTTPS origin, direct
  server-owned HTTPS fact, exact static `/addons/` POST target, bounded body,
  fixed content metadata, one opaque subject cookie, CSRF token, and
  idempotency key. It ignores Host/forwarding values, rejects encoded or
  ambiguous projected transport, requires the measured PHP body to match its
  projected length, and feeds the unchanged core dispatcher.
  The existing `frankenphp_attested` profile remains the default. This adapter
  is not itself a HostGator deployment or proof that every shared server
  projection is compatible
- Disposable real Apache/FastCGI `direct_php` proof. It starts the host Apache
  2.4 runtime with PHP FastCGI over temporary localhost TLS, confirms the exact
  HTTPS/PHP projection, canonical capture, Host/forwarding isolation, duplicate
  and encoding refusals, Apache chunk normalization, and desktop/mobile browser
  evidence, then builds a validated non-secret deployment-review packet. It
  opens no database, links no dispatcher, loads no package, changes no client
  state, and removes its temporary server, FastCGI process, and private key
- Core-owned public-mutation dispatcher composition. It accepts only
  explicit supported-ingress method/target/capture facts, selects one
  registrar-bound route, verifies the opaque subject and CSRF before decoding
  declared scalar fields, invokes the atomic runner, and returns only the fixed
  response model.
  A narrow front-controller bridge now composes it only for the reserved
  `/addons/` namespace after the explicit server flag, trusted HTTPS origin,
  and selected ingress-profile requirements all pass. The default attested
  profile additionally requires its process-environment HMAC key; the explicit
  direct-PHP profile requires a direct server-owned HTTPS fact and closed PHP
  transport projection. The dispatcher itself still emits no response or
  browser cookie and adds no package, enablement, Store Lite, or client-data
  behavior
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
  invalidate the old subject and CSRF evidence on rotation. The page
  coordinator can now ensure or resolve one subject for all accepted forms and
  a separate fixed emitter owns its response cookie. Disposable and
  supported-server proofs cover issuance, resolve-without-reissue, fixed
  clearance, replacement, and cleanup; this does not authorize a client
  deployment, package enablement, or Store Lite route
- Non-executing per-client public-mutation deployment profile validator. It
  accepts only an operator-owned review packet with one canonical HTTPS origin,
  either pinned FrankenPHP/Caddy attestation facts or pinned Apache/PHP direct
  projection facts, the profile-specific trust and route order, core response/
  cookie ownership, host-only cookie policy, and explicit client-isolation
  flags. It returns a
  deterministic non-secret profile hash and refuses starter-database reuse,
  request-derived trust, package/theme response ownership, policy drift, and
  all dispatcher/package/Store Lite activation flags; it reads no database,
  secret, filesystem, request, or client state
- Core-only public-mutation response emitter. It accepts only the
  existing exact fixed core envelopes, refuses to run after output starts,
  clears and sets only their no-store/nosniff JSON headers, and emits only the
  corresponding fixed bytes. It reads no request/cookie/session state,
  database, runtime, or package code. The supported endpoint bridge is now its
  only front-controller caller; it creates no browser cookie, Store Lite
  behavior, package enablement, or client deployment by itself
- Core-owned non-emitting public-mutation response owner. It composes only an
  already-valid fixed core envelope with the lifecycle bridge's exact subject-
  cookie descriptors, rejects arbitrary headers, policy drift, and body token
  leakage, and returns a deterministic pre-link result. It reads no request,
  database, secret, package, or client state and remains outside `index.php`.
- Fail-closed public-mutation endpoint and page-delivery bridge. It remains
  dormant unless server-local configuration explicitly enables the endpoint
  and provides the canonical HTTPS origin plus one explicit supported ingress
  profile. The default attested profile additionally requires its process-
  environment HMAC key; direct PHP additionally requires the current request's
  direct HTTPS fact. Before theme or session rendering, core may claim only the
  reserved `/addons/` namespace and emit one closed response. On normal `GET`
  pages, core parses the raw subject-cookie header, reuses one subject across
  at most 128 accepted forms, appends only core-rendered form HTML, delivers the
  fixed controller once, and emits only the fixed host-only cookie lifecycle.
  Theme and package code own none of these headers, cookies, scripts, or
  responses
- Non-executing per-client deployment review validator. It binds a reviewed
  profile hash to either non-secret Caddy/FrankenPHP attestation evidence or
  Apache/PHP direct-projection evidence, the matching trust facts, and bounded
  desktop/mobile browser evidence. It reads no deployment file or secret,
  changes no client state, and cannot link the dispatcher.
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
- Portable unsigned-integer schema guards compare semantic integer type and
  unsignedness without depending on the legacy MySQL 5.7 display-width string
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
- Store Lite editable Cart component: the separate Store Lite 0.1.24 package
  stores one restrictive core-parent/title placement, resolves only an existing
  core-owned anonymous subject, and binds each verified current line to pure
  quantity/remove presentations. Core owns their evidence, escaped forms,
  dispatcher, generic completion status, and same-page refresh. The isolated
  supported-server rehearsal passed 100 administrator and 147 public
  desktop/mobile checks through add, quantity update, recalculated totals, and
  removal while the starter retained no Store Lite package, table, setting, or
  business data
- Store Lite guest checkout: the separate Store Lite 0.1.28 package combines
  the Cart collection with one top-level, core-rendered twelve-field checkout
  form. Only configured pickup/delivery and pay-on-receipt choices render. The
  isolated supported-server rehearsal passed 100 administrator and 268 public
  desktop/mobile checks, persisted exact immutable server-derived order/line
  snapshots, proved retry/conflict behavior, and removed every disposable
  runtime artifact while the starter retained no Store Lite package, table,
  setting, order, or customer data

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
php scripts/addon-payment-adapter-preflight-self-test.php
php scripts/addon-payment-adapter-registrar-self-test.php
php scripts/addon-asset-plan-self-test.php
php scripts/addon-component-editor-self-test.php
php scripts/addon-component-editor-renderer-self-test.php
php scripts/addon-admin-tool-form-renderer-self-test.php
php scripts/addon-runtime-self-test.php
php scripts/addon-service-invocation-self-test.php
php scripts/addon-adapter-invocation-self-test.php
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
also dry-run first. In addition to those three constrained profiles, it accepts
the closed operational content-package profile only when the read-only evidence
is exact and current. It requires exact database, package, version, plan,
backup SHA-256, and installed-disabled confirmations before it validates the
fixed registrar. Operational registration must match every declared component,
service, tool, form, route, mutation, loader, writer, creator, deleter, and
state loader exactly; public-mutation table metadata must match its manifest
declaration, and every other registrar-bound transaction table must exist as
InnoDB in the current client database. No registered handler is invoked. The
command then atomically records `enabled` plus its bounded audit fact. Packages
outside the four accepted profiles remain blocked behind a separately reviewed
contract. Payment adapters use a separate narrower command,
`scripts/admin-payment-adapter-enable.php`. Its dry run recomputes the complete
P3A database, registrar, ingress, stored-setting, and opaque-secret-availability
plan. Apply additionally requires exact database, package, version, plan,
nonzero backup checksum, and `installed_disabled` confirmations, then repeats
that plan under lifecycle and package locks before committing only the state
compare-and-swap and value-free audit fact. It never resolves secret bytes,
invokes either registered handler, links the server-event route, or contacts a
provider. The disable command is
likewise CLI-only and dry-run first. It requires the exact Owner
`addons.disable` capability, current
enabled package evidence, plan and nonzero backup checksums, and
`enabled`-state confirmation. Enable and disable transitions share one
database-wide lifecycle lock. Disablement refuses an enabled dependent, never
includes package PHP or runs migrations, and atomically returns the package to
`installed_disabled` with one bounded audit fact. Package code, migration
evidence, settings, and data remain in place, while later request bootstrap no
longer loads the disabled package.

The upgrade command is likewise CLI-only and dry-run first. It requires the
exact Owner `addons.upgrade` capability, current and target versions, database,
package, plan, nonzero backup checksum, and lifecycle-state confirmation. A
package must be disabled before upgrade. Historical migrations must remain in
the target manifest with identical paths and checksums; stored setting keys,
types, and secret classification cannot be removed or changed. Core holds the
database-wide lifecycle and package locks, never includes `addon.php`, applies
only pending checksum-verified package migrations, and finishes
`installed_disabled`. Because MySQL DDL may commit implicitly, failure is
reported as non-loadable `upgrade_failed` rather than a false rollback claim.
Explicit `--resume-failed` revalidates the exact target and applies only the
remaining ledger migrations before atomically replacing registry identity and
recording completion.

The separate opt-in Store Lite upgrade rehearsal applies that boundary to the
historical external 0.1.28 package and the real 0.1.29 target. Two package-owned
order-list indexes are append-only; the rehearsal forces the second migration
to fail, proves the old identity plus one order and five settings remain exact
and non-loadable, then resumes only that migration and finishes 0.1.29 disabled.
It creates and removes one bounded disposable database, scoped grant, and two
staged package projects without changing the configured primary database:

```bash
scripts/store-lite-upgrade-rehearsal.sh
```

The
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
- [RED-CMS 5.1.0 Release Notes](docs/RELEASE-NOTES-5.1.0.md)
- [Roadmap](docs/ROADMAP.md)
- [Theme Author Guide](docs/THEME-AUTHOR-GUIDE.md)
- [Theme Activation Readiness](docs/THEME-ACTIVATION-READINESS.md)
- [Acceptance Suite](docs/ACCEPTANCE-SUITE.md)
- [Operational Form Boundary](docs/OPERATIONAL-FORM-BOUNDARY.md)
- [Member Access Direction](docs/MEMBER-ACCESS-DIRECTION.md)
- [Version 5.1 Add-On Contract](docs/ADD-ON-CONTRACT.md)
- [Public Mutation Boundary](docs/PUBLIC-MUTATION-BOUNDARY.md)
- [Store Lite Direction](docs/STORE-LITE-DIRECTION.md)
- [Store Lite Payment Adapter Direction](docs/PAYMENT-ADAPTER-DIRECTION.md)
- [Store Lite Payment Adapter P1 Decision](docs/PAYMENT-ADAPTER-P1-DECISION.md)
- [Store Lite Payment Adapter P2 Fixture](docs/PAYMENT-ADAPTER-P2-FIXTURE.md)
- [Store Lite Payment Adapter P3 Sandbox Proposal](docs/PAYMENT-ADAPTER-P3-SANDBOX-PROPOSAL.md)
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
