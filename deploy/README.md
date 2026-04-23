# Multi-domain VPS stack

This directory adds a production-oriented stack for:

- `sowwwl.xyz`
- `sowwwl.cloud`
- `api.sowwwl.cloud`
- `0.user.o.sowwwl.cloud`
- `sowwwl.org`
- `0wlslw0.com`
- `sowwwl.com`
- `sowwwl.art`

It uses one VPS, one Caddy reverse proxy, one light PHP app container for `sowwwl.xyz`, static domain sites, and one minimal API compatibility container.

`sowwwl.xyz` is no longer deployed through Cloudflare Workers or Wrangler.

## Domain promotion model

- `sowwwl.org` is the validation surface: approve wording, structure, and domain hierarchy there first
- `sowwwl.cloud` is the canonical hub: promote the approved frame there once it is validated
- `sowwwl.xyz` and `0.user.o.sowwwl.cloud` remain user-entry surfaces that follow the approved frame

## Files

- `docker-compose.prod.yml` - production stack
- `Caddyfile` - domain routing and redirects
- `api/` - minimal AzA API stub with docs and health endpoints
- `app/` - PHP runtime image for `sowwwl.xyz`
- `sites/` - static sites for the hub, org, alternate landing, SPA shell, and temporary product shell

## Prepare

1. Rotate any SSH key that was previously committed to git.
2. Copy `.env.production.example` to `.env.production`.
3. Replace the `CHANGE_ME_*` values.
4. Point DNS records at the VPS public IP.

## DNS records

Required apex records:

- `sowwwl.xyz`
- `sowwwl.cloud`
- `sowwwl.org`
- `0wlslw0.com`
- `sowwwl.com`
- `sowwwl.art`

Required subdomain records:

- `www.sowwwl.xyz`
- `www.sowwwl.cloud`
- `www.sowwwl.org`
- `www.0wlslw0.com`
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

Do not run `wrangler deploy` for `sowwwl.xyz`; Cloudflare should proxy the VPS origin instead.

## Cloudflare notes

If Cloudflare is enabled:

1. Point the DNS records to the VPS.
2. For the first certificate issuance, use DNS-only mode if needed.
3. Once Caddy has valid origin certificates, switch SSL mode to `Full (strict)`.
4. If `sowwwl.xyz` shows a `525`, the origin handshake is still failing. Verify the host exists in Caddy, the origin is reachable on `443`, and the origin certificate matches the domain.
5. If you previously had a `526` on `0wlslw0.com` or `sowwwl.cloud`, that error should disappear once the origin is serving a valid certificate and Cloudflare is in `Full (strict)`.

## What the API stub does

The `api.sowwwl.cloud` service now provides:

- `GET /healthz`
- `GET /docs`
- `GET /docs/AzA_v0.7_openapi.min.yaml`
- `GET /v1/status`

Protected write endpoints return `501 not_implemented` with JSON. This is deliberate: the host resolves and responds, but it does not pretend the production AzA service exists yet.

## Customization

- Replace `sites/sowwwl.com/` with the real product origin or change the `sowwwl.com` host block back to a reverse proxy.
- Replace `sites/0.user.o.sowwwl.cloud/` with the real SPA build when it is ready.
- Replace `sites/0wlslw0.com/` with a different landing if needed.
- Remove any host block from `Caddyfile` if that domain should continue to use another origin.
