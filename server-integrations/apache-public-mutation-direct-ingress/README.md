# Apache Direct-PHP Public-Mutation Proof

This directory contains only a disposable proof for the explicit RED-CMS
`direct_php` ingress profile. It is not a production Apache configuration,
HostGator deployment, Store Lite package, client database, or replacement for
the clean starter's deployment-neutral rules.

The paired script stages only this proof and the exact direct-ingress PHP
dependency set into a temporary server root:

```sh
scripts/apache-public-mutation-direct-ingress-proof.sh
```

It runs the host's stock Apache with PHP FastCGI over a generated localhost
certificate and checks the real `cgi-fcgi` projection. The accepted case proves direct
HTTPS, canonical `Origin`, form content metadata, one opaque subject cookie,
CSRF, idempotency, and measured body bytes reach the existing core capture.
Refusal cases cover HTTP with forged forwarding, duplicate Origin/CSRF/cookie,
and content encoding. Chunk framing must be removed by Apache and reach PHP as
the same bounded body with a measured content length. A valid HTTPS request
with a forged Host and forwarding value must still pass because those values
are ignored.

The same isolated server supplies fixed desktop and mobile HTTPS page evidence
without a cookie, dispatcher link, endpoint mutation, package execution, or
client-state change. Non-secret runtime, request-projection, certificate, and
browser evidence may be retained outside the starter. The certificate private
key, Apache server root, and FastCGI process are removed.

Passing this proof establishes compatibility with the reviewed Apache/FastCGI
shape only. A hosted installation must still confirm its actual Apache/PHP
versions, SAPI, direct TLS ownership, PHP projection, configuration boundary,
and separate client database before the endpoint or Store Lite is enabled.
