# Prod checklist — O. live app

Use this as the shortest operational checklist before and after touching production.

## Before prod

- Am I in the right repo (`o/`)?
- Am I deploying from the right stack (`o/deploy`)?
- Is the change pushed to `origin/main`?
- Do I know whether a DB migration is required?
- Do I know which domains I must verify right after deploy?

## Deploy

- Sync VPS source to `origin/main`
- Rebuild `sowwwl-o` app image
- Restart the stack
- Apply migration manually if the DB volume already existed

## After prod

- Check `https://0wlslw0.com`
- Check `https://sowwwl.com/signal`
- Check `https://sowwwl.com/str3m`
- Check `https://sowwwl.com/map`
- Check `https://sowwwl.com/island?u=<slug-connu>`
- Confirm the old placeholder is gone

## Minimum proof that prod is good

- public `curl` matches expected behavior
- app container contains expected files
- feature route behaves as intended
- island route renders the expected classic reading for at least one known land slug
- no critical regression appears on key domains

## If prod is suspicious

- do not trust a restart alone
- verify public response first
- verify app container contents second
- verify proxy mounts/logs third
- rollback only if the bad deploy is confirmed and a known-good commit exists

## Related docs

- `OPS_INDEX.md`
- `SESSION_RITUAL.md`
- `DEPLOY_QUICKREF.md`
- `LIVE_VERIFICATION.md`
- `ISLAND_DEPLOY_CHECKLIST.md`
- `ROLLBACK_PROTOCOL.md`
- `DB_MIGRATION_PROTOCOL.md`