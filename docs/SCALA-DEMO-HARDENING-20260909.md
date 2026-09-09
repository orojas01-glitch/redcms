# Scala demo hardening rehearsal — 2026-09-09

Target: `demo.red-sphere.com`, explicitly selected by the owner as a new isolated
Scala demo. This record does not authorize or describe a client deployment,
GitHub publication, payment action, DNS change or independent certification.

## Installed scope

Core candidate revision `12dad91`; runtime version remains 5.1.1 until release.
Credential-free source archive SHA256:
`2aab5d86e29bf6d9fa9cafd5a62e359596e3e67a65f76d26f8848da5b64ddf9b`.
The same checksum was verified after upload. No separately distributed add-ons
were installed or enabled. The password/key workbook was not accessed.

The new account initially contained only its provider PHP handler and ACME
folder. The owner created a localhost-only database user scoped to the fresh
demo database and entered its password directly in a hidden server terminal
prompt. Configuration values were never displayed. The server confirmed the
database was empty before importing the generic clean starter.

All 47 migrations completed on MariaDB 11.8.9. Status: 47 applied, zero pending,
zero drift. Re-running made no changes. The original handler, original webroot,
and migrated generic starter database have private rollback copies. The webroot
was populated without changing its provider-owned group or existing permissions;
operator scripts, migration SQL, release/deployment material and documentation
were excluded. The subsequently reviewed `images/.htaccess` correction was
applied as a separate tracked patch and is described below. The private PHP configuration has mode 0600.

## Hosting observations and corrections

- Website PHP handler: PHP 8.3. CLI default was 8.1.34; setup explicitly uses
  `/usr/bin/php83` (8.3.33), with mysqli, mbstring, DOM, cURL and fileinfo present.
- Default PHP CLI lint crashed with OPcache enabled. Disabling OPcache for the
  individual CLI invocation allowed lint; ordinary security tests executed.
  Web/server-wide PHP settings were not changed. Provider runtime diagnosis
  remains outstanding; this is not represented as a PHP source syntax failure.
- A replacement directory could not acquire the provider's web-server group.
  No group/permission protection was relaxed: activation retained the original
  public directory and copied the prepared entry point last.
- ModSecurity rejects Python-urllib's user-agent with 403. Chrome and curl serve
  the site successfully. The WAF was left unchanged. Python's 403 responses were
  not evidence of a broken PHP installation or failed file permissions.
- Installation-local `.htaccess` preserves the PHP handler, adds a fixed demo
  HTTP-to-HTTPS redirect (302 during rehearsal), no-index headers, and HSTS on
  verified HTTPS. Core routing/download-denial rules remain. Uploaded PHP-like
  files under `images` are denied. Robots disallows crawling this demo.

## Verified live evidence

- Chrome renders the generic public homepage and `/administracion/` login page.
  No public-page console warnings/errors were observed in the checked state.
  The starter homepage has no placed content; this was not a content redesign.
- HTTPS certificate verification succeeds; homepage returns 200. HTTP redirects
  to the fixed HTTPS demo hostname.
- Actual cookies include Secure, HttpOnly and SameSite=Lax. Session values were
  redacted from the local header record and were not put in this document.
- Responses include nosniff, strict-origin-when-cross-origin, demo no-index,
  and the application enforced CSP. HTTPS HSTS is max-age=31536000 without
  includeSubDomains. The host also emits its own HSTS on HTTP; browsers do not
  apply HSTS learned from HTTP. No server-wide policy was changed.
- Harmless synthetic probes inside `includes`, `scripts`, and image uploads,
  plus `.git/config`, returned 403. The synthetic ACME path returned 200.
  No request was made for the workbook or credential-bearing configuration. Synthetic
  files were then moved out of the webroot into private rehearsal evidence.
- Direct Scala PHP checks passed: CSP 27, password 19, session 12, theme 276.

## Authenticated CSP rehearsal completed

The owner created the administrator password and signed in directly; the agent
never entered or retrieved it. Guest credentials and add-on roles were unchanged.
The application compatibility CSP is now **enforced** on this demo. A private
copy of the previous configuration was preserved before switching modes.

- Article: both TinyMCE fields initialized. A temporary rich-text marker saved,
  persisted after reopening, and was restored from the original source.
- Enforced Article upload: a known core PNG uploaded successfully, synchronized
  its filename, and persisted through Save/reopen. The original empty image
  setting was then restored. The small fixture file remains available to the
  generated revision history; no permanent deletion or history erasure occurred.
- Video: the TinyMCE description field and real YouTube preview loaded under
  enforcement. No payment or messaging integration was used.
