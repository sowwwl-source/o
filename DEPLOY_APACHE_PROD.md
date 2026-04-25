# Deploy production Apache direct (`/var/www/html`)

Ce fichier documente le **vrai chemin de déploiement actuellement utilisé en production** pour `sowwwl.com`.

## État réel

La production active ne tourne **pas** depuis un clone Git ni depuis la stack Docker du dossier `deploy/`.

Elle tourne avec :

- Apache sur le droplet DigitalOcean
- `DocumentRoot /var/www/html`
- configuration runtime dans `/var/www/.env`
- mises à jour par **copie manuelle** de fichiers vers `/var/www/html`

## Quand utiliser ce document

Utiliser cette procédure quand on modifie en prod :

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
