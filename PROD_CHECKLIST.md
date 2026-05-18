# Prod checklist — O. live app

Use this as the shortest operational checklist before and after touching production.

## Before prod

- Am I in the right repo (`o/`)?
- Am I deploying from the right stack (`o/deploy`)?
- Am I updating the runtime that the public domains actually use (`sowwwl-o-caddy-1` -> `sowwwl-o-app-1`)?
- Is the change pushed to `origin/main`?
- Do I know whether a DB migration is required?
- Do I know which domains I must verify right after deploy?
- If `0wlslw0` must use the distant IA, are the `SOWWWL_0WLSLW0_*` variables actually present in `o/deploy/.env.production`?

## Deploy

- If the change touches `deploy/sites/` or `sowwwl.org`, prefer `bash scripts/deploy_prod_update.sh`
- Sync VPS source to `origin/main`
- Rebuild `sowwwl-o` app image
- Restart the stack
- Confirm the running `app` container contains the expected files
- Apply migration manually if the DB volume already existed

## After prod

- Check `https://0wlslw0.com`
- Run `docker exec sowwwl-o-app-1 php /var/www/html/scripts/check_0wlslw0_agent.php --require-remote-ok` when the distant relay is expected
- Check `https://sowwwl.org`
- Check `https://sowwwl.com/signal`
- Check `https://sowwwl.com/str3m`
- Check `https://sowwwl.com/map`
- Check `https://sowwwl.com/island?u=<slug-connu>`
- If that island has video, confirm the embedded reader prefers a browser-playable source over `.mov`
- Confirm the old placeholder is gone

## Minimum proof that prod is good

- public `curl` matches expected behavior
- `scripts/check_0wlslw0_agent.php` reports a real remote answer when the IA relay is expected
- app container contains expected files
- static hosts like `sowwwl.org` match the current validated copy
- feature route behaves as intended
- island route renders the expected classic reading for at least one known land slug
- if that land has both `.mov` and `.mp4`, the island reader prefers the `.mp4`
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
