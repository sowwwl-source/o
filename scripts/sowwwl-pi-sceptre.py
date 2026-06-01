#!/usr/bin/env python3
"""
sowwwl-pi-sceptre.py

Turns a Raspberry Pi 3 B+ with a Sensor HAT into a small ritual controller for
sowwwl.io and Fenetre harmonique. It reads motion + climate, derives a compact
magical state, mirrors a tiny palette on the LED matrix, and publishes the
result to the host Pi.
"""

from __future__ import annotations

import json
import math
import os
import signal
import time
from dataclasses import dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import requests

try:
    from sense_hat import SenseHat
except ImportError:
    SenseHat = None


PAGE_CYCLE = ["camera", "tore", "mix", "health"]
RITUAL_CYCLE = ["veille", "rituel", "marche", "frappe"]


def env_str(name: str, default: str = "") -> str:
    return os.getenv(name, default).strip()


def env_float(name: str, default: float) -> float:
    value = env_str(name)
    return float(value) if value else default


def env_int(name: str, default: int) -> int:
    value = env_str(name)
    return int(value) if value else default


def env_bool(name: str, default: bool = False) -> bool:
    value = env_str(name)
    if value == "":
        return default
    return value.lower() in {"1", "true", "yes", "on"}


def clamp(value: float, minimum: float, maximum: float) -> float:
    return max(minimum, min(maximum, value))


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat()


def normalize_slug(value: str, fallback: str = "ensemble") -> str:
    fragments = []
    for char in value.lower():
        fragments.append(char if char.isalnum() or char == "-" else "-")
    slug = "".join(fragments).strip("-")
    while "--" in slug:
        slug = slug.replace("--", "-")
    return slug or fallback


def signed_degrees(value: float) -> float:
    wrapped = math.fmod(value, 360.0)
    if wrapped > 180.0:
        wrapped -= 360.0
    if wrapped < -180.0:
        wrapped += 360.0
    return wrapped


def degrees_to_unit(value: float, divisor: float = 90.0) -> float:
    return clamp(signed_degrees(value) / divisor, -1.0, 1.0)


@dataclass(frozen=True)
class SceptreConfig:
    device_slug: str = field(default_factory=lambda: normalize_slug(env_str("SOWWWL_SCEPTRE_DEVICE_SLUG", "ensemble")))
    endpoint: str = field(default_factory=lambda: env_str("SOWWWL_SCEPTRE_ENDPOINT", "https://pi.sowwwl.cloud/ingest/sceptre"))
    token: str = field(default_factory=lambda: env_str("SOWWWL_SCEPTRE_TOKEN", env_str("SOWWWL_PI_TOKEN")))
    poll_seconds: float = field(default_factory=lambda: max(0.5, env_float("SOWWWL_SCEPTRE_POLL_SECONDS", 1.6)))
    timeout_seconds: float = field(default_factory=lambda: max(1.0, env_float("SOWWWL_SCEPTRE_TIMEOUT_SECONDS", 5.0)))
    state_file: Path = field(default_factory=lambda: Path(env_str("SOWWWL_SCEPTRE_STATE_FILE", "/var/lib/sowwwl/sceptre-state.json")))
    simulate: bool = field(default_factory=lambda: env_bool("SOWWWL_SCEPTRE_SIMULATE", False))
    screen_mode: str = field(default_factory=lambda: env_str("SOWWWL_SCEPTRE_SCREEN_MODE", "kiosk") or "kiosk")
    screen_label: str = field(default_factory=lambda: env_str("SOWWWL_SCEPTRE_SCREEN_LABEL", "camera"))
    screen_url: str = field(default_factory=lambda: env_str("SOWWWL_SCEPTRE_SCREEN_URL", "https://pi.sowwwl.cloud/sceptre/ensemble"))
    screen_page: str = field(default_factory=lambda: env_str("SOWWWL_SCEPTRE_SCREEN_PAGE", "camera"))
    max_heartbeat_seconds: float = field(default_factory=lambda: max(4.0, env_float("SOWWWL_SCEPTRE_HEARTBEAT_SECONDS", 10.0)))


