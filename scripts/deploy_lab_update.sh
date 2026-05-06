#!/usr/bin/env bash
set -euo pipefail

lab_root=""
env_file="deploy-lab/.env.lab"
compose_file="deploy-lab/docker-compose.lab.yml"
project_name="sowwwl-o-lab"
branch="main"
verify=1
slug=""
skip_caddy="auto"
sensor_smoke=0
deployed_without_caddy=0

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
	scripts/deploy_lab_update.sh [--skip-caddy] [--smoke-sensor]

What this script does:
  - updates the lab repo from the selected Git branch
  - rebuilds and restarts the lab stack from deploy-lab/
  - retries without the local caddy service when the host already uses another ingress or when /opt is mounted read-only
  - verifies that key public routes answer from the lab domains
  - optionally verifies the canonical island route when a slug is known
	- optionally sends a harmless physical-signal smoke event through /ingest/sensor
	- defaults to /opt/o-3ternet-lab, but falls back to /root/o-3ternet-lab if that checkout already exists

Examples:
  scripts/deploy_lab_update.sh --slug audrey
	scripts/deploy_lab_update.sh --root /opt/o-3ternet-lab --branch main
	scripts/deploy_lab_update.sh --root /root/o-3ternet-lab --slug audrey
  scripts/deploy_lab_update.sh --skip-caddy --smoke-sensor
  scripts/deploy_lab_update.sh --no-verify
EOF
}

compose_dir=""
compose_path=""
env_path=""

compose_lab() {
	docker compose -p "$project_name" --env-file "$env_path" -f "$compose_path" "$@"
}

read_env_value() {
	local key=$1
	local raw
	raw=$(grep -E "^${key}=" "$env_path" 2>/dev/null | tail -n 1 | cut -d '=' -f 2- || true)
	raw=${raw%$'\r'}
	raw=${raw#"\""}
	raw=${raw%"\""}
	raw=${raw#"'"}
	raw=${raw%"'"}
	printf '%s' "$raw"
}

deploy_service_bundle() {
	local label=$1
	shift
	local output

	echo "==> Rebuilding lab stack ($label)"
	if output=$(compose_lab up --build -d "$@" 2>&1); then
		printf '%s\n' "$output"
		return 0
	fi

	printf '%s\n' "$output" >&2
	return 1
}

run_sensor_smoke() {
	local sensor_token
	local sensor_message
	sensor_token=$(read_env_value "SOWWWL_PI_TOKEN")

	if [[ -z "$sensor_token" ]]; then
		echo "==> Sensor smoke skipped: SOWWWL_PI_TOKEN is not set in $env_path"
		return 0
	fi

	sensor_message="deploy_lab_update $(date -u +%FT%TZ)"
	echo "==> Sensor smoke test"
	curl -fsS -X POST https://lab.sowwwl.cloud/ingest/sensor \
		-H "Authorization: Bearer $sensor_token" \
		-H "Content-Type: application/json" \
		-d "{\"event\":\"lab_deploy_ping\",\"camera\":\"deploy\",\"message\":\"$sensor_message\"}" >/dev/null

	docker exec "${project_name}-app-1" sh -lc 'test -f /var/www/runtime/plasma/sensor-events.jsonl && tail -n 5 /var/www/runtime/plasma/sensor-events.jsonl || true'
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
		--skip-caddy)
			skip_caddy="yes"
			shift
			;;
		--with-caddy)
			skip_caddy="no"
			shift
			;;
		--smoke-sensor)
			sensor_smoke=1
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

env_path="$lab_root/$env_file"
compose_path="$lab_root/$compose_file"
compose_dir=$(dirname "$compose_path")

cd "$lab_root"

echo "==> Updating lab checkout"
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

cd "$compose_dir"

if [[ "$skip_caddy" == "yes" ]]; then
	deployed_without_caddy=1
	deploy_service_bundle "without caddy (forced)" db api app pocket
elif [[ "$skip_caddy" == "no" ]]; then
	deploy_service_bundle "full stack" caddy db api app pocket
else
	if ! deploy_service_bundle "full stack" caddy db api app pocket; then
		echo "==> Full-stack deploy failed; retrying without the local caddy service"
		deployed_without_caddy=1
		deploy_service_bundle "without caddy (fallback)" db api app pocket
	fi
fi

echo "==> Lab containers"
if [[ $deployed_without_caddy -eq 1 ]]; then
	compose_lab ps db api app pocket
	cat <<EOF
==> Local caddy skipped
The lab app/api/pocket services are running, but the local caddy service was skipped.
This is expected when another ingress already owns 80/443 or when the checkout lives on a read-only /opt mount.
Public verification still goes through the active ingress at lab.sowwwl.cloud.
EOF
else
	compose_lab ps
fi

if [[ $verify -eq 0 ]]; then
	echo "Verification skipped (--no-verify)."
	exit 0
fi

echo "==> Public verification"
curl -fsSI https://lab.sowwwl.cloud
curl -fsSI https://lab.sowwwl.cloud/0wlslw0
curl -fsSI https://lab.sowwwl.cloud/signal
curl -fsSI https://lab.sowwwl.cloud/aza
curl -fsSI https://pocket.lab.sowwwl.cloud
curl -fsSI https://api.lab.sowwwl.cloud/healthz

echo "==> App internals"
docker exec "${project_name}-app-1" sh -lc 'ls -la /var/www/html/0wlslw0.php /var/www/html/signal.php /var/www/html/aza.php /var/www/html/island.php /var/www/html/echo.php'

if [[ -n "$slug" ]]; then
	echo "==> Island verification for slug: $slug"
	curl -fsSI "https://lab.sowwwl.cloud/island?u=$slug"
	curl -fsSI "https://lab.sowwwl.cloud/island.php?u=$slug"
fi

if [[ $sensor_smoke -eq 1 ]]; then
	run_sensor_smoke
fi

echo "==> Lab deploy complete"
