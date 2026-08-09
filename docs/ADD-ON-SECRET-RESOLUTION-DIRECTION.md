# RED-CMS 5.1 Core-Owned Secret Resolution and Reference Replacement

Status: Gate 1C implementation complete and disposable acceptance-verified;
the replacement endpoint remains unlinked from administrator navigation and
package runtime secret consumption remains gated behind richer enablement.

This slice adds the smallest safe bridge between an operator-provisioned
server secret and an already-declared add-on `secret-reference` setting. It
does not make secrets part of the clean starter, a client database, a package
manifest, a browser response, or package-owned PHP.

## Server-local value sources

The opaque reference allowlist remains separate from the value inventory:

- `ADDON_SECRET_REFERENCES` in ignored `includes/config.local.php` and
  `RED_ADDON_SECRET_REFERENCES` in the operating-system environment declare
  which lowercase `config:` references the operator permits.
- `ADDON_SECRET_VALUES` in ignored `includes/config.local.php` may map those
  exact references to local values.
- `RED_ADDON_SECRET_VALUES_JSON` may provide a JSON object with the same exact
  reference-to-value mapping from the operating-system environment.

The value inventory accepts at most 200 references, an 8 KiB value per
reference, and 1 MiB total. Empty, NUL-bearing, malformed, nested, list-shaped,
unknown, or conflicting local/environment entries fail closed. Environment
and local values may repeat only when their bytes are identical. The resolver
reads neither `$_POST` nor request-projected `$_SERVER` values.

## Core-internal resolution

`red_addon_secret_resolve()` requires a valid reference, the separately
validated operator allowlist, and a valid server-local value inventory. It
returns only fixed resolution status; the secret bytes are delivered through
an internal by-reference output so they cannot accidentally appear in a
serialized result. Callers must not log, render, audit, persist, or return the
resolved value. The helper opens no database, reads no package file, executes
no package PHP, and changes no lifecycle or activation eligibility.

## Reference replacement contract

The reserved authenticated endpoint is:

`/admin/bin/replace_addon_secret_references.php`

It is intentionally unlinked from administrator navigation while the separate
secret-management UI is reviewed. Its POST contains exactly:

```text
csrf_token
PackageID
ExpectedPlanSha256
SecretReferences[<declared-secret-setting-key>]
```

The submitted values are opaque `config:` identifiers, never secret values.
Core re-discovers the validated data-only package, rechecks every declared
setting permission and current client installation, resolves each proposed
reference server-locally, preserves current ordinary values and unsubmitted
secret references, and passes the complete typed object to the existing
atomic settings writer. A first binding may supply every previously missing
secret reference in one request; a later replacement may supply only the
changed secret setting. The writer retains separate ordinary/secret columns,
shared lifecycle/package locks, stale-plan comparison, exact postcondition
reload, value-free audit detail `secret_reference_replaced`, and no-op
suppression.

The endpoint returns only fixed `updated`, `unchanged`, `invalid_request`,
`secret_unavailable`, `secret_unconfigured`, `stale_plan`, or generic
`settings_unavailable` results. It never returns a reference identifier,
secret value, source path, provider detail, package path, plan detail, or SQL
error. It executes no package PHP, changes no lifecycle state, creates no
route, and does not make Store Lite enablement-ready.

## Acceptance gate

Disposable evidence must prove:

1. exact local/environment value-source validation and conflict refusal;
2. allowlist-required resolution with no serialized secret bytes;
3. exact replacement request shape and secret-only setting keys;
4. initial missing-secret binding and existing-reference replacement;
5. server-local unavailability, stale-plan, permission, and schema refusal;
6. atomic ordinary/secret-column persistence, bounded audit, no-op behavior,
   and rollback; and
7. no package execution, lifecycle change, other-client mutation, or retained
   server-local secret fixture after cleanup.

Richer package enablement, package runtime secret consumption, a polished
administrator secret-management screen, and Store Lite remain separate gates.
