# Raspberry Pi 5 + AI HAT+ bootstrap

Use this to turn a Raspberry Pi 5 with an AI HAT+ into the first physical `sowwwl` pocket-land node.

This path prepares two layers:

- the local AI/sensing layer on the Pi itself
- the optional local `O.` web stack if you also want the Pi to host an ARM64 instance

The public shore node and the carried land stay conceptually separate.
The Pi is the `terre de poche`; the public `sowwwl.cloud` cutover can come after the physical loop is stable.

If the camera is not mounted on this Pi 5 and instead lives on a separate Raspberry Pi 2,
use `PI2_CAMERA_NODE_BOOTSTRAP.md` for that second node.

## Official references checked

These instructions were aligned on May 30, 2026 with:

- Raspberry Pi AI HAT+ docs: https://www.raspberrypi.com/documentation/accessories/ai-hat-plus.html
- Raspberry Pi AI software docs: https://www.raspberrypi.com/documentation/computers/ai.html
- Raspberry Pi camera install docs: https://www.raspberrypi.com/documentation/hardware/camera/

Important current constraints from the official docs:

- `AI HAT+` on Raspberry Pi 5 is for vision workloads with `hailo-all`
- `AI HAT+ 2` uses `hailo-h10-all` and is the model that adds local LLM/VLM support
- for vision, Raspberry Pi recommends attaching the camera before the AI HAT+
- for `AI HAT+`, PCIe Gen 3 is handled automatically; the manual `dtparam=pciex1_gen=3` step is for the older AI Kit path

## Target role

This Pi becomes:

- a local capture and inference node
- a pocket-land trace spooler
- a boot-persistent `sowwwl-pi-vision.py` daemon
- optionally, a local ARM64 host for the `O.` app stack

## Hardware checklist

- Raspberry Pi 5
- Raspberry Pi OS 64-bit, current `Trixie`
- Raspberry Pi AI HAT+ 13 TOPS or 26 TOPS
- Raspberry Pi Camera Module 3 or another supported camera
- microSD only for quick tests; SSD strongly preferred for real runtime
- Raspberry Pi Active Cooler recommended by Raspberry Pi
- stable power supply
- Ethernet preferred for first setup

## 1. Assemble the hardware

Follow the official assembly order:

1. Power the Pi off completely.
2. Mount the camera first.
3. Mount the Active Cooler if you have one.
4. Mount the AI HAT+ with its PCIe ribbon cable and GPIO stacking header.

Important detail from Raspberry Pi's current docs:

- when inserting the PCIe ribbon into the Raspberry Pi 5, the metallic contacts face inward, toward the USB ports

## 2. Clone the repo onto the Pi

Use a clean working path on the Pi:

```bash
sudo mkdir -p /opt
sudo chown "$USER":"$USER" /opt
cd /opt
git clone https://github.com/sowwwl-source/o.git o-pocket-land
cd /opt/o-pocket-land
```

If you are copying a local checkout instead of cloning:

```bash
rsync -a --delete /path/to/O_installation_FRESH/o/ /opt/o-pocket-land/
cd /opt/o-pocket-land
```

## 3. Bootstrap the Pi software

Run the bootstrap script from the repo:

```bash
cd /opt/o-pocket-land
sudo bash scripts/bootstrap_pi5_ai_hat.sh
```

What it installs by default:

- system updates and latest EEPROM update request
- the Hailo stack that matches the detected board:
  - `hailo-all` for AI HAT+ / Hailo-8 class boards
  - `hailo-h10-all` for AI HAT+ 2 / Hailo-10H boards
- `rpicam-apps`
- `python3-picamera2`
- `python3-numpy`
- `python3-opencv`
- `python3-requests`
- `docker.io` and `docker-compose-plugin`
- the local runtime directories under `/etc/sowwwl` and `/var/lib/sowwwl`

If the board is actually an `AI HAT+ 2`, use:

```bash
sudo bash scripts/bootstrap_pi5_ai_hat.sh --hailo-package hailo-h10-all
```

If you do not want Docker on this first pass:

```bash
sudo bash scripts/bootstrap_pi5_ai_hat.sh --skip-docker
```

Then reboot:

```bash
sudo reboot
```

## 4. Verify the AI HAT+ and camera after reboot

Back on the Pi, run:

```bash
hailortcli fw-control identify
rpicam-hello --list-cameras
```

For a first Hailo vision smoke test, Raspberry Pi currently documents:

```bash
rpicam-hello -t 0 --post-process-file /usr/share/rpi-camera-assets/hailo_yolov6_inference.json
```

Use `Ctrl+C` to stop it.

If `hailortcli` fails, do not continue to the service install yet.
Fix the hardware and driver layer first.

## 5. Configure the pocket-land uplink

Install the example environment file:

```bash
sudo install -d -m 755 /etc/sowwwl
sudo cp systemd/sowwwl-pi-vision.env.example /etc/sowwwl/pocket-land.env
sudoedit /etc/sowwwl/pocket-land.env
```