class SceptreDaemon:
    def __init__(self, config: SceptreConfig) -> None:
        self.config = config
        self.stop_requested = False
        self.session = requests.Session()
        self.session.headers.update({
            "Accept": "application/json",
            "Content-Type": "application/json",
            "User-Agent": "sowwwl-pi-sceptre/0.1",
        })
        self.sense = self._build_sense_hat()
        self.page_index = PAGE_CYCLE.index(config.screen_page) if config.screen_page in PAGE_CYCLE else 0
        self.ritual_index = 0
        self.last_post_fingerprint = ""
        self.last_post_at = 0.0
        self.last_shake_trigger_at = 0.0
        self.accent_until = 0.0
        self.baselines: dict[str, float] = {}

    def _build_sense_hat(self) -> Any:
        if SenseHat is None:
            return None
        try:
            sense = SenseHat()
            sense.low_light = True
            sense.clear()
            return sense
        except Exception as exc:  # pragma: no cover - hardware path
            print(f"[sceptre] Sense HAT unavailable: {exc}")
            return None

    def stop(self, *_args: Any) -> None:
        self.stop_requested = True

    def wait(self, seconds: float) -> bool:
        deadline = time.monotonic() + max(0.0, seconds)
        while not self.stop_requested and time.monotonic() < deadline:
            time.sleep(min(0.2, deadline - time.monotonic()))
        return self.stop_requested

    def cycle_page(self, delta: int) -> None:
        self.page_index = (self.page_index + delta) % len(PAGE_CYCLE)

    def cycle_ritual(self, delta: int) -> None:
        self.ritual_index = (self.ritual_index + delta) % len(RITUAL_CYCLE)

    def arm_accent(self, strength: float = 1.0) -> None:
        self.accent_until = max(self.accent_until, time.monotonic() + clamp(strength, 0.25, 1.0) * 1.25)

    def read_joystick(self) -> None:
        if self.sense is None:
            return
        try:
            events = self.sense.stick.get_events()
        except Exception:
            return

        for event in events:
            if getattr(event, "action", "") != "pressed":
                continue
            direction = getattr(event, "direction", "")
            if direction == "left":
                self.cycle_page(-1)
            elif direction == "right":
                self.cycle_page(1)
            elif direction == "up":
                self.cycle_ritual(1)
            elif direction == "down":
                self.cycle_ritual(-1)
            elif direction == "middle":
                self.arm_accent(1.0)
                self.ritual_index = RITUAL_CYCLE.index("frappe")

    def update_baseline(self, key: str, value: float) -> float:
        previous = self.baselines.get(key)
        if previous is None:
            self.baselines[key] = value
            return value
        blended = (previous * 0.94) + (value * 0.06)
        self.baselines[key] = blended
        return blended

    def normalize_climate(self, value: float, baseline: float, span: float) -> float:
        return clamp(0.5 + ((value - baseline) / span), 0.0, 1.0)

    def read_sample(self) -> dict[str, Any]:
        self.read_joystick()

        if self.sense is None and not self.config.simulate:
            return {
                "pitch": 0.0,
                "roll": 0.0,
                "yaw": 0.0,
                "tilt_x": 0.0,
                "tilt_y": 0.0,
                "sway": 0.0,
                "shake": 0.0,
                "stillness": 1.0,
                "heading": 0.0,
                "temperature_c": None,
                "humidity_percent": None,
                "pressure_hpa": None,
                "temperature": 0.5,
                "humidity": 0.5,
                "pressure": 0.5,
                "accent": 1.0 if time.monotonic() < self.accent_until else 0.0,
            }

        if self.sense is None:
            now = time.monotonic()
            pitch = math.sin(now * 0.34) * 0.42
            roll = math.cos(now * 0.28) * 0.54
            yaw = math.sin(now * 0.17) * 0.32
            sway = clamp(abs(math.sin(now * 0.82)) * 0.52, 0.0, 1.0)
            shake = clamp(abs(math.sin(now * 1.8)) * 0.28, 0.0, 1.0)
            if shake > 0.24:
                self.arm_accent(0.55)
            stillness = clamp(1.0 - max(sway * 0.82, shake), 0.0, 1.0)
            temperature_c = 22.0 + (math.sin(now * 0.06) * 1.8)
            humidity_percent = 51.0 + (math.cos(now * 0.08) * 7.0)
            pressure_hpa = 1013.0 + (math.sin(now * 0.05) * 4.0)
        else:
            orientation = self.sense.get_orientation_degrees()
            accel = self.sense.get_accelerometer_raw()
            gyro = self.sense.get_gyroscope_raw()
            try:
                heading_degrees = float(self.sense.get_compass())
            except Exception:
                heading_degrees = float(orientation.get("yaw", 0.0))

            pitch = degrees_to_unit(float(orientation.get("pitch", 0.0)))
            roll = degrees_to_unit(float(orientation.get("roll", 0.0)))
            yaw = degrees_to_unit(float(orientation.get("yaw", 0.0)), 180.0)
            gyro_magnitude = math.sqrt(sum(float(gyro.get(axis, 0.0)) ** 2 for axis in ("x", "y", "z")))
            accel_magnitude = math.sqrt(sum(float(accel.get(axis, 0.0)) ** 2 for axis in ("x", "y", "z")))
            sway = clamp((gyro_magnitude / 180.0) + (abs(pitch) * 0.08) + (abs(roll) * 0.08), 0.0, 1.0)
            shake = clamp((abs(accel_magnitude - 1.0) * 2.2) + (gyro_magnitude / 280.0), 0.0, 1.0)
            if shake > 0.72 and (time.monotonic() - self.last_shake_trigger_at) > 0.9:
                self.last_shake_trigger_at = time.monotonic()
                self.arm_accent(0.95)
            stillness = clamp(1.0 - max(sway * 0.84, shake), 0.0, 1.0)
            temperature_c = float(self.sense.get_temperature())
            humidity_percent = float(self.sense.get_humidity())
            pressure_hpa = float(self.sense.get_pressure())
            yaw = degrees_to_unit(heading_degrees, 180.0)

        accent = 1.0 if time.monotonic() < self.accent_until else 0.0
        heading = clamp((yaw + 1.0) * 0.5, 0.0, 1.0)
        baseline_temp = self.update_baseline("temperature", float(temperature_c)) if temperature_c is not None else 22.0
        baseline_humidity = self.update_baseline("humidity", float(humidity_percent)) if humidity_percent is not None else 50.0
        baseline_pressure = self.update_baseline("pressure", float(pressure_hpa)) if pressure_hpa is not None else 1013.0

        return {
            "pitch": pitch,
            "roll": roll,
            "yaw": yaw,
            "tilt_x": roll,
            "tilt_y": pitch,
            "sway": sway,
            "shake": shake,
            "stillness": stillness,
            "heading": heading,
            "temperature_c": temperature_c,
            "humidity_percent": humidity_percent,
            "pressure_hpa": pressure_hpa,
            "temperature": self.normalize_climate(float(temperature_c), baseline_temp, 7.0) if temperature_c is not None else 0.5,
            "humidity": self.normalize_climate(float(humidity_percent), baseline_humidity, 18.0) if humidity_percent is not None else 0.5,
            "pressure": self.normalize_climate(float(pressure_hpa), baseline_pressure, 11.0) if pressure_hpa is not None else 0.5,
            "accent": accent,
        }

    def derive_scene(self, sample: dict[str, Any]) -> str:
        if sample["accent"] > 0.5 or sample["shake"] > 0.72:
            return "frappe"
        if sample["sway"] > 0.44:
            return "traverse"
        if abs(sample["pitch"]) > 0.42 or abs(sample["roll"]) > 0.42:
            return "incline"
        if sample["stillness"] > 0.72:
            return "veille"
        return "rituel"

    def derive_magic(self, scene: str, ritual_mode: str, sample: dict[str, Any]) -> dict[str, str]:
        palette = "ardoise"
        if sample["temperature"] > 0.6:
            palette = "ambre"
        elif sample["humidity"] > 0.62:
            palette = "brume"
        elif sample["pressure"] > 0.58:
            palette = "azur"

        spell = {
            "frappe": "charge percussive",
            "traverse": "marche magnetique",
            "incline": "arc oblique",
            "veille": "silence tenu",
            "rituel": "halo en tension",
        }.get(scene, "silence tenu")

        sigil = {
            "frappe": "marteau",
            "traverse": "rive",
            "incline": "aiguille",
            "veille": "lune",
            "rituel": "spirale",
        }.get(ritual_mode, "lune")

        return {
            "sigil": sigil,
            "palette": palette,
            "spell": spell,
        }

    def build_state(self, sample: dict[str, Any]) -> dict[str, Any]:
        ritual_mode = RITUAL_CYCLE[self.ritual_index]
        page = PAGE_CYCLE[self.page_index]
        scene = self.derive_scene(sample)
        percussion_bias = clamp(max(sample["shake"], sample["accent"], sample["sway"] * 0.46), 0.0, 1.0)
        tempo_bias = clamp((sample["roll"] * 0.42) + (sample["sway"] * 0.86) - 0.18, -1.0, 1.0)
        swing_bias = clamp(sample["roll"] * 0.92, -1.0, 1.0)
        drone_bias = clamp(sample["stillness"] * 0.94, 0.0, 1.0)
        filter_bias = clamp((sample["pitch"] * 0.82) + ((sample["humidity"] - 0.5) * 0.44), -1.0, 1.0)
        volume_bias = clamp(0.16 + (sample["sway"] * 0.46) + (sample["shake"] * 0.3), 0.0, 1.0)
        brightness = clamp(((sample["pressure"] - 0.5) * 1.24) + (sample["stillness"] * 0.22) - 0.16, -1.0, 1.0)
        negative_bias = clamp((sample["shake"] * 0.42) + max(0.0, -brightness) * 0.26, 0.0, 1.0)
        torus_spin = clamp((sample["roll"] * 0.72) + (sample["yaw"] * 0.18), -1.0, 1.0)
        halo = clamp((sample["humidity"] * 0.56) + (sample["stillness"] * 0.32) + (sample["accent"] * 0.2), 0.0, 1.0)
        contrast_bias = clamp((abs(sample["pitch"]) * 0.34) + (sample["sway"] * 0.18) + (sample["pressure"] * 0.3), 0.0, 1.0)
        tint_warmth = clamp((sample["temperature"] - 0.5) * 1.5, -1.0, 1.0)
        magic = self.derive_magic(scene, ritual_mode, sample)

        lead = {
            "frappe": "Le sceptre claque et ouvre la percussion.",
            "traverse": "Le sceptre marche et pousse le tore.",
            "incline": "Le sceptre incline le champ et taille la note.",
            "veille": "Le sceptre garde une veille douce.",
            "rituel": "Le sceptre tient le rite en surface.",
        }.get(scene, "Le sceptre tient le rite en surface.")
        summary = {
            "frappe": "Secousse, accent et joystick peuvent maintenant relancer kick, snare et halo.",
            "traverse": "Le roulis et la marche servent de tempo, pendant que l ecran peut afficher la vue active du rite.",
            "incline": "Pitch et roll servent deja la lumiere, le filtre et la courbure du tore.",
            "veille": "Le sceptre reste calme: climat, halo et stillness gardent la surface respirable.",
            "rituel": "Le climat, l angle et la paume magique traversent deja sowwwl.io et Fenetre harmonique.",
        }.get(scene, "Le climat, l angle et la paume magique traversent deja la surface.")

        accent = clamp(sample["accent"], 0.0, 1.0)
        return {
            "ok": True,
            "device": self.config.device_slug,
            "source": "pi3-bplus-sceptre",
            "scene": scene,
            "ritual_mode": ritual_mode,
            "lead": lead,
            "summary": summary,
            "updated_at": utc_now(),
            "motion": {
                "pitch": sample["pitch"],
                "roll": sample["roll"],
                "yaw": sample["yaw"],
                "tilt_x": sample["tilt_x"],
                "tilt_y": sample["tilt_y"],
                "sway": sample["sway"],
                "shake": sample["shake"],
                "stillness": sample["stillness"],
                "heading": sample["heading"],
            },
            "climate": {
                "temperature": sample["temperature"],
                "humidity": sample["humidity"],
                "pressure": sample["pressure"],
                "temperature_c": sample["temperature_c"],
                "humidity_percent": sample["humidity_percent"],
                "pressure_hpa": sample["pressure_hpa"],
            },
            "music": {
                "tempo_bias": tempo_bias,
                "swing_bias": swing_bias,
                "drone_bias": drone_bias,
                "filter_bias": filter_bias,
                "percussion_bias": percussion_bias,
                "volume_bias": volume_bias,
            },
            "visual": {
                "brightness": brightness,
                "negative_bias": negative_bias,
                "torus_spin": torus_spin,
                "halo": halo,
                "contrast_bias": contrast_bias,
                "tint_warmth": tint_warmth,
            },
            "triggers": {
                "kick": clamp(max(accent, sample["shake"] * 0.82), 0.0, 1.0),
                "snare": clamp(max(accent * 0.84, sample["sway"] * 0.28), 0.0, 1.0),
                "hihat": clamp(max(sample["sway"] * 0.64, sample["shake"] * 0.34), 0.0, 1.0),
                "accent": accent,
            },
            "screen": {
                "page": page,
                "mode": self.config.screen_mode,
                "label": f"{page} · {scene}",
            },
            "magic": magic,
        }

    def render_leds(self, state: dict[str, Any]) -> None:
        if self.sense is None:
            return

        palette_key = state["magic"]["palette"]
        palette = {
            "ardoise": (48, 62, 94),
            "ambre": (136, 84, 22),
            "brume": (72, 96, 108),
            "azur": (42, 94, 138),
        }.get(palette_key, (48, 62, 94))
        halo = clamp(float(state["visual"]["halo"]), 0.0, 1.0)
        accent = clamp(float(state["triggers"]["accent"]), 0.0, 1.0)
        presence = clamp(max(float(state["motion"]["sway"]), float(state["motion"]["shake"])), 0.0, 1.0)
        pixels: list[tuple[int, int, int]] = []

        for y in range(8):
            for x in range(8):
                distance = math.sqrt(((x - 3.5) / 3.5) ** 2 + ((y - 3.5) / 3.5) ** 2)
                ring = clamp(1.0 - distance, 0.0, 1.0)
                red = int(clamp(palette[0] * (0.42 + ring * 0.74 + accent * 0.28), 0, 255))
                green = int(clamp(palette[1] * (0.42 + halo * 0.54 + presence * 0.18), 0, 255))
                blue = int(clamp(palette[2] * (0.42 + ring * 0.32 + halo * 0.42), 0, 255))
                if accent > 0.54 and (x in {3, 4} or y in {3, 4}):
                    red = min(255, red + 78)
                    green = min(255, green + 48)
                    blue = min(255, blue + 32)
                pixels.append((red, green, blue))

        try:
            self.sense.set_pixels(pixels)
        except Exception:
            pass

    def write_state(self, state: dict[str, Any]) -> None:
        self.config.state_file.parent.mkdir(parents=True, exist_ok=True)
        payload = dict(state)
        payload["screen"]["url"] = self.config.screen_url
        self.config.state_file.write_text(
            json.dumps(payload, indent=2, ensure_ascii=False) + "\n",
            encoding="utf-8",
        )

    def fingerprint(self, state: dict[str, Any]) -> str:
        return json.dumps({
            "scene": state["scene"],
            "ritual_mode": state["ritual_mode"],
            "screen": state["screen"],
            "motion": state["motion"],
            "music": state["music"],
            "visual": state["visual"],
            "triggers": state["triggers"],
        }, sort_keys=True, separators=(",", ":"))

    def post_state(self, state: dict[str, Any]) -> None:
        if self.config.token == "" or self.config.endpoint == "":
            print("[sceptre] token or endpoint missing; state kept local only.")
            return

        fingerprint = self.fingerprint(state)
        now = time.monotonic()
        if fingerprint == self.last_post_fingerprint and (now - self.last_post_at) < self.config.max_heartbeat_seconds:
            return

        response = self.session.post(
            self.config.endpoint,
            json=state,
            headers={"Authorization": f"Bearer {self.config.token}"},
            timeout=self.config.timeout_seconds,
        )
        response.raise_for_status()
        self.last_post_fingerprint = fingerprint
        self.last_post_at = now

    def run(self) -> None:
        print("=== sowwwl pi sceptre ===")
        print(f"device: {self.config.device_slug}")
        print(f"endpoint: {self.config.endpoint or 'local-only'}")
        print(f"screen: {self.config.screen_url}")
        print(f"sense-hat: {'yes' if self.sense is not None else 'no'}")
        print(f"simulate: {'yes' if self.config.simulate else 'no'}")

        while not self.stop_requested:
            sample = self.read_sample()
            state = self.build_state(sample)
            self.render_leds(state)
            self.write_state(state)
            try:
                self.post_state(state)
            except requests.RequestException as exc:
                print(f"[sceptre] publish failed: {exc}")
            if self.wait(self.config.poll_seconds):
                break

        if self.sense is not None:
            try:
                self.sense.clear()
            except Exception:
                pass


def main() -> None:
    config = SceptreConfig()
    daemon = SceptreDaemon(config)
    signal.signal(signal.SIGINT, daemon.stop)
    signal.signal(signal.SIGTERM, daemon.stop)
    daemon.run()


if __name__ == "__main__":
    main()
