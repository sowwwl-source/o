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
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any, Iterable
from urllib.parse import parse_qs, urlparse

import cv2
import numpy as np
import requests

try:
    from picamera2 import Picamera2
except ImportError as exc:  # pragma: no cover - only true off the Raspberry Pi.
    raise SystemExit(
        "Picamera2 is required on the Raspberry Pi camera node. Install python3-picamera2 with apt and verify rpicam-hello --list-cameras."
    ) from exc


def env_str(name: str, default: str = "") -> str:
    return os.getenv(name, default).strip()


def env_int(name: str, default: int) -> int:
    value = env_str(name)
    return int(value) if value else default


def env_float(name: str, default: float) -> float:
    value = env_str(name)
    return float(value) if value else default


def env_bool(name: str, default: bool = False) -> bool:
    value = env_str(name).lower()
    if value == "":
        return default
    return value in {"1", "true", "yes", "on"}


def parse_camera_ids(raw_value: str) -> list[int]:
    camera_ids: list[int] = []
    for fragment in raw_value.split(","):
        fragment = fragment.strip()
        if fragment:
            camera_ids.append(int(fragment))
    return camera_ids or [0, 1]


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat()


def open_picamera(camera_id: int) -> Picamera2:
    """Support both older and newer Picamera2 constructor spellings."""

    last_error: TypeError | None = None
    for kwargs in ({"camera_num": camera_id}, {"camera": camera_id}):
        try:
            return Picamera2(**kwargs)
        except TypeError as exc:
            last_error = exc

    try:
        return Picamera2(camera_id)
    except TypeError as exc:
        last_error = exc

    raise RuntimeError(f"Unable to open Picamera2 camera {camera_id}: {last_error}")


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
    stream_enabled: bool = field(default_factory=lambda: env_bool("SOWWWL_PI_STREAM_ENABLED", False))
    stream_bind: str = field(default_factory=lambda: env_str("SOWWWL_PI_STREAM_BIND", "127.0.0.1"))
    stream_port: int = field(default_factory=lambda: env_int("SOWWWL_PI_STREAM_PORT", 8082))
    stream_token: str = field(default_factory=lambda: env_str("SOWWWL_PI_STREAM_TOKEN"))
    stream_max_fps: float = field(default_factory=lambda: env_float("SOWWWL_PI_STREAM_MAX_FPS", 4.0))
    stream_jpeg_quality: int = field(default_factory=lambda: max(30, min(95, env_int("SOWWWL_PI_STREAM_JPEG_QUALITY", 72))))


class LiveFrameHub:
    """Keeps the latest compressed frame per camera for snapshot and MJPEG readers."""

    def __init__(self, config: VisionConfig) -> None:
        self.config = config
        self.lock = threading.Lock()
        self.condition = threading.Condition(self.lock)
        self.frames: dict[int, bytes] = {}
        self.timestamps: dict[int, float] = {}

    def publish(self, camera_id: int, frame: np.ndarray) -> None:
        if not self.config.stream_enabled:
            return

        now = time.monotonic()
        min_interval = 1.0 / max(0.5, self.config.stream_max_fps)

        with self.condition:
            previous = self.timestamps.get(camera_id, 0.0)
            if now - previous < min_interval:
                return

        bgr = cv2.cvtColor(frame, cv2.COLOR_RGB2BGR)
        encoded_ok, encoded = cv2.imencode(
            ".jpg",
            bgr,
            [int(cv2.IMWRITE_JPEG_QUALITY), int(self.config.stream_jpeg_quality)],
        )
        if not encoded_ok:
            return

        payload = encoded.tobytes()
        with self.condition:
            self.frames[camera_id] = payload
            self.timestamps[camera_id] = now
            self.condition.notify_all()

    def latest(self, camera_id: int) -> tuple[bytes | None, float]:
        with self.lock:
            return self.frames.get(camera_id), self.timestamps.get(camera_id, 0.0)

    def wait_for_newer(self, camera_id: int, previous_timestamp: float, timeout: float = 10.0) -> tuple[bytes | None, float]:
        with self.condition:
            self.condition.wait_for(
                lambda: self.timestamps.get(camera_id, 0.0) > previous_timestamp,
                timeout=timeout,
            )
            return self.frames.get(camera_id), self.timestamps.get(camera_id, 0.0)


