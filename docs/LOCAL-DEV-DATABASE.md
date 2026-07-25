# Local Dev Database

Date: 2026-07-23

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

## Current Local Data

`db-structure.sql` was imported into `redcms_dev`.

Current verification after the Milestone 5 content-version migration:

- application/support table count excluding the migration ledger: 17
- total table count after migration tracking: 18
- `RED_Articles` rows: 7
- `RED_Admin` rows: 2
- `RED_Admin.Password`: `varchar(255)`
- InnoDB tables: 18
- remaining MyISAM tables: 0
- recorded migrations: 29
- immutable content revisions: 0 before the first post-migration content save
- `RED_Categories.SectionRecordID` stores the owning Section; `RED_SubCategories.CategoryRecordID` stores the owning Category
- both parent columns are indexed and protected by `ON DELETE RESTRICT` / `ON UPDATE RESTRICT` foreign keys
- the local test hierarchy is `About → test-category → test-subcategory`, with canonical route `/about/test-category/test-subcategory/`
- administrator activity audit: empty after acceptance cleanup; only successful Administrator Users create/update/delete events are currently allowlisted
- evidence-backed public query indexes: 10 across Articles, hierarchy aliases, Menu, Form, and Gallery
- explicit application connection charset: `utf8mb4`
- `utf8mb4_unicode_ci` character columns: 160 across all 20 tables
- remaining `latin1` character columns: 0
- remaining `utf8mb3` character columns: 0

The password-width migration also ran successfully:

`database/migrations/2026-07-02-red-admin-password-hash.sql`

After the 2026-07-03 login smoke tests, both local dev admin rows were upgraded from legacy plaintext passwords to bcrypt-style hashes through the normal login path.

## Backup, Restore, And Migrations

The verified commands are:

```bash
scripts/db-backup.sh /absolute/private/path/redcms-backup.sql
scripts/db-restore.sh /absolute/private/path/redcms-backup.sql disposable_database_name
scripts/db-migrate.sh --status
scripts/db-migrate.sh --dry-run
scripts/db-migrate.sh
```

All tables are now InnoDB. `scripts/db-backup.sh` uses a transaction-consistent online dump and no longer requires the PHP application writer to be stopped. The first online dump was restored into a disposable database with all 15 tables, ten migration records, and matching administrator/layout fingerprints.

See `docs/DATABASE-MIGRATIONS.md` for guardrails, disposable-restore validation, deployment order, and migration immutability rules.

The parent-hierarchy migration first ran against a transaction-consistent restored copy, preserved both user-created test records, passed relationship and delete-protection checks, and returned `No pending migrations.` on rerun before it was applied to `redcms_dev`.

The content-version migration was then proven through a focused 29-assertion aggregate lifecycle and the complete disposable acceptance suite before primary application. The final migration status is `29 applied, 0 pending, 0 drifted`; no historical rows are synthesized automatically, so each existing content item receives an accurate baseline only when it is next changed.

## Notes

This is a local development database only. It is not a production database and does not change public URLs or table names.

See also:

`docs/LOCAL-DEV-PHP.md`
