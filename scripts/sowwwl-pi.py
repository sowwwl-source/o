#!/usr/bin/env python3
"""
Tiny 3ternet vision daemon for Raspberry Pi 5.

Configuration is read from environment variables so the script can travel
between the lab droplet, a desk test, and the future necklace without secrets
being committed in the repository.
"""

from __future__ import annotations

import os
import threading
import time
from datetime import datetime, timezone
from typing import Iterable

import cv2
import requests

try:
    from picamera2 import Picamera2
except ImportError:
    Picamera2 = None


ENDPOINT_URL = os.getenv("SOWWWL_PI_ENDPOINT", "https://lab.sowwwl.cloud/ingest/sensor")
AUTH_TOKEN = os.getenv("SOWWWL_PI_TOKEN", "")
LAND_SLUG = os.getenv("SOWWWL_PI_LAND_SLUG", "")
CAMERA_IDS = os.getenv("SOWWWL_PI_CAMERAS", "0,1")
MIN_AREA = int(os.getenv("SOWWWL_PI_MIN_AREA", "1500"))
FRAMERATE = float(os.getenv("SOWWWL_PI_FRAMERATE", "15"))
COOLDOWN_SECONDS = float(os.getenv("SOWWWL_PI_COOLDOWN_SECONDS", "3"))
REQUEST_TIMEOUT = float(os.getenv("SOWWWL_PI_TIMEOUT_SECONDS", "2"))
RESOLUTION = (
    int(os.getenv("SOWWWL_PI_WIDTH", "640")),
    int(os.getenv("SOWWWL_PI_HEIGHT", "480")),
)


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat()


def configured_camera_ids() -> list[int]:
    ids: list[int] = []
    for raw_value in CAMERA_IDS.split(","):
        raw_value = raw_value.strip()
        if raw_value == "":
            continue
        ids.append(int(raw_value))
    return ids or [0]


def send_signal(camera_id: int, message: str, metrics: dict | None = None) -> None:
    if AUTH_TOKEN == "":
        print("SOWWWL_PI_TOKEN is missing; refusing to send sensor events.")
        return

    payload = {
        "event": "motion_detected",
        "camera": f"cam-{camera_id}",
        "land_slug": LAND_SLUG,
        "timestamp": utc_now(),
        "message": message,
        "metrics": metrics or {},
    }
    headers = {
        "Authorization": f"Bearer {AUTH_TOKEN}",
        "Content-Type": "application/json",
        "Accept": "application/json",
    }

    try:
        response = requests.post(ENDPOINT_URL, json=payload, headers=headers, timeout=REQUEST_TIMEOUT)
        response.raise_for_status()
        print(f"[{datetime.now().strftime('%H:%M:%S')}] signal sent cam-{camera_id} -> {response.status_code}")
    except requests.exceptions.RequestException as exc:
        print(f"[cam-{camera_id}] network send failed: {exc}")


def camera_worker(camera_id: int) -> None:
    if Picamera2 is None:
        print(f"[cam-{camera_id}] Picamera2 unavailable; simulation mode.")
        while True:
            send_signal(camera_id, "simulation heartbeat", {"simulated": True})
            time.sleep(max(COOLDOWN_SECONDS, 10))

    print(f"[cam-{camera_id}] initializing {RESOLUTION[0]}x{RESOLUTION[1]} @ {FRAMERATE}fps")
    camera = Picamera2(camera=camera_id)
    camera.configure(camera.create_video_configuration(main={"size": RESOLUTION, "format": "RGB888"}))
    camera.start()

    subtractor = cv2.createBackgroundSubtractorMOG2(history=500, varThreshold=25, detectShadows=False)
    last_signal_time = 0.0

    try:
        while True:
            frame = camera.capture_array()
            gray = cv2.cvtColor(frame, cv2.COLOR_RGB2GRAY)
            gray = cv2.GaussianBlur(gray, (21, 21), 0)
            foreground = subtractor.apply(gray)
            foreground = cv2.dilate(foreground, None, iterations=2)
            contours, _ = cv2.findContours(foreground.copy(), cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
            largest_area = max((cv2.contourArea(contour) for contour in contours), default=0)

            now = time.time()
            if largest_area >= MIN_AREA and now - last_signal_time > COOLDOWN_SECONDS:
                send_signal(
                    camera_id,
                    "mouvement detecte sur la surface",
                    {"largest_area": round(largest_area, 2), "min_area": MIN_AREA},
                )
                last_signal_time = now

            time.sleep(max(0.01, 1.0 / FRAMERATE))
    except KeyboardInterrupt:
        print(f"[cam-{camera_id}] stop requested.")
    finally:
        camera.stop()
        print(f"[cam-{camera_id}] stopped.")


def start_workers(camera_ids: Iterable[int]) -> list[threading.Thread]:
    threads: list[threading.Thread] = []
    for camera_id in camera_ids:
        thread = threading.Thread(target=camera_worker, args=(camera_id,), daemon=True)
        thread.start()
        threads.append(thread)
        time.sleep(1)
    return threads


def main() -> None:
    print("=== sowwwl pi vision ===")
    print(f"endpoint: {ENDPOINT_URL}")
    print(f"land: {LAND_SLUG or 'unbound'}")
    print(f"cameras: {configured_camera_ids()}")
    start_workers(configured_camera_ids())

    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        print("general stop requested.")


if __name__ == "__main__":
    main()
