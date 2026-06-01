#!/usr/bin/env bash

set -euo pipefail

service_name="sowwwl-pi-ai-bridge"
run_user="${SUDO_USER:-${USER:-pi}}"
env_target="/etc/sowwwl/pocket-land.env"
env_example_override=""
start_when_ready=1

usage() {
	cat <<'EOF'
Usage:
  sudo bash scripts/install_pi_ai_bridge_service.sh [options]

Options:
  --user <name>           Unix user that should run the daemon
  --env-file <path>       Environment file path. Default: /etc/sowwwl/pocket-land.env
  --env-example <path>    Example env file to copy on first install
  --no-start              Install and enable the service, but do not start it
  --help                  Show this help

Examples:
  sudo bash scripts/install_pi_ai_bridge_service.sh --user pi
  sudo bash scripts/install_pi_ai_bridge_service.sh --user pablo --no-start
EOF
}

require_root() {
	if [[ ${EUID} -ne 0 ]]; then
		echo "Run this script with sudo or as root." >&2
		exit 1
	fi
}

append_group_if_present() {
	local user group_name
	user=$1
	group_name=$2

	if getent group "$group_name" >/dev/null; then
		usermod -aG "$group_name" "$user"
	fi
}

while (($# > 0)); do
	case "$1" in
		--user)
			run_user=${2:?Missing value for --user}
			shift 2
			;;
		--env-file)
			env_target=${2:?Missing value for --env-file}
			shift 2
			;;
		--env-example)
			env_example_override=${2:?Missing value for --env-example}
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
service_template="$repo_dir/systemd/${service_name}.service"
env_example="$repo_dir/systemd/${service_name}.env.example"
unit_target="/etc/systemd/system/${service_name}.service"

if [[ -n "$env_example_override" ]]; then
	if [[ "$env_example_override" = /* ]]; then
		env_example=$env_example_override
	else
		env_example="$repo_dir/$env_example_override"
	fi
fi

if [[ ! -f "$service_template" ]]; then
	echo "Missing service template: $service_template" >&2
	exit 1
fi

if [[ ! -f "$env_example" ]]; then
	echo "Missing env example: $env_example" >&2
	exit 1
fi

if ! id "$run_user" >/dev/null 2>&1; then
	echo "Unknown user: $run_user" >&2
	exit 1
fi

run_group=$(id -gn "$run_user")
env_dir=$(dirname "$env_target")

echo "==> Installing ${service_name}"
echo "Repo dir : $repo_dir"
echo "Run user : $run_user"
echo "Env file : $env_target"

install -d -m 755 /etc/sowwwl
install -d -m 755 "$env_dir"
install -d -m 755 /var/lib/sowwwl
chown "$run_user:$run_group" /var/lib/sowwwl

append_group_if_present "$run_user" render
append_group_if_present "$run_user" video

env_created=0
if [[ ! -f "$env_target" ]]; then
	install -m 640 "$env_example" "$env_target"
	env_created=1
fi

tmp_unit=$(mktemp)
trap 'rm -f "$tmp_unit"' EXIT

sed \
	-e "s|{{RUN_USER}}|$run_user|g" \
	-e "s|{{RUN_GROUP}}|$run_group|g" \
	-e "s|{{REPO_DIR}}|$repo_dir|g" \
	"$service_template" >"$tmp_unit"

install -m 644 "$tmp_unit" "$unit_target"

systemctl daemon-reload

service_ready=1
if ((env_created == 1)); then
	service_ready=0
fi

if grep -Eq 'CHANGE_ME_|replace-with-' "$env_target"; then
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

Then start the daemon:
  sudo systemctl enable $service_name
  sudo systemctl restart $service_name
  systemctl status $service_name --no-pager
  journalctl -u $service_name -f

EOF
fi
