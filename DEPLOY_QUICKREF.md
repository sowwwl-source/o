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

## 3b. If p0rt schema changed or was never applied

Only when the DB volume already existed before the liaisons+p0rts migration:

```bash
cd /root/O_installation_FRESH/o/deploy
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < /root/O_installation_FRESH/migrations/004_liaisons_ports.sql
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml restart app
```

For a fuller schema-state checklist, see `DB_MIGRATION_PROTOCOL.md`.

## 4. Verify the container contents

```bash
docker exec sowwwl-o-app-1 sh -lc 'ls -la /var/www/html/.htaccess /var/www/html/0wlslw0.php /var/www/html/0wlslw0_voice.php /var/www/html/str3m.php /var/www/html/map.php /var/www/html/map_points.php /var/www/html/land.php /var/www/html/aza.php /var/www/html/island.php'
docker exec sowwwl-o-app-1 sh -lc 'printenv | grep -E "SOWWWL_0WLSLW0_(CHAT_URL|AGENT_ENDPOINT|AGENT_MODE|AGENT_INPUT_FIELD|AGENT_TIMEOUT_SECONDS)"'
docker exec sowwwl-o-app-1 php /var/www/html/scripts/check_signal_validation.php --json
```

For a known land slug, inspect the actual mailbox state too:

```bash
docker exec sowwwl-o-app-1 php /var/www/html/scripts/check_signal_validation.php --slug <slug-connu>
```

## 5. Verify live domains

```bash
curl -I https://0wlslw0.com
curl -sL https://0wlslw0.com | grep -E 'Signal before story|concierge d entree|Entrer sans se perdre|0wlslw0'

curl -sL https://sowwwl.com/0wlslw0 | grep -E 'Accompagnement vocal|voice only|guide vocal|fallback local|Activer la voix'

curl -I https://sowwwl.com/signal
curl -I https://sowwwl.com/str3m
curl -I https://sowwwl.com/map
curl -I 'https://sowwwl.com/island?u=<slug-connu>'
curl -sL 'https://sowwwl.com/island?u=<slug-connu>' | grep -E 'île classique|Relief|Finder mémoire|Dernières traces'
```

### 5b. Verify island video compatibility when a land has multiple video formats

Use this when the target island contains both browser-friendly video and formats like `.mov`.

```bash
curl -I 'https://sowwwl.com/island?u=<slug-video>'
curl -I 'https://sowwwl.com/storage/aza/files/<video-mov>.mov'
curl -I 'https://sowwwl.com/storage/aza/files/<video-mp4>.mp4'
curl -sL 'https://sowwwl.com/island?u=<slug-video>' | grep -E 'Station de lecture|Vidéo disponible, mais pas lisible directement ici|ouvrir la vidéo'
```

Expected interpretation:

- if a land has `mp4`, `webm`, `ogv`, or `m4v`, `island` should prefer that video in the embedded reader
- if only non-browser-safe video exists, the island must show the explicit fallback copy instead of a broken inline player
- `.mov` may still be publicly downloadable; it should not silently win over a playable `.mp4`

## 6. Expected results

- `0wlslw0.com` should not serve the old static placeholder
- `0wlslw0.com` should redirect to `/0wlslw0` or serve the guide content
- `/0wlslw0` should expose the voice-only guide block
- `signal` should expose mailbox behavior
- `scripts/check_signal_validation.php` should report both a ready Signal schema and an honest delivery state (`mail`, `log`, or `display`)
- `str3m` should stay public
- `map` should respond from the O. app
- `island?u=<slug-connu>` should return `200` and expose the classic island reading
- `island` video reader should prefer browser-playable video over `.mov` when both exist
- if only `.mov`-like formats exist, the user should see the explicit island fallback message, not a dead player

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

For a focused post-deploy checklist, see `LIVE_VERIFICATION.md`.
For the dedicated island rollout, see `ISLAND_DEPLOY_CHECKLIST.md`.
For a deliberate recovery path, see `ROLLBACK_PROTOCOL.md`.