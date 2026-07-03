# Local Dev PHP Runtime

Date: 2026-07-03

## Install Locations

Two local PHP runtimes are installed outside the web root under:

`/Users/oscarrojas/Documents/red-cms-dev`

### Syntax Linting

Conda-forge PHP CLI:

`/Users/oscarrojas/Documents/red-cms-dev/php-8.5.8/bin/php`

Version:

`PHP 8.5.8`

This runtime is used for syntax checks. It does not include `mysqli`, so it is not used for MySQL-backed browser smoke tests.

### Local Web Runtime

FrankenPHP:

`/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp`

Version:

`FrankenPHP v1.12.4 PHP 8.5.8 Caddy v2.11.4`

The downloaded binary checksum was verified against the GitHub release metadata:

`sha256:41b4ca9fee1c766125c11d6e0ec5287bac7e4859175a54c93f2f1a51f12ea6c1`

This runtime includes `mysqli`, `mysqlnd`, and `pdo_mysql`, so it can run the CMS against the local MySQL dev database.

## Commands

Run PHP syntax checks from the project root:

```bash
scripts/dev-php-lint.sh
```

Start the local PHP web server from the project root:

```bash
scripts/dev-php-server.sh
```

By default the server listens on:

`http://127.0.0.1:8055`

Use another port if needed:

```bash
PORT=8056 scripts/dev-php-server.sh
```

## Notes

These runtimes are local development tooling only. They do not change public URLs, database table names, or the CMS deployment model.

