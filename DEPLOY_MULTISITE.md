# Deploy SOWWWL multi-domain stack

Use this file when the target is one VPS serving multiple domains through Caddy.

## Scope

This stack covers:

- `sowwwl.xyz` as the user ingress app
- `sowwwl.cloud` as the hub
- `api.sowwwl.cloud` as the minimal API host
- `0.user.o.sowwwl.cloud` as the SPA shell with fallback routing
- `sowwwl.org` as the public-facing static site
- `0wlslw0.com` as the alternate landing page
- `sowwwl.com` as a temporary product shell

## Why this layout

- one VPS
- one reverse proxy
- one light PHP runtime for `sowwwl.xyz`
- no DB dependency
- one place to fix TLS and Cloudflare issues
- no Wrangler or Cloudflare Worker deployment for `sowwwl.xyz`

## Promotion flow

- `sowwwl.org` is the validation layer for copy, structure, and domain roles
- `sowwwl.cloud` becomes the canonical hub only after that frame is approved
- `sowwwl.xyz` and `0.user.o.sowwwl.cloud` inherit the approved framing, but keep their user-ingress roles

## Deploy

From the repository root:

```bash
cp deploy/.env.production.example deploy/.env.production
docker compose --env-file deploy/.env.production -f deploy/docker-compose.prod.yml up --build -d
```

Do not deploy `sowwwl.xyz` with `wrangler deploy`. The `.xyz` host now runs from the VPS stack in `deploy/`.

## DNS checklist

Point these hosts to the VPS:

- `sowwwl.xyz`
- `www.sowwwl.xyz`
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

## Cloudflare checklist

1. Make sure the origin is reachable on ports `80` and `443`.
2. Let Caddy issue certificates on the origin.
3. Set Cloudflare SSL mode to `Full (strict)`.
4. If `sowwwl.xyz` returns `525`, the origin handshake is still wrong: verify the host exists in Caddy, port `443` is open, and the origin certificate covers `sowwwl.xyz`.
5. If `0wlslw0.com` or `sowwwl.cloud` returns `526`, the origin certificate or routing is still wrong.

## Result

- `sowwwl.xyz` resolves to the PHP ingress app
- `sowwwl.cloud` gets a real home page
- `api.sowwwl.cloud` resolves and responds
- `0.user.o.sowwwl.cloud` gets SPA fallback routing
- `sowwwl.org` becomes a real static site
- `0wlslw0.com` gets a valid HTTPS-ready landing
