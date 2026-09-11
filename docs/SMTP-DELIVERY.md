# Core authenticated SMTP delivery

Status: reviewed runtime deployed to the isolated Scala demo. Owner-entered
mailbox setup passed TLS/authentication; one authorized synthetic message was
accepted by SMTP and independently verified in the recipient's Gmail inbox.
The security-reporting mailbox receipt is unrelated evidence.

## Contract and compatibility

Core Contact, Response, Register and the legacy PHP MailHandler use the shared
server-owned transport settings. `MAIL_TRANSPORT=mail` is the compatibility default.
`smtp` requires a host, authenticated user/password, fixed sender, and either
STARTTLS on port 587 or implicit TLS on 465. Certificate and hostname verification
are required; self-signed certificates are refused. No new dependency is bundled.
SMTP settings use only operating-system environment or private config.local.php,
never request-projected variables or database-managed content slots.

SMTP mode does not fall back to PHP mail(), including after bad configuration,
authentication failure or an uncertain delivery result. There is no automatic
retry: a failure after DATA may be ambiguous, so check the server receipt/inbox
before repeating. SMTP debug output is disabled; error logs use fixed messages.
Connection timeout is 15 seconds and SMTP response time limit 30 seconds (DATA
may use the bundled library's longer limit); this is not an overall request SLA.

The fixed sender controls From and the envelope sender. A different validated
managed-form From remains a reply address. Existing recipients, CC/BCC, message
bodies, subjects, form validation, registration storage and public response formats
remain intact. Response forms still display their configured response even if
delivery fails; that legacy behavior is not evidence of successful email.
Add-ons and the legacy ASP.NET MailHandler are separate integrations.

## Configuration and owner entry

Use the keys in includes/config.local.example.php, or prefix each with `RED_` in
the process environment: MAIL_TRANSPORT, SMTP_HOST, SMTP_PORT, SMTP_SECURITY,
SMTP_USERNAME, SMTP_PASSWORD, SMTP_FROM_EMAIL, SMTP_FROM_NAME. Enter secrets
privately on the selected server. Never commit or paste passwords into reports.
Prefer a mailbox scoped to the demo; confirm its provider hostname and sender
rights. For this demo, the owner explicitly selected the existing
`security@red-sphere.com` mailbox instead of creating another address. SPanel's
mailbox connection instructions specify `mail.red-sphere.com`, port 465 and
authentication. No mailbox password was retrieved by the assistant.

## Delivery proof

1. Preserve the installation's code and private configuration checkpoint. Deploy
   the reviewed helper and endpoint changes only to the authorized demo.
2. Owner enters its SMTP settings privately. Run the CLI-only proof script with
   `--to=OWNER_APPROVED_ADDRESS` to validate the configuration without connecting.
3. Obtain explicit authorization for one synthetic message to the exact recipient.
   Then run the same script with `--send --confirm-to=OWNER_APPROVED_ADDRESS`.
   Do not expose the script in the document root.
4. Record only the synthetic reference and sanitized SMTP receipt. Successful
   server acceptance is separate from owner-confirmed inbox arrival. Inspect
   SPF/DKIM/DMARC results privately without exporting full headers or inbox data.
5. A direct CLI message proves the shared transport. Separately authorize and
   exercise a synthetic public-form submission to prove that endpoint end-to-end.
   Do not use retained client records or send test messages to their recipients.

## Verification, rollback and limits

The offline test exercises real PHPMailer configuration, request-projection
rejection, TLS/auth/fixed-sender rules, malformed configuration, and deterministic
SMTP failure without opening a socket. It runs in the pre-database acceptance
gate and therefore in core CI. Existing public-form contract checks also apply.

Local results: 35 focused transport assertions and 62 public-form contract
assertions passed. Core security/theme/dependency-file integrity checks passed.
The full disposable acceptance suite passed all 47 migrations, authentication,
Form/Article/Gallery CRUD, uploads and forced rollback, with clean runtime log
and cleanup of both temporary databases/grants. CLI proof dry-run with synthetic
settings passed without connecting; a send without exact recipient confirmation
was refused (exit 64). PHP syntax, ShellCheck warning gate and whitespace checks
passed. Current tracked-source secret scanning, including the new helper and
scripts, reported zero findings. The implementation is committed as `96e0578`;
[GitHub run 34558550868](https://github.com/orojas01-glitch/redcms/actions/runs/34558550868)
passes the complete core job, including this SMTP test, and current-source and
dependency scans. The historical-secret gate alone remains failed.

Checkpoint: `checkpoint/before-smtp-hardening`, based on
`b61b7ffc0c5267da218cb0a53218d8f5301929aa`. Earlier uncommitted policy/recovery
documentation is preserved. No database migration or public URL change is needed.
Rollback code from the focused checkpoint after checking subsequent edits. Keep
private server settings backed up outside the webroot. Reverting to local mail
is an explicit operator choice that removes the authenticated SMTP guarantee;
never silently downgrade during an outage.

Unverified: sustained delivery/monitoring, Response/Register live delivery and
other client-specific mail configurations. The successful demo Contact test
does not guarantee delivery to every recipient or cover every form type.

## Demo deployment checkpoint

The owner authorized the demo setup with the existing security mailbox. FileZilla
transferred the credential-free runtime package over TLS; remote SHA-256 matched
`ccf34051cc628d0af04ea4085f71940a29e62ecef189991cc66ef808bd7715c4`.
Before replacement, private copies of the four old endpoints and local configuration
were preserved outside the webroot. The helper was installed before its callers.
All five deployed PHP files passed server lint and byte comparison; the 35-assertion
transport test also passed on Scala with no mail effects. Operator scripts remain
outside the webroot. The generic macOS archive-metadata warnings did not affect
the verified deployed file bytes.

A one-time, demo-scoped setup helper accepted the owner-entered hidden password
and confirmation. It checked TLS/authentication before atomically saving SMTP
settings and refused an intervening configuration change. No email was sent by
setup. The earlier encrypted NAS
snapshot predates this SMTP deployment and does not contain the new mailbox settings.

## Verified synthetic delivery

The owner selected a Gmail inbox and authorized one synthetic delivery test.
The deployed CLI proof first passed its no-send draft check, then made one send
attempt. The SMTP server confirmed acceptance and returned a transaction receipt.
Synthetic reference: `a34c5ed387b4774680a152bd`.

The Gmail connector's account identity matched the owner-selected recipient.
An exact sender/reference search found the message with INBOX and UNREAD labels,
timestamp `2026-09-10T03:58:32Z` (September 9 locally). Metadata inspection of
Google's Authentication-Results confirmed SPF=pass, DKIM=pass and DMARC=pass.
No full headers, mailbox passwords or unrelated inbox messages were exported.
The message remained unread; no resend or additional form submission occurred.

This proves authenticated, certificate-verified SMTP through the shared core
transport and actual delivery to this Gmail inbox for the one synthetic message.
The application endpoints were wired and locally acceptance-tested separately;
the subsequent authorized live Contact test is recorded below.

## Verified live Contact submission — 2026-09-10

The owner authorized a live browser form test to the same selected Gmail inbox.
The existing `/contacto/` page rendered a Contact form. Its stored recipient did
not match the approved address, so the exact form row was checkpointed privately
outside the webroot before temporarily setting the approved recipient, fixed
sender, unique subject and empty CC/BCC. No extra form, article or public URL
was created. No other installation or recipient was used.

Chrome submitted the real form once with synthetic name, reserved example email,
fictional telephone number and message reference `redcms-form-20260910-a91f`.
The page displayed `Enviado!`. An exact Gmail sender/reference search independently
found the matching message in INBOX at `2026-09-10T04:04:33Z`, with the expected
synthetic form fields. Google Authentication-Results reported SPF=pass, DKIM=pass
and DMARC=pass. No full headers or unrelated inbox contents were exported.

After delivery, a transaction checked that only the planned settings differed,
restored the original fields and Updated timestamp, and verified the entire form
row matched its checkpoint. A fresh browser load rendered the original form and
no browser error logs were observed. No test routing remains in the live form.
The private checkpoint is retained; the synthetic email remains unread in Gmail.

This proves the demo's browser validation/submission, live Contact endpoint,
shared authenticated SMTP transport and actual inbox delivery for one submission.
The original demo recipient configuration was restored; this test does not
establish ongoing delivery for that original recipient or change its ownership.
