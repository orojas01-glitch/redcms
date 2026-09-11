# RED-CMS 5.1.2 candidate closeout — 2026-09-10

This is the current status record. Earlier dated sections in HARDENING-5.1.2.md
retain audit history and may describe issues that were subsequently fixed.
The candidate branch is `codex/5.1.2-security-hardening`; the completed SMTP and
operational closeout implementation is commit `96e0578f5e9cd6d806cf6975dc67e2bda3046322`.
No main merge, release
tag, published release or client deployment is authorized by this record.
Runtime version and client-kit manifest remain 5.1.1 until release approval.

## Release decision

**Not release-approved: historical credential incident remains unresolved.**
The retired PayPal PDT implementation and settings are removed, with the old URL
returning an inert 410. The owner reports an earlier revocation attempt found the
integration disabled. That is recorded as owner evidence of disablement, not
independent proof that the historical token is permanently invalid. The history
scan must continue to fail on that finding. No value was displayed, suppression
added, history rewritten or provider contacted during closeout.

To close that gate, obtain owner/provider evidence establishing invalidity of the
specific historical credential, record a sanitized incident disposition, and
review the appropriate narrow scanner handling. Feature removal or a clean
current-source scan alone does not resolve it. No replacement token is needed
for retired functionality. This report does not assert the token is active or
that any account was compromised.

## Completed work and evidence

| Surface | Correction and verified scope |
| --- | --- |
| Release lineage | Normal merges reconcile v5.1.1 ancestry and accepted commerce/security work. v5.1.1 remains an ancestor. No history rewrite. |
| Repository/deployment boundaries | Ignore/export controls, tracked-source-only redacted scanning, Apache denial of private/operator files, and thumbnail-compatible upload protection. Workbook excluded and never accessed. |
| Administrator security | Strict host-only cookies, HttpOnly/SameSite, direct HTTPS handling, fixation/CSRF renewal, inactivity/absolute expiry, logout and account invalidation. Hash-only login and explicit legacy-storage conversion/reset boundary. |
| Editor/dependencies | Self-hosted TinyMCE 8.9.0 GPL core, six configurations and preserved editor behavior; legacy utility provenance and compatibility fixes. No 5.2 redesign. Public dependency coordinates scanned separately from private installation data. |
| Headers/CSP | Server-owned compatibility policy, HTTP/browser regression checks and enforced Scala demo checks. Inline scripts/styles remain allowed; this is not a strict nonce policy. |
| CI/testing | Pinned offline fixture, portable Apache/header assertions, warning/error ShellCheck gate, core/theme/auth/migration/CRUD/rollback and browser checks. Informational/style suggestions remain, not warning/error failures. |
| Security reporting | Monitored contact selected and receipt owner-confirmed; accepted targets are 5 business days to acknowledge, 10 to assess and 14 calendar days between updates on confirmed open cases. |
| SMTP | Opt-in authenticated, certificate-verified transport with fixed sender and no local fallback in SMTP mode. 35 offline assertions; real demo authentication, synthetic send and real Contact browser submission reached Gmail with SPF/DKIM/DMARC passing. Original Contact settings restored exactly. |
| Recovery | Fresh demo files/database snapshot; private file restore and isolated 36-table database restore; 47 migration records; 35 unchanged table checksums; transactional write/readback/rollback and loopback application smoke. Encrypted NAS image reopened and enclosed artifact checksums passed. Temporary database grant revoked and test server stopped. |
| Integrations/monitoring | All nine demo global settings inventoried without exposing contents; Header/Footer/database CSS empty, zero installed add-ons, enforced CSP. Owner-approved five-minute Scala monitor with homepage/SSL checks and Gmail notifications; speed alerts off. |

Detailed evidence: [SMTP](SMTP-DELIVERY.md), [demo/CSP](SCALA-DEMO-HARDENING-20260909.md),
[recovery](SCALA-DEMO-RECOVERY-20260909.md), [operations](SCALA-DEMO-OPERATIONS-20260910.md),
[CI](GITHUB-CI-HARDENING.md), [dependency inventory](BUNDLED-DEPENDENCIES.md),
[security policy](../SECURITY.md) and [private update template](CLIENT-UPDATE-RECORD.md).

## Final candidate verification

