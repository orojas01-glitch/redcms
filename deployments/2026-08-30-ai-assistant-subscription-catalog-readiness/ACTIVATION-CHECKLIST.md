# Future Activation Checklist

## Release gate

1. Review and merge the core and adapter rollback branches independently.
2. Rebuild the credential-free client kit with Store Lite `0.1.50` and Stripe
   adapter `0.1.19`.
3. Run adapter aggregate tests, core focused subscription tests, full disposable
   acceptance, manifest checksums, credential scans, and cleanup verification.
4. Capture current hosted files, database backup, registry/settings state, and
   browser evidence before deployment.

## Dark deployment

1. Deploy the reviewed core helpers and adapter `0.1.19` package.
2. Upgrade the adapter through the normal owner-authorized lifecycle.
3. Merge only the catalog-binding key into ignored `config.local.php`.
4. Create or review the Store Lite Product and subscription offer from
   `store-lite-offer.json`.
5. Place the Subscription component but keep the public Checkout gate disabled.
6. Verify package inventories, database migrations, return routes, webhook
   endpoint, and responsive rendering without provider contact.

## Sandbox activation

1. Reconfirm the Stripe sandbox account, Product, Price, destination, five
   subscribed event types, and restricted-key scope without revealing values.
2. Enable the Sandbox Checkout gate and authorization hash server-locally.
3. Verify the public button renders the expected title and USD 59/month amount.
4. At the separately approved action step, create one Checkout Session and
   navigate to Stripe-hosted Checkout.
5. Complete one Stripe test subscription, then verify `200` delivery for
   Checkout completion and paid invoice plus active Store Lite entitlement.
6. Cancel that test subscription and verify the cancellation delivery and
   Store Lite cancellation state.
7. Disable the public Sandbox gate unless an ongoing public demo is explicitly
   approved.

## Explicit exclusions

No live key, live Product/Price, real charge, real customer data, `$800` setup
charge, tax configuration, automatic provisioning, production customer portal,
refund, dispute, or deployment to `red-sphere.com` is authorized by this kit.
