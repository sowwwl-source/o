#!/usr/bin/env bash
set -euo pipefail

repo_root=""
env_file="deploy/.env.production"
project_name="sowwwl-o"
skip_container_checks=0

default_repo_root() {
	local script_dir
	script_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)
	printf '%s\n' "$(cd "$script_dir/.." && pwd -P)"
}

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

repo_root=$(default_repo_root)

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

assert_header_absent() {
	local url=${1:?Missing URL}
	local header_name=${2:?Missing header name}

	if read_header_values "$url" "$header_name" | grep -q '.'; then
		echo "Expected ${header_name} to be absent on ${url}" >&2
		exit 1
	fi
}

assert_status_code() {
	local url=${1:?Missing URL}
	local expected=${2:?Missing expected status}
	local actual

	actual=$(curl -sS -o /dev/null -I -w '%{http_code}' "$url")
	if [[ "$actual" != "$expected" ]]; then
		echo "Expected ${url} to return ${expected}, got ${actual}" >&2
		exit 1
	fi
}

assert_body_matches() {
	local url=${1:?Missing URL}
	local pattern=${2:?Missing pattern}
	local tmp_file
	tmp_file=$(mktemp)

	curl -sS "$url" -o "$tmp_file"
	if ! grep -qE "$pattern" "$tmp_file"; then
		rm -f "$tmp_file"
		echo "Expected ${url} body to match ${pattern}" >&2
		exit 1
	fi

	rm -f "$tmp_file"
}

assert_body_absent() {
	local url=${1:?Missing URL}
	local pattern=${2:?Missing pattern}
	local tmp_file
	tmp_file=$(mktemp)

	curl -sS "$url" -o "$tmp_file"
	if grep -qE "$pattern" "$tmp_file"; then
		rm -f "$tmp_file"
		echo "Expected ${url} body to avoid ${pattern}" >&2
		exit 1
	fi

	rm -f "$tmp_file"
}

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

	signal_delivery=$(read_env_value "SOWWWL_SIGNAL_IDENTITY_DELIVERY")
	magic_delivery=$(read_env_value "SOWWWL_MAGIC_LINK_DELIVERY")

	printf '%s\n' "--require-schema-ready"
	printf '%s\n' "--require-runtime-ready"
	if [[ "${signal_delivery,,}" == "mail" || "${magic_delivery,,}" == "mail" ]]; then
		printf '%s\n' "--require-delivery-ready"
	fi
}

should_verify_0wlslw0_agent() {
	local endpoint

	endpoint=$(read_env_value "SOWWWL_0WLSLW0_AGENT_ENDPOINT")
	[[ -n "$endpoint" ]]
}

should_verify_pi_host() {
	local public_origin
	local api_origin

	public_origin=$(origin_from_url "$(read_env_value "SOWWWL_PUBLIC_ORIGIN")")
	api_origin=$(origin_from_url "$(read_env_value "API_PUBLIC_BASE_URL")")

	[[ "$public_origin" == "pi.sowwwl.cloud" || "$api_origin" == "pi.sowwwl.cloud" ]]
}

if [[ $skip_container_checks -eq 0 ]]; then
	echo "==> Verifying runtime helpers inside app container"
	docker exec "$app_container" php /var/www/html/scripts/check_spatial_surface.php --require-ready >/dev/null
	docker exec "$app_container" php /var/www/html/scripts/check_media_readers.php --require-ready >/dev/null
	mapfile -t signal_args < <(signal_validation_args)
	docker exec "$app_container" php /var/www/html/scripts/check_signal_validation.php "${signal_args[@]}" >/dev/null

	if should_verify_0wlslw0_agent; then
		echo "==> Verifying 0wlslw0 remote relay"
		docker exec "$app_container" php /var/www/html/scripts/check_0wlslw0_agent.php --require-remote-ok
	else
		echo "==> 0wlslw0 remote relay not configured in $env_path (local fallback remains available)"
	fi
fi

