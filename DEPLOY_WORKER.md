# Deploy the `sowwwl-xyz` Cloudflare Worker

This repo contains a Cloudflare Worker (`worker.js`) that serves the `sowwwl.xyz` ingress page and stores lands via a Durable Object (`Storage`).

## Required configuration

### 1) Routes (custom domains)

In `wrangler.toml` the Worker is routed to:

- `sowwwl.xyz/*`
- `www.sowwwl.xyz/*`

Make sure the `sowwwl.xyz` zone is in the same Cloudflare account and the DNS records are **proxied** (orange cloud) for the routes to attach.

### 2) Durable Object

Binding (already in `wrangler.toml`):

- binding name: `STORAGE`
- class: `Storage`

Migration (already in `wrangler.toml`):

- `tag = "v1"`
- `new_sqlite_classes = ["Storage"]`

### 3) Static assets

This Worker expects an assets binding named `ASSETS`.

The directory is `./public` (see `wrangler.toml`). It must contain:

- `styles.css`
- `main.js`
- `manifest.json`
- `site-sw.js`
- `favicon.svg`
- `icons/icon.svg`
- `icons/icon-mask.svg`

## Required secret

The Worker requires the secret:

- `APP_SECRET`

Set it as a **secret** (not plaintext env var) via the Cloudflare dashboard or Wrangler.

If missing, the Worker will throw: `Le secret APP_SECRET manque dans la configuration Workers.`

## Deploy (recommended)

- Install dependencies: `npm install`
- Deploy: `npm run deploy`

(You must be logged in to the correct Cloudflare account for Wrangler.)
