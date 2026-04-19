# Next session

## À reprendre

### Apparitions “rareté”
- Objectif : décider quelles “entrées prévues par le programme” sont `common` / `uncommon` / `rare` / `mythic`.
- À modifier : `main.js` → fonction `pickApparitionTargets()` (table `entrypoints` avec `id`, `label`, `href`, `rarity`).
- Rareté = 3 paramètres (dans `main.js`) :
  - `rarityChance()` : probabilité d’apparition
  - `rarityCooldownMs()` : cooldown par entrée (évite les répétitions)
  - `rarityWeight()` : poids dans le tirage quand plusieurs entrées sont éligibles

### Rappel comportement
- Les “apparitions” sont un bandeau fixe bas, avec reveal lettre par lettre, cliquable, visible ~11s.
- Elles pointent vers les routes clean (`/install`, `/land`, `/shore`, `/bato`, `/dashboard`, `/aza`, `/silence`) gérées par `/.htaccess`.

