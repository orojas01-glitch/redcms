# Add-On Runtime Setting Resolver Direction

Status: implemented generic core contract. This is a RED-CMS prerequisite for
an optional package that needs typed, non-secret per-client settings while
executing an already authorized internal form loader or writer.

## Problem

The existing `red_addon_setting_read_model()` intentionally returns settings
only to a current administrator after a fresh per-setting capability decision.
That protects the administrator editor, but a trusted package loader receives
no administrator identity and must not query the core-owned
`RED_Addon_Settings` table directly. Passing a package setting through a
browser form or allowing a loader to infer it from package data would weaken
the isolation boundary.

## Implemented core-only boundary

An `adminToolFormContracts` entry may declare up to 32 `runtimeSettings` keys.
The core resolver accepts the current client connection and the already-derived
form binding. Package identity and keys therefore come from the exact enabled
request-local owners and normalized manifest contract, not from browser input
or a package callback argument.

Before it reads a setting row, core must require all of the following:

1. Exact enabled request-local tool and form-loader ownership established by
   normal runtime bootstrap, after its persisted installation identity checks.
2. An unchanged normalized form contract and its declared setting-key list.
3. Exact manifest definitions using non-secret supported scalar types and no
   non-null defaults.
4. One configured normalized row per declared key, with matching types and no
   secret reference.

The loader and writer receive one final immutable
`RED_Addon_Admin_Tool_Form_Runtime_Settings` object through their existing
request. It exposes only the declared typed values and an opaque state hash.
That hash participates in form-state, submission-plan, and atomic-writer
evidence, so a configuration change invalidates an already-open edit.

## Boundaries

- No request/session/global reads, CSRF handling, endpoint, renderer, package
  execution, migration, registry mutation, audit, or setting write.
- No secret-reference setting, arbitrary key, default fallback, or access to an
  installed-disabled package.
- No caller-selected package or setting lookup in the loader/writer request;
  the enabled form owner and declaration are server-derived.
- A missing, malformed, unconfigured, stale, disabled, mismatched, or
  secret-bearing row returns a generic unavailable result with no partial value.

Store Lite may now declare its own `catalog.currency` setting. Its product
loader and writer must require a configured three-letter currency from the
injected request object, rather than trusting an existing product row. The
resolver itself adds no Store Lite setting, table, package, or client state.

## Acceptance gate

Disposable-database acceptance proves enabled owner/form binding, typed stored
value injection, missing-setting refusal before provider invocation,
configuration-bound state changes, unchanged forms without declarations,
zero resolver writes, and exact installation/setting/package fixture cleanup.
The full runtime, setting-storage, stale-plan, atomic writer, and rollback suites
continue to cover installation identity, schema drift, malformed values,
transaction containment, and primary-database isolation. Browser or endpoint
claims remain out of scope for this core-only helper.
