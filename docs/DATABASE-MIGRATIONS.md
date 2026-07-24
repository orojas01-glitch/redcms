# Database Backup, Restore, And Migrations

Date: 2026-07-23

These commands preserve the existing RED-CMS public URLs and application table names. Run them from the project root.

## Configuration

The scripts read the same database settings as the CMS from `includes/config.local.php` or these environment variables:

- `RED_DB_HOST`
- `RED_DB_USER`
- `RED_DB_PASS`
- `RED_DB_NAME`

Optional runtime overrides are `RED_PHP_BIN` and `RED_MYSQL_BIN_DIR`. Temporary MySQL option files are mode `0600` and are removed when each command exits, so passwords are not placed on the command line.

## Backup

Use an absolute destination outside the public web root:

```bash
scripts/db-backup.sh /absolute/private/path/redcms-backup.sql
```

The command validates that the dump completed and writes a SHA-256 companion file beside it.

While any MyISAM table remains, first stop the PHP application writer. Then explicitly confirm the offline condition:

```bash
RED_DB_OFFLINE_CONFIRMED=1 scripts/db-backup.sh /absolute/private/path/redcms-backup.sql
```

The command refuses this flag-free operation while MyISAM tables exist. After all tables use InnoDB, it automatically uses a transaction-consistent online dump.

The all-InnoDB online path was verified locally on 2026-07-11 by backing up while the PHP server remained available and restoring the result into a disposable database.

## Restore Test

Restores are intentionally limited to a database that already exists and is granted to the configured application account:

```bash
scripts/db-restore.sh /absolute/private/path/redcms-backup.sql disposable_database_name
```

The restore command refuses the configured primary database and any nonempty target by default. Treat overrides `RED_DB_ALLOW_PRIMARY_RESTORE=1` and `RED_DB_ALLOW_NONEMPTY_RESTORE=1` as emergency controls requiring a separately verified backup and explicit approval.

A backup is not accepted as usable until it restores into a disposable database and its tables, engines, relationships, and exact row counts are checked.

## Migration Runner

Review current state without changing migration records:

```bash
scripts/db-migrate.sh --status
scripts/db-migrate.sh --dry-run
```

Apply pending migrations:

```bash
scripts/db-migrate.sh
```

Test a disposable database without changing local configuration:

```bash
scripts/db-migrate.sh --database=disposable_database_name --status
scripts/db-migrate.sh --database=disposable_database_name
```

The runner:

- creates `RED_Schema_Migrations` only when an actual run needs it;
- applies files from `database/migrations/` in filename order;
- records each file name, SHA-256 checksum, timestamp, and execution time;
- obtains a database advisory lock while the batch runs;
- reports orphaned records and refuses edited checksums;
- performs no work when every migration is already recorded.

Applied migration files are immutable. Correct an old migration with a new, later migration instead of editing the applied file.

### Article Image Filename Capacity

Migration `2026-07-18-article-image-filename-capacity.sql` widens `RED_Articles.BigPict`, `SmallPict`, and `SmallPict2` from `varchar(50)` to `varchar(255)` without changing stored values. The shared upload helper keeps generated stored names within 200 characters, including the extension and any collision suffix, so database capacity remains larger than the application-generated limit.

### Category And Subcategory Parent Relationships

Migration `2026-07-23-area-parent-relationships.sql` adds the nullable indexed parent columns `RED_Categories.SectionRecordID` and `RED_SubCategories.CategoryRecordID`. Their foreign keys reference `RED_Sections.RecordID` and `RED_Categories.RecordID` with `ON DELETE RESTRICT` and `ON UPDATE RESTRICT`.

The migration backfills only relationships that can be inferred unambiguously from an existing Article route in the same language. Unused, conflicting, or otherwise ambiguous legacy rows remain `NULL` for explicit assignment in the administrator workspace instead of being attached to a guessed parent. New Category and Subcategory writes require a valid active parent, while existing parentless records remain readable through the compatibility fallback until an administrator assigns them.

Before primary application, a transaction-consistent backup was restored to a disposable database. The migration preserved the user-created Category and Subcategory records, passed assignment, reparenting, inherited-route, Move Content, public lookup, foreign-key rejection, and protected-deletion checks, and produced a no-op rerun. The configured local database then passed the complete disposable acceptance suite with 28 applied migrations, zero pending files, zero checksum drift, both parent foreign keys, and unchanged primary-database isolation.

