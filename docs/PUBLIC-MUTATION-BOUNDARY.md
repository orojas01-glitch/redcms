# RED-CMS Public Mutation Boundary

Status: data-only declaration validation, its non-executing preflight, a
separate read-only live-data preflight, an internal core-only
anonymous-subject/CSRF foundation, a fixed-window rate-limit foundation, and an
opaque idempotency-key foundation, an internal atomic transaction runner, a
pure declared-form decoder, HTTP request-envelope normalizer, private static
route selector, non-routable server request-facts adapter, closed response
emitter, pure subject-cookie serializer, core-owned subject-cookie lifecycle
bridge, and an optional Caddy/FrankenPHP
ingress-attestation source with an unlinked PHP verifier are implemented. An
unlinked explicit-input core dispatcher and a disposable supported-server
end-to-end proof are also implemented. A non-executing per-client deployment
profile validator now covers the operator-owned deployment boundary without
loading or applying a profile. The core-owned response-owner composer now
binds that profile to one exact response envelope and fixed lifecycle cookie
descriptors without emitting them. The dispatcher composes those contracts
but remains unlinked from the front controller. A non-executing deployment
review validator now binds a profile hash to non-secret server, trust, and
browser evidence without loading or applying it. This document does **not** add
a production public HTTP endpoint, production-emitted cookie, browser session access,
package, permission, enablement profile, or Store Lite behavior. The five
generic core tables remain empty in the clean starter.

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
| Anonymous cart identity | Core-owned lifecycle now validates/ensures, clears, and rotates one opaque subject with fixed host-only cookie descriptors; the pure serializer remains non-emitting and the lifecycle bridge remains unlinked | No production front-controller, client deployment, package access, route claim, session, or client business data |
| Public CSRF issuance/validation | Internal core issue/verify helper binds a short-lived value to one subject, client database, and validated declaration | No HTTP request parsing, token consumption, handler, ledger, or mutation |
| Public rate decision | Internal core fixed-window claim is limited to 12 requests per 60 seconds for one client, declaration, and opaque subject; the runner uses its transaction-only primitive | No dispatcher, request parsing, package execution, or enablement |
| Public idempotency evidence | Internal core issue/resolve helper binds a 10-minute opaque key to one client, declaration, and subject | No endpoint issues or accepts a key; the helper itself remains non-consuming |
| Public form evidence bootstrap | Core validates the complete presentation, ensures one opaque subject, issues same-subject scoped CSRF/idempotency evidence, and composes the pure form model with exact partial-issuance compensation | No request-global read, package load, HTML/header emission, front-controller link, or public endpoint |
| Public mutation ledger/audit | Internal core runner records one completed key relation, keyed HMAC command/state evidence, one bounded outcome, and one value-free anonymous audit fact | No endpoint, package fixture, browser behavior, or public response-emission path |
| Declared package fields | Pure core decoder accepts one trusted declaration and canonical raw URL-encoded package fields only | No HTTP request ownership, header/cookie/session access, route claim, package execution, or response emission |
| HTTP request envelope | Pure core normalizer accepts explicit static transport facts and releases opaque subject/CSRF/idempotency evidence only after complete validation | No PHP globals, route claim, endpoint, response emission, session, database/runtime/package access, or client state |
| Static mutation-route selection | Core maps one exact un-decoded path to one registrar-bound public route, mutation handler, state loader, and manifest identity | No request-global adapter, route claim, handler invocation, database, response emission, browser behavior, enablement, or client state |
| Server request facts | Core resolves one canonical HTTPS origin from operating-system/local configuration, reads only the current method/raw target, and accepts only an upstream-attested complete fixed security-header capture | No associative header fallback, body-stream read, route claim, handler invocation, database, response emission, browser behavior, enablement, or client state |
| Optional Caddy/FrankenPHP ingress attestation | Separately built source plus isolated temporary-image proofs strip spoofed internal headers, conditionally sign bounded candidate facts, and verify the unlinked PHP HMAC bridge | No default-server change, deployed client binary, active client Caddyfile, dispatcher, endpoint, cookie emission, package execution, or client state |
| Public-mutation dispatcher | Unlinked core composition accepts explicit method/target/capture facts, selects one registrar-bound route, verifies subject/CSRF, decodes declared fields, invokes the atomic runner, and returns only the fixed response model | No front-controller link, response emission, browser issuance, package enablement, or Store Lite behavior |
| Supported-server dispatcher rehearsal | Disposable Docker proof builds the pinned custom FrankenPHP/Caddy binary, runs the real attester, PHP ingress bridge, dispatcher, runner, emitter, and test-only subject-cookie lifecycle over a fresh MySQL database | No client database, default server, deployed binary, production browser flow, package installation, richer enablement, or Store Lite data |
| Per-client deployment profile | Pure validator accepts one non-secret operator review packet with canonical HTTPS, pinned server versions, fixed HMAC/trusted-origin sources, route order, core response/cookie ownership, host-only cookie policy, client isolation, and disabled activation flags | No profile loading, secret resolution, filesystem/database access, deployment, dispatcher link, package enablement, or Store Lite data |
| Response ownership/composition | Core accepts only a valid deployment profile and fixed response envelope, then appends zero, one, or an ordered clear/set subject-cookie descriptor from the lifecycle bridge; the result is non-emitting and deterministic | No arbitrary headers, package/theme ownership, cookie policy drift, request parsing, secret/database access, route claim, dispatcher, front-controller path, browser identity, enablement, or client state |
| Per-client deployment review | Pure validator binds the profile hash to pinned server/artifact evidence, process-environment trusted-origin/HMAC and rotation evidence, and fixed desktop/mobile browser evidence | No secret resolution, file loading, deployment, browser session, response emission, dispatcher link, package enablement, or Store Lite/client state |
| Installation-shaped HTTPS deployment rehearsal | Temporary Docker context builds the reviewed custom binary, mounts external TLS, proves restart-based process-key replacement, and captures fixed browser evidence | No Adriana/client installation, client database, starter data, dispatcher link, package enablement, Store Lite state, or retained private key/secret |
| Response emission | Core accepts only an existing fixed valid response envelope, rejects premature output, then clears and sets its exact no-store/nosniff JSON headers and matching fixed bytes | No request parsing, cookie/session access, database/runtime/package access, route claim, dispatcher, front-controller path, browser identity, enablement, or client state |
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

