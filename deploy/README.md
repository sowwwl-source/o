# Multi-domain VPS stack

This directory adds a production-oriented stack for:

- `sowwwl.xyz`
- `sowwwl.io`
- `sowwwl.cloud`
- `api.sowwwl.cloud`
- `*.o.sowwwl.cloud`
- `sowwwl.org`
- `0wlslw0.com`
- `sowwwl.com`
- `sowwwl.art`

It uses one VPS, one Caddy reverse proxy, one PHP app container for the `o/` experience, one MySQL service for messaging/state, static domain sites, and one minimal API compatibility container.

`sowwwl.xyz` is no longer deployed through Cloudflare Workers or Wrangler.
`sowwwl.io` should follow the same VPS path when you are ready to expose the spatial surface publicly.

## Domain promotion model

- `sowwwl.org` is the validation surface: approve wording, structure, and domain hierarchy there first
- `sowwwl.cloud` is the canonical hub: promote the approved frame there once it is validated
- `sowwwl.xyz` and `user.o.sowwwl.cloud/0` remain user-entry surfaces that follow the approved frame

## Files

- `docker-compose.prod.yml` - production stack
- `Caddyfile` - domain routing and redirects
- `api/` - minimal AzA API stub with docs and health endpoints
- `app/` - PHP runtime image for `sowwwl.xyz`
- `../init.sql` - base SQL schema mounted into MySQL on first boot
- `../migrations/003_echoes_notification.sql` - Echo notifications mounted into MySQL on first boot
- `../migrations/004_liaisons_ports.sql` - liaison/p0rt schema mounted into MySQL on first boot
- `../migrations/005_flows.sql` - fl0w schema mounted into MySQL on first boot
- `../migrations/2026_05_02_signal_mail.sql` - Signal mailbox/message schema mounted as `006_signal_mail.sql` on first boot
- `../migrations/007_query_indexes.sql` - additive query indexes mounted into MySQL on first boot
- `sites/` - static sites for the hub, org, alternate landing, and temporary product shell

## Prepare

1. Rotate any SSH key that was previously committed to git.
2. Copy `.env.production.example` to `.env.production`.
3. Replace the `CHANGE_ME_*` values, especially `DB_PASS`, `DB_ROOT_PASSWORD`, `AZA_API_TOKEN`, `SOWWWL_MAGIC_LINK_SECRET`, and SMTP credentials if Signal identity emails should be delivered.
4. Prefer `SOWWWL_ADMIN_PIN_HASH` over `SOWWWL_ADMIN_PIN` when password login must stay enabled, and keep `SOWWWL_TRUSTED_PROXY_CIDRS` aligned with the real proxy path.
4. Point DNS records at the VPS public IP.

Keep `SOWWWL_MEMBRANE_BRIDGE_URL` and `SOWWWL_PLASMA_FEED_URL` empty unless you intentionally want the browser membrane/plasma flow to cross origins.
Only set `SOWWWL_PLASMA_ALLOWED_ORIGINS` when that cross-origin routing is deliberate.
The production deploy helper refuses lab-facing plasma overrides unless you pass `--allow-cross-origin-plasma`.

## DNS records

Required apex records:

- `sowwwl.xyz`
- `sowwwl.io`
- `sowwwl.cloud`
- `sowwwl.org`
- `0wlslw0.com`
- `sowwwl.com`
- `sowwwl.art`

Required subdomain records:

- `www.sowwwl.xyz`
- `www.sowwwl.io`
- `www.sowwwl.cloud`
- `www.sowwwl.org`
- `www.0wlslw0.com`
- `www.sowwwl.com`
- `www.sowwwl.art`
- `api.sowwwl.cloud`
- `*.o.sowwwl.cloud`
- `upload.sowwwl.com`

`upload.sowwwl.com` should stay DNS-only if you want aZa direct uploads to bypass proxy upload limits.
If you also publish the bare helper host, point `o.sowwwl.cloud` at the same edge and keep it as a redirect only.

Wildcard user hosts are handled by the PHP app itself:

