# RED-CMS 5.1.2 — unpublished hardening candidate

Local SMTP follow-up: core PHP form mail now supports opt-in authenticated,
certificate-verified SMTP with a fixed sender and no local-mail fallback in SMTP
mode. Existing local-mail defaults and form responses remain compatible. The Scala
demo's authorized synthetic SMTP-to-Gmail delivery and live Contact browser
submission passed, including SPF/DKIM/DMARC; other installations remain unverified. See
[SMTP-DELIVERY.md](SMTP-DELIVERY.md).

Administrator account protection follow-up: a Webmaster could previously reset the
password, email or role of the Owner or a Superadmin through Administrator Users
and then sign in as that account. Now only the Owner can change or delete the Owner
account, and only a Superadmin or the Owner can change or delete a Superadmin.
Self-service changes and Guest management are unchanged. The disposable Owner
authorization self-test covers the refusals.

**Release blocked.** Historical PayPal PDT credential invalidity is unverified.
The owner reports the retired integration was disabled; this does not establish
token revocation. Core fixes, the isolated demo and operational evidence are
summarized in [the current closeout record](HARDENING-5.1.2-CLOSEOUT.md), including
the exact CI result and remaining installation-specific requirements.
TinyMCE 8.9.0 GPL core is verified locally and on the demo; the 8.9.3 patch
release, which fixes a media plugin stored XSS (GHSA-mf2p-h6hf-fcwm), replaces it
and is verified locally only. This is a reviewable
candidate, not a published release. Runtime version and the accepted
client-kit manifest remain 5.1.1 until the release gates are resolved.

The candidate reconciles v5.1.1 ancestry and accepted post-release commerce/security
work without rewriting history. It adds strict host-only session cookies,
fixation/CSRF renewal, 30-minute inactivity and 8-hour absolute expiration, complete
logout cleanup, and hash-only administrator login with an explicit pre-deployment
audit/conversion boundary. Existing
sessions must sign in again. Schema files, public URLs, add-on boundaries and editor
behavior are preserved.

Repository/archive exclusions and Apache download protection prevent local operator
state from being distributed accidentally. Current-source scans operate only on
tracked source; historical scans remain redacted and fail on the unresolved token.
CI adds offline core/theme contracts, disposable database/authentication/CRUD/rollback
acceptance and explicit public-coordinate dependency scanning. Stale phase hashes
were replaced by existing behavioral contracts; shell leak assertions now fail
explicitly when their sentinel is found.

The full local acceptance suite passed 47 migrations with zero pending/drifted,
no-op rerun, authentication and account invalidation, theme/add-on/content lifecycles,
uploads and forced rollback, with exact temporary database/grant cleanup. HTTP
session tests also pass. A synthetic Apache fixture also passed 14 denied-path checks and public/ACME
compatibility. Local CSP HTTP/browser checks pass, and the owner-authorized isolated Scala demo
now enforces the compatibility policy. The GitHub core job and current source/dependency checks now pass; historical
scanning remains blocked. Other client deployments require separate verification.

Review [`HARDENING-5.1.2.md`](HARDENING-5.1.2.md),
[`DEPLOYMENT-SECURITY-5.1.md`](DEPLOYMENT-SECURITY-5.1.md), and
[`BUNDLED-DEPENDENCIES.md`](BUNDLED-DEPENDENCIES.md). Restore selected paths from
`checkpoint/5.1.2-before-hardening`, or revert reviewed hardening commits; preserve
later work and never reset/delete untracked operator files.

TinyMCE upgrade detail: [TINYMCE-8-UPGRADE.md](TINYMCE-8-UPGRADE.md).

The [legacy utility review](LEGACY-UTILITY-PROVENANCE.md) also completes locally:
verified sources/licenses, repaired menu and upload compatibility, preserved URLs,
and new behavioral/provenance regression gates. Remaining maintenance/deployment
limits are documented explicitly. The later Scala demo deployment is recorded
separately; no HostGator/client deployment is claimed.


The owner-authorized [legacy PDT retirement](LEGACY-PDT-RETIREMENT.md) removes
its implementation and obsolete settings. The former callback returns only HTTP
410; newer PayPal adapter contracts and database compatibility remain intact.
Historical credential invalidity remains unverified; retirement is not revocation.
On 2026-11-08 the owner confirmed PDT is off on the account and accepted the
residual risk, closing the scanner finding with one exact-fingerprint ignore entry.

[Hash-only administrator authentication](HASH-ONLY-ADMIN-AUTH.md) removes the
plaintext fallback and its flag; local accounts already use hashes.

[CSP hardening](CSP-HARDENING.md) adds a server-controlled, opt-in compatibility
policy with report-only/enforcement modes, restricted origin extensions, generic
configuration failure and browser regression checks. All six TinyMCE modes pass
under enforcement. Inline JavaScript remains allowed; a stricter report can
identify that remaining gap. Client snippets and live host behavior still need
installation-specific verification before enabling enforcement.

The [Scala demo rehearsal](SCALA-DEMO-HARDENING-20260909.md) verifies authenticated
editor behavior and actual CSP blocking. A thumbnail-compatible `images/.htaccess`
now blocks executable uploads while preserving the shipped resize endpoint; local
Apache regression tests and real thumbnail/upload-denial checks pass.

Apache rules also deny provider-named `php.ini` files. Per-installation PHP settings
must use the effective `user_ini.filename` and be verified after cache refresh.

[GitHub CI execution](GITHUB-CI-HARDENING.md) identified an undeclared
offline Stripe test fixture. CI pins that separate public repository, uses a
maintained checkout action, and now enforces ShellCheck warnings as well as errors.
The historical secret scan remains strict and unresolved.
