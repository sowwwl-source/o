function initSceptreConsole() {
	const root = document.querySelector("[data-sceptre-console-root]");
	if (!(root instanceof HTMLElement)) {
		return;
	}

	const feedUrl = (root.dataset.sceptreFeed || "").trim();
	const badgeNode = root.querySelector("[data-sceptre-console-badge]");
	const leadNode = root.querySelector("[data-sceptre-console-lead]");
	const summaryNode = root.querySelector("[data-sceptre-console-summary]");
	const spellNode = root.querySelector("[data-sceptre-console-spell]");
	const ritualNode = root.querySelector("[data-sceptre-console-ritual]");
	const screenNode = root.querySelector("[data-sceptre-console-screen]");
	const motionNode = root.querySelector("[data-sceptre-console-motion]");
	const climateNode = root.querySelector("[data-sceptre-console-climate]");
	const percussionNode = root.querySelector("[data-sceptre-console-percussion]");
	const haloNode = root.querySelector("[data-sceptre-console-halo]");
	const tempoNode = root.querySelector("[data-sceptre-console-tempo]");
	const filterNode = root.querySelector("[data-sceptre-console-filter]");
	const negativeNode = root.querySelector("[data-sceptre-console-negative]");
	const spinNode = root.querySelector("[data-sceptre-console-spin]");
	const state = {
		payload: normalizeSceptreState(null),
		timer: 0,
		inFlight: false,
	};

	const setText = (node, value) => {
		if (node instanceof HTMLElement) {
			node.textContent = value;
		}
	};
	const formatPercent = (value) => `${Math.round(clampNumber(Number(value) || 0, 0, 1) * 100)}%`;
	const formatSignedPercent = (value) => {
		const safeValue = clampNumber(Number(value) || 0, -1, 1);
		const percent = Math.round(Math.abs(safeValue) * 100);
		if (percent === 0) {
			return "0%";
		}
		return `${safeValue > 0 ? "+" : "−"}${percent}%`;
	};
	const climateLabel = (payload) => {
		const parts = [];
		if (Number.isFinite(payload.climate.temperature_c)) {
			parts.push(`${Math.round(payload.climate.temperature_c)}°`);
		}
		if (Number.isFinite(payload.climate.humidity_percent)) {
			parts.push(`${Math.round(payload.climate.humidity_percent)}%`);
		}
		if (Number.isFinite(payload.climate.pressure_hpa)) {
			parts.push(`${Math.round(payload.climate.pressure_hpa)}hPa`);
		}
		return parts.length ? parts.join(" · ") : "respire";
	};

	const render = () => {
		const payload = state.payload;
		const motionLevel = clampNumber(Math.max(payload.motion.sway, payload.motion.shake, Math.abs(payload.motion.pitch) * 0.4), 0, 1);
		setText(badgeNode, payload.freshness === "fresh" ? payload.scene : (payload.freshness === "stale" ? "attente" : "veille"));
		setText(leadNode, payload.lead);
		setText(summaryNode, payload.summary);
		setText(spellNode, payload.magic.spell || "silence tenu");
		setText(ritualNode, payload.ritualMode || "veille");
		setText(screenNode, payload.screen.label || payload.screen.page || "veille");
		setText(motionNode, formatPercent(motionLevel));
		setText(climateNode, climateLabel(payload));
		setText(percussionNode, formatPercent(Math.max(payload.music.percussionBias, payload.triggers.accent)));
		setText(haloNode, formatPercent(payload.visual.halo));
		setText(tempoNode, formatSignedPercent(payload.music.tempoBias));
		setText(filterNode, formatSignedPercent(payload.music.filterBias));
		setText(negativeNode, formatPercent(payload.visual.negativeBias));
		setText(spinNode, formatSignedPercent(payload.visual.torusSpin));

		root.dataset.sceptreFreshness = payload.freshness;
		root.style.setProperty("--sceptre-presence", motionLevel.toFixed(3));
		root.style.setProperty("--sceptre-halo", payload.visual.halo.toFixed(3));
		root.style.setProperty("--sceptre-negative", payload.visual.negativeBias.toFixed(3));
		root.style.setProperty("--sceptre-spin", payload.visual.torusSpin.toFixed(3));
		root.style.setProperty("--sceptre-warmth", payload.visual.tintWarmth.toFixed(3));
	};

	const fetchState = async () => {
		if (!feedUrl) {
			return;
		}
		const response = await fetch(feedUrl, {
			cache: "no-store",
			headers: { Accept: "application/json" },
		});
		if (!response.ok) {
			throw new Error(`sceptre console feed ${response.status}`);
		}
		state.payload = normalizeSceptreState(await response.json());
		render();
	};

	const clearPoll = () => {
		if (state.timer) {
			window.clearTimeout(state.timer);
			state.timer = 0;
		}
	};

	const schedulePoll = (delay = 2800) => {
		clearPoll();
		if (!feedUrl) {
			return;
		}
		state.timer = window.setTimeout(async () => {
			if (!document.hidden && !state.inFlight) {
				state.inFlight = true;
				try {
					await fetchState();
				} catch {
					// Keep the last stable console state.
				} finally {
					state.inFlight = false;
				}
			}
			schedulePoll(document.hidden ? 5200 : 2800);
		}, delay);
	};

	render();
	if (feedUrl) {
		void fetchState().catch(() => {});
		schedulePoll(1200);
	}

	window.addEventListener("beforeunload", clearPoll);
}

