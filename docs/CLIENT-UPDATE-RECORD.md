# Private client update record template

Copy this template outside the repository and web root. No client has been updated
by the 5.1.2 candidate task.

| Evidence | Required record |
| --- | --- |
| Client and authority | Internal client reference; exact approved installation and scope |
| Release identity | Previous/new core revision; independent add-on versions; artifact SHA-256 |
| Backup | Private backup reference, checksum, encryption/access policy, restore rehearsal date/result |
| Database | Pre/post migration status; pending/drifted count; schema compatibility |
| Transport | Canonical HTTPS redirect; cookie/header checks; denied sensitive paths |
| Authentication | Login/logout, CSRF, inactivity/absolute expiry, password reset/account deletion invalidation |
| Compatibility | Public URL smoke; Article and Other save/reload; uploads; theme recovery |
| Delivery | Synthetic mail ID and redacted authenticated SMTP/provider receipt; inbox receipt separately |
| Operations | Monitoring owner, restore/rollback owner, maintenance window |
| Result | Pass/fail/unverified, unresolved risks, approval reference, date |
| Rollback | Trigger, exact restored revision/artifact, database decision, verification result |
