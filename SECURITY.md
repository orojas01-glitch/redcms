# Security policy

## Supported versions

The latest published 5.1 patch is the maintenance target. 5.1.2 is an unpublished
hardening candidate, not a supported release yet. Older 5.1 installations should
move to the latest patch after a client-specific backup and compatibility check.
5.0 and earlier have no promise of security backports. Separately distributed
add-ons and provider adapters have their own pinned compatibility contracts;
a core patch does not certify or automatically upgrade them. 5.2 editor work
is a separate objective.

## Reporting a vulnerability

Report privately to the RED-CMS maintainer through the existing private client
support conversation or repository-owner contact channel. If GitHub offers
“Report a vulnerability” at https://github.com/orojas01-glitch/redcms/security,
use that private form. Availability of that GitHub feature is not yet verified;
do not fall back to a public issue containing exploit details or client data.
A dedicated reporting mailbox and response-time commitment remain owner decisions.

Include affected version, surface, a minimal synthetic reproduction, consequence,
and a safe way to reply. Do not send passwords, tokens, database exports, personal
data, session cookies, workbooks or production payloads. Do not test another
client or payment provider without their authorization.

## Patch delivery

Reproduce in an isolated fixture, classify consequence and exploitability, add a
regression check, preserve compatibility and rollback, then prepare a reviewable
patch. Run core contracts, disposable database/authentication/migration/theme
acceptance, artifact boundary checks and redacted secret/dependency scans.
Known high-risk unresolved findings block release; suppressions require documented
synthetic-fixture evidence. A clean scanner result is not certification.

Publishing tags/releases and client deployment require separate owner approval.
Record the source revision and archive checksum, migration status, dependency
versions and open risks. For each client, record the approved scope, pre-update
version and backup, checksums, restore rehearsal, maintenance window, post-update
checks and rollback result using `docs/CLIENT-UPDATE-RECORD.md`. Store completed
client records privately outside this repository. Never include secrets in them.
