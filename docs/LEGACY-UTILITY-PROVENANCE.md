# Legacy utility provenance and local corrections

Review date: 2026-09-09. Baseline: `46f2b75` on
`codex/5.1.2-security-hardening`, clean tracked worktree.
Rollback checkpoint: `checkpoint/before-legacy-utility-review`.
Scope: seven remaining legacy JavaScript assets and their direct loader/transport
compatibility. No Scala/HostGator deployment, database migration, account access,
package installation script or upstream build tool was executed.

## Sources, licensing and decisions

| Files | Verified provenance | Current decision and usage |
| --- | --- | --- |
| `admin/assets/js/jquery.filedrop.js`, `jquery.filedrop2.js`, `jquery.filedrop3.js` | Weixi Yen/Resopollution MIT `jquery-filedrop` 0.1.0 family. Closest historical implementation at `a7ad5a723bc5b53c21d4ac2c151775fdae16d029`; exact original import revision is not claimed. The old numbered copies differed only in function name and a misleading upstream URL. | RED-CMS compatibility fork 1.0.0; all three standalone URLs/APIs retained and checked for drift. Modern Gallery/Video/Banner forms exit before these fallback scripts. They remain available for legacy branches. |
| `js/jquery.mobilemenu.js` | Original bytes exactly matched mambows/mobilemenu `20cfbc8b20784b8b2ca98db8cf9920e2f49895f7`, version 1.1. No license declaration found in that repository's files/history inspected. | Historical code replaced by an original MIT RED-CMS implementation of the existing API. Same select-menu structure/classes/options, with text-safe labels and safe URL protocols. Loaded by legacy fallback theme. |
| `js/superfish.js` | Original 1.7.4 banner and tagged upstream implementation, with local startup changes. Updated from official 1.7.10 tag at `442820e74732cd5bcfb00f86d37b0cd57d867294` (2018-02-27). MIT option selected from upstream's dual-license header; upstream MIT notice retained. | Exact upstream distribution bytes followed by the existing RED-CMS startup hook. Loaded only by legacy fallback theme. |
| `js/jquery.easing.1.3.js` | Original bytes exactly matched gdsmith/jquery.easing `0ea4f96be8b46215ec63a84108ceec74ef3374f2`, BSD-3-Clause. | Upstream bytes retained, with a small RED-CMS argument adapter for jQuery 3. No checked-in core loader references this asset; its public compatibility URL remains available. |
| `js/tm-scripts.js` | Historical file contained no author/license/version declaration; exact upstream origin was not established. | Unattributed glue replaced with original MIT RED-CMS year/mobile-menu initialization. The existing URL remains. Loaded only by legacy fallback theme. |

The authoritative hashes, before/after identities, upstream comparison metadata,
and current use classifications are in `release/legacy-utilities-manifest.json`.
Exact upstream prefixes are checked separately for Superfish and easing. Numbered
filedrop helpers remain standalone, with one normalized implementation enforced by
regression checks; changing one without the others fails the gate.

Upstream read-only repository snapshots observed: filedrop last commit 2017-02-09,
mobilemenu 2013-07-27, Superfish 2020-07-22, easing 2021-08-13. These projects are
dormant, not evidence of active vendor security support. Superfish and the MIT/BSD
sources are acceptable retained inputs with pinned provenance and local regression
coverage. File-only adoption was used; obsolete upstream development dependencies
were neither installed nor shipped. The current replacement/fork code is RED-CMS's
maintenance responsibility. Unknown historical origins are not presented as verified
licenses, and that unattributed code is no longer delivered at the replaced paths.

Primary sources:

- https://github.com/weixiyen/jquery-filedrop
- https://github.com/mambows/mobilemenu
- https://github.com/joeldbirch/superfish/tree/v1.7.10
- https://github.com/gdsmith/jquery.easing

## Verified problems and narrow corrections

1. Mobile menu construction reinterpreted escaped label text as HTML, retained
   `javascript:` option targets and duplicated selects on repeated initialization.
   The replacement uses native text options, protocol validation and one owned
   select per menu. Existing public paths, nesting indicators and external HTTP(S),
   mail and telephone links are preserved. Desktop anchors are not rewritten by
   this helper; server-generated link validation remains a separate boundary.
2. Superfish 1.7.4 invoked removed jQuery `.load(handler)` on iPhone user agents.
   Official 1.7.10 removes that path. Existing startup, focus and submenu behavior
   remain verified. No dropdown layout or theme redesign was made.
