# RED-CMS 5.1.1 Release Notes

Status: maintenance release prepared from the reviewed `v5.1.0` tag.

## Release Summary

RED-CMS 5.1.1 fixes the core Other component so its single administrator HTML
editor and every public placement use one canonical value. Successful Other
content creates and updates save the exact same HTML bytes to `ShortDesc` and
`LongDesc` in the existing revision-recorded transaction.

This release contains no Store Lite, payment, subscription, or other post-5.1
feature work.

## Behavior

- The dedicated Other create endpoint resolves `Other` from the core component
  registry, and an existing placeholder must already be an authorized Other
  database record. Submitted `Component` values are not used.
- Existing-record updates derive the component from `RED_Articles`; submitted
  component and secondary-description values cannot change the Other content
  path.
- The browser carries the canonical editor source as UTF-8 base64 so newline,
  attribute, embedded, template, and other advanced HTML bytes bypass visual
  editor and form-encoding normalization.
- Normal content writes mirror the canonical value to both description fields
  under a locked stale-state hash, prepared statements, one transaction, and
  rollback on any write or revision failure.
- A legacy Other record with different fields displays both exact stored
  sources. The administrator must explicitly choose the editor/listing
  (`ShortDesc`) version or the dedicated-page (`LongDesc`) version before any
  content reconciliation occurs.
- Reconciliation records exactly one complete pre-change checkpoint, then
  writes the selected bytes to both fields in that transaction. Restoring the
  checkpoint recovers the former mismatched state.
- SEO-only, placement-only, image-only, and unrelated metadata requests do not
  synchronize Other content.
- Article continues to support intentionally different short and long
  descriptions.

## Verification

- Focused disposable-database Other lifecycle: 31 assertions.
- Headless Chrome desktop/mobile editor and legacy-reconciliation QA: 28
  assertions with four screenshots, no horizontal overflow, and no console or
  page errors.
- Theme contract: 276 assertions.
- Repository PHP lint, JavaScript syntax, shell syntax, and whitespace checks.
- Complete guarded acceptance against an isolated current-schema baseline: all
  46 migrations, 35/35 InnoDB tables, normalized schema signature
  `0e75f9590094e9875c8df2aa83a8fe5646f2aad6931ed168a7ee935984f9f313`,
  CMS/add-on/theme/authentication/CRUD/rollback checks, and exact removal of the
  disposable baseline, acceptance database, and grants.

## Upgrade

Deploy the tracked 5.1.1 files over a 5.1.0 installation after the normal code,
database, and media backup. No new migration is required. Existing mismatched
Other records are not changed automatically; resolve each one through its
administrator editor after reviewing both displayed versions.
