# RED-CMS Public Mutation Boundary

Status: data-only declaration validation, its non-executing preflight, a
separate read-only live-data preflight, an internal core-only
anonymous-subject/CSRF foundation, a fixed-window rate-limit foundation, and an
opaque idempotency-key foundation, an internal atomic transaction runner, a
pure declared-form decoder, HTTP request-envelope normalizer, and private
static route selector are
implemented. This document records the
prerequisite for a future public write dispatcher. It does **not** add a public
HTTP dispatcher or endpoint, emitted
cookie/header, browser session access, package, permission, enablement profile,
or Store Lite behavior. The five generic core tables remain empty in the clean
starter.

## Purpose

RED-CMS already has a deliberately narrow add-on public route boundary: an
enabled package may serve an exact static public GET route as core-owned JSON.
That boundary refuses every unsafe method before a package handler can run.

Store Lite will eventually need a different capability: a guest can ask to add
one available product to the guest's own cart. That must not turn the general
public router into a package-controlled form or checkout system. This contract
defines the smallest future path for a public mutation while keeping Store Lite
an optional, separately distributed package.

It applies to a later first-party public mutation runner only. Existing
/bin/contact.php, /bin/response.php, /bin/register.php, /bin/login.php, and
their legacy form/session behavior remain outside this contract and are not
changed by it.

## Current State

| Capability | Current behavior | Change authorized by this document |
| --- | --- | --- |
| Add-on public routes | Exact static public GET JSON only | None |
| Unsafe add-on methods | Refused before package execution | None |
| `publicMutationContracts` | Optional closed manifest metadata plus value-free preflight evidence | No route claim, runtime loading, handler, or enablement |
| Public-mutation live-data preflight | Read-only installed-disabled package, migration, InnoDB-table, typed-setting, opaque-secret-availability, and core subject/CSRF/rate-limit/idempotency/execution storage evidence | No dispatcher, secret resolution, package execution, lifecycle change, or package-data write |
| Anonymous cart identity | Internal core subject issuance/resolution exists for a future endpoint; it is not a cart identity | No browser cookie read/write, session, package access, route, or client business data |
| Public CSRF issuance/validation | Internal core issue/verify helper binds a short-lived value to one subject, client database, and validated declaration | No HTTP request parsing, token consumption, handler, ledger, or mutation |
| Public rate decision | Internal core fixed-window claim is limited to 12 requests per 60 seconds for one client, declaration, and opaque subject; the runner uses its transaction-only primitive | No dispatcher, request parsing, package execution, or enablement |
| Public idempotency evidence | Internal core issue/resolve helper binds a 10-minute opaque key to one client, declaration, and subject | No endpoint issues or accepts a key; the helper itself remains non-consuming |
| Public mutation ledger/audit | Internal core runner records one completed key relation, keyed HMAC command/state evidence, one bounded outcome, and one value-free anonymous audit fact | No response, package fixture, browser behavior, or publicly reachable execution path |
| Declared package fields | Pure core decoder accepts one trusted declaration and canonical raw URL-encoded package fields only | No HTTP request ownership, header/cookie/session access, route claim, package execution, or response emission |
| HTTP request envelope | Pure core normalizer accepts explicit static transport facts and releases opaque subject/CSRF/idempotency evidence only after complete validation | No PHP globals, route claim, endpoint, response emission, session, database/runtime/package access, or client state |
| Static mutation-route selection | Core maps one exact un-decoded path to one registrar-bound public route, mutation handler, state loader, and manifest identity | No request-global adapter, route claim, handler invocation, database, response emission, browser behavior, enablement, or client state |
| Store Lite files, tables, or records | Absent from the clean starter | None |

The planned contract cannot make a route-bearing package eligible for the
current constrained enablement profiles. It must be implemented and accepted
before a richer enablement decision can consider it.

## Implemented Declarative Shape

The optional closed `publicMutationContracts` manifest field is implemented in
the Version 1 schema and trust validator. It is intentionally separate from
`routes`. Existing package manifests without it remain compatible. If present,
each entry must bind exactly one already-declared route to one mutation identity
and must declare every one of these fixed values:

- a route id and unique mutation id, bound to an exact static, unencoded path
  in the package's reserved public namespace;
- `scope: public`, `authentication: public`, `method: POST`, and
  `csrf: required`, matching the referenced route exactly;
- `encoding: application/x-www-form-urlencoded`, a body bound from 128 to
  8,192 bytes, and one to eight closed request fields;
