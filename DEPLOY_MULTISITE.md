# Deploy SOWWWL multi-domain stack

Use this file when the target is one VPS serving multiple domains through Caddy.

## Scope

This stack covers:

- `sowwwl.cloud` as the hub
- `api.sowwwl.cloud` as the minimal API host
- `0.user.o.sowwwl.cloud` as the SPA shell with fallback routing
- `sowwwl.org` as the public-facing static site
- `0wlslw0.com` as the alternate landing page
- `sowwwl.com` as the PHP product app
- `sowwwl.art` as a static landing alias

## Why this layout

- one VPS
- one reverse proxy
- one app runtime
- one DB
- one place to fix TLS and Cloudflare issues

## Deploy

From the repository root:

```bash
cp deploy/.env.production.example deploy/.env.production
docker compose --env-file deploy/.env.production -f deploy/docker-compose.prod.yml up --build -d
```

## DNS checklist

Point these hosts to the VPS:

- `sowwwl.cloud`
- `www.sowwwl.cloud`
- `api.sowwwl.cloud`
- `0.user.o.sowwwl.cloud`
- `sowwwl.org`
- `www.sowwwl.org`
- `0wlslw0.com`
- `www.0wlslw0.com`
- `sowwwl.com`
- `www.sowwwl.com`
- `sowwwl.art`
- `www.sowwwl.art`

## Cloudflare checklist

1. Make sure the origin is reachable on ports `80` and `443`.
2. Let Caddy issue certificates on the origin.
3. Set Cloudflare SSL mode to `Full (strict)`.
4. If `0wlslw0.com` or `sowwwl.cloud` returns `526`, the origin certificate or routing is still wrong.

## Result

- `sowwwl.cloud` gets a real home page
- `api.sowwwl.cloud` resolves and responds
- `0.user.o.sowwwl.cloud` gets SPA fallback routing
- `sowwwl.org` becomes a real static site
- `0wlslw0.com` gets a valid HTTPS-ready landing
