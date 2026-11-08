# Bundled dependency review — 5.1.2 candidate

Inventory and advisory query date: 2026-09-09 UTC. No package manager lockfile
previously described the hand-bundled runtime. The CycloneDX inventory at
`release/bundled-dependencies.cdx.json` contains only public package coordinates;
OSV is queried with this file, never the repository or client configuration.

| Library | Verified bundled version / surface | Candidate result |
| --- | --- | --- |
| TinyMCE | 8.9.3, `admin/assets/js/tiny_mce/` | Patch upgrade from 8.9.0 resolves GHSA-mf2p-h6hf-fcwm (High, media plugin stored XSS); local checks pass |
| jQuery | 3.7.1, `js/jquery-3.7.1.min.js` | No OSV matches returned in this query |
| Bootstrap | 5.3.3, `js/bootstrap*` and `css/bootstrap*` | No OSV matches returned in this query |
| PHPMailer | 6.9.3, `bin/phpmailer.php`, `bin/SMTP.php`, `bin/Exception.php` | No OSV matches returned in this query; SMTP delivery remains unverified |
| Filedrop compatibility fork | RED-CMS 1.0.0, three standalone administrator APIs | MIT lineage reviewed; instance/transport failures corrected and tested |
| jQuery easing | 1.3 plus RED-CMS calling adapter | Exact upstream BSD source verified; jQuery 3 compatibility repaired; not core-loaded |
| Superfish | 1.7.10, official tagged source plus startup hook | MIT; no OSV matches returned; dormant upstream, local regression coverage |
| mobilemenu and tm-scripts | Original RED-CMS 1.0.0 compatibility implementations | Replaced historical code with missing license/provenance; browser tests pass |

No OSV match is not proof of support, exploit absence or a complete dependency
inventory. Independently distributed add-ons/adapters are not bundled core
libraries and must be checked in their own repositories.

## TinyMCE decision and compatibility boundary

The owner confirmed RED-CMS is open source and selected the free self-hosted core.
TinyMCE 8.9.3 now runs in GPL mode; existing MIT notices in RED-CMS source remain.
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


## Legacy utilities review completed locally

See [provenance, corrections and limitations](LEGACY-UTILITY-PROVENANCE.md).
All seven scoped files now have an explicit source/license/ownership disposition,
pinned hashes and regression coverage. The source-only/local implementations are
not falsely described as npm packages or as covered by the five-coordinate OSV
query. Dormant upstream maintenance, physical-browser verification and client
integration-slot usage remain explicit limitations, not unperformed provenance work.
