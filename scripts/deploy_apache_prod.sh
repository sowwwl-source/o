#!/usr/bin/env bash
set -euo pipefail

script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
project_root=$(cd -- "$script_dir/.." && pwd)

remote_host="root@161.35.157.37"
remote_stage_dir="/tmp"
profile=""
declare -a selected_files=()
server_script="$script_dir/install_apache_prod.sh"

usage() {
	cat <<'EOF'
Usage:
  scripts/deploy_apache_prod.sh [--host user@host] [--stage-dir /tmp] --profile homepage
  scripts/deploy_apache_prod.sh [--host user@host] [--stage-dir /tmp] --profile aza
  scripts/deploy_apache_prod.sh [--host user@host] [--stage-dir /tmp] --files index.php main.js styles.css

Profiles:
  homepage   Uploads index.php, main.js, styles.css
  aza        Uploads aza.php, config.php

Examples:
  scripts/deploy_apache_prod.sh --profile homepage
  scripts/deploy_apache_prod.sh --profile aza
  scripts/deploy_apache_prod.sh --host root@161.35.157.37 --files index.php styles.css

What this script does:
  - validates that each selected local file exists under the o/ project root
  - uploads the files to the remote staging directory with scp
	- uploads the companion server-side installer script
	- prints the exact command to run on the server

What this script does not do:
  - it does not modify /var/www/html remotely
  - it does not reload Apache automatically
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
		*)
			echo "Unknown profile: $1" >&2
			exit 1
			;;
	esac
}

while [[ $# -gt 0 ]]; do
	case "$1" in
		--host)
			remote_host=${2:?Missing value for --host}
			shift 2
			;;
		--stage-dir)
			remote_stage_dir=${2:?Missing value for --stage-dir}
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

declare -a absolute_files=()
declare -a basenames=()
if [[ ! -f "$server_script" ]]; then
	echo "Missing server-side installer script: $server_script" >&2
	exit 1
fi

for relative_path in "${selected_files[@]}"; do
	if [[ "$relative_path" = /* ]]; then
		echo "Use paths relative to the o/ project root, not absolute paths: $relative_path" >&2
		exit 1
	fi

	absolute_path="$project_root/$relative_path"
	if [[ ! -f "$absolute_path" ]]; then
		echo "Missing local file: $absolute_path" >&2
		exit 1
	fi

	absolute_files+=("$absolute_path")
	basenames+=("$(basename "$relative_path")")
done

printf 'Uploading to %s:%s\n' "$remote_host" "$remote_stage_dir"
printf 'Files:\n'
for absolute_path in "${absolute_files[@]}"; do
	printf '  - %s\n' "$absolute_path"
done
printf '  - %s\n' "$server_script"

scp "${absolute_files[@]}" "$server_script" "$remote_host:$remote_stage_dir/"

echo
echo "Upload complete. Run the following on the server:"
echo
if [[ -n "$profile" ]]; then
	printf 'bash %q --profile %q\n' "$remote_stage_dir/$(basename "$server_script")" "$profile"
else
	printf 'bash %q --files' "$remote_stage_dir/$(basename "$server_script")"
	for base in "${basenames[@]}"; do
		printf ' %q' "$base"
	done
	printf '\n'
fi

echo
echo 'optional live checks:'
if [[ "$profile" == "homepage" ]]; then
	printf '%s\n' "curl -s https://sowwwl.com/ | grep -n 'hero-backdrop\\|torus-shell\\|data-torus-cloud'"
elif [[ "$profile" == "aza" ]]; then
	printf '%s\n' "curl -s https://sowwwl.com/aza.php | grep -n 'gros ZIP\\|entrée directe\\|upload.sowwwl.com'"
	printf '%s\n' 'curl -I https://upload.sowwwl.com/aza.php'
else
	printf '%s\n' '# run the appropriate curl checks for the files you deployed'
fi
