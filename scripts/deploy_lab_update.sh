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
require_local_caddy=0
allow_cross_origin_plasma=0
preflight_only=0

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
	scripts/deploy_lab_update.sh [--require-local-caddy]
	scripts/deploy_lab_update.sh [--allow-cross-origin-plasma]
	scripts/deploy_lab_update.sh [--preflight-only]

What this script does:
  - updates the lab repo from the selected Git branch
  - rebuilds and restarts the lab stack from deploy-lab/
	- retries without the local caddy service when the host already uses another ingress or when /opt is mounted read-only
	- verifies that key public routes answer from the lab domains
	- optionally verifies the canonical island route when a slug is known
	- optionally sends a harmless physical-signal smoke event through /ingest/sensor
	- defaults to /opt/o-3ternet-lab, but falls back to /root/o-3ternet-lab if that checkout already exists
	- can require a fully local ingress with --require-local-caddy
	- refuses prod-facing plasma overrides unless --allow-cross-origin-plasma is passed
	- can stop after a safe build/config preflight with --preflight-only

Examples:
  scripts/deploy_lab_update.sh --slug audrey
	scripts/deploy_lab_update.sh --root /opt/o-3ternet-lab --branch main
	scripts/deploy_lab_update.sh --root /root/o-3ternet-lab --slug audrey
  scripts/deploy_lab_update.sh --skip-caddy --smoke-sensor
  scripts/deploy_lab_update.sh --require-local-caddy
  scripts/deploy_lab_update.sh --preflight-only
  scripts/deploy_lab_update.sh --no-verify
EOF
}

compose_dir=""
compose_path=""
env_path=""

compose_lab() {
	docker compose -p "$project_name" --env-file "$env_path" -f "$compose_path" "$@"
}