## Implemented Server Request-Facts Adapter

`includes/addon_public_mutation_server_request_helpers.php` establishes the
smallest server-owned bridge needed before a future dispatcher can use the
existing request-envelope normalizer. Its trusted canonical HTTPS origin comes
only from `RED_PUBLIC_MUTATION_TRUSTED_ORIGIN` in the operating-system
environment or `PUBLIC_MUTATION_TRUSTED_ORIGIN` in ignored local configuration.
The dedicated `red_server_config_value()` deliberately does not inherit the
compatibility helper's `$_ENV` or `$_SERVER` fallback, so `Host` and a
request-projected server value cannot configure the origin.

The adapter reads only the current `REQUEST_METHOD` and raw `REQUEST_URI`.
A later web-server-specific integration must supply raw body bytes and an exact
ordered `{ complete: true, headers: [{ name, value }, ...] }` capture of the
fixed security-relevant header families. It
rejects an associative header map, reordered capture shape, duplicate critical
headers, malformed transport facts, and bodies above the generic 8,192-byte
ceiling. The generic PHP header API and an `HTTP_*` server projection are not
used because neither can demonstrate that duplicate wire-header evidence was
retained for the existing envelope validation.

This is still not a body reader, selector, dispatcher, or emitter. It is not
wired into `index.php`; it does not bootstrap runtime state, inspect a manifest,
open a database, read parsed fields/cookies/sessions, issue a subject or CSRF
cookie, invoke package code, run a transaction, or change lifecycle,
enablement, Store Lite, or client state. Its short-lived captured facts are
private core input for a future dispatcher only.