- only `identifier` fields with explicit 1–160 byte bounds or
  `positive-integer` fields with explicit 1–2,147,483,647 bounds;
- `subject: anonymous`, `idempotency: core-issued-key`,
  `privacy: no-store`, `rateLimit: required`, and
  `postcondition: server-derived-state`;
- one to eight package-shaped tables, a bounded value-free audit category; and
- exactly the public success vocabulary `accepted`, `unchanged`.

Core canonicalizes field and table order, rejects duplicated route, mutation,
field, and table identities, reserves all core-controlled request names, and
rejects unknown/executable metadata. It also rejects core add-on tables as
package tables. The validation does not claim the route, verify a table,
generate a subject/token/key, or admit the package to enablement.

The manifest may not name PHP files, classes, arbitrary callbacks, SQL, HTML,
redirects, response headers, cookie names, sessions, payment-provider fields,
or client-selected table names. It cannot weaken core method, CSRF,
same-origin, privacy, transaction, or enablement rules. An unknown,
incomplete, duplicate, route-mismatched, executable, or overly broad entry
must fail discovery and preflight before addon.php is loaded.

The initial mutation form must remain application/x-www-form-urlencoded with
no files, nested values, JSON, query-controlled operation selection, or
cross-origin support. POST is the only planned method. PUT, PATCH, and DELETE
remain closed until separately reviewed.

## Implemented Read-Only Live-Data Evidence

`includes/addon_public_mutation_live_data_helpers.php` is deliberately later
than declaration validation but earlier than a public request path. It accepts
only a current, trusted, `installed_disabled` package and one already-validated
declaration, then reads the existing client migration ledger, the declaration's
package-owned table engines, typed generic setting state, declared opaque
secret-reference availability, and exact core-owned
subject/CSRF/rate-limit/idempotency/execution storage shape.

Its deterministic plan returns no raw table name, setting value, opaque
reference, or secret: it exposes only bounded counts, blocker codes, package
identity, and SHA-256 fingerprints. Even when its data-evidence gate clears,
the plan remains non-activating and non-executing: `enableReady`,
`activationSupported`, and `requestDispatch` are false. It does not itself
issue an anonymous subject, CSRF token, or idempotency key; resolve a secret;
start a transaction; load package PHP; mutate client state; claim a route; or
change enablement.

## Implemented Core-Owned Subject And CSRF Foundation

`RED_Addon_Public_Mutation_Subjects` and
`RED_Addon_Public_Mutation_CSRF_Tokens` are empty generic core tables in every
new client database. They retain only SHA-256 digests of random 256-bit subject
and CSRF values, created/expiry facts, a CSRF scope hash, and an opaque subject
relationship. No raw token, cookie value, package id, cart, order, request body,
secret, or business record is stored.

The internal helper accepts an explicit raw subject value only from a later
core endpoint. It returns a future endpoint's host-only cookie descriptor
(`Path=/`, `Secure`, `HttpOnly`, `SameSite=Strict`, 30 minutes) but does not
call `setcookie`, read `$_COOKIE`, start a session, or emit a header. CSRF
values expire after 10 minutes, are bound to the active subject plus the current
client database and one already-valid declaration, and verify without returning
the raw value. Expired rows are removed by bounded core cleanup. A later
transaction runner must still consume the appropriate replay/idempotency fact;
this helper does not dispatch, parse, mutate package tables, or execute package
code.

## Implemented Core-Owned Rate-Limit Foundation

`RED_Addon_Public_Mutation_Rate_Limits` is the third empty generic core table.
The manifest validator reserves all five core tables, so an add-on cannot
claim any of them as package-owned storage. A rate row retains only an opaque
subject record relation, SHA-256 scope, fixed window start/expiry facts, and a
small request count. It never stores a raw subject or CSRF value, package id,
route, request body, secret, cart, order, or business record.

`includes/addon_public_mutation_rate_limit_helpers.php` is internal core code,
not a package API. It derives its opaque scope from the current client database,
one already-valid declaration, and the fixed core policy. The policy is exactly
12 requests per 60 seconds for one client database, declared package route, and
opaque anonymous subject. Each claim uses a short internal InnoDB transaction,
rejects a caller-owned transaction, handles bounded contention, and fails closed
when exact subject/CSRF/rate storage is unavailable. Expired evidence is removed
in bounded cleanup, while expiry of the parent subject cascades its rate rows.
The rate helper does not read request/cookie/session globals, emit a response,
load package PHP, invoke a handler, inspect an idempotency key, change
lifecycle, or mutate package data.

