# RED-CMS Security Notes

Date: 2026-07-25

## Configuration Secrets

Runtime secrets should not live in `includes/config.php`.

Supported configuration sources:

1. Environment or server variables.
2. `includes/config.local.php` on each server.

`includes/config.local.php` is intentionally ignored by Git and blocked by `.htaccess`.

Use `includes/config.local.example.php` as the template for server-specific values.

Supported environment variables:

- `RED_DB_HOST`
- `RED_DB_USER`
- `RED_DB_PASS`
- `RED_DB_NAME`
- `RED_IPSTACK_ACCESS_KEY`
- `RED_LEGACY_MAIL_OWNER`
- `RED_PAYPAL_PDT_HOSTNAME`
- `RED_PAYPAL_PDT_AUTH_TOKEN`
- `RED_PAYPAL_CONFIRMATION_FROM_EMAIL`
- `RED_PAYPAL_CONFIRMATION_FROM_NAME`

The existing constants `DBHOST`, `DBUSER`, `DBPASS`, and `DBNAME` are preserved so current CMS classes continue to work.

## Admin Passwords

`bin/login.php` now supports modern password hashes using PHP `password_hash()` and `password_verify()`.

Backward compatibility is deliberate:

- Existing plain-text passwords can still log in temporarily.
- After a successful login, the password is upgraded to a hash only if the database column is large enough.
- Run the migration below before relying on automatic upgrades.

```sql
ALTER TABLE `RED_Admin`
  MODIFY `Password` varchar(255) NOT NULL;
```

Migration file:

`database/migrations/2026-07-02-red-admin-password-hash.sql`

Administrator Users now creates and resets passwords with `password_hash()` only. The manager never renders stored password values. Password resets invalidate existing sessions for that account on its next protected admin request.

Only accounts whose `AdminType` is `webmaster` or `superadmin` can open or submit Administrator Users actions. Self-deletion and deletion of the final manager are rejected. Component permissions come from `AdminComponents`; utility-tool permissions come from `AdminTools` after applying:

`database/migrations/2026-07-10-red-admin-user-tools.sql`

Administrator email is required for every create/update request and duplicate non-empty email values are rejected by the application. The migration widens `RED_Admin.Email` to `varchar(254)`. Existing blank legacy emails are shown as repair rows in Edit User and must be completed before those accounts can be saved.

## Portable Starter Data

The tracked `db-structure.sql` is the portable starter installer. Its
administrator rows use unavailable password hashes and generic identities; its
Form presets contain no retained client identity, legal copy, recipient, or
payment form.

Client database exports must never replace this file. Keep every client
database, theme, media archive, local configuration, and rollback point outside
the starter repository. Before distribution, run:

```bash
php scripts/clean-starter-boundary-self-test.php
```

The same dependency-free check runs before `scripts/dev-acceptance.sh` creates
its disposable database.

## Legacy Mail And Payment Configuration

The CMS-owned Contact, Response, and Register behavior remains governed by
`docs/OPERATIONAL-FORM-BOUNDARY.md`.

The compatibility paths `/bin/MailHandler.php` and `/bat/MailHandler.php`
retain their URLs but send only to `RED_LEGACY_MAIL_OWNER` or the matching
server-local configuration value. With no valid configured owner, they return
`mail failed` and invoke no mail transport. The obsolete ASP.NET handler is
inert.

`/bin/paypal_response.php` remains default-inert. It requires a server-local PDT
token and a valid `RED_PAYPAL_CONFIRMATION_FROM_EMAIL` before contacting
PayPal. Confirmation mail uses only that configured sender and the verified
payer address; it has no fixed BCC or fallback recipient. This compatibility
route does not activate the Version 5.1 member, entitlement, or payment model.

## Database Backup Security

Full database dumps contain administrator hashes, email addresses, site content, and configuration data stored in tables. Keep backups outside the public web root with access limited to the operator, record their SHA-256 checksums, use encrypted storage for production copies, and remove expired archives according to the site's retention policy.

Use `scripts/db-backup.sh` and `scripts/db-restore.sh` as documented in `docs/DATABASE-MIGRATIONS.md`. The restore command protects the configured primary database and nonempty targets by default.

