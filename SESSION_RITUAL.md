# Session ritual — O. live app

Use this checklist before touching code, before deploying, and after verifying production.

Master entry point for all workflow docs: `OPS_INDEX.md`.

## 1. Decide the workspace boundary

### If the change is about the live O. app
Work in:
- `o/`
- `o/deploy/`

Typical scope:
- `0wlslw0`
- `signal`
- `str3m`
- `echo`
- `map`
- PHP app files copied into the `sowwwl-o` image
- Caddy and Compose files inside `o/deploy/`

### If the change is about the parent legacy shell or shared infra
Work in:
- repository root `/O_installation_FRESH`

Typical scope:
- root Apache bridges
- root `deploy/`
- root `index.php`, `config.php`, `start.sh`
- parent migrations and legacy upload/media endpoints

## 2. Before changing anything

Run these checks first.

### In `o/`
```bash
cd /Users/pabloespallergues/Downloads/O_installation_FRESH/o
pwd
git branch --show-current
git status --short
```

### If deploying live O.
```bash
cd /Users/pabloespallergues/Downloads/O_installation_FRESH/o/deploy
pwd
```

Confirm mentally:
- repo = `o/`
- deploy stack = `o/deploy`
- compose project = `sowwwl-o`
- target domains = `sowwwl.com`, `0wlslw0.com`, optionally `sowwwl.xyz`

## 3. Before editing code

Check these questions:
- Is this an app change or a root/infra change?
- Will Docker build from the directory I am editing?
- Will the modified file be copied into the runtime image?
- Is there an existing clean route or rewrite rule already handling this?
- Is there a DB migration involved?

## 4. Before deploying

### For app deploys from `o/`
```bash
cd /Users/pabloespallergues/Downloads/O_installation_FRESH/o
git status --short
```

If needed:
```bash
git add <files>
git commit -m "<clear message>"
git push origin main
```

### On the VPS
```bash
cd /root/O_installation_FRESH/o
git fetch origin
git reset --hard origin/main
```

Then:
```bash
cd /root/O_installation_FRESH/o/deploy
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml build --no-cache app
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml up -d
```

If the change also touches `o/deploy/sites/` or any public static host like `sowwwl.org`, prefer:

```bash
cd /root/O_installation_FRESH/o
bash scripts/deploy_prod_update.sh
```

That avoids the common drift where the app image is current but the static directory mounted by `sowwwl-o-caddy-1` still serves an older snapshot.

## 5. If Signal schema changed

If the DB volume already existed before the migration:
```bash
cd /root/O_installation_FRESH/o/deploy
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < /root/O_installation_FRESH/migrations/2026_05_02_signal_mail.sql
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml restart app
```

## 6. Runtime verification after deploy

### Verify files inside the app container
```bash
docker exec sowwwl-o-app-1 sh -lc 'ls -la /var/www/html/.htaccess /var/www/html/0wlslw0.php /var/www/html/str3m.php /var/www/html/map.php /var/www/html/map_points.php'
```

### Verify key domains and routes
```bash
curl -I https://0wlslw0.com
curl -sL https://0wlslw0.com | grep -E 'Signal before story|concierge d entree|Entrer sans se perdre|0wlslw0'

curl -I https://sowwwl.com
curl -I https://sowwwl.org
curl -I https://sowwwl.com/signal
curl -I https://sowwwl.com/str3m
curl -I https://sowwwl.com/map
```

### Expected outcomes
- `0wlslw0.com` should no longer serve the old static placeholder
- `0wlslw0.com` should redirect to `/0wlslw0` or serve the guide content
- `sowwwl.org` should reflect the current validated explanatory layer, not a stale static copy
- `signal` should expose the mailbox UX, not the old public trace wall
- `str3m` should remain public
- `map` should respond from the O. app

## 7. If something looks wrong

Debug in this order:
1. public domain response
2. Caddy container and mounted config
3. app container contents
4. source code present on VPS
5. local repo vs `origin/main`

Use these kinds of checks:
```bash
docker logs --tail=120 sowwwl-o-caddy-1
docker logs --tail=120 sowwwl-o-app-1
docker inspect sowwwl-o-caddy-1 --format '{{range .Mounts}}{{println .Source " -> " .Destination}}{{end}}'
```

## 8. Session close-out

Before ending a session, record briefly:
- which repo was touched
- which domains were verified
- whether the VPS was synced and rebuilt
- whether production behavior was confirmed by `curl`
- any migration or follow-up still pending

For commit hygiene and push/deploy decisions, see `COMMIT_PROTOCOL.md`.

## 9. Golden rules

- Do not mix root `deploy/` and `o/deploy/` casually.
- For the live O. app, prefer `o/deploy/` with compose project `sowwwl-o`.
- Verify production with `curl`, not assumptions.
- A proxy restart is not proof that the app image contains the new code.
- A local fix is not deployed until VPS source, image, and public domain all match.

For a short VPS-oriented deploy sequence, see `DEPLOY_QUICKREF.md`.