echo "==> Verifying public contract"
public_shell_url=$(resolve_versioned_asset_url https://sowwwl.com/ public-shell.js)
main_js_url=$(resolve_versioned_asset_url https://sowwwl.com/str3m main.js)
main_str3m_url=$(resolve_versioned_asset_url https://sowwwl.com/str3m main.str3m.js)
main_sceptre_url=$(resolve_versioned_asset_url https://sowwwl.com/ main.sceptre.js)
main_landscape_url=$(resolve_versioned_asset_url https://sowwwl.com/ main.landscape.js)
main_island_url=$(resolve_versioned_asset_url https://sowwwl.com/ main.island.js)
main_pages_url=$(resolve_versioned_asset_url https://sowwwl.com/ main.pages.js)

curl -fsSI https://sowwwl.com/
curl -fsSI https://sowwwl.com/robots.txt
curl -fsSI https://sowwwl.com/sitemap.xml
curl -fsSI https://0wlslw0.com/
curl -fsSI https://0wlslw0.com/robots.txt
curl -fsSI https://0wlslw0.com/sitemap.xml
curl -fsSI https://sowwwl.io/
curl -fsSI https://www.sowwwl.io/
curl -fsSI https://sowwwl.cloud/
curl -fsSI https://www.sowwwl.cloud/
curl -fsSI https://sowwwl.xyz/
curl -fsSI https://sowwwl.xyz/map
curl -fsSI https://sowwwl.com/signal
curl -fsSI https://sowwwl.com/str3m
curl -fsSI https://sowwwl.com/rejoindre
curl -fsSI 'https://sowwwl.com/island?u=pablo-espallergues'
curl -fsSI https://sowwwl.com/0wlslw0
curl -fsSI https://sowwwl.com/icons/icon.svg
curl -fsSI https://sowwwl.com/icons/icon-192.png
curl -fsSI 'https://sowwwl.com/manifest.php?app=owl'
curl -fsSI https://sowwwl.org/
curl -fsSI https://api.sowwwl.cloud/healthz
curl -fsSI https://api.sowwwl.cloud/v1/status
curl -fsSI "$public_shell_url"
curl -fsSI "$main_js_url"
curl -fsSI "$main_str3m_url"
curl -fsSI "$main_sceptre_url"
curl -fsSI "$main_landscape_url"
curl -fsSI "$main_island_url"
curl -fsSI "$main_pages_url"

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

assert_body_matches https://sowwwl.com/ 'Trois portes : public, terre, 0wlslw0|Passer par 0wlslw0|commande noyau'
assert_body_matches https://sowwwl.com/robots.txt 'Sitemap: https://sowwwl\.com/sitemap\.xml'
assert_body_matches https://sowwwl.com/sitemap.xml '<loc>https://sowwwl\.com/</loc>|<loc>https://sowwwl\.com/str3m</loc>'
assert_body_matches https://0wlslw0.com/ 'Entrer sans se perdre|guide des passages|Parler à 0wlslw0'
assert_body_matches https://0wlslw0.com/robots.txt 'Sitemap: https://0wlslw0\.com/sitemap\.xml'
assert_body_matches https://0wlslw0.com/sitemap.xml '<loc>https://0wlslw0\.com/</loc>'
assert_body_matches "$public_shell_url" 'querySelectorAll\("\.reveal"\)|public-shell'
assert_body_absent "$public_shell_url" 'requestIdleCallback'
assert_body_matches "$main_js_url" 'runPageInit\("xyzCamera",[[:space:]]*initXyzCamera\);?'
assert_body_matches "$main_js_url" 'const[[:space:]]+hasRecognition[[:space:]]*=[[:space:]]*Boolean\(RecognitionCtor\);?'
assert_body_matches "$main_str3m_url" 'function[[:space:]]+initStr3mIntegratedPlayer\(\)|function[[:space:]]+bindStr3mIntegratedPlayer\('
assert_body_matches "$main_sceptre_url" 'function[[:space:]]+initSceptreConsole\(\)|function[[:space:]]+initPocketCameraPanels\('
assert_body_matches "$main_landscape_url" 'function[[:space:]]+initLandscapeChoirs\(\)'
assert_body_matches "$main_island_url" 'function[[:space:]]+initIslandReaderStation\(\)|function[[:space:]]+initIslandReaderFullscreen\(\)'
assert_body_matches "$main_pages_url" 'runPageInit\("landscapeChoirs",[[:space:]]*initLandscapeChoirs\);?'
assert_body_matches "$main_pages_url" 'runPageInit\("sceptreConsole",[[:space:]]*initSceptreConsole\);?'
assert_body_matches "$main_pages_url" 'runPageInit\("str3mIntegratedPlayer",[[:space:]]*initStr3mIntegratedPlayer\);?'
assert_body_matches "$main_pages_url" 'runPageInit\("guideVoice",[[:space:]]*initGuideVoice\);?'
assert_body_matches https://sowwwl.com/str3m 'data-str3m-player-engine|data-str3m-player-source-state|ouvrir la source'
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
assert_header_absent https://0wlslw0.com set-cookie
assert_header_absent 'https://sowwwl.com/manifest.php?app=owl' set-cookie

if should_verify_pi_host; then
	curl -fsSI https://pi.sowwwl.cloud/
	curl -fsSI https://pi.sowwwl.cloud/healthz
	curl -fsSI https://pi.sowwwl.cloud/v1/status
	curl -fsSI https://pi.sowwwl.cloud/camera/pi3-camera-01
	curl -fsSI https://pi.sowwwl.cloud/sceptre/ensemble
	assert_body_matches https://pi.sowwwl.cloud/v1/status '"service"[[:space:]]*:[[:space:]]*"pi\.sowwwl\.cloud"'
	assert_body_matches https://pi.sowwwl.cloud/v1/status '"openapi"[[:space:]]*:[[:space:]]*"https://pi\.sowwwl\.cloud/docs/AzA_v0\.7_openapi\.min\.yaml"'
	assert_body_matches https://pi.sowwwl.cloud/camera/pi3-camera-01 'Fen.tre harmonique'
	assert_body_matches https://pi.sowwwl.cloud/sceptre/ensemble 'Sceptre harmonique|pi\.sowwwl\.cloud'
fi

assert_single_header https://sowwwl.com/ cross-origin-opener-policy
assert_single_header https://sowwwl.com/ cross-origin-resource-policy
assert_single_header https://sowwwl.com/ x-permitted-cross-domain-policies
assert_single_header https://sowwwl.xyz/ cross-origin-opener-policy
assert_single_header https://sowwwl.xyz/ cross-origin-resource-policy
assert_single_header https://sowwwl.xyz/ x-permitted-cross-domain-policies
assert_single_header https://sowwwl.io/ cross-origin-opener-policy
assert_single_header https://sowwwl.io/ cross-origin-resource-policy
assert_single_header https://sowwwl.io/ x-permitted-cross-domain-policies
assert_single_header https://0wlslw0.com cross-origin-opener-policy
assert_single_header https://0wlslw0.com cross-origin-resource-policy
assert_single_header https://0wlslw0.com x-permitted-cross-domain-policies

assert_header_contains https://sowwwl.com/ permissions-policy 'microphone=\(self\)'
assert_header_contains https://sowwwl.com/ permissions-policy 'screen-wake-lock=\(self\)'
assert_header_contains https://sowwwl.io/ permissions-policy 'accelerometer=\(self\)'
assert_header_contains https://sowwwl.io/ permissions-policy 'camera=\(self\)'
assert_header_contains https://sowwwl.io/ permissions-policy 'microphone=\(self\)'
assert_header_contains https://sowwwl.io/ permissions-policy 'screen-wake-lock=\(self\)'
assert_header_contains https://sowwwl.xyz/ permissions-policy 'accelerometer=\(self\)'
assert_header_contains https://sowwwl.xyz/ permissions-policy 'ambient-light-sensor=\(self\)'
assert_header_contains https://sowwwl.xyz/ permissions-policy 'camera=\(self\)'
assert_header_contains https://sowwwl.xyz/ permissions-policy 'gyroscope=\(self\)'
assert_header_contains https://sowwwl.xyz/ permissions-policy 'magnetometer=\(self\)'
assert_header_contains https://sowwwl.xyz/ permissions-policy 'microphone=\(self\)'
assert_header_contains https://sowwwl.xyz/ permissions-policy 'screen-wake-lock=\(self\)'

if [[ $skip_container_checks -eq 0 ]]; then
	docker inspect "$caddy_container" --format '{{range .Mounts}}{{println .Source " -> " .Destination}}{{end}}' | grep '/srv/sites' >/dev/null
fi

echo "==> Public contract verified"
