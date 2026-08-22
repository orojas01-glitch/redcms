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

### Version 5.1.0 Release

RED-CMS 5.1.0 was released on 2026-08-15 as
[`v5.1.0`](https://github.com/orojas01-glitch/redcms/releases/tag/v5.1.0)
after the complete disposable acceptance gate passed: 32 installer tables, all
46 migrations, a no-op rerun with zero pending or drifted files, the normalized
35-table schema, canonical public routes, a clean runtime log, and exact
temporary database and grant cleanup. The retained historical starter and every
client database remained untouched.

The basic Store Lite proof is complete as a separately distributed optional
package. It is not bundled with the starter and does not broaden the 5.1.0
release into hosted payments or the other example verticals. See
[`RELEASE-NOTES-5.1.0.md`](RELEASE-NOTES-5.1.0.md).

### Next Optional Store Lite Gate: Hosted Payments

Payment Adapter Gate P0 is complete as a provider-neutral contract. It keeps
hosted checkout, server-verified events, credentials, webhooks, replay,
refund/reversal handling, and client isolation in a separately versioned
adapter rather than the core or Store Lite base package. Gate P1 selects Stripe
Checkout's hosted full-page flow as the first provisional USD hosted-card
candidate without performing payment integration. The completed non-network
fixture and later gated sandbox are separate gates; see
[`PAYMENT-ADAPTER-DIRECTION.md`](PAYMENT-ADAPTER-DIRECTION.md) and
[`PAYMENT-ADAPTER-P1-DECISION.md`](PAYMENT-ADAPTER-P1-DECISION.md), including
the fixture boundary in
[`PAYMENT-ADAPTER-P2-FIXTURE.md`](PAYMENT-ADAPTER-P2-FIXTURE.md).

P3 planning is defined, all five closed P3A core-contract slices are
implemented, and the separately distributed Store Lite package completes P3B
through version 0.1.35. The separately distributed Stripe adapter has completed
P3C plus P3D-1 through P3D-5 offline lifecycle and synthetic-secret bootstrap
proof. Core P3D-6 adds the reusable typed invocation boundary, while the
external adapter handler, provider endpoint, and every network step remain
separately gated. The generic enablement command still refuses adapters and
outbound hosts; the narrow P3A runner accepts only the exact reviewed profile.
Store Lite now owns the provider-neutral transition decision,
append-only history migration, transactional writer/service, and disposable
lifecycle rehearsal. Browser public-mutation ingress still cannot be reused as
a provider webhook. The P3 proposal sequences the external adapter, offline
lifecycle proof, and a newly isolated Stripe Sandbox without authorizing an
account, key, network request, simulated payment, hosted change, or production
work. See
[`PAYMENT-ADAPTER-P3-SANDBOX-PROPOSAL.md`](PAYMENT-ADAPTER-P3-SANDBOX-PROPOSAL.md).

P3A-1 adds the non-executing Stripe/Store Lite adapter manifest profile and a
declaration-only `server-signature` POST route that neither public dispatcher
can select. It still reports database-bound preflight, registrar, ingress, and
atomic-enablement blockers. It does not resolve a secret, load a package, open
a connection, expose a webhook, alter Store Lite, or advance P3 beyond its
first core-contract slice.

P3A-2 adds read-only, database-selected evidence for persisted Owner enable
authority, the exact current enabled Store Lite dependency, the adapter's
immutable migration ledger, and every migration-touched table as InnoDB. The
result exposes only bounded counts and hashes, changes no database fact, and
still blocks registrar validation, server-event ingress, and atomic
enablement. It does not inspect settings or secret values, execute package
PHP, expose a route, contact Stripe, or alter any client installation.

P3A-3 adds registration-only validation. Core refreshes P3A-2 against the
selected database before it loads the fixed integrity-checked `addon.php`,
requires exactly one declared adapter and one declared server-event route
registration, reduces the request-local registry to bounded counts and hashes,
and discards it. It invokes neither registered handler, publishes no runtime
registry or route, resolves no secret, performs no lifecycle write, and opens
no core network path. The registrar remains reviewed trusted in-process PHP,
not a sandbox. Server-event ingress and atomic payment-adapter enablement remain
explicit blockers.

P3A-4 adds the closed core server-event ingress contract without linking a
webhook. It accepts only caller-supplied evidence for the exact declared static
`POST` path, one complete canonical three-header capture, a 1-to-65,536-byte
raw body, and an explicit receipt time. The raw body and complete
`Stripe-Signature` value remain transient verification material outside
ordinary object properties; only bounded metadata and SHA-256 evidence can be
serialized or logged. Core does not read PHP request globals, parse JSON,
resolve a secret, verify the provider signature, invoke a handler, access a
database, emit a response, publish a route, or contact Stripe. Provider
verification remains P3C work.

P3A-5 completes the closed core profile with a separate CLI-only atomic
enablement path. Planning refreshes P3A-2, reruns the P3A-3 registration-only
registrar, validates the P3A-4 ingress contract, requires every exact stored
setting, and proves that both opaque secret references are available without
resolving either value. Apply recomputes the identical plan under the shared
lifecycle lock and target-package lock, then commits only the exact
`installed_disabled` to `enabled` compare-and-swap and one value-free audit
fact. It is Owner-authorized, dry-run first, backup-bound, database-bound, and
stale-plan safe. It invokes no adapter or route handler, exposes no endpoint,
resolves no secret value, publishes no runtime, and opens no network
connection. No package or client installation was changed by this core batch.

P3B is complete in the separately distributed Store Lite package: 0.1.32 adds
the pure provider-neutral transition decision, 0.1.33 adds the append-only
history migration, 0.1.34 adds the typed transactional writer/service, and
0.1.35 completes disposable lifecycle, rollback, isolation, and exact-cleanup
rehearsal. External P3C packaging and P3D-1 through P3D-5 offline lifecycle
proof are complete. P3D-6 provides the generic core typed adapter call, but the
Stripe handler remains refusal-only. Its next separately reviewed gate may
adopt only that typed offline contract; provider transport, route dispatch,
Store Lite mutation, browser checkout, client deployment, and payment remain
unauthorized.

P3E-7 now closes the core-owned authorization prerequisite for the later
read-only sandbox probe. It consumes only exact value-free P3E-6 evidence,
revalidates the database-backed Owner and enabled same-database package state,
and atomically commits a nonce-bound immutable action row plus one audit fact.
Original and changed-envelope nonce replay fail at the existing database
primary key. No credential, environment, DNS, TLS, HTTP, Stripe, Checkout,
payment, webhook, Store Lite mutation, browser, client, or deployment path is
entered. See
[`PAYMENT-ADAPTER-P3E7-AUTHORIZATION.md`](PAYMENT-ADAPTER-P3E7-AUTHORIZATION.md).

P3E-8A now closes the separate core-owned one-attempt claim prerequisite. It
recomputes the exact P3E-7 decision, requires its immutable authorization row,
revalidates current Owner and enabled same-database package state, and commits
one second nonce-derived immutable claim row plus one value-free audit fact.
The claim does not extend the original expiry, cannot be repeated, and remains
non-executing: no credential, environment, package handler, DNS, TLS, HTTP,
Stripe, Checkout, payment, webhook, Store Lite mutation, browser, client, or
deployment path is entered. The actual read-only sandbox request remains a
separately approved P3E-8B gate. See
[`PAYMENT-ADAPTER-P3E8A-CLAIM.md`](PAYMENT-ADAPTER-P3E8A-CLAIM.md).

P3E-8B2 now closes the non-networking execution rehearsal. Core requires the
still-active exact P3E-8A claim, commits an immutable execution-start row and
audit before any package or secret access, integrity-checks the registrar,
resolves only `stripe.secret-key`, and invokes one contained typed
`provider-contact.read-only-probe-loopback` handler. It records only a closed
bounded outcome; a committed start permanently prevents retry even if secret
resolution, handler execution, or outcome persistence fails. No DNS, TLS,
HTTP, Stripe, provider, Checkout, payment, webhook, Store Lite mutation,
browser, client, or deployment path is entered. See
[`PAYMENT-ADAPTER-P3E8B2-LOOPBACK-EXECUTION.md`](PAYMENT-ADAPTER-P3E8B2-LOOPBACK-EXECUTION.md).

P3E-8B3B now closes the synthetic real-package integration. The shared
authorization path accepts only `0.1.1/disabled` for the retained loopback
proof or `0.1.3/synthetic_only` for the new package operation. Core preserves
the durable start/no-retry rule, resolves only `stripe.secret-key`, invokes
`provider-contact.read-only-probe-synthetic`, validates the exact bounded
non-network result, and records the immutable outcome. The actual provider
transport remains unreachable. See
[`PAYMENT-ADAPTER-P3E8B3B-SYNTHETIC-EXECUTION.md`](PAYMENT-ADAPTER-P3E8B3B-SYNTHETIC-EXECUTION.md).

P3E-8B3C2 now closes the core provider-operation runner gate. The shared
authorization path adds only the exact adapter `0.1.4/provider_read_only`
profile. Core preserves the durable start/no-retry rule, resolves only
`stripe.secret-key`, invokes `provider-contact.read-only-probe-sandbox`, and
records only bounded outcome evidence. Acceptance uses an integrity-checked
in-memory handler, so the first real restricted-key sandbox GET remains the
separate P3E-8B3C3 operator rehearsal. See
[`PAYMENT-ADAPTER-P3E8B3C2-PROVIDER-RUNNER.md`](PAYMENT-ADAPTER-P3E8B3C2-PROVIDER-RUNNER.md).

P3E-8B3C3A now closes the server-local operator-command contract. The command
defaults to a non-executing dry run and has only one call site to the B3C2
runner. Apply requires the exact printed state and hashes, nonzero backup,
sandbox operation/target, restricted-test mode, one attempt, no retry, and no
mutation. It accepts no key argument and adds no browser, public, scheduled, or
automatic caller. The first real restricted-key sandbox GET remains separately
approved P3E-8B3C3B work. See
[`PAYMENT-ADAPTER-P3E8B3C3A-OPERATOR-COMMAND.md`](PAYMENT-ADAPTER-P3E8B3C3A-OPERATOR-COMMAND.md).

P3E-8B3C3B now closes the first real provider-contact rehearsal. The dedicated
RED-CMS Stripe sandbox issued one Checkout Sessions Read-only restricted key;
the disposable operator path made one exact GET and recorded the expected
bounded `404 resource_miss_observed` result. Stripe logged one matching request,
the private evidence archive passed checksums and credential scanning, and
cleanup returned database/grant/project/process zero with the primary
unchanged. After evidence review, the operator explicitly expired the key; it
no longer appears in the active restricted-key list and cannot authorize a
retry.
See
[`PAYMENT-ADAPTER-P3E8B3C3B-SANDBOX-EXECUTION.md`](PAYMENT-ADAPTER-P3E8B3C3B-SANDBOX-EXECUTION.md).

The approved P3E-9 frontier limited P3E-9A to pure, non-executing Checkout-
creation request, response, expiry, and operation-profile contracts around the
already-reviewed adapter source. The completed P3E-8 evidence is mutation-
disabled and cannot be reused. New synthetic
integration, mutation-specific authorization, one real Sandbox Session, key
expiration, Session expiration, simulated payment, webhook proof, and P4 each
remain separate stops. See
[`PAYMENT-ADAPTER-P3E9-SANDBOX-CHECKOUT-CREATION-FRONTIER.md`](PAYMENT-ADAPTER-P3E9-SANDBOX-CHECKOUT-CREATION-FRONTIER.md).

P3E-9A is now complete in the separately distributed adapter at merged commit
`28c17a230db22e893ced272fc75e99491cee9705`. The source-only contract reuses
the existing planner, codec, and response gate; adds bounded expiry; rejects
read-only-profile reuse; discards the synthetic Checkout URL; and passed 53
focused plus 921 aggregate assertions. Adapter `0.1.4` and its installable
package remain unchanged. P3E-9B synthetic-only package/core integration is
next; credentials, network, provider mutation, Checkout creation, payment,
webhook, browser flow, demo/client state, and P4 remain gated. See
[`PAYMENT-ADAPTER-P3E9A-CONTRACT-ADOPTION.md`](PAYMENT-ADAPTER-P3E9A-CONTRACT-ADOPTION.md).

P3E-9B is now complete across two isolated repositories. External adapter
`0.1.5` adopts the exact P3E-9A source and adds only
`checkout.create-sandbox-synthetic`; core P3E-9B2 validates that package and
input, registers its integrity-checked handler, and invokes the operation only
through the typed adapter boundary with injected scoped access. The focused
core fixture passes 37 dependency-free assertions with exact temporary-project
cleanup. No database, durable authority, credential resolver, network,
provider mutation, Checkout Session, payment, webhook, browser, Store Lite,
demo/client, or deployment path is added. P3E-9C1 now records only one
nonce-bound mutation authorization and one value-free audit after fresh Owner,
`addons.enable`, Store Lite `store.orders.manage`, and exact enabled-package
validation. Its 34-assertion disposable fixture proves replay, expiry,
revocation, and audit-rollback refusal without claiming or executing an
attempt. P3E-9C2 now consumes only that exact authorization into one immutable
claim plus one value-free audit. Its 37-assertion disposable fixture proves
replay, missing/changed/tampered authorization, expiry, fresh-authority,
disabled-dependency, and audit-rollback refusal without starting or executing
the attempt. P3E-9C3A now commits immutable start before one core-owned
transport-double call and records one bounded result afterward. Its
36-assertion disposable fixture proves successful/fault outcomes, rollback,
replay refusal, and permanent post-start no-retry with every real provider and
business effect false. P3E-9C3B1 now adds the CLI-only dry-run-first command
contract with exact evidence, backup, hash, and no-effect confirmations; its
45 pure assertions open no database. P3E-9C3B2 now proves dry run, confirmation
refusal, one exact apply, replay refusal, zero provider effects, and cleanup
`database:0 grant:0 staged-project:0 primary:unchanged`. P3E-9C is complete;
P3E-9D0 adds only the pure real-POST request contract and 25 source assertions.
External adapter P3E-9D1 is complete through canonical-hash-compatible
`0.1.7`. Core P3E-9D2 invokes only its non-executing preflight operation,
contains the exact typed result, and derives non-persistent start/result
identity hashes in 39 focused assertions. P3E-9D3A adds the CLI-only,
dry-run-first operator command and its 68-assertion source contract without
credentials, persistence, or network. P3E-9D3B now completes the disposable
no-contact rehearsal: dry run, changed-confirmation refusal, one contained
apply, zero provider effects, no database, unchanged sources, and exact
temporary cleanup. P3E-9D3 is complete; the real Session, payment, and webhook
remain gated. D4A is complete in the separately distributed adapter at
`0.1.8`: the exact provider-write operation passed offline and local-loopback
acceptance without Stripe contact. D4B is now complete: D4B1 fresh adapter-
`0.1.8` authorization and claim pass 20 pure plus 30 disposable-database
assertions, and D4B2 durable start/result plus one sealed in-memory invocation
passes 29 assertions. D4C operator command/no-contact rehearsal is next; D4D
remains one separately authorized real POST.
See
[`PAYMENT-ADAPTER-P3E9B2-SYNTHETIC-CORE-RUNNER.md`](PAYMENT-ADAPTER-P3E9B2-SYNTHETIC-CORE-RUNNER.md)
and
[`PAYMENT-ADAPTER-P3E9C1-MUTATION-AUTHORIZATION.md`](PAYMENT-ADAPTER-P3E9C1-MUTATION-AUTHORIZATION.md)
and
[`PAYMENT-ADAPTER-P3E9C2-MUTATION-CLAIM.md`](PAYMENT-ADAPTER-P3E9C2-MUTATION-CLAIM.md)
and
[`PAYMENT-ADAPTER-P3E9C3A-TRANSPORT-DOUBLE-RUNNER.md`](PAYMENT-ADAPTER-P3E9C3A-TRANSPORT-DOUBLE-RUNNER.md)
and
[`PAYMENT-ADAPTER-P3E9C3B1-OPERATOR-COMMAND.md`](PAYMENT-ADAPTER-P3E9C3B1-OPERATOR-COMMAND.md)
and
[`PAYMENT-ADAPTER-P3E9C3B2-OPERATOR-REHEARSAL.md`](PAYMENT-ADAPTER-P3E9C3B2-OPERATOR-REHEARSAL.md)
and
[`PAYMENT-ADAPTER-P3E9D0-REAL-POST-PREFLIGHT.md`](PAYMENT-ADAPTER-P3E9D0-REAL-POST-PREFLIGHT.md)
and
[`PAYMENT-ADAPTER-P3E9D2-CORE-PREFLIGHT-RUNNER.md`](PAYMENT-ADAPTER-P3E9D2-CORE-PREFLIGHT-RUNNER.md)
and
[`PAYMENT-ADAPTER-P3E9D3A-OPERATOR-COMMAND.md`](PAYMENT-ADAPTER-P3E9D3A-OPERATOR-COMMAND.md)
and
[`PAYMENT-ADAPTER-P3E9D3B-NO-CONTACT-REHEARSAL.md`](PAYMENT-ADAPTER-P3E9D3B-NO-CONTACT-REHEARSAL.md)
and
[`PAYMENT-ADAPTER-P3E9D4-REAL-CREATION-PLAN.md`](PAYMENT-ADAPTER-P3E9D4-REAL-CREATION-PLAN.md).

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

The current objective, completed gates, active phase, and remaining Store Lite
launch path are shown in
[`ADD-ON-PLATFORM-STATUS.md`](ADD-ON-PLATFORM-STATUS.md).

The hosted Store Lite preflight exposed and closed the final core release-
identity mismatch: runtime bootstrap and install branding now advertise
RED-CMS 5.1, and the default non-executing manifest path accepts a trusted
package whose compatibility range begins at 5.1. This gate changes no hosted
installation or database; deployment and browser verification remain separate.

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

Gate 0 decision: Store Lite v1 will support both simple products (for example,
bananas sold by unit or pack) and bounded variable products (for example,
T-shirts with size and optional color). Variant data remains package-owned;
unbounded variant matrices, free-form modifiers, and weight-based pricing stay
out of scope.

Gate 2A is now complete as a package-contract checkpoint. The exact first
release bounds are three option groups, sixteen values per group, and 128
explicit variants per product parent, with bounded identifiers/SKUs, integer
minor-unit money, one installation currency, and fail-closed simple/variable
record normalization. The 20-assertion fixture covers both banana-style and
Size/Color shirt data without adding Store Lite code, tables, routes, or state
to the clean starter. See
[`STORE-LITE-PRODUCT-CONTRACT.md`](STORE-LITE-PRODUCT-CONTRACT.md).

Gate 2B is now complete as the first pure server-authoritative commerce
calculation. The browser-shaped declaration contains only product, integer
quantity 1–100, and an optional variant. The separately distributed Store Lite
0.1.12 resolver repeats product normalization and derives the current SKU,
option labels, integer unit price/total, currency, stock sufficiency, and
product-state SHA-256 from server-loaded data. Its 26 package assertions and
the clean-core 21-assertion contract fixture refuse unknown commercial fields,
draft/unavailable/mismatched products, missing/stale/unavailable variants,
invalid quantity, and insufficient tracked stock without a partial line. No
cart table, route, cookie, service registration, response, order, Store Lite
business data, or activation path was added to core. See
[`STORE-LITE-CART-LINE-CONTRACT.md`](STORE-LITE-CART-LINE-CONTRACT.md).

Gate 2C is now complete as an internal package persistence boundary. The
separately distributed Store Lite 0.1.13 package adds three namespaced InnoDB
tables for one numeric anonymous-subject cart, exact server-derived cart lines,
and value-free before/after activity evidence. Its caller-owned transaction
locks cart/line/product/variant state, rejects stale expected cart hashes,
reruns the Gate 2B resolver, verifies the complete postcondition, and leaves
commit/rollback to core. Raw subject tokens and cookies are never stored, and
there is deliberately no foreign key to expiring core subject infrastructure.
The clean starter gains only the mirrored contract and rehearsal migration
inventory. Public mutation registration, Add-to-cart UI, cookies, orders,
checkout, and payment remain later. See
[`STORE-LITE-CART-PERSISTENCE-CONTRACT.md`](STORE-LITE-CART-PERSISTENCE-CONTRACT.md).

Gate 2D1 is now complete as the first package-to-core cart mutation binding.
The separately distributed Store Lite 0.1.14 package declares one closed
Add-to-cart POST contract and registers its fail-closed route callback, handler,
state loader, and exact eight-table transaction set. The disposable rehearsal
proves simple and explicit-variant acceptance, exact replay, changed-command
idempotency conflict, invalid-variant rollback, server-derived postconditions,
and atomic package/core audit evidence while retaining the existing 87/87
desktop/mobile browser result. RED-CMS still has no production public-mutation
endpoint, browser subject/token bootstrap, Add-to-cart form, cart display,
checkout, order, or payment flow. Gate 2D2 owns that browser/server integration.
See
[`STORE-LITE-CART-MUTATION-CONTRACT.md`](STORE-LITE-CART-MUTATION-CONTRACT.md).

Gate 2D2A is now complete as the generic browser-form presentation boundary.
Core can derive one static mutation action and exact hidden identifier,
positive-integer, and identifier-select controls from a validated declaration,
then combine them only with same-subject issued CSRF/idempotency result shapes.
All labels/options and semantic status markup are core-rendered and escaped;
the opaque evidence is reserved for a later fetch controller and is not part
of the package form body. This helper performs no issuance, cookie/header
emission, package loading, component integration, route linking, or mutation.
Gate 2D2B now belongs to the separately distributed Store Lite package's
bounded simple/variant presentation model, followed by core browser bootstrap
and supported-server desktop/mobile integration.

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
now receive only a bounded placement context and return a title/summary model
with an optional bounded list of plain-text label/value facts. Core escapes the
complete model and owns the semantic default public renderer; package HTML and
every other handler type remain non-dispatched. This generic fact-card contract
is consumed by Store Lite's separately distributed pure public-product
presenter. Store Lite 0.1.11 now adds its restrictive Product-placement table,
transactional component-data callbacks, and read-only runtime Product handler.
The core Add Content catalog now advertises an add-on component only after
re-deriving current runtime ownership, enabled installation evidence, its
manifest editor schema, exact creator/loader bindings, and the actor's fresh
create grant. The protected core form creates an inactive component with a
server-allocated numeric id and then offers the language homepage or an
existing public Article as an independently authorized placement. A fresh
clean-core-plus-package rehearsal proves Product creation, Homepage placement,
and public rendering at desktop and mobile widths without a fixture-created
placement. Normal richer-package enablement remains blocked. The clean starter
has no package directory or enabled package state.

The read-only enablement plan now resolves declarative theme, settings, and
live-data gates for four deliberately constrained profiles: a registration-only
service package, a secret-capable registration-only service package, a
core-rendered default public component package, and a default public component
combined with registration-only services. The secret-capable profile permits
only secret-reference settings and requires complete client storage plus
server-local values; it still excludes migrations, routes, jobs, assets,
administrator tools/actions, adapters, and outbound hosts. The other profiles
exclude settings as before. Any richer package remains blocked behind its
explicit contracts. The separate
Owner-authorized enable command revalidates that exact plan under the shared
lifecycle lock and target package lock, validates the fixed registrar, and
atomically records `enabled` with its bounded audit fact. Safe default
component dispatch is implemented. Services can be invoked only through a
non-HTTP core boundary that requires exact enabled request-local ownership,
supplies one immutable typed request, accepts one typed result, and bounds all
JSON-compatible values. A secret-capable service request may resolve only its
own declared server-local secret through an internal by-reference value; core
rejects secret-bearing result data and keeps plans, context, audits, and
responses value-free. Exact static public `GET` routes now have a separate
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
The separate pure form UI boundary derives the exact static action and allowed
hidden identifier, bounded integer, and identifier-select controls from that
same declaration. It combines them only with a bounded presentation model and
same-subject issued CSRF/idempotency result shapes, then returns escaped
semantic markup with one status region and no package HTML. It does not issue
evidence, read request/database/package state, emit output or headers, inject a
controller, link a route, or create Store Lite behavior.
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
apply a client deployment; production deployment and browser review remain
before any front-controller link.
The core-owned response-owner composer is now complete as a non-emitting,
profile-bound step. It accepts only the fixed response envelope and lifecycle
cookie descriptors, preserves clear-before-set rotation ordering, rejects
arbitrary headers and ownership/policy drift, and remains unlinked from the
front controller. Actual per-client deployment and browser evidence remain.
The non-executing deployment-review packet is now complete. It binds the
profile hash to non-secret server/artifact hashes, process-environment
trusted-origin/HMAC and old-key-revocation evidence, and fixed desktop/mobile
browser results without reading files, resolving secrets, or changing client
state. Actual per-client deployment and browser capture remain.
An installation-shaped HTTPS rehearsal command now stages only the reviewed
attestation integration into a temporary Docker context, uses an external
localhost certificate, proves process-environment HMAC replacement across a
restart, and captures fixed desktop/mobile browser evidence into a non-secret
external packet. A successful Docker/browser run and later client-specific
review are still required; no client or Adriana installation is touched.
Tool-bearing packages remain
ineligible for current enablement. The Owner-authorized disable command serializes with
enablement, refuses enabled dependents, and atomically returns a package to
`installed_disabled` without executing package PHP or deleting package code,
migrations, settings, media, or business data. Richer route/tool actions,
adapter dispatch, upgrades, uninstall/purge, Member Access, Store
Lite, and the other optional verticals remain later reviewed batches.

The first settings prerequisite is now implemented without package execution.
Core normalizes only a valid data-only settings array, requires defaults to
match their exact declared non-secret type, and validates one closed
configuration object with bounded values and exact missing/unknown reporting.
Secret settings accept only opaque lowercase `config:` references and remain
separate from ordinary values. Per-client persistence, permissioned reads and
writes, masked editor behavior, and non-executing secret availability are
implemented; secret bytes remain outside this core-only data boundary.

The per-client settings storage and authorization prerequisite is now
implemented. The clean installer contains one empty generic
`RED_Addon_Settings` table; it stores ordinary typed JSON scalars separately
from opaque secret-reference identifiers and is bound to the current
installation row. Operational settings must explicitly name a permission
already declared by their package. The read-only write preflight revalidates
the exact filesystem/registry identity, installed-disabled or enabled state,
complete typed target configuration, fresh binary grant decisions, and current
stored-state fingerprint. It writes no row and resolves no secret. Atomic
persistence and the ordinary settings editor/endpoint are accepted separately.
The core-owned server-local secret resolution/reference replacement boundary is
also accepted separately. The narrow secret-capable registration-only service
profile now consumes only its own resolved references through the typed service
request; richer settings-bearing enablement remains blocked.

Atomic per-client setting persistence is now implemented as an internal core
helper. It refuses caller-owned transactions, acquires the shared lifecycle
and exact package locks, locks the installation and setting rows, recreates
the complete plan, replaces every normalized ordinary value or opaque secret
reference, reloads the exact target hash/count, and commits one value-free
`addon.settings.updated` audit fact. Exact no-ops add no audit. Audit,
postcondition, injected, permission, identity, lifecycle, or state failure
rolls the replacement back. The narrow secret-capable service path is separate
from ordinary settings UI and does not admit richer package enablement.

A separate core-only current-setting read model is now implemented. It binds
the same trusted package identity and supported installed-disabled/enabled
lifecycle state, requires every operational setting to name a package-declared
permission, and makes a fresh binary decision per setting. Authorized readers
receive only normalized non-secret stored/default/unset values plus a
deterministic model hash; secret settings report only whether an opaque
reference is configured. Identity, lifecycle, schema, stored-value, or grant
drift fails closed with no partial model. It adds no package execution or
secret lookup; the separate core-owned editor/endpoint remains permissioned,
masked, and stale-plan guarded.

Non-executing secret-reference availability evidence is now implemented.
Each server may declare a bounded list of opaque `config:` references in its
ignored local configuration or `RED_ADDON_SECRET_REFERENCES`; core merges and
validates the inventory, revalidates the complete typed package configuration,
and returns only counts, missing setting keys, and deterministic declaration,
configuration, and evidence hashes. The evidence contains no reference
identifier or secret value, reads no database, executes no package, and does
not relax activation. The core-owned ordinary settings editor/endpoint is
implemented and accepted separately: it is permission-scoped, CSRF-protected,
stale-plan guarded, and secret-masked. Core-owned server-local secret
resolution/reference replacement is accepted separately. The narrow
secret-capable service profile now resolves only package-owned references at
request time and rejects secret-bearing result data; richer enablement remains
blocked. The contracts are
documented in
[`ADD-ON-SETTINGS-EDITOR-DIRECTION.md`](ADD-ON-SETTINGS-EDITOR-DIRECTION.md):
ordinary typed settings are edited through a core-owned form while
secret-reference state remains masked and unchanged. The separate
[`ADD-ON-SECRET-RESOLUTION-DIRECTION.md`](ADD-ON-SECRET-RESOLUTION-DIRECTION.md)
contract accepts only allowlisted, server-local references and never exposes
secret bytes.

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
package markup. The ordinary core-owned settings editor and core-owned
secret-reference replacement boundary are accepted separately; package-runtime
secret consumption and richer enablement remain blocked.

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

The first operational package-permission workflow is now implemented without
adding a web grant-management surface. A server-local Owner command discovers
only permissions from an integrity-valid manifest, produces a deterministic
dry-run plan, and requires exact database, actor, target, and plan
confirmations before atomically granting or revoking one permission. The same
transaction writes one exact permission audit fact. Discovery and mutation
never load package PHP or change package lifecycle, settings, or content, and
every client database remains independent.

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
complete, the closed JSON administrator-form declaration and non-executing
permission-scoped form plan plus bounded two-level field schema are complete.
The exact registrar-bound current-value loader now performs a fresh package
grant check, validates a complete nested typed graph, binds record/contract
state evidence, and may populate only escaped disabled core controls. A
separate unlinked core endpoint now authenticates and verifies header CSRF
before reading an exact canonical bounded JSON body, repeats the form preflight
and current-value load, refuses stale state, validates the complete submitted
graph, and derives opaque actor/contract/target/state-bound plan evidence. It
returns only a generic validation result and invokes no writer. A separate
internal write preflight now requires one exact registrar-bound writer and one
to eight declared package-owned InnoDB tables, then binds the validation plan,
package version, table set, actor, and target. Its atomic runner recreates the
plan under lifecycle/package locks, repeats grant and state checks, refuses
stale/replayed/drifted evidence, contains the trusted writer, reloads the exact
postcondition, and commits one value-free audit fact with the package mutation.
The separate core-owned operational bridge now accepts only an exact
tool/form/positive-target edit request after administrator session and header
CSRF checks, reloads the authorized current state, renders escaped editable
scalar and bounded nested-collection controls, and sends canonical JSON through
a second authenticated Save endpoint to that atomic runner. Its public result
is only `saved`, `unchanged`, or a bounded refusal. Chrome desktop/mobile
rendered inspection passes with zero horizontal overflow and no console, page,
or failed-request errors. A separate clean-core-plus-package rehearsal now
stages Store Lite 0.1.11 outside the starter, validates its exact inventory,
creates a fresh per-client schema, records one acceptance-only enabled
installation, and proves authenticated Products -> Add product -> Create ->
reload plus existing target -> Edit -> Save -> reload behavior for simple
products alongside an unchanged variable T-shirt. The browser now also follows
Add Content -> Product -> Create component -> Homepage -> Place component,
starting from zero Product placements and proving the resulting public semantic
fact card. Its 87 desktop/mobile Chrome checks, 85 focused generic component
creation/article-placement assertions, and exact core/package revision and
audit postconditions pass before the temporary server, schema, grant, and
staged package are removed. This proof does not relax normal richer-package
enablement blockers or add Store Lite files, migrations, tables, or product
state to the starter.
The core-only authorized setting read model is complete, and the narrow
secret-capable registration-only service profile now proves package-specific
by-reference secret consumption plus core result redaction. The generic
public-mutation subject/CSRF, fixed-window rate-limit, opaque
idempotency-key, atomic transaction-runner, bounded response, declared-form
decoder, pure HTTP request-envelope, private static route-selector, and
non-routable server request-facts adapter plus closed response-emitter
and non-emitting subject-cookie-serialization and optional
Caddy/FrankenPHP ingress-attestation foundations are complete. The bounded
dispatcher, supported-server disposable rehearsal, and core-owned browser
subject-cookie lifecycle bridge, non-executing per-client deployment profile,
response-owner composer, and deployment-review packet are also complete. The
installation-shaped HTTPS deployment rehearsal passed on 2026-08-07 in an
isolated Docker environment with pinned FrankenPHP 1.12.4 and Caddy 2.11.4.
The full disposable acceptance suite also passed against a separately built
current-schema baseline, including all 46 migration files, idempotency,
runtime/browser, lifecycle, settings foundations, CRUD/media cleanup, and
rollback checks; its acceptance database and grant were removed afterward. The
rehearsal uses only a temporary custom binary, fixture endpoint, and fresh
MySQL databases; it does not deploy a client. The retained local starter
database was not migrated because
it is an older historical snapshot. Actual client-specific Caddyfile/TLS/
proxy deployment, trusted-origin/HMAC provisioning and rotation, browser
capture, live-data disable/upgrade compatibility, and richer package
persistence contracts must still be
implemented and accepted with disposable fixtures before the separately
distributed package can be enabled. A separate read-only review of the demo
client confirmed its public and administrator-login routes return HTTPS 200
from Apache without Store Lite/add-on/cart/checkout markers; that reviewed demo
deployment remains intentionally unchanged. See
[`ADD-ON-DEPLOYMENT-REVIEW-20260807-DEMO.md`](ADD-ON-DEPLOYMENT-REVIEW-20260807-DEMO.md).

Gate 2D2 now also has the pure core form renderer, the separately distributed
Store Lite simple/variant field presenter, evidence bootstrap, browser
controller, and the fail-closed supported-server front-controller bridge. The
bridge stays dormant unless a server-local flag, canonical trusted HTTPS origin,
and process-environment ingress HMAC key are all present; core owns the response,
host-only cookie, request-local subject coordination, form placement, and fixed
controller delivery. Gate 2D2D3C is complete through the isolated opt-in
supported-server rehearsal: pinned Store Lite 0.1.16, clean staged core, fresh
MySQL, temporary HTTPS and process secrets, the real administrator Product
placement, and the real desktop/mobile Add-to-cart route passed accepted,
exact-retry, conflict, invalid-input, simple-product, exact Size/Color variant,
accessibility, responsive, console, network, atomic-state, and cleanup checks.
The clean starter flag remains false,
the hosted demo remains unchanged, and no client package or database was used.
The read-only Cart milestone is now complete through a second isolated
supported-server rehearsal pinned to external Store Lite 0.1.19 at merged
revision `e274c48b57b7c2f8ca33d1b13e554128275661be`. The real administrator flow
created and placed Product and Cart components; desktop and mobile Chrome
proved empty Cart rendering and current subject-owned simple/Size-Color lines
after real Add-to-cart requests. The 100 administrator checks, 76 public checks,
exact `4:4:6:4:4:4` cart state, revision/audit evidence, and full Docker cleanup
passed. At that checkpoint, the next Store Lite milestone was quantity update
and line removal.

The editable-Cart composition prerequisites are now complete. Separately
distributed Store Lite 0.1.24 binds each server-derived current line identity
to pure set-quantity and remove-line form descriptions. Core revalidates the
complete collection model, verifies same-package runtime ownership, derives
stable per-placement row/form instances, issues separate CSRF and idempotency
evidence under one request-local subject, and renders only escaped core-owned
form markup beside the row facts. Packages still supply no HTML, browser
evidence, response behavior, or callback during composition. At that
checkpoint, real desktop and mobile quantity/remove dispatch, Cart refresh
behavior, and visitor-facing status wording remained the follow-up gate; the
hosted demo and all client installations remained unchanged.

That editable-Cart follow-up gate is now complete in the isolated
supported-server rehearsal pinned to external Store Lite 0.1.24 at merged
revision `c3dc7405d9e62c1112555503523c0c339e4b8fa8`. The fixed core controller
uses mutation-neutral status copy and refreshes only the current page after an
accepted or unchanged command. Desktop and mobile Chrome each added a real
simple or exact Size/Color line, proved accessible quantity/remove controls and
one server-derived line handle, updated quantity through the real dispatcher,
verified recalculated totals after refresh, removed the line, and returned to
the empty Cart without rotating the subject. The established 100 administrator
checks and 147 public checks passed with zero console, page, request, HTTP, or
overflow errors. Exact final state was `4:2:3:8:8:8`, and every container,
network, image, database, certificate, secret, and staged project was removed.
No hosted or client installation changed. The immutable guest-order foundation,
generic non-secret runtime-setting boundary, and separate Store Lite 0.1.28
checkout linkage are now complete. Desktop pickup and mobile delivery used the
closed core-rendered form and real dispatcher to persist exact server-derived
order snapshots with pay on receipt. The 100 administrator and 268 public
checks covered conditional fields, retry/conflict behavior, responsive output,
database facts, and complete Docker cleanup. The core additionally locks
canonical idempotent component normalization when one view contains both a
collection and top-level form. Store Lite remains external to the starter. The
first release sub-gate produces deterministic read-only evidence for a closed
operational content-package profile. Release B now consumes that evidence
through Owner-authorized exact registrar validation and an atomic
enabled-state/audit transition. Generic and real Store Lite disposable
rehearsals prove failed-registration refusal, non-executing disable, preserved
code/settings/migrations/data, disabled bootstrap exclusion, and identical
registrar evidence on re-enable. Store Lite remains external to the starter.
Release C1 now provides the generic disabled-package upgrade and recovery
boundary. It requires a higher trusted same-type target, append-only unchanged
historical migration evidence, compatible stored settings, explicit Owner and
backup confirmations, and no runtime execution. Forced partial migration and
completion-audit failures remain non-loadable as `upgrade_failed`; exact resume
applies only remaining migrations and finishes disabled with preserved data.
Release C2 now proves the real Store Lite 0.1.28 to 0.1.29 append-only
migration. Two package-owned order-list indexes preserve all eight historical
migration paths/checksums. A forced second-migration failure kept the old
identity, one real order, five settings, and the first DDL ledger exact and
non-loadable; explicit resume applied only the remaining index and finished
0.1.29 disabled. Release C3 is also complete: Store Lite 0.1.29 passed the
supported-server 100-check administrator and 268-check public desktop/mobile
rehearsal with exact cleanup, while a separate 14-assertion proof installed and
enabled two isolated clients with different currency, fulfillment, and product
data; mutations and disable/re-enable state remained database-local. Both
client databases and grants were removed and a schema-neutral full hash proved
the configured primary unchanged. The next milestone had been a separately
reviewed basic `demo.red-sphere.com` deployment. Hosted-provider adapters
remain later work.

The deployment review exposed that the original public-mutation endpoint was
coupled to a custom Caddy/FrankenPHP attester. The shared-host compatibility
gate is now complete locally: `direct_php` remains explicit while
`frankenphp_attested` remains the default hardened profile, and a disposable
real Apache 2.4/PHP FastCGI HTTPS run proves the closed projection, Apache
chunk normalization, refusal cases, desktop/mobile browser behavior, and
matching non-secret deployment review. It opened no database, linked no
dispatcher, loaded no package, or changed client state. That prerequisite is
now followed by the separately approved hosted deployment and closeout.

The maintained [add-on platform status map](ADD-ON-PLATFORM-STATUS.md) shows
the completed foundation, achieved Store Lite basic-demo target, and later
optional payment or vertical-package gates without changing their scope.

The approved basic `demo.red-sphere.com` deployment installed Store Lite
`0.1.31` in the disabled state with all ten package migrations applied and no
package content. Hosted MySQL 5.7 then exposed core schema guards that depended
on display-width text such as `int unsigned` instead of the equivalent
`int(10) unsigned`. Pull requests #116 and #118 made the affected storage and
permission-audit checks semantic and portable. Pull requests #117 and #119
completed the Owner package-grant path and polished the installed add-on
administrator surfaces. Store Lite was then enabled only for the demo and
populated with nine fictional dog products, including one exact nine-choice
Size/Color scarf. Product and Cart authoring, Products and Orders tools, the
public catalog, cart, responsive Checkout route, pickup/delivery, and pay on
receipt are verified without touching another client installation or database.

Pull request #120 replaced the starter Instructions article with the clean-core
RED-CMS 5.1 guide and explicitly keeps Store Lite and all business packages
separately installed per client. The Store Lite v1 basic-demo target is now
closed. No new hosted order was submitted during the read-only closeout; real
order creation and immutable snapshots remain covered by the isolated
supported-server browser gate. Hosted payment adapters remain a separate later
milestone. See
[`STORE-LITE-DEMO-CLOSEOUT-20260815.md`](STORE-LITE-DEMO-CLOSEOUT-20260815.md).

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