- `user.o.sowwwl.cloud/` redirects to `island?u=user`
- `user.o.sowwwl.cloud/0` opens the membrane / camera chamber for that user

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

Before mutating the live containers, prefer the safe preflight:

```bash
cd /root/O_installation_FRESH/o
bash scripts/deploy_prod_update.sh --preflight-only
```

That path validates the served static-sites directory, checks the Compose config, and builds `app` + `api` without restarting the live stack.

## LAN smoke test on a Pi before public cutover

If the Pi is ready locally but DNS / router / tunnel work is not finished yet, you can expose the first instance on the LAN first:

```bash
cd deploy
cp .env.production .env.lan
```

Then set at least:

```dotenv
SOWWWL_PUBLIC_ORIGIN=http://192.168.1.36
SOWWWL_AZA_DIRECT_ORIGIN=http://192.168.1.36
API_PUBLIC_BASE_URL=http://192.168.1.36
API_ALLOWED_ORIGINS=http://192.168.1.36,http://sowwwl-pi.local,http://192.168.1.36:8080,http://sowwwl-pi.local:8080
```

Bring up the LAN overlay:

```bash
docker compose -p sowwwl-o --env-file .env.lan -f docker-compose.prod.yml -f docker-compose.lan.yml up -d db app api caddy_lan
```

Then verify from the same network:

```bash
curl -I http://192.168.1.36/
curl -I http://192.168.1.36/str3m
curl -I 'http://192.168.1.36/island?u=qa-multimatiere'
curl -I http://192.168.1.36/healthz
curl -sL http://192.168.1.36/v1/status
```

Ports `8080` and `8081` can stay exposed too as direct debug paths for `app` and `api`.

If the same Pi is then published through a hostname or tunnel such as
`https://pi.sowwwl.cloud`, update these values before restarting the stack:

```dotenv
SOWWWL_PUBLIC_ORIGIN=https://pi.sowwwl.cloud
SOWWWL_AZA_DIRECT_ORIGIN=https://pi.sowwwl.cloud
API_PUBLIC_BASE_URL=https://pi.sowwwl.cloud
API_ALLOWED_ORIGINS=http://192.168.1.36,http://sowwwl-pi.local,http://192.168.1.36:8080,http://sowwwl-pi.local:8080,https://pi.sowwwl.cloud
```

If a separate Raspberry Pi camera node will feed this host, also set:

```dotenv
SOWWWL_PI_TOKEN=replace-with-long-random-ingest-token
SOWWWL_SENSOR_LOG_DIR=/var/www/runtime/plasma
PI3_CAMERA_STREAM_UPSTREAM=192.168.1.62:8082
PI3_CAMERA_STREAM_TOKEN=replace-with-the-pi3-viewer-token
```

Then the public ingest lives at:

```text
https://pi.sowwwl.cloud/ingest/sensor
```

If that separate camera node also enables the optional MJPEG helper, the Pi 5 edge can proxy it here:

```text
https://pi.sowwwl.cloud/camera/pi3-camera-01
http://192.168.1.36/camera/pi3-camera-01/stream.mjpg
http://192.168.1.36/camera/pi3-camera-01/snapshot.jpg
https://pi.sowwwl.cloud/camera/pi3-camera-01/stream.mjpg
https://pi.sowwwl.cloud/camera/pi3-camera-01/snapshot.jpg
```

The first URL is the integrated page in the app itself. The Pi 5 proxy forwards the private viewer token upstream as a header, so the browser URL itself does not need to contain the token.

If the Pi 5 should also run Hailo analysis against that proxied camera, add these host-side values to `/etc/sowwwl/pocket-land.env` and install `sowwwl-pi-ai-bridge`:

```dotenv
SOWWWL_PI_AI_CAMERA_SLUG=pi3-camera-01
SOWWWL_PI_AI_SNAPSHOT_URL=http://127.0.0.1/camera/pi3-camera-01/snapshot.jpg
SOWWWL_PI_AI_ENDPOINT=http://127.0.0.1/ingest/camera-ai
# Optional. Leave empty to reuse SOWWWL_PI_TOKEN.
# SOWWWL_PI_AI_TOKEN=
```

