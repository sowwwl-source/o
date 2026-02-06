## Déployer O. (PHP + MySQL)

### Avant de commencer
- Choisis la cible : **OVH mutualisé (FTPS /www)** ou **VPS (Docker)**.
- Prépare tes secrets (à ne jamais committer) : `DB_*`, `INVITE_CODES`, `AZA_API_TOKEN`.

---

## Option A — OVH mutualisé (FTPS /www)

### 1) Base de données
1. Crée une base MySQL + un utilisateur dans le manager OVH.
2. Importe `init.sql` (phpMyAdmin ou outil OVH) pour créer la table `lands`.

### 2) Configuration (`.env` côté serveur)
1. Crée un fichier `/www/.env` (il est ignoré par git).
2. Mets-y :
   - `DB_HOST=...`
   - `DB_NAME=...`
   - `DB_USER=...`
   - `DB_PASS=...`
   - `INVITE_CODES=...` (ex: `CODE1,CODE2`)
   - `AZA_API_BASE_URL=https://api.sowwwl.cloud`
   - `AZA_API_TOKEN=...`

> `config.php` charge automatiquement `/www/.env` si présent (sans écraser les variables déjà fournies par le serveur).

### 3) Upload des fichiers
1. Upload **uniquement** le code utile dans `/www` (pas de `.git/`, pas d’archives, pas de clés).
2. Assure-toi que `.htaccess` est bien uploadé à la racine.

### 4) Test
- Va sur `https://ton-domaine.tld/install`
- Puis `https://ton-domaine.tld/aza`
- Vérifie que les URLs propres (`/land`, `/shore`, etc.) fonctionnent.

---

## Option B — VPS (Docker)

### 1) Pré-requis
1. Installer Docker + le plugin Compose sur le serveur.
2. Ouvrir le firewall :
   - soit `80/443` (recommandé via reverse-proxy),
   - soit `8080` si tu exposes direct.

### 2) Déploiement
1. Copie le repo sur le serveur (git clone / rsync / scp).
2. Crée un `.env` à côté de `docker-compose.yml` (exemple : `.env.example`).
3. Lance :
   - `docker compose up --build -d`

### 3) Reverse proxy (recommandé)
- Mets Caddy/Nginx devant et proxy vers `127.0.0.1:8080`.

---

## Checklist “go live”
- `INVITE_CODES` changé (pas `TEST123`)
- `AZA_API_TOKEN` réel (si utilisé)
- Mots de passe DB forts
- Pas de clés/archives uploadées par erreur (la `.htaccess` bloque déjà `.env`, `.git`, etc.)

