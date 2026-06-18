#!/usr/bin/env bash
set -euo pipefail

repo_root=""
env_file="deploy/.env.production"
project_name="sowwwl-o"
skip_container_checks=0
script_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)
. "$script_dir/shell_common_helpers.sh"
. "$script_dir/app_runtime_contract_helpers.sh"
. "$script_dir/bundle_contract_helpers.sh"
. "$script_dir/http_contract_helpers.sh"

usage() {
	cat <<'EOF'
Usage:
	scripts/verify_public_contract.sh
	scripts/verify_public_contract.sh --root /root/O_installation_FRESH/o
	scripts/verify_public_contract.sh --project-name sowwwl-o
	scripts/verify_public_contract.sh --skip-container-checks

What this script verifies:
  - core public routes on sowwwl.com, sowwwl.io, sowwwl.xyz, sowwwl.cloud, sowwwl.org, and 0wlslw0.com
  - robots, sitemap, manifest, custom 404, and canonical redirect behavior
  - current public copy markers and key JS assets
  - Signal readiness and 0wlslw0 relay health when configured
  - Pi-edge routes when the live env is configured to expose pi.sowwwl.cloud
EOF
}

repo_root=$(script_parent_repo_root "${BASH_SOURCE[0]}")

while [[ $# -gt 0 ]]; do
	case "$1" in
		--root)
			repo_root=${2:?Missing value for --root}
			shift 2
			;;
		--env-file)
			env_file=${2:?Missing value for --env-file}
			shift 2
			;;
		--project-name)
			project_name=${2:?Missing value for --project-name}
			shift 2
			;;
		--skip-container-checks)
			skip_container_checks=1
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

env_path="$repo_root/$env_file"
app_container="${project_name}-app-1"
caddy_container="${project_name}-caddy-1"

if [[ ! -d "$repo_root/.git" ]]; then
	echo "Repository root does not look like a git checkout: $repo_root" >&2
	exit 1
fi

if [[ ! -f "$env_path" ]]; then
	echo "Missing env file: $env_path" >&2
	exit 1
fi

resolve_versioned_asset_url() {
	local page_url=${1:?Missing page URL}
	local asset_name=${2:?Missing asset name}
	local html
	local asset_path
	local escaped_asset_name

	html=$(curl -fsS -H 'Cache-Control: no-cache' "$page_url")
	escaped_asset_name=$(printf '%s' "$asset_name" | sed 's/[][(){}.^$*+?|\\/]/\\&/g')
	asset_path=$(printf '%s' "$html" | tr '\n' ' ' | grep -oE "/${escaped_asset_name}\\?v=[0-9]+" | head -n 1 || true)

	if [[ -z "$asset_path" ]]; then
		echo "Could not resolve versioned asset ${asset_name} from ${page_url}" >&2
		exit 1
	fi

	printf 'https://sowwwl.com%s\n' "$asset_path"
}

signal_validation_args() {
	local signal_delivery
	local magic_delivery

	signal_delivery=$(shell_read_env_value "$env_path" "SOWWWL_SIGNAL_IDENTITY_DELIVERY")
	magic_delivery=$(shell_read_env_value "$env_path" "SOWWWL_MAGIC_LINK_DELIVERY")

	printf '%s\n' "--require-schema-ready"
	printf '%s\n' "--require-runtime-ready"
	if [[ "$(shell_to_lower "$signal_delivery")" == "mail" || "$(shell_to_lower "$magic_delivery")" == "mail" ]]; then
		printf '%s\n' "--require-delivery-ready"
	fi
}

should_verify_0wlslw0_agent() {
	local endpoint

	endpoint=$(shell_read_env_value "$env_path" "SOWWWL_0WLSLW0_AGENT_ENDPOINT")
	[[ -n "$endpoint" ]]
}

should_verify_pi_host() {
	local public_origin
	local api_origin

	public_origin=$(shell_origin_from_url "$(shell_read_env_value "$env_path" "SOWWWL_PUBLIC_ORIGIN")")
	api_origin=$(shell_origin_from_url "$(shell_read_env_value "$env_path" "API_PUBLIC_BASE_URL")")

	[[ "$public_origin" == "pi.sowwwl.cloud" || "$api_origin" == "pi.sowwwl.cloud" ]]
}

detect_container_repo_root() {
	local container_name=${1:?Missing container name}
	local candidate

	for candidate in /var/www/html /var/www/html/o; do
		if docker exec "$container_name" test -f "$candidate/config.php" >/dev/null 2>&1 \
			&& docker exec "$container_name" test -d "$candidate/scripts" >/dev/null 2>&1; then
			printf '%s\n' "$candidate"
			return 0
		fi
	done

	return 1
}

