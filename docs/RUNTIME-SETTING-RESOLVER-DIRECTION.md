# Add-On Runtime Setting Resolver Direction

Status: Gate 26H design contract. This is a generic RED-CMS core prerequisite
for an optional package that needs one typed, non-secret per-client setting
while executing an already authorized internal loader or writer.

## Problem

The existing `red_addon_setting_read_model()` intentionally returns settings
only to a current administrator after a fresh per-setting capability decision.
That protects the administrator editor, but a trusted package loader receives
no administrator identity and must not query the core-owned
`RED_Addon_Settings` table directly. Passing a package setting through a
browser form or allowing a loader to infer it from package data would weaken
the isolation boundary.

## Proposed core-only boundary

`red_addon_runtime_setting_resolve()` will be a non-executing internal core
helper. It accepts a current client connection, the exact package identity from
the enabled request-local runtime, and one declared setting key. It returns
only a final typed result for an already validated, stored non-secret setting.

Before it reads a setting row, core must require all of the following:

1. Exact trusted runtime package id, version, manifest hash, and inventory
   hash, with a matching enabled installation in the current client database.
2. One exact manifest setting definition and a non-secret supported scalar
   type.
3. One configured, normalized row with a matching type and no secret reference.
4. A call from the already loaded package's registered internal handler; it is
   not an administrator, public-route, HTTP, template, or generic package API.

The result contains only `found`, a typed scalar value, and bounded identity
evidence. It must not disclose another package's setting, stored JSON, secret
references, setting-write evidence, installation inventory, or raw SQL state.

## Boundaries

- No request/session/global reads, CSRF handling, endpoint, renderer, package
  execution, migration, registry mutation, audit, or setting write.
- No secret-reference setting, arbitrary key, default fallback, or access to an
  installed-disabled package.
- No caller-supplied package id; the enabled runtime owner is server-derived.
- A missing, malformed, unconfigured, stale, disabled, mismatched, or
  secret-bearing row returns a generic unavailable result with no partial value.

Store Lite may use this only after its own `catalog.currency` setting is
declared. Its product loader and writer must require a configured three-letter
currency through this core resolver, rather than trusting an existing product
row. The resolver itself adds no Store Lite setting, table, package, or client
state.

## Acceptance gate

Disposable-database acceptance must prove exact enabled runtime/installation
identity, package and key isolation, typed stored-value normalization, missing
and malformed-row refusal, secret/default/unconfigured refusal, disabled and
version/inventory/manifest drift refusal, cross-package refusal, zero writes,
zero package execution, and exact fixture cleanup. Browser or endpoint claims
remain out of scope for this core-only helper.
