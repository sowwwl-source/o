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

It uses one VPS, one Caddy reverse proxy, one PHP app container for the `o/` experience, one MySQL service for messaging/state, static domain sites, and one minimal API compatibility container.

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
- `../../init.sql` - base SQL schema mounted into MySQL on first boot
- `../../migrations/2026_05_02_signal_mail.sql` - Signal mailbox/message schema mounted into MySQL on first boot
- `sites/` - static sites for the hub, org, alternate landing, SPA shell, and temporary product shell

## Prepare

1. Rotate any SSH key that was previously committed to git.
2. Copy `.env.production.example` to `.env.production`.
3. Replace the `CHANGE_ME_*` values, especially `DB_PASS`, `DB_ROOT_PASSWORD`, `SOWWWL_ADMIN_PIN`, `SOWWWL_MAGIC_LINK_SECRET`, and SMTP credentials if Signal identity emails should be delivered.
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
- `upload.sowwwl.com`

`upload.sowwwl.com` should stay DNS-only if you want aZa direct uploads to bypass proxy upload limits.

For large aZa imports, the live `app` image must also carry PHP upload limits compatible with the app-level 2GB ceiling. If `upload.sowwwl.com` still fails after DNS is correct, verify the running container values for `upload_max_filesize` and `post_max_size`, then recreate both `app` and `caddy` so the current image and host blocks are actually live.

## Deploy

From the repository root:

```bash
cp deploy/.env.production.example deploy/.env.production
docker compose -p sowwwl-o --env-file deploy/.env.production -f deploy/docker-compose.prod.yml up --build -d
```

From `o/deploy/` itself:

```bash
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml up --build -d
```

Using an explicit project name avoids clashing with the sibling top-level `deploy/` directory, which would otherwise also default to the Compose project name `deploy`.

On a fresh MySQL volume, both `init.sql` and the Signal mailbox migration are imported automatically.

If the database already exists and predates Signal, apply the migration manually:

```bash
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml exec -T db \
	mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < ../../migrations/2026_05_02_signal_mail.sql
```

Then restart the PHP app:

```bash
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml restart app
```

After deploy, verify at least:

- `https://sowwwl.com/`
- `https://sowwwl.com/0wlslw0`
- `https://sowwwl.com/signal`
- `https://sowwwl.com/str3m`
- `https://sowwwl.com/echo.php`

Expected behavior:

- `0wlslw0` shows the voice guide block when the updated app image is live
- `Signal` shows a mailbox UX, not the old public trace wall
- `Signal` uses the land virtual email and can send an identity verification email
- `Écho` still works and lists contacts from JSON lands, even if SQL `lands` rows are absent
- `sowwwl.xyz` redirects toward `sowwwl.com`

If you want the live app to relay to the DigitalOcean voice agent, also set these variables in `.env.production`:

- `SOWWWL_0WLSLW0_AGENT_ENDPOINT`
- `SOWWWL_0WLSLW0_AGENT_KEY`
- `SOWWWL_0WLSLW0_AGENT_AUTH_HEADER`
- `SOWWWL_0WLSLW0_AGENT_AUTH_SCHEME`
- `SOWWWL_0WLSLW0_AGENT_MODE`
- `SOWWWL_0WLSLW0_AGENT_INPUT_FIELD`
- `SOWWWL_0WLSLW0_AGENT_EXTRA_HEADERS_JSON`

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
- `0wlslw0.com` now points to the live `0wlslw0` guide inside the PHP app, so domain visitors land on the real onboarding experience instead of the old static placeholder.
- Keep `sites/0wlslw0.com/` only as archive/reference material unless you intentionally switch that host back to a static landing.
- Remove any host block from `Caddyfile` if that domain should continue to use another origin.
