# O. — live app workspace

This directory is the main source of truth for the live O. app.

Use this repo for:
- `0wlslw0`
- `signal`
- `str3m`
- `echo`
- `map`
- the live PHP app shipped in the `sowwwl-o` image
- deployment files inside `o/deploy/`

## Live scope

Primary live domains:
- `https://sowwwl.com`
- `https://0wlslw0.com`

Secondary / optional scope depending on routing state:
- `https://sowwwl.xyz`

## Where to deploy from

For the live O. app, deploy from:
- `o/deploy/`

Compose project name:
- `sowwwl-o`

## 0wlslw0 voice relay

The voice-only guide now lives in `0wlslw0` and posts to:
- `/0wlslw0/voice`

Server-side relay variables live in `o/.env` and are documented in `o/.env.example`:
- `SOWWWL_0WLSLW0_AGENT_ENDPOINT`
- `SOWWWL_0WLSLW0_AGENT_KEY`
- `SOWWWL_0WLSLW0_AGENT_AUTH_HEADER`
- `SOWWWL_0WLSLW0_AGENT_AUTH_SCHEME`
- `SOWWWL_0WLSLW0_AGENT_MODE`

If the upstream agent is unreachable, `0wlslw0` falls back to a local orientation guide instead of failing silently.

## Operational docs

Start here depending on the situation:

- `OPS_INDEX.md` — master entry point
- `SESSION_RITUAL.md` — begin a session cleanly
- `COMMIT_PROTOCOL.md` — prepare commits and pushes
- `DEPLOY_QUICKREF.md` — shortest deploy path
- `PROD_CHECKLIST.md` — compact before/after prod checklist
- `LIVE_VERIFICATION.md` — verify real production behavior
- `ROLLBACK_PROTOCOL.md` — controlled rollback path
- `DB_MIGRATION_PROTOCOL.md` — DB and migration state checks

## Working rule

- app live changes belong in `o/`
- root legacy shell / shared infra changes belong in the parent repository
- do not mix root `deploy/` and `o/deploy/` casually

## Typical workflow

1. read `OPS_INDEX.md`
2. follow `SESSION_RITUAL.md`
3. commit with `COMMIT_PROTOCOL.md`
4. deploy with `DEPLOY_QUICKREF.md`
5. check `PROD_CHECKLIST.md`
6. verify with `LIVE_VERIFICATION.md`

## If production goes strange

Do not trust a restart alone.

Check in this order:
1. public `curl`
2. files inside `sowwwl-o-app-1`
3. Caddy logs and mounts
4. VPS source state
5. DB schema state if the feature depends on SQL
