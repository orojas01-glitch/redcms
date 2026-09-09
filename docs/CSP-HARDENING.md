# RED-CMS 5.1.2 CSP hardening

Status: implemented locally and enforced on the owner-authorized isolated Scala
demo. See [SCALA-DEMO-HARDENING-20260909.md](SCALA-DEMO-HARDENING-20260909.md)
for actual server/browser evidence and remaining limits.
This is a compatibility policy, not a strict nonce-based XSS policy or a security
certification. The initial local implementation changed no server state. The later demo
rehearsal changed only the selected demo installation; no client database, DNS
or payment-provider setting was changed. The protected workbook was not accessed.

## Baseline and rollback

Clean tracked baseline: `5e2c9ca3a405fefb465a7900febd962976392116` on
`codex/5.1.2-security-hardening`. Checkpoint: `checkpoint/before-csp-hardening`.
No migrations, editor redesign, vendor bytes or add-on contracts changed.

Review and revert the CSP commit to restore this baseline while preserving later
work. Do not reset the worktree or restore private files from Git. For a future
installation rehearsal, save its exact previous non-secret CSP settings privately;
restore those settings if the rehearsal breaks accepted behavior. `CSP_MODE=off`
disables only this application policy; it cannot remove a policy set at a proxy or
server. Disabling a deployed protection is an owner-reviewed rollback, not an
automatic browser action. No rollback of hashed passwords or schema is needed.

## Verified problem and implementation

There was no application CSP. Shared `includes/bootstrap.php` now calls
`red_csp_emit()` from `includes/csp_helpers.php` before output. It covers ordinary
public/theme responses and bootstrap-backed administrator requests, including
administration embedded inside public pages. It does not select a policy from
request URLs, cookies, permissions or user-controlled headers. Early add-on routes
that exit before bootstrap, static HTML, server errors and non-bootstrap legacy
entry points are not automatically covered. Review them at the server separately.
A header added to an AJAX fragment cannot tighten its already-loaded parent page.

Only OS environment or the ignored local configuration controls mode and extra
origins. Default is **off** to preserve unreviewed existing installations. Neither
private local configuration nor the retained database was changed in this task.
The mode is selected at the installation boundary after its integrations are
reviewed; there is no administrator UI switch or database-controlled bypass.

| Setting in config.local.php | Process environment | Values |
| --- | --- | --- |
| `CSP_MODE` | `RED_CSP_MODE` | `off`, `report-only`, `enforce` |
| `CSP_SOURCES` | `RED_CSP_SOURCES` | PHP directive-to-origin arrays; JSON object in environment |
| `CSP_STRICT_REPORT` | `RED_CSP_STRICT_REPORT` | PHP boolean; `0` or `1` in environment |

Environment takes precedence. For the isolated demo, begin with:

```php
'CSP_MODE' => 'report-only',
'CSP_SOURCES' => [],
'CSP_STRICT_REPORT' => false,
```

Review violations before setting `CSP_MODE` to `enforce`. Exact additional origins
can be assigned per directive, for example a deliberately synthetic integration:

```php
'CSP_SOURCES' => ['connect-src' => ['https://api.example.test']],
```

Do not copy that example hostname into a real configuration. Allowed extensions
are script/style/image/font/connect/media/frame/form destinations. Inputs cannot
replace object/base/ancestor protections, add reporting destinations, wildcard
origins, HTTP origins, unsafe keywords, userinfo, paths, queries or new headers.
An enabled but invalid configuration fails with generic HTTP 503 and a value-free
log message instead of silently dropping protection. `off` ignores dormant source
settings. CLI imports do not emit HTTP headers.

## Resource inventory and policy limitations

The inventory inspected tracked runtime PHP/JS/CSS/HTML only, excluding deployment
material, private configuration, vendors, operator scripts and database contents.
A heuristic found 42 files with inline-script markup and 59 files with event
attribute candidates; these include source templates and are not rendered counts.
They establish why blindly requiring nonces would break accepted behavior.

| Surface | Verified source / policy treatment |
| --- | --- |
| Core/admin/theme assets and add-on asset endpoint | Same origin permitted by default |
| TinyMCE 8.9.0, all six configurations | Same origin; inline styles, data fonts/images and blob images/media retained; 84 browser assertions pass with enforcement |
| Legacy theme Popper | `themes/legacy-bootstrap/document.php` uses pinned jsDelivr URL and SRI; exact CDN origin permitted for scripts |
| Legacy theme Facebook SDK | Same document loads `connect.facebook.net`; initial script origin permitted. Actual SDK secondary requests and widgets remain unverified; review their precise destinations on staging |
| YouTube/Vimeo video forms/gallery | `includes/video_url_helpers.php` and video UI use www.youtube.com, www.youtube-nocookie.com and player.vimeo.com; those frame origins permitted |
| Local forms/uploads/AJAX | Same-origin form and connection destinations permitted; cross-origin form posts require explicit installation review |
| Global head/body HTML, custom themes, add-ons, remote images/fonts | Contents and runtime destinations vary by installation; no client slots were read. Review privately before enforcement |

