# RED-CMS 5.1 Core-Owned Add-On Settings Editor

Status: Gate 1B implementation complete and disposable acceptance-verified;
the endpoints remain unlinked from administrator navigation and actual secret
lookup/replacement remains gated.

This slice is the smallest useful generic settings surface after the
per-client storage, atomic writer, authorized read model, and non-executing
secret-availability evidence foundations. It is core-owned and package-
agnostic. A package contributes only the validated data-only manifest schema,
labels, types, choices, defaults, and already-declared permissions.

## Boundary

The editor may update ordinary typed settings for one trusted package in the
current client database. It may not execute package PHP, change package
lifecycle, enable a route or administrator tool, resolve a secret, alter
another client, or add Store Lite data to core.

The first slice supports `text`, `boolean`, `integer`, `select`, `url`, and
`email` settings. A `secret-reference` setting is displayed as configured or
not configured and is retained unchanged by ordinary edits. Replacing a
secret reference requires a later, separately approved secret-management
surface; the editor never receives or prints a secret value or opaque
reference.

## Core-owned read model and form

For an authenticated administrator, core derives the package, installation,
manifest, lifecycle state, setting permissions, and current values from the
database and trusted registry. A browser-supplied package name is only a
selector; it never identifies the package, permission, table, or writer.

The rendered form must:

- expose only settings for which the current administrator has the exact
  manifest-declared permission;
- render escaped core-owned labels and controls with stable `id`/
  `aria-describedby` relationships and a namespaced RED-CMS class;
- show the package version and lifecycle state as read-only context;
- include the current deterministic model/plan hash and one current CSRF token;
- show a secret setting only as `configured` or `not configured`, with no
  `value`, reference identifier, default, or credential-bearing HTML;
- contain no package markup, callback output, asset injection, or hidden
  secret field; and
- remain available for `installed_disabled` and `enabled` packages without
  changing either state.

For this first writer-backed slice, every declared setting must be authorized
before a form is rendered or saved. A mixed-grant administrator receives the
generic unavailable response rather than a partial form that could rewrite
settings outside the current grant. Partial per-setting editing can be
reviewed later without weakening the atomic complete-configuration writer.

No package-provided HTML, JavaScript, CSS, or field renderer is accepted in
this slice.

## Core-owned POST contract

The reserved authenticated endpoint is `/admin/bin/update_addon_settings.php`.
It is not a public route and is not linked to the front controller. The
canonical request contains exactly:

```text
csrf_token
PackageID
ExpectedPlanSha256
Settings[<declared-key>]
```

Core accepts only one scalar value per declared ordinary setting. It rejects
duplicate keys, nested arrays, unknown keys, malformed encoding, oversized
payloads, invalid package selectors, invalid hashes, and secret-reference
submissions. Core re-derives the package and every setting permission, decodes
and validates the complete typed object, preserves existing secret-reference
rows, and passes the exact fresh plan to the existing atomic writer.

The response is a fixed core-owned result:

- `200 {"ok":true,"status":"updated"}` when a change committed;
- `200 {"ok":true,"status":"unchanged"}` for an exact no-op;
- `400` for an invalid request shape;
- `403` for missing/invalid session or CSRF, or no authorized settings;
- `409` for stale model/plan or concurrent state; and
- `422`/`503` for validation, storage, identity, lifecycle, or writer refusal.

Failure responses contain no package path, setting value, secret reference,
plan detail, SQL error, callback output, or client identifier beyond the fixed
status/reason vocabulary. The endpoint invokes no package code and records
only the existing bounded value-free `addon.settings.updated` audit fact after
an actual commit.

## Acceptance gate

Before this slice is considered complete, disposable tests must prove:

1. exact authorized-setting visibility and denial for mixed grants;
2. escaped labels, controls, errors, model hash, and CSRF in desktop/mobile
   administrator rendering;
3. strict ordinary scalar decoding and type validation;
4. rejection of unknown, duplicate, nested, malformed, oversized, stale, and
   secret-bearing submissions;
5. preservation of configured secret-reference rows without disclosure;
6. atomic update, exact no-op, concurrent/stale refusal, audit failure, and
   injected late-failure rollback;
7. unchanged lifecycle, registry, package execution, route/tool eligibility,
   and other client databases; and
8. cleanup of every disposable package, administrator, grant, setting row,
   audit row, session, and temporary browser artifact.

The full disposable acceptance suite and relevant authenticated desktop/mobile
browser checks must pass before this contract can clear the settings UI/
endpoint gate. Actual secret lookup/replacement, richer enablement, and the
Store Lite package remain separate milestones.
