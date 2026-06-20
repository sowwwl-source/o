# Next session

## À reprendre

### Apparitions “rareté”
- Déjà en place dans `main.js` :
  - table `APPARITION_ENTRYPOINTS`
  - `rarityChance()`
  - `rarityCooldownMs()`
  - `rarityWeight()`
- Ajustement repris cette session :
  - `INSTALL` n’apparaît plus quand une session a déjà basculé en contexte membre, y compris depuis des chemins auth-only comme `/echo` ou `/port/...`
  - sur `/install`, les portes auth-only (`LAND`, `SHORE`, `BATO`, `DASHBOARD`, `SILENCE`) ne sont plus proposées comme si elles étaient ouvertes
  - cadence retunée : les apparitions montrent maintenant 1 à 2 portes au lieu d’un petit paquet quasi systématique
  - `LAND` et `SHORE` ne monopolisent plus ensemble le bandeau : une seule entrée “anchor” peut sortir à la fois
  - `AZA`, `SILENCE` et `INSTALL` ont été remontés en fréquence relative pour redevenir perceptibles en usage
  - la porosité passe maintenant aussi par de vraies portes publiques : `STR3M` et `SIGNAL` peuvent apparaître, avec quota propre pour éviter un bandeau trop chargé
- À affiner ensuite :
  - décider si certaines surfaces “mixed” publiques doivent continuer à laisser apparaître des portes membre (`LAND`, `SHORE`, `SILENCE`) ou si elles doivent devenir plus strictes
  - si besoin, distinguer plus explicitement les routes “core” et les routes “spectrales” au lieu de rester sur un seul tirage pondéré

### Rappel comportement
- Les “apparitions” sont un bandeau fixe bas, avec reveal lettre par lettre, cliquable, visible ~11s.
- Elles pointent vers les routes clean (`/install`, `/land`, `/shore`, `/bato`, `/dashboard`, `/aza`, `/silence`, `/signal`, `/str3m`) gérées par `/.htaccess`.
