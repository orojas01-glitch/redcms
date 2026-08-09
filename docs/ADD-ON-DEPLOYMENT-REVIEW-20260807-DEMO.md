# RED-CMS 5.1 Add-On Deployment Review: Demo Client

Date: 2026-08-07

This is a read-only Gate 1B boundary review of the separate demo client at
`https://demo.red-sphere.com/`. The Adriana installation, its database, and
its deployment area were not accessed.

## Evidence

- `https://demo.red-sphere.com/` returned HTTP 200.
- `https://demo.red-sphere.com/administracion/` returned HTTP 200.
- `https://demo.red-sphere.com/administracion/login` returned HTTP 200.
- The observed response server was Apache; this review did not claim a
  Caddy/FrankenPHP deployment.
- The public and administrator-login HTML contained no `Store Lite`, RED-CMS
  add-on, cart, or checkout markers.
- No administrator session was created, no form was submitted, and no
  database, filesystem, package, or client content was changed.

## Decision

The existing demo deployment is a valid separate-client boundary review, but
it is not an approved dispatcher deployment target. The add-on dispatcher,
Store Lite package, and package-owned business data remain unlinked and
uninstalled. Any future activation requires an explicit client-specific
Apache/FrankenPHP or supported-server deployment plan, origin/key provisioning,
browser evidence, and a separate client approval.

The next generic RED-CMS 5.1 slice is the smallest core-owned settings
editor/endpoint contract. It must remain package-agnostic, permission-scoped,
CSRF-protected, secret-masked, and activation-neutral before any Store Lite
package is distributed.
