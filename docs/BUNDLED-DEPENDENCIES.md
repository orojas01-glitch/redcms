# Bundled dependency review — 5.1.2 candidate

Inventory and advisory query date: 2026-09-09 UTC. No package manager lockfile
previously described the hand-bundled runtime. The CycloneDX inventory at
`release/bundled-dependencies.cdx.json` contains only public package coordinates;
OSV is queried with this file, never the repository or client configuration.

| Library | Verified bundled version / surface | Candidate result |
| --- | --- | --- |
| TinyMCE | 8.9.0, `admin/assets/js/tiny_mce/` | Local GPL core upgrade verified; no OSV matches returned |
| jQuery | 3.7.1, `js/jquery-3.7.1.min.js` | No OSV matches returned in this query |
| Bootstrap | 5.3.3, `js/bootstrap*` and `css/bootstrap*` | No OSV matches returned in this query |
| PHPMailer | 6.9.3, `bin/phpmailer.php`, `bin/SMTP.php`, `bin/Exception.php` | No OSV matches returned in this query; SMTP delivery remains unverified |
| jQuery filedrop | 0.1.0 banner, three administrator copies | Legacy utility; no verified package coordinate or maintained upstream pin |
| jQuery easing | 1.3 banner | Legacy utility; separate source/compatibility review pending |
| mobilemenu, superfish, tm-scripts | Hand-bundled public utilities | Complete upstream version/provenance unresolved; excluded from OSV coverage claims |

No OSV match is not proof of support, exploit absence or a complete dependency
inventory. Independently distributed add-ons/adapters are not bundled core
libraries and must be checked in their own repositories.

## TinyMCE decision and compatibility boundary

The owner confirmed RED-CMS is open source and selected the free self-hosted core.
TinyMCE 8.9.0 now runs in GPL mode; existing MIT notices in RED-CMS source remain.
The earlier TinyMCE 4.0.12 payload was replaced, resolving the 19 advisory matches
in the package-coordinate scan. Nothing was suppressed to produce the clean result.

See [local upgrade evidence and rollback](TINYMCE-8-UPGRADE.md) and
[third-party license/source notices](../THIRD-PARTY-NOTICES.md). All six production
configurations and desktop/mobile synthetic browser checks pass. Complete client
content/CSP compatibility and remote CI remain deployment-stage verification.

Official references: [current GPL mode](https://www.tiny.cloud/docs/tinymce/latest/license-key/),
[4-to-8 migration](https://www.tiny.cloud/docs/tinymce/latest/migration-from-4x-to-8x/).
The supported editor's built-in skin changes; no CMS content-editor redesign was
performed. The free core, local assets and existing persistence paths are retained.
