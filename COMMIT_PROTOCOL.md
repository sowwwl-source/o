# Commit protocol — O. live app

Use this protocol before every commit in `o/`.

## 1. Classify the change

Choose one of these three buckets.

### A. Micro change
Use when:
- wording or copy only
- one-file fix
- low-risk route or UI tweak
- no schema change
- no deploy-side config change

Typical message style:
- `Refine 0wlslw0 guide copy`
- `Fix map link in home UI`
- `Normalize host handling in app entrypoints`

### B. Standard app change
Use when:
- several app files changed together
- feature logic changed
- app routing changed
- runtime image contents changed

Typical message style:
- `Reintegrate 0wlslw0 into live app flow`
- `Add map route and points endpoint`
- `Convert Signal into mailbox UX`

### C. Deploy-affecting change
Use when:
- `o/deploy/` changed
- Docker build context changed
- Caddy routing changed
- MySQL service or env vars changed
- migration required

Typical message style:
- `Update deploy stack for Signal mailbox`
- `Route 0wlslw0.com to live app`
- `Add explicit Compose project name for o deploy`

## 2. Check the repo state

Run:

```bash
cd /Users/pabloespallergues/Downloads/O_installation_FRESH/o
git status --short
```

Ask:
- Are all changed files part of the same idea?
- Is any runtime noise included?
- Is any editor-local file included?
- Is this commit touching both app logic and deploy infra unnecessarily?

If the answer is yes to the last question, split the commit.

## 3. Stage intentionally

Prefer explicit staging over `git add .`.

```bash
git add <file1> <file2> <file3>
```

Good rule:
- one commit = one idea

## 4. Write the commit message

Format:

```text
<verb> <scope> <outcome>
```

Examples:
- `Refine 0wlslw0 onboarding copy`
- `Normalize host handling in live app`
- `Route 0wlslw0.com to live app`
- `Add session ritual and commit protocol docs`

Avoid vague messages like:
- `fix stuff`
- `updates`
- `wip`
- `deploy`

## 5. Decide if push is enough or deploy is required

### Push only
Use when:
- docs changed
- non-live helper docs changed
- local workflow notes changed
- code changed but no production rollout is intended yet

### Push + VPS deploy
Use when any of these changed:
- PHP files used by the live app
- `.htaccess`
- `o/deploy/Caddyfile`
- `o/deploy/docker-compose.prod.yml`
- `o/deploy/app/Dockerfile`
- files copied into `/var/www/html` at build time
- SQL migration required for the active feature

## 6. Commit sequence

### Minimal
```bash
git add <files>
git commit -m "<message>"
git push origin main
```

### If deploy is required
After push, use the deploy sequence from `SESSION_RITUAL.md`.

## 7. Recommended split strategy

If a session touched many layers, split like this:

1. app behavior
2. deploy/runtime config
3. docs / workflow notes

Example:
- commit 1: `Reintegrate 0wlslw0 into live app flow`
- commit 2: `Route 0wlslw0.com to live app`
- commit 3: `Document live deploy workflow`

## 8. Before pushing to main

Quick checklist:
- repo is the right one (`o/`)
- staged files match one coherent change
- no runtime files included
- no editor-only files included
- commit message states intent clearly
- if live deploy is needed, you know the VPS commands already

## 9. After pushing

Record mentally or in notes:
- pushed only, or pushed + deployed
- which domains need verification
- whether a migration is still pending
- whether the change touched app, deploy, or both

## 10. Golden rule

Do not let one commit mix:
- app feature logic
- unrelated infra cleanup
- random local files

Clean, small commits are easier to deploy, easier to verify, and much easier to undo when production gets theatrical.