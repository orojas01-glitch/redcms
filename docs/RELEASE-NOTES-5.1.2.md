# RED-CMS 5.1.2 — unpublished hardening candidate

**Release blocked.** Historical PayPal PDT credential revocation is unverified;
client-specific operational and remote release checks remain pending. TinyMCE has
now been upgraded locally to 8.9.0 GPL core with passing browser and dependency checks. This is
reviewable local work, not a published release. Runtime version and the accepted
client-kit manifest remain 5.1.1 until the release gates are resolved.

The candidate reconciles v5.1.1 ancestry and accepted post-release commerce/security
work without rewriting history. It adds strict host-only session cookies,
fixation/CSRF renewal, 30-minute inactivity and 8-hour absolute expiration, complete
logout cleanup, and mandatory successful hash migration for legacy login. Existing
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
session tests also pass. A synthetic Apache fixture also passed eight denied-path checks and public/ACME
compatibility. Remote CI, browser CSP and client deployment remain unverified.

Review [`HARDENING-5.1.2.md`](HARDENING-5.1.2.md),
[`DEPLOYMENT-SECURITY-5.1.md`](DEPLOYMENT-SECURITY-5.1.md), and
[`BUNDLED-DEPENDENCIES.md`](BUNDLED-DEPENDENCIES.md). Restore selected paths from
`checkpoint/5.1.2-before-hardening`, or revert reviewed hardening commits; preserve
later work and never reset/delete untracked operator files.

TinyMCE upgrade detail: [TINYMCE-8-UPGRADE.md](TINYMCE-8-UPGRADE.md).

The [legacy utility review](LEGACY-UTILITY-PROVENANCE.md) also completes locally:
verified sources/licenses, repaired menu and upload compatibility, preserved URLs,
and new behavioral/provenance regression gates. Remaining maintenance/deployment
limits are documented explicitly; no Scala or HostGator change occurred.


The owner-authorized [legacy PDT retirement](LEGACY-PDT-RETIREMENT.md) removes
its implementation and obsolete settings. The former callback returns only HTTP
410; newer PayPal adapter contracts and database compatibility remain intact.
Historical credential invalidity remains unverified; retirement is not revocation.
