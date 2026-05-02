# Rollback protocol — O. live app

Use this only when production is clearly worse after a deploy and the issue is confirmed by live checks.

## 1. When to rollback

Rollback when all three are true:

- the problem is visible in public `curl` checks
- the deployed version is confirmed to be the cause
- a known-good commit exists and is safer than continuing live debugging

Do **not** rollback just because something feels suspicious.
Verify first.

## 2. Before rollbacking

Capture the current state.

```bash
cd /root/O_installation_FRESH/o
git rev-parse HEAD
git log --oneline -n 5
```

Also capture the live symptom:

```bash
curl -I https://0wlslw0.com
curl -sL https://0wlslw0.com | grep -E 'Signal before story|concierge d entree|Entrer sans se perdre|0wlslw0'
```

## 3. Choose the rollback target

Pick a commit that is known-good because:

- it was deployed successfully
- the domain behavior was verified after that deploy
- it predates the regression you are seeing now

Inspect recent commits:

```bash
cd /root/O_installation_FRESH/o
git log --oneline --decorate -n 12
```

## 4. Execute the rollback on the VPS

```bash
cd /root/O_installation_FRESH/o
git fetch origin
git reset --hard <known-good-commit>
```

Then rebuild and restart:

```bash
cd /root/O_installation_FRESH/o/deploy
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml build --no-cache app
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml up -d
```

## 5. Verify that rollback actually worked

```bash
docker exec sowwwl-o-app-1 sh -lc 'git --version >/dev/null 2>&1 || true; ls -la /var/www/html/.htaccess /var/www/html/0wlslw0.php'
curl -I https://0wlslw0.com
curl -sL https://0wlslw0.com | grep -E 'Signal before story|concierge d entree|Entrer sans se perdre|0wlslw0'
```

Expected:

- the previously working public behavior returns
- the regression symptom disappears

## 6. If rollback did not fix production

Then the issue was likely not the app commit alone.
Check next:

1. Caddy config and mounts
2. wrong VPS source state
3. wrong compose project/container still exposed
4. CDN / DNS cache
5. missing DB migration or stale volume state

## 7. After rollback

Record briefly:

- broken commit or suspected bad range
- rollback target commit
- domains verified after rollback
- whether rollback fully fixed the issue
- what still needs investigation before redeploying

## 8. Golden rules

- rollback to a commit, not to a vague memory
- verify public behavior before and after rollback
- rebuild the app image after resetting source
- do not keep debugging the broken state if production is degraded and a safe rollback exists

Rollback is not failure. It is a controlled retreat so the next move is deliberate.