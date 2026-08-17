# RED-CMS Typed Add-On Adapter Invocation

Status: P3D-6 adds the reusable core-owned typed invocation boundary for an
already-enabled, request-local, manifest-declared adapter. It does not make any
adapter operational by itself.

## Purpose

Adapters connect an internal service to an external provider, but registration
alone must not let arbitrary callers execute a provider-specific closure. Core
therefore owns the only generic adapter invocation boundary and supplies a
closed request/result contract comparable to the existing typed service path.

`includes/addon_adapter_helpers.php` requires:

- one valid manifest-declared adapter identifier;
- one bounded lowercase operation identifier;
- one JSON-compatible input graph with no floats, objects, resources, unsafe
  keys, control bytes, excessive depth, node count, string size, or encoded
  size;
- exact request-local runtime ownership and current manifest declaration; and
- one final `RED_Addon_Adapter_Result` from the registered handler.

The final `RED_Addon_Adapter_Request` exposes only the adapter id, operation,
bounded input, and a by-reference lookup for the owning package's private
runtime secret access. It supplies no database connection, HTTP request,
browser state, session, administrator authority, route, Store Lite object, or
provider client.

## Containment

Core contains package output, exceptions, output-buffer stack changes,
malformed results, and secret-bearing result data or error text. Public results
use only fixed reasons such as `invalid_request`, `adapter_unavailable`,
`adapter_output`, `adapter_failed`, `invalid_result`, `secret_disclosure`,
`adapter_error`, or `completed`.

This remains operator-reviewed first-party PHP, not a sandbox. The invocation
helper does not inspect or authorize network activity inside a future package
handler. Provider transport, outbound-host enforcement, request construction,
timeouts, redirects, response validation, persistence, and client deployment
must pass their own package and operations gates.

## P3D-6 proof

The dependency-free 19-assertion fixture proves missing ownership refusal,
seven malformed-input refusals before invocation, one exact successful typed
call, explicit adapter failure, malformed result, emitted output, exception,
and buffer-stack containment, package-bound secret consumption, undeclared
setting refusal, and data/error secret-disclosure rejection.

It creates no package installation, database, route, provider object, network
connection, payment, order mutation, browser state, or client deployment. The
separately distributed Stripe adapter remains unchanged and refusal-only until
its next reviewed adoption gate.
