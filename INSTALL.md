# RED-CMS 5.1 Clean Installation

This package installs RED-CMS with the portable `starter-reference` theme and
generic starter content. It does not contain a client theme, client media, or a
client database.

## Requirements

- PHP 8.2 or newer with `mysqli`
- MySQL 8 or a compatible MariaDB release
- A web server whose document root is this directory
- HTTPS for production

## Install

1. Create an empty database and a database user limited to that database.
2. Import `db-structure.sql`.
3. Copy `includes/config.local.example.php` to
   `includes/config.local.php`.
4. Enter the database host, port, database name, user, and password in the
   local configuration file.
   Leave the optional legacy mail and PayPal values empty unless that
   compatibility path is deliberately configured for this installation.
5. Run the pending migrations described in
   `docs/DATABASE-MIGRATIONS.md`.
6. Replace the disabled starter administrator password hashes before the first
   login. Do not enable a shared default password.
7. Serve the project and open `/admin/`.
8. Immediately verify the site URL, administrator identity, email settings,
   uploads directory permissions, and HTTPS configuration.

`includes/config.local.php` is ignored by Git and must never be included in a
release archive.

The package includes empty `images/articles` and `images/gallery` boundaries.
Keep their local `.gitignore` files in place so installation-owned uploads do
not enter a starter release.

The tracked `.htaccess` contains portable routing and configuration-file
protection only. Add domain redirects, hosting-account PHP handlers, and other
provider-specific rules in the individual installation; do not commit them to
the starter distribution.

## Verify

From the project root:

```bash
php scripts/clean-starter-boundary-self-test.php
scripts/dev-php-lint.sh
php scripts/theme-contract-self-test.php
scripts/dev-acceptance.sh
```

The acceptance runner uses a uniquely named disposable database and refuses to
operate on the configured primary database.

## Upgrade Safety

Back up the database and uploaded media before applying a release. Test the
backup and every migration against a disposable restore first. Never import
`db-structure.sql` over an existing site.
