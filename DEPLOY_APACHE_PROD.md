# Deploy production Apache direct (`/var/www/html`)

Ce fichier documente un **chemin serveur direct utile pour maintenance manuelle**, mais **pas le chemin principal actuellement servi au public** pour `sowwwl.com`.

## État réel

Le droplet expose encore un Apache avec `DocumentRoot /var/www/html`, mais le trafic public principal observé sur `sowwwl.com` passe aujourd’hui par :

- `sowwwl-o-caddy-1` sur `80/443`
- reverse proxy vers `sowwwl-o-app-1`
- source applicative sous `/var/www/sowwwl.com`
- rebuild/recreate du conteneur `app` pour rendre les changements effectifs publiquement

En pratique :

- modifier seulement `/var/www/html` peut mettre à jour une copie serveur
- mais cela **ne suffit pas** pour la version publique si le conteneur `sowwwl-o-app-1` n’est pas reconstruit
- pour la prod publique, le réflexe prioritaire reste `DEPLOY_QUICKREF.md`

## Quand utiliser ce document

Utiliser cette procédure seulement pour :

- inspection manuelle du docroot Apache
- maintenance locale hors trafic public principal
- comparaison entre docroot direct et source Docker

Si l’objectif est de mettre à jour ce que sert réellement `https://sowwwl.com`, préférer la stack Docker du dossier `deploy/` sous `/var/www/sowwwl.com`.

Les fichiers concernés restent souvent :

- `index.php`
- `main.js`
- `styles.css`
- `aza.php`
- `config.php`
- ou tout autre fichier PHP/CSS/JS réellement servi depuis `/var/www/html`

## Vérifier le docroot actif

Sur le serveur :

```bash
apachectl -S
```

On doit voir :

- `*:443 sowwwl.com`
- `*:80 sowwwl.com`
- `Main DocumentRoot: "/var/www/html"`

## Sauvegarde avant déploiement

Exemple pour la homepage :

```bash
STAMP=$(date +%F-%H%M)
mkdir -p "/root/backup-homepage-$STAMP"
cp /var/www/html/index.php "/root/backup-homepage-$STAMP/index.php"
cp /var/www/html/main.js "/root/backup-homepage-$STAMP/main.js"
cp /var/www/html/styles.css "/root/backup-homepage-$STAMP/styles.css"
```

Exemple pour `aZa` :

```bash
STAMP=$(date +%F-%H%M)
mkdir -p "/root/backup-aza-$STAMP"
cp /var/www/html/aza.php "/root/backup-aza-$STAMP/aza.php"
cp /var/www/html/config.php "/root/backup-aza-$STAMP/config.php"
```

## Copier depuis le Mac vers le serveur

Depuis la machine locale, envoyer les fichiers dans `/tmp/` du serveur.

## Chemin recommandé : one-shot local

⚠️ Ce helper copie dans `/var/www/html`. Il est utile pour maintenance directe, mais il ne remplace pas un rebuild de `sowwwl-o-app-1` quand le trafic public passe par Caddy → Docker.

Depuis la racine `o/`, utiliser le helper :

```bash
scripts/deploy_apache_prod.sh --execute --profile homepage
```

ou :

```bash
scripts/deploy_apache_prod.sh --execute --profile aza
```

ou pour déployer d’un coup les surfaces web principales :

```bash
scripts/deploy_apache_prod.sh --execute --profile full-web
```

Le script :

- vérifie que les fichiers locaux existent
- les envoie dans `/tmp/` sur le serveur via `scp`
- envoie aussi le script serveur `scripts/install_apache_prod.sh`
- lance automatiquement le script serveur via `ssh`
- vérifie ensuite les URLs live attendues pour le profil choisi

Le flux recommandé devient donc :

1. depuis le Mac : `scripts/deploy_apache_prod.sh --execute ...`
2. vérification locale des URLs servies

Pour la homepage, le helper vérifie automatiquement la présence de marqueurs comme `hero-backdrop`, `torus-shell` ou `data-torus-cloud` sur `https://sowwwl.com/`.

Pour `aZa`, il vérifie automatiquement :

- les marqueurs `gros ZIP`, `entrée directe` ou `upload.sowwwl.com` sur `https://sowwwl.com/aza.php`
- la réponse HTTPS de `https://upload.sowwwl.com/aza.php`

Pour `full-web`, il ajoute aussi une vérification simple de route sur `https://sowwwl.com/island?u=pablo-espallergues`.

Si on veut exécuter le déploiement sans attendre ces checks live, utiliser :

```bash
scripts/deploy_apache_prod.sh --execute --no-verify --profile homepage
```

## Profils disponibles

