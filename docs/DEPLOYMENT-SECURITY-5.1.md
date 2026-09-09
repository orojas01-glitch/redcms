# RED-CMS 5.1 deployment security requirements

This is a per-installation verification contract, not evidence of deployment.

## Web server

Use maintained PHP/MySQL releases and Apache 2.4 with rewrite, headers and
AllowOverride configured, or an explicitly reviewed equivalent server profile.
The repository `.htaccess` is an Apache control only: Caddy, nginx and PHP's
built-in server do not enforce it. Serve only reviewed release files, not a
workstation directory. Keep configuration, backups and operator tools outside
public reach. Disable directory indexes. Ship `images/.htaccess` to block executable uploads
while retaining the root `images/resize.php` thumbnail endpoint; verify both
allowed thumbnails and denied scripts in nested upload directories. Preserve the canonical routes and
`/.well-known/` certificate validation paths.

Configure HTTP-to-HTTPS redirect with a fixed canonical hostname in the server
virtual host, never a request-supplied Host or forwarded value. Set on TLS:

```apache
# Inside the approved HTTPS virtual host (not an HTTP development host).
Header always set Strict-Transport-Security "max-age=31536000"
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

Do not add includeSubDomains or preload until every affected hostname is verified.
For reverse proxies, normalize TLS and client address at the trusted server/SAPI
boundary; application code deliberately ignores X-Forwarded-Proto and
X-Forwarded-For. Verify PHP sees HTTPS=on. Do not expose the origin's HTTP listener.
Configure `display_errors=Off`, `log_errors=On`, `zend.exception_ignore_args=On`,
a private log destination, and a private per-client session storage directory.
Verify the active `user_ini.filename` rather than assuming `.user.ini`; the Scala
demo uses `php.ini`. The core Apache rules deny that filename at every depth.
Keep the file owner-readable only, allow for PHP-FPM settings-cache refresh, and
verify the effective web runtime. Changing session storage requires fresh login.
No shared cookie domain or shared session save path across clients is permitted.

## Administrator sessions and passwords

Cookies are host-only, HttpOnly, SameSite=Lax, session-lifetime and Secure on
server-confirmed HTTPS. Local HTTP development keeps Secure false. Strict mode
rejects unissued identifiers and URL-based session IDs are disabled. Login renews
the session ID and CSRF token. Server-side expiry is 30 minutes inactivity and
8 hours absolute; existing sessions without timestamps must sign in again.
Requests using the shared session count as activity. Logging out clears all
session state, expires the cookie and destroys server storage. The legacy logout
URL is retained; cross-site forced logout remains a low-consequence compatibility
risk. Password change and account deletion invalidate protected requests; current
permissions reload from the database. Expiry can interrupt unsaved editing.

Apply `2026-07-02-red-admin-password-hash.sql` before enabling this patch.
Plaintext login is removed, and `RED_ADMIN_ALLOW_LEGACY_PASSWORDS` is ignored.
Run the counts-only audit in `scripts/admin-password-migration.php`, complete any
explicit offline conversion or owner reset before deployment, and verify a recovery
manager. The tool refuses ambiguous values and unsafe bcrypt lengths and never
prints usernames, passwords or hashes. See [HASH-ONLY-ADMIN-AUTH.md](HASH-ONLY-ADMIN-AUTH.md).
Do not revert storage to plaintext or reintroduce a compatibility flag.

## CSP and embedding

The candidate now provides server-controlled application CSP in the shared PHP
bootstrap. See [CSP-HARDENING.md](CSP-HARDENING.md) for exact settings, compatibility
limits, tests and rollback. It defaults off for unreviewed installations; start
with `CSP_MODE=report-only` on the isolated demo and enable enforcement only after
reviewing that installation's integrations. PHP owns this policy; do not duplicate
it in Apache/proxy headers. Local HTTP/browser enforcement checks and the isolated Scala demo rehearsal
pass within the scope in [SCALA-DEMO-HARDENING-20260909.md](SCALA-DEMO-HARDENING-20260909.md).
Other client/production installations still require their own verification.

The current editor, themes and database HTML use inline scripts/styles and may
embed external media. A universal strict CSP would break accepted behavior.
Start with the application **report-only** policy in private staging; enumerate
actual script/style/image/font/frame/connect origins for public and administrator
routes separately. Keep reports private and scrub URLs and payload samples.
Do not add a report collector or third-party telemetry without approval.

The compatibility profile retains inline scripts and styles, blocks objects and
restricts base URLs, framing and resource origins. It does not prevent inline XSS.
The optional strict report identifies inline-script usage without blocking it.
Before enforcement verify all editor modes,
uploads, video embeds, global integration snippets and optional add-ons. Protected
administrator endpoints set no-store, nosniff and SAMEORIGIN when headers can still
be emitted; set these at the server for complete embedded-view coverage.

## Operational evidence required per client

- Mail: current forms use PHPMailer/local mail defaults and fallback delivery;
  authenticated SMTP is not configured by this patch. Record TLS/auth configuration,
  fixed approved sender, SPF/DKIM/DMARC checks, a synthetic provider receipt and
  independent inbox arrival. A successful `mail()` return proves only local handoff.
- Backups: use the existing guarded backup/restore tools, encrypted private storage,
  defined retention, and a timed restore into an isolated empty database. Record
  checksum and restore result. Never package a dump with the web release.
- Monitoring: assign an owner for HTTPS/certificate expiry, HTTP failures, PHP/DB
  errors, disk capacity, backup age and synthetic delivery failures. Never log
  request bodies, session IDs, credentials or payment payloads.
- Global integration slots: database-managed head/body HTML is executable trusted
  administrator content, not a secret store or CSP bypass exemption. Inventory
  each installed client's slots, purpose, owner, third-party hosts and rollback
  reference privately. No client slot was queried or changed in this task.

Live headers, browser CSP, SMTP, restore objectives and monitoring remain
unverified until each client records evidence.
