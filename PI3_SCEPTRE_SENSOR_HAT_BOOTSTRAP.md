# Raspberry Pi 3 B+ sceptre harmonique bootstrap

Use this path when a Raspberry Pi 3 B+ with a Sensor HAT becomes the physical sceptre for `sowwwl.io` and `Fenetre harmonique`.

This node is not the main host.
The Pi 5 stays the public host and AI bridge.
The Pi 3 B+ becomes:

- a moving musical command
- a climate probe
- a joystick ritual selector
- a tiny LED oracle
- a control-screen companion for `https://pi.sowwwl.cloud/sceptre/ensemble`

## Recommended OS

For a Raspberry Pi 3 B+ dedicated to the sceptre:

- Raspberry Pi OS Lite
- 32-bit
- current major release, ideally `Trixie`

Bookworm also remains acceptable if that is already what you have on the card.

## Official references checked

These instructions were aligned on June 1, 2026 with:

- Raspberry Pi OS docs: https://www.raspberrypi.com/documentation/computers/os.html
- Sense HAT docs: https://www.raspberrypi.com/documentation/accessories/sense-hat.html
- Raspberry Pi configuration docs: https://www.raspberrypi.com/documentation/computers/configuration.html

## 1. Copy the repo

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

## 2. Bootstrap the sceptre node

```bash
cd /opt/o-pocket-land
sudo bash scripts/bootstrap_pi_sceptre_node.sh
```

That installs:

- `python3-sense-hat`
- `python3-requests`
- `i2c-tools`
- `raspi-config`

It also enables I2C non-interactively when `raspi-config` is present.

## 3. Verify the Sensor HAT before the daemon

```bash
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
```

If I2C or the Sense HAT does not answer, stop there and fix the hardware path first.

## 4. Install the service env

```bash
sudo bash scripts/install_pi_sceptre_service.sh --user "$USER" --no-start
sudo nano /etc/sowwwl/pocket-land.env
```

Set at least:

```dotenv
SOWWWL_SCEPTRE_ENDPOINT=https://pi.sowwwl.cloud/ingest/sceptre
SOWWWL_SCEPTRE_TOKEN=replace-with-the-host-token
SOWWWL_SCEPTRE_DEVICE_SLUG=ensemble
SOWWWL_SCEPTRE_SCREEN_URL=https://pi.sowwwl.cloud/sceptre/ensemble
SOWWWL_SCEPTRE_SCREEN_MODE=kiosk
SOWWWL_SCEPTRE_SCREEN_LABEL=camera
SOWWWL_SCEPTRE_SCREEN_PAGE=camera
```

If several sceptres will live on `sowwwl.io`, give each node its own slug
(`ensemble`, `atlas`, `rune-02`, etc.). The host can also move to a per-device
token registry with:

```dotenv
SOWWWL_SCEPTRE_TOKENS_FILE=/var/www/runtime/sceptre/tokens.json
```

Example registry on the host:

```json
{
  "ensemble": "token-ensemble",
  "atlas": "token-atlas"
}
```

Useful tuning defaults:

```dotenv
SOWWWL_SCEPTRE_POLL_SECONDS=1.6
SOWWWL_SCEPTRE_TIMEOUT_SECONDS=5
SOWWWL_SCEPTRE_HEARTBEAT_SECONDS=10
SOWWWL_SCEPTRE_STATE_FILE=/var/lib/sowwwl/sceptre-state.json
```

Optional fallback without a real HAT:

```dotenv
SOWWWL_SCEPTRE_SIMULATE=1
```

## 5. Start the daemon

```bash
sudo systemctl enable sowwwl-pi-sceptre
sudo systemctl restart sowwwl-pi-sceptre
systemctl status sowwwl-pi-sceptre --no-pager
journalctl -u sowwwl-pi-sceptre -f
```

## 6. What the daemon sends

The sceptre publishes a compact state to `POST /ingest/sceptre`:

- motion: pitch, roll, sway, shake, stillness
- climate: temperature, humidity, pressure
- music bias: tempo, swing, drone, filter, percussion, volume
- visual bias: brightness, negative, torus spin, halo, contrast, warmth
- triggers: kick, snare, hihat, accent
- screen: current page for the dedicated display
- magic: sigil, palette, spell

## 7. Screen role

Your dedicated control screen can simply open:

- `https://pi.sowwwl.cloud/sceptre/ensemble`

That page mirrors the live sceptre state with:

- ritual mode
- screen page
- motion level
- climate
- percussion intensity
- halo / negative / torus spin

## 8. Suggested physical mapping

- tilt left / right: swing + tore spin
- tilt forward / back: filter + contrast
- quick shake: percussion accent
- quiet hold: drone bias + halo
- joystick left / right: screen page
- joystick up / down: ritual mode
- joystick press: direct accent