## Implemented Core-Owned Idempotency Foundation

`RED_Addon_Public_Mutation_Idempotency_Keys` is the fourth empty generic core
table. The manifest validator reserves it with the other public-mutation core
tables, so an add-on cannot claim it as package-owned storage. A row retains
only an opaque subject record relation, a SHA-256 declaration/database scope, a
SHA-256 digest of a core-issued key, and creation/expiry facts. It never stores
a raw subject, CSRF value, idempotency key, package id, route, request body,
secret, cart, order, or business record.

`includes/addon_public_mutation_idempotency_helpers.php` is internal core code,
not a package API. It issues or resolves a fresh 256-bit opaque key only for an
active anonymous subject and an already-valid declaration. The fixed lifetime
is 10 minutes; expired evidence is removed in bounded cleanup, and parent
subject expiry cascades related key rows. Issuance refuses a caller-owned
transaction and fails closed when exact subject/CSRF/rate/idempotency storage is
unavailable. Resolution remains deliberately non-consuming: the separate
runner below is the only internal consumer. No helper reads request/cookie/
session globals, emits a response, loads package PHP, invokes a handler,
changes lifecycle, or mutates package data.

## Implemented Internal Atomic Runner

`RED_Addon_Public_Mutation_Executions` is the fifth empty generic core table.
Its one-to-one relationship with an existing opaque idempotency-key record
stores a keyed HMAC-SHA-256 command fingerprint, keyed previous/current
server-derived state fingerprints, one of the bounded `accepted` / `unchanged`
outcomes, and completion time. It stores no raw subject, CSRF value,
idempotency key, package id, route, request body, secret, cart, order, or other
business value. Parent-subject cleanup cascades through the key record to this
ledger.

`includes/addon_public_mutation_execution_helpers.php` is internal core code,
not a public endpoint or a package API. Its entry point accepts only an
already-typed command, a resolved opaque subject, raw CSRF/idempotency evidence
from a future core dispatcher, and a current in-memory first-party runtime
binding. Under the existing lifecycle and package locks it rechecks the enabled
installation, subject, CSRF, key, declared InnoDB tables, fixed rate budget,
state loader, postcondition, replay evidence, and one value-free audit fact in
one transaction. Its in-transaction rate claim first performs the same bounded
expired-rate cleanup, so the future runner path does not retain expired opaque
rate evidence. Exact replay returns only its bounded stored outcome before a
rate claim, state load, handler call, or second audit. A different command for
the same key is refused. Output, exceptions, malformed results, transaction
loss, postcondition drift, ledger/audit failure, and rate failure roll back or
refuse as applicable.

The registrar callback receives the core-supplied active transaction connection
and final immutable request object. This is a reviewed first-party package
boundary, not a database sandbox: a package is still forbidden to commit,
roll back, alter output buffers, use request globals, or write outside its
declared tables. The runner detects transaction loss and contains ordinary
callback failure, but cannot make untrusted arbitrary PHP safe. There is no
current public dispatcher, response emission, cookie/header emission, request
parser, enabled package profile, or Store Lite package that can reach it.

## Implemented Bounded Response Contract

`includes/addon_public_mutation_response_helpers.php` is a pure core-owned,
dependency-free response model. It maps only the internal runner's exact
`accepted` / `unchanged` outcomes and the future dispatcher's closed refusal
inputs to fixed JSON envelopes:

- HTTP `200`: `{ "ok": true, "outcome": "accepted" }` or
  `{ "ok": true, "outcome": "unchanged" }`;
- HTTP `400`: a generic `invalid_request` refusal, including CSRF, subject,
  idempotency, origin, encoding, body-size, and field-validation failures;
- HTTP `405`: `method_not_allowed` plus exact `Allow: POST`;
- HTTP `409`: `request_conflict` for a conflicting idempotency key;
- HTTP `429`: `rate_limited`; and
- HTTP `503`: `temporarily_unavailable` for every unavailable, malformed, or
  otherwise unmapped internal condition.

Every envelope has exact JSON `Content-Type`, `Cache-Control: no-store`,
`X-Content-Type-Options: nosniff`, and `Content-Length` fields. A replay maps
to the original bounded outcome, without exposing that it was replayed. The
model does not inspect a request, emit a header/cookie/body, start a session,
load package code, access the database, or alter lifecycle, package, or Store
Lite state. Its existence does not make a route claimable or a package
enablement-eligible.

