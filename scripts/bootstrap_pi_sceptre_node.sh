#!/usr/bin/env bash

set -euo pipefail

skip_full_upgrade=0
force_unsupported_os=0
reboot_now=0

usage() {
	cat <<'EOF'
Usage:
  sudo bash scripts/bootstrap_pi_sceptre_node.sh [options]

Options:
  --skip-upgrade         Do not run apt full-upgrade
  --force-unsupported-os Continue even if the Pi is not on Bookworm/Trixie
  --reboot-now           Reboot automatically at the end
  --help                 Show this help
EOF
}

require_root() {
	if [[ ${EUID} -ne 0 ]]; then
		echo "Run this script with sudo or as root." >&2
		exit 1
	fi
}

os_codename() {
	. /etc/os-release
	echo "${VERSION_CODENAME:-unknown}"
}

userspace_arch() {
	dpkg --print-architecture 2>/dev/null || echo "unknown"
}

while (($# > 0)); do
	case "$1" in
		--skip-upgrade)
			skip_full_upgrade=1
			shift
			;;
		--force-unsupported-os)
			force_unsupported_os=1
			shift
			;;
		--reboot-now)
			reboot_now=1
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

pkg_arch=$(userspace_arch)
codename=$(os_codename)

if ((force_unsupported_os == 0)); then
	case "$pkg_arch" in
		armhf|arm64)
			;;
		*)
			echo "Unsupported architecture: $pkg_arch" >&2
			exit 1
			;;
	esac

	case "$codename" in
		bookworm|trixie)
			;;
		*)
			echo "Unsupported Raspberry Pi OS codename: $codename" >&2
			echo "Use --force-unsupported-os if you intentionally want to continue." >&2
			exit 1
			;;
	esac
fi

export DEBIAN_FRONTEND=noninteractive

apt-get update

if ((skip_full_upgrade == 0)); then
	apt-get full-upgrade -y
fi

apt-get install -y \
	ca-certificates \
	curl \
	git \
	i2c-tools \
	nano \
	python3-requests \
	python3-sense-hat \
	raspi-config \
	rsync

if command -v raspi-config >/dev/null 2>&1; then
	raspi-config nonint do_i2c 0 || true
fi

install -d -m 755 /etc/sowwwl
install -d -m 755 /var/lib/sowwwl

cat <<EOF

Bootstrap complete.

Next:
  1. Verify the Sensor HAT:
       sudo i2cdetect -y 1
       python3 - <<'PY'
from sense_hat import SenseHat
sense = SenseHat()
print({
    "temp": round(sense.get_temperature(), 2),
    "humidity": round(sense.get_humidity(), 2),
    "pressure": round(sense.get_pressure(), 2),
})
PY
  2. Install the daemon:
       sudo bash scripts/install_pi_sceptre_service.sh --user \${SUDO_USER:-$USER} --no-start
  3. Edit /etc/sowwwl/pocket-land.env with the real token and target URL.
  4. Start the service:
       sudo systemctl enable sowwwl-pi-sceptre
       sudo systemctl restart sowwwl-pi-sceptre
       journalctl -u sowwwl-pi-sceptre -f
  5. Open the screen console:
       ${SOWWWL_SCEPTRE_SCREEN_URL:-https://pi.sowwwl.cloud/sceptre/ensemble}

EOF

if ((reboot_now == 1)); then
	reboot
fi