## Implemented Optional Caddy/FrankenPHP Ingress Attestation

`server-integrations/frankenphp-public-mutation-attestation/` contains source
for one optional operator-built Caddy middleware module. It is not a compiled
binary, root `Caddyfile`, or change to `scripts/dev-php-server.sh`. The module
always removes client-provided internal capture/signature headers, then calls
the next handler without writing a response. It conditionally replaces them
only for a bounded `POST` candidate whose raw path begins `/addons/`, body has
a known non-chunked length of at most 8,192 bytes, transfer/content encodings
are absent, and each fixed security header family occurs at most once.

The signed payload contains only `POST`, the raw request target, measured body
length, SHA-256 body hash, and the ordered present subset of `Origin`,
`Content-Type`, `Cookie`, `X-RED-CMS-CSRF`, and `Idempotency-Key`. It has no
arbitrary PHP header map. Caddy/Go parses content length and transfer encoding
before middleware; the attestation binds that accepted body with length/hash
instead of asserting a raw `Content-Length` line. A missing, duplicate,
encoded, unknown-length, oversized, or malformed candidate receives no
attestation and still passes to the ordinary next handler.

`includes/addon_public_mutation_frankenphp_ingress_helpers.php` is a separate
unlinked future bridge. It accepts the internal capture only with the exact
per-installation `RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY` process-environment
value, rechecks the current method/target and raw body length/hash, and then
uses the existing explicit server request-facts adapter. It never accepts a
key from `Host`, a request header, `$_SERVER`, `$_ENV`, or `config.local.php`.
It reads `php://input` only after HMAC verification. It is not included by
`index.php`, so it creates no endpoint, response, subject cookie, browser flow,
route claim, runtime/package load, database access, enablement change, Store
Lite behavior, or client data.

The source, example Caddyfile, Go handler test, PHP verifier test, and build
boundary are documented in
[`server-integrations/frankenphp-public-mutation-attestation/README.md`](../server-integrations/frankenphp-public-mutation-attestation/README.md).
`scripts/frankenphp-public-mutation-custom-binary-proof.sh` separately stages
only reviewed source into a temporary Docker context, builds the versioned
FrankenPHP/Caddy proof binary, confirms the registered module/configuration,
and sends local container-only traffic through the actual Caddy handler and
unlinked PHP verifier. It proves body preservation, replacement of forged
internal headers, and withholding for spoofed `GET`, duplicate-origin, and
content-encoded requests. The proof container, image, and context are removed
afterward. It does not deploy a client binary or Caddyfile.
An operator must separately build a matching FrankenPHP/Caddy binary and keep
the binary, Caddyfile, HMAC key, certificate/proxy configuration, and each
client's deployment configuration outside the clean starter and other client
installations.

## Implemented Unlinked Core-Owned Dispatcher

`includes/addon_public_mutation_dispatch_helpers.php` is the first core-owned
composition point for the reviewed foundations. It accepts explicit method,
raw target, and a complete server-integration capture; it does not read PHP
request globals. It selects one exact registrar-bound mutation route, refuses
incomplete or ambiguous bindings, requires an attested `POST` capture, runs
the request-envelope normalizer, resolves the opaque subject, verifies CSRF
before semantic field decoding, calls the existing atomic transaction runner,
and returns only the closed response model. It never emits headers or bytes,
starts a session, issues a browser cookie/token, or exposes package values.

The focused dispatcher fixture covers the unlinked result/capture shape,
runtime-unavailable behavior, non-`POST` refusal, missing-attestation refusal,
incomplete registrar refusal, callback non-invocation, and source/front-controller
isolation. The helper is intentionally not included by `index.php`. A later
linking batch still requires an operator review of each client's custom binary,
Caddyfile, TLS/proxy, trusted origin, and HMAC-key boundary.

## Implemented Supported-Server Dispatcher Rehearsal

