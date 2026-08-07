# Optional FrankenPHP Public-Mutation Ingress Attestation

This directory contains source for one operator-built Caddy middleware module,
not an active RED-CMS server configuration or a compiled binary. It is the
first non-routable server-integration step for a later core-owned public
mutation dispatcher. It does not add an endpoint, call PHP, select an add-on
route, issue/clear/rotate a browser cookie, enable a package, or create Store
Lite/client data.

The default local server command remains unchanged:

```sh
scripts/dev-php-server.sh
```

It does not read this directory or the included `Caddyfile.example`.

## What the handler does

The handler runs before `php_server` in an explicit Caddy `route` block. On
every request, it removes client-provided `X-RED-Public-Mutation-Capture` and
`X-RED-Public-Mutation-Signature` headers. It then continues downstream
without writing a response.

Only a narrow candidate can receive replacement internal headers:

- `POST` with a raw request path beginning `/addons/`;
- known non-chunked body length at most 8,192 bytes;
- no transfer or content encoding;
- at most one `Origin`, `Content-Type`, `Cookie`, `X-RED-CMS-CSRF`, and
  `Idempotency-Key` header; and
- bounded, control-free values for those fixed header families.

For such a request, the handler preserves the downstream body and signs only
the method, raw target, measured body length, SHA-256 body hash, and ordered
fixed security-header subset. It emits:

- `X-RED-Public-Mutation-Capture: v1.<base64url-json>`
- `X-RED-Public-Mutation-Signature: sha256=<hmac-sha256>`

The capture deliberately does not include an arbitrary PHP header map or a
raw `Content-Length` header. Caddy/Go owns content-length and transfer-encoding
parsing; the signed length/hash binds the body that PHP will receive. A later
PHP bridge accepts the capture only if its HMAC, current method/target, body
length, and body hash all agree. Missing or invalid attestation is not an
alternate route: the future dispatcher must issue its ordinary generic
refusal.

## Per-installation key

Set exactly one 256-bit key in the FrankenPHP process environment for each
client installation:

```sh
RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY=<64 lowercase hexadecimal characters>
```

The Caddyfile contains the environment *name*, never the secret value. The
unlinked PHP verifier reads only the same process environment value; it does
not use `$_SERVER`, `$_ENV`, `Host`, a request header, or
`config.local.php` for this key. Keep the key out of source control, command
history, logs, diagnostics, and client packages. Rotate it per installation
through the client deployment runbook. The core browser subject-cookie
lifecycle bridge is now implemented and proven separately, but HMAC-key
rotation, production response binding, and browser evidence remain deployment
gates in this integration slice.

`RED_PUBLIC_MUTATION_TRUSTED_ORIGIN` remains a separately configured
non-secret canonical HTTPS origin. It is never derived from `Host` or a
request value.

## Per-client deployment review packet

Before any client-specific dispatcher link, prepare an operator-owned profile
and validate it with the dependency-free core helper:

```sh
php scripts/addon-public-mutation-deployment-profile-self-test.php
```

The profile is a review artifact, not a runtime configuration file. It records
the separate client database, canonical HTTPS origin, pinned FrankenPHP/Caddy
versions, process-environment HMAC-key name, server-local trusted-origin
source, attestation-before-`php_server` route order, response/cookie ownership,
fixed host-only cookie policy, and clean-starter isolation. It must keep the
dispatcher, package, and Store Lite activation flags false. The validator
returns only a deterministic non-secret hash; it does not load the profile,
resolve a key, read a database, or deploy the client.

The core-only response-owner composition step is also dependency-free:

```sh
php scripts/addon-public-mutation-response-owner-self-test.php
```

The response-owner composer accepts that validated profile, one fixed core
response envelope, and optional lifecycle descriptors. It returns only the
allowed response plus zero, one, or ordered clear-then-set `Set-Cookie` lines;
it emits no headers/body and remains unlinked. Actual per-client Caddy/TLS/
proxy, trusted-origin/HMAC provisioning and rotation, and browser deployment
review remain required before a front-controller link.

The non-executing deployment-review packet can be checked independently:

```sh
php scripts/addon-public-mutation-deployment-review-self-test.php
```