If several sceptres will later feed `sowwwl.io`, the host can keep one primary
device while accepting many nodes:

```dotenv
SOWWWL_SCEPTRE_PRIMARY_DEVICE=ensemble
SOWWWL_SCEPTRE_TOKENS_FILE=/var/www/runtime/sceptre/tokens.json
```

Then expose the roster through:

```text
https://pi.sowwwl.cloud/sceptre/constellation.json
```

If you also want a public preview of the future spatial surfaces on the Pi host
itself, allow that host to use `?surface=xyz|io|lab`:

```dotenv
SOWWWL_SURFACE_PREVIEW_HOSTS=pi.sowwwl.cloud
```

Then for example:

```text
https://pi.sowwwl.cloud/?surface=io
```

Then on the Pi 5:

```bash
sudo bash scripts/install_pi_ai_bridge_service.sh --user "$USER"
```

This is intentionally not the public deployment. It is only the shortest honest path for validating the first Pi-backed instance on a local network.

On a fresh MySQL volume, `init.sql` and migrations `003` through `007` are imported automatically in filename order.

If the database already exists, mounted init scripts are not replayed automatically. Apply missing migrations manually, in order:

```bash
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < ../migrations/003_echoes_notification.sql
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < ../migrations/004_liaisons_ports.sql
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < ../migrations/005_flows.sql
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < ../migrations/2026_05_02_signal_mail.sql
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < ../migrations/007_query_indexes.sql
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
- `https://sowwwl.xyz/` and confirm the membrane bridge stays on `sowwwl.xyz`
- `https://sowwwl.io/` and confirm the spatial surface answers from the same app
- `https://sowwwl.xyz/map`
- `https://api.sowwwl.cloud/healthz`
- `https://api.sowwwl.cloud/v1/status`

For the bridge specifically, the live HTML on `sowwwl.xyz` should expose a same-host endpoint, not the lab:

```bash
curl -sL https://sowwwl.xyz/ | grep -E 'data-xyz-plasma-bridge="https://sowwwl\.xyz(/o)?/ingest/membrane"'
curl -sL https://sowwwl.xyz/ | grep -E 'data-xyz-plasma-bridge="https://lab\.sowwwl\.cloud' && false || true
curl -I https://sowwwl.io/
curl -sL https://sowwwl.xyz/map | grep -E 'Le tore des terres actives|Console lexicale de la map|courants actifs'
curl -sL https://api.sowwwl.cloud/v1/status | grep -E '"service": ?"api.sowwwl.cloud"|AzA_v0.7_openapi.min.yaml'
```

For Signal identity validation specifically, check the runtime from inside the live app container:

```bash
docker exec sowwwl-o-app-1 php /var/www/html/scripts/check_signal_validation.php --require-schema-ready
```

If production must actually send validation emails through SMTP, set `SOWWWL_SIGNAL_IDENTITY_DELIVERY=mail` in `.env.production`, then run the stricter gate:

```bash
docker exec sowwwl-o-app-1 php /var/www/html/scripts/check_signal_validation.php --require-schema-ready --require-delivery-ready
```

That stricter check must fail if SMTP still points to placeholders such as `smtp.example.com` or `CHANGE_ME_*` values.

Expected behavior:

- `0wlslw0` shows the voice guide block when the updated app image is live
- `Signal` shows a mailbox UX, not the old public trace wall
- `Signal` uses the land virtual email and can send an identity verification email
- `Écho` still works and lists contacts from JSON lands, even if SQL `lands` rows are absent
- `sowwwl.xyz` exposes the membrane surface and keeps its plasma bridge on `sowwwl.xyz`, unless you intentionally override it
- `sowwwl.io` exposes the spatial surface from the same app with camera/microphone permissions available through the `permissions_surface` host block
- `sowwwl.xyz/map` responds from the same O. app instead of redirecting back to `sowwwl.com`

