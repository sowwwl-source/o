# `sowwwl.xyz` No Longer Uses Wrangler

The old Cloudflare Worker deployment path for `sowwwl.xyz` has been retired from this repository.

`sowwwl.xyz` now runs through the VPS stack:

- [`DEPLOY_MULTISITE.md`](./DEPLOY_MULTISITE.md)
- [`deploy/README.md`](./deploy/README.md)
- [`deploy/docker-compose.prod.yml`](./deploy/docker-compose.prod.yml)
- [`deploy/Caddyfile`](./deploy/Caddyfile)

## What Changed

- `wrangler.toml` was removed
- the Worker entrypoint was removed
- the duplicate Worker `public/` bundle was removed
- the repo should no longer be deployed with `npx wrangler deploy`

## What To Change In Cloudflare

If a Cloudflare Workers Builds project is still attached to this repository, update or disable it:

1. Remove the deploy command `npx wrangler deploy`.
2. Stop using Workers Builds for `sowwwl.xyz`.
3. Point `sowwwl.xyz` and `www.sowwwl.xyz` to the VPS origin instead.
4. Keep Cloudflare in front only as DNS and proxy, with SSL mode set to `Full (strict)` once the origin certificates are valid.

## Symptom Of A Stale Worker

If `https://sowwwl.xyz/` keeps reloading, shows "chargement raté", or never settles on the real home page, the old Worker route is still serving the former static asset bundle.

In the previous Worker deployment, the asset bundle contained a `public/index.html` placeholder with a meta refresh to `/`. When that stale Worker is still attached to `sowwwl.xyz/*`, the browser ends up in a self-redirect loop on the root page.

In that case:

1. Remove or disable the `sowwwl.xyz/*` and `www.sowwwl.xyz/*` Worker routes in Cloudflare.
2. Disable the old Workers Builds project for this repository.
3. Purge the Cloudflare cache for `sowwwl.xyz`.
4. Verify that `/` is now served by the VPS origin instead of the old Worker asset bundle.

## Deploy Path For `.xyz`

From the repository root:

```bash
cp deploy/.env.production.example deploy/.env.production
docker compose --env-file deploy/.env.production -f deploy/docker-compose.prod.yml up --build -d
```

After deployment:

- `sowwwl.xyz` should resolve to the PHP ingress app
- `www.sowwwl.xyz` should redirect to `sowwwl.xyz`
- Cloudflare should not run Wrangler for this repo anymore
