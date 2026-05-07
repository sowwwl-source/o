# Next session

## À reprendre

### Apparitions “rareté”
- Déjà en place dans `main.js` :
  - table `APPARITION_ENTRYPOINTS`
  - `rarityChance()`
  - `rarityCooldownMs()`
  - `rarityWeight()`
- Ajustement repris cette session :
  - `INSTALL` n’apparaît plus quand on est déjà sur un chemin clairement membre (`/land`, `/shore`, `/bato`, `/dashboard`, `/silence`)
- À affiner ensuite :
  - décider si certains chemins auth-only doivent aussi être masqués sur `/install`
  - retuner la fréquence réelle des entrées `rare` / `mythic` si elles paraissent trop discrètes en usage

### Rappel comportement
- Les “apparitions” sont un bandeau fixe bas, avec reveal lettre par lettre, cliquable, visible ~11s.
- Elles pointent vers les routes clean (`/install`, `/land`, `/shore`, `/bato`, `/dashboard`, `/aza`, `/silence`) gérées par `/.htaccess`.
