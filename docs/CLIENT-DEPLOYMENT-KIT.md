# RED-CMS Store Lite Client Deployment Kit

The client deployment kit is a credential-free release archive for installing
RED-CMS 5.1 and Store Lite on a new, isolated client domain. It is not a copy of
`demo.red-sphere.com`: it contains no client database, products, offers, media,
administrator accounts, provider identifiers, or secrets.

## Repository boundary

The kit composes independently versioned sources without moving their code into
the public core repository:

- RED-CMS core `5.1.0` from this repository;
- required Store Lite `0.1.50` from `redcms-store-lite`;
- optional Stripe Checkout `0.1.20` from
  `redcms-store-lite-stripe-checkout`; and
- optional Wompi `0.1.5` from `redcms-store-lite-wompi`.

The exact package revisions and destinations are pinned in
[`release/client-deployment-kit-v1.json`](../release/client-deployment-kit-v1.json).
PayPal remains intentionally refused by this release manifest. Its separate
`0.2.0` offline foundation, exact core profile, disposable enablement,
two-client isolation, and sealed transport contracts are verified, but real
provider transport and Sandbox payment acceptance are not yet complete, so it
is not a client-kit selection.

Adapter `0.1.20` includes the credential-free catalog-Price binding contract
and the bounded transport acceptance for that exact request form. Its prior
`0.1.18` lifecycle evidence remains valid for the unchanged webhook path. The
existing-Price path completed its $59/month activation-and-cancellation
lifecycle on `demo.red-sphere.com`; every future client still requires its own
isolated Sandbox acceptance before live-mode review.

## Build the archive

Use an absolute output path outside all source repositories. Dry-run is the
default:

```sh
/path/to/php scripts/client-deployment-kit.php \
  --output=/private/tmp/redcms-store-lite-client-kit.tar.gz \
  --store-lite-repo=/path/to/redcms-store-lite \
  --stripe-repo=/path/to/redcms-store-lite-stripe-checkout \
  --wompi-repo=/path/to/redcms-store-lite-wompi \
  --adapters=stripe,wompi
```

The planner requires clean tracked source trees, the pinned package commits,
matching manifest identities and versions, every declared package-file hash,
and a passing clean-starter boundary. It prints a plan SHA-256 and performs no
write.

To build, repeat the exact command with the printed confirmation:

```sh
  --confirm-plan-sha256=PRINTED_SHA256 --apply
```

Apply writes a new `.tar.gz` and adjacent `.sha256` only. The archive is built
from tracked Git bytes, scans for credential-value patterns, and contains its
own `RELEASE-EVIDENCE.json` and `SHA256SUMS`. It does not connect to a database,
install or enable a package, contact a provider, or change a client site.

For a Stripe-only or Wompi-only delivery, select just that adapter. Store Lite
is always included:

```sh
--adapters=stripe
--adapters=wompi
```

## New-client sequence

1. Create a separate document root, database, database grant, canonical HTTPS
   origin, administrator, backup location, and client profile.
2. Verify the archive checksum, extract it into the new document root, and keep
   client media and configuration outside the source release.
3. Install RED-CMS and apply current core migrations.
4. Create the first client-local Owner and grant only the Store Lite
   capabilities that client needs.
5. Run package validation, install Store Lite as `installed_disabled`, test its
   migrations against a disposable restored database, then enable it.
6. Install only the selected adapter as `installed_disabled`. Keep provider
   secret references unavailable until the client owns and reviews the target
   provider account.
7. Enter non-secret settings and owner-controlled secret references on the
   server. Never put raw values in the client profile or archive.
8. Enable the adapter, configure its Sandbox webhook/event destination, and
   complete one provider-signed Sandbox lifecycle.
9. Record route, package, database, browser, webhook, and rollback evidence.
10. Treat live-mode enablement as a separate client authorization after the
    Sandbox acceptance report passes.

Start each client profile from
[`client-deployment-profile.v1.example.json`](examples/client-deployment-profile.v1.example.json).
The example is deliberately disabled and Sandbox-only.

## Acceptance boundary

A release archive is ready for client installation only when:

- the core clean-starter test passes;
- the client-kit self-test passes;
- package identities, versions, commits, and integrity inventories match;
- no tracked client identity, data, media, secret value, or local configuration
  enters the archive;
- Store Lite is required but every payment adapter is optional;
- a second fresh database can install and disable the selected packages without
  changing another client database; and
- the release report distinguishes file delivery, installation, Sandbox
  verification, and live activation.

The kit is a distribution mechanism, not provider certification. Provider
readiness and remaining gates are tracked in
[`PAYMENT-PROVIDER-READINESS.md`](PAYMENT-PROVIDER-READINESS.md).

When the configured local starter database is an older retained snapshot, run
the broad acceptance suite through a disposable current-schema anchor:

```sh
scripts/client-deployment-kit-full-acceptance.sh
```

The wrapper imports the portable installer into a uniquely named database,
applies current migrations, runs the full disposable acceptance suite against
that anchor, then revokes the exact temporary grant and removes the anchor. It
also verifies that the configured primary database is unchanged.
