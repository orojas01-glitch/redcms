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
component dispatch is implemented. Services can be invoked only through a
non-HTTP core boundary that requires exact enabled request-local ownership,
supplies one immutable typed request, accepts one typed result, and bounds all
JSON-compatible values. Exact static public `GET` routes now have a separate
core-owned JSON dispatcher with the same request-local ownership and
containment discipline. It exposes no session, database, member,
administrator, unsafe-method, placeholder, HTML, redirect, upload, or arbitrary
header surface, and route-bearing packages remain ineligible for current
enablement. Read-only administrator tools now require a data-only manifest
mapping, exact enabled registrar owner, and fresh case-sensitive package grant;
core accepts only a bounded text model and renders escaped display-only markup
through a POST/CSRF endpoint. Owner/lifecycle/legacy authority does not imply
tool access. The separate non-executing administrator action preflight now
requires an explicitly declared `POST`/CSRF-required action, matching tool and
action runtime owners, a fresh action-specific grant, fixed
`once-per-target` idempotency, and one numeric target; it returns deterministic
metadata evidence without invoking a package action or writing state. A
separate internal atomic runner now revalidates exact target-state evidence
under lifecycle/package locks, reserves a per-client action/target ledger key,
contains one registrar action and state reload, and commits only an exact
changed postcondition with a value-free audit fact. The separate core-owned,
unlinked administrator endpoint validates POST/session/CSRF itself, derives the
action plan server-side, and returns only bounded value-free outcomes; it adds
no administrator control, form, or public route. The separately documented
public-mutation boundary now validates optional closed declaration metadata and
returns value-free non-executing preflight evidence for a future static-POST,
anonymous, CSRF/idempotency/rate-limited core path. It still adds no dispatcher,
emitted cookie/header or session access, package behavior, or enablement change.
Its separate read-only live-data preflight can now bind one trusted,
`installed_disabled` package declaration to current per-client migration,
InnoDB-table, typed-setting, opaque-secret-availability, and exact core-owned
anonymous-subject/CSRF/rate-limit/idempotency/execution storage evidence. It returns
hashes and counts only, and still cannot enable, dispatch, resolve a secret,
issue idempotency material, load package code, or write package state. The
separate core-only subject/CSRF foundation stores only SHA-256 digests of opaque 256-bit values in
two empty generic per-client tables. It returns a future endpoint's host-only
secure cookie descriptor and a declaration/database-scoped CSRF value. The
separate fixed-window rate-limit foundation adds one empty core table holding
only an opaque subject relation, a SHA-256 declaration/database scope, window
facts, and a bounded count; it admits at most 12 requests per 60 seconds for
one client, declared route, and subject. Neither helper has a dispatcher or
endpoint, emits a cookie/header, reads a browser cookie or session, executes
package code, or changes lifecycle or Store Lite state.
The separate opaque idempotency-key foundation adds one empty core table holding
only an opaque subject relation, a SHA-256 declaration/database scope, a
SHA-256 key digest, and expiry facts. It can issue or resolve one 10-minute
core key but cannot consume it or record a replay result itself. The new fifth
empty core execution ledger holds only an idempotency-key relation, keyed HMAC
command/state evidence, a bounded outcome, and completion time. Its internal
atomic runner uses a trusted in-memory first-party registrar binding plus typed
input from a future dispatcher, locks lifecycle/package state, verifies CSRF,
key, rate, and server-derived postconditions, then commits package state,
replay evidence, and a value-free anonymous audit fact together. It adds no
endpoint, emitted response, browser behavior, route execution, public package fixture,
or Store Lite state; its callback connection is a reviewed first-party boundary,
not a database sandbox.
The separate pure response contract now turns only the runner's two bounded
outcomes and five generic refusal classes into fixed JSON envelopes with
no-store, nosniff, content-type, length, and method-allow headers. It redacts
all package, route, mutation, subject, token, key, replay, state, cart, order,
plan, and internal-failure evidence. It is not a dispatcher or emitter: it
does not parse a request, read a cookie/session, access a database, load a
package, set a header/cookie, or change lifecycle, enablement, or Store Lite
state.
The separate pure declared-form decoder accepts only one validated in-memory
declaration plus canonical raw URL-encoded package fields, producing a sorted
typed scalar map or no values. It rejects duplicate, nested, unknown,
malformed, noncanonical, and oversized input, but owns no HTTP request metadata,
origin, cookie/session, route claim, runtime/package access, database,
response emission, lifecycle, enablement, or Store Lite state.
The separate pure HTTP request-envelope normalizer accepts only explicit
transport values from a future core dispatcher: one trusted canonical HTTPS
origin, exact static POST path, complete header list, and raw body. It rejects
origin, content metadata, length, transfer/content encoding, opaque
subject-cookie, CSRF, and idempotency ambiguity before it returns raw evidence
to the form decoder. It never derives trust from `Host`, reads PHP globals,
accesses a database/runtime/package, claims a route, emits a response, starts a
session, or changes lifecycle, enablement, Store Lite, or client state.
The separate private static mutation-route selector now maps one exact
un-decoded path to one current registrar-bound route, mutation handler, and
state loader. It reserves a query-bearing known path for later envelope
refusal, but itself reads no request global, bootstraps no runtime, opens no
database, invokes no package callback, and creates no route, endpoint,
response, browser state, enablement change, or Store Lite behavior.
The separate non-routable core server request-facts adapter now resolves a
canonical HTTPS origin only from operating-system/local configuration, reads
only the current method/raw target, and requires a later web-server integration
to attest a complete ordered fixed security-header capture plus body bytes. It
rejects associative PHP header maps, does not read a body stream, and remains
outside the front controller. It creates no route, endpoint, browser state,
package invocation, response, enablement change, Store Lite behavior, or
client data.
The optional Caddy/FrankenPHP ingress-attestation source now supplies one
separately configured server-side seam without changing the default local
server. It removes spoofed internal headers on every request and conditionally
HMAC-signs only bounded `/addons/` POST method/target, body length/hash, and
fixed security-header facts for an unlinked PHP verifier. Separate isolated
Docker proofs build the matching custom binary, confirm its
module/configuration, verify Caddy-to-PHP capture/body behavior, and carry a
fixture request through the unlinked dispatcher/runner/emitter against fresh
MySQL without client data. They ship no deployed client binary or active
Caddyfile and create no linked endpoint, cookie flow, package invocation,
enablement change, Store Lite behavior, or client data.
The separate core-only response emitter now accepts only the existing exact
fixed core envelopes, rejects output that has already started, clears and sets
only their fixed no-store/nosniff JSON headers, and emits only their matching
fixed body bytes. It reads no request/cookie/session state, database, runtime,
or package code and remains outside the front controller. It creates no route,
endpoint, browser cookie, Store Lite behavior, enablement change, or client
data; the unlinked dispatcher returns immediately after it uses the emitter,
while a front-controller link remains separately gated.
The separate pure subject-cookie serializer now accepts only the exact
core-issued descriptor shape and constructs one fixed future host-only
`Set-Cookie` value: `Max-Age=1800`, `Path=/`, `Secure`, `HttpOnly`, and
`SameSite=Strict`, without `Domain` or `Expires`. It emits no header/cookie,
reads no request/cookie/session state, database, runtime, or package code, and
remains outside the front controller. It creates no endpoint, browser
issuance/rotation, enablement change, Store Lite behavior, or client data.
The core-owned lifecycle bridge now provides transactional `ensure`, `clear`,
and `rotate` operations over the hash-only subject store. It returns only the
fixed serializer descriptors, refuses malformed rotation sources and active
caller transactions, and expires the old subject (and therefore its CSRF
evidence) before committing a distinct replacement. The 18-assertion
disposable fixture and supported-server HTTP rehearsal prove issuance,
resolve-without-reissue, fixed clearance, old-token refusal, and cleanup. It
still has no front-controller link; client response ownership and deployment
remain separate gates.
The non-executing per-client deployment profile is now complete. It validates
one operator-owned packet with a separate database, canonical HTTPS origin,
pinned FrankenPHP/Caddy versions, fixed process-environment HMAC and
trusted-origin sources, attestation-before-PHP route order, core response and
host-only cookie ownership, clean-starter isolation, and disabled activation
flags. It returns only a deterministic non-secret hash and does not load or
apply a client deployment; response-owner and production deployment review
remain before any front-controller link.
Tool-bearing packages remain
ineligible for current enablement. The Owner-authorized disable command serializes with
enablement, refuses enabled dependents, and atomically returns a package to
`installed_disabled` without executing package PHP or deleting package code,
migrations, settings, media, or business data. Richer route/tool actions,
adapter dispatch, upgrades, uninstall/purge, Member Access, Store
Lite, and the other optional verticals remain later reviewed batches.