if [[ $skip_container_checks -eq 0 ]]; then
	container_repo_root=""

	if ! shell_container_is_running "$app_container"; then
		echo "App container is not running: $app_container" >&2
		shell_read_lines_into_array running_app_containers < <(shell_list_running_app_containers)
		if [[ ${#running_app_containers[@]} -gt 0 ]]; then
			echo "Running app containers detected: ${running_app_containers[*]}" >&2
		fi
		echo "Retry with --project-name <compose-project> or use --skip-container-checks." >&2
		exit 1
	fi

	container_repo_root=$(detect_container_repo_root "$app_container" || true)
	if [[ -z "$container_repo_root" ]]; then
		echo "Could not locate repository root inside $app_container. Checked /var/www/html and /var/www/html/o." >&2
		exit 1
	fi

	echo "==> Verifying runtime helpers inside app container"
	app_runtime_require_standard_guard_files "$app_container" "$container_repo_root/scripts"
	app_runtime_run_spatial_surface_guard "$app_container" "$container_repo_root/scripts"
		shell_read_lines_into_array signal_args < <(signal_validation_args)
	app_runtime_run_standard_guard_suite "$app_container" "$container_repo_root/scripts" "${signal_args[@]}"

	if should_verify_0wlslw0_agent; then
		echo "==> Verifying 0wlslw0 remote relay"
		app_runtime_run_0wlslw0_remote_guard "$app_container" "$container_repo_root/scripts"
	else
		echo "==> 0wlslw0 remote relay not configured in $env_path (local fallback remains available)"
	fi
fi

echo "==> Verifying public contract"
bundle_resolve_public_asset_urls

assert_head_ok https://sowwwl.com/
assert_head_ok https://sowwwl.com/robots.txt
assert_head_ok https://sowwwl.com/sitemap.xml
assert_head_ok https://0wlslw0.com/
assert_head_ok https://0wlslw0.com/robots.txt
assert_head_ok https://0wlslw0.com/sitemap.xml
assert_head_ok https://sowwwl.io/
assert_head_ok https://www.sowwwl.io/
assert_head_ok https://sowwwl.cloud/
assert_head_ok https://www.sowwwl.cloud/
assert_head_ok https://sowwwl.xyz/
assert_head_ok https://sowwwl.xyz/map
assert_head_ok https://sowwwl.com/signal
assert_head_ok https://sowwwl.com/str3m
assert_head_ok https://sowwwl.com/rejoindre
assert_head_ok 'https://sowwwl.com/island?u=pablo-espallergues'
assert_head_ok https://sowwwl.com/0wlslw0
assert_head_ok https://sowwwl.com/icons/icon.svg
assert_head_ok https://sowwwl.com/icons/icon-192.png
assert_head_ok 'https://sowwwl.com/manifest.php?app=owl'
assert_head_ok https://sowwwl.org/
assert_head_ok https://api.sowwwl.cloud/healthz
assert_head_ok https://api.sowwwl.cloud/v1/status
bundle_assert_public_asset_heads

assert_status_code https://sowwwl.com/robots.txt 200
assert_status_code https://sowwwl.com/sitemap.xml 200
assert_status_code 'https://sowwwl.com/manifest.php?app=owl' 200
assert_status_code https://0wlslw0.com/robots.txt 200
assert_status_code https://0wlslw0.com/sitemap.xml 200
assert_status_code https://sowwwl.com/this-page-should-not-exist-xyz 404
assert_status_code https://sowwwl.com/index.php 308
assert_status_code https://sowwwl.com/map.php 308
assert_status_code https://sowwwl.com/rejoindre.php 308
assert_status_code https://sowwwl.com/0wlslw0.php 308

assert_body_matches https://sowwwl.com/ 'Trois portes suffisent|Voir le public|Poser une terre|Se faire guider|J’ai déjà une terre'
assert_body_matches https://sowwwl.com/robots.txt 'Sitemap: https://sowwwl\.com/sitemap\.xml'
assert_body_matches https://sowwwl.com/sitemap.xml '<loc>https://sowwwl\.com/</loc>|<loc>https://sowwwl\.com/str3m</loc>'
assert_body_matches https://0wlslw0.com/ 'Trouver la bonne première porte|Parler ou écrire|Lire le public'
assert_body_matches https://0wlslw0.com/robots.txt 'Sitemap: https://0wlslw0\.com/sitemap\.xml'
assert_body_matches https://0wlslw0.com/sitemap.xml '<loc>https://0wlslw0\.com/</loc>'
bundle_assert_public_js_contract
assert_body_matches https://sowwwl.com/str3m 'data-str3m-player-engine|data-str3m-player-source-state|ouvrir la source|Aucune écoute publique aujourd’hui|Lire aujourd’hui|Se faire guider'
assert_body_matches 'https://sowwwl.com/island?u=pablo-espallergues' 'data-island-reader-shell|data-str3m-player-engine|ouvrir la source'
assert_body_matches https://sowwwl.io/ 'monde instrument|Surface de jeu Terre et Mine|Mode casque web'
assert_body_matches https://sowwwl.io/ 'Perspective caméra|data-xyz-camera-facing-button="environment"'
assert_body_matches 'https://sowwwl.io/manifest.php?app=io&spatial=headset' '"name"[[:space:]]*:[[:space:]]*"SOWWWL IO"'
assert_body_matches 'https://sowwwl.io/manifest.php?app=io&spatial=headset' 'spatial=headset'
assert_header_contains https://www.sowwwl.io location '^https://sowwwl\.io/'
assert_body_matches https://sowwwl.cloud/ 'One network\. Accueil des fleurs\.|Open the product|Review validation layer'
assert_header_contains https://www.sowwwl.cloud location '^https://sowwwl\.cloud/'
assert_body_matches https://sowwwl.xyz/ 'Le tore écoute le monde réel|Activer la membrane|Silence web|Partager'
assert_body_matches https://sowwwl.xyz/ 'data-xyz-plasma-bridge="https://sowwwl\.xyz(/o)?/ingest/membrane"'
assert_body_absent https://sowwwl.xyz/ 'data-xyz-plasma-bridge="https://lab\.sowwwl\.cloud'
assert_body_matches https://sowwwl.xyz/map 'Le tore des terres actives|Console lexicale de la map|courants actifs'
assert_body_matches https://sowwwl.com/map '<link rel="canonical" href="https://sowwwl\.com/map"|<meta property="og:url" content="https://sowwwl\.com/map"|application/ld\+json'
assert_body_matches https://sowwwl.org/ 'Comprendre les domaines sans se perdre|carte des rôles|Ouvrir sowwwl\.com'
assert_body_matches https://sowwwl.com/this-page-should-not-exist-xyz '404|introuvable|Not Found|retour'
assert_body_matches 'https://sowwwl.com/manifest.php?app=owl' '"name"[[:space:]]*:[[:space:]]*"0wlslw0"|SOWWWL'
assert_body_matches https://api.sowwwl.cloud/v1/status '"service"[[:space:]]*:[[:space:]]*"api\.sowwwl\.cloud"'
assert_body_matches https://api.sowwwl.cloud/v1/status '"openapi"[[:space:]]*:[[:space:]]*"https://api\.sowwwl\.cloud/docs/AzA_v0\.7_openapi\.min\.yaml"'

assert_header_contains https://sowwwl.com/index.php location '^https://sowwwl\.com/$'
assert_header_contains https://sowwwl.com/map.php location '^https://sowwwl\.com/map$'
assert_header_contains https://sowwwl.com/rejoindre.php location '^https://sowwwl\.com/rejoindre$'
assert_header_contains https://sowwwl.com/0wlslw0.php location '^https://sowwwl\.com/0wlslw0$'
assert_header_contains https://sowwwl.com strict-transport-security 'max-age=31536000'
assert_header_contains https://0wlslw0.com strict-transport-security 'max-age=31536000'
assert_header_contains 'https://sowwwl.com/?connexion=1' cache-control 'no-store'
assert_header_contains https://sowwwl.com/ cache-control 'public, max-age='
assert_header_absent https://sowwwl.com/ set-cookie
assert_header_absent 'https://sowwwl.com/?connexion=1' set-cookie
assert_header_absent https://sowwwl.com/rejoindre set-cookie
assert_header_absent 'https://sowwwl.com/manifest.php?app=owl' set-cookie

if should_verify_pi_host; then
	assert_head_ok https://pi.sowwwl.cloud/
	assert_head_ok https://pi.sowwwl.cloud/healthz
	assert_head_ok https://pi.sowwwl.cloud/v1/status
	assert_head_ok https://pi.sowwwl.cloud/camera/pi3-camera-01
	assert_head_ok https://pi.sowwwl.cloud/sceptre/ensemble
	assert_body_matches https://pi.sowwwl.cloud/v1/status '"service"[[:space:]]*:[[:space:]]*"pi\.sowwwl\.cloud"'
	assert_body_matches https://pi.sowwwl.cloud/v1/status '"openapi"[[:space:]]*:[[:space:]]*"https://pi\.sowwwl\.cloud/docs/AzA_v0\.7_openapi\.min\.yaml"'
	assert_body_matches https://pi.sowwwl.cloud/camera/pi3-camera-01 'Fen.tre harmonique'
	assert_body_matches https://pi.sowwwl.cloud/sceptre/ensemble 'Sceptre harmonique|pi\.sowwwl\.cloud'
fi

assert_standard_cross_origin_headers https://sowwwl.com/
assert_standard_cross_origin_headers https://sowwwl.xyz/
assert_standard_cross_origin_headers https://sowwwl.io/
assert_standard_cross_origin_headers https://0wlslw0.com

assert_permissions_policy_self_directives https://sowwwl.com/ microphone screen-wake-lock
assert_permissions_policy_self_directives https://sowwwl.io/ accelerometer camera microphone screen-wake-lock
assert_permissions_policy_self_directives https://sowwwl.xyz/ \
	accelerometer ambient-light-sensor camera gyroscope magnetometer microphone screen-wake-lock

if [[ $skip_container_checks -eq 0 ]]; then
	if shell_container_exists "$caddy_container"; then
		docker inspect "$caddy_container" --format '{{range .Mounts}}{{println .Source " -> " .Destination}}{{end}}' | grep '/srv/sites' >/dev/null
	else
		echo "==> Skipping caddy mount check: container not present for project $project_name"
	fi
fi

echo "==> Public contract verified"