## Implemented Declared Form Decoder

`includes/addon_public_mutation_form_helpers.php` decodes only raw canonical
`application/x-www-form-urlencoded` package-field bytes after its caller
supplies one validated in-memory manifest, declared route, and declared mutation
identity. It accepts only the contract's one-to-eight identifier or
positive-integer fields and returns a sorted typed field map. It fails closed
with no partial values for missing required fields, duplicates, PHP-style
nested names, undeclared names, malformed segments, raw control bytes,
noncanonical `%`/`+` encodings (apart from canonical `%7E` identifier bytes),
noncanonical integers, out-of-bounds values, or
an over-limit body.

This is not an HTTP parser or a route dispatcher. It reads no PHP request,
cookie, session, or server globals; it does not inspect origin, content type,
content length, path, or method; it does not access a database, runtime,
package code, or browser state; and it emits no response. A later core-owned
HTTP dispatcher must establish those facts before it selects this decoder and
then pass its result to the existing transaction runner.

## Implemented HTTP Request-Envelope Normalizer

`includes/addon_public_mutation_http_request_helpers.php` accepts only explicit
values supplied by a later core-owned request adapter: one validated in-memory
manifest/declaration identity, a server-configured canonical HTTPS origin,
method, raw request target, complete header list, and raw body bytes. The
trusted origin is never derived from `Host` or another request value. The raw
request target must equal the declaration's exact static unencoded path, and
the only accepted method is `POST`.

The normalizer requires an exact matching `Origin`, one canonical form content
type (`application/x-www-form-urlencoded` with an optional canonical
`charset=UTF-8` suffix), and an optional `Content-Length` only when its
canonical decimal value equals the supplied raw-body length. It rejects
duplicate critical headers, control bytes, transfer/content encoding, malformed
metadata, and bodies over the declaration maximum. It does not decode form
fields; the separate form decoder remains the only package-field parser.

The header list must contain at most one raw `Cookie`, `X-RED-CMS-CSRF`, and
`Idempotency-Key` field. Core extracts only one exact
`redcms_public_mutation_subject` 256-bit opaque cookie value. The cookie,
CSRF, and idempotency values must each use the fixed lowercase hexadecimal
token shape. No arbitrary cookie, header, subject, token, or raw body value is
returned on a refusal. The normalized output is only a future dispatcher’s
short-lived private input; no package receives request headers, cookie/session
globals, raw tokens, or the body.

This helper is intentionally not a public endpoint or HTTP emitter. It reads
no PHP request globals, does not start a session, access a database or runtime,
issue/resolve a subject/CSRF/key, load package code, claim a route, run the
transaction, or change lifecycle, enablement, Store Lite, or client state.

## Implemented Static Mutation-Route Selector

`includes/addon_public_mutation_route_helpers.php` accepts one raw request
target and an already initialized core runtime context. It uses only the raw,
un-decoded path portion to find an exact declared static mutation path, while a
later normalizer still receives the complete target and therefore rejects a
query-bearing target. A successful private selection binds one package id,
route id, mutation id, and canonical path only when the current context has
the exact registrar-owned public route, mutation handler, and state-loader
bindings. Duplicate path candidates or a missing binding are claimed but fail
closed without returning an owner.

The selector does not read `$_SERVER` or any request/session/cookie global,
open a database, bootstrap a runtime, read package files, invoke a route,
mutation, or state-loader callback, issue browser evidence, emit a response,
or change lifecycle, enablement, Store Lite, or client state. It is not wired
into `index.php`; it creates no live route or endpoint.

## Future Core-Owned Request And Response Path

When a dispatcher is approved, core—not a theme, browser script, or package
route file—must own this sequence:

1. Use the implemented private selector only after core has initialized one
   current trusted enabled runtime context. The dispatcher must still bind that
   selected declaration to the complete raw target and reject every malformed,
   noncanonical, disabled, stale, untrusted, or undeclared request before a
   package callback runs.
2. Use the implemented core-owned, client-scoped anonymous subject foundation
   only through the future dispatcher. It is an opaque, unguessable
   cookie-bound reference; it is neither an administrator session, a Member
   Access identity, a raw cookie value exposed to package code, nor a client
   database/global identity. The dispatcher must serialize the supplied
   host-only descriptor correctly, define rotation/retention, and prove those
   browser behaviors separately.
