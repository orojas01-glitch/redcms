# Legacy PayPal PDT retirement

Date: 2026-09-09. Owner instruction: the old integration was used long ago and
can be removed completely. Scope: local RED-CMS current implementation and its
obsolete settings. No provider action, server deployment or history rewrite.

## Removed and retired

- Deleted `includes/public_paypal_helpers.php`: PDT parsing and confirmation-mail
  implementation is no longer bundled.
- Replaced `bin/paypal_response.php` with a terminal **410 Gone** response. Keeping
  this inert retirement marker ensures old links and a later file-copy deployment
  cannot accidentally fall through to the CMS or retain an executable callback.
  It neither reads request/configuration data nor loads sessions, mail, databases,
  credentials or provider code. GET, POST, HEAD, OPTIONS and PUT are covered.
- Removed `PAYPAL_PDT_HOSTNAME`, `PAYPAL_PDT_AUTH_TOKEN`,
  `PAYPAL_CONFIRMATION_FROM_EMAIL`, and `PAYPAL_CONFIRMATION_FROM_NAME` from the
  configuration example and private local configuration. A token-based edit removed
  only those four entries, preserved all other bytes and checked PHP syntax without
  displaying private values.
- Updated the clean-starter gate and CI to enforce retirement. The newer PayPal
  profile/registrar/ingress and all database migration files remain unchanged.

## Rollback

Tracked baseline: `eb1b454`; checkpoint:
`checkpoint/before-legacy-pdt-retirement`. Revert this retirement commit only after
reviewing its full scope. Restoring old code would reinstate an obsolete payment
integration and is not the recommended recovery path.

The private local configuration has a separate rollback copy at
`/private/tmp/redcms-pdt-config-rollback-m3p9dmod/config.local.php`, inside an
owner-only directory with a mode-0600 file. It may contain private configuration
values and must never be committed, served or shared. It exists solely to recover
unrelated configuration if needed; do not restore obsolete PDT settings by default.
The temporary location is not durable backup storage. The password/key workbook
was not opened, moved or modified.

## Verification

- Clean-starter boundary: 22 assertions pass with the helper absent and settings
  removed; the callback contains no bootstrap or transport path.
- `scripts/legacy-pdt-retirement-self-test.py`: five real loopback HTTP methods
  return 410, fixed body (empty for HEAD), no-store/nosniff, no cookie and no
  redirect. Synthetic legacy settings cannot reactivate it. Mail, cURL and MySQL
  functions are disabled in the isolated fixture; no real provider is contacted.
- Existing modern PayPal profile (27), registrar (10), and ingress (13) assertions
  pass without handler invocation, credential access, database or provider effects.
- Full disposable acceptance: PASS. All 47 migrations, no-op rerun, auth,
  theme/add-on/content lifecycles, uploads and forced rollback passed. The runtime
  log was clean and both temporary databases/grants were cleaned up.
- The current tool environment has no obsolete PDT variables. The private local
  configuration has zero remaining references to the four removed keys.

Current tracked-source secret scanning is clean; Actionlint and whitespace checks
also pass. Historical scanning intentionally still reports the old finding.

## What local removal does not establish

The old credential remains in historical Git commits and potentially older backups
or deployed configurations. The scanner finding is deliberately not suppressed.
Owner authorization to retire code is not proof of PayPal-side invalidation. The
credential incident remains open until invalidity/revocation is confirmed; this
retired application does not need a replacement token.

Owner clarification on 2026-09-09: a previous revocation attempt found the legacy
integration disabled. This is recorded as an owner report, not independent
confirmation that the historical token was revoked or permanently invalidated.
No additional provider action was performed and the scanner finding remains open.

No PayPal account, Scala, HostGator, client database or remote repository was
changed. A later authorized deployment must replace the old callback, remove its
helper, and remove obsolete settings from that specific server. Do not delete
historic database tables or purchase records as part of this file-only retirement.