- Other: visual and HTML modes synchronized a harmless markup probe, including
  bold formatting, under report-only and enforced policies. The probe was cleared
  and never saved as a new content record.
- Form: the administrator sign-in form editor rendered its three-field contract.
  No public contact form or email was submitted.
- Advanced: the shared-header HTML editor loaded under both policies; its content
  was not changed.
- Desktop and 390 px phone emulation: both Article editor iframes fit; measured
  document width equaled viewport width. Video and Other also loaded at 390 px.
  The browser-level viewport control initially had no effect; tab-scoped emulation
  was measured to verify the actual width. All viewport overrides were cleared.
- The actual signed-in document response was 200 with enforced CSP, no report-only
  header, and `Cache-Control: no-store`. Response inspection emitted only those
  selected fields, never session values.
- A temporary, input-free PHP probe used the real application policy and a script
  from a reserved invalid test hostname. Chrome displayed
  `BLOCKED: script-src-elem (enforce)`. The probe was moved out of the webroot.
- No unexpected CSP/error messages were observed in the tested editor states.
  A server-side boolean check confirmed the temporary Article marker was absent,
  its original empty image field was restored, and CSP mode remained enforced.

### Thumbnail compatibility correction

The first installation-local image rule denied every PHP file beneath `images`,
including the shipped thumbnail endpoint. A direct request reproduced its 403;
this was a deployment-rule regression, not a TinyMCE or CSP defect. It was fixed
before completing this rehearsal.

Tracked `images/.htaccess` now permits only the root-relative `resize.php` endpoint
and rejects PHP-like/CGI paths elsewhere, including double extensions and a nested
file also named `resize.php`. It does not grant a filename exception across upload
subdirectories. Synthetic local Apache tests pass 14 denied paths plus successful
public, ACME, ordinary-image and thumbnail paths. Real Scala checks confirm the
thumbnail returns 200 `image/png`, the ordinary PNG returns 200, and an uploaded
`images/articles/resize.php` sentinel returns 403. That sentinel was moved back
into private evidence. The previous image rule has a private backup.

The core PHP, editor code and database migrations were not changed by this
correction. Existing full acceptance results remain applicable to those sources;
the relevant changed server behavior was checked with Apache and on Scala.

### PHP operational settings

The deployed runtime confirmed direct HTTPS, error display disabled, error logging
enabled, exception arguments ignored and GD available. Its initial session folder
was writable but did not meet the private-per-account directory requirement.
The runtime reports `user_ini.filename=php.ini`; a standard `.user.ini` is
ignored on this host. Installation-local `php.ini` selects dedicated mode-0700
session and log storage outside the webroot and retains those error-safety
settings. It contains no credentials, is owner-readable only, and is protected
by an explicit `php.ini` denial added to the core Apache rules and regression
tests before the server file was installed.
Changing session storage requires a fresh sign-in; old sessions were not copied
or read. After PHP-FPM refreshed its configuration, the HTTP probe confirmed the exact
private session directory was selected, mode bits excluded other users, ownership
matched the application, the directory was writable, and the private error-log
path was selected. Error display remained off and exception arguments hidden.
A status-only check confirmed the credential-free `php.ini` returned 403. The
value-free runtime probe was then moved out of the webroot.

## Remaining limits

The enforced profile still allows legacy inline scripts/styles and trusts the
listed script origins as whole origins. It does not prevent inline-script XSS.
Gallery configurations not offered by this starter's current content controls,
legacy theme activation, Facebook secondary requests, arbitrary client integration
slots, physical devices and other browser engines were not exercised live.
Local six-configuration editor tests remain separate evidence, not substitutes
for these installation-specific checks.

SMTP delivery, full restore rehearsal, monitoring and per-client operational
proof remain separate requirements. The host's default CLI OPcache/lint crash,
historical PDT invalidity and remote CI remain unresolved. A working demo is not
permission to publish the release or deploy to clients.

## Rollback and private handoff

Current-source secret scanning passed with zero findings; changed Apache rules
passed the 14-path synthetic gate and real positive/negative endpoint checks.
Remote CI has not been run because nothing was pushed.

The private operational handoff records exact account-local staging/backup paths,
checksums, configuration status and outstanding owner input. Keep those paths,
configuration and database copies outside repository reports and the webroot.
Rehearsal evidence on the workstation is under `/private/tmp/redcms-demo-12dad91`
and is temporary, not a durable backup service.

To roll back the web activation, first preserve any subsequent demo edits, then
restore the original webroot archive and provider handler using the privately
recorded paths. Retain the database instead of deleting it; restoring a database
snapshot requires checking for later writes. Do not reset Git, restore plaintext
passwords, remove ACME files, or reuse another installation's configuration.
