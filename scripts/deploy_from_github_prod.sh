#!/usr/bin/env bash
set -euo pipefail

owner="sowwwl-source"
repo="o"
ref="main"
stage_dir="/tmp/github-deploy"
profile=""
declare -a selected_files=()

usage() {
	cat <<'EOF'
Usage:
  deploy_from_github_prod.sh [--owner sowwwl-source] [--repo o] [--ref main] --profile homepage
  deploy_from_github_prod.sh [--owner sowwwl-source] [--repo o] [--ref main] --profile aza
	deploy_from_github_prod.sh [--owner sowwwl-source] [--repo o] [--ref main] --profile full-web
  deploy_from_github_prod.sh [--owner sowwwl-source] [--repo o] [--ref main] --files index.php lib/security.php

What this script does:
  - downloads the selected files for a given Git ref directly from raw.githubusercontent.com
  - downloads scripts/install_apache_prod.sh from the same Git ref
  - stages everything under /tmp by default
  - runs the existing Apache installer locally on the server

Examples:
  bash deploy_from_github_prod.sh --ref main --profile homepage
  bash deploy_from_github_prod.sh --ref a43fd83 --files config.php index.php land.php aza.php logout.php styles.css lib/security.php lib/lands.php lib/aza_archive.php
EOF
}

profile_files() {
	case "$1" in
		homepage)
			printf '%s\n' index.php main.js styles.css
			;;
		aza)
			printf '%s\n' aza.php config.php
			;;
		full-web)
			printf '%s\n' index.php land.php aza.php config.php main.js styles.css manifest.json site-sw.js favicon.svg
			;;
		*)
			echo "Unknown profile: $1" >&2
			exit 1
			;;
	 esac
}

download_file() {
	local relative_path=$1
	local destination=$2
	local url="https://raw.githubusercontent.com/$owner/$repo/$ref/$relative_path"

	mkdir -p "$(dirname "$destination")"
	echo "Downloading $relative_path"
	curl --fail --silent --show-error --location "$url" --output "$destination"
}

while [[ $# -gt 0 ]]; do
	case "$1" in
		--owner)
			owner=${2:?Missing value for --owner}
			shift 2
			;;
		--repo)
			repo=${2:?Missing value for --repo}
			shift 2
			;;
		--ref)
			ref=${2:?Missing value for --ref}
			shift 2
			;;
		--stage-dir)
			stage_dir=${2:?Missing value for --stage-dir}
			shift 2
			;;
		--profile)
			profile=${2:?Missing value for --profile}
			shift 2
			;;
		--files)
			shift
			while [[ $# -gt 0 && $1 != --* ]]; do
				selected_files+=("$1")
				shift
			done
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

if [[ -n "$profile" && ${#selected_files[@]} -gt 0 ]]; then
	echo "Use either --profile or --files, not both." >&2
	exit 1
fi

if [[ -n "$profile" ]]; then
	while IFS= read -r file; do
		selected_files+=("$file")
	done < <(profile_files "$profile")
fi

if [[ ${#selected_files[@]} -eq 0 ]]; then
	echo "No files selected." >&2
	usage >&2
	exit 1
fi

rm -rf "$stage_dir"
mkdir -p "$stage_dir"

for relative_path in "${selected_files[@]}"; do
	if [[ "$relative_path" = /* ]]; then
		echo "Use repo-relative paths, not absolute paths: $relative_path" >&2
		exit 1
	fi

	download_file "$relative_path" "$stage_dir/$relative_path"
done

installer_path="$stage_dir/install_apache_prod.sh"
download_file "scripts/install_apache_prod.sh" "$installer_path"
chmod +x "$installer_path"

declare -a install_cmd=(bash "$installer_path" --stage-dir "$stage_dir")
if [[ -n "$profile" ]]; then
	install_cmd+=(--profile "$profile")
else
	install_cmd+=(--files)
	for relative_path in "${selected_files[@]}"; do
		install_cmd+=("$relative_path")
	done
fi

echo
echo "Running Apache installer from Git ref: $ref"
printf '%q ' "${install_cmd[@]}"
printf '\n\n'
"${install_cmd[@]}"