The first settings prerequisite is now implemented without storage or package
execution. Core normalizes only a valid data-only settings array, requires
defaults to match their exact declared non-secret type, and validates one
closed configuration object with bounded values and exact missing/unknown
reporting. Secret settings accept only opaque lowercase `config:` references
and remain separate from ordinary values; core does not resolve secret
material. Per-client persistence, package settings permissions/UI, secret
availability, and settings-bearing package enablement remain blocked.

The per-client settings storage and authorization prerequisite is now
implemented. The clean installer contains one empty generic
`RED_Addon_Settings` table; it stores ordinary typed JSON scalars separately
from opaque secret-reference identifiers and is bound to the current
installation row. Operational settings must explicitly name a permission
already declared by their package. The read-only write preflight revalidates
the exact filesystem/registry identity, installed-disabled or enabled state,
complete typed target configuration, fresh binary grant decisions, and current
stored-state fingerprint. It writes no row and resolves no secret. Atomic
persistence, settings UI/endpoints, secret availability, and richer
enablement remain blocked.

Atomic per-client setting persistence is now implemented as an internal core
helper. It refuses caller-owned transactions, acquires the shared lifecycle
and exact package locks, locks the installation and setting rows, recreates
the complete plan, replaces every normalized ordinary value or opaque secret
reference, reloads the exact target hash/count, and commits one value-free
`addon.settings.updated` audit fact. Exact no-ops add no audit. Audit,
postcondition, injected, permission, identity, lifecycle, or state failure
rolls the replacement back. Settings UI/endpoints, secret availability, and
richer enablement remain blocked.

