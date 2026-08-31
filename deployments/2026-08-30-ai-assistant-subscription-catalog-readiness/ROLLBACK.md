# Rollback

1. Set the public subscription Checkout enable flag to false.
2. Leave the webhook endpoint enabled long enough to acknowledge any already
   created sandbox subscription events, unless incident containment requires an
   immediate stop.
3. Disable adapter `0.1.19` through the non-destructive package lifecycle.
4. Restore the pre-deployment core files and adapter package from the captured
   filesystem backup, then reconcile registry and migration evidence.
5. Remove only the `SUBSCRIPTION_STRIPE_CATALOG_BINDING` key that was added to
   server-local configuration; preserve all unrelated keys and secrets.
6. Unpublish or unplace the AI Assistant subscription component. Retain Product,
   offer, intent, lifecycle, and receipt rows for evidence unless an independent
   purge is explicitly approved.
7. Verify the site, return routes, webhook response behavior, package state,
   database fingerprint, and browser console after restoration.

Stripe Product, Price, webhook destination, restricted key, test subscriptions,
and sandbox deletion are independent provider actions. Do not archive, expire,
roll, cancel, or delete them as part of a filesystem rollback without separate
approval.