function initPocketCameraPanels() {
	const roots = Array.from(document.querySelectorAll("[data-pocket-camera-root]"));
	if (!roots.length) {
		return;
	}

	const withCacheBust = (url) => {
		if (typeof url !== "string" || url.trim() === "") {
			return "";
		}

		try {
			const resolved = new URL(url, window.location.href);
			resolved.searchParams.set("_t", `${Date.now()}`);
			return resolved.toString();
		} catch {
			return `${url}${url.includes("?") ? "&" : "?"}_t=${Date.now()}`;
		}
	};

	roots.forEach((root) => {
		if (!(root instanceof HTMLElement)) {
			return;
		}

		const frame = root.querySelector("[data-pocket-camera-frame]");
		const fallback = root.querySelector("[data-pocket-camera-fallback]");
		const overlay = root.querySelector("[data-pocket-camera-overlay]");
		const status = root.querySelector("[data-pocket-camera-status]");
		const badge = root.querySelector("[data-pocket-camera-badge]");
		const mode = root.querySelector("[data-pocket-camera-mode]");
		const presence = root.querySelector("[data-pocket-camera-presence]");
		const aiLine = root.querySelector("[data-pocket-camera-ai-line]");
		const vision = root.querySelector("[data-pocket-camera-vision]");
		const liveButton = root.querySelector("[data-pocket-camera-live]");
		const snapshotButton = root.querySelector("[data-pocket-camera-snapshot]");
		const openLink = root.querySelector("[data-pocket-camera-open]");

		if (!(frame instanceof HTMLImageElement)) {
			return;
		}

		const streamUrl = (root.dataset.pocketCameraStream || "").trim();
		const snapshotUrl = (root.dataset.pocketCameraSnapshot || "").trim();
		const aiFeedUrl = (root.dataset.pocketCameraAiFeed || "").trim();
		const autostart = root.dataset.pocketCameraAutostart === "1";

		const state = {
			mode: snapshotUrl ? "snapshot" : "live",
			pendingMode: "",
			liveTimeout: 0,
			snapshotLoopTimer: 0,
			aiPollTimer: 0,
			ai: normalizeCameraAiState(null),
		};
		let aiPollInFlight = false;

		const clearLiveTimeout = () => {
			if (state.liveTimeout) {
				window.clearTimeout(state.liveTimeout);
				state.liveTimeout = 0;
			}
		};

		const clearSnapshotLoop = () => {
			if (state.snapshotLoopTimer) {
				window.clearTimeout(state.snapshotLoopTimer);
				state.snapshotLoopTimer = 0;
			}
		};

		const clearAiPoll = () => {
			if (state.aiPollTimer) {
				window.clearTimeout(state.aiPollTimer);
				state.aiPollTimer = 0;
			}
		};

		const setText = (node, text) => {
			if (node instanceof HTMLElement) {
				node.textContent = text;
			}
		};

		const setFallbackVisible = (visible) => {
			if (!(fallback instanceof HTMLElement)) {
				return;
			}
			fallback.hidden = !visible;
			fallback.setAttribute("aria-hidden", visible ? "false" : "true");
		};

		const renderAiOverlay = () => {
			const detections = Array.isArray(state.ai.detections) ? state.ai.detections.slice(0, 4) : [];
			const dominant = detections[0] || null;
			const center = Array.isArray(dominant?.center) && dominant.center.length >= 2
				? dominant.center
				: [0.5, 0.46];
			const area = clampNumber(Number(dominant?.area) || 0, 0, 1);
			const presenceLevel = clampNumber(state.ai.objectCount / 4, 0, 1);
			const staleAi = state.ai.stale === true;

			root.style.setProperty("--camera-ai-focus-x", `${(center[0] * 100).toFixed(2)}%`);
			root.style.setProperty("--camera-ai-focus-y", `${(center[1] * 100).toFixed(2)}%`);
			root.style.setProperty("--camera-ai-spread", `${(28 + (area * 44)).toFixed(2)}%`);
			root.style.setProperty("--camera-ai-score", state.ai.dominantScore.toFixed(3));
			root.style.setProperty("--camera-ai-presence", presenceLevel.toFixed(3));
			root.dataset.pocketCameraAiFreshness = state.ai.freshness;

			if (vision instanceof HTMLElement) {
				if (staleAi) {
					vision.textContent = "Lecture en attente.";
				} else if (!detections.length) {
					vision.textContent = "Lecture en veille.";
				} else {
					const leadLabel = dominant && dominant.label ? dominant.label : "forme";
					const score = Math.round((dominant?.score || 0) * 100);
					const count = state.ai.objectCount > 1 ? ` · ${state.ai.objectCount}` : "";
					vision.textContent = `${state.ai.scene} · ${leadLabel} ${score}%${count}`;
				}
			}

			if (presence instanceof HTMLElement) {
				if (state.ai.objectCount > 0) {
					presence.textContent = `${state.ai.objectCount} forme${state.ai.objectCount > 1 ? "s" : ""}`;
				} else if (staleAi) {
					presence.textContent = "attente";
				}
			}

			if (!(overlay instanceof HTMLElement)) {
				return;
			}

			const fragment = document.createDocumentFragment();
			detections.forEach((detection) => {
				if (!Array.isArray(detection.bbox) || detection.bbox.length < 4) {
					return;
				}

				const [xMin, yMin, xMax, yMax] = detection.bbox;
				const mark = document.createElement("div");
				mark.className = "camera-negative-layer__detection";
				mark.style.setProperty("--camera-box-left", clampNumber(xMin, 0, 1).toFixed(4));
				mark.style.setProperty("--camera-box-top", clampNumber(yMin, 0, 1).toFixed(4));
				mark.style.setProperty("--camera-box-width", clampNumber(xMax - xMin, 0.02, 1).toFixed(4));
				mark.style.setProperty("--camera-box-height", clampNumber(yMax - yMin, 0.02, 1).toFixed(4));
				mark.style.setProperty("--camera-box-alpha", clampNumber(detection.score, 0, 1).toFixed(4));

				const label = document.createElement("span");
				label.className = "camera-negative-layer__detection-label";
				label.append(document.createTextNode(detection.label || "forme"));

				const score = document.createElement("strong");
				score.textContent = `${Math.round(clampNumber(detection.score, 0, 1) * 100)}%`;
				label.append(score);

				mark.append(label);
				fragment.append(mark);
			});

			overlay.replaceChildren(fragment);
		};

		const setAiState = (payload) => {
			state.ai = normalizeCameraAiState(payload);
			root.dataset.pocketCameraAiScene = state.ai.scene;
			root.dataset.pocketCameraAiLabel = state.ai.dominantLabel || "";
			root.style.setProperty("--camera-ai-attention", state.ai.attention.toFixed(3));
			root.style.setProperty("--camera-ai-movement", state.ai.movement.toFixed(3));
			root.style.setProperty("--camera-ai-density", state.ai.density.toFixed(3));
			if (aiLine instanceof HTMLElement) {
				aiLine.textContent = [state.ai.lead, state.ai.summary].filter(Boolean).join(" ");
			}
			renderAiOverlay();
		};

		const setPanelState = ({ statusText, badgeText, modeText, presenceText, ready = false }) => {
			setText(status, statusText);
			setText(badge, badgeText);
			setText(mode, modeText);
			setText(presence, presenceText);
			root.dataset.pocketCameraReady = ready ? "1" : "0";
			root.dataset.pocketCameraVisualState = state.pendingMode || state.mode;
		};

		const setFrameSource = (url) => {
			const resolved = withCacheBust(url);
			frame.src = resolved;
		};

		const fetchAiState = async () => {
			if (!aiFeedUrl) {
				return state.ai;
			}

			const response = await fetch(aiFeedUrl, {
				cache: "no-store",
				headers: { Accept: "application/json" },
			});
			if (!response.ok) {
				throw new Error(`camera ai feed ${response.status}`);
			}

			const payload = await response.json();
			setAiState(payload);
			return state.ai;
		};

		const scheduleAiPoll = (delay = 6200) => {
			clearAiPoll();
			if (!aiFeedUrl) {
				return;
			}

			state.aiPollTimer = window.setTimeout(async () => {
				if (document.hidden) {
					scheduleAiPoll(2800);
					return;
				}

				if (!aiPollInFlight) {
					aiPollInFlight = true;
					try {
						await fetchAiState();
					} catch {
						// Ignore polling failures and keep the last stable state.
					} finally {
						aiPollInFlight = false;
					}
				}

				scheduleAiPoll(6200);
			}, delay);
		};

		const loadSnapshot = (statusText = "Image fixe.") => {
			if (!snapshotUrl) {
				setPanelState({
					statusText: "Pas de snapshot.",
					badgeText: "offline",
					modeText: "indisponible",
					presenceText: "absent",
					ready: false,
				});
				setFallbackVisible(true);
				return;
			}

			clearLiveTimeout();
			clearSnapshotLoop();
			state.mode = "snapshot";
			state.pendingMode = "snapshot";
			setFallbackVisible(true);
			setPanelState({
				statusText,
				badgeText: "image",
				modeText: "image",
				presenceText: "lecture",
				ready: false,
			});
			setFrameSource(snapshotUrl);
		};

		const startSnapshotCadence = (statusText = "Cadence.") => {
			if (!snapshotUrl) {
				loadSnapshot("Live absent.");
				return;
			}

			clearLiveTimeout();
			clearSnapshotLoop();
			state.mode = "cadence";
			state.pendingMode = "cadence";
			setFallbackVisible(true);
			setPanelState({
				statusText,
				badgeText: "cadence",
				modeText: "images",
				presenceText: "mouvement",
				ready: false,
			});

			const tick = () => {
				setFrameSource(snapshotUrl);
				if (document.hidden) {
					state.snapshotLoopTimer = window.setTimeout(tick, 2200);
					return;
				}
				const cadenceMs = Math.max(720, 1480 - Math.round((state.ai.attention * 320) + (state.ai.movement * 220) + (state.ai.density * 140)));
				state.snapshotLoopTimer = window.setTimeout(tick, cadenceMs);
			};

			tick();
		};

		const loadLive = () => {
			if (!streamUrl) {
				loadSnapshot("Live absent.");
				return;
			}

			clearLiveTimeout();
			clearSnapshotLoop();
			state.mode = "live";
			state.pendingMode = "live";
			setFallbackVisible(true);
			setPanelState({
				statusText: "Ouverture.",
				badgeText: "live",
				modeText: "live",
				presenceText: "attente",
				ready: false,
			});
			setFrameSource(streamUrl);
			state.liveTimeout = window.setTimeout(() => {
				if (state.pendingMode !== "live") {
					return;
				}
				startSnapshotCadence("Cadence auto.");
			}, 4200);
		};

		frame.addEventListener("load", () => {
			clearLiveTimeout();
			setFallbackVisible(false);
			if (state.pendingMode === "live") {
				setPanelState({
					statusText: "Direct.",
					badgeText: "live",
					modeText: "live",
					presenceText: "actif",
					ready: true,
				});
				return;
			}

			if (state.pendingMode === "cadence") {
				setPanelState({
					statusText: "Cadence.",
					badgeText: "cadence",
					modeText: "images",
					presenceText: "mobile",
					ready: true,
				});
				return;
			}

			setPanelState({
				statusText: "Image fixe.",
				badgeText: "image",
				modeText: "image",
				presenceText: "stable",
				ready: true,
			});
		});

		frame.addEventListener("error", () => {
			clearLiveTimeout();
			setFallbackVisible(true);
			if (state.pendingMode === "live" && snapshotUrl) {
				startSnapshotCadence("Cadence auto.");
				return;
			}

			if (state.pendingMode === "cadence") {
				setPanelState({
					statusText: "Attente.",
					badgeText: "cadence",
					modeText: "attente",
					presenceText: "attente",
					ready: false,
				});
				return;
			}

			setPanelState({
				statusText: "Flux indisponible.",
				badgeText: "offline",
				modeText: "erreur",
				presenceText: "erreur",
				ready: false,
			});
		});

		if (liveButton instanceof HTMLButtonElement) {
			liveButton.addEventListener("click", () => {
				loadLive();
			});
		}

		if (snapshotButton instanceof HTMLButtonElement) {
			snapshotButton.addEventListener("click", () => {
				loadSnapshot("Image fixe.");
			});
		}

		if (openLink instanceof HTMLAnchorElement) {
			openLink.href = streamUrl || snapshotUrl || "#";
		}

		if (autostart && streamUrl) {
			loadSnapshot("Image.");
			window.setTimeout(() => {
				loadLive();
			}, 320);
		} else {
			loadSnapshot("Image.");
		}

		setAiState(null);
		if (aiFeedUrl) {
			void fetchAiState().catch(() => {});
			scheduleAiPoll(6200);
		}

		window.addEventListener("beforeunload", () => {
			clearLiveTimeout();
			clearSnapshotLoop();
			clearAiPoll();
		});
	});
}
