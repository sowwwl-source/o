# Deploy SOWWWL multi-domain stack

Use this file when the target is one VPS serving multiple domains through Caddy.

## Scope

This stack covers:

- `sowwwl.xyz` as the user ingress app
- `sowwwl.cloud` as the hub
- `api.sowwwl.cloud` as the minimal API host
- `*.o.sowwwl.cloud` as the user host wildcard
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
- `sowwwl.xyz` and `user.o.sowwwl.cloud/0` inherit the approved framing, but keep their user-ingress roles

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
- `sowwwl.io`
- `www.sowwwl.io`
- `sowwwl.cloud`
- `www.sowwwl.cloud`
- `api.sowwwl.cloud`
- `*.o.sowwwl.cloud`
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
4. If `sowwwl.xyz` or `sowwwl.io` returns `525`, the origin handshake is still wrong: verify the host exists in Caddy, port `443` is open, and the origin certificate covers that domain.
5. If `0wlslw0.com`, `sowwwl.cloud`, or `sowwwl.io` returns `526`, the origin certificate or routing is still wrong.
6. If you want Cloudflare to edge-cache the anonymous `https://sowwwl.com/` home, add a Cache Rule with:

```text
(http.host eq "sowwwl.com" and http.request.method in {"GET" "HEAD"} and http.request.uri.path eq "/" and not http.request.uri.query contains "connexion=" and not http.cookie contains "sowwwl_session=")
```

Action:

- `Cache eligibility` -> `Eligible for cache`
- `Edge TTL` -> `Use cache-control header if present, use default Cloudflare caching behavior if not`

This keeps the anonymous shell cacheable while excluding session-backed visits and the explicit login route `/?connexion=1`.

## Result

- `sowwwl.xyz` resolves to the PHP ingress app
- `sowwwl.io` resolves to the spatial ingress app surface
- `sowwwl.cloud` gets a real home page
- `api.sowwwl.cloud` resolves and responds
- `user.o.sowwwl.cloud/` resolves to the user island and `/0` to the membrane chamber
- `sowwwl.org` becomes a real static site
- `0wlslw0.com` gets a valid HTTPS-ready landing
