# syntax=docker/dockerfile:1
#
# Isolated proof image for the optional RED-CMS public-mutation ingress
# attestation module. It is deliberately not a production image and is built
# only by the paired proof script/CI workflow.

ARG FRANKENPHP_VERSION=1.12.4

FROM caddy:2.11.4-builder AS xcaddy

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-builder-php8 AS builder

ARG FRANKENPHP_VERSION

COPY --from=xcaddy /usr/bin/xcaddy /usr/bin/xcaddy
COPY integration /opt/redcms/frankenphp-public-mutation-attestation

WORKDIR /go/src/app

# This follows FrankenPHP's documented custom-Caddy-module build boundary.
# The proof keeps only FrankenPHP's PHP/Caddy modules plus the RED-CMS module;
# it is not a replacement for an operator's production module inventory.
RUN CGO_ENABLED=1 \
    XCADDY_SETCAP=1 \
    XCADDY_GO_BUILD_FLAGS="-ldflags='-w -s' -tags=nobadger,nomysql,nopgx" \
    CGO_CFLAGS="$(php-config --includes)" \
    CGO_LDFLAGS="$(php-config --ldflags) $(php-config --libs)" \
    xcaddy build v2.11.4 \
        --output /usr/local/bin/frankenphp \
        --with github.com/dunglas/frankenphp@v${FRANKENPHP_VERSION}=/go/src/app \
        --with github.com/dunglas/frankenphp/caddy@v${FRANKENPHP_VERSION}=/go/src/app/caddy \
        --with github.com/orojas01-glitch/redcms/server-integrations/frankenphp-public-mutation-attestation=/opt/redcms/frankenphp-public-mutation-attestation

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php8 AS proof

COPY --from=builder /usr/local/bin/frankenphp /usr/local/bin/frankenphp
COPY integration/proof/Caddyfile /etc/caddy/Caddyfile
COPY integration/proof/index.php /app/public/index.php
COPY includes/ /app/public/includes/

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/frankenphp"]
CMD ["run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