The profile blocks plugin objects, foreign base URLs, cross-origin framing of the
page, unapproved resource hosts, unapproved form destinations, and string eval.
It intentionally still permits inline JavaScript/event handlers and inline CSS.
**It does not prevent inline-script XSS.** Allowed script origins are trusted as
whole origins, so the shared CDN allowance is broader than the one SRI-protected
script. No `unsafe-eval`, wildcard script host, blanket HTTPS script allowance,
nonce rewriting of arbitrary HTML or automatic permission expansion was added.

Set `CSP_STRICT_REPORT=true` alongside enforcement to receive a second,
report-only policy that omits the inline-script allowance. Styles retain their
compatibility allowance. These observations identify future source changes; they
are not an enforced strict policy. No report-uri/report-to collector, payload
samples or third-party telemetry is configured. Developer Tools and the synthetic
browser harness observe violations locally. Do not export raw browser logs from
client pages: URLs can contain private identifiers.

## Server and Scala rehearsal

PHP owns the application policy. Do not paste an additional CSP into `.htaccess`
without reviewing policy composition: multiple enforced policies intersect; a
second permissive header does not override a restrictive one. Report-only and
enforced headers can coexist. See the [W3C CSP specification](https://www.w3.org/TR/CSP3/)
and [MDN header documentation](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy).

The Apache requirements remain in [DEPLOYMENT-SECURITY-5.1.md](DEPLOYMENT-SECURITY-5.1.md).
SPanel alone does not prove which web server handles a domain or whether it honors
`.htaccess`. Before changing the named demo, verify its exact domain, document
root, isolated database, current revision, recoverable backup and PHP/server
configuration. The open panel context in this task did not identify a RED-CMS demo
file manager. That was the initial local preparation state; the later owner-authorized demo
deployment and its verified edits are recorded in the linked rehearsal report.

After separately authorized deployment to the isolated demo:

1. Start report-only; verify the actual GET response headers in Developer Tools
   on public pages, login, authenticated editing, and denied endpoints. Verify
   redirects/error/static surfaces separately. Confirm there is no competing
   host/proxy CSP and no accidental output-before-header gap.
2. Test desktop/mobile login/logout, Article/Other exact and visual editing,
   Gallery/Video/Advanced, save/reload, uploads, menus, public forms, selected
   themes, installed add-ons, embeds and private global integration inventory.
   Use synthetic content and no payment/provider actions.
3. Review each violation's purpose/owner and exact origin; do not simply allow
   every reported host. Test real Facebook/video integrations on that isolated
   domain without treating their synthetic origin allowance as delivery proof.
4. Enable enforcement, repeat the same flows and a harmless blocked-resource
   probe. Record revision, effective headers, tested browser/viewport, results,
   outstanding exceptions and rollback reference privately.
5. Verify real HTTPS redirect/canonical host, TLS, Secure/HttpOnly/SameSite cookies,
   HSTS on verified TLS only, nosniff/referrer headers, protected file boundaries
   using synthetic sentinels (never request the password workbook), upload
   execution protection and no-cache administrator behavior.

Production/client deployment remains a separate approved action. The Scala demo
cannot establish every client's integration compatibility. SMTP delivery,
backups/restores and monitoring retain their separate evidence requirements.

## Verification

- `php scripts/csp-self-test.php`: 27 policy/configuration assertions passed.
- `python3 scripts/csp-http-self-test.py`: 8 actual PHP HTTP cases passed,
  including public/denied-admin coverage, off/report/enforce/both, forged settings,
  malformed settings, generic failure and no unsolicited session creation.
- `node scripts/csp-browser-qa.mjs`: 15 Chromium assertions passed on two synthetic
  loopback origins. Actual page scripts demonstrate blocking of foreign scripts,
  fetch, forms, objects, base URL, eval and framing; report-only allows execution
  while exposing violations, including inline scripts. No external traffic.
- `node scripts/tinymce-browser-qa.mjs`: 84 existing editor assertions passed at
  1440 and 390 px with the generated compatibility CSP enforced. Test setup uses
  browser automation to initialize captured configurations; actual vendor/plugin
  loading and editor behavior occur under CSP. It is not a full client page test.
- `scripts/core-security-checks.sh`: core/theme, vendor/provenance and CSP pass.
- Current tracked-source secret scan: zero findings. Actionlint, PHP syntax,
  changed-shell error checks and staged whitespace checks pass. OSV found no
  matches for the three locked browser-tooling packages; this is separate from
  the still-unresolved historical credential finding.
- `scripts/hardening-full-acceptance.sh`: all 47 migrations and existing
  authentication, CRUD, theme/add-on, upload and rollback checks passed; temporary
  DB/grant cleanup confirmed. This suite uses the default off mode; the separate
  HTTP/browser suites exercise enforcement. The retained primary is unchanged.

CI runs policy/HTTP checks and both browser suites using the locked Playwright
1.62.1 test-only dependency. Lockfile integrity comes from the public npm registry;
installation disables lifecycle scripts. CI separately scans those public tooling
coordinates. Remote CI has not run because nothing was pushed. Local evidence:
`/private/tmp/redcms-csp-acceptance.log`, `/private/tmp/redcms-csp-core.log` and
`/private/tmp/redcms-tinymce-browser-qa` (temporary, not durable release evidence).
No live server, actual Safari/Firefox, real third-party integration or client
content compatibility is claimed by these results.
