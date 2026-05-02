# Deploy quickref — O. live app

Use this when the change is already pushed to `origin/main` and production must be updated fast and clean.

## 1. Sync VPS source

```bash
cd /root/O_installation_FRESH/o
git fetch origin
git reset --hard origin/main
```

## 2. Rebuild and restart live app

```bash
cd /root/O_installation_FRESH/o/deploy
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml build --no-cache app
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml up -d
```

## 3. If Signal schema changed

Only when the DB volume already existed before the migration:

```bash
cd /root/O_installation_FRESH/o/deploy
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < /root/O_installation_FRESH/migrations/2026_05_02_signal_mail.sql
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml restart app
```

## 4. Verify the container contents

```bash
docker exec sowwwl-o-app-1 sh -lc 'ls -la /var/www/html/.htaccess /var/www/html/0wlslw0.php /var/www/html/str3m.php /var/www/html/map.php /var/www/html/map_points.php'
```

## 5. Verify live domains

```bash
curl -I https://0wlslw0.com
curl -sL https://0wlslw0.com | grep -E 'Signal before story|concierge d entree|Entrer sans se perdre|0wlslw0'

curl -I https://sowwwl.com/signal
curl -I https://sowwwl.com/str3m
curl -I https://sowwwl.com/map
```

## 6. Expected results

- `0wlslw0.com` should not serve the old static placeholder
- `0wlslw0.com` should redirect to `/0wlslw0` or serve the guide content
- `signal` should expose mailbox behavior
- `str3m` should stay public
- `map` should respond from the O. app

## 7. If something breaks

Debug in this order:

1. public `curl`
2. Caddy logs
3. app logs
4. files inside `sowwwl-o-app-1`
5. source code on VPS vs `origin/main`

Useful commands:

```bash
docker logs --tail=120 sowwwl-o-caddy-1
docker logs --tail=120 sowwwl-o-app-1
docker inspect sowwwl-o-caddy-1 --format '{{range .Mounts}}{{println .Source " -> " .Destination}}{{end}}'
```

## 8. Minimal rollback mindset

If production looks wrong after deploy:

- do not guess
- confirm what `curl` returns
- confirm what files are inside the app container
- confirm the VPS repo really matches `origin/main`
- only then rebuild or revert

Rollback command shape if needed:

```bash
cd /root/O_installation_FRESH/o
git log --oneline -n 5
git reset --hard <known-good-commit>
cd /root/O_installation_FRESH/o/deploy
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml build --no-cache app
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml up -d
```