# Bug: Contact form migration can preserve the wrong sender and unreliable mail transport

## Status

- Confirmed against the Adriana production replacement package on 2026-07-26.
- The Adriana production routing was corrected through the existing Form
  editor. A controlled submission reached the approved mailbox, and the client
  confirmed receipt.
- Confirmed a separate HostGator human-verification/AJAX incompatibility on
  2026-07-26; an Adriana-only theme compatibility correction is deployed.
- A generic RED-CMS 5.1 repair is not yet implemented.

## Impact

The imported Adriana contact form rendered and validated, but its original
replacement-package delivery settings did not match the production form that
was backed up. Before the production correction, the replacement package used:

- Subject: `Contact RedCMS Support`
- From: an unrelated external-domain sender
- To: an external operator mailbox
- CC/BCC: empty

The pre-replacement production form assigned to the `contacto` section used:

- Subject: `contacto web adrianagranobles.com`
- From: the verified site-domain mailbox
- To: the site-domain mailbox and an approved operator mailbox
- CC/BCC: empty

The cPanel account confirms that the site-domain mailbox exists and is
unrestricted. The replacement package's configured sender is not one of the
listed mailboxes. Client-specific addresses are intentionally redacted from
this public report.

This can cause submissions to be sent with an unrelated or nonexistent From
identity, delivered to the wrong inbox, rejected by hosting-provider policy, or
accepted by the RED-CMS UI without proving final delivery.

## Reproduction

1. Import the approved Adriana replacement database.
2. Sign in and open `/contacto`.
3. Edit the Form component without saving.
4. Inspect **Delivery settings**.
5. Observe the Red Sphere/Gmail values instead of the backed-up Adriana values.
6. Submit the public form with a valid payload in an approved test environment.
7. Observe that the browser success callback replaces the form after any HTTP
   success response; it does not distinguish transport success, fallback
   failure, or final mailbox delivery.
8. On hosting that protects the endpoint with a JavaScript human challenge,
   observe that the AJAX request receives HTTP 409 and the form remains visible
   without success, failure, or retry guidance.

Do not use a production visitor address for reproduction. Use a controlled
mailbox and review the hosting mail-delivery report.

## Evidence

- The approved replacement SQL stores an unrelated external-domain address as
  `Submitter` and an operator mailbox as `Destinatary` for the affected Form
  record.
- The pre-replacement database backup stores the active `contacto` Contact form
  with the verified site-domain mailbox as sender and the approved
  site/operator routing as recipients.
- The public form posts by AJAX to `/bin/contact.php`.
- `/bin/contact.php` uses PHPMailer 6.9.3 but leaves it on PHP's native
  `mail()` transport. It has no configurable authenticated SMTP transport.
- The primary transport does not set Reply-To to the visitor's validated email.
  The fallback sets Reply-To to the site sender rather than the visitor.
- cPanel Track Delivery contains external Gmail failures rejected with
  `AUP#SNDR`, demonstrating that native server-envelope delivery is not a
  reliable external-delivery guarantee on this host.
- The Adriana production request reached `/bin/contact.php` but received HTTP
  409 with an 83-byte hosting response that sets `humans_21909=1` and requests
  a document reload.
- The response is returned to jQuery AJAX, which does not execute the returned
  script as a top-level navigation. The legacy submit handler implements only a
  success callback, so the visitor receives no confirmation or error.

## Cause

There are three separate problems:

1. **Client migration data selection:** the approved client package preserved a
   generic Contact template from the migration source database instead of the
   backed-up production routing values. The persisted
   `System_Adriana_28_Contact_Template` metadata also contains those stale
   values, so a later rerun can restore them after a manual correction.
2. **Core transport limitation:** RED-CMS validates From/To/CC/BCC syntax, but
   it does not require a local-domain sender, provide authenticated SMTP
   settings, expose transport health, or return an honest delivery state to the
   public form.
3. **Hosting challenge incompatibility:** the public form assumes every request
   can complete through background AJAX. Host-level JavaScript challenges may
   require a cookie and document navigation before PHP runs. The current client
   does not prepare for the challenge, inspect non-2xx responses, or present a
   recoverable error.

## Immediate client recovery

After taking a database backup, update only the Adriana Form component through
the administrator UI:

- Subject: `Contacto web adrianagranobles.com`
- From: the verified site-domain mailbox and display name
- To: the approved site-domain and client mailboxes
- CC: the approved operator mailbox
- BCC: empty

Then submit one controlled test. Confirm that the local
site-domain mailbox receives it and review cPanel Track Delivery for the
external recipients. Do not declare the issue resolved from the browser's
“Enviado” message alone. The client-specific destination addresses reflect the
routing approved during the 2026-07-26 correction; a generic importer must
never seed them into another installation.

Before rerunning the Adriana content importer, update its persisted Contact
template metadata through a reviewed client-only repair; otherwise it can
reapply the stale routing values.

For the current Adriana installation only, the active theme's existing
`integrations.js` prepares the exact HostGator human-verification cookie during
the contact form's capture-phase submit event. This permits the unchanged
legacy AJAX handler to reach `/bin/contact.php`. This host-specific workaround
must not be copied into generic starter content or treated as a replacement for
truthful transport status.

## Version 5.1 repair boundary

1. Separate client mail-routing data from generic starter content and require
   an explicit manifest mapping for From, To, CC, BCC, and subject.
2. Make the contact-template dry run report the selected source record and the
   exact non-secret routing changes. Refuse ambiguous, missing, unrelated-domain,
   or overwrite cases.
3. Preserve an administrator-edited form on rerun unless the operator approves
   an exact old/new routing diff.
4. Add site-level mail transport settings outside the public database dump:
   authenticated SMTP host, port, encryption, username, secret reference,
   timeout, and enabled state.
5. Require a verified site-domain From address. Set Reply-To to the visitor's
   validated email without ever using visitor input as the envelope sender.
6. Return separate application states for validation accepted, transport
   accepted, transport failed, and retry/fallback. The public UI must not show
   “Enviado” when both primary and fallback transports fail.
7. Record a bounded, privacy-safe delivery audit: timestamp, form id, transport,
   outcome, and provider/queue id when available. Do not store message bodies,
   visitor email addresses, SMTP credentials, or raw provider responses.
8. Add acceptance coverage for local and external recipients, malformed
   routing, SMTP failure, native-mail fallback, Reply-To, duplicate submissions,
   and migration reruns after administrator edits.
9. Add an AJAX error and timeout path that preserves visitor input, announces a
   clear failure, and offers a safe retry. A non-2xx hosting challenge must
   never leave the form silently stalled.
10. Document and test the deployment contract for WAF/bot protection. Prefer a
    host configuration that excludes the same-origin contact endpoint from
    browser-script challenges while retaining rate limits, CSRF protection,
    validation, honeypot checks, and abuse monitoring.

## Acceptance criteria

- A dry run identifies the correct client form and reports exact routing without
  exposing credentials.
- Import refuses to replace populated routing with unrelated starter values.
- The local-domain sender is used and the validated visitor address is Reply-To.
- Authenticated SMTP can be configured privately and tested without committing
  secrets.
- A failed transport produces a truthful public error/retry state.
- HTTP 4xx/5xx, timeout, invalid response, and host-challenge cases produce a
  visible, accessible retry message without clearing submitted fields.
- A successful controlled submission is visible in transport diagnostics and
  arrives in the intended local mailbox.
- Rerunning the client importer does not undo an approved administrator change.
- Rollback restores the prior form row and private transport configuration.
