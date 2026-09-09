# Third-party notices

## TinyMCE 8.9.0

Copyright (c) 2025 Ephox Corporation DBA Tiny Technologies, Inc.

This RED-CMS build uses the self-hosted core under **GPL-2.0-or-later**, with
`license_key: 'gpl'`. No paid plugins or Tiny Cloud subscription are enabled.
The upstream notice remains at `admin/assets/js/tiny_mce/license.md` and the
GPL version 2 text is provided in `docs/licenses/GPL-2.0.txt`.

Official upstream source: https://github.com/tinymce/tinymce/tree/8.9.0
Corresponding source archive: https://github.com/tinymce/tinymce/archive/refs/tags/8.9.0.tar.gz
Package source: https://registry.npmjs.org/tinymce/-/tinymce-8.9.0.tgz

`release/tinymce-vendor-manifest.json` records registry integrity and every
bundled file hash. Both minified and unminified package files are retained.
When distributing to clients, include the applicable notices/license and arrange
GPL-compliant corresponding-source delivery. Retain the exact upstream source
archive with the private release materials; do not treat a minified script alone
as the complete preferred source. Do not include client data or credentials.

Existing RED-CMS MIT source-header notices are preserved. This upgrade does not
assign a new blanket license to separately distributed add-ons or client content.
Other bundled components retain their existing copyright/license headers; their
inventory and remaining provenance gaps are in `docs/BUNDLED-DEPENDENCIES.md`.
