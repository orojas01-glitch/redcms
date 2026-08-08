# RED-CMS Secret-Capable Service Runtime Direction

Status: Gate 1D is implemented for one deliberately narrow add-on profile.
This is a reusable core boundary, not Store Lite commerce behavior.

## Purpose

Some optional services need a server-local credential to perform a bounded
operation. The credential must remain outside the database, package manifest,
request context snapshot, lifecycle plan, audit log, HTTP response, and public
rendering. The service may receive the credential only when core has already
proved the package identity, client installation, setting storage, and enabled
lifecycle state.

The accepted profile is named
`registration_only_service_with_secrets`. It is an extension of the existing
registration-only service profile, not a general permission to enable Store
Lite or any route-, editor-, asset-, migration-, or administrator-tool-bearing
package.

## Admitted manifest shape

The package must:

- declare at least one service and no component;
- declare one or more settings, and every setting must be `secret-reference`;
- declare no component editor, public or administrator asset, migration,
  route, job, administrator tool/action, adapter, or outbound host;
- use the normal trusted manifest, registry, dependency, capability, and
  registrar checks; and
- remain a separately deployed package in the current client's database.

Ordinary settings are intentionally not admitted in this profile. A package
that needs ordinary operational configuration must use a later reviewed
settings-bearing profile. Store Lite therefore remains blocked: it needs
product persistence, editor and administrator surfaces, routes, assets,
ordinary settings, and commerce state in addition to secret credentials.

## Value flow

1. Core validates the package manifest and the exact current installation.
2. Core reads the package's complete per-client setting rows and verifies the
   declared secret-reference schema, stored state, installation identity, and
   deterministic settings state hash.
3. Core validates the server-local opaque-reference allowlist and value
   inventory, then resolves only the package's declared references in private
   request memory.
4. The enablement preflight exposes only counts, state hashes, readiness, and a
   value-free reason. It never serializes or returns the private access object.
5. Owner-authorized atomic enablement revalidates the same evidence under the
   lifecycle/package locks and validates the fixed registrar before committing
   `installed_disabled` -> `enabled`.
6. On an enabled request, core creates a typed
   `RED_Addon_Service_Request`. The package can call
   `secret($settingKey, &$resolvedValue)` for its own declared setting only.
   The lookup result contains status/reason fields; the bytes travel only
   through the internal by-reference variable.
7. Core accepts only a bounded `RED_Addon_Service_Result`. Before returning
   it, core scans all result keys and string values and rejects any value that
   contains a resolved secret. The public invocation result is therefore
   value-free and uses `secret_disclosure` on refusal.

The access object is package-bound, private, non-serializable, and has a
debug representation containing only package id and setting count. It is not
placed in the request input, runtime context snapshot, plan material, audit
detail, revision, response, or browser state. Package PHP remains trusted
first-party in-process code; this contract is containment and disclosure
prevention, not a PHP sandbox.

## Fail-closed rules

Runtime access is unavailable when any of the following is true:

- the package is missing, drifted, disabled, or not the current installation;
- a setting row is missing, duplicated, malformed, or schema-incompatible;
- a required secret reference is unconfigured, not allowlisted, missing from
  the server-local inventory, conflicting, or invalid;
- the settings storage, grant, or state fingerprint cannot be verified; or
- the package registrar, service owner, request, result, output buffer, or
  bounded result shape is invalid.

The failure occurs before package PHP runs for bootstrap configuration errors.
An invoked service that attempts to disclose a resolved secret is contained
and rejected without exposing the matching bytes.

## Isolation and operations

Secret values come only from the client installation's ignored local
configuration or operating-system environment. They are never copied into the
clean starter, another client database, package files, migrations, media,
backups used for distribution, or Git. Each client has its own setting rows,
allowlist, value inventory, package installation, lifecycle state, and runtime
access object. Disablement retains package data and settings but removes the
package from later request bootstrap; it does not rotate or delete a secret.

The profile has no public endpoint, browser cookie, payment callback, cart,
order, administrator form, or navigation entry. A future service with a
public or administrator surface must pass the corresponding route, mutation,
asset, tool, persistence, and browser gates independently.

## Evidence

Gate 1D is accepted only with:

- the dependency-free runtime-secret self-test (8 assertions), covering
  by-reference lookup, missing references, serialization refusal, safe result
  consumption, data/error disclosure refusal, and nested-data scanning;
- the disposable bootstrap fixture (5 assertions), covering enabled service
  consumption, no secret in serialized output, missing-environment refusal
  before registrar execution, Owner preflight evidence, and atomic enablement;
- the full disposable acceptance suite against a fresh current-schema database,
  including 45 migrations, idempotent rerun, 34 final tables, cleanup, and
  unchanged primary-database isolation; and
- PHP lint and `git diff --check`.

## Next boundary

The next milestone after this runtime boundary is the separately distributed
Store Lite package implementation. Gate 2A has already fixed its bounded
package-owned Product contract: simple products use one sellable SKU/price,
variable products use explicit Size/Color-style option tuples, and the package
caps option groups, values, variants, identifiers, integer minor-unit money,
and one installation currency. The package still needs its own migrations,
core-owned editor/create/update/delete and public-placement integration,
catalog/cart/order services, and pay-on-receipt flow. Payment providers,
Events Calendar, Appointments, Donations, Restaurant Ordering, Member Access,
upgrades, uninstall, and purge remain later independent packages or lifecycle
gates.
