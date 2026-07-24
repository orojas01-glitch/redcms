# RED-CMS Edition Profiles

RED-CMS 5.0 is maintained in two separate profiles. They share the CMS core, migrations, administrator workspace, and theme contract, but they do not share content databases.

## Adriana Site Profile

Purpose: the continuing Adriana Granobles website.

- Uses the `adriana-granobles` standard theme package.
- Keeps its imported 28-route, pagewise editable content database.
- May receive real Adriana content and design changes.
- Must never be overwritten from the portable starter seed.
- Must not be deleted by disposable acceptance cleanup.
- Must be backed up as its own site before migration, activation, or release work.

The currently attached local test instance is served separately on `http://127.0.0.1:8060/`. Its generated database name is runtime-specific and should be discovered from `scripts/adriana-disposable-runtime.sh status`, not hard-coded into application configuration or documentation.

Although the original migration tooling calls this a disposable runtime, this attached Adriana instance is now retained by project decision. Future destructive cleanup requires an explicit database-name check, a fresh backup, and user approval.

## Portable Starter Profile

Purpose: the reusable RED-CMS distribution for a new website or template installation.

- Uses the `starter-reference` standard theme package.
- Keeps generic starter content and installation defaults only.
- Must not contain Adriana client content, routes, identity assets, or database records.
- Remains the portable reference for theme authors, clean installations, documentation, and acceptance tests.
- May create temporary databases only through the guarded disposable acceptance workflow.

The current local starter administrator is served separately on `http://127.0.0.1:8055/admin/`.

## Shared-Code Rule

Core fixes should normally work in both profiles. Site-specific presentation and content belong to the corresponding theme package and database.

Before a change:

1. Identify the target profile.
2. Record the exact database and active theme.
3. Back up the retained database when a write or migration is involved.
4. Run shared contract tests.
5. Verify the target profile in the browser.
6. Verify that the other profile was not modified.

Do not copy a full database from one profile over the other. Portable starter releases and Adriana site releases should use separate backups, deployment records, and rollback points.