## Multi-User Authorization

Administrator component and utility selections are now server-side authorization rules, not presentation-only settings.

- `AdminComponents` controls content creation, rendering of edit controls, updates, deletes, ordering, feature assignment, content uploads, and bulk-tool record selection.
- Group components resolve the exact child subtype through `RED_C_Form.FormType` or `RED_C_Gallery.GalleryType`. A user assigned Video cannot operate Gallery or Banner records.
- `AdminTools` controls both the render and write endpoints for each utility tool.
- Webmaster is the assignable site-manager role. Layout, navigation, sections, categories, subcategories, advanced settings, and administrator-account management are denied to Guest accounts at the endpoint. Legacy `superadmin` database values remain recognized as managers for compatibility but cannot be newly assigned.
- Add User and Edit User expose an allowlisted `AdminType` selector with only Guest and Webmaster. The signed-in manager cannot change their own role, and the final manager cannot be changed to Guest.
- Submitted component changes and subtype changes are reauthorized before a write. Mismatched parent/child component inserts are rejected.
- Authorization failures return HTTP 403 `no` and write a minimal denial entry to the server error log.

The shared contracts live in:

- `includes/bootstrap.php`
- `includes/admin_authorization_helpers.php`

## Failed Login Throttling

`database/migrations/2026-07-12-login-attempt-throttling.sql` adds `RED_Login_Attempts`, and `includes/login_throttle_helpers.php` applies the policy from `bin/login.php`.

- Only failed administrator login attempts are stored here. Successful login history is intentionally not stored by the minimal activity-audit batch; its initial scope is successful Administrator Users mutations only.
- Stored fields are a lowercase/trimmed username SHA-256 digest, the packed client network address supplied by `REMOTE_ADDR`, and the failure timestamp. Passwords, password hashes, submitted usernames, session IDs, CSRF tokens, and content are not stored.
- The application deliberately ignores client-supplied `X-Forwarded-For`. A reverse proxy must normalize the trusted client address at the web-server boundary before PHP if per-client throttling is required behind that proxy.
- Within a rolling 15-minute window, a new login is temporarily blocked after five failures for the same username/client pair, 15 failures for the same username across clients, or 30 failures from one client across usernames.
- Blocked and failed requests preserve the existing generic HTTP 200 `no` response so account existence and lock state are not exposed through a new response contract.
- A successful login clears failures for that normalized username. It does not erase failures for other usernames from the same client and does not invalidate already-authenticated sessions.
- Failed attempts older than 24 hours are removed in indexed batches of up to 500 during login traffic. The table is InnoDB so rollback behavior remains available.

## Administrator Activity Audit

`database/migrations/2026-07-12-administrator-activity-audit.sql` adds `RED_Admin_Activity_Log`, and `includes/admin_audit_helpers.php` writes the allowlisted events from `admin/bin/update_admin_users.php`.

- Initial events are only successful `administrator.created`, `administrator.updated`, and `administrator.deleted` operations.
- Each row contains the event name, numeric actor administrator ID, target type, numeric target record ID, and timestamp. No foreign key is used so a deletion event remains attributable after the target account is removed.
- The table does not contain usernames, aliases, emails, IP addresses, passwords or password hashes, session IDs, CSRF tokens, request bodies, component/tool permission lists, or content bodies.
- The administrator mutation and its audit insertion share one InnoDB transaction. If audit persistence fails, the user mutation rolls back and the existing endpoint returns `no`.
- Events older than 180 days are removed during audited writes in indexed batches of up to 500. Production retention requirements should be reviewed before deployment if a longer legal or operational history is required.
- Login/logout history and general content, layout, navigation, upload, and tool actions are outside this minimal first scope. Expand coverage only through separately reviewed, allowlisted event batches.

## Next Security Work

- Review whether additional allowlisted activity categories are needed after the repeatable acceptance suite is established; do not add request payload logging.
- Optionally replace immediate account/password creation with single-use, expiring email invitations.
- Rotate real production credentials and confirm production PHP/MySQL versions before deployment.

The active milestone order is maintained in `docs/ROADMAP.md`.
