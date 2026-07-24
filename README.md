# RED-CMS 5.0

RED-CMS is a lightweight PHP and MySQL content management system for structured, template-driven websites. Version 5.0 modernizes the legacy application while preserving its public URLs, existing database table names, and compatibility-first deployment model.

The current release adds a consistent administrator workspace, standard theme packages, visual page structures, reusable layouts, content version history, safer database migrations, and repeatable acceptance testing.

## Release Status

RED-CMS 5.0 and Milestone 5 are implementation-complete on the release branch. The complete checkpoint is under review in [pull request #2](https://github.com/orojas01-glitch/redcms/pull/2) before it is merged into `main`.

Version 5.1 is documented as product direction only. Payment, member access, editorial workflow, notifications, expanded roles, installable tools, and social publishing integrations are not active 5.0 features.

## Highlights

- Polished, responsive administrator workspace
- Article, Banner, Form, FTP, Gallery, Other, and Video authoring
- Parent-backed Sections, Categories, and Subcategories
- Three-level top navigation
- Direct drag-and-drop component positioning
- Reusable Layout Builder with desktop and mobile maps
- Non-destructive content version history and restoration
- Local, provider-independent TinyMCE compatibility editor
- Standard theme contract with validation, preview, activation, and rollback
- Prepared database operations, CSRF enforcement, scoped permissions, and transactional writes
- Migration ledger, guarded backup/restore tools, and disposable acceptance testing

## Two Separate Profiles

This repository supports two profiles that share the RED-CMS core but keep separate content databases:

1. **Adriana site** — the retained Adriana Granobles website using the `adriana-granobles` theme and its own 28-route content database.
2. **Portable starter** — the reusable RED-CMS distribution using the `starter-reference` theme and generic starter data.

Never overwrite one profile with the other. The retained Adriana database must not be removed by disposable test cleanup. See [Edition Profiles](docs/EDITION-PROFILES.md).

## Local Development

The verified local environment uses:

- PHP 8.5.8 through FrankenPHP
- MySQL 8.4 LTS
- MySQL at `127.0.0.1:3307`
- Portable starter at `http://127.0.0.1:8055/`
- Starter administrator at `http://127.0.0.1:8055/admin/`
- Retained Adriana site at `http://127.0.0.1:8060/`

From the repository root:

```bash
scripts/dev-mysql-status.sh
scripts/dev-mysql-start.sh
scripts/dev-php-server.sh
```

Check service state first and start only services that are stopped. Local credentials belong in the ignored `includes/config.local.php`; never commit that file.

Detailed setup notes:

- [Local PHP runtime](docs/LOCAL-DEV-PHP.md)
- [Local database](docs/LOCAL-DEV-DATABASE.md)
- [Database migrations](docs/DATABASE-MIGRATIONS.md)

## Verification

Run PHP syntax checks:

```bash
scripts/dev-php-lint.sh
```

Run the theme and administrator contract suite:

```bash
php scripts/theme-contract-self-test.php
```

Run the complete guarded acceptance lifecycle:

```bash
scripts/dev-acceptance.sh
```

The acceptance runner creates a uniquely named temporary database, refuses the configured primary database, exercises migrations and representative CMS operations, and removes only its exact temporary database, grant, server, media, and fixture artifacts.

## Documentation

- [Administrator Manual Introduction](docs/ADMIN-MANUAL-INTRODUCTION.md)
- [Roadmap](docs/ROADMAP.md)
- [Theme Author Guide](docs/THEME-AUTHOR-GUIDE.md)
- [Theme Activation Readiness](docs/THEME-ACTIVATION-READINESS.md)
- [Acceptance Suite](docs/ACCEPTANCE-SUITE.md)
- [Operational Form Boundary](docs/OPERATIONAL-FORM-BOUNDARY.md)
- [Member Access Direction](docs/MEMBER-ACCESS-DIRECTION.md)
- [Version 5.1 Direction](docs/VERSION-5.1-DIRECTION.md)
- [Security Notes](docs/SECURITY.md)

## Database And Release Safety

- Back up a retained database before migrations or release work.
- Test migrations first against a disposable restored copy.
- Never edit an applied migration.
- Preserve public URL and table-name compatibility unless a separate migration explicitly approves a change.
- Keep the Adriana and portable starter databases, backups, and rollback points independent.
- Review and merge release branches through pull requests; do not publish directly from an unverified dirty worktree.

## License

RED-CMS source headers identify the project as MIT-licensed. Bundled third-party libraries retain their own license terms, including the local TinyMCE compatibility editor.
