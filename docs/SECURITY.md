# RED-CMS Security Notes

Date: 2026-07-02

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

## Seed Data Warning

The current `db-structure.sql` includes data rows from an existing site. Before using it as a reusable installer artifact:

- Redact or replace admin users.
- Replace all passwords.
- Remove site-specific content if the dump will be distributed.
- Rotate any credentials that have been shared in documents or dumps.

## Next Security Work

- Continue moving remaining admin AJAX write endpoints onto centralized admin-session and CSRF checks.
- Continue upload hardening review beyond `admin/bin/post_file.php` and `admin/bin/post_ftp.php`.
- Move more queries to prepared statements, continuing with admin write endpoints.
