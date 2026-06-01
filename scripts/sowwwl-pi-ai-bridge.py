#!/usr/bin/env python3
"""
sowwwl-pi-ai-bridge.py

Reads the remote Pi camera snapshot through the Pi 5 host, runs object
detection on the AI HAT+, and publishes a compact camera state back into the
local sowwwl app runtime.
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

import cv2
import numpy as np
import requests
from picamera2.devices.hailo.hailo import Hailo


COCO_LABELS = [
    "person", "bicycle", "car", "motorcycle", "airplane", "bus", "train", "truck",
    "boat", "traffic light", "fire hydrant", "stop sign", "parking meter", "bench",
    "bird", "cat", "dog", "horse", "sheep", "cow", "elephant", "bear", "zebra",
    "giraffe", "backpack", "umbrella", "handbag", "tie", "suitcase", "frisbee",
    "skis", "snowboard", "sports ball", "kite", "baseball bat", "baseball glove",
    "skateboard", "surfboard", "tennis racket", "bottle", "wine glass", "cup",
    "fork", "knife", "spoon", "bowl", "banana", "apple", "sandwich", "orange",
    "broccoli", "carrot", "hot dog", "pizza", "donut", "cake", "chair", "couch",
    "potted plant", "bed", "dining table", "toilet", "tv", "laptop", "mouse",
    "remote", "keyboard", "cell phone", "microwave", "oven", "toaster", "sink",
    "refrigerator", "book", "clock", "vase", "scissors", "teddy bear", "hair drier",
    "toothbrush",
]

PERSON_LABELS = {"person"}
VEHICLE_LABELS = {"bicycle", "car", "motorcycle", "airplane", "bus", "train", "truck", "boat"}
ANIMAL_LABELS = {"bird", "cat", "dog", "horse", "sheep", "cow", "elephant", "bear", "zebra", "giraffe"}


def env_str(name: str, default: str = "") -> str:
    return os.getenv(name, default).strip()


def env_float(name: str, default: float) -> float:
    value = env_str(name)
    return float(value) if value else default


def env_int(name: str, default: int) -> int:
    value = env_str(name)
    return int(value) if value else default


def clamp(value: float, minimum: float = 0.0, maximum: float = 1.0) -> float:
    return max(minimum, min(maximum, value))


def normalize_slug(value: str) -> str:
    fragments = []
    for char in value.lower():
        fragments.append(char if char.isalnum() or char == "-" else "-")
    slug = "".join(fragments).strip("-")
    while "--" in slug:
        slug = slug.replace("--", "-")
    return slug or "pi3-camera-01"


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat()


def default_camera_slug() -> str:
    return normalize_slug(env_str("SOWWWL_PI_AI_CAMERA_SLUG", env_str("SOWWWL_PI_CAMERA_SLUG", "pi3-camera-01")))


def default_snapshot_url() -> str:
    return env_str("SOWWWL_PI_AI_SNAPSHOT_URL", f"http://127.0.0.1/camera/{default_camera_slug()}/snapshot.jpg")


def default_state_path() -> Path:
    return Path(env_str("SOWWWL_PI_AI_STATE_FILE", "/var/lib/sowwwl/camera-ai-bridge.json"))


def default_ingest_token() -> str:
    return env_str("SOWWWL_PI_AI_TOKEN", env_str("SOWWWL_PI_TOKEN"))


@dataclass(frozen=True)
class AiBridgeConfig:
    camera_slug: str = field(default_factory=default_camera_slug)
    snapshot_url: str = field(default_factory=default_snapshot_url)
    ingest_endpoint: str = field(default_factory=lambda: env_str("SOWWWL_PI_AI_ENDPOINT", "http://127.0.0.1/ingest/camera-ai"))
    ingest_token: str = field(default_factory=default_ingest_token)
    model_path: Path = field(default_factory=lambda: Path(env_str("SOWWWL_PI_AI_MODEL", "/usr/share/hailo-models/yolov8m_h10.hef")))
    poll_seconds: float = field(default_factory=lambda: max(0.8, env_float("SOWWWL_PI_AI_POLL_SECONDS", 2.8)))
    request_timeout_seconds: float = field(default_factory=lambda: max(1.0, env_float("SOWWWL_PI_AI_TIMEOUT_SECONDS", 6.0)))
    confidence_threshold: float = field(default_factory=lambda: clamp(env_float("SOWWWL_PI_AI_CONFIDENCE", 0.35), 0.05, 0.99))
    max_detections: int = field(default_factory=lambda: max(1, env_int("SOWWWL_PI_AI_MAX_DETECTIONS", 8)))
    state_file: Path = field(default_factory=default_state_path)


class CameraAiBridge:
    def __init__(self, config: AiBridgeConfig) -> None:
        self.config = config
        self.stop_requested = False
        self.previous_detections: list[dict[str, Any]] = []
        self.last_post_fingerprint = ""
        self.session = requests.Session()
        self.session.headers.update({
            "Accept": "application/json",
            "Cache-Control": "no-store",
            "User-Agent": "sowwwl-pi-ai-bridge/0.1",
        })

    def stop(self, *_args: Any) -> None:
        self.stop_requested = True

    def wait(self, seconds: float) -> bool:
        deadline = time.monotonic() + max(0.0, seconds)
        while not self.stop_requested and time.monotonic() < deadline:
            time.sleep(min(0.25, deadline - time.monotonic()))
        return self.stop_requested

    def fetch_snapshot(self) -> np.ndarray:
        response = self.session.get(
            self.config.snapshot_url,
            timeout=self.config.request_timeout_seconds,
            headers={"Accept": "image/jpeg,image/*;q=0.9"},
        )
        response.raise_for_status()
        frame_bytes = np.frombuffer(response.content, dtype=np.uint8)
        bgr = cv2.imdecode(frame_bytes, cv2.IMREAD_COLOR)
        if bgr is None:
            raise RuntimeError("snapshot decode failed")
        return bgr

    def prepare_frame(self, bgr_frame: np.ndarray, input_shape: tuple[int, int, int]) -> np.ndarray:
        height, width = int(input_shape[0]), int(input_shape[1])
        rgb = cv2.cvtColor(bgr_frame, cv2.COLOR_BGR2RGB)
        resized = cv2.resize(rgb, (width, height), interpolation=cv2.INTER_LINEAR)
        return np.ascontiguousarray(resized, dtype=np.uint8)

    def parse_output(self, raw_output: Any) -> list[dict[str, Any]]:
        if isinstance(raw_output, dict) and len(raw_output) == 1:
            raw_output = next(iter(raw_output.values()))

        if not isinstance(raw_output, list):
            return []

        detections: list[dict[str, Any]] = []
        for class_index, class_rows in enumerate(raw_output[:len(COCO_LABELS)]):
            if class_rows is None:
                continue

            rows = np.asarray(class_rows, dtype=np.float32)
            if rows.size == 0:
                continue

            rows = rows.reshape((-1, 5))
            label = COCO_LABELS[class_index]
            for row in rows:
                y_min, x_min, y_max, x_max, score = [float(value) for value in row[:5]]
                if score < self.config.confidence_threshold:
                    continue

                bbox = [
                    clamp(x_min),
                    clamp(y_min),
                    clamp(x_max),
                    clamp(y_max),
                ]
                width = max(0.0, bbox[2] - bbox[0])
                height = max(0.0, bbox[3] - bbox[1])
                area = clamp(width * height)
                detections.append({
                    "label": label,
                    "score": clamp(score),
                    "bbox": bbox,
                    "area": area,
                    "center": [
                        clamp(bbox[0] + (width / 2.0)),
                        clamp(bbox[1] + (height / 2.0)),
                    ],
                })

        detections.sort(key=lambda item: (item["score"] * 0.82) + (item["area"] * 0.18), reverse=True)
        return detections[:self.config.max_detections]

    def compute_movement(self, detections: list[dict[str, Any]]) -> float:
        if not detections:
            self.previous_detections = []
            return 0.0

        if not self.previous_detections:
            self.previous_detections = [dict(item) for item in detections]
            return clamp(0.16 + (sum(item["area"] for item in detections[:4]) * 0.8))

        score_total = 0.0
        matches = 0
        used_previous: set[int] = set()
        previous = self.previous_detections[: self.config.max_detections]

        for detection in detections[: self.config.max_detections]:
            best_index = -1
            best_score = 99.0
            for index, earlier in enumerate(previous):
                if index in used_previous or earlier.get("label") != detection.get("label"):
                    continue
                center = detection.get("center", [0.0, 0.0])
                earlier_center = earlier.get("center", [0.0, 0.0])
                if len(center) < 2 or len(earlier_center) < 2:
                    continue

                distance = math.dist(center, earlier_center)
                area_gap = abs(float(detection.get("area", 0.0)) - float(earlier.get("area", 0.0)))
                candidate = distance + (area_gap * 0.85)
                if candidate < best_score:
                    best_score = candidate
                    best_index = index

            if best_index >= 0:
                used_previous.add(best_index)
                score_total += clamp(best_score * 2.1)
                matches += 1

        count_delta = abs(len(detections) - len(previous))
        if matches == 0:
            movement = clamp(0.18 + (count_delta * 0.16) + (sum(item["area"] for item in detections[:3]) * 0.5))
        else:
            movement = clamp((score_total / matches) + (count_delta * 0.06))

        self.previous_detections = [dict(item) for item in detections]
        return movement

    def build_state(self, detections: list[dict[str, Any]]) -> dict[str, Any]:
        object_count = len(detections)
        person_count = sum(1 for item in detections if item["label"] in PERSON_LABELS)
        vehicle_count = sum(1 for item in detections if item["label"] in VEHICLE_LABELS)
        animal_count = sum(1 for item in detections if item["label"] in ANIMAL_LABELS)
        dominant = detections[0] if detections else None
        total_area = clamp(sum(float(item["area"]) for item in detections[: self.config.max_detections]))
        density = clamp((total_area * 0.68) + (min(object_count, 6) / 6.0 * 0.32))
        movement = self.compute_movement(detections)
        dominant_score = float(dominant["score"]) if dominant else 0.0
        attention = clamp(
            (dominant_score * 0.44)
            + (movement * 0.24)
            + (density * 0.18)
            + min(person_count, 2) * 0.08
            + min(animal_count, 2) * 0.06
            + min(vehicle_count, 2) * 0.04
        )
        dominant_label = str(dominant["label"]) if dominant else ""

        if object_count == 0:
            scene = "veille"
            lead = "Le Pi 5 laisse encore le paysage respirer."
        elif person_count > 0 and movement >= 0.36:
            scene = "passage"
            lead = "Une presence humaine traverse la fenetre."
        elif person_count > 0:
            scene = "presence"
            lead = "Une presence humaine tient le cadre."
        elif animal_count > 0:
            scene = "frisson"
            lead = "Un vivant leger remue la lisiere."
        elif vehicle_count > 0:
            scene = "traverse"
            lead = "Un passage mecanique densifie le cadre."
        else:
            scene = "formes"
            lead = f"Le Pi 5 accroche surtout {dominant_label or 'une forme diffuse'}."

        if object_count == 0:
            summary = "Aucune detection stable. Le tore reste doux et le paysage garde sa lecture."
        else:
            parts = [
                f"{object_count} forme{'s' if object_count > 1 else ''}",
                f"dominante {dominant_label or 'diffuse'}",
                f"attention {round(attention * 100)}%",
                f"mouvement {round(movement * 100)}%",
            ]
            if person_count > 0:
                parts.append(f"{person_count} humain{'s' if person_count > 1 else ''}")
            if animal_count > 0:
                parts.append(f"{animal_count} {'animaux' if animal_count > 1 else 'animal'}")
            if vehicle_count > 0:
                parts.append(f"{vehicle_count} passage{'s' if vehicle_count > 1 else ''}")
            summary = ". ".join(parts) + "."

        return {
            "ok": True,
            "camera": self.config.camera_slug,
            "scene": scene,
            "lead": lead,
            "summary": summary,
            "model": self.config.model_path.name,
            "dominant_label": dominant_label,
            "dominant_score": round(dominant_score, 4),
            "attention": round(attention, 4),
            "movement": round(movement, 4),
            "density": round(density, 4),
            "object_count": object_count,
            "person_count": person_count,
            "vehicle_count": vehicle_count,
            "animal_count": animal_count,
            "detections": detections,
            "updated_at": utc_now(),
        }

    def write_local_state(self, payload: dict[str, Any]) -> None:
        self.config.state_file.parent.mkdir(parents=True, exist_ok=True)
        self.config.state_file.write_text(
            json.dumps(payload, ensure_ascii=False, indent=2) + "\n",
            encoding="utf-8",
        )

    def post_state(self, payload: dict[str, Any]) -> None:
        headers = {
            "Content-Type": "application/json",
        }
        if self.config.ingest_token:
            headers["Authorization"] = f"Bearer {self.config.ingest_token}"

        response = self.session.post(
            self.config.ingest_endpoint,
            headers=headers,
            data=json.dumps(payload, ensure_ascii=False),
            timeout=self.config.request_timeout_seconds,
        )
        response.raise_for_status()

    def log_state(self, payload: dict[str, Any]) -> None:
        fingerprint = "|".join([
            payload.get("scene", ""),
            payload.get("dominant_label", ""),
            str(payload.get("object_count", 0)),
            str(payload.get("person_count", 0)),
            str(payload.get("vehicle_count", 0)),
            str(payload.get("animal_count", 0)),
        ])
        if fingerprint == self.last_post_fingerprint:
            return

        self.last_post_fingerprint = fingerprint
        print(
            "[ai] scene={scene} dominant={dominant} objects={objects} attention={attention}% movement={movement}%".format(
                scene=payload.get("scene", "veille"),
                dominant=payload.get("dominant_label", "none") or "none",
                objects=payload.get("object_count", 0),
                attention=round(float(payload.get("attention", 0.0)) * 100),
                movement=round(float(payload.get("movement", 0.0)) * 100),
            ),
            flush=True,
        )

    def run_model_loop(self) -> None:
        with Hailo(str(self.config.model_path), output_type="FLOAT32") as model:
            input_shape = tuple(int(value) for value in model.get_input_shape())
            if len(input_shape) != 3:
                raise RuntimeError(f"unexpected model input shape: {input_shape}")

            print(
                f"[ai] model={self.config.model_path.name} input={input_shape} snapshot={self.config.snapshot_url}",
                flush=True,
            )
            while not self.stop_requested:
                started_at = time.monotonic()
                frame = self.fetch_snapshot()
                tensor = self.prepare_frame(frame, input_shape)
                raw_output = model.run(tensor)
                detections = self.parse_output(raw_output)
                payload = self.build_state(detections)
                self.write_local_state(payload)
                self.post_state(payload)
                self.log_state(payload)

                elapsed = time.monotonic() - started_at
                self.wait(max(0.0, self.config.poll_seconds - elapsed))

    def run(self) -> None:
        if not self.config.model_path.is_file():
            raise SystemExit(f"Missing HEF model: {self.config.model_path}")

        print("=== sowwwl pi ai bridge ===", flush=True)
        print(f"camera   : {self.config.camera_slug}", flush=True)
        print(f"snapshot : {self.config.snapshot_url}", flush=True)
        print(f"endpoint : {self.config.ingest_endpoint}", flush=True)
        print(f"model    : {self.config.model_path}", flush=True)

        while not self.stop_requested:
            try:
                self.run_model_loop()
            except requests.RequestException as exc:
                print(f"[ai] network error: {exc}", flush=True)
            except Exception as exc:  # pragma: no cover - depends on Pi runtime.
                print(f"[ai] loop error: {exc}", flush=True)

            if self.wait(4.0):
                break


def main() -> None:
    config = AiBridgeConfig()
    bridge = CameraAiBridge(config)

    signal.signal(signal.SIGINT, bridge.stop)
    signal.signal(signal.SIGTERM, bridge.stop)

    bridge.run()


if __name__ == "__main__":
    main()
