# RED-CMS 5.1.2 — unpublished hardening candidate

**Release blocked.** TinyMCE 4.0.12 remains unsupported with known advisory
matches, and historical PayPal PDT credential revocation is unverified. This is
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