Closeout repeats the SMTP/public-form regression tests, all tracked shell scripts
at warning severity, Actionlint, whitespace and redacted current-source scanning.
The unchanged SMTP runtime previously passed the full disposable acceptance suite
with 47 migrations, authentication, content/theme/add-on operations, uploads,
forced rollback, clean runtime log and exact temporary database/grant cleanup.
The complete GitHub workflow ran on that assembled revision:
[run 34558550868](https://github.com/orojas01-glitch/redcms/actions/runs/34558550868).

| Final executable-candidate gate | Result |
| --- | --- |
| Shell, offline core/adapter/theme, Apache, retired PDT, HTTP sessions/CSP, disposable authentication/migrations/CRUD/rollback, browser CSP/editor | Passed |
| Current tracked-source secret scan | Passed; zero findings locally and successful remote gate |
| Bundled and browser-tooling dependency scans | Passed |
| Historical secret scan | Failed; one existing finding, count independently checked from the redacted job log |
| Overall workflow | Failed solely at the historical-secret step; release remains blocked |

The later verification-record commit changes documentation only and triggers its
own workflow. See [current candidate runs](https://github.com/orojas01-glitch/redcms/actions/workflows/core-hardening.yml?query=branch%3Acodex%2F5.1.2-security-hardening)
for its exact SHA/result. No CI bypass, disabled gate or new scanner suppression
was used to produce this result.

A local review archive of `96e0578` contains 1,360 regular files and passed an
excluded-path inventory check: no local configuration, workbook, keys, logs or
deployment/operator folders. SHA-256:
`a2fe1c26372e08cb52571bd2f1a01aee24261a7b74958ed810d5bc8bbdc7114d`.
It is not a published release or a substitute for installation-specific packaging,
corresponding-source/license delivery, private configuration and restore planning.

## Outstanding operational requirements and limitations

These are separate from the historical-token release gate; they remain required
where applicable before claiming a client installation is operationally ready.

- A populated provider monitoring sample is verified; actual alert delivery remains
  untested. Website
  monitoring is not proof of disk, PHP/DB-error, backup-age or mail-delivery alerts.
- Select backup frequency, retention, acceptable data loss and recovery time.
  The verified encrypted NAS snapshot is one-time and predates SMTP configuration;
  it must be refreshed to include those later server-local settings. Any encrypted
  refresh requires the owner's private password entry. No recurring backup job
  or retention deletion policy has been silently introduced.
- Temporary downloaded plaintext source copies, the marked incomplete image,
  private server checkpoints and isolated restored data are retained. Cleanup
  must identify exact targets and preserve the verified encrypted backup and
  required rollback records. No protected workbook is a cleanup target.
- Successful owner login and visual editor save/reload on the restored copy,
  live Response/Register mail delivery, absent legacy/gallery integrations and
  other browser engines/devices were not all exercised on Scala. Do not generalize
  the documented local and demo evidence to every client or add-on.
- Scala's default CLI OPcache/lint crash was avoided with OPcache disabled only
  for the CLI invocation. Provider root-cause confirmation remains outstanding.
- The security reporting address needs ongoing owner coverage, including absences.
  Shared use of that mailbox for demo SMTP was explicitly owner-selected; its
  password remains server-local and was never added to this repository.

## Rollback and publication boundary

The exact pre-closeout worktree was preserved in a private temporary archive,
SHA-256 `7b4e4e9021a8f3acc15980a259947f6d8a4050a50d4669d2e831f0bf09906171`,
before editing status records. It contains only the 19 explicitly selected source
and documentation files, not local credentials or the workbook. Committed baseline:
`b61b7ffc0c5267da218cb0a53218d8f5301929aa`; SMTP checkpoint:
`checkpoint/before-smtp-hardening`. Original hardening baseline/checkpoint and
per-group rollback instructions remain in HARDENING-5.1.2.md.

For source rollback, review and revert only the relevant candidate commits while
preserving accepted later work. For demo SMTP rollback, use its private pre-change
endpoint/configuration copies after preserving newer edits. Do not reset database
passwords to plaintext, downgrade transport silently or restore a live database
without a separate approved recovery boundary.

Candidate branch publication is authorized; main merge and version/tag/release
publication remain separate owner decisions after the unresolved gate is closed.
RED-CMS 5.2 content/editor work remains a separate objective.
