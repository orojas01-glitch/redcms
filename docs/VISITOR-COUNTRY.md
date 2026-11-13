# Visitor country (display only)

`red_visitor_country_code()` (`includes/visitor_country_helpers.php`) returns the
visitor's ISO 3166-1 alpha-2 country, or `null` when unknown or switched off.
Add-ons use it to decide what a page shows, for example Store Lite's
"Show Wompi only to visitors detected in Colombia". Never use it to authorize
a payment, price, or permission: VPNs change the apparent country and a header
can be forged when the origin is reachable without the proxy that sets it.

Set it in `includes/config.local.php` (or the `RED_` environment variables):

| `VISITOR_COUNTRY_SOURCE` | Reads | Use when |
| --- | --- | --- |
| `off` (default) | nothing | no country-dependent display |
| `cloudflare` | `CF-IPCountry` header | all traffic reaches the site through Cloudflare (IP Geolocation is on by default) |
| `server-variable` | `GEOIP_COUNTRY_CODE`, `MM_COUNTRY_CODE`, or `VISITOR_COUNTRY_SERVER_VARIABLE` | the web server runs a GeoIP module (mod_geoip, mod_maxminddb) |

No visitor IP is ever sent to an outside service. Cloudflare's `XX` (unknown)
and `T1` (Tor) count as unknown. If pages are cached in front of PHP, make the
cache vary on country or exclude the cart page, or every visitor sees the
first visitor's version.

Check it on the server with `php scripts/visitor-country-self-test.php`.