### Content Version History

Migration `2026-07-23-content-revisions.sql` adds `RED_Content_Revisions`, an append-only history table keyed by the stable `RED_Articles.RecordID`. Each row stores a canonical JSON snapshot, SHA-256 state hash, sequential version number, operation, administrator identity, timestamp, and optional restored-from version.

The table deliberately has no cascading foreign key to `RED_Articles`: a pre-delete snapshot must survive content deletion for future recycle-bin work. Article and Other snapshots contain their complete Article row; Gallery, Banner, and Video snapshots include the paired `RED_C_Gallery` row; Form Builder and Admin Login snapshots include the paired `RED_C_Form` row. Restores are transactional, permission-checked, non-destructive, subtype-preserving, and protected by a current-state hash so a stale history panel cannot overwrite a newer save.

The migration was proven on fresh disposable installs, reran as an exact no-op, and passed the focused 29-assertion create/update/restore/conflict/deduplication/paired-delete lifecycle before primary application. The configured local database now reports 29 applied migrations, zero pending files, and zero checksum drift.

### Custom Layout Builder

Migration `2026-07-24-custom-layout-builder.sql` adds `RED_Custom_Layouts` and `RED_Custom_Layout_Revisions`. The first table stores separate draft and published labels, bounded declarative JSON definitions and content hashes for stable `custom-*` layout ids. The second is an append-only version timeline with operation, administrator identity, snapshot hash, and optional restore source.

No PHP, HTML, CSS, query text, or template path is stored in either table. Publishing validates every row against an exact 12-unit grid, refuses a definition that removes an occupied position, and makes only the published snapshot available to the active standard-theme catalog. Archival is refused while any Section, Category, Subcategory, or Article assignment still uses the layout. The independent revision table intentionally retains history without cascading deletion.

The migration was applied to the configured local database after a disposable lifecycle proved draft save, publish, assignment, public rendering, conflict rejection, restore, occupied-position protection, archival protection, cleanup, and a no-op migration rerun. Current schema state is 20 InnoDB tables and 31 applied migrations with zero pending files or checksum drift.

## Local Clean-Install Acceptance

Milestone 3 provides one local command for disposable installer and migration verification:

```bash
scripts/dev-acceptance.sh
```

It creates a uniquely named `redcms_acceptance_*` database, grants the existing application account access, imports `db-structure.sql`, applies every migration, requires a no-op rerun and zero checksum drift, validates schema and relationships, runs the focused content-revision, page-distribution, and custom-layout lifecycles, compares the normalized schema to the configured primary database, starts an isolated FrankenPHP server on a generated non-conflicting port, and checks the five canonical clean-install routes with content markers and error scans. It then runs a disposable Webmaster login/password-upgrade/CSRF/logout/session-invalidation lifecycle, narrow Guest allowed/denied component, tool, site-management, and user-management checks with a 20-table no-mutation comparison, one protected Article lifecycle with long-filename uploads both before metadata creation and during editing, paired Contact Form and Video Gallery lifecycles, and one protected Gallery image-upload lifecycle. These require exact placeholder promotion, editor/public-route behavior, relationships, database values, file hashes, unchanged pre-existing media manifests, and cleanup. A final paired Gallery check installs a disposable-database-only trigger to force the late child update to fail, requires legacy response `no` and identical all-table checksums, removes the trigger, proves the same update can commit, and deletes the fixture pair. Finally the suite removes fixtures and temporary cookies/responses, generated upload sources and media, stops the server, revokes and verifies removal of the grant, and drops the database.

The command refuses the configured primary database and pre-existing acceptance-looking databases. Its exit trap removes any disposable trigger and fixtures before server, grant, and database cleanup after success, failure, interruption, or termination. See `docs/ACCEPTANCE-SUITE.md` for requirements, safety boundaries, and complete Milestone 3 coverage.

This command is for local development or controlled staging. It is not a production or HostGator deployment command.

## Required Deployment Order

1. Put the application into maintenance/offline mode when MyISAM tables are involved.
2. Create and verify an external backup plus checksum.
3. Run `scripts/db-migrate.sh --status` and review every pending file.
4. Run `scripts/db-migrate.sh` once.
5. Run it a second time and require `No pending migrations.`
6. Verify data counts, relationships, login, administrator permissions, and core public routes.
7. Keep the backup until the deployment has passed its acceptance window.