3. Use the implemented core-owned CSRF issue/verify foundation before semantic
   request parsing or handler invocation. The token, subject, raw cookie,
   request body, and secrets must not enter general logs, package output, audit
   rows, or public errors. The actual dispatcher must enforce same-origin
   behavior and emit no CORS policy that would widen access.
4. After static route selection, invoke the implemented request-envelope
   normalizer with a configured HTTPS origin and complete raw transport facts.
   It validates same-origin, content type, body-size, opaque subject/CSRF/key,
   and static path/method evidence before the dispatcher calls the implemented
   decoder for only declared scalar form fields. Core rejects unknown, repeated,
   nested, noncanonical, malformed, oversized, or out-of-range values. A
   browser never supplies a package id, route owner, price, currency, total,
   order state, cart owner, permission, plan, or database target.
5. Re-derive the exact manifest, registry, enabled runtime owner, anonymous
   subject, idempotency key, current fixed-window rate decision, and current
   package state on the server. A future request must be refused when rate-limit
   storage or required state evidence is unavailable; a client-provided retry
   flag cannot bypass a refusal.
6. Invoke only one registrar-bound mutation handler through the implemented
   internal core-owned transaction runner. It receives a final typed command
   and core-supplied active transaction connection, never PHP request globals,
   raw cookies, CSRF values, or server headers. It remains reviewed first-party
   PHP rather than a SQL sandbox, and must write only its declared package-owned
   InnoDB tables.
7. Use the implemented runner to reserve the declared idempotency fact before
   mutation, reload the exact server-derived postcondition afterward, and
   atomically commit the package change, replay evidence, and one value-free
   audit fact. Output, exceptions, buffer tampering, stale state, transaction
   loss, postcondition drift, or ledger/audit failure must roll back or refuse
   the complete request.
8. Select only the implemented valid core response envelope after the complete
   request/transaction result is known. A later dispatcher/emitter may not
   expose package/actor/cart/order/plan/state values, HTML, redirects,
   arbitrary headers, payment data, a privileged action token, or replay
   status.

The initial rate budget is a core decision, not a browser or package choice:
12 requests per 60 seconds per client database, declared package route, and
anonymous subject. A future scope expansion requires separate review; it cannot
silently weaken privacy, retention, or failure-closed enforcement.

## Store Lite Mapping

The first Store Lite use of this boundary is limited to cart intent. A future
add-to-cart command may carry only a public product reference, bounded
quantity, and core-issued idempotency/CSRF evidence. Core and the commerce
service must resolve current product availability, price, currency, cart
ownership, and authoritative total server-side.

Update-cart and remove-cart need the same opaque-cart ownership and
idempotency protections. Initial checkout, customer fulfillment fields,
payment handoff, refunds, webhooks, and paid transitions are separate later
contracts. A browser return from a payment provider can never authorize an
order or paid-state change.

This boundary does not make Store Lite core functionality. It does not create
addons/redcms/store-lite, RED_Addon_StoreLite_* tables, catalog records, carts,
orders, settings, media, cookies, or a registry row in the clean starter.
Every client that later adopts Store Lite receives an independently deployed
package and a separate database installation.

## Enablement And Live-Data Gates

Before a package declaring a public mutation can be enabled, a later richer
enablement review must prove all of the following without executing a live
mutation:

- exact trusted filesystem, manifest, migration, registry, and runtime-owner
  evidence for the one client installation;
- no route, asset, capability, setting, or namespace collision;
- declared package-owned InnoDB tables and every required migration present;
- exact anonymous-subject, CSRF, idempotency, rate-limit, privacy, retention,
  and audit readiness;
- package settings and any secret availability evidence required by that
  declared mutation, without returning secret material;
- route-specific public fallback, accessibility, theme/CSS isolation, and
  cache/privacy behavior; and
- disabled-state and later-disable proof: no route claim, runtime bootstrap,
  asset injection, or mutation after disable, with client data retained.

The live-data preflight now also attests exact generic
subject/CSRF/rate-limit/idempotency/execution storage in this list, but it does
not establish an enabled runtime binding, execute the runner, issue a response,
or supply richer-enablement evidence. No current enablement profile satisfies
the complete set. The future validator must report a bounded, value-free
refusal rather than silently downgrade a public write to a weaker route.

## Required Future Acceptance Evidence

