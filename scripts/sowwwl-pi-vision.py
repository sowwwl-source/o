#!/usr/bin/env python3
"""
sowwwl-pi-vision.py

Main sensory daemon for an O. pocket land.

This process does not stream the world to a cloud.
It listens locally, compresses reality into small traces, and feeds the Plasma
only when something meaningful moves.
"""

from __future__ import annotations

import json
import os
import signal
import threading
import time
from dataclasses import asdict, dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterable

import cv2
import numpy as np
import requests

try:
    from picamera2 import Picamera2
except ImportError as exc:  # pragma: no cover - only true off the Raspberry Pi.
    raise SystemExit(
        "Picamera2 is required on Raspberry Pi 5. Install python3-picamera2 with apt."
    ) from exc


def env_str(name: str, default: str = "") -> str:
    return os.getenv(name, default).strip()


def env_int(name: str, default: int) -> int:
    value = env_str(name)
    return int(value) if value else default


def env_float(name: str, default: float) -> float:
    value = env_str(name)
    return float(value) if value else default


def parse_camera_ids(raw_value: str) -> list[int]:
    camera_ids: list[int] = []
    for fragment in raw_value.split(","):
        fragment = fragment.strip()
        if fragment:
            camera_ids.append(int(fragment))
    return camera_ids or [0, 1]


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat()


@dataclass(frozen=True)
class VisionConfig:
    plasma_endpoint: str = field(default_factory=lambda: env_str("SOWWWL_PI_ENDPOINT", "http://localhost/ingest/sensor"))
    plasma_token: str = field(default_factory=lambda: env_str("SOWWWL_PI_TOKEN"))
    land_slug: str = field(default_factory=lambda: env_str("SOWWWL_PI_LAND_SLUG", "pocket-land"))
    camera_ids: list[int] = field(default_factory=lambda: parse_camera_ids(env_str("SOWWWL_PI_CAMERAS", "0,1")))
    trace_spool: Path = field(default_factory=lambda: Path(env_str("SOWWWL_PI_SPOOL", "/var/lib/sowwwl/plasma-spool.jsonl")))
    width: int = field(default_factory=lambda: env_int("SOWWWL_PI_WIDTH", 640))
    height: int = field(default_factory=lambda: env_int("SOWWWL_PI_HEIGHT", 480))
    framerate: float = field(default_factory=lambda: env_float("SOWWWL_PI_FRAMERATE", 12.0))
    min_area: int = field(default_factory=lambda: env_int("SOWWWL_PI_MIN_AREA", 1500))
    cooldown_seconds: float = field(default_factory=lambda: env_float("SOWWWL_PI_COOLDOWN_SECONDS", 4.0))
    request_timeout_seconds: float = field(default_factory=lambda: env_float("SOWWWL_PI_TIMEOUT_SECONDS", 2.5))
    plasma_replay_interval_seconds: float = field(default_factory=lambda: env_float("SOWWWL_PI_REPLAY_INTERVAL_SECONDS", 20.0))
    max_replay_batch: int = field(default_factory=lambda: env_int("SOWWWL_PI_MAX_REPLAY_BATCH", 24))


@dataclass(frozen=True)
class PlasmaTrace:
    event: str
    camera: str
    timestamp: str
    land_slug: str
    message: str
    metrics: dict[str, Any]

    @classmethod
    def presence(
        cls,
        *,
        camera_id: int,
        land_slug: str,
        largest_area: float,
        contour_count: int,
        frame_luma: float,
    ) -> "PlasmaTrace":
        return cls(
            event="presence_detected",
            camera=f"cam-{camera_id}",
            timestamp=utc_now(),
            land_slug=land_slug,
            message="presence locale detectee par la terre de poche",
            metrics={
                "largest_area": round(largest_area, 2),
                "contour_count": contour_count,
                "frame_luma": round(frame_luma, 4),
            },
        )


class OfflineSpool:
    """A small shore for traces emitted while the pocket land is roaming."""

    def __init__(self, path: Path) -> None:
        self.path = path
        self.lock = threading.Lock()
        self.path.parent.mkdir(parents=True, exist_ok=True)

    def append(self, trace: PlasmaTrace) -> None:
        with self.lock:
            with self.path.open("a", encoding="utf-8") as handle:
                handle.write(json.dumps(asdict(trace), ensure_ascii=False) + "\n")

    def pop_batch(self, limit: int) -> list[PlasmaTrace]:
        with self.lock:
            if not self.path.exists():
                return []

            lines = self.path.read_text(encoding="utf-8").splitlines()
            selected = lines[:limit]
            remaining = lines[limit:]
            self.path.write_text(("\n".join(remaining) + "\n") if remaining else "", encoding="utf-8")

        traces: list[PlasmaTrace] = []
        for line in selected:
            try:
                payload = json.loads(line)
                traces.append(PlasmaTrace(**payload))
            except (TypeError, ValueError, json.JSONDecodeError):
                continue
        return traces

    def restore(self, traces: Iterable[PlasmaTrace]) -> None:
        for trace in traces:
            self.append(trace)


