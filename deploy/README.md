# Multi-domain VPS stack

This directory adds a production-oriented stack for:

- `sowwwl.cloud`
- `sowwwl.me`
- `api.sowwwl.cloud`
- `0.user.o.sowwwl.cloud`
- `sowwwl.org`
- `0wlslw0.com`
- `0wlslw0.fr`
- `sowwwl.com`
- `sowwwl.art`

It uses one VPS, one Caddy reverse proxy, one PHP app container, one MySQL container,
and one minimal API compatibility container.

## Files

- `docker-compose.prod.yml` - production stack
- `Caddyfile` - domain routing and redirects
- `api/` - minimal AzA API stub with docs and health endpoints
- `sites/` - static sites for the hub, PFVEE layer, org, alternate landing, and SPA shell

## Prepare

1. Rotate any SSH key that was previously committed to git.
2. Copy `.env.production.example` to `.env.production`.
3. Replace all `CHANGE_ME_*` values.
4. Point DNS records at the VPS public IP.
5. Adjust `API_ALLOWED_ORIGINS` if you later add or remove browser-facing hosts.

## DNS records

Required apex records:

- `sowwwl.cloud`
- `sowwwl.me`
- `sowwwl.org`
- `0wlslw0.com`
- `0wlslw0.fr`
- `sowwwl.com`
- `sowwwl.art`

Required subdomain records:

- `www.sowwwl.cloud`
- `www.sowwwl.me`
- `www.sowwwl.org`
- `www.0wlslw0.com`
- `www.0wlslw0.fr`
- `www.sowwwl.com`
- `www.sowwwl.art`
- `api.sowwwl.cloud`
- `0.user.o.sowwwl.cloud`

## Deploy

From the repository root:

```bash
cp deploy/.env.production.example deploy/.env.production
docker compose --env-file deploy/.env.production -f deploy/docker-compose.prod.yml up --build -d
```

## Cloudflare notes

If Cloudflare is enabled:

1. Point the DNS records to the VPS.
2. For the first certificate issuance, use DNS-only mode if needed.
3. Once Caddy has valid origin certificates, switch SSL mode to `Full (strict)`.
4. If you previously had a `526` on `0wlslw0.com` or `sowwwl.cloud`, that error should disappear once the origin is serving a valid certificate and Cloudflare is in `Full (strict)`.

## What the API stub does

The `api.sowwwl.cloud` service now provides:

- `GET /healthz`
- `GET /docs`
- `GET /docs/AzA_v0.7_openapi.min.yaml`
- `GET /v1/status`

Protected write endpoints return `501 not_implemented` with JSON. This is deliberate: the host resolves and responds, but it does not pretend the production AzA service exists yet.

## Customization

- Replace `sites/0.user.o.sowwwl.cloud/` with the real SPA build when it is ready.
- Replace `sites/0wlslw0.com/` with a different landing if needed.
- Remove the `sowwwl.com` and `sowwwl.art` host blocks from `Caddyfile` if those domains should continue to use another origin.
