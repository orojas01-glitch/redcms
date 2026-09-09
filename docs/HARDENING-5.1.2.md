# RED-CMS 5.1.2 local hardening candidate

Status: local hardening groups verified; release blocked. No release or deployment performed.

## Baseline and rollback

Starting branch: `codex/paypal-checkout-foundation`.
Starting commit: `cee2062fe0d5d2b292169ea076c0e03bdf1173ae`.
Tracked tree was clean. Untracked local deployment material and the protected
password/key workbook were not read, copied, moved or deleted.
Checkpoint: `checkpoint/5.1.2-before-hardening`.
Working branch: `codex/5.1.2-security-hardening`.

The v5.1.1 tag resolves to commit `9aa08eb6da881c5bd81778dceacca5bbc0fffef0`.
Its Other-content fix already existed as cherry-pick `7abf22c`; normal merge
`ff0f340` reconciles ancestry with no runtime change. Three documentation
conflicts retained newer accepted release history. The accepted commerce and
security branch was then merged without conflicts.
Reconciled baseline: `da95001c8e9a3721e4e7351105a3f0cd7211e206`.

Rollback: review `git diff checkpoint/5.1.2-before-hardening -- <path>` and
restore only approved hardening paths from that checkpoint if requested.
Prefer reverting individual hardening commits to preserve later accepted work.
Do not reset the worktree, erase untracked files or rewrite published history.
No database migration has been changed by this hardening work.

## Verification record

Initial public-form test reproduced a stale login source hash failure after
51 assertions. ShellCheck at error severity passes after accepted historical
repairs were merged. Existing source hashes are phase checkpoints, not a
substitute for behavioral authentication tests.

## Completed bounded groups

| Group | Verified issue and narrow correction | Evidence / remaining limit |
| --- | --- | --- |
| Lineage | Reconciled v5.1.1 ancestry and accepted commerce/security branches; no history rewrite | Tag and accepted branches are ancestors; Other behavior passes full suite |
| Boundaries | Local operator artifacts were unignored and tracked deployment notes entered archives; add ignore/export controls and Apache denial | Current-source Gitleaks clean; synthetic Apache 8 denied paths, public and ACME success; no live server claim |
| Sessions/authentication | Default cookies, no application expiry, incomplete logout, plaintext login despite failed hash upgrade | 12 session assertions, HTTP renewal/expiry/logout, full database auth and account invalidation pass; old sessions must reauthenticate |
| Acceptance | Stale phase hashes rejected accepted changes; negated grep assertions bypassed errexit | 62 public-form assertions; source errors pass ShellCheck; leak sentinels now explicit failure; remaining lower-severity legacy ShellCheck notices are not represented as all-clear |
| CI and operations | CI previously covered only one ingress proof | New core/theme, disposable migration/auth/CRUD/rollback, Apache/session and scan workflow; remote execution unverified |
| Policies | No root security policy or portable update record | Added security/support/reporting/patch policy, deployment contract, dependency inventory and private client-update template; mailbox/SLA owner decision remains |

## Executed acceptance

- `scripts/core-security-checks.sh`: all pre-database core checks and five
  dependency-free theme suites passed (theme contract 276 assertions; layout
  extensibility 67; compatibility 20; readiness 50; region context 54).
- `scripts/hardening-full-acceptance.sh`: PASS using fresh temporary current-schema
  baseline. All 47 migrations applied, zero pending/drifted; no-op rerun; existing
  auth/account invalidation, add-on/theme/content, uploads and forced rollback
  gates passed. Clean runtime log and exact temporary database/grant cleanup.
- `scripts/admin-session-self-test.php`: 12 assertions passed.
- `scripts/admin-session-http-self-test.py`: HTTP cookie, untrusted forwarding,
  session renewal, inactivity and logout checks passed; Secure tested through a
  synthetic direct-TLS SAPI projection, not a production TLS handshake.
- `scripts/apache-boundary-self-test.py`: Apache 2.4.67, 8 blocked synthetic
  paths, ordinary public file and ACME success, nosniff header present. Fixture
  served only synthetic files; neither workbook nor repository runtime was served.
- Actionlint: both workflows pass. ShellCheck error gate passes; 39 existing
  warnings (mostly CDPATH assignment style and shared helper exports), 68 info
  notices and 21 style notices remain outside this bounded correction.
- `scripts/security-scan.py`: zero current tracked-source findings using the
  accepted exact synthetic-fixture suppressions. Only tracked source is staged;
  local operator material is excluded before reading.
- Full-history Gitleaks: FAIL, one unresolved PDT finding. Secret fully redacted.
- OSV public-coordinate query: FAIL, 19 TinyMCE advisory matches; other three
  inventoried package versions returned no matches. Legacy utilities lack complete
  package provenance and are not covered by that result.

The retained primary database is behind current schema. Its initial direct
acceptance attempt failed before creating a disposable target; the fresh-baseline
wrapper resolves this without migrating the retained primary. A source assertion
and embedded-view header warning were corrected before the final passing run.
No client database, payment provider, credential or live deployment was changed.

## Initial release blockers and owner decisions (superseded for TinyMCE and legacy utilities below)

1. TinyMCE 4.0.12 remains installed and unsupported. Approve the distribution
   licensing route before a compatible supported replacement is integrated and
   browser-tested. This security upgrade belongs before 5.2, not to its redesign.
2. Historical PDT revocation/replacement or confirmed permanent invalidity needs
   owner evidence. Feature disablement alone is not rotation proof. No secret
   value, provider request, rotation or history rewrite occurred.
3. Run the committed workflow remotely and verify client-specific HTTPS/CSP,
   SMTP/inbox delivery, backups/restores and monitoring before deployment.
4. Dedicated private reporting mailbox/response commitment and the remaining
   legacy utility provenance inventory need owner/review completion.

Do not publish 5.1.2 or mark security checks clean while the first two gates fail.
The core version/client-kit remain 5.1.1 intentionally. Existing accepted
post-release add-on/payment work is preserved; no 5.2 editor redesign was started.


## 2026-09-09 TinyMCE follow-up

The owner selected free self-hosted core and confirmed RED-CMS is open source.
TinyMCE 8.9.0 replaces 4.0.12 locally, preserving existing MIT source notices and
adding GPL/source notices. The package integrity, six configurations, 84 real-editor
browser assertions and 28 existing Other HTML/reconciliation assertions pass.
The public-coordinate OSV query now returns no matches for all four inventoried
versions. The TinyMCE dependency/licensing-selection blocker above is resolved
for this local integration; the PayPal and remaining operational gates are unchanged.
See [TINYMCE-8-UPGRADE.md](TINYMCE-8-UPGRADE.md) for precise scope and limitations.
No Scala or HostGator state was changed. Source/license delivery remains part of
preparing the future client/demo distribution.


## 2026-09-09 legacy utility follow-up

The seven-file provenance review is complete locally. Unlicensed/unattributed
mobile-menu/theme glue was replaced, Superfish updated to pinned 1.7.10, upload
compatibility helpers repaired, and the preserved BSD easing source adapted for
jQuery 3. Tests reproduce the earlier failures and verify the corrected behavior.
See [LEGACY-UTILITY-PROVENANCE.md](LEGACY-UTILITY-PROVENANCE.md) for source/license
facts, 38 browser and 168 source/API checks, rollback and precise limitations.
The earlier pending legacy-utility provenance item is superseded by that report;
this does not close the PayPal, server/SMTP/backup/monitoring or remote CI gates.
