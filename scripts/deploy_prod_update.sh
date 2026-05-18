#!/usr/bin/env bash
set -euo pipefail

prod_root=""
env_file="deploy/.env.production"
compose_file="deploy/docker-compose.prod.yml"
project_name="sowwwl-o"
branch="main"
static_sites_dir=""
verify=1
allow_cross_origin_plasma=0
preflight_only=0

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
	scripts/deploy_prod_update.sh [--allow-cross-origin-plasma]
	scripts/deploy_prod_update.sh [--preflight-only]

What this script does:
  - updates the selected production git checkout
  - synchronizes deploy/sites/ into the live static-sites directory used by Caddy
  - validates the served static-sites directory before any live mutation
  - rebuilds the live app+api images before any live mutation
  - restarts the live app+api containers from o/deploy
  - ensures Caddy is up so bind-mounted proxy config changes are applied live
  - verifies the main public routes, sowwwl.org copy markers, Signal readiness, and the 0wlslw0 relay when configured
  - refuses lab-facing plasma overrides unless --allow-cross-origin-plasma is passed
  - can stop after a safe preflight with --preflight-only

Examples:
	scripts/deploy_prod_update.sh
	scripts/deploy_prod_update.sh --root /root/O_installation_FRESH/o --static-sites-dir /var/www/sowwwl.com/deploy/sites
	scripts/deploy_prod_update.sh --allow-cross-origin-plasma
	scripts/deploy_prod_update.sh --preflight-only
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
		--allow-cross-origin-plasma)
			allow_cross_origin_plasma=1
			shift
			;;
		--preflight-only)
			preflight_only=1
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

	echo "==> Cleaning conflicting live containers"
	docker ps -a --format '{{.ID}}\t{{.Names}}\t{{.Status}}' | awk -v project="$project_name" -v services="$service_pattern" '
		$2 ~ ("(^|_)" project "-(" services ")-1$") { print }
	'
	docker rm -f "${conflict_ids[@]}" >/dev/null
}

compose_prod_up_retry_conflicts() {
	local output
	local retried_on_conflict=0
	local -a services=("$@")

	while true; do
		if output=$(compose_prod up -d "${services[@]}" 2>&1); then
			printf '%s\n' "$output"
			return 0
		fi

		printf '%s\n' "$output" >&2
		if [[ $retried_on_conflict -eq 0 && "$output" == *"Error when allocating new name: Conflict."* ]]; then
			retried_on_conflict=1
			cleanup_conflicting_service_containers "${services[@]}"
			echo "==> Retrying live stack after container cleanup"
			continue
		fi

		return 1
	done
}

resolve_dir_path() {
	local dir=$1

	if [[ -d "$dir" ]]; then
		(
			cd "$dir"
			pwd -P
		)
		return
	fi

	(
		cd "$(dirname "$dir")"
		printf '%s/%s\n' "$(pwd -P)" "$(basename "$dir")"
	)
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
	local bridge_url
	local feed_url
	local allowed_origins
	local bridge_host
	local feed_host
	local host

	bridge_url=$(read_env_value "SOWWWL_MEMBRANE_BRIDGE_URL")
	feed_url=$(read_env_value "SOWWWL_PLASMA_FEED_URL")
	allowed_origins=$(read_env_value "SOWWWL_PLASMA_ALLOWED_ORIGINS")
	bridge_host=$(origin_from_url "$bridge_url")
	feed_host=$(origin_from_url "$feed_url")

	if [[ $allow_cross_origin_plasma -eq 1 ]]; then
		return 0
	fi

	for host in "$bridge_host" "$feed_host"; do
		if [[ -n "$host" && "$host" =~ (^|\.)lab\.sowwwl\.cloud$ ]]; then
			echo "Refusing lab-facing plasma target in production: $host" >&2
			echo "Use --allow-cross-origin-plasma only if this split is intentional." >&2
			exit 1
		fi
	done

	for host in "https://lab.sowwwl.cloud" "https://www.lab.sowwwl.cloud" "https://api.lab.sowwwl.cloud" "https://pocket.lab.sowwwl.cloud"; do
		if csv_contains_origin "$allowed_origins" "$host"; then
			echo "Refusing lab-facing plasma allowlist entry in production: $host" >&2
			echo "Use --allow-cross-origin-plasma only if this split is intentional." >&2
			exit 1
		fi
	done
}