`scripts/frankenphp-public-mutation-dispatch-proof.sh` is a separate Docker-only
proof for the supported-server boundary. It stages only the reviewed core
helpers and test-only `dispatch-*` endpoint into a temporary context, builds the
pinned FrankenPHP `1.12.4`/Caddy `2.11.4` binary, and starts a fresh MySQL `8.4`
container with the current 45 migrations. The proof image adds `mysqli` only to
that disposable stage because the stock FrankenPHP PHP image exposes mysqlnd
but not the PHP `mysqli` extension required by the RED-CMS helpers.

The fixture provisions an isolated anonymous subject, CSRF token, and
idempotency key through a secret-guarded test-only bootstrap path, then sends a
real attested `POST` through Caddy, the PHP verifier, the core dispatcher, the
atomic runner, and the fixed response emitter. It proves an accepted mutation,
replay after forged internal headers are supplied, `GET` refusal, withheld
attestation refusal, and idempotency conflict. The same temporary image also
proves HTTP-owned subject-cookie `ensure`, `resolve`, `rotate`, and `clear`:
issuance emits one fixed `Secure; HttpOnly; SameSite=Strict` cookie without
disclosing the token in JSON, rotation emits one fixed deletion plus one
distinct replacement, the old token fails closed, a valid token is not
reissued, and clearing emits the fixed deletion value. It checks the resulting
core cleanup state alongside one fixture cart row, one value-free activity row,
and the bounded mutation ledgers. Temporary containers, network, image, build
context, package marker, and database are removed by the script.

The endpoint, bootstrap path, and subject lifecycle paths exist only inside
that temporary proof image; they are not RED-CMS routes, package code,
front-controller links, client deployments, or production browser-cookie
implementations. The proof therefore authorizes the supported-server
integration and core lifecycle gates only; it does not authorize linking the
dispatcher or enabling Store Lite.

## Implemented Core-Owned Browser Subject-Cookie Lifecycle

`includes/addon_public_mutation_subject_cookie_lifecycle_helpers.php` is the
explicit core bridge between the hash-only subject store and a future owner of
the HTTP response. `ensure` resolves one active opaque value or issues a new
subject; `clear` expires the active server subject and returns one fixed
host-only deletion descriptor; and `rotate` expires the locked old subject,
issues a distinct replacement in one transaction, and returns both descriptors.
The bridge refuses an active caller transaction, malformed rotation sources,
unavailable storage, and serialization drift. It never reads request globals,
calls `setcookie()`, emits a header, starts a session, loads package code,
migrates package state, or changes lifecycle/enablement. Related core CSRF,
rate, idempotency, and execution rows remain governed by the existing subject
foreign keys and bounded cleanup; no package data is migrated.

The 18-assertion disposable self-test proves no request/cookie/session/header
mutation, valid-cookie resolution without reissue, old-token and old-CSRF
invalidation after rotation, fixed clearance, malformed-input fail-closed
behavior, active-transaction refusal, and exact cleanup. The Docker rehearsal
proves the same descriptors cross a temporary supported HTTP server. This is a
core lifecycle gate, not a production endpoint or client browser deployment.

## Non-Executing Per-Client Deployment Profile

`includes/addon_public_mutation_deployment_profile_helpers.php` validates one
operator-owned review packet without loading or applying it. The packet binds a
client slug to a separate database name and canonical HTTPS origin, pins the
supported FrankenPHP/Caddy versions, requires the fixed process-environment
HMAC key name and attestation-before-PHP route order, records the trusted-origin
source and operator-owned key-rotation responsibility, and requires the core
response emitter and lifecycle bridge to own all public headers/cookies. It
also requires the fixed host-only subject-cookie policy, configuration/binary/
secret/media isolation outside the clean starter, and all dispatcher/package/
Store Lite activation flags to remain false.

The validator returns only a normalized non-secret profile and deterministic
SHA-256 profile hash. It rejects starter-database reuse, request-derived trust,
server/version drift, reversed ingress order, package/theme response ownership,
cookie policy drift, isolation gaps, secret-shaped fields, and any activation
flag. It reads no PHP request/global state, filesystem, database, package,
secret, lifecycle, or response state. The dependency-free 27-assertion fixture
proves both direct and explicitly operator-trusted proxy profiles while keeping
the deployment packet non-executing.

