# Live verification — O. app

Use this right after deploy, or anytime production feels suspicious.

## 1. First rule

Do not trust a restart.
Trust what `curl` returns.

## 2. Core checks

Before drilling into page behavior, remember the public path:

- `sowwwl.com` -> `sowwwl-o-caddy-1`
- reverse proxy -> `sowwwl-o-app-1`

If `/var/www/html` was updated but the `app` container was not rebuilt, public output can still be stale.

### 0wlslw0
```bash
curl -I https://0wlslw0.com
curl -sL https://0wlslw0.com | grep -E 'Signal before story|concierge d entree|Entrer sans se perdre|0wlslw0'
```

Expected:
- no old static placeholder
- redirect to `/0wlslw0` or direct guide content
- guide markers like `concierge d entree` or `Entrer sans se perdre`

Red flag:
- `Signal before story.`
- unexpected `200` with old static payload

### sowwwl.com entrypoints
```bash
curl -I https://sowwwl.com/
curl -I https://sowwwl.com/signal
curl -I https://sowwwl.com/str3m
curl -I https://sowwwl.com/map
curl -I 'https://sowwwl.com/island?u=<slug-connu>'
```

Expected:
- `signal` responds from the mailbox app
- `str3m` responds publicly
- `map` responds from the O. app
- `island` responds from the O. app for a known land slug

### Signal validation readiness
```bash
docker exec sowwwl-o-app-1 php /var/www/html/scripts/check_signal_validation.php
docker exec sowwwl-o-app-1 php /var/www/html/scripts/check_signal_validation.php --slug <slug-connu>
```

Expected:
- Signal schema reports `ready`
- delivery mode is explicit (`mail`, `log`, or `display`)
- if mode is `mail`, the helper must not report placeholder SMTP configuration

### island route and video check
```bash
curl -sL 'https://sowwwl.com/island?u=<slug-connu>' | grep -E 'Station de lecture|Relief|Dernières traces'
curl -sL 'https://sowwwl.com/island?u=<slug-video>' | grep -E 'ouvrir la vidéo|Vidéo disponible, mais pas lisible directement ici'
```

Expected:
- `Station de lecture` is visible on the upgraded island page
- for mixed video formats, a browser-playable source should win over `.mov`
- the explicit fallback message should appear only when no browser-playable video exists

## 3. Container-content check

If public behavior looks wrong, verify what the app image actually contains.

```bash
docker exec sowwwl-o-app-1 sh -lc 'ls -la /var/www/html/.htaccess /var/www/html/0wlslw0.php /var/www/html/str3m.php /var/www/html/map.php /var/www/html/map_points.php /var/www/html/island.php'
docker exec sowwwl-o-app-1 sh -lc 'grep -n "0wlslw0.com\|/0wlslw0" /var/www/html/index.php | head -20'
docker exec sowwwl-o-app-1 sh -lc 'grep -n "concierge d entree\|Entrer sans se perdre" /var/www/html/0wlslw0.php'
docker exec sowwwl-o-app-1 sh -lc 'grep -n "Station de lecture\|Vidéo disponible, mais pas lisible directement ici" /var/www/html/island.php'
```

Interpretation:
- if files are missing, the image is stale or built from the wrong source
- if files exist but live output is wrong, inspect proxy and host routing next

## 4. Proxy-level check

```bash
docker logs --tail=120 sowwwl-o-caddy-1
docker inspect sowwwl-o-caddy-1 --format '{{range .Mounts}}{{println .Source " -> " .Destination}}{{end}}'
```

Use this to confirm:
- the expected Caddyfile is mounted
- the expected `sites/` directory is mounted
- the container is the right compose project (`sowwwl-o`)

## 5. App-level check

```bash
docker logs --tail=120 sowwwl-o-app-1
```

Look for:
- missing files
- Apache rewrite issues
- PHP fatals
- unexpected requests to `/` returning the wrong content

## 6. Fast diagnosis map

### Case A — domain is wrong
Symptoms:
- public `curl` returns old content
- container files are correct

Check:
- Caddy routing
- DNS / CDN cache
- wrong compose stack still exposed

### Case B — image is wrong
Symptoms:
- public `curl` wrong
- app container missing expected files

Check:
- VPS repo sync
- Docker build context
- whether rebuild happened after source update

### Case C — host/routing mismatch
Symptoms:
- files exist
- app still behaves unexpectedly on one host only

Check:
- host normalization
- redirects
- Caddy host block
- Apache rewrites

### Case D — migration missing
Symptoms:
- UI loads
- messaging features fail

Check:
- SQL tables
- manual migration execution

## 7. Minimum proof before saying “it works”

You can consider production verified only when:

- public `curl` matches expected behavior
- key files exist in the live app container
- the domain no longer returns old placeholder content
- target feature route responds correctly

## 8. Golden rule

When in doubt, verify in this order:

1. public domain
2. app container contents
3. proxy mounts and logs
4. VPS source state

That order is faster than arguing with the ghost.