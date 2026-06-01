#!/usr/bin/env bash

set -euo pipefail

service_name="sowwwl-cloudflared-pi"
env_target="/etc/sowwwl/cloudflared-pi.env"
start_when_ready=1

usage() {
	cat <<'EOF'
Usage:
  sudo bash scripts/install_cloudflared_pi_tunnel.sh [options]

Options:
  --env-file <path>       Environment file path. Default: /etc/sowwwl/cloudflared-pi.env
  --no-start              Install and enable the service, but do not start it
  --help                  Show this help
EOF
}

require_root() {
	if [[ ${EUID} -ne 0 ]]; then
		echo "Run this script with sudo or as root." >&2
		exit 1
	fi
}

install_cloudflared_repo() {
	install -d -m 755 /usr/share/keyrings

	if [[ ! -f /usr/share/keyrings/cloudflare-main.gpg ]]; then
		curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg >/usr/share/keyrings/cloudflare-main.gpg
	fi

	cat >/etc/apt/sources.list.d/cloudflared.list <<'EOF'
deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main
EOF
}

while (($# > 0)); do
	case "$1" in
		--env-file)
			env_target=${2:?Missing value for --env-file}
			shift 2
			;;
		--no-start)
			start_when_ready=0
			shift
			;;
		--help|-h)
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

require_root

repo_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
runner_script="$repo_dir/scripts/run_cloudflared_pi_tunnel.sh"
service_template="$repo_dir/systemd/${service_name}.service"
env_example="$repo_dir/systemd/${service_name}.env.example"
unit_target="/etc/systemd/system/${service_name}.service"
env_dir=$(dirname "$env_target")

if [[ ! -x "$runner_script" ]]; then
	echo "Missing runner script: $runner_script" >&2
	exit 1
fi

if [[ ! -f "$service_template" ]]; then
	echo "Missing service template: $service_template" >&2
	exit 1
fi

if [[ ! -f "$env_example" ]]; then
	echo "Missing env example: $env_example" >&2
	exit 1
fi

echo "==> Installing cloudflared package"
install_cloudflared_repo
apt-get update
apt-get install -y cloudflared

install -d -m 755 /etc/sowwwl
install -d -m 755 "$env_dir"

env_created=0
if [[ ! -f "$env_target" ]]; then
	install -m 640 "$env_example" "$env_target"
	env_created=1
fi

tmp_unit=$(mktemp)
trap 'rm -f "$tmp_unit"' EXIT

sed \
	-e "s|{{ENV_FILE}}|$env_target|g" \
	-e "s|{{RUNNER_SCRIPT}}|$runner_script|g" \
	"$service_template" >"$tmp_unit"

install -m 644 "$tmp_unit" "$unit_target"

systemctl daemon-reload

service_ready=1
if ((env_created == 1)); then
	service_ready=0
fi

if grep -Eq 'CHANGE_ME_' "$env_target"; then
	service_ready=0
fi

if ((service_ready == 1)); then
	systemctl enable "$service_name"
fi

should_start=$start_when_ready
if ((service_ready == 0)); then
	should_start=0
fi

if ((should_start == 1)); then
	echo "==> Starting ${service_name}"
	systemctl restart "$service_name"
	sleep 2
	systemctl --no-pager --full status "$service_name" || true
else
	cat <<EOF

Service installed but not started yet.

Edit this file first:
  $env_target

Then start the tunnel:
  sudo systemctl enable $service_name
  sudo systemctl restart $service_name
  systemctl status $service_name --no-pager
  journalctl -u $service_name -f

EOF
fi
