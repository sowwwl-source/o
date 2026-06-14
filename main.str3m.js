function bindStr3mIntegratedPlayer(root) {
	if (!(root instanceof HTMLElement) || root.dataset.str3mPlayerBound === "1") {
		return;
	}
	root.dataset.str3mPlayerBound = "1";

	const audio = root.querySelector("[data-str3m-player-audio]");
	if (!(audio instanceof HTMLAudioElement)) {
		return;
	}

	const hasSource = root.dataset.str3mPlayerHasSource === "1";
	const toggleButton = root.querySelector("[data-str3m-player-toggle]");
	const backButton = root.querySelector("[data-str3m-player-back]");
	const forwardButton = root.querySelector("[data-str3m-player-forward]");
	const progressInput = root.querySelector("[data-str3m-player-progress]");
	const currentOutput = root.querySelector("[data-str3m-player-current]");
	const durationOutput = root.querySelector("[data-str3m-player-duration]");
	const statusOutput = root.querySelector("[data-str3m-player-status]");
	const rateOutput = root.querySelector("[data-str3m-player-rate-output]");
	const rateStateOutput = root.querySelector("[data-str3m-player-rate-state]");
	const eqStateOutput = root.querySelector("[data-str3m-player-eq-state]");
	const eqSummaryOutput = root.querySelector("[data-str3m-player-summary]");
	const engineOutput = root.querySelector("[data-str3m-player-engine]");
	const outputModeOutput = root.querySelector("[data-str3m-player-output]");
	const sourceStateOutput = root.querySelector("[data-str3m-player-source-state]");
	const sourceOutput = root.querySelector("[data-str3m-player-source]");
	const sourceOpenLink = root.querySelector("[data-str3m-player-open]");
	const retryButton = root.querySelector("[data-str3m-player-retry]");
	const raNoteOutput = root.querySelector("[data-str3m-player-ra-note]");
	const preservePitchInput = root.querySelector("[data-str3m-player-preserve-pitch]");
	const resetButton = root.querySelector("[data-str3m-player-reset]");
	const rateStepButtons = Array.from(root.querySelectorAll("[data-str3m-player-rate-step]"));
	const listeningPresetButtons = Array.from(root.querySelectorAll("[data-str3m-player-listening-preset]"));
	const bassInput = root.querySelector("[data-str3m-player-bass]");
	const midInput = root.querySelector("[data-str3m-player-mid]");
	const trebleInput = root.querySelector("[data-str3m-player-treble]");
	const gainInput = root.querySelector("[data-str3m-player-gain]");
	const bassValue = root.querySelector("[data-str3m-player-bass-value]");
	const midValue = root.querySelector("[data-str3m-player-mid-value]");
	const trebleValue = root.querySelector("[data-str3m-player-treble-value]");
	const gainValue = root.querySelector("[data-str3m-player-gain-value]");
	const title = root.dataset.str3mPlayerTitle || "str3m quotidien";
	const sourceUrl = root.dataset.str3mPlayerSourceUrl || audio.currentSrc || audio.querySelector("source")?.getAttribute("src") || "";
	const initialAriaHidden = audio.getAttribute("aria-hidden");
	const initiallyHadControls = audio.hasAttribute("controls");
	const storageKey = "o:str3m-player:v1";
	const prefersReducedMotion = window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches === true;
	const islandAudioGrid = root.closest(".island-reader-grid--audio");
	const visualHosts = [root];
	if (islandAudioGrid instanceof HTMLElement) {
		visualHosts.push(islandAudioGrid);
	}

	const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
	const formatTime = (value) => {
		if (!Number.isFinite(value) || value < 0) {
			return "00:00";
		}

		const totalSeconds = Math.floor(value);
		const minutes = Math.floor(totalSeconds / 60);
		const seconds = totalSeconds % 60;
		return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
	};

	const readSettings = () => {
		try {
			const raw = window.localStorage.getItem(storageKey);
			if (!raw) {
				return null;
			}
			return JSON.parse(raw);
		} catch (_error) {
			return null;
		}
	};

	const storedSettings = readSettings();
	const buildSettingsFromPreset = (preset) => ({
		rate: preset?.rate ?? 1,
		preservePitch: preset?.preservePitch ?? true,
		bass: preset?.bass ?? 0,
		mid: preset?.mid ?? 0,
		treble: preset?.treble ?? 0,
		gain: preset?.gain ?? 100,
		listeningProfile: "auto",
	});
	const applyPresetDecor = (preset) => {
		root.dataset.str3mPlayerRaPreset = preset?.key || "";
		root.dataset.str3mPlayerWorldPreset = preset?.worldKey || "";
		root.dataset.str3mPlayerWorldTone = preset?.tone || "";
		if (raNoteOutput instanceof HTMLElement && preset?.note) {
			raNoteOutput.textContent = preset.note;
		}
	};
	let currentSpatialPreset = str3mPlayerPresetFromSpatialState(readActiveIoRaSession(), readActiveIoWorldInstrumentSession());
	let currentDefaultSettings = buildSettingsFromPreset(currentSpatialPreset);
	const listeningProfiles = {
		auto: {
			label: "auto",
			status: "profil auto",
			note: "suit le preset spatial actif",
		},
		velvet: {
			label: "velours",
			status: "profil velours",
			note: "bas rond, aigus adoucis",
			rate: 0.98,
			preservePitch: true,
			bass: 1.8,
			mid: 0.3,
			treble: -0.8,
			gain: 98,
		},
		voice: {
			label: "voix",
			status: "profil voix",
			note: "presence et paroles devant",
			rate: 1,
			preservePitch: true,
			bass: -0.8,
			mid: 2.4,
			treble: 1.1,
			gain: 101,
		},
		wide: {
			label: "large",
			status: "profil large",
			note: "air, detail et scene ouverte",
			rate: 1.02,
			preservePitch: true,
			bass: 0.8,
			mid: 0.1,
			treble: 1.7,
			gain: 102,
		},
		night: {
			label: "nuit",
			status: "profil nuit",
			note: "gain retenu, ecoute douce",
			rate: 0.96,
			preservePitch: true,
			bass: -1,
			mid: -0.3,
			treble: -1.4,
			gain: 86,
		},
	};
	const normalizeListeningProfile = (key) => Object.prototype.hasOwnProperty.call(listeningProfiles, key) ? key : "custom";
	const storedListeningProfile = typeof storedSettings?.listeningProfile === "string"
		? normalizeListeningProfile(storedSettings.listeningProfile)
		: "";
	let userCustomizedSettings = Boolean(storedSettings) && storedListeningProfile !== "auto";

	const setEngineState = (label) => {
		if (engineOutput instanceof HTMLElement) {
			engineOutput.textContent = label;
		}
	};

	const setOutputMode = (label) => {
		if (outputModeOutput instanceof HTMLElement) {
			outputModeOutput.textContent = label;
		}
	};

	const setSourceState = (label) => {
		if (sourceStateOutput instanceof HTMLElement) {
			sourceStateOutput.textContent = label;
		}
	};

	const syncSourceAccess = () => {
		if (!(sourceOpenLink instanceof HTMLAnchorElement)) {
			return;
		}

		if (!hasSource || !sourceUrl) {
			sourceOpenLink.hidden = true;
			sourceOpenLink.setAttribute("aria-hidden", "true");
			sourceOpenLink.removeAttribute("href");
			return;
		}

		sourceOpenLink.hidden = false;
		sourceOpenLink.removeAttribute("aria-hidden");
		sourceOpenLink.href = sourceUrl;
	};

	const enableNativeAudioFallback = (statusCopy = "lecture native") => {
		stopScopeRender();
		root.dataset.str3mPlayerFallback = "1";
		audio.controls = true;
		audio.setAttribute("controls", "controls");
		audio.removeAttribute("aria-hidden");
		audio.classList.add("is-fallback-controls");
		if (eqStateOutput instanceof HTMLElement) {
			eqStateOutput.textContent = "natif";
		}
		setEngineState("natif");
		setOutputMode("native secours");
		if (statusCopy) {
			setStatus(statusCopy);
		}
	};

	const disableNativeAudioFallback = () => {
		root.dataset.str3mPlayerFallback = "0";
		audio.classList.remove("is-fallback-controls");
		if (!initiallyHadControls) {
			audio.controls = false;
			audio.removeAttribute("controls");
		}
		if (initialAriaHidden === null) {
			audio.removeAttribute("aria-hidden");
		} else {
			audio.setAttribute("aria-hidden", initialAriaHidden);
		}
	};

	const settings = {
		...currentDefaultSettings,
		...(storedSettings || {}),
	};
	settings.listeningProfile = typeof settings.listeningProfile === "string"
		? normalizeListeningProfile(settings.listeningProfile)
		: (storedSettings ? "custom" : "auto");
	if (settings.listeningProfile === "auto") {
		Object.assign(settings, currentDefaultSettings, { listeningProfile: "auto" });
	}

	syncSourceAccess();
	if (!hasSource) {
		setEngineState("veille");
		setOutputMode("veille");
		setSourceState("aucune source");
	} else {
		setEngineState("web en attente");
		setOutputMode("intégrée");
		setSourceState("annoncée");
	}

	applyPresetDecor(currentSpatialPreset);

	let graph = null;
	let scopeCanvas = null;
	let scopeContext = null;
	let scopeFrame = 0;
	let frequencyData = null;
	let timeData = null;
	const visualLevels = {
		energy: 0,
		low: 0,
		mid: 0,
		high: 0,
	};

	const writeVisualLevels = () => {
		visualHosts.forEach((host) => {
			host.style.setProperty("--player-energy", visualLevels.energy.toFixed(3));
			host.style.setProperty("--player-low", visualLevels.low.toFixed(3));
			host.style.setProperty("--player-mid", visualLevels.mid.toFixed(3));
			host.style.setProperty("--player-high", visualLevels.high.toFixed(3));
		});
	};

	const resetVisualLevels = () => {
		visualLevels.energy = 0;
		visualLevels.low = 0;
		visualLevels.mid = 0;
		visualLevels.high = 0;
		writeVisualLevels();
	};

	const ensureScopeCanvas = () => {
		if (prefersReducedMotion) {
			return null;
		}
		if (scopeCanvas instanceof HTMLCanvasElement && scopeContext instanceof CanvasRenderingContext2D) {
			return { canvas: scopeCanvas, context: scopeContext };
		}

		const canvas = document.createElement("canvas");
		canvas.className = "str3m-player__scope";
		canvas.dataset.str3mPlayerScope = "1";
		canvas.setAttribute("aria-hidden", "true");
		root.prepend(canvas);

		const context = canvas.getContext("2d");
		if (!(context instanceof CanvasRenderingContext2D)) {
			canvas.remove();
			return null;
		}

		scopeCanvas = canvas;
		scopeContext = context;
		return { canvas, context };
	};

	const resizeScopeCanvas = (canvas) => {
		const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
		const rect = canvas.getBoundingClientRect();
		const width = Math.max(1, Math.floor(rect.width * pixelRatio));
		const height = Math.max(1, Math.floor(rect.height * pixelRatio));
		if (canvas.width !== width || canvas.height !== height) {
			canvas.width = width;
			canvas.height = height;
		}
		return { width, height, pixelRatio };
	};

	const averageBins = (data, start, end) => {
		const safeStart = Math.max(0, Math.min(data.length - 1, Math.floor(start)));
		const safeEnd = Math.max(safeStart + 1, Math.min(data.length, Math.floor(end)));
		let total = 0;
		for (let index = safeStart; index < safeEnd; index += 1) {
			total += data[index];
		}
		return total / ((safeEnd - safeStart) * 255);
	};

	const drawRoundedBar = (context, x, y, width, height, radius) => {
		if (typeof context.roundRect === "function") {
			context.beginPath();
			context.roundRect(x, y, width, height, radius);
			context.fill();
			return;
		}
		context.fillRect(x, y, width, height);
	};

	const drawScopeFrame = () => {
		const analyser = graph?.analyser;
		if (!analyser) {
			resetVisualLevels();
			return;
		}

		const scope = ensureScopeCanvas();
		if (!scope) {
			return;
		}

		const { canvas, context } = scope;
		const { width, height, pixelRatio } = resizeScopeCanvas(canvas);
		if (!frequencyData || frequencyData.length !== analyser.frequencyBinCount) {
			frequencyData = new Uint8Array(analyser.frequencyBinCount);
		}
		if (!timeData || timeData.length !== analyser.fftSize) {
			timeData = new Uint8Array(analyser.fftSize);
		}

		analyser.getByteFrequencyData(frequencyData);
		analyser.getByteTimeDomainData(timeData);

		const length = frequencyData.length;
		const low = averageBins(frequencyData, 1, length * 0.08);
		const mid = averageBins(frequencyData, length * 0.08, length * 0.36);
		const high = averageBins(frequencyData, length * 0.36, length * 0.82);
		const energy = clamp((low * 0.48) + (mid * 0.34) + (high * 0.18), 0, 1);
		visualLevels.low += (low - visualLevels.low) * 0.24;
		visualLevels.mid += (mid - visualLevels.mid) * 0.22;
		visualLevels.high += (high - visualLevels.high) * 0.2;
		visualLevels.energy += (energy - visualLevels.energy) * 0.24;
		writeVisualLevels();

		context.clearRect(0, 0, width, height);
		context.globalCompositeOperation = "lighter";

		const lowGlow = context.createRadialGradient(width * 0.2, height * 0.18, 0, width * 0.2, height * 0.18, width * (0.36 + visualLevels.low * 0.16));
		lowGlow.addColorStop(0, `rgba(103, 255, 214, ${0.06 + visualLevels.low * 0.18})`);
		lowGlow.addColorStop(1, "rgba(103, 255, 214, 0)");
		context.fillStyle = lowGlow;
		context.fillRect(0, 0, width, height);

		const highGlow = context.createRadialGradient(width * 0.82, height * 0.84, 0, width * 0.82, height * 0.84, width * (0.3 + visualLevels.high * 0.18));
		highGlow.addColorStop(0, `rgba(255, 220, 154, ${0.05 + visualLevels.high * 0.16})`);
		highGlow.addColorStop(1, "rgba(255, 220, 154, 0)");
		context.fillStyle = highGlow;
		context.fillRect(0, 0, width, height);

		const barCount = Math.min(64, Math.max(28, Math.floor(width / (pixelRatio * 15))));
		const binStep = Math.max(1, Math.floor(length / barCount));
		const barWidth = Math.max(pixelRatio * 2, (width / barCount) * 0.44);
		const centerY = height * (0.6 - visualLevels.low * 0.06);
		for (let index = 0; index < barCount; index += 1) {
			const start = index * binStep;
			const value = averageBins(frequencyData, start, start + binStep);
			const lift = Math.pow(value, 1.3);
			const barHeight = Math.max(pixelRatio * 2, lift * height * 0.42);
			const x = (index / barCount) * width;
			const y = centerY - (barHeight / 2);
			const alpha = 0.12 + lift * 0.5;
			context.fillStyle = index % 3 === 0
				? `rgba(255, 226, 166, ${alpha})`
				: `rgba(132, 255, 224, ${alpha})`;
			drawRoundedBar(context, x, y, barWidth, barHeight, barWidth / 2);
		}

		context.globalCompositeOperation = "source-over";
		context.beginPath();
		const samples = timeData.length;
		for (let index = 0; index < samples; index += 4) {
			const x = (index / (samples - 1)) * width;
			const sample = (timeData[index] - 128) / 128;
			const y = (height * 0.38) + (sample * height * (0.08 + visualLevels.energy * 0.16));
			if (index === 0) {
				context.moveTo(x, y);
			} else {
				context.lineTo(x, y);
			}
		}
		context.strokeStyle = `rgba(255, 241, 197, ${0.22 + visualLevels.energy * 0.42})`;
		context.lineWidth = Math.max(1, pixelRatio * 1.2);
		context.stroke();
	};

	const stopScopeRender = () => {
		if (scopeFrame) {
			window.cancelAnimationFrame(scopeFrame);
			scopeFrame = 0;
		}
		root.classList.remove("is-visualizing", "is-playing");
		resetVisualLevels();
		if (scopeCanvas instanceof HTMLCanvasElement && scopeContext instanceof CanvasRenderingContext2D) {
			const { width, height } = resizeScopeCanvas(scopeCanvas);
			scopeContext.clearRect(0, 0, width, height);
		}
	};

	const startScopeRender = () => {
		if (prefersReducedMotion || scopeFrame || !graph?.analyser) {
			return;
		}
		root.classList.add("is-visualizing");

		const tick = () => {
			scopeFrame = 0;
			if (audio.paused || audio.ended || !graph?.analyser) {
				stopScopeRender();
				return;
			}
			drawScopeFrame();
			scopeFrame = window.requestAnimationFrame(tick);
		};

		scopeFrame = window.requestAnimationFrame(tick);
	};

	const syncPlaybackVisualState = () => {
		const isPlaying = !audio.paused && !audio.ended;
		root.classList.toggle("is-playing", isPlaying);
		if (isPlaying) {
			startScopeRender();
			return;
		}
		stopScopeRender();
	};

	resetVisualLevels();

	const saveSettings = () => {
		try {
			window.localStorage.setItem(storageKey, JSON.stringify(settings));
		} catch (_error) {
			// storage unavailable — keep the stream moving
		}
	};

	const setStatus = (copy) => {
		if (statusOutput instanceof HTMLElement) {
			statusOutput.textContent = copy;
		}
	};

	const syncToggleLabel = () => {
		if (toggleButton instanceof HTMLButtonElement) {
			toggleButton.textContent = audio.paused ? "lecture" : "pause";
		}
	};

	const setPreservePitch = (enabled) => {
		if ("preservesPitch" in audio) {
			audio.preservesPitch = enabled;
		}
		if ("mozPreservesPitch" in audio) {
			audio.mozPreservesPitch = enabled;
		}
		if ("webkitPreservesPitch" in audio) {
			audio.webkitPreservesPitch = enabled;
		}
	};

	const syncRate = () => {
		audio.playbackRate = clamp(Number(settings.rate) || 1, 0.5, 2);
		const label = `${audio.playbackRate.toFixed(2)}×`;
		if (rateOutput instanceof HTMLElement) {
			rateOutput.textContent = label;
		}
		if (rateStateOutput instanceof HTMLElement) {
			rateStateOutput.textContent = label;
		}
	};

	const syncEqSummary = () => {
		const isFlat = [settings.bass, settings.mid, settings.treble].every((value) => Math.abs(Number(value) || 0) < 0.01)
			&& Math.abs((Number(settings.gain) || 100) - 100) < 0.01;
		const summary = isFlat
			? "plat"
			: `B ${Number(settings.bass).toFixed(1)} · M ${Number(settings.mid).toFixed(1)} · T ${Number(settings.treble).toFixed(1)} · G ${Math.round(Number(settings.gain))}%`;
		if (eqSummaryOutput instanceof HTMLElement) {
			eqSummaryOutput.textContent = summary;
		}
	};

	const syncSliderOutputs = () => {
		if (bassValue instanceof HTMLElement) {
			bassValue.textContent = `${Number(settings.bass).toFixed(1)} dB`;
		}
		if (midValue instanceof HTMLElement) {
			midValue.textContent = `${Number(settings.mid).toFixed(1)} dB`;
		}
		if (trebleValue instanceof HTMLElement) {
			trebleValue.textContent = `${Number(settings.treble).toFixed(1)} dB`;
		}
		if (gainValue instanceof HTMLElement) {
			gainValue.textContent = `${Math.round(Number(settings.gain))}%`;
		}
	};

	const syncSettingInputs = () => {
		if (bassInput instanceof HTMLInputElement) {
			bassInput.value = String(settings.bass);
		}
		if (midInput instanceof HTMLInputElement) {
			midInput.value = String(settings.mid);
		}
		if (trebleInput instanceof HTMLInputElement) {
			trebleInput.value = String(settings.treble);
		}
		if (gainInput instanceof HTMLInputElement) {
			gainInput.value = String(settings.gain);
		}
		if (preservePitchInput instanceof HTMLInputElement) {
			preservePitchInput.checked = Boolean(settings.preservePitch);
		}
	};

	const syncListeningProfileButtons = () => {
		const activeProfile = normalizeListeningProfile(settings.listeningProfile || "custom");
		listeningPresetButtons.forEach((button) => {
			if (!(button instanceof HTMLButtonElement)) {
				return;
			}
			const buttonProfile = normalizeListeningProfile(button.dataset.str3mPlayerListeningPreset || "auto");
			button.setAttribute("aria-pressed", buttonProfile === activeProfile ? "true" : "false");
		});
		root.dataset.str3mPlayerListeningProfile = activeProfile;
	};

	const syncProgress = () => {
		if (!(progressInput instanceof HTMLInputElement)) {
			return;
		}

		const duration = Number.isFinite(audio.duration) ? audio.duration : 0;
		progressInput.value = duration > 0 ? String(audio.currentTime / duration) : "0";
		if (currentOutput instanceof HTMLElement) {
			currentOutput.textContent = formatTime(audio.currentTime);
		}
		if (durationOutput instanceof HTMLElement) {
			durationOutput.textContent = formatTime(duration);
		}
	};

	const applyEqSettings = () => {
		syncSliderOutputs();
		syncEqSummary();
		syncListeningProfileButtons();

		if (!graph) {
			return;
		}

		graph.bass.gain.value = Number(settings.bass) || 0;
		graph.mid.gain.value = Number(settings.mid) || 0;
		graph.treble.gain.value = Number(settings.treble) || 0;
		graph.gain.gain.value = clamp((Number(settings.gain) || 100) / 100, 0, 1.5);
	};

	const applyCurrentSpatialDefaults = ({ persist = false, status = "" } = {}) => {
		settings.rate = currentDefaultSettings.rate;
		settings.preservePitch = currentDefaultSettings.preservePitch;
		settings.bass = currentDefaultSettings.bass;
		settings.mid = currentDefaultSettings.mid;
		settings.treble = currentDefaultSettings.treble;
		settings.gain = currentDefaultSettings.gain;
		settings.listeningProfile = "auto";

		syncSettingInputs();
		setPreservePitch(Boolean(settings.preservePitch));
		syncRate();
		applyEqSettings();
		if (persist) {
			saveSettings();
		}
		if (status) {
			setStatus(status);
		}
	};

	const applyListeningProfile = (profileKey, { persist = true } = {}) => {
		const key = normalizeListeningProfile(profileKey);
		if (key === "custom") {
			return;
		}

		if (key === "auto") {
			userCustomizedSettings = false;
			applyCurrentSpatialDefaults({
				persist,
				status: currentSpatialPreset?.status || listeningProfiles.auto.status,
			});
			return;
		}

		const profile = listeningProfiles[key];
		userCustomizedSettings = true;
		settings.rate = profile.rate;
		settings.preservePitch = profile.preservePitch;
		settings.bass = profile.bass;
		settings.mid = profile.mid;
		settings.treble = profile.treble;
		settings.gain = profile.gain;
		settings.listeningProfile = key;

		syncSettingInputs();
		setPreservePitch(Boolean(settings.preservePitch));
		syncRate();
		applyEqSettings();
		if (persist) {
			saveSettings();
		}
		setStatus(profile.status);
		if (eqStateOutput instanceof HTMLElement) {
			eqStateOutput.textContent = profile.note;
		}
	};

	const ensureAudioGraph = async () => {
		if (!hasSource) {
			return null;
		}

		if (graph) {
			if (graph.context.state === "suspended") {
				await graph.context.resume().catch(() => {});
			}
			return graph;
		}

		const AudioContextClass = window.AudioContext || window.webkitAudioContext;
		if (!AudioContextClass) {
			enableNativeAudioFallback("lecture native");
			if (eqStateOutput instanceof HTMLElement) {
				eqStateOutput.textContent = "natif";
			}
			return null;
		}

		try {
			const context = new AudioContextClass();
			const source = context.createMediaElementSource(audio);
			const bass = context.createBiquadFilter();
			const mid = context.createBiquadFilter();
			const treble = context.createBiquadFilter();
			const gain = context.createGain();
			const analyser = context.createAnalyser();

			bass.type = "lowshelf";
			bass.frequency.value = 180;
			mid.type = "peaking";
			mid.frequency.value = 1000;
			mid.Q.value = 0.85;
			treble.type = "highshelf";
			treble.frequency.value = 3200;
			analyser.fftSize = 1024;
			analyser.smoothingTimeConstant = 0.78;

			source.connect(bass);
			bass.connect(mid);
			mid.connect(treble);
			treble.connect(gain);
			gain.connect(analyser);
			analyser.connect(context.destination);

			graph = { context, bass, mid, treble, gain, analyser };
			disableNativeAudioFallback();
			applyEqSettings();
			ensureScopeCanvas();

			if (eqStateOutput instanceof HTMLElement) {
				eqStateOutput.textContent = "actif";
			}
			setEngineState("eq web");
			setOutputMode("intégrée");

			if (context.state === "suspended") {
				await context.resume().catch(() => {});
			}
			syncPlaybackVisualState();

			return graph;
		} catch (_error) {
			enableNativeAudioFallback("lecture native");
			graph = null;
			return null;
		}
	};

	const resetPlayer = () => {
		userCustomizedSettings = false;
		applyCurrentSpatialDefaults({
			persist: true,
			status: hasSource ? (currentSpatialPreset?.status || "preset spatial") : "veille",
		});
	};

	setPreservePitch(Boolean(settings.preservePitch));
	syncRate();
	syncSliderOutputs();
	syncEqSummary();
	syncProgress();
	syncToggleLabel();
	syncPlaybackVisualState();
	syncSettingInputs();
	syncListeningProfileButtons();

	if (sourceOutput instanceof HTMLElement && !hasSource) {
		sourceOutput.textContent = "aucune nappe";
	}

	if (!hasSource) {
		setStatus("veille");
		if (eqStateOutput instanceof HTMLElement) {
			eqStateOutput.textContent = "hors source";
		}
		return;
	}

	setStatus(currentSpatialPreset?.status || "prêt");
	if (sourceOutput instanceof HTMLElement) {
		sourceOutput.textContent = title;
	}

	const seekBy = (offset) => {
		audio.currentTime = clamp(audio.currentTime + offset, 0, Number.isFinite(audio.duration) ? audio.duration : audio.currentTime + offset);
		syncProgress();
	};

	const updateEqFromInput = (input, key) => {
		if (!(input instanceof HTMLInputElement)) {
			return;
		}
		userCustomizedSettings = true;
		settings.listeningProfile = "custom";
		settings[key] = Number(input.value);
		applyEqSettings();
		saveSettings();
	};

	if (toggleButton instanceof HTMLButtonElement) {
		toggleButton.addEventListener("click", async () => {
			await ensureAudioGraph().catch(() => {
				enableNativeAudioFallback("lecture native");
			});
			if (audio.paused) {
				audio.play().then(() => {
					setStatus(graph?.analyser && !prefersReducedMotion ? "en lecture · aura" : "en lecture");
					syncPlaybackVisualState();
				}).catch(() => {
					setStatus("interaction requise");
				});
				return;
			}

			audio.pause();
			setStatus("pause");
		});
	}

	if (backButton instanceof HTMLButtonElement) {
		backButton.addEventListener("click", () => {
			seekBy(-5);
			setStatus("recul −5 s");
		});
	}

	if (forwardButton instanceof HTMLButtonElement) {
		forwardButton.addEventListener("click", () => {
			seekBy(5);
			setStatus("avance +5 s");
		});
	}

	if (progressInput instanceof HTMLInputElement) {
		progressInput.addEventListener("input", () => {
			if (!Number.isFinite(audio.duration) || audio.duration <= 0) {
				return;
			}
			audio.currentTime = audio.duration * Number(progressInput.value);
			syncProgress();
		});
	}

	rateStepButtons.forEach((button) => {
		if (!(button instanceof HTMLButtonElement)) {
			return;
		}

		button.addEventListener("click", () => {
			userCustomizedSettings = true;
			settings.listeningProfile = "custom";
			const delta = Number(button.dataset.str3mPlayerRateStep || 0);
			settings.rate = clamp((Number(settings.rate) || 1) + delta, 0.5, 2);
			syncRate();
			syncListeningProfileButtons();
			saveSettings();
			setStatus(`vitesse ${audio.playbackRate.toFixed(2)}×`);
		});
	});

	listeningPresetButtons.forEach((button) => {
		if (!(button instanceof HTMLButtonElement)) {
			return;
		}

		button.addEventListener("click", () => {
			applyListeningProfile(button.dataset.str3mPlayerListeningPreset || "auto");
		});
	});

	if (preservePitchInput instanceof HTMLInputElement) {
		preservePitchInput.addEventListener("change", () => {
			userCustomizedSettings = true;
			settings.listeningProfile = "custom";
			settings.preservePitch = preservePitchInput.checked;
			setPreservePitch(settings.preservePitch);
			syncListeningProfileButtons();
			saveSettings();
			setStatus(settings.preservePitch ? "hauteur conservée" : "hauteur libre");
		});
	}

	if (bassInput instanceof HTMLInputElement) {
		bassInput.addEventListener("input", () => updateEqFromInput(bassInput, "bass"));
	}
	if (midInput instanceof HTMLInputElement) {
		midInput.addEventListener("input", () => updateEqFromInput(midInput, "mid"));
	}
	if (trebleInput instanceof HTMLInputElement) {
		trebleInput.addEventListener("input", () => updateEqFromInput(trebleInput, "treble"));
	}
	if (gainInput instanceof HTMLInputElement) {
		gainInput.addEventListener("input", () => updateEqFromInput(gainInput, "gain"));
	}

	if (resetButton instanceof HTMLButtonElement) {
		resetButton.addEventListener("click", resetPlayer);
	}

	if (retryButton instanceof HTMLButtonElement) {
		retryButton.addEventListener("click", async () => {
			setStatus("relance moteur…");
			setSourceState("vérification");
			const restoredGraph = await ensureAudioGraph().catch(() => null);
			if (restoredGraph) {
				setStatus("EQ relancé");
				setSourceState(audio.readyState >= 2 ? "prête" : "annoncée");
				syncPlaybackVisualState();
				return;
			}
			enableNativeAudioFallback("lecture native");
			setSourceState("native disponible");
		});
	}

	audio.addEventListener("loadedmetadata", syncProgress);
	audio.addEventListener("loadedmetadata", () => {
		setSourceState("chargée");
	});
	audio.addEventListener("durationchange", syncProgress);
	audio.addEventListener("timeupdate", syncProgress);
	audio.addEventListener("play", () => {
		syncToggleLabel();
		syncPlaybackVisualState();
		setStatus(graph?.analyser && !prefersReducedMotion ? "en lecture · aura" : "en lecture");
	});
	audio.addEventListener("pause", () => {
		syncToggleLabel();
		syncPlaybackVisualState();
		if (audio.ended) {
			setStatus("terminé");
			return;
		}
		setStatus("pause");
	});
	audio.addEventListener("ended", () => {
		syncToggleLabel();
		syncPlaybackVisualState();
		setStatus("terminé");
	});
	audio.addEventListener("waiting", () => {
		setStatus("mise en mémoire…");
		setSourceState("mise en mémoire");
	});
	audio.addEventListener("canplay", () => {
		setSourceState("prête");
		if (audio.paused) {
			setStatus("prêt");
		}
	});
	audio.addEventListener("stalled", () => {
		setSourceState("réseau lent");
	});
	audio.addEventListener("suspend", () => {
		if (audio.networkState === HTMLMediaElement.NETWORK_IDLE) {
			setSourceState(audio.readyState >= 2 ? "prête" : "pause réseau");
		}
	});
	audio.addEventListener("emptied", () => {
		setSourceState("vidée");
	});
	audio.addEventListener("error", () => {
		setStatus("erreur média");
		setSourceState("erreur média");
	});

	const refreshSpatialPreset = () => {
		currentSpatialPreset = str3mPlayerPresetFromSpatialState(readActiveIoRaSession(), readActiveIoWorldInstrumentSession());
		currentDefaultSettings = buildSettingsFromPreset(currentSpatialPreset);
		applyPresetDecor(currentSpatialPreset);
		if (!userCustomizedSettings) {
			applyCurrentSpatialDefaults({
				persist: false,
				status: hasSource ? (currentSpatialPreset?.status || "preset spatial") : "veille",
			});
		}
	};

	window.addEventListener("o:ra-modulation", refreshSpatialPreset);
	window.addEventListener("o:world-instrument", refreshSpatialPreset);

	root.addEventListener("keydown", async (event) => {
		const target = event.target;
		if (target instanceof HTMLElement && target.closest("input, textarea, select")) {
			return;
		}

		if (event.code === "Space") {
			event.preventDefault();
			await ensureAudioGraph();
			if (audio.paused) {
				audio.play().catch(() => {
					setStatus("interaction requise");
				});
			} else {
				audio.pause();
			}
			return;
		}

		if (event.key === "ArrowLeft") {
			event.preventDefault();
			seekBy(-5);
			return;
		}

		if (event.key === "ArrowRight") {
			event.preventDefault();
			seekBy(5);
			return;
		}

		if (event.key === "-" || event.key === "_") {
			event.preventDefault();
			userCustomizedSettings = true;
			settings.listeningProfile = "custom";
			settings.rate = clamp((Number(settings.rate) || 1) - 0.25, 0.5, 2);
			syncRate();
			syncListeningProfileButtons();
			saveSettings();
			return;
		}

		if (event.key === "+" || event.key === "=") {
			event.preventDefault();
			userCustomizedSettings = true;
			settings.listeningProfile = "custom";
			settings.rate = clamp((Number(settings.rate) || 1) + 0.25, 0.5, 2);
			syncRate();
			syncListeningProfileButtons();
			saveSettings();
		}
	});
}

function initStr3mIntegratedPlayer() {
	const roots = Array.from(document.querySelectorAll("[data-str3m-player]"));
	if (!roots.length) {
		return;
	}

	roots.forEach((root) => {
		bindStr3mIntegratedPlayer(root);
	});
}
