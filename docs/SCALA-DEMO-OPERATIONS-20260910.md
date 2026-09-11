# Scala demo integration and monitoring review

Scope: `demo.red-sphere.com` only. Existing SMTP and recovery evidence is in
[SMTP-DELIVERY.md](SMTP-DELIVERY.md) and
[SCALA-DEMO-RECOVERY-20260909.md](SCALA-DEMO-RECOVERY-20260909.md).

## Database-managed integration inventory

Read-only server-side inspection covered all nine RED_Advanced records. Only
item names and boolean indicators were emitted; no stored HTML, settings values,
credentials or client records were displayed or exported.

Website_Header, Website_Footer and Website_CSS were empty. Website_Title,
Website_Slogan and Website_Logo were also empty. The active/previous theme entries
and Red Sphere credit flag were populated. None of the nine values contained
script-tag, inline-event-handler or external-URL indicators. There were zero
installed add-ons, and private runtime configuration confirmed CSP enforcement.

Source review confirms Header/Footer content is trusted global HTML, with a
dedicated administrator write path. Database Website_CSS is not the whole active
theme stylesheet: the current CSS editor writes the active theme's file. These
empty database fields do not prove all templates/assets contain no integrations.
Regex indicators are an inventory aid, not a sanitizer or an XSS certification.
No database or theme changes were necessary. Other clients need their own review.

## Provider monitor — activated with owner approval

SPanel showed the demo's website monitoring as Disabled. Its edit dialog offers
1-, 5- and 15-minute intervals, optional speed thresholds, SSL checks and homepage
expected-text checks. The SSL help specifies alerts below 72 hours to expiration.
The health check searches for expected homepage text and can identify an error
page; it does not authenticate to the administrator or test a full user journey.

The owner explicitly approved activation for the demo with their Gmail inbox as
the recipient. SPanel saved Enabled, five-minute interval, SSL checks on, homepage
health checks on and speed alerts off. Reopening the settings confirmed these
values and the approved contact address (visually verified in the saved dialog).

The provider stripped the hyphen from the proposed `RED-CMS` expected text, saving
`REDCMS`. Before relying on the check, it was corrected to the existing homepage
navigation text `Contacto`; reopening confirmed that exact value persisted.
Update the expected text if the homepage navigation changes later.

The contact-field help describes an account contact for all automatic
notifications. The owner accepted that possible wider demo-account effect; do
not represent it as guaranteed outage-only mail. The recurring checks run through
Scala rather than this workstation. Rollback is to disable this demo monitor and
review the contact field before restoring any account-wide notification setting.
The prior monitor was disabled and its contact field was blank; no unrelated
account's monitor was changed.

The provider's Force Manual Check was invoked once. Its initial summary still
showed zero-duration measurements and zero SSL days, without a verified completed
sample. An independent TLS handshake with hostname/certificate validation passed:
the actual demo certificate expires `2026-12-08T14:46:37Z`. Do not interpret those
initial provider numbers as measured uptime or proof of an expired certificate.
During the 2026-09-10 closeout, the provider summary was populated: Enabled,
100% reported uptime, 0.15-second average response, 0.09-second TTFB, zero slow
events and 90 days remaining on SSL. This confirms the monitor collected results;
the displayed uptime has no independently established measurement window and is
not an SLA. Actual monitor-alert delivery remains unverified.

## Operational gaps and response procedure

Saved settings and a populated provider sample are verified. Before declaring
monitoring fully exercised, verify notification delivery. No outage or certificate failure was induced.
Do not disrupt the live demo simply to trigger a test alert. Any isolated alert
test should use a separately authorized disposable target or provider test action.

Website checks alone do not prove disk-capacity, backup-age, PHP/DB-error or mail
delivery monitoring. Existing SMTP and NAS results are one-time evidence. An owner
still needs to accept backup frequency/retention, acceptable data loss and recovery
time, and coverage during absences. No new paid service or workstation job was added;
the only new recurring check is the owner-approved Scala website monitor above.

For an alert, verify the affected demo URL and certificate, then check hosting
resource status, private error logs and database connectivity without exporting
payloads. Use the focused deployment rollback and verified snapshot when needed,
preserving newer writes and obtaining approval for an actual restore/cutover.
Escalate provider incidents through the owner's existing support channel.

This group changed the scoped provider monitor and documentation. Earlier SMTP,
policy and recovery edits remain intact; no code tests were repeated.
Documentation whitespace checks pass.