class PiCameraStreamServer(ThreadingHTTPServer):
    daemon_threads = True
    allow_reuse_address = True

    def __init__(
        self,
        server_address: tuple[str, int],
        handler_class: type[BaseHTTPRequestHandler],
        *,
        frame_hub: LiveFrameHub,
        stop_event: threading.Event,
        token: str,
        default_camera_id: int,
    ) -> None:
        self.frame_hub = frame_hub
        self.stop_event = stop_event
        self.token = token
        self.default_camera_id = default_camera_id
        super().__init__(server_address, handler_class)


class PiCameraStreamHandler(BaseHTTPRequestHandler):
    server_version = "sowwwl-pi-stream/0.1"

    def do_GET(self) -> None:  # noqa: N802 - handler API
        parsed = urlparse(self.path)
        if not self.authorized(parsed.query):
            self.respond_text(HTTPStatus.UNAUTHORIZED, "stream authorization required\n")
            return

        if parsed.path == "/healthz":
            self.respond_json(HTTPStatus.OK, b'{"ok":true,"stream":true}')
            return

        camera_id = self.resolve_camera_id(parsed.path)
        if camera_id is None:
            self.respond_text(HTTPStatus.NOT_FOUND, "not found\n")
            return

        if parsed.path.endswith("/snapshot.jpg") or parsed.path == "/snapshot.jpg":
            self.serve_snapshot(camera_id)
            return

        if parsed.path.endswith("/stream.mjpg") or parsed.path == "/stream.mjpg":
            self.serve_stream(camera_id)
            return

        self.respond_text(HTTPStatus.NOT_FOUND, "not found\n")

    def log_message(self, format: str, *args: object) -> None:  # noqa: A003 - handler API
        return

    @property
    def stream_server(self) -> PiCameraStreamServer:
        assert isinstance(self.server, PiCameraStreamServer)
        return self.server

    def authorized(self, raw_query: str) -> bool:
        server = self.stream_server
        if server.token == "":
            return True

        query_token = parse_qs(raw_query).get("token", [""])[0].strip()
        header_token = self.headers.get("X-Sowwwl-Stream-Token", "").strip()
        authorization = self.headers.get("Authorization", "").strip()
        bearer_token = authorization[7:].strip() if authorization.lower().startswith("bearer ") else ""

        return any(candidate == server.token for candidate in (query_token, header_token, bearer_token) if candidate)

    def resolve_camera_id(self, path: str) -> int | None:
        normalized = path.rstrip("/") or "/"
        if normalized in {"/snapshot.jpg", "/stream.mjpg"}:
            return self.stream_server.default_camera_id

        parts = [fragment for fragment in normalized.split("/") if fragment]
        if len(parts) != 2 or not parts[0].startswith("cam-"):
            return None

        try:
            return int(parts[0].split("-", 1)[1])
        except ValueError:
            return None

    def serve_snapshot(self, camera_id: int) -> None:
        frame, timestamp = self.stream_server.frame_hub.latest(camera_id)
        if frame is None or timestamp <= 0:
            self.respond_text(HTTPStatus.SERVICE_UNAVAILABLE, "camera frame not ready\n")
            return

        self.send_response(HTTPStatus.OK)
        self.send_header("Content-Type", "image/jpeg")
        self.send_header("Cache-Control", "no-store")
        self.send_header("Content-Length", str(len(frame)))
        self.end_headers()
        self.wfile.write(frame)

    def serve_stream(self, camera_id: int) -> None:
        boundary = "frame"
        self.send_response(HTTPStatus.OK)
        self.send_header("Age", "0")
        self.send_header("Cache-Control", "no-store")
        self.send_header("Pragma", "no-cache")
        self.send_header("Connection", "close")
        self.send_header("Content-Type", f"multipart/x-mixed-replace; boundary={boundary}")
        self.end_headers()

        latest, previous_timestamp = self.stream_server.frame_hub.latest(camera_id)
        if latest is not None and previous_timestamp > 0:
            self.write_mjpeg_part(boundary, latest)

        try:
            while not self.stream_server.stop_event.is_set():
                frame, next_timestamp = self.stream_server.frame_hub.wait_for_newer(camera_id, previous_timestamp, timeout=10.0)
                if frame is None or next_timestamp <= previous_timestamp:
                    continue

                previous_timestamp = next_timestamp
                self.write_mjpeg_part(boundary, frame)
        except (BrokenPipeError, ConnectionResetError):
            return

    def write_mjpeg_part(self, boundary: str, frame: bytes) -> None:
        self.wfile.write(f"--{boundary}\r\n".encode("ascii"))
        self.wfile.write(b"Content-Type: image/jpeg\r\n")
        self.wfile.write(f"Content-Length: {len(frame)}\r\n\r\n".encode("ascii"))
        self.wfile.write(frame)
        self.wfile.write(b"\r\n")
        self.wfile.flush()

    def respond_text(self, status: HTTPStatus, body: str) -> None:
        payload = body.encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "text/plain; charset=utf-8")
        self.send_header("Cache-Control", "no-store")
        self.send_header("Content-Length", str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)

    def respond_json(self, status: HTTPStatus, payload: bytes) -> None:
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Cache-Control", "no-store")
        self.send_header("Content-Length", str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)


