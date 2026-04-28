#!/usr/bin/env bash
set -euo pipefail

# Se place dans le dossier du projet pour que les chemins soient corrects
cd "$(dirname "$0")"

# Prépare le fichier d'environnement s'il n'existe pas
if [ ! -f "deploy/.env.production" ]; then
    echo "Création du fichier .env.production..."
    cp deploy/.env.production.example deploy/.env.production
fi

echo "Lancement de la stack Docker..."
docker compose \
    --env-file deploy/.env.production \
    -f deploy/docker-compose.prod.yml \
    up --build -d

echo -e "\n--- Statut des conteneurs ---"
docker compose --env-file deploy/.env.production -f deploy/docker-compose.prod.yml ps

echo -e "\n--- Logs de Caddy (pour vérifier le HTTPS) ---"
docker compose --env-file deploy/.env.production -f deploy/docker-compose.prod.yml logs caddy --tail=30

echo -e "\nDéploiement terminé. Vérifiez vos domaines dans le navigateur."