## Core-Owned Response-Owner Composition

`includes/addon_public_mutation_response_owner_helpers.php` is the next
non-emitting boundary. It requires a valid deployment-profile result, the
existing fixed response envelope, and an optional valid lifecycle result. It
returns the profile hash, unchanged core response, and only the lifecycle's
fixed `Set-Cookie` descriptors: none for a resolved subject, one issuance or
clearance line, or clear-then-set for rotation. Arbitrary response headers,
package/theme ownership, linked-dispatcher profiles, malformed cookie
attributes, and cookie-token body leakage fail closed.

The response-owner composer does not call `header()`, `setcookie()`, `echo`,
or `http_response_code()`. It reads no request/global/session state, secret,
database, filesystem, package, or client state and remains outside `index.php`.
The separate emitter remains the only future core HTTP emission primitive;
actual per-client Caddy/TLS/proxy configuration, trusted-origin/HMAC
provisioning and rotation, and browser deployment evidence remain required
before any front-controller link.

## Non-Executing Per-Client Deployment Review

`includes/addon_public_mutation_deployment_review_helpers.php` binds one
operator-owned review packet to the existing deployment-profile hash. It
requires pinned FrankenPHP/Caddy/TLS/proxy facts, attestation-before-PHP route
order, four deployment artifacts outside the clean starter, non-secret hashes
for the Caddyfile/binary/certificate chain, process-environment trusted-origin
and HMAC sources, explicit active-key/old-key rotation evidence, and no secret
values in the packet.

The packet also records bounded desktop `1440x1000` and mobile `390x844`
browser evidence: HTTPS 200, zero console/network errors, exact response and
cookie policy matches, no opaque token in the body, evidence hashes outside the
starter, no dispatcher link, no mutation endpoint exercise, and no client
state change. The validator reads no file or secret, performs no deployment or
browser action, and returns only normalized non-secret evidence plus a
deterministic review hash. Actual per-client deployment and browser capture
remain the next gate.

## Installation-Shaped HTTPS Deployment Rehearsal

`scripts/frankenphp-public-mutation-deployment-rehearsal.sh` is the next
deployment gate without a client installation. It stages only the reviewed
attestation integration and a static fixture into a temporary Docker context,
builds the pinned FrankenPHP/Caddy binary, mounts a generated localhost
certificate, and runs the attestation route before `php_server` over HTTPS.
The fixture page is not `index.php`, a package route, or a dispatcher.

The rehearsal starts with one process-environment HMAC key, verifies the
static HTTPS page, removes that container, restarts the same installation-shaped
fixture with a distinct key, and proves the old key is absent from the new
process environment. It then captures Chrome desktop `1440x1000` and mobile
`390x844` evidence: HTTPS 200, zero console/network errors, exact no-store and
nosniff headers, no cookie, no opaque token, no dispatcher link, and no client
state change. The external packet retains only hashes and booleans. The
private key, secret values, image, container, and build context are removed;
the rehearsal never accesses Adriana or another client installation.

The command may be pointed at an external evidence directory with
`RED_DEPLOYMENT_REHEARSAL_OUTPUT`. A successful Docker/browser run remains
required before any client-specific deployment or front-controller link.

## Future Core-Owned Request And Response Path

When the dispatcher is linked, core—not a theme, browser script, or package
route file—must own this sequence:

1. Use the implemented server request-facts adapter only with a supported
   server-specific attestation, configured canonical HTTPS origin, raw body,
   and current method/target. The optional Caddy bridge can provide only its
   fixed signed security-header subset plus body length/hash; no dispatcher may
   consult an arbitrary PHP header map. Then use the implemented private selector
   only after core has initialized one current
   trusted enabled runtime context. The dispatcher must still bind that
   selected declaration to the complete raw target and reject every malformed,
   noncanonical, disabled, stale, untrusted, or undeclared request before a
   package callback runs.
