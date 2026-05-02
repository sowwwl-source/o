# Ops index — O. live app

This is the master entry point for the operational workflow around the live O. app.

Use it when you need to know **which document to open next**.

## By moment

### I am starting a session
Open:
- `SESSION_RITUAL.md`

Use it to decide:
- which repo you are touching
- whether you are in `o/` or in the parent repo
- which deploy stack is in scope

### I am preparing a commit
Open:
- `COMMIT_PROTOCOL.md`

Use it to decide:
- whether the change is micro / standard / deploy-affecting
- how to split commits
- whether push-only is enough or VPS deploy is required

### I am about to deploy to production
Open:
- `DEPLOY_QUICKREF.md`

Use it for:
- VPS sync
- rebuild
- restart
- minimal post-deploy checks

### I want to verify production behavior
Open:
- `LIVE_VERIFICATION.md`

Use it for:
- public `curl` checks
- expected live behavior
- distinguishing stale image vs wrong proxy vs wrong content

### Production is worse and I may need to revert
Open:
- `ROLLBACK_PROTOCOL.md`

Use it for:
- deciding whether rollback is justified
- selecting a known-good commit
- rebuilding from a safe state

### A feature may be blocked by SQL schema state
Open:
- `DB_MIGRATION_PROTOCOL.md`

Use it for:
- fresh vs existing DB volume logic
- manual migration execution
- checking whether schema state matches code expectations

## By problem

### “I don’t know where to work”
Start with:
- `SESSION_RITUAL.md`

### “I changed code, now how do I package it?”
Start with:
- `COMMIT_PROTOCOL.md`

### “The code is pushed, now how do I roll it out?”
Start with:
- `DEPLOY_QUICKREF.md`

### “The deploy says it worked, but I don’t trust it”
Start with:
- `LIVE_VERIFICATION.md`

### “The feature is up, but it says tables/migration are missing”
Start with:
- `DB_MIGRATION_PROTOCOL.md`

### “The live site is worse and I need a controlled retreat”
Start with:
- `ROLLBACK_PROTOCOL.md`

## Recommended order in real life

Most sessions follow this sequence:

1. `SESSION_RITUAL.md`
2. `COMMIT_PROTOCOL.md`
3. `DEPLOY_QUICKREF.md`
4. `LIVE_VERIFICATION.md`

Only when needed:

5. `DB_MIGRATION_PROTOCOL.md`
6. `ROLLBACK_PROTOCOL.md`

## Short map

- **before work** → `SESSION_RITUAL.md`
- **before commit** → `COMMIT_PROTOCOL.md`
- **before deploy** → `DEPLOY_QUICKREF.md`
- **after deploy** → `LIVE_VERIFICATION.md`
- **if DB feels stale** → `DB_MIGRATION_PROTOCOL.md`
- **if production is degraded** → `ROLLBACK_PROTOCOL.md`

## Golden rule

Do not rely on memory if one of these documents already carries the decision path.

Open the right protocol, follow the shortest path, and let the documentation do part of the thinking.