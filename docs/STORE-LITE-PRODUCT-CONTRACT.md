# Store Lite Product and Variant Contract

Status: Gate 2A accepted as a package-contract fixture on 2026-08-07 and now
implemented by the separately distributed Store Lite package. This document
continues to fix the first product/variant bounds; it is not a core commerce
implementation.

Store Lite remains an optional separately deployed package. The contract is
kept in the RED-CMS repository so the package, its migrations, and its
disposable acceptance fixtures can be reviewed against one exact boundary.
The clean starter still contains no Store Lite directory, table, migration
row, setting, media, registry record, cart, order, or business data.

## Product paths

The first package has two mutually exclusive product paths:

- **Simple** — one sellable SKU, one integer minor-unit price, one availability
  state, and optional stock. A banana sold by unit or pack uses this path.
- **Variable** — one product parent with bounded option groups and explicit
  sellable variants. A T-shirt can define Size and Color, then list each
  sellable combination with its own SKU, price, availability, image, and stock.

The Product component references the package product parent. It does not own
catalog, inventory, cart, order, or payment state. A variable product must
resolve one current variant before a cart mutation is accepted.

## Fixed bounds

| Value | Contract |
| --- | --- |
| Product and variant identifier | lowercase ASCII identifier, first character a letter, then `[a-z0-9._-]`, maximum 64 bytes |
| SKU | uppercase ASCII identifier, first character a letter or digit, then `[A-Z0-9._-]`, maximum 64 bytes |
| Title | valid UTF-8 text, 1–160 characters, no control characters |
| Summary | optional valid UTF-8 text, 1–1,000 characters when present |
| Currency | one installation currency, uppercase ISO-style three-letter code; the first fixture uses `USD` |
| Price | integer minor units from `0` through `999,999,999`; floats and numeric strings are refused |
| Stock | optional integer from `0` through `1,000,000,000` |
| Option groups | 1–3 per variable product |
| Values per option group | 1–16; each value has a unique lowercase identifier and bounded label |
| Explicit variants | 1–128 per variable product |
| Image reference | optional `media:` reference with a bounded lowercase identifier |
| State | `draft`, `published`, or `archived` |
| Availability | `available` or `unavailable` |

These limits are package limits, not a request to add generic commerce
columns to RED-CMS. A later package version may propose a new versioned
contract, but it must not silently widen the existing one.

## Normalized record shape

The package normalizes a product to the following closed shape before writing
its own tables:

```text
id, type, title, summary, currency, state, availability, imageRef,
sku, priceMinor, stock, options, variants
```

For a simple product, `sku` and `priceMinor` are required, `stock` is
optional, and `options`/`variants` are empty. For a variable product, the
parent has no `sku`, `priceMinor`, or `stock`; every variant carries those
sellable values. The parent and every variant still carry a validated
availability state.

Each variable option group has only:

```text
key, label, values[{id, label}]
```

Each explicit variant has only:

```text
id, sku, options{group-key: value-id}, priceMinor,
availability, stock, imageRef
```

Every declared option group must appear exactly once in every variant. A
variant value must belong to its declared group. Variant identifiers, SKUs,
and the complete option tuple must be unique within the product parent. The
package may publish a subset of the mathematical option matrix; it may not
accept an unbounded or implicit matrix.

The normalized result is discarded on any error. Unknown fields, duplicate
keys, malformed text, mismatched installation currency, non-canonical money,
missing option groups, duplicate tuples, stale option values, and parent/child
field mixing fail closed before a package write.

## Persistence ownership

The package owns namespaced tables including:

- `RED_Addon_StoreLite_Products` for the product parent and publish state;
- `RED_Addon_StoreLite_Product_Options` and
  `RED_Addon_StoreLite_Product_Option_Values` for bounded choices; and
- `RED_Addon_StoreLite_Product_Variants` for explicit sellable combinations;
  and
- `RED_Addon_StoreLite_Product_Placements` for the exact numeric relationship
  between one core Product component parent and one package product parent.

Every table is package-owned, InnoDB, migration-backed, and independently
installed in the adopting client's database. The placement table has one row
per core parent and restrictive foreign keys to both `RED_Articles.RecordID`
and the package Product `RecordID`; neither side can be removed while that
relationship exists. Core does not select these tables or store their business
fields.

Cart lines and order lines are later package tables. Order creation must copy
the selected title, option labels, SKU, integer price, currency, and quantity
into an immutable order-line snapshot; later product edits cannot rewrite
history.

Store Lite 0.1.10 provides a pure public presenter that re-normalizes this
exact record before producing title, summary, price, effective availability,
and bounded option-label facts for the core-owned default renderer. Store Lite
0.1.11 adds the package-owned relationship plus exact loader/creator/writer/
deleter callbacks and the runtime handler that reloads the bound product before
calling that presenter. The handler returns only the closed view model; it does
not emit HTML or modify cart, order, inventory, or payment state.

## Server and mutation rules

- The browser sends only a public product reference, bounded quantity, and an
  optional public variant reference for a variable product.
- The commerce service resolves the current product, option tuple, price,
  currency, availability, stock, and cart ownership server-side.
- A missing, stale, unavailable, or mismatched variant is refused.
- Browser-submitted price, currency, stock, totals, and option labels are never
  authoritative.
- The existing generic public-mutation declaration remains non-executing and
  does not create a Store Lite route, cookie, cart, or database state.
- Pay-on-receipt is the first checkout mode. Hosted payment providers remain
  separate provider-neutral adapters.

## Acceptance evidence

`scripts/store-lite-product-contract-self-test.php` is a dependency-free
20-assertion fixture. It proves:

- simple banana-style normalization;
- variable Size/Color normalization with four explicit variants;
- integer money and one installation currency;
- unique identifiers, SKUs, and option tuples;
- the three-group, 16-value, and 128-variant bounds; and
- fail-closed refusal without a partial normalized record.

The fixture performs no database, filesystem, request, package, lifecycle, or
runtime work. A later Store Lite package must repeat these rules inside its own
installer, editor, catalog service, and disposable client database. This gate
does not authorize package enablement.
