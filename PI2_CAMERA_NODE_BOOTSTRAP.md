# Raspberry Pi 2 / 3 camera-node bootstrap

Use this path when the Pi 5 hosts the `sowwwl` instance and a separate Raspberry Pi 2 or Raspberry Pi 3 carries the camera.

This node does not stream raw video to the public host.
It only detects local presence changes and emits compact sensor traces to `/ingest/sensor`.

If you explicitly enable the optional MJPEG helper, it can also expose a live LAN-only viewer from the camera node itself. The default path for that helper is `http://<pi-node>:8082/stream.mjpg`.

## Official references checked

These instructions were aligned on May 31, 2026 with:

- Raspberry Pi OS docs: https://www.raspberrypi.com/documentation/computers/os.html
- Raspberry Pi camera docs: https://www.raspberrypi.com/documentation/hardware/camera/
- Raspberry Pi camera software docs: https://www.raspberrypi.com/documentation/computers/camera_software.html

Important current notes from the official docs:

- Raspberry Pi OS Lite is a good fit for older boards such as Raspberry Pi 2
- the 32-bit Raspberry Pi OS build is the one designed for Raspberry Pi 2 class hardware
- the current supported camera path is `rpicam-*` plus `Picamera2`
- the old legacy camera stack is deprecated and unsupported

## Target role

This Pi becomes:

- a separate physical eye
- a tiny local motion detector
- an offline-capable trace spooler
- a boot-persistent `sowwwl-pi-vision.py` daemon

## Recommended OS

For a Raspberry Pi 2 Model B v1.2, use:

- Raspberry Pi OS Lite
- 32-bit
- current major release, ideally `Trixie`

Bookworm also remains acceptable for the camera stack if that is what you already have on the card.

For a Raspberry Pi 3 used only as the camera node, `Raspberry Pi OS Lite 32-bit (Trixie)` is still the recommended first path. It keeps memory pressure lower while staying on the supported `rpicam` + `Picamera2` stack.

## 1. Copy the repo

On the camera node:

```bash
sudo mkdir -p /opt
sudo chown "$USER":"$USER" /opt
cd /opt
git clone https://github.com/sowwwl-source/o.git o-pocket-land
cd /opt/o-pocket-land
```

Or copy your local checkout:

```bash
rsync -a --delete /path/to/O_installation_FRESH/o/ /opt/o-pocket-land/
cd /opt/o-pocket-land
```

## 2. Bootstrap the camera node

```bash
cd /opt/o-pocket-land
sudo bash scripts/bootstrap_pi_camera_node.sh
```

That installs:

- `python3-numpy`
- `python3-opencv`
- `python3-picamera2`
- `python3-requests`
- `rpicam-apps`
- `v4l-utils`

## 3. Verify the camera before the daemon

Do not continue until the camera stack itself is healthy:

```bash
rpicam-hello --list-cameras
rpicam-jpeg --output /tmp/pi-camera-test.jpg --timeout 1500
```

If `rpicam-hello --list-cameras` returns no camera, fix the hardware or OS image before moving on.

## 4. Install the service env with board-matched defaults

```bash
sudo bash scripts/install_pi_vision_service.sh \
  --user "$USER" \
  --env-example systemd/sowwwl-pi-vision.pi2.env.example \
  --no-start
```

For a Raspberry Pi 3, use:

```bash
sudo bash scripts/install_pi_vision_service.sh \
  --user "$USER" \
  --env-example systemd/sowwwl-pi-vision.pi3.env.example \
  --no-start
```

Then edit:

```bash
sudo nano /etc/sowwwl/pocket-land.env
```

Set at least:

```dotenv
SOWWWL_PI_ENDPOINT=https://pi.sowwwl.cloud/ingest/sensor
SOWWWL_PI_TOKEN=replace-with-the-pi5-ingest-token
SOWWWL_PI_LAND_SLUG=pi2-camera-01
SOWWWL_PI_CAMERAS=0
SOWWWL_PI_WIDTH=320
SOWWWL_PI_HEIGHT=240
SOWWWL_PI_FRAMERATE=6
SOWWWL_PI_MIN_AREA=900
SOWWWL_PI_COOLDOWN_SECONDS=5
SOWWWL_PI_TIMEOUT_SECONDS=4
```

Optional live viewer on the camera node itself:

```dotenv
SOWWWL_PI_STREAM_ENABLED=1
SOWWWL_PI_STREAM_BIND=0.0.0.0
SOWWWL_PI_STREAM_PORT=8082
SOWWWL_PI_STREAM_TOKEN=replace-with-a-random-viewer-token
SOWWWL_PI_STREAM_MAX_FPS=4
SOWWWL_PI_STREAM_JPEG_QUALITY=72
```

Why these defaults:

- Pi 2 CPU and RAM are modest
- the daemon only needs enough resolution for motion contours
- `320x240 @ 6fps` is a safer first honest profile than chasing image quality

## 5. Start the daemon

```bash
sudo systemctl enable sowwwl-pi-vision
sudo systemctl restart sowwwl-pi-vision
systemctl status sowwwl-pi-vision --no-pager
journalctl -u sowwwl-pi-vision -f
```

If the live viewer is enabled, you can then test it from the same LAN:

```bash
curl -I "http://<pi-node>:8082/healthz?token=$SOWWWL_PI_STREAM_TOKEN"
curl -I "http://<pi-node>:8082/snapshot.jpg?token=$SOWWWL_PI_STREAM_TOKEN"
```

## 6. Smoke-test the public ingest

From the Pi 2, with the same token:

```bash
curl -sS -X POST https://pi.sowwwl.cloud/ingest/sensor \
  -H "Authorization: Bearer $SOWWWL_PI_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "event":"camera_node_online",
    "camera":"cam-0",
    "land_slug":"pi2-camera-01",
    "message":"camera node online",
    "metrics":{"frame_luma":0.0}
  }'
```

Expected result:

- HTTP `202`
- JSON body with `"ok": true`

## 7. What the Pi 5 must expose

The hosting Pi needs:

- the public hostname live, currently `https://pi.sowwwl.cloud`
- `POST /ingest/sensor`
- a configured `SOWWWL_PI_TOKEN` in the app runtime

That host-side wiring can be prepared before the Pi 2 ever comes online.
