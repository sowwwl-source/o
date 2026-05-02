# DB and migration protocol — O. live app

Use this whenever a feature depends on SQL schema changes or when production behavior suggests the database state may not match the code.

## 1. First question: fresh volume or existing volume?

### Fresh MySQL volume
If the DB volume is new, init scripts mounted into `/docker-entrypoint-initdb.d/` should run automatically on first boot.

For the live O. stack, that includes:
- `../../init.sql`
- `../../migrations/2026_05_02_signal_mail.sql`

### Existing MySQL volume
If the DB volume already existed before a new migration was added, mounted init scripts will **not** run again automatically.

In that case, the migration must be applied manually.

## 2. Know when a migration is required

Typical signs:
- UI loads but features fail
- Signal mailbox pages open but actions fail
- SQL-backed counts or unread totals stay broken
- code path checks for tables and falls back to degraded behavior

Examples in this app:
- `signal_mailboxes`
- `signal_messages`
- new columns such as `lands.notification_email`

## 3. Baseline checks on the VPS

```bash
cd /root/O_installation_FRESH/o/deploy
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml ps
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml exec db sh -lc 'echo "$MYSQL_DATABASE / $MYSQL_USER"'
```

Confirm:
- the `db` container is up
- the compose project is `sowwwl-o`
- the expected database name and user are loaded

## 4. Check whether tables/columns exist

```bash
cd /root/O_installation_FRESH/o/deploy
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SHOW TABLES LIKE \"signal_mailboxes\"; SHOW TABLES LIKE \"signal_messages\"; SHOW COLUMNS FROM lands LIKE \"notification_email\";"'
```

Interpretation:
- if tables/column exist, schema is at least partially applied
- if they do not exist, the migration is missing on this DB volume

## 5. Apply the migration manually

For the Signal mailbox migration:

```bash
cd /root/O_installation_FRESH/o/deploy
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < /root/O_installation_FRESH/migrations/2026_05_02_signal_mail.sql
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml restart app
```

## 6. Verify after migration

Run the schema checks again:

```bash
cd /root/O_installation_FRESH/o/deploy
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SHOW TABLES LIKE \"signal_mailboxes\"; SHOW TABLES LIKE \"signal_messages\"; SHOW COLUMNS FROM lands LIKE \"notification_email\";"'
```

Then verify feature behavior from the app:

```bash
curl -I https://sowwwl.com/signal
```

If you have an authenticated session in the browser, also confirm the mailbox no longer shows the “migration required” state.

## 7. Fast diagnosis map

### Case A — code deployed, schema missing
Symptoms:
- route works
- UI renders
- feature says messaging/migration unavailable

Action:
- apply migration manually

### Case B — schema exists, feature still broken
Symptoms:
- tables are present
- app still behaves as if messaging is unavailable

Check:
- app environment variables
- connection credentials
- PHP logs
- whether app container was restarted after migration

### Case C — fresh volume expected but tables missing
Symptoms:
- DB was recreated
- init scripts should have run
- tables are absent anyway

Check:
- docker-compose mounts
- actual file presence on VPS
- MySQL container logs

## 8. Useful inspection commands

### Check mounted init scripts
```bash
docker inspect sowwwl-o-db-1 --format '{{range .Mounts}}{{println .Source " -> " .Destination}}{{end}}'
```

### Check DB logs
```bash
docker logs --tail=120 sowwwl-o-db-1
```

### Inspect table structure
```bash
cd /root/O_installation_FRESH/o/deploy
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "DESCRIBE signal_mailboxes; DESCRIBE signal_messages;"'
```

## 9. Golden rules

- mounted init scripts do not replay on an existing MySQL volume
- code can be correct while schema is stale
- after manual migration, restart the app container
- verify schema state with SQL, not assumption
- treat DB state as a deploy layer of its own

The app, the proxy, and the image can all be right while the database is still living in yesterday.