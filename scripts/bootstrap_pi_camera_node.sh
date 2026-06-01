#!/usr/bin/env bash

set -euo pipefail

skip_full_upgrade=0
force_unsupported_os=0
reboot_now=0

usage() {
	cat <<'EOF'
Usage:
  sudo bash scripts/bootstrap_pi_camera_node.sh [options]

Options:
  --skip-upgrade         Do not run apt full-upgrade
  --force-unsupported-os Continue even if the Pi is not on Bookworm/Trixie
  --reboot-now           Reboot automatically at the end
  --help                 Show this help

Examples:
  sudo bash scripts/bootstrap_pi_camera_node.sh
  sudo bash scripts/bootstrap_pi_camera_node.sh --skip-upgrade
EOF
}

require_root() {
	if [[ ${EUID} -ne 0 ]]; then
		echo "Run this script with sudo or as root." >&2
		exit 1
	fi
}

device_model() {
	if [[ -r /proc/device-tree/model ]]; then
		tr -d '\0' </proc/device-tree/model
		return
	fi

	echo "unknown"
}

userspace_arch() {
	dpkg --print-architecture 2>/dev/null || echo "unknown"
}

os_codename() {
	. /etc/os-release
	echo "${VERSION_CODENAME:-unknown}"
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

arch=$(uname -m)
pkg_arch=$(userspace_arch)
codename=$(os_codename)
model=$(device_model)

echo "==> Raspberry Pi camera-node bootstrap"
echo "Architecture : $arch"
echo "Userland     : $pkg_arch"
echo "Codename     : $codename"
echo "Model        : $model"

if [[ "$model" == *"Raspberry Pi 2"* && "$pkg_arch" != "armhf" ]]; then
	echo "Warning: Raspberry Pi documentation currently recommends 32-bit Raspberry Pi OS for Pi 2 class boards." >&2
fi

if ((force_unsupported_os == 0)); then
	case "$pkg_arch" in
		armhf|arm64)
			;;
		*)
			cat >&2 <<EOF
Unsupported Raspberry Pi OS userspace architecture: $pkg_arch

This bootstrap expects a Raspberry Pi OS install using either:
  - armhf for older boards such as Raspberry Pi 2
  - arm64 for newer boards

If you intentionally want to continue anyway, rerun with:
  --force-unsupported-os
EOF
			exit 1
			;;
	esac

	case "$codename" in
		bookworm|trixie)
			;;
		*)
			cat >&2 <<EOF
Unsupported Raspberry Pi OS codename: $codename

The current camera stack documented by Raspberry Pi uses rpicam + Picamera2
on Bookworm and Trixie. The older legacy camera stack is deprecated.

If you intentionally want to continue anyway, rerun with:
  --force-unsupported-os
EOF
			exit 1
			;;
	esac
fi

export DEBIAN_FRONTEND=noninteractive

echo "==> Updating apt indexes"
apt-get update

if ((skip_full_upgrade == 0)); then
	echo "==> Upgrading system packages"
	apt-get full-upgrade -y
fi

echo "==> Installing camera-node dependencies"
apt-get install -y \
	ca-certificates \
	curl \
	git \
	nano \
	python3-numpy \
	python3-opencv \
	python3-picamera2 \
	python3-requests \
	rpicam-apps \
	rsync \
	v4l-utils

echo "==> Preparing runtime directories"
install -d -m 755 /etc/sowwwl
install -d -m 755 /var/lib/sowwwl

cat <<EOF

Bootstrap complete.

Next:
  1. Verify the camera stack:
       rpicam-hello --list-cameras
       rpicam-jpeg --output /tmp/pi-camera-test.jpg --timeout 1500
  2. Install the service env with the profile that matches the board:
       sudo bash scripts/install_pi_vision_service.sh --user \${SUDO_USER:-$USER} --env-example systemd/sowwwl-pi-vision.pi2.env.example --no-start
       sudo bash scripts/install_pi_vision_service.sh --user \${SUDO_USER:-$USER} --env-example systemd/sowwwl-pi-vision.pi3.env.example --no-start
  3. Edit /etc/sowwwl/pocket-land.env and set the real public ingest token.
  4. Start the daemon:
       sudo systemctl enable sowwwl-pi-vision
       sudo systemctl restart sowwwl-pi-vision
       journalctl -u sowwwl-pi-vision -f

If rpicam-hello does not list the camera, stop there and fix the camera path before starting the daemon.

EOF

if ((reboot_now == 1)); then
	echo "==> Rebooting now"
	reboot
fi