Minimum values to set:

- `SOWWWL_PI_ENDPOINT`
- `SOWWWL_PI_TOKEN`
- `SOWWWL_PI_LAND_SLUG`

Recommended first Pi edge values:

```dotenv
SOWWWL_PI_ENDPOINT=https://pi.sowwwl.cloud/ingest/sensor
SOWWWL_PI_TOKEN=replace-with-long-random-pi-token
SOWWWL_PI_LAND_SLUG=pi-pocket-01
SOWWWL_PI_CAMERAS=0
```

If this node later uplinks into another public shore, change only the endpoint:

```dotenv
SOWWWL_PI_ENDPOINT=https://your-public-host/ingest/sensor
```

## 6. Install the daemon as a boot service

From the repo root on the Pi:

```bash
cd /opt/o-pocket-land
sudo bash scripts/install_pi_vision_service.sh --user "$USER"
```

If this Pi 5 is reading a separate Pi camera through the local proxy, install the AI bridge too:

```bash
sudo bash scripts/install_pi_ai_bridge_service.sh --user "$USER" --no-start
```

Then extend `/etc/sowwwl/pocket-land.env` with:

```dotenv
SOWWWL_PI_AI_CAMERA_SLUG=pi3-camera-01
SOWWWL_PI_AI_SNAPSHOT_URL=http://127.0.0.1/camera/pi3-camera-01/snapshot.jpg
SOWWWL_PI_AI_ENDPOINT=http://127.0.0.1/ingest/camera-ai
```

Then verify:

```bash
systemctl status sowwwl-pi-vision --no-pager
journalctl -u sowwwl-pi-vision -f
```

Useful controls:

```bash
sudo systemctl restart sowwwl-pi-vision
sudo systemctl stop sowwwl-pi-vision
sudo systemctl disable sowwwl-pi-vision
```

## 7. Manual daemon test before trusting systemd

If you want one clean foreground run first:

```bash
set -a
source /etc/sowwwl/pocket-land.env
set +a
python3 scripts/sowwwl-pi-vision.py
```

That gives you immediate logs before the service is enabled.

## 8. Optional: run the local O. web stack on the Pi

If this Pi should also host a local ARM64 instance of the `O.` stack:

```bash
cd /opt/o-pocket-land/deploy
cp .env.production.example .env.production
sudoedit .env.production
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml up --build -d
docker compose -p sowwwl-o --env-file .env.production -f docker-compose.prod.yml ps
```

Use this only when you intentionally want the Pi to act as a local host, not only as a carried land.

Keep in mind:

- the Pi should use SSD storage for any serious DB/runtime load
- the first stable milestone is the sensing daemon, not the public cutover
- the public `sowwwl.cloud` promotion should wait until the presence / tunnel story is honest

## 8b. Optional: expose the Pi through Cloudflare Tunnel

If the domain already lives on Cloudflare and you do not want to depend on home-router port forwarding first, prepare the tunnel service on the Pi:

```bash
cd /opt/o-pocket-land
sudo bash scripts/install_cloudflared_pi_tunnel.sh --no-start
sudoedit /etc/sowwwl/cloudflared-pi.env
```

Then, once the real tunnel token is in place:

```bash
sudo install -m 600 /dev/stdin /etc/sowwwl/cloudflared-pi.token
sudo systemctl enable sowwwl-cloudflared-pi
sudo systemctl restart sowwwl-cloudflared-pi
systemctl status sowwwl-cloudflared-pi --no-pager
journalctl -u sowwwl-cloudflared-pi -f
```

Recommended first hostname:

- start with `pi.sowwwl.cloud` or `pocket.sowwwl.cloud`
- only move `sowwwl.cloud` itself once the Pi-backed route is stable

For the full local-host stack, see:

- `deploy/README.md`
- `3TERNET_ARCHITECTURE.md`

## 9. Quick troubleshooting

### Hailo not detected

Run:

```bash
hailortcli fw-control identify
lspci -nn
```

If the Hailo device does not appear, re-check:

- ribbon orientation
- connector latch seating
- full OS update and reboot
- correct package: `hailo-all` for `AI HAT+`, `hailo-h10-all` for `AI HAT+ 2`

### Camera not detected

Run:

```bash
rpicam-hello --list-cameras
```

If nothing appears, re-check the CSI cable seating before debugging the Python daemon.

### Service starts but traces stay local

That usually means one of:

- `SOWWWL_PI_TOKEN` is blank or placeholder
- the Pi has no outbound network
- the endpoint is not reachable

Use:

```bash
journalctl -u sowwwl-pi-vision -n 80 --no-pager
```

### Service fails immediately

Run the script directly once:

```bash
set -a
source /etc/sowwwl/pocket-land.env
set +a
python3 /opt/o-pocket-land/scripts/sowwwl-pi-vision.py
```

That usually surfaces the missing package or wrong camera index much faster than reading a boot log first.
