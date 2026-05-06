#!/usr/bin/env bash
set -euo pipefail

lab_root=""
env_file="deploy-lab/.env.lab"
compose_file="deploy-lab/docker-compose.lab.yml"
project_name="sowwwl-o-lab"
branch="main"
verify=1
slug=""

default_lab_root() {
	if [[ -d "/opt/o-3ternet-lab/.git" ]]; then
		printf '%s\n' "/opt/o-3ternet-lab"
		return
	fi

	if [[ -d "/root/o-3ternet-lab/.git" ]]; then
		printf '%s\n' "/root/o-3ternet-lab"
		return
	fi

	printf '%s\n' "/opt/o-3ternet-lab"
}

lab_root=$(default_lab_root)

usage() {
	cat <<'EOF'
Usage:
	scripts/deploy_lab_update.sh [--root /opt/o-3ternet-lab] [--branch main] [--slug audrey]
	scripts/deploy_lab_update.sh [--root /root/o-3ternet-lab] [--no-verify]

What this script does:
  - updates the lab repo from the selected Git branch
  - rebuilds and restarts the lab stack from deploy-lab/
  - verifies that key public routes answer from the lab domains
  - optionally verifies the canonical island route when a slug is known
	- defaults to /opt/o-3ternet-lab, but falls back to /root/o-3ternet-lab if that checkout already exists

Examples:
  scripts/deploy_lab_update.sh --slug audrey
	scripts/deploy_lab_update.sh --root /opt/o-3ternet-lab --branch main
	scripts/deploy_lab_update.sh --root /root/o-3ternet-lab --slug audrey
  scripts/deploy_lab_update.sh --no-verify
EOF
}

while [[ $# -gt 0 ]]; do
	case "$1" in
		--root)
			lab_root=${2:?Missing value for --root}
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
		--slug)
			slug=${2:?Missing value for --slug}
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

if [[ ! -d "$lab_root/.git" ]]; then
	echo "Lab root does not look like a git checkout: $lab_root" >&2
	exit 1
fi

cd "$lab_root"

echo "==> Updating lab checkout"
git fetch origin
git checkout "$branch"
git pull --ff-only origin "$branch"

if [[ ! -f "$lab_root/$env_file" ]]; then
	echo "Missing env file: $lab_root/$env_file" >&2
	exit 1
fi

if [[ ! -f "$lab_root/$compose_file" ]]; then
	echo "Missing compose file: $lab_root/$compose_file" >&2
	exit 1
fi

echo "==> Rebuilding lab stack"
cd "$lab_root/deploy-lab"
docker compose -p "$project_name" --env-file .env.lab -f docker-compose.lab.yml up --build -d

echo "==> Lab containers"
docker compose -p "$project_name" --env-file .env.lab -f docker-compose.lab.yml ps

if [[ $verify -eq 0 ]]; then
	echo "Verification skipped (--no-verify)."
	exit 0
fi

echo "==> Public verification"
curl -I https://lab.sowwwl.cloud
curl -I https://lab.sowwwl.cloud/0wlslw0
curl -I https://lab.sowwwl.cloud/signal
curl -I https://lab.sowwwl.cloud/aza
curl -I https://pocket.lab.sowwwl.cloud
curl -I https://api.lab.sowwwl.cloud/healthz

echo "==> App internals"
docker exec "${project_name}-app-1" sh -lc 'ls -la /var/www/html/0wlslw0.php /var/www/html/signal.php /var/www/html/aza.php /var/www/html/island.php /var/www/html/echo.php'

if [[ -n "$slug" ]]; then
	echo "==> Island verification for slug: $slug"
	curl -I "https://lab.sowwwl.cloud/island?u=$slug"
	curl -I "https://lab.sowwwl.cloud/island.php?u=$slug"
fi

echo "==> Lab deploy complete"
