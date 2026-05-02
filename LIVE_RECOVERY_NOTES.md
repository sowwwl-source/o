# Live recovery notes — Signal SQL incident

This note captures the production recovery that restored live messaging after the Signal schema was missing on the VPS.

## 1. Incident shape

Observed symptom:

- `https://sowwwl.com/signal` loaded, but the UI reported that messaging was not initialized.

Root issue cluster:

1. the migration file `migrations/2026_05_02_signal_mail.sql` was missing from the VPS app tree
2. the same path was later discovered as a **directory**, not a file
3. the persistent MySQL volume had credentials that no longer matched the compose environment values
4. the migration SQL used `ADD COLUMN IF NOT EXISTS`, which was rejected by the production MySQL build

In short: missing migration, wrong path type, stale DB credentials, then a MySQL compatibility tripwire. A tidy little obstacle course.

## 2. Production context

Relevant VPS paths:

- source tree: `/root/O_installation_FRESH`
- live deploy directory: `/root/O_installation_FRESH/o/deploy`
- migration path: `/root/O_installation_FRESH/migrations/2026_05_02_signal_mail.sql`

Compose project:

- `sowwwl-o`

Relevant containers during recovery:

- `sowwwl-o-app-1`
- `sowwwl-o-db-1`
- `sowwwl-o-caddy-1`

Relevant DB volume:

- `sowwwl-o_sowwwl_xyz_db_data`

## 3. What was verified first

The first useful checks were:

- public route behavior on `/signal`
- existence of the migration file on the VPS
- MySQL container environment values
- actual schema presence for:
  - `signal_mailboxes`
  - `signal_messages`
  - `lands.notification_email`

Important lesson: on an existing MySQL volume, files mounted in `docker-entrypoint-initdb.d` do **not** replay automatically. If the volume already exists, imports must be executed deliberately.

## 4. Recovery sequence

### Step 1 — restore the missing migration file

The expected file on the VPS was missing, then found to be a directory. That had to be corrected first, because Docker cannot mount a directory onto a file path expected by the container.

Red-flag error seen during this phase:

- `not a directory: Are you trying to mount a directory onto a file (or vice-versa)?`

Action taken:

- remove the mistaken directory
- copy the real SQL file back to `/root/O_installation_FRESH/migrations/2026_05_02_signal_mail.sql`

### Step 2 — diagnose database credential drift

The compose environment exposed these intended values inside the DB container:

- `MYSQL_DATABASE=o`
- `MYSQL_USER=user`
- `MYSQL_PASSWORD=g1b0sax0`
- `MYSQL_ROOT_PASSWORD=TOSKArl0215!`

But the existing database volume rejected both the app user and root password, which confirmed that the persistent DB state had diverged from the compose file.

### Step 3 — reset credentials on the existing volume

A temporary MySQL container was started directly on the existing named volume. A reset SQL script was mounted into that temporary container and used to restore the expected accounts.

Accounts reset:

- `root@localhost`
- `root@%`
- `user@%`
- `user@localhost`

This approach preserved the existing data volume while restoring access.

### Step 4 — recreate the DB container

After the path-type issue, restarting the DB service was not enough. The DB container had to be removed and recreated so Docker would drop the stale directory-to-file mount assumption.

Important lesson:

- if a bind-mounted path changes from directory to file, recreate the container; do not trust a simple restart.

### Step 5 — patch the migration for MySQL compatibility

The original migration used:

- `ALTER TABLE lands ADD COLUMN IF NOT EXISTS notification_email VARCHAR(255) DEFAULT NULL;`

Production MySQL rejected that syntax.

The migration was patched locally to use an `INFORMATION_SCHEMA.COLUMNS` check and a prepared statement so the column add would remain conditional without relying on unsupported syntax.

### Step 6 — import the patched migration manually

Once credentials worked and the DB container was healthy again, the patched migration was copied to the VPS and imported manually.

### Step 7 — restart the app and verify public behavior

After import, the app container was restarted and the live route was checked again.

Successful verification included:

- `/signal` returned `HTTP 200`
- the live UI no longer showed the “messaging not initialized” state
- the new tables existed
- `lands.notification_email` existed

## 5. Final schema proof

Recovery was considered successful only after all of the following were true:

- `signal_mailboxes` exists
- `signal_messages` exists
- `lands.notification_email` exists
- public `/signal` renders normal messaging UI

## 6. Operational lessons

1. **DB state is its own deploy layer.** App code can be correct while production still fails because schema state lags behind.
2. **Existing MySQL volumes do not replay init scripts.** Mounted migration files are not magic after first boot.
3. **A directory accidentally created where a file should live can poison a bind mount.** Recreate the affected container after fixing the path.
4. **Compose env values are not proof of real DB credentials.** The volume wins if it was initialized earlier with different credentials.
5. **Test migration syntax against the actual production MySQL.** “Works in theory” is not a database strategy.

## 7. Minimal repeatable response next time

If messaging looks uninitialized again, verify in this order:

1. public `/signal` output
2. presence of `/root/O_installation_FRESH/migrations/2026_05_02_signal_mail.sql`
3. DB login using the compose credentials
4. existence of the Signal tables and `lands.notification_email`
5. whether the DB container needs recreation after any bind mount path fix

Then cross-check with:

- `LIVE_VERIFICATION.md`
- `DB_MIGRATION_PROTOCOL.md`
- `ROLLBACK_PROTOCOL.md`

## 8. Current known-good state

At the end of this recovery:

- the migration file existed as a real file on the VPS
- DB credentials matched the compose configuration again
- the Signal schema was present
- the live `/signal` route responded normally

That restored messaging without wiping the persistent database volume.