# Bug: Global head and body integration code is theme-owned instead of database-managed

## Status

- Confirmed during the Adriana production replacement on 2026-07-26.
- The Adriana Analytics, Google Tag Manager, and Jotform integrations currently
  work through theme files.
- A generic RED-CMS 5.1 database and administrator implementation is not yet
  available.

## Problem

RED-CMS has an Advanced item named `Website_Header`, but that item is not the
HTML document `<head>`. It is trusted HTML rendered inside the active theme's
visible public header region.

As a result, administrators cannot correctly add, replace, disable, or reorder
site-wide integrations such as:

- Google Analytics
- Google Tag Manager
- Jotform or another support/contact widget
- Consent management
- Search-console verification
- Advertising pixels
- Other reviewed site-wide scripts

The only correct current option is to edit theme templates or theme asset
manifests. That makes client configuration harder to migrate, obscures the
difference between reusable theme code and client-owned integrations, and
risks losing integrations when a theme is replaced.

Putting these integrations into the existing `Website_Header` field is not a
valid workaround:

- Analytics and the GTM loader belong in the document `<head>`.
- GTM's `<noscript>` iframe belongs immediately after the opening `<body>`.
- Jotform belongs near the end of `<body>`.
- `Website_Header` renders inside the visible `<header>` element.

## Adriana evidence

The current Adriana integration locations are theme-managed:

- Google Analytics and the GTM loader are in the production page template.
- GTM `<noscript>` is immediately after the opening `<body>`.
- Jotform is loaded from a theme JavaScript asset registered at `body-end`.
- `RED_Advanced.Website_Header` is empty.

The installation therefore works, but an administrator cannot manage these
client integrations through RED-CMS and a theme replacement can omit them.

## Expected behavior

Advanced settings should provide a database-backed **Global Integrations**
manager with three explicit rendering placements:

1. **Document head** — before `</head>`
2. **Body start** — immediately after the opening `<body>`
3. **Body end** — before `</body>`

An authorized administrator should be able to:

- add a named integration;
- choose one of the three placements;
- edit or replace its reviewed code;
- enable or disable it without deleting it;
- set deterministic order within its placement;
- see its last update and responsible administrator;
- preview the placement without executing the code inside the administrator UI;
- restore the previous saved version;
- prevent duplicate active entries for integrations such as a GTM container.

The existing **Website Header** and **Website Footer** region editors must
remain unchanged for compatibility and must be relabeled clearly as visual
theme regions, not document-level code injection points.

## Proposed database model

Add a dedicated table rather than overloading the language-oriented
`RED_Advanced` rows:

`RED_Site_Integrations`

| Column | Purpose |
| --- | --- |
| `RecordID` | Stable primary key |
| `IntegrationKey` | Stable administrator-defined identifier |
| `Title` | Human-readable name |
| `Placement` | `head`, `body-start`, or `body-end` |
| `Content` | Reviewed trusted markup/script content |
| `Active` | Explicit enabled/disabled state |
| `SortOrder` | Deterministic order within a placement |
| `ContentSHA256` | Stale-write and deployment verification |
| `UpdatedBy` | Administrator id or stable actor reference |
| `UpdatedAt` | Last successful update |

Add a bounded revision table or reuse the existing content-revision contract so
that replacement and rollback do not depend on a database backup for every
small integration change.

Integration records are global installation configuration. They must not be
language-specific and must not be copied into the clean starter database as
client content.

## Rendering contract

RED-CMS core—not individual themes—must read, validate, order, and expose the
three integration collections.

- Standard production themes receive prepared `head`, `body-start`, and
  `body-end` integration HTML through the guarded adapter.
- The legacy compatibility renderer receives the same three phases.
- Themes cannot query `RED_Site_Integrations` directly.
- The core emits each phase exactly once.
- Theme activation and rollback preserve database integration records.
- A missing theme hook fails readiness before activation rather than silently
  omitting active integrations.
- Administrator pages and theme previews do not execute stored third-party
  integrations unless a separately approved preview mode exists.

Ordering should be deterministic: `Placement`, then `SortOrder`, then
`RecordID`.

## Security boundary

This feature is an intentional trusted-code boundary. It must not be presented
as ordinary content editing.

- Require a dedicated capability such as `integrations.manage`, limited by
  default to Owner/Webmaster roles.
- Require CSRF protection, a database transaction, stale-write checks, and an
  immutable audit event for every add, update, enable, disable, reorder, and
  rollback operation.
- Use strict length and record-count limits.
- Reject PHP, server-side include syntax, document wrappers, duplicate
  `<html>/<head>/<body>` elements, and placement-incompatible markup.
- Treat external domains, script URLs, iframe URLs, container ids, and inline
  code as reviewable evidence in the save confirmation.
- Integrate with the site's Content Security Policy. Inline scripts must use a
  core-generated nonce or be rejected when the active policy cannot permit
  them.
- Never store API secrets, SMTP passwords, private keys, or service-account
  credentials in these fields.
- Escape all code when displaying it in the administrator editor. Never execute
  it inside the administrator workspace.

## Migration and deployment behavior

1. Create the tables through a reversible, checksum-tracked migration.
2. Leave a clean installation empty.
3. Provide a client-only importer that can extract reviewed theme integrations
   into proposed database records.
4. Dry-run first and report exact placement, order, content hash, detected
   external domains, and conflicts.
5. Refuse overwrites, duplicate active integration keys, or hash drift.
6. Apply only after an exact approved plan and verified database backup.
7. Remove theme-owned duplicates only after browser and source verification
   proves the database-managed versions render once.
8. Rollback restores the prior database rows and the pre-change theme files.

For Adriana, the proposed initial records would be:

- `adriana-google-analytics` — `head`
- `adriana-google-tag-manager` — `head`
- `adriana-google-tag-manager-noscript` — `body-start`
- `adriana-jotform-agent` — `body-end`

The importer must preserve the currently approved identifiers and exact script
URLs without exposing unrelated private configuration.

## Acceptance criteria

- The existing visual `Website_Header` and `Website_Footer` behavior is
  unchanged.
- An authorized administrator can add, replace, disable, reorder, and roll back
  a global integration.
- Unauthorized roles cannot read or mutate trusted integration source.
- Head code appears before `</head>`, body-start code immediately follows
  `<body>`, and body-end code appears before `</body>`.
- Each enabled integration renders exactly once across all public routes.
- Active integrations survive theme activation and rollback.
- Desktop and mobile checks pass across representative routes with no new
  console or network errors.
- Analytics/GTM browser diagnostics show one expected load and no duplicate
  page-view initialization.
- GTM `<noscript>` and Jotform remain in their correct phases.
- CSP behavior is verified for external and inline scripts.
- Dry-run, apply, idempotence, conflict refusal, checksum drift, revision
  rollback, database rollback, and clean-install acceptance all pass.
- No client integration rows or identifiers ship in the generic starter.
