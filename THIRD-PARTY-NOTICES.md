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
inventory and review dispositions are in `docs/BUNDLED-DEPENDENCIES.md`.

## Legacy utility follow-up

- Superfish 1.7.10: Copyright (c) 2018 Joel Birch; used under the MIT option.
  The upstream notice is in `docs/licenses/Superfish-MIT.txt` and the source header.
- jQuery Easing 1.3 with a RED-CMS calling-convention adapter: Copyright © 2008
  George McGinley Smith, BSD-3-Clause. See the retained source header and
  `docs/licenses/jquery-easing-BSD-3-Clause.txt`.
- Filedrop compatibility fork: original interface/source lineage by Weixi Yen,
  Copyright (c) 2010 Resopollution, MIT. See `docs/licenses/filedrop-MIT.txt`.
  The complete MIT terms are reproduced from the license cited by those headers.
- The current mobileMenu and legacy-theme initialization are original RED-CMS MIT
  implementations. Historical unlicensed/unattributed implementations were replaced.

Source identities, modifications, maintenance limits and usage are recorded in
`docs/LEGACY-UTILITY-PROVENANCE.md` and `release/legacy-utilities-manifest.json`.