validate_static_site_file() {
	local root_dir=${1:?Missing root directory}
	local relative_path=${2:?Missing relative path}
	local absolute_path="$root_dir/$relative_path"

	if [[ ! -s "$absolute_path" ]]; then
		echo "Missing or empty static site asset: $absolute_path" >&2
		exit 1
	fi
}

validate_static_sites_source() {
	local source_dir="$prod_root/deploy/sites"

	validate_static_site_file "$source_dir" "sowwwl.cloud/index.html"
	validate_static_site_file "$source_dir" "sowwwl.org/index.html"
	validate_static_site_file "$source_dir" "0.user.o.sowwwl.cloud/index.html"
	validate_static_site_file "$source_dir" "0wlslw0.com/index.html"
}

validate_static_sites_target() {
	local expected_dir
	local resolved_expected_dir
	local resolved_target_dir

	expected_dir="$compose_dir/sites"
	resolved_expected_dir=$(resolve_dir_path "$expected_dir")
	resolved_target_dir=$(resolve_dir_path "$static_sites_dir")

	if [[ "$resolved_target_dir" != "$resolved_expected_dir" ]]; then
		cat >&2 <<EOF
Refusing to sync static sites into an unserved directory.
Caddy serves: $resolved_expected_dir
Requested static-sites dir: $resolved_target_dir
Adjust --static-sites-dir or --compose-file so both point to the same live sites directory.
EOF
		exit 1
	fi
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

signal_validation_args() {
	local signal_delivery
	local magic_delivery

	signal_delivery=$(read_env_value "SOWWWL_SIGNAL_IDENTITY_DELIVERY")
	magic_delivery=$(read_env_value "SOWWWL_MAGIC_LINK_DELIVERY")

	printf '%s\n' "--require-schema-ready"
	if [[ "${signal_delivery,,}" == "mail" || "${magic_delivery,,}" == "mail" ]]; then
		printf '%s\n' "--require-delivery-ready"
	fi
}

should_verify_0wlslw0_agent() {
	local endpoint

	endpoint=$(read_env_value "SOWWWL_0WLSLW0_AGENT_ENDPOINT")
	[[ -n "$endpoint" ]]
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

validate_plasma_split

validate_static_sites_source
validate_static_sites_target

echo "==> Validating compose config"
compose_prod config -q

echo "==> Building live images (safe preflight)"
cd "$compose_dir"
compose_prod build app api

if [[ $preflight_only -eq 1 ]]; then
	echo "Preflight complete (--preflight-only). No live containers or static sites were changed."
	exit 0
fi

if [[ "$(resolve_dir_path "$prod_root/deploy/sites")" == "$(resolve_dir_path "$static_sites_dir")" ]]; then
	echo "==> Static sites already served from $static_sites_dir"
else
	mkdir -p "$static_sites_dir"
	echo "==> Syncing static sites to $static_sites_dir"
	rsync -a --delete-delay --delay-updates "$prod_root/deploy/sites/" "$static_sites_dir/"
	validate_static_site_file "$static_sites_dir" "sowwwl.cloud/index.html"
	validate_static_site_file "$static_sites_dir" "sowwwl.org/index.html"
	validate_static_site_file "$static_sites_dir" "0.user.o.sowwwl.cloud/index.html"
	validate_static_site_file "$static_sites_dir" "0wlslw0.com/index.html"
fi

echo "==> Refreshing live app/api"
compose_prod_up_retry_conflicts app api

echo "==> Ensuring live proxy is up"
compose_prod_up_retry_conflicts caddy

echo "==> Live containers"
compose_prod ps app api caddy

if [[ $verify -eq 0 ]]; then
	echo "Verification skipped (--no-verify)."
	exit 0
fi

echo "==> Public verification"
if should_verify_0wlslw0_agent; then
	echo "==> Verifying 0wlslw0 remote relay"
	docker exec "${project_name}-app-1" php /var/www/html/scripts/check_0wlslw0_agent.php --require-remote-ok
else
	echo "==> 0wlslw0 remote relay not configured in $env_path (local fallback remains available)"
fi
curl -fsSI https://sowwwl.com/
curl -fsSI https://sowwwl.xyz/
curl -fsSI https://sowwwl.xyz/map
curl -fsSI https://sowwwl.com/signal
curl -fsSI https://sowwwl.com/str3m
curl -fsSI https://sowwwl.com/0wlslw0
curl -fsSI https://sowwwl.com/icons/icon.svg
curl -fsSI https://sowwwl.com/icons/icon-192.png
curl -fsSI 'https://sowwwl.com/manifest.php?app=owl'
curl -fsSI https://sowwwl.org/
curl -fsSI https://api.sowwwl.cloud/healthz
curl -fsSI https://api.sowwwl.cloud/v1/status
assert_body_matches https://sowwwl.com/ 'Trois portes : public, terre, 0wlslw0|Passer par 0wlslw0|commande noyau'
assert_body_matches https://sowwwl.com/main.js 'runPageInit\("xyzCamera", initXyzCamera\);'
assert_body_matches https://sowwwl.com/main.js 'runPageInit\("guideVoice", initGuideVoice\);'
assert_body_matches https://sowwwl.com/main.js 'const hasRecognition = Boolean\(RecognitionCtor\);'
assert_body_matches https://sowwwl.xyz/ 'Le tore écoute le monde réel|Activer la membrane|Silence web|Partager'
assert_body_matches https://sowwwl.xyz/ 'data-xyz-plasma-bridge="https://sowwwl\.xyz(?:/o)?/ingest/membrane"'
assert_body_absent https://sowwwl.xyz/ 'data-xyz-plasma-bridge="https://lab\.sowwwl\.cloud'
assert_body_matches https://sowwwl.xyz/map 'Le tore des terres actives|Console lexicale de la map|courants actifs'
assert_body_matches https://sowwwl.org/ 'Comprendre les domaines sans se perdre|carte des rôles|Ouvrir sowwwl\.com'
assert_body_matches https://api.sowwwl.cloud/v1/status '"service"[[:space:]]*:[[:space:]]*"api\.sowwwl\.cloud"'
assert_body_matches https://api.sowwwl.cloud/v1/status '"openapi"[[:space:]]*:[[:space:]]*"https://api\.sowwwl\.cloud/docs/AzA_v0\.7_openapi\.min\.yaml"'
assert_single_header https://sowwwl.com/ cross-origin-opener-policy
assert_single_header https://sowwwl.com/ cross-origin-resource-policy
assert_single_header https://sowwwl.com/ x-permitted-cross-domain-policies
assert_single_header https://sowwwl.xyz/ cross-origin-opener-policy
assert_single_header https://sowwwl.xyz/ cross-origin-resource-policy
assert_single_header https://sowwwl.xyz/ x-permitted-cross-domain-policies
assert_single_header https://0wlslw0.com cross-origin-opener-policy
assert_single_header https://0wlslw0.com cross-origin-resource-policy
assert_single_header https://0wlslw0.com x-permitted-cross-domain-policies
assert_header_contains https://sowwwl.com/ permissions-policy 'microphone=\(self\)'
assert_header_contains https://sowwwl.com/ permissions-policy 'screen-wake-lock=\(self\)'
assert_header_contains https://sowwwl.xyz/ permissions-policy 'accelerometer=\(self\)'
assert_header_contains https://sowwwl.xyz/ permissions-policy 'ambient-light-sensor=\(self\)'
assert_header_contains https://sowwwl.xyz/ permissions-policy 'camera=\(self\)'
assert_header_contains https://sowwwl.xyz/ permissions-policy 'gyroscope=\(self\)'
assert_header_contains https://sowwwl.xyz/ permissions-policy 'magnetometer=\(self\)'
assert_header_contains https://sowwwl.xyz/ permissions-policy 'microphone=\(self\)'
assert_header_contains https://sowwwl.xyz/ permissions-policy 'screen-wake-lock=\(self\)'
mapfile -t signal_args < <(signal_validation_args)
docker exec "${project_name}-app-1" php /var/www/html/scripts/check_signal_validation.php "${signal_args[@]}" >/dev/null
docker inspect "${project_name}-caddy-1" --format '{{range .Mounts}}{{println .Source " -> " .Destination}}{{end}}' | grep '/srv/sites'

echo "==> Production deploy complete"