If you want the live app to relay to the DigitalOcean voice agent, also set these variables in `.env.production`:

- `SOWWWL_0WLSLW0_AGENT_ENDPOINT`
- `SOWWWL_0WLSLW0_AGENT_KEY`
- `SOWWWL_0WLSLW0_AGENT_AUTH_HEADER`
- `SOWWWL_0WLSLW0_AGENT_AUTH_SCHEME`
- `SOWWWL_0WLSLW0_AGENT_MODE`
- `SOWWWL_0WLSLW0_AGENT_INPUT_FIELD`
- `SOWWWL_0WLSLW0_AGENT_EXTRA_HEADERS_JSON`

Then verify the relay from inside the live app container:

```bash
docker exec sowwwl-o-app-1 php /var/www/html/scripts/check_0wlslw0_agent.php
docker exec sowwwl-o-app-1 php /var/www/html/scripts/check_0wlslw0_agent.php --require-remote-ok
```

`--require-remote-ok` must fail loudly if the endpoint is still a placeholder, the auth key is missing, or the remote agent answers with an unusable payload.

Do not run `wrangler deploy` for `sowwwl.xyz`; Cloudflare should proxy the VPS origin instead.

## SMTP cutover checklist for Signal

Before claiming that production email validation is live, set `SOWWWL_SIGNAL_IDENTITY_DELIVERY=mail`, then replace and verify these values in `.env.production`:

- `SOWWWL_SMTP_HOST`
- `SOWWWL_SMTP_PORT`
- `SOWWWL_SMTP_USERNAME`
- `SOWWWL_SMTP_PASSWORD`
- `SOWWWL_MAGIC_LINK_FROM`
- `SOWWWL_MAGIC_LINK_FROM_NAME`

Then rebuild the app and re-run:

```bash
docker exec sowwwl-o-app-1 php /var/www/html/scripts/check_signal_validation.php --require-schema-ready --require-delivery-ready
```

Expected result:

- exit code `0`
- `schema ready : yes`
- `delivery ready : yes`
- no placeholder SMTP issues reported

## Cloudflare notes

If Cloudflare is enabled:

1. Point the DNS records to the VPS.
2. For the first certificate issuance, use DNS-only mode if needed.
3. Once Caddy has valid origin certificates, switch SSL mode to `Full (strict)`.
4. If `sowwwl.xyz` shows a `525`, the origin handshake is still failing. Verify the host exists in Caddy, the origin is reachable on `443`, and the origin certificate matches the domain.
5. Apply the same rule to `sowwwl.io`: `A @ -> 161.35.157.37`, `CNAME www -> @`, proxied in Cloudflare once the origin certificate is live.
6. If you previously had a `526` on `0wlslw0.com`, `sowwwl.cloud`, or `sowwwl.io`, that error should disappear once the origin is serving a valid certificate and Cloudflare is in `Full (strict)`.

## What the API stub does

The `api.sowwwl.cloud` service now provides:

- `GET /healthz`
- `GET /docs`
- `GET /docs/AzA_v0.7_openapi.min.yaml`
- `GET /v1/status`

Protected write endpoints return `501 not_implemented` with JSON. This is deliberate: the host resolves and responds, but it does not pretend the production AzA service exists yet.

The production deploy helper now rebuilds `api` alongside `app` and verifies that `/v1/status` still reports `api.sowwwl.cloud` before it declares success.

## Customization

- Replace `sites/sowwwl.com/` with the real product origin or change the `sowwwl.com` host block back to a reverse proxy.
- Keep `*.o.sowwwl.cloud` on the app proxy path so the user slug can resolve dynamically inside PHP.
- `0wlslw0.com` now points to the live `0wlslw0` guide inside the PHP app, so domain visitors land on the real onboarding experience instead of the old static placeholder.
- Keep `sites/0wlslw0.com/` only as archive/reference material unless you intentionally switch that host back to a static landing.
- Remove any host block from `Caddyfile` if that domain should continue to use another origin.
