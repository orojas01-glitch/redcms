# Store Lite Server-Authoritative Cart-Line Contract

Status: Gate 2B accepted as a dependency-free package-contract fixture on
2026-08-08 and implemented by the separately distributed Store Lite 0.1.12
package. This is not a core cart implementation.

Store Lite remains optional and separately deployed. RED-CMS keeps this
contract and its disposable fixture so the package can be reviewed against one
exact boundary without placing Store Lite code, tables, routes, settings,
business data, or an enabled registry record in the clean starter.

## Browser declaration

A future add-to-cart request may declare only:

```text
product, quantity, optional variant
```

- `product` is a lowercase public product identifier up to 64 bytes.
- `quantity` is an integer from 1 through 100.
- `variant` is absent for a simple product and required for a variable product.
- Unknown fields—including SKU, price, currency, stock, option labels, and
  totals—are refused.

## Server authority

The package caller loads the complete current product from the current client
database and supplies the installation currency. The resolver repeats the
Gate 2A product normalization before it derives any commerce value.

The current product must be `published`, `available`, and in the installation
currency. A simple product resolves its own SKU, integer price, and optional
stock. A variable product resolves one exact current explicit variant and
derives its selected option labels from the normalized parent. Missing, stale,
mismatched, unavailable, or insufficient-stock selections fail closed.

## Closed internal line

One successful resolution contains only:

```text
productId, variantId, sku, title, optionLabels, quantity,
unitPriceMinor, currency, lineTotalMinor, stockTracked,
stockAvailable, productStateSha256
```

The line total is integer minor-unit multiplication with explicit overflow
refusal and a fixed maximum of `99,999,999,900`. The state SHA-256 binds the
line to the exact normalized server product used for resolution. Every refusal
returns `line: null`; no partial SKU, price, currency, stock, label, or total is
released.

This is an internal package result. A future public mutation dispatcher must
still map it to the fixed generic response contract and must not expose
commercial or inventory internals unnecessarily.

## Deliberate exclusions

Gate 2B does not:

- open a database or read request, session, or cookie state;
- register or invoke `commerce.cart`;
- create a cart identity, cart table, or cart line;
- reserve or decrement inventory;
- add an add-to-cart control or public route;
- emit a response, cookie, or header;
- change package enablement; or
- create an order, checkout, or payment behavior.

Cart persistence requires separate package-owned migrations, ownership and
locking rules, exact transaction postconditions, mutation-runner integration,
CSRF/idempotency/replay proof, and desktop/mobile browser acceptance.

## Acceptance evidence

`scripts/store-lite-cart-line-contract-self-test.php` is dependency-free and
runs before database creation. It proves:

- server-derived simple banana and variable Size/Color T-shirt lines;
- integer quantity, unit-price, and total bounds;
- exact variant and declared-order option-label resolution;
- tracked and untracked stock behavior;
- product-state hash changes after a current server price edit;
- refusal of browser-owned commercial fields;
- draft, unavailable, currency-drifted, malformed, stale-variant, and
  insufficient-stock refusal; and
- one closed line shape or no line at all.

The separately distributed package repeats this contract in
`RED_CMS_Store_Lite_Cart_Line_Resolver` and its own 26-assertion fixture.
