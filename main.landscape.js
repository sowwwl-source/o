function initLandscapeChoirs() {
	const roots = Array.from(document.querySelectorAll("[data-landscape-choir-root]"));
	if (!roots.length) {
		return;
	}

	const AudioContextClass = window.AudioContext || window.webkitAudioContext;
	const choirModes = {
		soft: {
			label: "chant doux",
			badge: "doux",
			idleCopy: "Souffle lent.",
			activeCopy: "Souffle ouvert.",
			droneType: "sine",
			harmonyType: "triangle",
			shimmerType: "sine",
			bellType: "sine",
			masterScale: 1.32,
			droneScale: 1.02,
			harmonyScale: 0.94,
			shimmerScale: 1.34,
			filterScale: 1.12,
			filterOffset: 120,
			lfoScale: 0.88,
			lfoDepthScale: 0.82,
			bellGainScale: 1.18,
			bellSweepScale: 1.14,
			bellDuration: 1.55,
		},
		ritual: {
			label: "chant rituel",
			badge: "rituel",
			idleCopy: "Veille dense.",
			activeCopy: "Rituel dense.",
			droneType: "sawtooth",
			harmonyType: "square",
			shimmerType: "triangle",
			bellType: "triangle",
			masterScale: 1.54,
			droneScale: 1.34,
			harmonyScale: 1.42,
			shimmerScale: 0.82,
			filterScale: 0.92,
			filterOffset: -40,
			lfoScale: 1.18,
			lfoDepthScale: 1.28,
			bellGainScale: 1.62,
			bellSweepScale: 0.98,
			bellDuration: 1.22,
		},
	};
	const formatPercent = (value) => `${Math.round(clampNumber(Number.isFinite(value) ? value : 0, 0, 1) * 100)}%`;
	const parseMetric = (metrics, key) => {
		if (!metrics || typeof metrics !== "object" || !(key in metrics)) {
			return 0;
		}

		const value = Number(metrics[key]);
		return Number.isFinite(value) ? value : 0;
	};
	const parseSeed = (root) => {
		const node = root.querySelector("[data-landscape-choir-seed]");
		if (!(node instanceof HTMLScriptElement)) {
			return { weather: {}, events: [] };
		}

		try {
			const parsed = JSON.parse(node.textContent || "{}");
			return parsed && typeof parsed === "object" ? parsed : { weather: {}, events: [] };
		} catch {
			return { weather: {}, events: [] };
		}
	};
	const eventTimeMs = (event) => {
		if (!event || typeof event !== "object") {
			return 0;
		}

		const rawValue = typeof event.timestamp === "string" && event.timestamp
			? event.timestamp
			: (typeof event.received_at === "string" ? event.received_at : "");
		if (!rawValue) {
			return 0;
		}

		const timestamp = Date.parse(rawValue);
		return Number.isFinite(timestamp) ? timestamp : 0;
	};
	const readCameraEvents = (events, cameraSlug) => {
		const slug = typeof cameraSlug === "string" ? cameraSlug.trim().toLowerCase() : "";
		const list = Array.isArray(events) ? events : [];
		if (!slug) {
			return list;
		}

		return list.filter((event) => {
			if (!event || typeof event !== "object") {
				return false;
			}

			const landSlug = typeof event.land_slug === "string" ? event.land_slug.trim().toLowerCase() : "";
			const source = typeof event.source === "string" ? event.source.trim().toLowerCase() : "";
			const camera = typeof event.camera === "string" ? event.camera.trim().toLowerCase() : "";

			return landSlug === slug || source === slug || camera === slug;
		});
	};
	const summarizeSnapshot = (snapshot) => {
		if (!snapshot.latestEvent) {
			return "veille basse";
		}

		if (snapshot.intensity >= 0.72) {
			return "surge chantant";
		}
		if (snapshot.intensity >= 0.46) {
			return "marche sensible";
		}
		if (snapshot.intensity >= 0.2) {
			return "veille vibrante";
		}

		return "souffle ténu";
	};
	const computeSnapshot = (events, weather = {}) => {
		const list = Array.isArray(events) ? events : [];
		const latestEvent = list[0] && typeof list[0] === "object" ? list[0] : null;
		const metrics = latestEvent && latestEvent.metrics && typeof latestEvent.metrics === "object"
			? latestEvent.metrics
			: {};
		const largestArea = Math.max(0, parseMetric(metrics, "largest_area"));
		const contourCount = Math.max(0, parseMetric(metrics, "contour_count"));
		const frameLuma = clampNumber(parseMetric(metrics, "frame_luma"), 0, 1);
		const safeWeather = weather && typeof weather === "object" ? weather : {};
		const weatherStale = safeWeather.stale === true || safeWeather.freshness === "stale";
		const weatherAgeSeconds = Number(safeWeather.age_seconds ?? safeWeather.ageSeconds);
		const ageSeconds = Number.isFinite(weatherAgeSeconds) && weatherAgeSeconds >= 0
			? weatherAgeSeconds
			: null;
		const weatherStaleAfter = Number(safeWeather.stale_after_seconds ?? safeWeather.staleAfterSeconds);
		const staleAfterSeconds = Number.isFinite(weatherStaleAfter) && weatherStaleAfter > 0
			? weatherStaleAfter
			: 90;
		const weatherEnergy = clampNumber(Number(safeWeather.energy) || 0, 0, 1);
		let area = clampNumber(Math.sqrt(largestArea / 48000), 0, 1);
		let contours = clampNumber(contourCount / 6, 0, 1);
		let density = clampNumber((list.length - 1) / 5, 0, 1);
		const ageMs = latestEvent ? Math.max(0, Date.now() - eventTimeMs(latestEvent)) : Number.POSITIVE_INFINITY;
		let recency = Number.isFinite(ageMs) ? clampNumber(1 - (ageMs / 120000), 0, 1) : 0;
		let intensity = clampNumber((area * 0.38) + (contours * 0.18) + (frameLuma * 0.18) + (density * 0.1) + (recency * 0.08) + (weatherEnergy * 0.08), 0, 1);
		if (weatherStale) {
			const staleOverrun = ageSeconds !== null ? Math.max(0, ageSeconds - staleAfterSeconds) : staleAfterSeconds;
			const staleFade = clampNumber(1 - (staleOverrun / Math.max(staleAfterSeconds * 3, 45)), 0.14, 0.48);
			area *= staleFade;
			contours *= 0.44;
			density *= 0.28;
			recency = 0;
			intensity = Math.min(intensity * (0.22 + (staleFade * 0.18)), 0.18);
		}
		let lightLift = clampNumber((frameLuma * 0.74) + (weatherEnergy * 0.18) + (recency * 0.08), 0, 1);
		let cloudCover = clampNumber((contours * 0.48) + (density * 0.22) + ((1 - frameLuma) * 0.22) + (weatherEnergy * 0.08), 0, 1);
		let skyDrift = clampNumber((cloudCover * 0.42) + (density * 0.22) + (recency * 0.12) + (Math.abs(frameLuma - 0.5) * 0.24), 0, 1);
		if (weatherStale) {
			lightLift *= 0.62;
			cloudCover *= 0.52;
			skyDrift *= 0.38;
		}
		const scale = [130.81, 146.83, 164.81, 174.61, 196.0, 220.0, 246.94, 293.66];
		const scaleIndex = Math.round(frameLuma * (scale.length - 1));
		const baseFreq = scale[scaleIndex] * (1 + ((area - 0.5) * 0.14) + ((lightLift - 0.5) * 0.06));
		const harmonyRatio = contours >= 0.66 ? 1.5 : contours >= 0.33 ? 1.333 : 1.25;
		const harmonyFreq = baseFreq * (harmonyRatio + (cloudCover * 0.06));
		const shimmerFreq = baseFreq * (2.08 + (density * 0.86) + (lightLift * 0.58) + (skyDrift * 0.26));
		const filterCutoff = 380 + (lightLift * 2800) + (intensity * 920) + (cloudCover * 240);
		let latestMessage = latestEvent && typeof latestEvent.message === "string" && latestEvent.message
			? latestEvent.message
			: "Le prochain passage du Pi 3 donnera une voix au paysage.";
		const latestLabel = latestEvent && typeof latestEvent.timestamp === "string" && latestEvent.timestamp
			? latestEvent.timestamp
			: "veille";
		let statusLabel = summarizeSnapshot({
			latestEvent,
			intensity,
		});
		if (weatherStale) {
			statusLabel = "trace froide";
			if (typeof safeWeather.detail === "string" && safeWeather.detail) {
				latestMessage = safeWeather.detail;
			}
		}

		return {
			events: list,
			latestEvent,
			weather: safeWeather,
			intensity,
			area,
			contours,
			luma: frameLuma,
			density,
			recency,
			weatherEnergy,
			lightLift,
			cloudCover,
			skyDrift,
			baseFreq,
			harmonyFreq,
			shimmerFreq,
			filterCutoff,
			statusLabel,
			latestMessage,
			latestLabel,
		};
	};

	roots.forEach((root) => {
		if (!(root instanceof HTMLElement)) {
			return;
		}

		const statusNode = root.querySelector("[data-landscape-choir-status]");
		const copyNode = root.querySelector("[data-landscape-choir-copy]");
		const badgeNode = root.querySelector("[data-landscape-choir-badge]");
		const lumaNode = root.querySelector("[data-landscape-choir-luma]");
		const contourNode = root.querySelector("[data-landscape-choir-contours]");
		const intensityNode = root.querySelector("[data-landscape-choir-intensity]");
		const lumaBar = root.querySelector("[data-landscape-choir-luma-bar]");
		const contourBar = root.querySelector("[data-landscape-choir-contours-bar]");
		const intensityBar = root.querySelector("[data-landscape-choir-intensity-bar]");
		const toggleButton = root.querySelector("[data-landscape-choir-toggle]");
		const volumeInput = root.querySelector("[data-landscape-choir-volume]");
		const volumeLabel = root.querySelector("[data-landscape-choir-volume-label]");
		const lastNode = root.querySelector("[data-landscape-choir-last]");
		const modeButtons = Array.from(root.querySelectorAll("[data-landscape-choir-mode]")).filter((node) => node instanceof HTMLButtonElement);
		const cameraSlug = (root.dataset.landscapeChoirCamera || "").trim();
		const cameraLabel = (root.dataset.landscapeChoirLabel || cameraSlug || "pocket").trim();
		const feedUrl = (root.dataset.landscapeChoirFeed || "").trim();
		const aiFeedUrl = (root.dataset.landscapeChoirAiFeed || "").trim();
		const seed = parseSeed(root);
		const state = {
			running: false,
			pollTimer: 0,
			volume: 0.72,
			audioContext: null,
			nodes: null,
			rawSnapshot: null,
			snapshot: null,
			lastEventId: "",
			mode: "soft",
			ai: normalizeCameraAiState(null),
			aiPollTimer: 0,
		};

		const currentModeProfile = () => choirModes[state.mode] || choirModes.soft;

		const setText = (node, text) => {
			if (node instanceof HTMLElement) {
				node.textContent = text;
			}
		};

		const setMeter = (labelNode, barNode, value) => {
			const normalized = clampNumber(Number.isFinite(value) ? value : 0, 0, 1);
			setText(labelNode, formatPercent(normalized));
			if (barNode instanceof HTMLElement) {
				barNode.style.setProperty("--landscape-choir-fill", `${Math.round(normalized * 100)}%`);
			}
		};

		const setToggleState = () => {
			if (!(toggleButton instanceof HTMLButtonElement)) {
				return;
			}

			toggleButton.setAttribute("aria-pressed", state.running ? "true" : "false");
			toggleButton.textContent = state.running ? "Couper" : "Écouter";
		};

		const syncModeButtons = () => {
			root.dataset.landscapeChoirMode = state.mode;
			modeButtons.forEach((button) => {
				const nextMode = (button.dataset.landscapeChoirMode || "").trim();
				button.setAttribute("aria-pressed", nextMode === state.mode ? "true" : "false");
			});
		};

		const mergeSnapshotWithAi = (snapshot, aiPayload) => {
			const safeSnapshot = snapshot && typeof snapshot === "object"
				? { ...snapshot }
				: computeSnapshot([], {});
			const ai = normalizeCameraAiState(aiPayload);
			const objectEnergy = clampNumber((ai.objectCount / 6), 0, 1);
			const personBias = clampNumber(ai.personCount * 0.22, 0, 1);
			const vehicleBias = clampNumber(ai.vehicleCount * 0.18, 0, 1);
			const animalBias = clampNumber(ai.animalCount * 0.22, 0, 1);
			const aiEnergy = clampNumber((ai.attention * 0.44) + (ai.movement * 0.32) + (ai.density * 0.24), 0, 1);

			return {
				...safeSnapshot,
				intensity: clampNumber((safeSnapshot.intensity * 0.8) + (aiEnergy * 0.2) + (personBias * 0.06), 0, 1),
				contours: clampNumber((safeSnapshot.contours * 0.84) + (ai.movement * 0.08) + (ai.density * 0.14), 0, 1),
				density: clampNumber((safeSnapshot.density * 0.76) + (ai.density * 0.14) + (objectEnergy * 0.1), 0, 1),
				weatherEnergy: clampNumber((safeSnapshot.weatherEnergy * 0.92) + (aiEnergy * 0.08), 0, 1),
				lightLift: clampNumber((safeSnapshot.lightLift * 0.92) + (ai.attention * 0.05) + (personBias * 0.03), 0, 1),
				cloudCover: clampNumber((safeSnapshot.cloudCover * 0.88) + (ai.density * 0.08) + (vehicleBias * 0.06), 0, 1),
				skyDrift: clampNumber((safeSnapshot.skyDrift * 0.82) + (ai.movement * 0.12) + (ai.attention * 0.06), 0, 1),
				baseFreq: safeSnapshot.baseFreq * (1 + (personBias * 0.04) - (vehicleBias * 0.03)),
				harmonyFreq: safeSnapshot.harmonyFreq * (1 + (animalBias * 0.06) + (personBias * 0.03)),
				shimmerFreq: safeSnapshot.shimmerFreq * (1 + (ai.density * 0.1) + (animalBias * 0.08)),
				filterCutoff: Math.max(220, (safeSnapshot.filterCutoff * (0.9 + (ai.attention * 0.18))) - (vehicleBias * 180)),
				ai,
			};
		};

		const renderSnapshot = (snapshot) => {
			const safeSnapshot = snapshot && typeof snapshot === "object"
				? snapshot
				: computeSnapshot([], {});
			const modeProfile = currentModeProfile();
			const ai = safeSnapshot.ai && typeof safeSnapshot.ai === "object"
				? normalizeCameraAiState(safeSnapshot.ai)
				: normalizeCameraAiState(null);
			const weather = safeSnapshot.weather && typeof safeSnapshot.weather === "object"
				? safeSnapshot.weather
				: {};
			const weatherStale = weather.stale === true || weather.freshness === "stale";
			const aiStale = ai.stale === true || ai.freshness === "stale";
			const toneBadge = typeof weather.badge === "string" && weather.badge
				? weather.badge
				: (safeSnapshot.latestEvent ? safeSnapshot.statusLabel : "veille");
			const aiBadge = ai.objectCount > 0 && ai.dominantLabel
				? `ia ${ai.dominantLabel}`
				: (aiStale ? "ia attente" : "")
			;
			const lead = ai.objectCount > 0
				? [toneBadge, ai.dominantLabel || ai.scene, `${Math.round(ai.attention * 100)}%`].filter(Boolean).join(" · ")
				: (aiStale
					? [toneBadge, "attente IA"].filter(Boolean).join(" · ")
					: toneBadge);
			const weatherDetail = typeof weather.detail === "string" && weather.detail
				? weather.detail
				: "";
			const detail = [
				state.running ? modeProfile.activeCopy : modeProfile.idleCopy,
				weatherDetail,
				ai.summary,
			].filter(Boolean).join(" ");
			const latestLine = safeSnapshot.latestEvent
				? `${safeSnapshot.latestLabel} · ${weatherStale ? "Trace refroidie." : safeSnapshot.latestMessage}`
				: (weatherStale ? "Traces refroidies." : "Aucune trace récente.");

			root.dataset.landscapeChoirState = state.running ? "singing" : "idle";
			root.dataset.landscapeChoirFreshness = typeof weather.freshness === "string" && weather.freshness
				? weather.freshness
				: (safeSnapshot.latestEvent ? "fresh" : "idle");
			root.dataset.landscapeChoirAiFreshness = ai.freshness;
			root.style.setProperty("--landscape-choir-energy", safeSnapshot.intensity.toFixed(3));
			root.style.setProperty("--landscape-choir-luma", safeSnapshot.luma.toFixed(3));
			root.style.setProperty("--camera-ai-attention", ai.attention.toFixed(3));
			root.style.setProperty("--camera-ai-movement", ai.movement.toFixed(3));
			setText(statusNode, state.running ? [modeProfile.badge, lead].filter(Boolean).join(" · ") : lead);
			setText(copyNode, detail);
			setText(badgeNode, state.running
				? `${modeProfile.badge} · ${aiBadge || toneBadge}`
				: (aiBadge ? `${aiBadge} · ${toneBadge}` : toneBadge));
			setText(lastNode, latestLine);
			setMeter(lumaNode, lumaBar, safeSnapshot.luma);
			setMeter(contourNode, contourBar, safeSnapshot.contours);
			setMeter(intensityNode, intensityBar, safeSnapshot.intensity);
		};

		const buildAudioGraph = () => {
			if (!AudioContextClass) {
				return null;
			}

			const context = new AudioContextClass();
			const masterGain = context.createGain();
			masterGain.gain.value = 0.0001;
			masterGain.connect(context.destination);

			const filter = context.createBiquadFilter();
			filter.type = "lowpass";
			filter.frequency.value = 900;
			filter.Q.value = 0.72;
			filter.connect(masterGain);

			const drone = context.createOscillator();
			drone.type = "sine";
			const droneGain = context.createGain();
			droneGain.gain.value = 0.0001;
			drone.connect(droneGain);
			droneGain.connect(filter);

			const harmony = context.createOscillator();
			harmony.type = "triangle";
			const harmonyGain = context.createGain();
			harmonyGain.gain.value = 0.0001;
			harmony.connect(harmonyGain);
			harmonyGain.connect(filter);

			const shimmer = context.createOscillator();
			shimmer.type = "sine";
			const shimmerGain = context.createGain();
			shimmerGain.gain.value = 0.0001;
			shimmer.connect(shimmerGain);
			shimmerGain.connect(filter);

			const lfo = context.createOscillator();
			lfo.type = "sine";
			lfo.frequency.value = 0.16;
			const lfoDepth = context.createGain();
			lfoDepth.gain.value = 0.02;
			lfo.connect(lfoDepth);
			lfoDepth.connect(droneGain.gain);
			lfoDepth.connect(harmonyGain.gain);

			drone.start();
			harmony.start();
			shimmer.start();
			lfo.start();

			return {
				context,
				masterGain,
				filter,
				drone,
				droneGain,
				harmony,
				harmonyGain,
				shimmer,
				shimmerGain,
				lfo,
				lfoDepth,
			};
		};

		const ringBell = (snapshot, accent = 1) => {
			if (!state.nodes || !state.audioContext || !state.running) {
				return;
			}

			const context = state.audioContext;
			const bell = context.createOscillator();
			const bellGain = context.createGain();
			const now = context.currentTime;
			const modeProfile = currentModeProfile();
			const ai = snapshot.ai && typeof snapshot.ai === "object" ? normalizeCameraAiState(snapshot.ai) : normalizeCameraAiState(null);
			const lightLift = clampNumber(snapshot.lightLift ?? snapshot.luma, 0, 1);
			const cloudCover = clampNumber(snapshot.cloudCover ?? snapshot.contours, 0, 1);
			const level = clampNumber(
				(0.024 + (snapshot.intensity * 0.058) + (lightLift * 0.018) + (cloudCover * 0.012))
				* state.volume
				* accent
				* modeProfile.bellGainScale
				* (1 + (ai.attention * 0.22)),
				0.0002,
				0.16,
			);

			bell.type = modeProfile.bellType;
			bell.frequency.setValueAtTime(snapshot.harmonyFreq * (1 + (lightLift * 0.34) + (cloudCover * 0.08)), now);
			bell.frequency.exponentialRampToValueAtTime(
				Math.max(100, snapshot.shimmerFreq * modeProfile.bellSweepScale * (1 + (lightLift * 0.14) + (cloudCover * 0.08))),
				now + 0.9,
			);
			bellGain.gain.setValueAtTime(0.0001, now);
			bellGain.gain.exponentialRampToValueAtTime(Math.max(0.0002, level), now + 0.04);
			bellGain.gain.exponentialRampToValueAtTime(0.0001, now + modeProfile.bellDuration);

			bell.connect(bellGain);
			bellGain.connect(state.nodes.filter);
			bell.start(now);
			bell.stop(now + modeProfile.bellDuration + 0.1);
		};

		const applySnapshotToAudio = (snapshot, { ring = false } = {}) => {
			if (!state.nodes || !state.audioContext || !snapshot) {
				return;
			}

			const now = state.audioContext.currentTime;
			const modeProfile = currentModeProfile();
			const ai = snapshot.ai && typeof snapshot.ai === "object" ? normalizeCameraAiState(snapshot.ai) : normalizeCameraAiState(null);
			const weatherEnergy = clampNumber(snapshot.weatherEnergy ?? snapshot.weather?.energy ?? snapshot.intensity, 0, 1);
			const lightLift = clampNumber(snapshot.lightLift ?? snapshot.luma, 0, 1);
			const cloudCover = clampNumber(snapshot.cloudCover ?? snapshot.contours, 0, 1);
			const skyDrift = clampNumber(snapshot.skyDrift ?? snapshot.density, 0, 1);
			const masterTarget = clampNumber(
				(0.034 + (snapshot.intensity * 0.148) + (weatherEnergy * 0.026) + (lightLift * 0.018))
				* state.volume
				* modeProfile.masterScale
				* (1 + (ai.attention * 0.18) + (cloudCover * 0.12)),
				0.0001,
				0.24,
			);
			const droneTarget = clampNumber((0.04 + (snapshot.intensity * 0.19) + (cloudCover * 0.028)) * modeProfile.droneScale, 0.0001, 0.34);
			const harmonyTarget = clampNumber((0.024 + (snapshot.contours * 0.108) + (cloudCover * 0.024) + (lightLift * 0.018)) * modeProfile.harmonyScale, 0.0001, 0.24);
			const shimmerTarget = clampNumber((0.012 + (lightLift * 0.076) + (skyDrift * 0.028) + (ai.animalCount * 0.01)) * modeProfile.shimmerScale, 0.0001, 0.19);

			state.nodes.drone.type = modeProfile.droneType;
			state.nodes.harmony.type = modeProfile.harmonyType;
			state.nodes.shimmer.type = modeProfile.shimmerType;

			state.nodes.masterGain.gain.cancelScheduledValues(now);
			state.nodes.masterGain.gain.setTargetAtTime(masterTarget, now, 0.42);
			state.nodes.filter.frequency.cancelScheduledValues(now);
			state.nodes.filter.frequency.setTargetAtTime(
				clampNumber((snapshot.filterCutoff * modeProfile.filterScale) + modeProfile.filterOffset + (lightLift * 240) + (cloudCover * 90), 220, 4200),
				now,
				0.46,
			);
			state.nodes.drone.frequency.cancelScheduledValues(now);
			state.nodes.drone.frequency.setTargetAtTime(snapshot.baseFreq, now, 0.52);
			state.nodes.harmony.frequency.cancelScheduledValues(now);
			state.nodes.harmony.frequency.setTargetAtTime(snapshot.harmonyFreq, now, 0.6);
			state.nodes.shimmer.frequency.cancelScheduledValues(now);
			state.nodes.shimmer.frequency.setTargetAtTime(snapshot.shimmerFreq, now, 0.58);
			state.nodes.droneGain.gain.cancelScheduledValues(now);
			state.nodes.droneGain.gain.setTargetAtTime(droneTarget, now, 0.5);
			state.nodes.harmonyGain.gain.cancelScheduledValues(now);
			state.nodes.harmonyGain.gain.setTargetAtTime(harmonyTarget, now, 0.56);
			state.nodes.shimmerGain.gain.cancelScheduledValues(now);
			state.nodes.shimmerGain.gain.setTargetAtTime(shimmerTarget, now, 0.62);
			state.nodes.lfo.frequency.cancelScheduledValues(now);
			state.nodes.lfo.frequency.setTargetAtTime(
				clampNumber((0.09 + (snapshot.intensity * 0.6) + (snapshot.density * 0.18) + (skyDrift * 0.26) + (lightLift * 0.12)) * modeProfile.lfoScale, 0.06, 1.6),
				now,
				0.7,
			);
			state.nodes.lfoDepth.gain.cancelScheduledValues(now);
			state.nodes.lfoDepth.gain.setTargetAtTime(
				clampNumber((0.014 + (snapshot.intensity * 0.044) + (cloudCover * 0.014) + (Math.abs(lightLift - 0.5) * 0.016)) * modeProfile.lfoDepthScale, 0.01, 0.12),
				now,
				0.6,
			);

			if (ring) {
				ringBell(snapshot, 1);
			}
		};

		const stopPolling = () => {
			if (state.pollTimer) {
				window.clearInterval(state.pollTimer);
				state.pollTimer = 0;
			}
		};

		const stopAiPolling = () => {
			if (state.aiPollTimer) {
				window.clearInterval(state.aiPollTimer);
				state.aiPollTimer = 0;
			}
		};

		const refreshFromCurrentState = () => {
			state.snapshot = mergeSnapshotWithAi(state.rawSnapshot, state.ai);
			renderSnapshot(state.snapshot);
			if (state.running && state.snapshot) {
				applySnapshotToAudio(state.snapshot);
			}
		};

		const fetchRecent = async ({ ringOnFresh = false } = {}) => {
			if (!feedUrl) {
				return state.snapshot;
			}

			const response = await fetch(feedUrl, {
				cache: "no-store",
				headers: { Accept: "application/json" },
			});
			if (!response.ok) {
				throw new Error(`landscape choir feed ${response.status}`);
			}

			const payload = await response.json();
			const events = readCameraEvents(payload?.events, cameraSlug);
			const snapshot = computeSnapshot(events, payload?.weather);
			const nextEventId = snapshot.latestEvent && typeof snapshot.latestEvent.id === "string"
				? snapshot.latestEvent.id
				: "";
			const isFreshEvent = nextEventId !== "" && nextEventId !== state.lastEventId;

			state.rawSnapshot = snapshot;
			state.snapshot = mergeSnapshotWithAi(snapshot, state.ai);
			if (nextEventId !== "") {
				state.lastEventId = nextEventId;
			}

			renderSnapshot(state.snapshot);
			if (state.running) {
				applySnapshotToAudio(state.snapshot, { ring: ringOnFresh && isFreshEvent });
			}

			return state.snapshot;
		};

		const fetchAi = async () => {
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

			state.ai = normalizeCameraAiState(await response.json());
			refreshFromCurrentState();
			return state.ai;
		};

		const ensureAudio = async () => {
			if (!AudioContextClass) {
				return false;
			}

			if (!state.nodes) {
				state.nodes = buildAudioGraph();
				state.audioContext = state.nodes?.context || null;
			}

			if (!state.audioContext) {
				return false;
			}

			if (state.audioContext.state === "suspended") {
				await state.audioContext.resume().catch(() => {});
			}

			return true;
		};

		const startChoir = async () => {
			const audioReady = await ensureAudio();
			if (!audioReady) {
				setText(statusNode, "Le navigateur ne peut pas ouvrir le chant Web Audio ici.");
				setText(copyNode, "Essaie depuis Safari, Chrome ou un autre navigateur qui laisse le geste ouvrir la sortie audio.");
				if (toggleButton instanceof HTMLButtonElement) {
					toggleButton.disabled = true;
				}
				return;
			}

			state.running = true;
			setToggleState();
			root.dataset.landscapeChoirState = "singing";

			if (state.snapshot) {
				applySnapshotToAudio(state.snapshot, { ring: true });
				renderSnapshot(state.snapshot);
			}

			stopPolling();
			await fetchRecent({ ringOnFresh: false }).catch(() => {});
			state.pollTimer = window.setInterval(() => {
				if (document.hidden) {
					return;
				}
				void fetchRecent({ ringOnFresh: true }).catch(() => {});
			}, 6400);
		};

		const stopChoir = () => {
			state.running = false;
			setToggleState();
			stopPolling();
			root.dataset.landscapeChoirState = "idle";

			if (state.nodes && state.audioContext) {
				const now = state.audioContext.currentTime;
				state.nodes.masterGain.gain.cancelScheduledValues(now);
				state.nodes.masterGain.gain.setTargetAtTime(0.0001, now, 0.28);
			}

			renderSnapshot(state.snapshot);
			if (state.audioContext && typeof state.audioContext.suspend === "function") {
				window.setTimeout(() => {
					if (!state.running) {
						state.audioContext.suspend().catch(() => {});
					}
				}, 380);
			}
		};

		if (toggleButton instanceof HTMLButtonElement) {
			toggleButton.addEventListener("click", () => {
				if (state.running) {
					stopChoir();
					return;
				}

				void startChoir();
			});
		}

		if (volumeInput instanceof HTMLInputElement) {
			const syncVolume = () => {
				const nextVolume = clampNumber(Number(volumeInput.value) / 100, 0, 1);
				state.volume = nextVolume;
				setText(volumeLabel, `${Math.round(nextVolume * 100)}%`);
				if (state.running && state.snapshot) {
					applySnapshotToAudio(state.snapshot);
				}
			};

			volumeInput.addEventListener("input", syncVolume);
			syncVolume();
		}

		modeButtons.forEach((button) => {
			button.addEventListener("click", () => {
				const nextMode = (button.dataset.landscapeChoirMode || "").trim();
				if (!(nextMode in choirModes) || nextMode === state.mode) {
					return;
				}

				state.mode = nextMode;
				syncModeButtons();
				renderSnapshot(state.snapshot);
				if (state.running && state.snapshot) {
					applySnapshotToAudio(state.snapshot, { ring: true });
				}
			});
		});

		const seedEvents = readCameraEvents(seed?.events, cameraSlug);
		state.rawSnapshot = computeSnapshot(seedEvents, seed?.weather);
		state.snapshot = mergeSnapshotWithAi(state.rawSnapshot, state.ai);
		state.lastEventId = state.snapshot.latestEvent && typeof state.snapshot.latestEvent.id === "string"
			? state.snapshot.latestEvent.id
			: "";
		setToggleState();
		syncModeButtons();
		renderSnapshot(state.snapshot);
		void fetchRecent({ ringOnFresh: false }).catch(() => {});
		if (aiFeedUrl) {
			void fetchAi().catch(() => {});
			state.aiPollTimer = window.setInterval(() => {
				if (document.hidden) {
					return;
				}
				void fetchAi().catch(() => {});
			}, 6200);
		}

		document.addEventListener("visibilitychange", () => {
			if (document.hidden) {
				return;
			}

			void fetchRecent({ ringOnFresh: false }).catch(() => {});
			if (aiFeedUrl) {
				void fetchAi().catch(() => {});
			}
		});

		window.addEventListener("beforeunload", () => {
			stopPolling();
			stopAiPolling();
			if (state.audioContext && typeof state.audioContext.close === "function") {
				state.audioContext.close().catch(() => {});
			}
		});
	});
}

