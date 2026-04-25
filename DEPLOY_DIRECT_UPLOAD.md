# Direct upload host for aZa

Objectif : permettre les très gros uploads ZIP en dehors du plafond imposé par le proxy principal.

## État actuel

- `aZa` accepte déjà jusqu'à `2 Go` côté app.
- Le serveur Apache/PHP en production accepte déjà environ `2 Go`.
- L'app lit désormais sa configuration depuis `/var/www/.env` (hors docroot) si le fichier existe.
- Le lien direct n'est **pas encore activé** tant que `upload.sowwwl.com` n'existe pas en DNS.

## Étape 1 — DNS

Dans le gestionnaire DNS / Cloudflare :

- créer `A upload.sowwwl.com -> 161.35.157.37`
- mettre le record en **DNS only** (pas proxied)

## Étape 2 — Apache

Ajouter `upload.sowwwl.com` comme alias sur les vhosts HTTP et HTTPS actifs.

Fichiers concernés sur le VPS :

- `/etc/apache2/sites-available/000-default.conf`
- `/etc/apache2/sites-available/000-default-le-ssl.conf`

Puis recharger Apache.

## Étape 3 — TLS

Une fois le DNS propagé :

- étendre le certificat Let's Encrypt existant pour inclure `upload.sowwwl.com`
- recharger Apache

## Étape 4 — Activer le lien dans l'app

Dans `/var/www/.env`, décommenter ou ajouter :

- `SOWWWL_AZA_DIRECT_ORIGIN=https://upload.sowwwl.com`

Puis recharger Apache si nécessaire.

## Résultat attendu

- `https://sowwwl.com/aza.php` continue de fonctionner normalement
- `https://sowwwl.com/aza.php` affiche un lien « gros ZIP : entrée directe »
- `https://upload.sowwwl.com/aza.php` devient l'entrée recommandée pour les archives très lourdes
