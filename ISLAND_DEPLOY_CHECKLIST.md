# Island deploy checklist — classic rollout

Use this for the first public release of `island`.

## Before deploy

- Confirm `origin/main` contains `island.php`, the island rewrite rule, the `land` island links, and the `aZa` island link.
- Pick one real land slug you can safely use for verification.
- Confirm that land already has enough memory content to make the island readable.
- Confirm no DB migration is required for this rollout.

## Deploy

Follow `DEPLOY_QUICKREF.md`:

1. Sync VPS source
2. Rebuild the app image
3. Restart the production stack

## First checks after deploy

Replace `<slug-connu>` with the real slug selected before deploy.

```bash
curl -I 'https://sowwwl.com/island?u=<slug-connu>'
curl -sL 'https://sowwwl.com/island?u=<slug-connu>' | grep -E 'île classique|Relief|Finder mémoire|Dernières traces'
curl -sL 'https://sowwwl.com/land?u=<slug-connu>' | grep -E 'Île classique|Ouvrir l’île|finder|visuel'
curl -sL 'https://sowwwl.com/aza?u=<slug-connu>' | grep -E 'Ouvrir l’île|Voir le visible|Voir les provenances'
```

## Expected result

- `island?u=<slug-connu>` returns `200`
- the page shows the classic island header
- the page shows at least `Relief` and `Dernières traces`
- `land` exposes the island entry points
- `aZa` exposes the island opening link

## Manual click path

Check this sequence in a browser:

1. open `land?u=<slug-connu>`
2. click `Île classique` or `Ouvrir l’île`
3. confirm the island page opens correctly
4. click back to `aZa complet`
5. confirm the loop between land / aZa / island is intact

## If the island looks wrong

- confirm the slug really exists
- confirm `island.php` is present inside the app container
- confirm the live container uses the latest code
- test the same slug on `land`, `aza`, and `island`
- only rollback if the route is confirmed broken in production

## Related docs

- `DEPLOY_QUICKREF.md`
- `PROD_CHECKLIST.md`
- `LIVE_VERIFICATION.md`
- `ROLLBACK_PROTOCOL.md`