cleanup_conflicting_service_containers() {
	local service_pattern
	local line
	local container_id
	local container_name
	local -a services=("$@")
	local -a conflict_ids=()

	if [[ ${#services[@]} -eq 0 ]]; then
		return 0
	fi

	service_pattern=$(printf '%s|' "${services[@]}")
	service_pattern=${service_pattern%|}

	while IFS= read -r line; do
		container_id=${line%% *}
		container_name=${line#* }
		if [[ "$container_name" =~ (^|_)${project_name}-(${service_pattern})-1$ ]]; then
			conflict_ids+=("$container_id")
		fi
	done < <(docker ps -a --format '{{.ID}} {{.Names}}')

	if [[ ${#conflict_ids[@]} -eq 0 ]]; then
		return 0
	fi

	echo "==> Cleaning conflicting lab containers"
	docker ps -a --format '{{.ID}}\t{{.Names}}\t{{.Status}}' | awk -v project="$project_name" -v services="$service_pattern" '
		$2 ~ ("(^|_)" project "-(" services ")-1$") { print }
	'
	docker rm -f "${conflict_ids[@]}" >/dev/null
}

read_header_values() {
	local url=${1:?Missing URL}
	local header_name=${2:?Missing header name}
	local header_name_lc

	header_name_lc=$(printf '%s' "$header_name" | tr '[:upper:]' '[:lower:]')
	curl -fsSI "$url" | awk -F': ' -v header_name_lc="$header_name_lc" '
		tolower($1) == header_name_lc {
			sub(/\r$/, "", $2)
			print $2
		}
	'
}

assert_single_header() {
	local url=${1:?Missing URL}
	local header_name=${2:?Missing header name}
	local count

	count=$(read_header_values "$url" "$header_name" | awk 'END { print NR + 0 }')
	if [[ "$count" -ne 1 ]]; then
		echo "Expected a single ${header_name} header on ${url}, got ${count}" >&2
		exit 1
	fi
}

assert_header_contains() {
	local url=${1:?Missing URL}
	local header_name=${2:?Missing header name}
	local pattern=${3:?Missing pattern}

	if ! read_header_values "$url" "$header_name" | grep -qE "$pattern"; then
		echo "Expected ${header_name} on ${url} to match ${pattern}" >&2
		exit 1
	fi
}

assert_body_matches() {
	local url=${1:?Missing URL}
	local pattern=${2:?Missing pattern}

	if ! curl -fsS "$url" | grep -qE "$pattern"; then
		echo "Expected ${url} body to match ${pattern}" >&2
		exit 1
	fi
}

assert_body_absent() {
	local url=${1:?Missing URL}
	local pattern=${2:?Missing pattern}

	if curl -fsS "$url" | grep -qE "$pattern"; then
		echo "Expected ${url} body to avoid ${pattern}" >&2
		exit 1
	fi
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

origin_from_url() {
	local value=${1:-}
	value=${value#http://}
	value=${value#https://}
	value=${value%%/*}
	value=${value%%\?*}
	printf '%s' "${value,,}"
}

csv_contains_origin() {
	local csv=${1:-}
	local origin=${2:-}
	local item

	[[ -z "$csv" || -z "$origin" ]] && return 1

	IFS=',' read -r -a items <<<"${csv// /}"
	for item in "${items[@]}"; do
		if [[ "${item,,}" == "${origin,,}" ]]; then
			return 0
		fi
	done

	return 1
}

validate_plasma_split() {
	local public_origin
	local bridge_url
	local feed_url
	local allowed_origins
	local bridge_host
	local feed_host
	local host

	public_origin=$(read_env_value "SOWWWL_PUBLIC_ORIGIN")
	bridge_url=$(read_env_value "SOWWWL_MEMBRANE_BRIDGE_URL")
	feed_url=$(read_env_value "SOWWWL_PLASMA_FEED_URL")
	allowed_origins=$(read_env_value "SOWWWL_PLASMA_ALLOWED_ORIGINS")
	bridge_host=$(origin_from_url "$bridge_url")
	feed_host=$(origin_from_url "$feed_url")

	if [[ $allow_cross_origin_plasma -eq 1 ]]; then
		return 0
	fi

	for host in "$bridge_host" "$feed_host"; do
		if [[ -n "$host" && ! "$host" =~ (^|\.)lab\.sowwwl\.cloud$ ]]; then
			echo "Refusing cross-origin plasma target on lab: $host" >&2
			echo "Use --allow-cross-origin-plasma only if this split is intentional." >&2
			exit 1
		fi
	done

	for host in "https://sowwwl.com" "https://www.sowwwl.com" "https://sowwwl.xyz" "https://www.sowwwl.xyz" "https://sowwwl.cloud" "https://www.sowwwl.cloud" "https://0wlslw0.com" "https://www.0wlslw0.com"; do
		if csv_contains_origin "$allowed_origins" "$host"; then
			echo "Refusing prod-facing plasma allowlist entry on lab: $host" >&2
			echo "Use --allow-cross-origin-plasma only if this split is intentional." >&2
			exit 1
		fi
	done

	if [[ -n "$bridge_url" || -n "$feed_url" || -n "$allowed_origins" ]]; then
		echo "==> Plasma routing guard"
		echo "Lab plasma overrides are present and stay inside *.lab.sowwwl.cloud."
		[[ -n "$public_origin" ]] && echo "Public origin: $public_origin"
	fi
}

deploy_service_bundle() {
	local label=$1
	shift
	local output
	local retried_on_conflict=0

	echo "==> Rebuilding lab stack ($label)"
	while true; do
		if output=$(compose_lab up --build -d "$@" 2>&1); then
			printf '%s\n' "$output"
			return 0
		fi

		printf '%s\n' "$output" >&2
		if [[ $retried_on_conflict -eq 0 && "$output" == *"Error when allocating new name: Conflict."* ]]; then
			retried_on_conflict=1
			cleanup_conflicting_service_containers "$@"
			echo "==> Retrying lab stack after container cleanup"
			continue
		fi

		return 1
	done
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
		--require-local-caddy)
			require_local_caddy=1
			shift
			;;
		--allow-cross-origin-plasma)
			allow_cross_origin_plasma=1
			shift
			;;
		--preflight-only)
			preflight_only=1
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

if [[ "$skip_caddy" == "yes" && $require_local_caddy -eq 1 ]]; then
	echo "--skip-caddy and --require-local-caddy cannot be used together." >&2
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

validate_plasma_split

cd "$compose_dir"

echo "==> Validating compose config"
compose_lab config -q

echo "==> Building lab images (safe preflight)"
compose_lab build app pocket api db

if [[ $preflight_only -eq 1 ]]; then
	echo "Preflight complete (--preflight-only). No live containers were changed."
	exit 0
fi

if [[ "$skip_caddy" == "yes" ]]; then
	deployed_without_caddy=1
	deploy_service_bundle "without caddy (forced)" db api app pocket
elif [[ "$skip_caddy" == "no" ]]; then
	deploy_service_bundle "full stack" caddy db api app pocket
else
	if ! deploy_service_bundle "full stack" caddy db api app pocket; then
		if [[ $require_local_caddy -eq 1 ]]; then
			echo "Local caddy is required for this lab deploy, but the full stack did not start." >&2
			exit 1
		fi
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
Use --require-local-caddy on the next run if the split must fail closed instead of sharing ingress.
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
curl -fsSI https://api.lab.sowwwl.cloud/v1/status
assert_body_matches https://lab.sowwwl.cloud/ 'atelier du tore|Activer les capteurs|Fake pocket avant le Pi|Le téléphone devient membrane'
assert_body_matches https://lab.sowwwl.cloud/ 'data-lab-plasma-feed="https://lab\.sowwwl\.cloud(?:/o)?/plasma/recent"'
assert_body_absent https://lab.sowwwl.cloud/ 'Demander à Owl|Si tu reviens|Commence ici\. Trois portes suffisent'
assert_body_matches https://lab.sowwwl.cloud/0wlslw0 '0wlslw0|Accompagnement vocal|Comprendre le schéma'
assert_body_matches https://api.lab.sowwwl.cloud/v1/status '"service"[[:space:]]*:[[:space:]]*"api\.lab\.sowwwl\.cloud"'
assert_body_matches https://api.lab.sowwwl.cloud/v1/status '"openapi"[[:space:]]*:[[:space:]]*"https://api\.lab\.sowwwl\.cloud/docs/AzA_v0\.7_openapi\.min\.yaml"'
assert_single_header https://lab.sowwwl.cloud/ cross-origin-opener-policy
assert_single_header https://lab.sowwwl.cloud/ cross-origin-resource-policy
assert_single_header https://lab.sowwwl.cloud/ x-permitted-cross-domain-policies
assert_single_header https://pocket.lab.sowwwl.cloud/ cross-origin-opener-policy
assert_single_header https://pocket.lab.sowwwl.cloud/ cross-origin-resource-policy
assert_single_header https://pocket.lab.sowwwl.cloud/ x-permitted-cross-domain-policies
assert_header_contains https://lab.sowwwl.cloud/ permissions-policy 'accelerometer=\(self\)'
assert_header_contains https://lab.sowwwl.cloud/ permissions-policy 'ambient-light-sensor=\(self\)'
assert_header_contains https://lab.sowwwl.cloud/ permissions-policy 'camera=\(self\)'
assert_header_contains https://lab.sowwwl.cloud/ permissions-policy 'gyroscope=\(self\)'
assert_header_contains https://lab.sowwwl.cloud/ permissions-policy 'magnetometer=\(self\)'
assert_header_contains https://lab.sowwwl.cloud/ permissions-policy 'microphone=\(self\)'
assert_header_contains https://lab.sowwwl.cloud/ permissions-policy 'screen-wake-lock=\(self\)'

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