3. `tm-scripts` wrote an extra viewport, disabled zoom and used the removed
   two-function `.toggle()` event API. Its replacement leaves the document's
   viewport intact and delegates interaction to the existing menu helpers.
4. Filedrop shared options/files across instances and allowed repeated binding.
   Tests reproduced uploads going to the wrong instance's endpoint/callback.
   The fork owns options and batch state per element, safely rebinds handlers,
   refuses overlapping batches and preserves all three callback interfaces.
5. Manual binary multipart serialization could corrupt non-ASCII filenames and
   used global prototype mutation. Native FormData now owns multipart encoding;
   the existing parameter names, file bytes, dynamic data, rename, size/count limits,
   progress, CSRF header and completion callbacks are retained.
6. Filedrop reported successful HTTP responses with malformed JSON as success.
   Invalid JSON, non-object JSON, transport failures and non-2xx responses now go
   to failure callbacks. Initial foreign-origin upload URLs are refused before
   XHR/CSRF use. The server upload handler is expected to return direct JSON;
   this is not a general redirect-security guarantee for arbitrary servers.
7. Legacy upload URLs redundantly included the CSRF token even though the guarded
   upload endpoint validates the header/form token. Removed that query value from
   the three legacy templates; the existing `X-CSRF-Token` transport remains.
   Their obsolete IE-only HTTP Google Code shim was also retired. Bootstrap 5,
   TinyMCE 8 and the supported runtime already require modern browser APIs.
8. Easing 1.3 returned NaN under jQuery 3's single-progress-argument convention.
   Its unmodified upstream curves are wrapped to accept both modern calls and
   legacy time/start/change/duration arguments. It was not added to core loading.

The menu issue was reproduced with synthetic escaped text, not a real client
exploit. Upload failures were reproduced against synthetic loopback endpoints;
current modern administrator upload flows use different helpers. No claim of a
live client compromise or independent security certification is made.

## Verification and coverage

- Initial focused browser run: 9/27 passed, reproducing the named menu/upload
  failures. Final expanded browser suite: 38/38 passed.
- `node scripts/legacy-utilities-browser-qa.mjs`: desktop, iPhone and Android user
  agents in Chromium; nested keyboard navigation, selection URLs, repeated init,
  literal labels, zoomable viewport, isolated uploads, UTF-8 filename/CSRF,
  numbered APIs, HTTP/JSON failures, rebinding, foreign URL refusal, overlap,
  file-size refusal, rename/dynamic fields, once-per-batch completion and real
  jQuery easing animation. No client content, real upload or external request.
- `node scripts/legacy-utilities-self-test.mjs`: 168 checks, covering easing curves
  in both calling conventions, clone drift, source hashes and upstream prefixes.
  Included in `scripts/core-security-checks.sh`.
- Full disposable acceptance passes: 47 migrations, no-op rerun, auth,
  theme/add-on/content and upload lifecycles, forced transaction rollback, clean
  runtime log, exact temporary database/grant cleanup. Retained primary unchanged.
- Current tracked-source Gitleaks: zero findings; no new suppressions added.
- JavaScript/PHP syntax, core/theme contracts, Actionlint and whitespace checks pass.
- OSV: no advisory matches for the five real package coordinates now inventoried,
  including Superfish 1.7.10. No invented npm identities were assigned to local
  code or to the historical, pre-registry easing source. Those files have source
  review and regression evidence, not an asserted clean package-advisory result.

Browser UA emulation is not physical-device or Safari/WebKit verification. Client
HTML/integration slots were not queried. Core source inspection cannot rule out a
client loading an otherwise unused compatibility URL. Public fallback CSS/browser
and real hosting/transport behavior still require per-client deployment checks.
Externally loaded CDN assets (including pinned Popper) and separate client/add-on
packages are outside these seven bundled utilities; this review does not silently
extend its coverage to them.

## Rollback and later deployment

Revert the reviewed utility correction commit, or restore only the seven utility
files, associated three legacy templates, manifests/notices and tests from
`checkpoint/before-legacy-utility-review` after reviewing the diff. Do not reset
unrelated accepted work or delete untracked operator files. Restore the helper set
and callers together; rollback restores the reproduced defects.

Before a Scala demo, retain these versions/notices, verify cache refresh and run
public fallback navigation plus actual supported upload flows on that server. No
server change, GitHub push or release publication is part of this review. The
historical PayPal credential and other operational gates remain separate.