It binds the profile hash to non-secret server/artifact hashes,
process-environment trusted-origin/HMAC and old-key-revocation evidence, and
fixed desktop/mobile browser results. It reads no deployment file or secret,
does not open a browser or change client state, and cannot link the dispatcher.
Actual per-client Caddy/TLS/proxy deployment and browser capture remain the
next gate.

## Build and configuration boundary

The module targets Caddy `v2.11.4`, which matches the local FrankenPHP
runtime used by this repository, and its `go.mod` therefore requires Go
`1.25.1` or later. A stock FrankenPHP binary cannot load a new Caddy module
after it has been built. An operator must build a matching
FrankenPHP/Caddy binary using the official custom-module process, then add
this module with the equivalent of:

```sh
--with github.com/orojas01-glitch/redcms/server-integrations/frankenphp-public-mutation-attestation=./server-integrations/frankenphp-public-mutation-attestation
```

Keep the official FrankenPHP modules in that build as documented by
[FrankenPHP’s custom Caddy-module guidance](https://frankenphp.dev/docs/docker/).
Use the included [`Caddyfile.example`](Caddyfile.example) only as a placement
example: copy its `route` ordering into an installation-owned Caddyfile with
the real site name, root, TLS/proxy rules, and per-client environment. Do not
place a Caddyfile in the RED-CMS repository root unless an operator has
explicitly chosen to replace the default local development server behavior.

The built binary, Caddyfile, process environment, certificates, and all
deployment configuration remain outside the clean starter distribution and
outside every other client installation.

## Verification

Run the Caddy handler unit test separately from the PHP/MySQL acceptance
suite:

```sh
scripts/frankenphp-public-mutation-ingress-self-test.sh
```

It requires Go and verifies header stripping, HMAC capture, body preservation,
duplicate/encoded/oversized refusal, and no handler-generated response. The
ordinary `scripts/dev-acceptance.sh` suite runs the paired dependency-free PHP
verifier test, including one fixed Go/Caddy JSON-and-HMAC compatibility
fixture, but does not require Go or build a server binary.

## Isolated custom-binary proof

The separately runnable command below stages only this module, its disposable
probe, and the exact PHP-helper dependency set into a temporary Docker build
context. It does not send local configuration, client files, media, database
data, or a clean-starter package to Docker.

```sh
scripts/frankenphp-public-mutation-custom-binary-proof.sh
```

The proof builds a temporary versioned FrankenPHP `1.12.4`/Caddy `2.11.4` binary with
this module, confirms that the binary lists
`http.handlers.red_public_mutation_attestation`, adapts the nested proof
Caddyfile, and makes local container-only requests through the real Caddy
handler and unlinked PHP verifier. It proves a valid body reaches PHP, forged
internal headers are replaced, and spoofed `GET`, duplicate-origin, and
content-encoded requests receive no attestation. The image, container, and
temporary context are removed after the proof.

The repository workflow runs this proof on the relevant pull requests and on
`main`. It is a generic build-and-request gate, not deployment authorization:
an operator must still build a matching binary for the chosen client, preserve
the client-specific Caddyfile/TLS/proxy configuration and per-installation key
outside the starter, and obtain a later dispatcher review before enabling any
public-mutation behavior.

## Isolated supported-server dispatcher rehearsal

After the custom-binary proof is green, the separate command below stages a
test-only dispatcher endpoint and the reviewed core helpers into another
temporary Docker context:

```sh
scripts/frankenphp-public-mutation-dispatch-proof.sh
```

It builds the same pinned FrankenPHP/Caddy binary, adds `mysqli` only to the
disposable proof image because the stock PHP image does not expose that
extension, starts a fresh MySQL `8.4` container, applies the current migrations,
and carries one secret-guarded fixture request through Caddy attestation, the
PHP verifier, the core dispatcher, atomic runner, and fixed response emitter.
The proof checks accepted/replay, forged-header replacement, `GET` refusal,
withheld-attestation refusal, idempotency conflict, and exact execution,
activity, subject, CSRF, idempotency, and rate-limit evidence. Its fixture
endpoint, bootstrap secret, package marker, database, image, network, and
build context are removed on success or failure. This rehearsal does not link
the dispatcher to `index.php`, deploy a client binary/Caddyfile, issue a browser
cookie, change package enablement, or create Store Lite data.

The repository's FrankenPHP proof workflow runs both the custom-binary ingress
proof and this supported-server rehearsal when the integration or its reviewed
core dependencies change.
