#!/usr/bin/env bash
set -euo pipefail

prod_root=""
env_file="deploy/.env.production"
compose_file="deploy/docker-compose.prod.yml"
project_name="sowwwl-o"
branch="main"
static_sites_dir=""
verify=1

default_prod_root() {
	if [[ -d "/root/O_installation_FRESH/o/.git" ]]; then
		printf '%s\n' "/root/O_installation_FRESH/o"
		return
	fi

	if [[ -d "/var/www/sowwwl.com/.git" ]]; then
		printf '%s\n' "/var/www/sowwwl.com"
		return
	fi

	printf '%s\n' "/root/O_installation_FRESH/o"
}

default_static_sites_dir() {
	if [[ -d "/var/www/sowwwl.com/deploy/sites" ]]; then
		printf '%s\n' "/var/www/sowwwl.com/deploy/sites"
		return
	fi

	printf '%s\n' "$prod_root/deploy/sites"
}

usage() {
	cat <<'EOF'
Usage:
	scripts/deploy_prod_update.sh [--root /root/O_installation_FRESH/o] [--branch main]
	scripts/deploy_prod_update.sh [--static-sites-dir /var/www/sowwwl.com/deploy/sites]
	scripts/deploy_prod_update.sh [--no-verify]

What this script does:
  - updates the selected production git checkout
  - synchronizes deploy/sites/ into the live static-sites directory used by Caddy
  - rebuilds and restarts the live app container from o/deploy
  - verifies the main public routes, sowwwl.org copy markers, and Signal readiness

Examples:
	scripts/deploy_prod_update.sh
	scripts/deploy_prod_update.sh --root /root/O_installation_FRESH/o --static-sites-dir /var/www/sowwwl.com/deploy/sites
	scripts/deploy_prod_update.sh --branch main --no-verify
EOF
}

prod_root=$(default_prod_root)

while [[ $# -gt 0 ]]; do
	case "$1" in
		--root)
			prod_root=${2:?Missing value for --root}
			shift 2
			;;
		--env-file)
			env_file=${2:?Missing value for --env-file}
			shift 2
			;;
		--compose-file)
			compose_file=${2:?Missing value for --compose-file}
			shift 2
			;;
		--project-name)
			project_name=${2:?Missing value for --project-name}
			shift 2
			;;
		--branch)
			branch=${2:?Missing value for --branch}
			shift 2
			;;
		--static-sites-dir)
			static_sites_dir=${2:?Missing value for --static-sites-dir}
			shift 2
			;;
		--no-verify)
			verify=0
			shift
			;;
		-h|--help)
			usage
			exit 0
			;;
		*)
			echo "Unknown argument: $1" >&2
			usage >&2
			exit 1
			;;
	esac
done

if [[ ! -d "$prod_root/.git" ]]; then
	echo "Production root does not look like a git checkout: $prod_root" >&2
	exit 1
fi

if [[ -z "$static_sites_dir" ]]; then
	static_sites_dir=$(default_static_sites_dir)
fi

env_path="$prod_root/$env_file"
compose_path="$prod_root/$compose_file"
compose_dir=$(dirname "$compose_path")

compose_prod() {
	docker compose -p "$project_name" --env-file "$env_path" -f "$compose_path" "$@"
}

echo "==> Updating production checkout"
cd "$prod_root"
git fetch origin
git checkout "$branch"
git pull --ff-only origin "$branch"

if [[ ! -f "$env_path" ]]; then
	echo "Missing env file: $env_path" >&2
	exit 1
fi

if [[ ! -f "$compose_path" ]]; then
	echo "Missing compose file: $compose_path" >&2
	exit 1
fi

mkdir -p "$static_sites_dir"
	echo "==> Syncing static sites to $static_sites_dir"
	rsync -a --delete "$prod_root/deploy/sites/" "$static_sites_dir/"

echo "==> Rebuilding live app"
cd "$compose_dir"
compose_prod build app
compose_prod up -d app

echo "==> Live containers"
compose_prod ps app caddy

if [[ $verify -eq 0 ]]; then
	echo "Verification skipped (--no-verify)."
	exit 0
fi

echo "==> Public verification"
curl -fsSI https://sowwwl.com/
curl -fsSI https://sowwwl.com/signal
curl -fsSI https://sowwwl.com/str3m
curl -fsSI https://sowwwl.org/
curl -fsS https://sowwwl.com/ | grep -qE 'Pourquoi \.org \?|Comprendre la carte|sowwwl\.org'
curl -fsS https://sowwwl.org/ | grep -qE 'Comprendre les domaines sans se perdre|carte des rôles|Ouvrir sowwwl\.com'
docker exec "${project_name}-app-1" php /var/www/html/scripts/check_signal_validation.php --require-schema-ready --require-delivery-ready >/dev/null
docker inspect "${project_name}-caddy-1" --format '{{range .Mounts}}{{println .Source " -> " .Destination}}{{end}}' | grep '/srv/sites'

echo "==> Production deploy complete"
