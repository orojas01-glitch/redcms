# Local TinyMCE 8 core upgrade

Date: 2026-09-09. Scope: local self-hosted core maintenance for the 5.1.2 candidate.
The owner confirmed RED-CMS is open source and selected the free self-hosted route.
No cloud account, payment, license purchase, client deployment or server migration
was performed. Scala demo preparation is the next separate deployment phase.

## Baseline and provenance

Baseline: `eba2eb6`, clean `codex/5.1.2-security-hardening` tree.
Rollback checkpoint: `checkpoint/before-tinymce-upgrade`.
The old vendor payload is recoverable from that commit and is no longer shipped.
Use a reviewed revert of this upgrade commit for rollback; restore the vendor
payload and all six configurations together, not just `tinymce.min.js`.
Rolling back restores the old vulnerable dependency and is not a release remedy.

Official `tinymce` npm 8.9.0 was selected from vendor-linked registry metadata.
Its SHA-512 integrity matched before extraction. It has no package installation
scripts or runtime npm dependencies; no package script was executed. All 272
upstream files are preserved, with exact per-file SHA-256 inventory. Trust class:
preferred official source; project-local vendoring only. Built-in upstream code
is not independently certified by this check.

## Narrow compatibility changes

- Same `/admin/assets/js/tiny_mce/` hosting path; loader cache key updated to 8.9.0.
- GPL mode and no promotional upgrade button at six initialization points.
- Grouped v4 plugin arrays joined into supported space-separated plugin names.
- `styleselect` renamed to `styles`; existing formatting groups and save paths kept.
- Removed requests for obsolete paste/textcolor/print/hr/contextmenu plugins:
  corresponding core features no longer require those plugin files.
- Removed the unconfigured template request and unbundled moxiemanager request.
  No template catalogue or MoxieManager implementation was found in this checkout.
- Legacy `mode/elements` targeting replaced with explicit selectors. Inert Advanced
  initialization remains inert; plain fields are not silently converted to editors.
- New `.tox-tinymce` container uses the existing CMS field borders and sizing.
  TinyMCE's supported Silver/Oxide interface replaces the obsolete built-in skin;
  no CMS form, page structure or 5.2 editor redesign was introduced.
- Other's exact HTML mode and reconciliation remain independent from visual
  sanitization. Modern editor security defaults were not disabled to preserve
  unsafe embeds; advanced source continues through the existing trusted-HTML path.

## Verification

- `node scripts/tinymce-vendor-self-test.mjs`: 272 exact vendor files, all six
  GPL/core configurations and dependency inventory pass. Included in core checks.
- `node scripts/tinymce-browser-qa.mjs`: 84 assertions pass; real bundled editor in loopback synthetic
  fixtures, 1440px desktop and 390px phone widths. Editable GPL mode, plugin loading,
  undo/redo, textarea save, table insertion, XSS sanitization, source-dialog save,
  teardown/reinitialization, independent Article field, Other canonical visual
  payload and no horizontal overflow pass, with no external requests or console
  errors. The Advanced config is additionally mounted on a synthetic field after
  checking its normal selector mounts nothing. Legacy Gallery configs are tested
  with synthetic Banner targeting; database acceptance covers real CRUD separately.
- Existing `node scripts/other-content-browser-qa.mjs`: 28 exact HTML/reconciliation
  checks pass across desktop/mobile. Local screenshots were visually inspected.
- Existing full disposable acceptance: all 47 migrations, auth, theme/add-on/content
  lifecycles, uploads, no-op migration rerun and forced rollback pass, followed by
  exact temporary database/grant cleanup. No retained primary migration occurred.
- OSV query on public package coordinates: zero matches for the four inventoried
  versions, including TinyMCE 8.9.0. The prior 19 TinyMCE 4 matches are resolved by
  replacement, not suppressed. This does not cover unidentified legacy utilities.

No real client content corpus, live Scala server, browser TLS deployment, remote CI
run or complete client-specific CSP was exercised. Corresponding source and license
notices must accompany the later client distribution. The historical PayPal token
and the other operational hardening gates remain independent and unresolved.


The exact upstream TypeScript source archive has also been prepared outside the
web tree at `/private/tmp/redcms-tinymce-source-8.9.0.tar.gz`. Its source URL and
SHA-256 are recorded in the vendor manifest so the later Scala release preparation
can reacquire/verify it. Preserve it with the distribution's source materials;
the temporary path is not durable backup storage.

Final core/theme checks pass, and current tracked-source Gitleaks reports zero
findings. Upstream-only whitespace is preserved with a narrowly scoped vendor
attribute; first-party whitespace checks and full vendor hash verification remain
active. The earlier baseline `.gitleaksignore` was not broadened for this upgrade.