class PiCameraStreamThread(threading.Thread):
    def __init__(self, config: VisionConfig, frame_hub: LiveFrameHub, stop_event: threading.Event) -> None:
        super().__init__(name="pi-camera-stream", daemon=True)
        self.config = config
        self.server = PiCameraStreamServer(
            (config.stream_bind, config.stream_port),
            PiCameraStreamHandler,
            frame_hub=frame_hub,
            stop_event=stop_event,
            token=config.stream_token,
            default_camera_id=config.camera_ids[0] if config.camera_ids else 0,
        )

    def run(self) -> None:
        print(
            f"[stream] mjpeg on http://{self.config.stream_bind}:{self.config.stream_port}/stream.mjpg"
            + (" (token protected)" if self.config.stream_token else "")
        )
        self.server.serve_forever(poll_interval=0.5)

    def stop(self) -> None:
        self.server.shutdown()
        self.server.server_close()


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

    def __init__(
        self,
        camera_id: int,
        config: VisionConfig,
        emitter: PlasmaEmitter,
        frame_hub: LiveFrameHub,
        stop_event: threading.Event,
    ) -> None:
        super().__init__(name=f"pocket-eye-{camera_id}", daemon=True)
        self.camera_id = camera_id
        self.config = config
        self.emitter = emitter
        self.frame_hub = frame_hub
        self.stop_event = stop_event
        self.last_trace_at = 0.0

    def run(self) -> None:
        camera = open_picamera(self.camera_id)
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
                self.frame_hub.publish(self.camera_id, frame)
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
        self.frame_hub = LiveFrameHub(config)
        self.eyes = [
            PocketEye(camera_id, config, self.emitter, self.frame_hub, self.stop_event)
            for camera_id in config.camera_ids
        ]
        self.stream_thread = PiCameraStreamThread(config, self.frame_hub, self.stop_event) if config.stream_enabled else None

    def start(self) -> None:
        print("=== O. pocket land vision ===")
        print(f"shore node: {self.config.plasma_endpoint}")
        print(f"land: {self.config.land_slug}")
        print(f"eyes: {self.config.camera_ids}")
        print(f"spool: {self.config.trace_spool}")
        if self.config.stream_enabled:
            print(f"stream: http://{self.config.stream_bind}:{self.config.stream_port}/stream.mjpg")
            if self.config.stream_token:
                print("stream auth: token required")

        if self.stream_thread is not None:
            self.stream_thread.start()

        for eye in self.eyes:
            eye.start()
            time.sleep(0.8)

        while not self.stop_event.is_set():
            self.emitter.replay_sleeping_traces()
            time.sleep(self.config.plasma_replay_interval_seconds)

    def stop(self, *_: object) -> None:
        print("[daemon] land entering asleep state")
        self.stop_event.set()
        if self.stream_thread is not None:
            self.stream_thread.stop()


def main() -> None:
    config = VisionConfig()
    daemon = PocketLandDaemon(config)
    signal.signal(signal.SIGINT, daemon.stop)
    signal.signal(signal.SIGTERM, daemon.stop)
    daemon.start()


if __name__ == "__main__":
    main()
