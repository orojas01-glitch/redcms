# RED-CMS Public Mutation Boundary

Status: data-only declaration validation, its non-executing preflight, a
separate read-only live-data preflight, an internal core-only
anonymous-subject/CSRF foundation, a fixed-window rate-limit foundation, and an
opaque idempotency-key foundation are implemented. This document records the
prerequisite for a future public write dispatcher. It does **not** add a public
dispatcher or endpoint, emitted
cookie/header, browser session access, handler, package, permission, enablement
profile, or Store Lite behavior. The four generic core tables remain empty in
the clean starter.

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
| Public-mutation live-data preflight | Read-only installed-disabled package, migration, InnoDB-table, typed-setting, opaque-secret-availability, and core subject/CSRF/rate-limit/idempotency storage evidence | No dispatcher, secret resolution, package execution, lifecycle change, or package-data write |
| Anonymous cart identity | Internal core subject issuance/resolution exists for a future endpoint; it is not a cart identity | No browser cookie read/write, session, package access, route, or client business data |
| Public CSRF issuance/validation | Internal core issue/verify helper binds a short-lived value to one subject, client database, and validated declaration | No HTTP request parsing, token consumption, handler, ledger, or mutation |
| Public rate decision | Internal core fixed-window claim is limited to 12 requests per 60 seconds for one client, declaration, and opaque subject | No dispatcher, request parsing, package execution, idempotency, package-data mutation, or enablement |
| Public idempotency evidence | Internal core issue/resolve helper binds a 10-minute opaque key to one client, declaration, and subject | No dispatcher, key consumption, replay result, package-data mutation, or enablement |
| Public mutation ledger/audit | Does not exist | None |
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
subject/CSRF/rate-limit/idempotency storage shape.

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
The manifest validator reserves all four core table names, so an add-on cannot
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
unavailable. Resolution remains deliberately non-consuming: a later core
transaction runner must atomically reserve the key, package mutation, replay
evidence, and audit fact together. No helper reads request/cookie/session
globals, emits a response, loads package PHP, invokes a handler, changes
lifecycle, or mutates package data.

## Future Core-Owned Request Path

When implementation is approved, core—not a theme, browser script, or package
route file—must own this sequence:

1. Recognize only a current trusted, enabled, static declaration in the
   reserved package namespace. It must reject every malformed, noncanonical,
   disabled, stale, untrusted, or undeclared request before package code runs.
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
4. Parse only the declared scalar form fields. Core rejects unknown, repeated,
   nested, noncanonical, malformed, oversized, or out-of-range values. A
   browser never supplies a package id, route owner, price, currency, total,
   order state, cart owner, permission, plan, or database target.
5. Re-derive the exact manifest, registry, enabled runtime owner, anonymous
   subject, idempotency key, current fixed-window rate decision, and current
   package state on the server. A future request must be refused when rate-limit
   storage or required state evidence is unavailable; a client-provided retry
   flag cannot bypass a refusal.
6. Invoke only one registrar-bound mutation handler through a future
   core-owned transaction runner. The handler receives a final typed command
   and controlled transaction context, never PHP request globals, raw cookies,
   CSRF values, server headers, arbitrary SQL text, or a package-selected
   connection. The runner must constrain writes to the declared package-owned
   InnoDB tables.
7. Reserve the declared idempotency fact before mutation, reload the exact
   server-derived postcondition afterward, and atomically commit the package
   change, replay evidence, and one value-free audit fact. Output, exceptions,
   buffer/header tampering, stale state, transaction loss, postcondition drift,
   or ledger/audit failure must roll back or refuse the complete request.
8. Construct the response in core. It may expose only a small documented
   success/refusal vocabulary with no-store and nosniff protections. It may
   not expose package/actor/cart/order/plan/state values, HTML, redirects,
   arbitrary headers, payment data, or a privileged action token.

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
subject/CSRF/rate-limit/idempotency storage in this list, but it supplies no key
consumption, transaction, response, or richer-enablement evidence. No current
enablement profile satisfies the complete set. The future validator must report
a bounded, value-free refusal rather than silently downgrade a public write to a
weaker route.

## Required Future Acceptance Evidence

The current 19-assertion subject/CSRF test, 15-assertion rate-limit test, and
18-assertion idempotency test prove exact storage, hash-only/opaque persistence,
declaration/database scope, the fixed 12-per-60-second cap, 10-minute key
lifetime, subject-bound expiry, bounded cleanup, and absence of a package
fixture. They are not HTTP, browser, or package-mutation acceptance.
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
7. A separate batch may add a transaction runner and core-owned bounded
   responses, with disposable fixtures only.
8. A separate richer enablement review may admit only packages that satisfy
   every declared prerequisite.
9. Store Lite can then implement its separately distributed catalog and cart
   behavior against the accepted generic contract. Checkout and payments stay
   later, provider-neutral work.