2. Use the implemented core-owned, client-scoped anonymous subject foundation
   only through the dispatcher. It is an opaque, unguessable cookie-bound
   reference; it is neither an administrator session, a Member Access identity,
   a raw cookie value exposed to package code, nor a client database/global
   identity. The unlinked dispatcher still requires an existing subject. A
   future response owner may call the implemented lifecycle bridge to ensure,
   clear, or rotate that subject, compose the exact fixed envelope and cookie
   descriptors through the response-owner boundary, then emit only the
   validated result after the per-client deployment profile and reviewed
   production deployment boundaries are accepted.
3. Use the implemented core-owned CSRF issue/verify foundation before semantic
   request parsing or handler invocation. The token, subject, raw cookie,
   request body, and secrets must not enter general logs, package output, audit
   rows, or public errors. The dispatcher enforces same-origin behavior and
   emits no CORS policy that would widen access; token issuance remains tied to
   the accepted subject lifecycle and client response owner.
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
   request/transaction result is known, compose it with any lifecycle cookie
   descriptors through the core response-owner boundary, then use the separate
   core emitter and return immediately. A later dispatcher/emitter may not expose package/
   actor/cart/order/plan/state values, HTML, redirects, arbitrary headers,
   payment data, a privileged action token, or replay status.

The initial rate budget is a core decision, not a browser or package choice:
12 requests per 60 seconds per client database, declared package route, and
anonymous subject. A future scope expansion requires separate review; it cannot
silently weaken privacy, retention, or failure-closed enforcement.

## Store Lite Mapping

The first Store Lite use of this boundary is limited to cart intent. A future
add-to-cart command may carry only a public product reference, bounded
quantity, an optional public variant reference for a variable product, and
core-issued idempotency/CSRF evidence. Core and the commerce service must
resolve current product availability, the selected option tuple, price,
currency, cart ownership, and authoritative total server-side.

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
- the optional Caddy handler unit gate proves spoofed internal-header stripping,
  bounded HMAC capture, body preservation, duplicate/encoded/oversized
  withholding, and no handler response; a later deployment gate must prove the
  matching custom FrankenPHP binary and Caddyfile preserve that contract before
  any dispatcher is linked; neither bridge may configure the trusted origin or
  HMAC key from `Host`, a request header, or a request-projected server value;
- the separate Docker-only supported-server rehearsal builds the matching
  custom binary and temporary MySQL fixture, proves a real attested request
  reaches the dispatcher/runner/emitter, proves issuance/rotation/clearance of
  the core subject-cookie descriptors over temporary HTTP, refuses forged or
  withheld transport, preserves the exact replay/conflict contract, and removes
  every temporary container, image, database, package marker, and build
  context; it does not count as a client deployment;
- the core lifecycle self-test proves exact secure host-only descriptors, no
  token disclosure in JSON/logs, old-token and subject-bound CSRF invalidation,
  malformed-input fail-closed behavior, active-transaction refusal, and exact
  subject/CSRF cleanup before a client response owner is considered;
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

- a production endpoint, browser form, administrator control, package
  JavaScript, or Store Lite user interface; the rehearsal's lifecycle paths are
  test-only and temporary;
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
12. Completed the non-routable server request-facts adapter with a dedicated
    operating-system/local trusted-origin resolver, bounded current
    method/target capture, and an explicit complete fixed security-header
    requirement. It has no body-reader, front-controller, dispatcher,
    browser, response, package, or enablement path.
13. Completed the core-only non-routable response emitter with exact closed
    fixed-envelope validation, premature-output refusal, fixed no-store/
    nosniff JSON headers, and exact body emission. It has no request,
    cookie/session, database/runtime/package, route, browser, dispatcher,
    enablement, or client-state path.
14. Completed the pure non-emitting subject-cookie serializer with exact
    core-issued descriptor-shape validation and one fixed host-only future
    cookie value: 30-minute `Max-Age`, `Path=/`, `Secure`, `HttpOnly`,
    `SameSite=Strict`, and no `Domain` or `Expires`. It has no request,
    database, header/cookie emission, browser, route, dispatcher, enablement,
    or client-state path.