class PlasmaEmitter:
    """Turns local perception into a small, authorized pulse toward the shore node."""

    def __init__(self, config: VisionConfig, spool: OfflineSpool) -> None:
        self.config = config
        self.spool = spool
        self.session = requests.Session()

    def feed_plasma(self, trace: PlasmaTrace, *, defer_on_failure: bool = True) -> bool:
        if self.config.plasma_token == "":
            print("[plasma] SOWWWL_PI_TOKEN missing; trace kept local.")
            if defer_on_failure:
                self.spool.append(trace)
            return False

        headers = {
            "Authorization": f"Bearer {self.config.plasma_token}",
            "Accept": "application/json",
            "Content-Type": "application/json",
        }

        try:
            response = self.session.post(
                self.config.plasma_endpoint,
                headers=headers,
                json=asdict(trace),
                timeout=self.config.request_timeout_seconds,
            )
            response.raise_for_status()
            print(f"[plasma] {trace.camera} -> {response.status_code} {trace.event}")
            return True
        except requests.RequestException as exc:
            print(f"[plasma] shore unreachable; trace sleeps locally: {exc}")
            if defer_on_failure:
                self.spool.append(trace)
            return False

    def replay_sleeping_traces(self) -> None:
        traces = self.spool.pop_batch(self.config.max_replay_batch)
        if not traces:
            return

        unsent: list[PlasmaTrace] = []
        for trace in traces:
            if not self.feed_plasma(trace, defer_on_failure=False):
                unsent.append(trace)

        if unsent:
            self.spool.restore(unsent)


class PocketEye(threading.Thread):
    """One MIPI eye watching locally without extracting video from the wearer."""

    def __init__(self, camera_id: int, config: VisionConfig, emitter: PlasmaEmitter, stop_event: threading.Event) -> None:
        super().__init__(name=f"pocket-eye-{camera_id}", daemon=True)
        self.camera_id = camera_id
        self.config = config
        self.emitter = emitter
        self.stop_event = stop_event
        self.last_trace_at = 0.0

    def run(self) -> None:
        camera = Picamera2(camera=self.camera_id)
        camera.configure(
            camera.create_video_configuration(
                main={"size": (self.config.width, self.config.height), "format": "RGB888"},
                controls={"FrameRate": self.config.framerate},
            )
        )

        subtractor = cv2.createBackgroundSubtractorMOG2(
            history=500,
            varThreshold=25,
            detectShadows=False,
        )

        print(f"[cam-{self.camera_id}] eye opens {self.config.width}x{self.config.height}")
        camera.start()

        try:
            while not self.stop_event.is_set():
                frame = camera.capture_array()
                trace = self.listen_for_presence(frame, subtractor)
                if trace is not None:
                    self.emitter.feed_plasma(trace)
                    self.last_trace_at = time.monotonic()

                time.sleep(max(0.01, 1.0 / self.config.framerate))
        finally:
            camera.stop()
            print(f"[cam-{self.camera_id}] eye closes")

    def listen_for_presence(self, frame: np.ndarray, subtractor: cv2.BackgroundSubtractor) -> PlasmaTrace | None:
        gray = cv2.cvtColor(frame, cv2.COLOR_RGB2GRAY)
        gray = cv2.GaussianBlur(gray, (21, 21), 0)
        foreground = subtractor.apply(gray)
        foreground = cv2.erode(foreground, None, iterations=1)
        foreground = cv2.dilate(foreground, None, iterations=2)

        contours, _ = cv2.findContours(foreground.copy(), cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        significant = [contour for contour in contours if cv2.contourArea(contour) >= self.config.min_area]
        largest_area = max((cv2.contourArea(contour) for contour in significant), default=0.0)

        now = time.monotonic()
        if largest_area < self.config.min_area:
            return None

        if now - self.last_trace_at < self.config.cooldown_seconds:
            return None

        return PlasmaTrace.presence(
            camera_id=self.camera_id,
            land_slug=self.config.land_slug,
            largest_area=largest_area,
            contour_count=len(significant),
            frame_luma=float(np.mean(gray) / 255.0),
        )


class PocketLandDaemon:
    """The local sovereign loop: eyes, plasma, and graceful roaming."""

    def __init__(self, config: VisionConfig) -> None:
        self.config = config
        self.stop_event = threading.Event()
        self.spool = OfflineSpool(config.trace_spool)
        self.emitter = PlasmaEmitter(config, self.spool)
        self.eyes = [
            PocketEye(camera_id, config, self.emitter, self.stop_event)
            for camera_id in config.camera_ids
        ]

    def start(self) -> None:
        print("=== O. pocket land vision ===")
        print(f"shore node: {self.config.plasma_endpoint}")
        print(f"land: {self.config.land_slug}")
        print(f"eyes: {self.config.camera_ids}")
        print(f"spool: {self.config.trace_spool}")

        for eye in self.eyes:
            eye.start()
            time.sleep(0.8)

        while not self.stop_event.is_set():
            self.emitter.replay_sleeping_traces()
            time.sleep(self.config.plasma_replay_interval_seconds)

    def stop(self, *_: object) -> None:
        print("[daemon] land entering asleep state")
        self.stop_event.set()


def main() -> None:
    config = VisionConfig()
    daemon = PocketLandDaemon(config)
    signal.signal(signal.SIGINT, daemon.stop)
    signal.signal(signal.SIGTERM, daemon.stop)
    daemon.start()


if __name__ == "__main__":
    main()