- `homepage` : `index.php`, `main.js`, `styles.css`
- `aza` : `aza.php`, `config.php`
- `full-web` : `index.php`, `land.php`, `island.php`, `aza.php`, `config.php`, `main.js`, `styles.css`, `manifest.json`, `site-sw.js`, `favicon.svg`

Utiliser `full-web` quand une évolution touche plusieurs surfaces publiques à la fois et qu’on veut garder un seul déploiement cohérent.

## Mode fallback : deux temps

Si l’exécution distante automatique n’est pas souhaitée ou si la session SSH impose une étape manuelle, utiliser le mode sans `--execute`.

Dans ce cas :

1. depuis le Mac : `scripts/deploy_apache_prod.sh ...`
2. sur le serveur : `bash /tmp/install_apache_prod.sh ...`

Il accepte aussi une liste manuelle de fichiers :

```bash
scripts/deploy_apache_prod.sh --execute --files index.php styles.css
```

Et un hôte différent si nécessaire :

```bash
scripts/deploy_apache_prod.sh --host user@server --execute --profile homepage
```

## Installer côté serveur avec le helper dédié

Une fois les fichiers uploadés dans `/tmp/`, utiliser le script compagnon sur le VPS.

Exemple homepage :

```bash
bash /tmp/install_apache_prod.sh --profile homepage
```

Exemple `aZa` :

```bash
bash /tmp/install_apache_prod.sh --profile aza
```

Exemple `full-web` :

```bash
bash /tmp/install_apache_prod.sh --profile full-web
```

Exemple manuel :

```bash
bash /tmp/install_apache_prod.sh --files index.php styles.css
```

Le script serveur :

- vérifie que les fichiers sont bien présents dans `/tmp/`
- crée une sauvegarde horodatée dans `/root/backup-...`
- installe les fichiers dans `/var/www/html`
- lance les `php -l` utiles
- fait `apachectl configtest`
- fait `systemctl reload apache2`

## Copier manuellement avec `scp`

Exemple homepage :

```bash
scp \
  "/Users/pabloespallergues/Downloads/O_installation_FRESH/o/index.php" \
  "/Users/pabloespallergues/Downloads/O_installation_FRESH/o/main.js" \
  "/Users/pabloespallergues/Downloads/O_installation_FRESH/o/styles.css" \
  root@161.35.157.37:/tmp/
```

Exemple `aZa` :

```bash
scp \
  "/Users/pabloespallergues/Downloads/O_installation_FRESH/o/aza.php" \
  "/Users/pabloespallergues/Downloads/O_installation_FRESH/o/config.php" \
  root@161.35.157.37:/tmp/
```

## Installer dans `/var/www/html`

Sur le serveur :

```bash
install -m 644 /tmp/index.php /var/www/html/index.php
install -m 644 /tmp/main.js /var/www/html/main.js
install -m 644 /tmp/styles.css /var/www/html/styles.css
```

Adapter la liste aux fichiers réellement modifiés.

## Vérifications avant reload

### PHP

```bash
php -l /var/www/html/index.php
php -l /var/www/html/aza.php
php -l /var/www/html/config.php
```

### Apache

```bash
apachectl configtest
```

## Reload Apache

```bash
systemctl reload apache2
```

## Vérifications HTTP

### Homepage

```bash
curl -s https://sowwwl.com/ | grep -n "hero-backdrop\|torus-shell\|data-torus-cloud"
```

### aZa principal

```bash
curl -s https://sowwwl.com/aza.php | grep -n "gros ZIP\|entrée directe\|upload.sowwwl.com"
```

### aZa direct

```bash
curl -I https://upload.sowwwl.com/aza.php
```

## Cas particulier : gros ZIP

`sowwwl.com` peut être limité par Cloudflare pour les gros uploads si le host est proxifié.

Pour les archives lourdes, utiliser :

- `https://upload.sowwwl.com/aza.php`

Conditions pour que cela fonctionne :

- `upload.sowwwl.com` en **DNS only** dans Cloudflare
- certificat TLS valide incluant `upload.sowwwl.com`
- `/var/www/.env` contient :

```dotenv
SOWWWL_AZA_DIRECT_ORIGIN=https://upload.sowwwl.com
```

## Limitation actuelle connue

Le dépôt Git `sowwwl-source/o.git` est la source de vérité du code, mais la prod active n’est pas encore connectée à un clone Git sur le serveur.

Conséquence :

- `git push` **ne déploie pas** en prod
- il faut encore faire la **copie manuelle** vers `/var/www/html`

## Amélioration future recommandée

Quand le temps le permet, migrer la prod vers l’un de ces deux modèles :

1. un clone Git propre sur le serveur avec procédure de pull contrôlée
2. un vrai pipeline de release qui synchronise automatiquement `/var/www/html`
