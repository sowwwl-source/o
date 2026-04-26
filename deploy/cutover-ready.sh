#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "$0")" && pwd)"
repo_root="$(cd -- "$script_dir/.." && pwd)"
active_caddyfile="$script_dir/Caddyfile"
cutover_caddyfile="$script_dir/Caddyfile.cutover-ready"
backup_caddyfile="$script_dir/Caddyfile.full.backup"
env_file="$script_dir/.env.production"
compose_file="$script_dir/docker-compose.prod.yml"

if [[ ! -f "$cutover_caddyfile" ]]; then
  echo "Missing cutover Caddyfile: $cutover_caddyfile" >&2
  exit 1
fi

if [[ ! -f "$env_file" ]]; then
  echo "Missing production env file: $env_file" >&2
  exit 1
fi

if [[ -f "$active_caddyfile" ]]; then
  cp "$active_caddyfile" "$backup_caddyfile"
  echo "Backed up active Caddyfile to $backup_caddyfile"
fi

cp "$cutover_caddyfile" "$active_caddyfile"
echo "Applied cutover-ready Caddyfile"

cd "$repo_root"
docker compose --env-file "$env_file" -f "$compose_file" up --build -d

echo
echo "Production cutover stack started."
echo "Next recommended checks:"
echo "  docker compose --env-file deploy/.env.production -f deploy/docker-compose.prod.yml ps"
echo "  docker compose --env-file deploy/.env.production -f deploy/docker-compose.prod.yml logs --tail=100 caddy"
echo "  curl -I https://sowwwl.me/"