A separate core-only current-setting read model is now implemented. It binds
the same trusted package identity and supported installed-disabled/enabled
lifecycle state, requires every operational setting to name a package-declared
permission, and makes a fresh binary decision per setting. Authorized readers
receive only normalized non-secret stored/default/unset values plus a
deterministic model hash; secret settings report only whether an opaque
reference is configured. Identity, lifecycle, schema, stored-value, or grant
drift fails closed with no partial model. It adds no administrator UI or
endpoint, write, package execution, secret lookup, or enablement eligibility.

Non-executing secret-reference availability evidence is now implemented.
Each server may declare a bounded list of opaque `config:` references in its
ignored local configuration or `RED_ADDON_SECRET_REFERENCES`; core merges and
validates the inventory, revalidates the complete typed package configuration,
and returns only counts, missing setting keys, and deterministic declaration,
configuration, and evidence hashes. The evidence contains no reference
identifier or secret value, reads no database, executes no package, and does
not relax activation. Settings UI/endpoints, actual secret lookup, and richer
enablement remain blocked.

The namespaced package-asset foundation is now complete through core-owned
document injection. Trusted manifests form deterministic plans only for
package-owned CSS at `head` and JavaScript at `body-end`, using reserved
checksum-versioned URLs and escaped core-owned tags. The static endpoint reruns
current integrity and enabled-registry evidence before theme, session, or
runtime bootstrap, serves only exact CSS/JavaScript bytes up to 4 MiB through
`GET`/`HEAD` with fixed immutable-cache and `nosniff` headers, and returns
generic fail-closed HTTP responses for invalid, disabled, drifted, or
unavailable state. During ordinary page rendering, a separate non-executing
planner revalidates the trusted catalog, registry, and both surfaces for every
enabled package. It adds public tags at the document `head` and `body-end`, and
adds administrator tags only when the existing signed-in overlay is present.
Catalog, registry, integrity, plan, or document-boundary ambiguity emits no
package markup. Settings UI/endpoints, actual secret lookup, and richer
enablement remain blocked.

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
public placement, restore UI actions, and create/delete endpoints remain later
isolated batches. The operational existing-record form is now complete.

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

