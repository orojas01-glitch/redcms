# Security report intake and patch response

Status: prepared locally on 2026-09-09. The owner selected and confirmed creation
of `security@red-sphere.com`, accepted the response targets below, and confirmed
receipt of a test email on 2026-09-09. Receipt is owner-confirmed evidence; the
assistant did not inspect the mailbox or send that initial receipt-test message.
The accepted policy is included with the candidate documentation. Later explicitly
authorized outbound SMTP tests are separately recorded in SMTP-DELIVERY.md.
The public policy is [SECURITY.md](../SECURITY.md).

## Intake ownership and evidence

The repository API returned `enabled: false` for private vulnerability reporting
on 2026-09-09. Existing private client support is available to established clients,
but was not a complete discoverable reporting path for outside researchers.
The dedicated email now supplies that contact in the local policy.

1. Selected: `security@red-sphere.com`, creation confirmed by the owner on
   2026-09-09; test receipt confirmed by the owner the same day.
2. The repository owner maintains intake and the accepted response targets.
   Coverage during absences remains to be arranged; no backup responder is claimed.
3. Accepted response targets and contact are included in the local root policy.
   Candidate-branch publication and CI are tracked in HARDENING-5.1.2-CLOSEOUT.md;
   this does not publish a software release.
4. Keep operational ownership details privately. Keep
   reporter identities and report contents outside the public repository.

Owner-accepted targets: acknowledge within 5 business days;
provide an initial assessment within 10 business days; update confirmed, unresolved
cases at least every 14 calendar days. Ordinary bug reports and feature requests
are outside this security intake policy. Business days mean Monday through Friday excluding
US federal holidays, using America/New_York time. These are communication targets,
not guaranteed fix dates. An acknowledgement should state the next update date.

## Handling a report

- Assign a private case reference, receipt time and responsible maintainer. Request
  affected versions, minimal synthetic reproduction and a safe reply channel.
  Never request passwords, tokens, client exports or unauthorized testing.
- Reproduce in an isolated fixture. Classify exploitability, consequence, affected
  versions and client/add-on boundaries. Record confirmed facts separately from
  reporter claims and unknowns. Prioritize active exploitation and authentication,
  data exposure or code execution risks for immediate maintainer attention.
- Acknowledge privately, explain the assessment and state the next update date.
  Explain delays before the target elapses where possible; do not promise a patch
  date before impact and compatibility are understood.
- Preserve a rollback checkpoint, implement the narrow correction and regression
  test, and run the relevant release checks specified in the root security policy.
  Keep exploit details private while preparing the fix and coordinated disclosure.
- Obtain release and client deployment authorization separately. Deliver a pinned
  patch, affected-version guidance, migration requirements and rollback instructions.
  Use [CLIENT-UPDATE-RECORD.md](CLIENT-UPDATE-RECORD.md) privately for each installation.
- Close with the fix revision/release, verification, remaining exposure and agreed
  disclosure status. Ask before naming or crediting a reporter. A scanner finding
  or owner report alone does not establish external credential revocation.

## Evidence and rollback for this group

Baseline: `b61b7ffc0c5267da218cb0a53218d8f5301929aa`, clean working tree;
checkpoint: `checkpoint/before-security-reporting-policy`.
Changes are documentation only; there is no runtime, database or account change.
Rollback restores the affected tracked documents from that checkpoint and removes
this newly added procedure after checking for subsequent edits.

Validation: documentation whitespace checks passed; linked local policy and client
update record exist. No runtime checks were repeated for these documentation-only edits.
Unverified: absence coverage and a real vulnerability report handling exercise.
Mailbox receipt is owner-confirmed, not independently inspected. This does not
verify RED-CMS authenticated SMTP or application-generated mail delivery.
No GitHub setting, mail account, release or client deployment was changed by the assistant.
