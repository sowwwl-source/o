#!/usr/bin/env bash
set -euo pipefail

stage_dir="/tmp"
docroot="/var/www/html"
backup_root="/root"
profile=""
declare -a selected_files=()

usage() {
	cat <<'EOF'
Usage:
  install_apache_prod.sh [--stage-dir /tmp] [--docroot /var/www/html] [--backup-root /root] --profile homepage
  install_apache_prod.sh [--stage-dir /tmp] [--docroot /var/www/html] [--backup-root /root] --profile aza
	install_apache_prod.sh [--stage-dir /tmp] [--docroot /var/www/html] [--backup-root /root] --profile full-web
  install_apache_prod.sh [--stage-dir /tmp] [--docroot /var/www/html] [--backup-root /root] --files index.php main.js styles.css

Profiles:
  homepage   Installs index.php, main.js, styles.css
  aza        Installs aza.php, config.php
	full-web   Installs the main public PHP/CSS/JS surfaces served from /var/www/html

What this script does:
  - verifies staged files exist in the stage directory
  - creates a timestamped backup under the backup root
  - installs selected files into the Apache docroot
  - runs PHP lint for deployed PHP files
  - validates Apache config and reloads Apache
  - prints optional curl checks
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
		--stage-dir)
			stage_dir=${2:?Missing value for --stage-dir}
			shift 2
			;;
		--docroot)
			docroot=${2:?Missing value for --docroot}
			shift 2
			;;
		--backup-root)
			backup_root=${2:?Missing value for --backup-root}
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

for required_dir in "$stage_dir" "$docroot" "$backup_root"; do
	if [[ ! -d "$required_dir" ]]; then
		echo "Missing required directory: $required_dir" >&2
		exit 1
	fi
done

label=${profile:-manual}
stamp=$(date +%F-%H%M%S)
backup_dir="$backup_root/backup-${label}-${stamp}"
mkdir -p "$backup_dir"

declare -a php_targets=()

echo "Installing into $docroot"
echo "Backup directory: $backup_dir"
for relative_path in "${selected_files[@]}"; do
	staged="$stage_dir/$relative_path"
	target="$docroot/$relative_path"

	if [[ ! -f "$staged" ]]; then
		echo "Missing staged file: $staged" >&2
		exit 1
	fi

	mkdir -p "$(dirname "$target")"

	if [[ ! -f "$target" ]]; then
		echo "Warning: target does not exist yet, backup skipped for $target" >&2
	else
		mkdir -p "$(dirname "$backup_dir/$relative_path")"
		cp "$target" "$backup_dir/$relative_path"
	fi

	install -m 644 "$staged" "$target"
	echo "  installed $target"

	case "$target" in
		*.php)
			php_targets+=("$target")
			;;
	esac
done

echo
if [[ ${#php_targets[@]} -gt 0 ]]; then
	echo "PHP lint:"
	for php_file in "${php_targets[@]}"; do
		php -l "$php_file"
	done
	echo
fi

echo "Apache config test:"
apachectl configtest

echo
echo "Reload Apache:"
systemctl reload apache2

echo
echo "Deployment complete. Optional checks:"
if [[ "$profile" == "homepage" ]]; then
	echo "curl -s https://sowwwl.com/ | grep -n 'hero-backdrop\\|torus-shell\\|data-torus-cloud'"
elif [[ "$profile" == "aza" ]]; then
	echo "curl -s https://sowwwl.com/aza.php | grep -n 'gros ZIP\\|entrée directe\\|upload.sowwwl.com'"
	echo "curl -I https://upload.sowwwl.com/aza.php"
elif [[ "$profile" == "full-web" ]]; then
	echo "curl -s https://sowwwl.com/ | grep -n 'hero-backdrop\\|torus-shell\\|data-torus-cloud'"
	echo "curl -s https://sowwwl.com/aza.php | grep -n 'gros ZIP\\|entrée directe\\|upload.sowwwl.com'"
	echo "curl -I https://upload.sowwwl.com/aza.php"
else
	echo "# run the appropriate curl checks for the files you deployed"
fi
