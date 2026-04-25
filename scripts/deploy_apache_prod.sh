#!/usr/bin/env bash
set -euo pipefail

script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
project_root=$(cd -- "$script_dir/.." && pwd)

remote_host="root@161.35.157.37"
remote_stage_dir="/tmp"
profile=""
execute_remote=0
verify_live=1
declare -a selected_files=()
server_script="$script_dir/install_apache_prod.sh"

usage() {
	cat <<'EOF'
Usage:
  scripts/deploy_apache_prod.sh [--host user@host] [--stage-dir /tmp] --profile homepage
  scripts/deploy_apache_prod.sh [--host user@host] [--stage-dir /tmp] --profile aza
	scripts/deploy_apache_prod.sh [--host user@host] [--stage-dir /tmp] --profile full-web
  scripts/deploy_apache_prod.sh [--host user@host] [--stage-dir /tmp] --files index.php main.js styles.css
	scripts/deploy_apache_prod.sh [--host user@host] [--stage-dir /tmp] --execute --profile homepage
	scripts/deploy_apache_prod.sh [--host user@host] [--stage-dir /tmp] --execute --no-verify --profile homepage

Profiles:
  homepage   Uploads index.php, main.js, styles.css
  aza        Uploads aza.php, config.php
	full-web   Uploads the main public PHP/CSS/JS surfaces served from /var/www/html

Examples:
  scripts/deploy_apache_prod.sh --profile homepage
  scripts/deploy_apache_prod.sh --profile aza
	scripts/deploy_apache_prod.sh --profile full-web
  scripts/deploy_apache_prod.sh --host root@161.35.157.37 --files index.php styles.css
	scripts/deploy_apache_prod.sh --execute --profile homepage
	scripts/deploy_apache_prod.sh --execute --no-verify --profile homepage

What this script does:
  - validates that each selected local file exists under the o/ project root
  - uploads the files to the remote staging directory with scp
	- uploads the companion server-side installer script
	- prints the exact command to run on the server
	- optionally runs that command over ssh with --execute
	- when using --execute, runs live HTTP verification by default

What this script does not do:
	- by default, it does not modify /var/www/html remotely
	- without --execute, it does not reload Apache automatically
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
		--execute|--remote-run)
			execute_remote=1
			shift
			;;
		--no-verify)
			verify_live=0
			shift
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

run_live_checks() {
	case "$1" in
		homepage)
			echo "Running live homepage verification..."
			if curl -fsS https://sowwwl.com/ | grep -qE 'hero-backdrop|torus-shell|data-torus-cloud'; then
				echo "✓ Homepage markers detected live"
			else
				echo "✗ Homepage markers not detected live" >&2
				return 1
			fi
			;;
		aza)
			echo "Running live aZa verification..."
			if curl -fsS https://sowwwl.com/aza.php | grep -qE 'gros ZIP|entrée directe|upload\.sowwwl\.com'; then
				echo "✓ Main aZa direct-upload markers detected live"
			else
				echo "✗ Main aZa direct-upload markers not detected live" >&2
				return 1
			fi

			if curl -fsSI https://upload.sowwwl.com/aza.php >/dev/null; then
				echo "✓ Direct aZa host responds over HTTPS"
			else
				echo "✗ Direct aZa host did not respond over HTTPS" >&2
				return 1
			fi
			;;
		full-web)
			run_live_checks homepage
			run_live_checks aza
			;;
		*)
			echo "No automated live verification profile for this selection."
			;;
	esac
}

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
done

printf 'Uploading to %s:%s\n' "$remote_host" "$remote_stage_dir"
printf 'Files:\n'
for absolute_path in "${absolute_files[@]}"; do
	printf '  - %s\n' "$absolute_path"
done
printf '  - %s\n' "$server_script"

ssh "$remote_host" "mkdir -p $(printf '%q' "$remote_stage_dir")"

for index in "${!absolute_files[@]}"; do
	relative_path=${selected_files[$index]}
	absolute_path=${absolute_files[$index]}
	remote_parent=$(dirname "$relative_path")
	if [[ "$remote_parent" != "." ]]; then
		ssh "$remote_host" "mkdir -p $(printf '%q' "$remote_stage_dir/$remote_parent")"
	fi
	scp "$absolute_path" "$remote_host:$(printf '%q' "$remote_stage_dir/$relative_path")"
done

scp "$server_script" "$remote_host:$(printf '%q' "$remote_stage_dir/$(basename "$server_script")")"

declare -a remote_cmd=("bash" "$remote_stage_dir/$(basename "$server_script")")
if [[ -n "$profile" ]]; then
	remote_cmd+=(--profile "$profile")
else
	remote_cmd+=(--files)
	for relative_path in "${selected_files[@]}"; do
		remote_cmd+=("$relative_path")
	done
fi

echo
echo "Upload complete."

if [[ $execute_remote -eq 1 ]]; then
	echo "Running remote installer via ssh..."
	ssh "$remote_host" "$(printf '%q ' "${remote_cmd[@]}")"
	echo
	echo "Remote deployment finished."
	if [[ $verify_live -eq 1 && -n "$profile" ]]; then
		echo
		run_live_checks "$profile"
	elif [[ "$profile" == "homepage" ]]; then
		echo "Suggested local check:"
		printf '%s\n' "curl -s https://sowwwl.com/ | grep -n 'hero-backdrop\\|torus-shell\\|data-torus-cloud'"
	elif [[ "$profile" == "aza" ]]; then
		echo "Suggested local checks:"
		printf '%s\n' "curl -s https://sowwwl.com/aza.php | grep -n 'gros ZIP\\|entrée directe\\|upload.sowwwl.com'"
		printf '%s\n' 'curl -I https://upload.sowwwl.com/aza.php'
	elif [[ "$profile" == "full-web" ]]; then
		echo "Suggested local checks:"
		printf '%s\n' "curl -s https://sowwwl.com/ | grep -n 'hero-backdrop\\|torus-shell\\|data-torus-cloud'"
		printf '%s\n' "curl -s https://sowwwl.com/aza.php | grep -n 'gros ZIP\\|entrée directe\\|upload.sowwwl.com'"
		printf '%s\n' 'curl -I https://upload.sowwwl.com/aza.php'
	fi
	exit 0
fi

echo "Run the following on the server:"
echo
printf '%q ' "${remote_cmd[@]}"
printf '\n'

echo
echo 'optional live checks:'
if [[ "$profile" == "homepage" ]]; then
	printf '%s\n' "curl -s https://sowwwl.com/ | grep -n 'hero-backdrop\\|torus-shell\\|data-torus-cloud'"
elif [[ "$profile" == "aza" ]]; then
	printf '%s\n' "curl -s https://sowwwl.com/aza.php | grep -n 'gros ZIP\\|entrée directe\\|upload.sowwwl.com'"
	printf '%s\n' 'curl -I https://upload.sowwwl.com/aza.php'
elif [[ "$profile" == "full-web" ]]; then
	printf '%s\n' "curl -s https://sowwwl.com/ | grep -n 'hero-backdrop\\|torus-shell\\|data-torus-cloud'"
	printf '%s\n' "curl -s https://sowwwl.com/aza.php | grep -n 'gros ZIP\\|entrée directe\\|upload.sowwwl.com'"
	printf '%s\n' 'curl -I https://upload.sowwwl.com/aza.php'
else
	printf '%s\n' '# run the appropriate curl checks for the files you deployed'
fi
