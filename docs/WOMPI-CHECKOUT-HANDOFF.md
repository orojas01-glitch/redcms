# Wompi Web Checkout handoff (Sandbox)

This sends a customer who chooses Wompi at the store checkout to Wompi's
hosted payment page, then back to a fixed return page. It complements the
webhook in `WOMPI-WEBHOOK-ENDPOINT.md`, which is still the only thing that
can mark an order paid.

## Flow

1. The checkout form lists "Wompi (PSE, Nequi, card)" next to pay on receipt
   when Store Lite's "Offer Wompi Web Checkout" setting is on and the catalog
   currency is COP (Store Lite 0.1.54).
2. On submit, before any order is created, core checks that the handoff can
   work: the server gate, the exact package pair (Store Lite 0.1.54, Wompi
   0.4.0, both enabled and current), a Sandbox public key (`pub_test_…`), the
   integrity key reference, and the canonical HTTPS origin. If any is missing
   the request is refused with the normal "temporarily unavailable" response
   and no order exists.
3. The normal guest-checkout mutation places the order (`wompi_checkout`,
   kind `hosted`, pending).
4. Core reads the visitor's newest order through `commerce.orders`
   `payment.order.handoff` (only a pending Wompi order at most 30 minutes
   old), signs the Web Checkout fields with the package's W1 planner, and
   answers with `{ok, outcome: "wompi_checkout_ready", checkoutUrl,
   navigationMode: "location.assign"}`. Only the integrity key is resolved,
   and only the signature reaches the browser.
5. `js/public-addon-mutation.js` follows the URL only when it is
   `https://checkout.wompi.co/p/` with a Sandbox key, COP, an `ord_…`
   reference, and a `redirect-url` back to this site's
   `/checkout/wompi-return`. It fires a cancelable
   `redcms:wompi-checkout-ready` event first.
6. Wompi sends the customer to `/checkout/wompi-return?id=…&env=test`. The page
   is fixed, no-store, and ignores the query. The order changes only when the
   verified webhook arrives.

The payment link expires after 30 minutes (`expiration-time`), matching the
handoff window.

## Enabling (Sandbox only)

All of these are server-local and default off:

- `WOMPI_CHECKOUT_SANDBOX_ENABLED` (or `RED_WOMPI_CHECKOUT_SANDBOX_ENABLED`)
  is `true` or `1`;
- the public-mutation endpoint is enabled with its trusted HTTPS origin;
- in the Wompi package settings: the Sandbox public key and the integrity key
  reference (the key value itself lives only in server-local secrets);
- in Store Lite settings: "Offer Wompi Web Checkout" is on and the catalog
  currency is COP.

Production keys are refused by design until a later, separately authorized
change.

## Tests

`scripts/addon-wompi-checkout-handoff-self-test.php` (in dev acceptance) covers
the composition, refusals, URL validation, the return page and the browser
controller. Set `RED_WOMPI_PACKAGE_ROOT` to the Wompi package directory to also
check the real W1 planner produces the identical URL.