The current 19-assertion subject/CSRF test, 15-assertion rate-limit test,
18-assertion idempotency test, and isolated atomic-runner fixture prove exact
storage, hash-only/opaque persistence, declaration/database scope, the fixed
12-per-60-second cap, 10-minute key lifetime, subject-bound expiry, bounded
cleanup, replay/conflict handling, postcondition checks, and rollback of
package/rate/ledger/audit state. The runner fixture uses only a disposable
in-memory runtime context and temporary package table; it is not HTTP, browser,
or package enablement acceptance.
An implementation is not ready because a manifest validates or a route returns
HTTP 200. A disposable, client-isolated acceptance suite must still prove:

- malformed, duplicate, executable, unsafe, mismatched, and drifted
  declarations fail before package execution;
- current public GET routing still refuses unsafe methods until the new
  dispatcher is explicitly present;
- anonymous subjects and CSRF tokens are opaque, scoped to one client, and
  unavailable to another client, administrator session, member session, or
  package request;
- no-CSRF, stale-CSRF, cross-subject, wrong-route, malformed-field,
  cross-origin, rate-limited, disabled, revoked, replayed, and stale-state
  requests cause no package mutation;
- every successful mutation derives authoritative server state, performs only
  declared package-table writes, rechecks its postcondition, and emits no
  sensitive value in response, log, audit, feed, sitemap, preview, or
  structured data;
- duplicate requests, concurrent requests, callback output/exceptions,
  transaction loss, ledger/audit failure, and forced late failure preserve the
  exact atomic/replay outcome; and
- exact fixture, temporary package, temporary grant, temporary database, and
  temporary cookie cleanup completes while the configured primary database is
  unchanged.

Desktop and mobile verification must also prove keyboard/screen-reader
behavior, theme isolation, no horizontal overflow, and an accessible bounded
refusal path. No real payment provider, customer, or client installation is
used for that evidence.

## Explicit Non-Goals

This planning slice does not authorize:

- an endpoint, browser form, administrator control, package JavaScript, or
  Store Lite user interface;
- package activation, a relaxed enablement profile, a live package fixture,
  package registry change, or package migration;
- Member Access, checkout, payment adapters, webhooks, external network calls,
  file upload, e-mail, or PII collection;
- a generic CMS hook system, arbitrary public PHP endpoint, raw package HTML,
  redirect, or headers; or
- changes to legacy public forms, client installations, client databases,
  client media, client settings, or production data.

## Delivery Order

1. This contract recorded the fixed safety boundary without code or state.
2. The closed data-only declaration and value-free deterministic preflight are
   complete. They still refuse route dispatch and enablement, invoke no package
   handler, and read no database, request, cookie, or session state.
3. The separate read-only live-data preflight is complete. It inspects only
   current trusted installed-disabled client evidence and returns counts and
   fingerprints; it does not issue tokens, dispatch, enable, execute, resolve
   secrets, or write.
4. Completed the core-owned anonymous-subject and CSRF foundation with empty
   generic storage, hash-only persistence, bounded expiry cleanup, and no
   request dispatcher or package behavior.
5. Completed the core-owned fixed-window rate-limit foundation with empty
   generic storage, client/declaration/subject scope, a 12-per-60-second cap,
   bounded cleanup, and no request dispatcher or package behavior.
6. Completed the core-owned opaque idempotency-key foundation with empty
   generic storage, client/declaration/subject scope, 10-minute expiry,
   hash-only persistence, bounded cleanup, and no consumption, dispatcher, or
   package behavior.
7. Completed the internal atomic transaction runner and keyed replay ledger
   with disposable fixture-only package state. It still adds no dispatcher,
   response emission, browser behavior, or enablement profile.
8. Completed the pure bounded response contract with fixed success/refusal
   envelopes, replay redaction, and no HTTP request ownership or response emission,
   browser behavior, or enablement profile.
9. Completed the pure declared-form decoder with canonical package-field
   validation and no HTTP request ownership, browser behavior, or enablement
   profile.
10. Completed the pure HTTP request-envelope normalizer with a configured HTTPS
    origin, exact static POST path, fixed opaque browser evidence, and no
    request-global, endpoint, browser, package, or enablement path.
11. Completed the private static mutation-route selector with exact
    registrar-binding checks and no request-global, handler, endpoint, or
    response path.
12. A separate batch may add a core-owned server request adapter, HTTP
    dispatcher, and emitter, with disposable fixtures only.
13. A separate richer enablement review may admit only packages that satisfy
   every declared prerequisite.
14. Store Lite can then implement its separately distributed catalog and cart
   behavior against the accepted generic contract. Checkout and payments stay
   later, provider-neutral work.
