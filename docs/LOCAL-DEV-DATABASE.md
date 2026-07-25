# Local Dev Database

Date: 2026-07-25

## Install Location

MySQL Community Server 8.4.10 LTS for macOS ARM was installed outside the web root:

`/Users/oscarrojas/Documents/red-cms-dev/mysql-8.4.10-macos15-arm64`

Data directory:

`/Users/oscarrojas/Documents/red-cms-dev/mysql-data`

The server runs locally on:

`127.0.0.1:3307`

## Project Configuration

Every RED-CMS checkout must use its own ignored `includes/config.local.php`,
database name, and database account. The verified Version 5.1 starter workspace
uses:

- Host: `127.0.0.1:3307`
- Database: `redcms_v51_starter`
- User: `redcms_v51_starter`

The password and all optional mail/payment values remain only in the ignored
local configuration. Do not point a client installation at this database or
reuse a client database for starter development.

## Start, Stop, Status

From the project root:

```bash
scripts/dev-mysql-start.sh
scripts/dev-mysql-status.sh
scripts/dev-mysql-stop.sh
```

## Current Local Data

`db-structure.sql` was imported into `redcms_v51_starter`, then all checked-in
migrations were applied.

Current verified state:

- total table count: 20
- recorded migrations: 31
- pending migrations: 0
- drifted migrations: 0
- `RED_Articles` rows: 4
- `RED_Admin` rows: 2
- `RED_Admin.Password`: `varchar(255)`
- InnoDB tables: 20
- remaining MyISAM tables: 0
- active theme: `starter-reference`
- previous hard-recovery theme: `legacy-bootstrap`
- generic unavailable administrator password hashes
- empty `images/articles` and `images/gallery` upload boundaries
- remaining `latin1` character columns: 0
- remaining `utf8mb3` character columns: 0

The complete guarded acceptance lifecycle passed on 2026-07-25 against a
separate `redcms_acceptance_*` database. It preserved the
`redcms_v51_starter` isolation snapshot and removed its exact temporary server,
fixtures, media, grant, and database.

## Backup, Restore, And Migrations

The verified commands are:

```bash
scripts/db-backup.sh /absolute/private/path/redcms-backup.sql
scripts/db-restore.sh /absolute/private/path/redcms-backup.sql disposable_database_name
scripts/db-migrate.sh --status
scripts/db-migrate.sh --dry-run
scripts/db-migrate.sh
```

All tables are InnoDB. `scripts/db-backup.sh` uses a transaction-consistent
online dump and does not require the PHP application writer to be stopped.

See `docs/DATABASE-MIGRATIONS.md` for guardrails, disposable-restore validation, deployment order, and migration immutability rules.

## Notes

This is a local development database only. It is not a production database and does not change public URLs or table names.

See also:

`docs/LOCAL-DEV-PHP.md`
