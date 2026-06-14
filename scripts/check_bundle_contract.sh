#!/usr/bin/env bash
set -euo pipefail

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)
cd "$repo_root"

required_files=(
	main.js
	main.str3m.js
	main.sceptre.js
	main.landscape.js
	main.island.js
	main.pages.js
	public-shell.js
)

for file in "${required_files[@]}"; do
	if [[ ! -s "$file" ]]; then
		echo "Missing or empty bundle: $file" >&2
		exit 1
	fi
done

main_head=$(php -r 'require "config.php"; echo render_o_page_head_assets(null, null, ["script_bundle" => "main"]);')
public_shell_head=$(php -r 'require "config.php"; echo render_o_page_head_assets(null, null, ["script_bundle" => "public-shell"]);')

require_contains() {
	local haystack=${1:?Missing haystack}
	local needle=${2:?Missing needle}
	if ! grep -Fq "$needle" <<<"$haystack"; then
		echo "Missing expected fragment: $needle" >&2
		exit 1
	fi
}

line_of() {
	local haystack=${1:?Missing haystack}
	local needle=${2:?Missing needle}
	awk -v needle="$needle" 'index($0, needle) { print NR; exit }' <<<"$haystack"
}

assert_order() {
	local haystack=${1:?Missing haystack}
	shift
	local previous=0
	local needle
	for needle in "$@"; do
		local current
		current=$(line_of "$haystack" "$needle")
		if [[ -z "$current" ]]; then
			echo "Could not find ordered fragment: $needle" >&2
			exit 1
		fi
		if (( current <= previous )); then
			echo "Bundle order is invalid around: $needle" >&2
			exit 1
		fi
		previous=$current
	done
}

script_count=$(grep -c '<script defer src=' <<<"$public_shell_head")
if [[ "$script_count" -ne 1 ]]; then
	echo "Expected public-shell head to emit exactly one script tag, got $script_count" >&2
	exit 1
fi

require_contains "$main_head" 'meta name="o-main-bundle"'
require_contains "$main_head" 'meta name="o-main-str3m-bundle"'
require_contains "$main_head" 'meta name="o-main-sceptre-bundle"'
require_contains "$main_head" 'meta name="o-main-landscape-bundle"'
require_contains "$main_head" 'meta name="o-main-island-bundle"'
require_contains "$main_head" 'meta name="o-main-pages-bundle"'

assert_order "$main_head" \
	'<script defer src="/main.js' \
	'<script defer src="/main.str3m.js' \
	'<script defer src="/main.sceptre.js' \
	'<script defer src="/main.landscape.js' \
	'<script defer src="/main.island.js' \
	'<script defer src="/main.pages.js'

require_contains "$public_shell_head" 'meta name="o-main-bundle"'
require_contains "$public_shell_head" 'meta name="o-main-str3m-bundle"'
require_contains "$public_shell_head" 'meta name="o-main-sceptre-bundle"'
require_contains "$public_shell_head" 'meta name="o-main-landscape-bundle"'
require_contains "$public_shell_head" 'meta name="o-main-island-bundle"'
require_contains "$public_shell_head" 'meta name="o-main-pages-bundle"'
require_contains "$public_shell_head" '<script defer src="/public-shell.js'

echo "bundle-contract-ok"