The existing-record package update prerequisite now backs a core-owned
administrator endpoint without changing activation eligibility. A registrar may bind at most one
writer per declared editor and must list the package-owned transaction tables.
Core requires those tables and the placement parent to be InnoDB, locks the
exact enabled parent, checks the current view and edit grants plus state hash,
passes only normalized schema values, contains writer output and failures,
reloads the saved values, and commits only when the complete postcondition
matches. Stale state, revoked grants, drift, disabled ownership, unsupported
tables, exceptions, output, buffer changes, false returns, and partial writes
fail closed with rollback. No create path, parent-metadata update, revision
history action, restore, delete, audit workflow, or activation
eligibility is added. The form submits only a numeric parent id, state hash,
CSRF token, and schema values; package/component ownership is re-derived from
the database and runtime registry. Successful updates atomically retain
immutable baseline
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

The read-only public-placement prerequisite is now implemented. It requires
the exact publish grant before package loading and reuses the complete
view-authorized inactive parent, enabled binding, package-state, and current
core-revision evidence. A numeric destination id must resolve to one unique
active Article route; core derives its hierarchy, alias, layout, and language,
requires source/destination language agreement, and validates the proposed
page position against the active theme. The deterministic plan binds both
states and the closed placement values while writing and activating nothing.
The locked atomic placement/activation runner is now implemented behind that
exact plan. It serializes lifecycle and active-theme changes; locks the enabled
installation, inactive source, and destination page; and revalidates the plan.
Only the seven derived placement fields change. Package state and destination
state must remain exact, and one explicit-actor core `move` revision plus one
bounded `component.public_placed` administrator audit fact commit in the same
transaction. The core-owned authenticated POST/CSRF control exposes only
numeric destination choices and stale-state hashes; package/component identity,
permissions, target ownership, and the exact plan are derived again server-side.
Caller transactions, stale/reused plans, revoked grants,
destination drift, unsupported positions, transaction loss, postcondition
mismatch, revision failure, and audit failure roll back.

The Store Lite product and security direction is now defined without adding
commerce behavior or data to core. Its generic component-plus-service
registration shape is accepted, but the complete Store Lite manifest remains
blocked. The generic numeric parent relationship, public binding resolver, and
declarative editor-schema, submitted-value validation, and activation-blocked
existing-record package updates and immutable revision snapshots are
implemented; component-creation planning and its atomic inactive runner are
implemented, and the activation-blocked parent-metadata writer plus atomic
inactive delete runner and operational existing-record form are implemented,
while the administrator action preflight and internal atomic runner are
complete, a core-only authorized setting read model is complete, and the
generic public-mutation subject/CSRF, fixed-window rate-limit, opaque
idempotency-key, atomic transaction-runner, bounded response, declared-form
decoder, pure HTTP request-envelope, private static route-selector, and
non-routable server request-facts adapter plus closed response-emitter
and non-emitting subject-cookie-serialization and optional
Caddy/FrankenPHP ingress-attestation foundations are complete. The bounded
dispatcher, supported-server disposable rehearsal, and core-owned browser
subject-cookie lifecycle bridge, and non-executing per-client deployment
profile are also complete. The rehearsal uses only a temporary custom binary,
fixture endpoint, and fresh MySQL database; it does not link the dispatcher to
the front controller or deploy a client. Client-specific Caddyfile/TLS/proxy,
trusted-origin/HMAC provisioning and rotation, response-owner, protected
settings UI/endpoint, actual secret lookup, live-data disable/upgrade
compatibility, and richer package persistence contracts must still be
implemented and accepted with disposable fixtures before the separately
distributed package can be enabled.

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