15. Completed the optional non-routable Caddy/FrankenPHP ingress-attestation
    source, unlinked PHP HMAC verifier, and isolated custom-binary proof. The
    proof builds the module into a temporary versioned image, checks registration
    and configuration, and exercises Caddy-to-PHP capture behavior without a
    client deployment. It adds no default server configuration, client binary,
    dispatcher, endpoint, browser cookie, package execution, enablement, Store
    Lite, or client state.
16. Completed the unlinked core-owned public-mutation dispatcher composition:
    explicit captured method/target facts select one registrar-bound route,
    require attested POST transport, verify subject/CSRF before field decoding,
    invoke the existing atomic runner, and return only the fixed response model.
    Its focused fixture has no front-controller, response-emission, browser,
    package, enablement, Store Lite, or client-state path.
17. Completed the disposable supported-server dispatcher rehearsal: a temporary
    pinned FrankenPHP/Caddy image and fresh MySQL database exercised the real
    attester, PHP verifier, core dispatcher, atomic runner, and fixed emitter,
    proving accepted/replay/refusal/conflict behavior and exact ledger/audit/
    rate evidence before cleaning every temporary artifact. It does not deploy
    a client or link the dispatcher. A separate batch may link it only after the
    later per-client Caddyfile/TLS/proxy, trusted-origin, HMAC-key, and browser
    subject-cookie deployment review.
18. Completed the core-owned browser subject-cookie lifecycle bridge and its
    18-assertion disposable plus supported-server HTTP proofs. Ensure, clear,
    and rotate are transactional, fixed-descriptor, non-package operations;
    client deployment remains unaccepted.
19. Completed the non-executing per-client deployment profile and its
    27-assertion dependency-free fixture. It validates the operator-owned
    client database/origin, pinned server and ingress facts, core response and
    cookie ownership, fixed host-only policy, clean-starter isolation, and
    disabled activation flags without loading or applying a deployment.
20. Completed the core-owned non-emitting response-owner composer and its
    14-assertion dependency-free fixture. It binds the profile to the fixed
    response envelope and zero, one, or ordered clear-then-set lifecycle
    cookie descriptors while rejecting arbitrary headers, ownership/policy
    drift, invalid lifecycle state, and front-controller linking.
21. Completed the non-executing per-client deployment review packet and its
    17-assertion dependency-free fixture. It binds the profile hash to
    non-secret server/artifact, process-environment trust/rotation, and fixed
    desktop/mobile browser evidence while refusing secret values, deployment
    file loading, browser state, and dispatcher linking.
22. Added the installation-shaped HTTPS deployment rehearsal harness. It
    retains only non-secret external evidence, keeps the dispatcher and
    front-controller links disabled, and refuses starter-resident output.
23. Completed the pure core-owned public-mutation form UI boundary. It derives
    one static action and exact allowed controls from the validated declaration,
    accepts only a bounded package presentation model plus same-subject issued
    CSRF/idempotency evidence, and renders escaped labels, hidden identifiers,
    bounded integer controls, variant selects, a live status region, and a
    no-script notice. Security evidence is carried only as fetch-controller
    attributes, never package form fields. It reads no request/database/package
    state, emits nothing, and remains absent from the front controller.
24. Completed the core-owned public-mutation form evidence bootstrap and its
    12-assertion disposable fixture. It validates the complete presentation
    before issuance, ensures or reuses one opaque subject, issues a fresh
    declaration-scoped CSRF/key pair for that subject, composes the existing
    form model, and compensates exact partial evidence on failure. It reads no
    request globals, loads no package code, emits no HTML/header, and remains
    absent from the front controller.
25. A separate richer enablement review may admit only packages that satisfy
    every declared prerequisite.
26. Store Lite can then implement its separately distributed catalog and cart
    behavior against the accepted generic contract. Checkout and payments stay
    later, provider-neutral work.
