# Local Dev Database

Date: 2026-07-02

## Install Location

MySQL Community Server 8.4.10 LTS for macOS ARM was installed outside the web root:

`/Users/oscarrojas/Documents/red-cms-dev/mysql-8.4.10-macos15-arm64`

Data directory:

`/Users/oscarrojas/Documents/red-cms-dev/mysql-data`

The server runs locally on:

`127.0.0.1:3307`

## Project Configuration

`includes/config.local.php` points this workspace at the local dev database:

- Host: `127.0.0.1:3307`
- Database: `redcms_dev`
- User: see `includes/config.local.php`

`includes/config.local.php` is ignored by Git and should stay local to each machine.

## Start, Stop, Status

From the project root:

```bash
scripts/dev-mysql-start.sh
scripts/dev-mysql-status.sh
scripts/dev-mysql-stop.sh
```

## Imported Data

`db-structure.sql` was imported into `redcms_dev`.

Verification after import:

- `RED_*` table count: 14
- `RED_Articles` rows: 154
- `RED_Admin` rows: 2
- `RED_Admin.Password`: `varchar(255)`

The password-width migration also ran successfully:

`database/migrations/2026-07-02-red-admin-password-hash.sql`

After the 2026-07-03 login smoke tests, both local dev admin rows were upgraded from legacy plaintext passwords to bcrypt-style hashes through the normal login path.

## Notes

This is a local development database only. It is not a production database and does not change public URLs or table names.

See also:

`docs/LOCAL-DEV-PHP.md`
