#!/usr/bin/env bash

set -euo pipefail

hailo_package="auto"
install_docker=1
reboot_now=0
force_unsupported_os=0

usage() {
	cat <<'EOF'
Usage:
  sudo bash scripts/bootstrap_pi5_ai_hat.sh [options]

Options:
  --hailo-package <name>  Hailo package to install. Default: auto-detect
  --skip-docker           Do not install docker.io or docker-compose-plugin
  --force-unsupported-os  Continue even if the Pi is not on 64-bit Raspberry Pi OS Trixie
  --reboot-now            Reboot automatically at the end
  --help                  Show this help

Examples:
  sudo bash scripts/bootstrap_pi5_ai_hat.sh
  sudo bash scripts/bootstrap_pi5_ai_hat.sh --hailo-package hailo-h10-all
  sudo bash scripts/bootstrap_pi5_ai_hat.sh --skip-docker
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

detect_hailo_package() {
	local pci_lines
	pci_lines=$(lspci -nn 2>/dev/null | grep -i 'Hailo Technologies' || true)

	if [[ "$pci_lines" == *"Hailo-10H"* || "$pci_lines" == *"[1e60:45c4]"* ]]; then
		echo "hailo-h10-all"
		return
	fi

	if [[ -n "$pci_lines" ]]; then
		echo "hailo-all"
		return
	fi

	echo "hailo-all"
}

remove_conflicting_hailo_meta() {
	local requested installed
	requested=$1

	case "$requested" in
		hailo-all)
			installed="hailo-h10-all"
			;;
		hailo-h10-all)
			installed="hailo-all"
			;;
		*)
			return
			;;
	esac

	if dpkg-query -W -f='${Status}' "$installed" 2>/dev/null | grep -q "install ok installed"; then
		echo "==> Removing conflicting Hailo metapackage: $installed"
		apt-get purge -y "$installed"
		apt-get autoremove -y
	fi
}

while (($# > 0)); do
	case "$1" in
		--hailo-package)
			hailo_package=${2:?Missing value for --hailo-package}
			shift 2
			;;
		--skip-docker)
			install_docker=0
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
requested_hailo_package=$hailo_package

if [[ "$hailo_package" == "auto" ]]; then
	hailo_package=$(detect_hailo_package)
fi

echo "==> Raspberry Pi bootstrap"
echo "Architecture : $arch"
echo "Userland     : $pkg_arch"
echo "Codename     : $codename"
echo "Model        : $model"
echo "Hailo pkg    : $hailo_package"
if [[ "$requested_hailo_package" == "auto" ]]; then
	echo "Hailo detect : auto"
fi

if [[ "$arch" != "aarch64" && "$arch" != "arm64" ]]; then
	echo "Warning: this script is intended for a 64-bit Raspberry Pi OS install on ARM64." >&2
fi

if [[ "$model" != *"Raspberry Pi 5"* ]]; then
	echo "Warning: Raspberry Pi 5 was expected, but detected model is: $model" >&2
fi

if ((force_unsupported_os == 0)); then
	if [[ "$pkg_arch" != "arm64" ]]; then
		cat >&2 <<EOF
This Pi is not running a 64-bit Raspberry Pi OS userspace.
Detected dpkg architecture: $pkg_arch

The Raspberry Pi AI software stack currently expects Raspberry Pi OS 64-bit.
Reflash the Pi with Raspberry Pi OS 64-bit (Trixie), then rerun this script.

If you intentionally want to continue anyway, rerun with:
  --force-unsupported-os
EOF
		exit 1
	fi

	if [[ "$codename" != "trixie" ]]; then
		cat >&2 <<EOF
This Pi is not running Raspberry Pi OS Trixie.
Detected codename: $codename

Raspberry Pi's current AI software docs require Raspberry Pi OS 64-bit (Trixie)
for the supported AI HAT+ software path.

If you intentionally want to continue anyway, rerun with:
  --force-unsupported-os
EOF
		exit 1
	fi
fi

export DEBIAN_FRONTEND=noninteractive

echo "==> Updating apt indexes"
apt-get update

echo "==> Upgrading system packages"
apt-get full-upgrade -y

echo "==> Installing Pi + Hailo dependencies"
remove_conflicting_hailo_meta "$hailo_package"
apt-get install -y \
	ca-certificates \
	curl \
	dkms \
	git \
	nano \
	pciutils \
	python3-numpy \
	python3-opencv \
	python3-picamera2 \
	python3-requests \
	rpicam-apps \
	rsync \
	v4l-utils \
	"$hailo_package"

if ((install_docker == 1)); then
	echo "==> Installing Docker packages"
	docker_packages=(docker.io)
	if apt-cache show docker-compose-plugin >/dev/null 2>&1; then
		docker_packages+=(docker-compose-plugin)
	else
		docker_packages+=(docker-compose)
	fi
	apt-get install -y "${docker_packages[@]}"
	systemctl enable --now docker
	if [[ -n "${SUDO_USER:-}" && "${SUDO_USER}" != "root" ]]; then
		usermod -aG docker "$SUDO_USER"
	fi
fi

echo "==> Refreshing Raspberry Pi EEPROM"
rpi-eeprom-update -a || true

echo "==> Preparing runtime directories"
install -d -m 755 /etc/sowwwl
install -d -m 755 /var/lib/sowwwl

cat <<EOF

Bootstrap complete.

Next:
  1. Reboot the Pi.
  2. Run:
       hailortcli fw-control identify
       rpicam-hello --list-cameras
       rpicam-hello -t 0 --post-process-file /usr/share/rpi-camera-assets/hailo_yolov6_inference.json
  3. Edit /etc/sowwwl/pocket-land.env
  4. Install the service:
       sudo bash scripts/install_pi_vision_service.sh --user \${SUDO_USER:-$USER}

EOF

if ((reboot_now == 1)); then
	echo "==> Rebooting now"
	reboot
fi
