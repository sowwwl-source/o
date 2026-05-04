const DEFAULT_TIMEZONE = "Europe/Paris";
const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
const coarsePointer = window.matchMedia("(pointer: coarse)").matches || (navigator.maxTouchPoints || 0) > 0;
const THEME_KEY = "o-theme-inverted";
const GUIDE_VOICE_MUTE_KEY = "o-guide-voice-muted-v1";

const reveals = Array.from(document.querySelectorAll(".reveal"));
const usernameInput = document.querySelector("[data-username-input]");
const timezoneInput = document.querySelector("[data-timezone-input]");
const timezoneStatus = document.querySelector("[data-timezone-status]");
const timezoneChips = Array.from(document.querySelectorAll("[data-timezone-chip]"));
const previewClock = document.querySelector("[data-preview-clock]");
const previewShell = document.querySelector("[data-preview-shell]");
const useLocalTimezoneButton = document.querySelector("[data-use-local-timezone]");
const bootline = document.getElementById("bootline");
const copyButtons = Array.from(document.querySelectorAll("[data-copy-link]"));
const torusCanvases = Array.from(document.querySelectorAll("[data-torus-cloud]"));

// === SIGNUP SPECTRUM LOGIC ===
const signupProgramInputs = Array.from(document.querySelectorAll('[data-signup-program-input]'));
const signupProgramCards = Array.from(document.querySelectorAll('[data-signup-program-card]'));
const signupLambdaInput = document.querySelector('[data-signup-lambda-input]');
const signupProgramLabelOutputs = Array.from(document.querySelectorAll('[data-signup-program-label], [data-preview-program-label]'));
const signupProgramToneOutputs = Array.from(document.querySelectorAll('[data-signup-program-tone], [data-preview-program-tone]'));
const signupLambdaValueOutputs = Array.from(document.querySelectorAll('[data-signup-lambda-value]'));
const signupLambdaRangeOutput = document.querySelector('[data-signup-lambda-range]');

function updateSignupSpectrumUI() {
	const checkedProgram = signupProgramInputs.find((input) => input.checked);
	if (!checkedProgram) return;
	const programLabel = checkedProgram.dataset.programLabel || checkedProgram.value;
	const programTone = checkedProgram.dataset.programTone || '';
	const lambdaMin = Number(checkedProgram.dataset.lambdaMin || 400);
	const lambdaMax = Number(checkedProgram.dataset.lambdaMax || 700);
	const lambdaDefault = Number(checkedProgram.dataset.lambdaDefault || Math.floor((lambdaMin + lambdaMax) / 2));
	let lambda = Number(signupLambdaInput?.value || lambdaDefault);
	lambda = Math.max(lambdaMin, Math.min(lambda, lambdaMax));
	if (signupLambdaInput) {
		signupLambdaInput.min = lambdaMin;
		signupLambdaInput.max = lambdaMax;
		signupLambdaInput.value = lambda;
	}
	signupProgramLabelOutputs.forEach((el) => el.textContent = programLabel);
	signupProgramToneOutputs.forEach((el) => el.textContent = programTone);
	signupLambdaValueOutputs.forEach((el) => el.textContent = lambda);
	if (signupLambdaRangeOutput) signupLambdaRangeOutput.textContent = `${lambdaMin}–${lambdaMax} nm`;
}

if (signupProgramInputs.length && signupLambdaInput) {
	signupProgramInputs.forEach((input) => {
		input.addEventListener('change', () => {
			updateSignupSpectrumUI();
		});
	});
	signupLambdaInput.addEventListener('input', () => {
		updateSignupSpectrumUI();
	});
	// Initial sync
	updateSignupSpectrumUI();
}

function applyThemeState(isInverted) {
	document.body.classList.toggle("is-inverted", Boolean(isInverted));
}

function readThemeState() {
	return window.localStorage.getItem(THEME_KEY) === "1";
}

function writeThemeState(isInverted) {
	window.localStorage.setItem(THEME_KEY, isInverted ? "1" : "0");
	applyThemeState(isInverted);
}

function toggleThemeState() {
	writeThemeState(!readThemeState());
}

function readGuideVoiceMutedState() {
	return window.localStorage.getItem(GUIDE_VOICE_MUTE_KEY) === "1";
}

function writeGuideVoiceMutedState(isMuted) {
	window.localStorage.setItem(GUIDE_VOICE_MUTE_KEY, isMuted ? "1" : "0");
	window.dispatchEvent(new CustomEvent("o:guide-voice-mute-change", {
		detail: { muted: Boolean(isMuted) },
	}));
}

function toggleGuideVoiceMutedState() {
	const next = !readGuideVoiceMutedState();
	writeGuideVoiceMutedState(next);
	return next;
}

function toggleInversionAndGuideVoiceMute() {
	toggleThemeState();
	return toggleGuideVoiceMutedState();
}

applyThemeState(readThemeState());

function isInteractiveElementTarget(target) {
	return target instanceof Element && Boolean(target.closest("a, button, input, textarea, select, summary, label, details, [contenteditable=\"true\"]"));
}

function canToggleInversionFromTarget(target) {
	if (!(target instanceof Element)) {
		return false;
	}

	if (isInteractiveElementTarget(target)) {
		return false;
	}

	if (target.closest("[data-torus-cloud], .main-torus, canvas")) {
		return false;
	}

	return true;
}

function parseRgbTriplet(value, fallback) {
	const match = value.match(/\d+(?:\.\d+)?/g);
	if (!match || match.length < 3) {
		return fallback;
	}

	return match.slice(0, 3).map((part) => Number(part));
}

function clampNumber(value, minimum, maximum) {
	return Math.min(maximum, Math.max(minimum, value));
}

function mixRgb(left, right, amount) {
	const factor = clampNumber(amount, 0, 1);
	return left.map((value, index) => Math.round(value + (right[index] - value) * factor));
}

function lambdaToRgb(lambda) {
	const wavelength = clampNumber(Number.isFinite(lambda) ? lambda : 548, 380, 780);
	let red = 0;
	let green = 0;
	let blue = 0;

	if (wavelength >= 380 && wavelength < 440) {
		red = -(wavelength - 440) / (440 - 380);
		blue = 1;
	} else if (wavelength < 490) {
		green = (wavelength - 440) / (490 - 440);
		blue = 1;
	} else if (wavelength < 510) {
		green = 1;
		blue = -(wavelength - 510) / (510 - 490);
	} else if (wavelength < 580) {
		red = (wavelength - 510) / (580 - 510);
		green = 1;
	} else if (wavelength < 645) {
		red = 1;
		green = -(wavelength - 645) / (645 - 580);
	} else {
		red = 1;
	}

	let attenuation = 1;
	if (wavelength > 700) {
		attenuation = 0.3 + 0.7 * ((780 - wavelength) / (780 - 700));
	} else if (wavelength < 420) {
		attenuation = 0.3 + 0.7 * ((wavelength - 380) / (420 - 380));
	}

	const gamma = 0.8;
	const toChannel = (channel) => Math.round(255 * Math.pow(clampNumber(channel * attenuation, 0, 1), gamma));

	return [toChannel(red), toChannel(green), toChannel(blue)];
}

function emitLandSignatureChange() {
	window.dispatchEvent(new CustomEvent("o:land-signature-change"));
}

function spectralLambdaValue(candidate, fallback = 548) {
	const parsed = Number.parseFloat(candidate);
	return clampNumber(Number.isFinite(parsed) ? parsed : fallback, 380, 780);
}

function normalizeGuideSpeechText(value) {
	return (value || "")
		.normalize("NFD")
		.replace(/[\u0300-\u036f]/g, "")
		.toLowerCase();
}

function detectGuideSpeechLanguage(text) {
	const normalized = normalizeGuideSpeechText(text);
	if (!normalized) {
		return "fr-FR";
	}

	const markers = {
		"en-US": ["hello", "please", "take me", "guide me", "what is", "public entry"],
		"es-ES": ["hola", "quiero", "llevame", "explica", "puerta", "publico"],
		"pt-PT": ["ola", "quero", "leva-me", "leva me", "porta", "publico", "terra"],
		"it-IT": ["ciao", "voglio", "portami", "spiega", "porta", "terra"],
		"fr-FR": ["bonjour", "salut", "terre", "porte", "projet", "visiter"],
	};

	let bestLanguage = "fr-FR";
	let bestScore = 0;

	Object.entries(markers).forEach(([language, needles]) => {
		let score = 0;
		needles.forEach((needle) => {
			if (normalized.includes(needle)) {
				score += 1;
			}
		});

		if (score > bestScore) {
			bestScore = score;
			bestLanguage = language;
		}
	});

	return bestLanguage;
}

function pickGuideSpeechVoice(synth, languageCode = "fr-FR") {
	if (!synth || typeof synth.getVoices !== "function") {
		return null;
	}

	const prefix = languageCode.split("-")[0].toLowerCase();
	const preferredNames = /(premium|natural|enhanced|siri|google|audrey|amelie|thomas|paulina|monica|jorge|joana|alice|anna)/i;
	const voices = synth.getVoices().filter((voice) => {
		return typeof voice.lang === "string" && voice.lang.toLowerCase().startsWith(prefix);
	});

	if (!voices.length) {
		return null;
	}

	const ranked = voices
		.map((voice) => {
			let score = 0;
			if (voice.default) {
				score += 3;
			}
			if (voice.localService) {
				score += 2;
			}
			if (preferredNames.test(voice.name || "")) {
				score += 4;
			}

			return { voice, score };
		})
		.sort((left, right) => right.score - left.score);

	return ranked[0]?.voice || voices[0] || null;
}

function resolveGuideVoiceSpectralProfile(program = "collective", lambdaCandidate = 548, tone = "", label = "") {
	const lambda = spectralLambdaValue(lambdaCandidate, 548);
	const normalized = (lambda - 380) / (780 - 380);
	const frequencyBias = 1 - normalized;
	let pitch = 0.82 + frequencyBias * 0.34;
	let rate = 0.86 + frequencyBias * 0.18;
	let volume = 0.94;

	switch ((program || "collective").toLowerCase()) {
		case "dur3rb":
			pitch -= 0.08;
			rate -= 0.06;
			volume += 0.02;
			break;
		case "tocu":
			pitch += 0.07;
			rate += 0.06;
			break;
		case "culbu1on":
			pitch += 0.02;
			rate += 0.01;
			break;
		default:
			break;
	}

	pitch = clampNumber(pitch, 0.74, 1.24);
	rate = clampNumber(rate, 0.82, 1.14);
	volume = clampNumber(volume, 0.86, 1);

	let registerLabel = "medium soyeux";
	if (frequencyBias >= 0.82) {
		registerLabel = "aigu clair";
	} else if (frequencyBias >= 0.64) {
		registerLabel = "haut satine";
	} else if (frequencyBias >= 0.24) {
		registerLabel = "grave souple";
	} else if (frequencyBias < 0.24) {
		registerLabel = "grave velours";
	}

	let tempoLabel = "tempo pose";
	if (rate >= 1.08) {
		tempoLabel = "tempo vif";
	} else if (rate >= 1) {
		tempoLabel = "tempo fluide";
	} else if (rate < 0.89) {
		tempoLabel = "tempo lent";
	} else if (rate < 0.95) {
		tempoLabel = "tempo retenu";
	}

	return {
		program: program || "collective",
		label: label || program || "collectif",
		tone: tone || "",
		lambda,
		pitch,
		rate,
		volume,
		registerLabel,
		tempoLabel,
		orbPulseDuration: clampNumber(2.14 - rate * 0.76, 1.18, 1.72),
		coreScale: clampNumber(0.98 + (pitch - 0.74) * 0.42, 1.02, 1.2),
	};
}

function readGuideVoiceSpectralProfile(root) {
	const body = document.body?.dataset || {};
	const rootData = root?.dataset || {};
	const program = body.landProgram || rootData.guideVoiceProgram || "collective";
	const label = body.landLabel || rootData.guideVoiceLabel || program || "collectif";
	const tone = body.landTone || rootData.guideVoiceTone || "";
	const lambdaCandidate = body.landLambda || rootData.guideVoiceLambda || "548";

	return resolveGuideVoiceSpectralProfile(program, lambdaCandidate, tone, label);
}

function readCameraReactiveState() {
	const body = document.body;
	if (!(body instanceof HTMLBodyElement)) {
		return {
			ready: false,
			luma: 0,
			motion: 0,
			rgb: [180, 180, 180],
		};
	}

	return {
		ready: body.classList.contains("is-camera-ready"),
		luma: clampNumber(Number.parseFloat(body.dataset.cameraLuma || "0"), 0, 1),
		motion: clampNumber(Number.parseFloat(body.dataset.cameraMotion || "0"), 0, 1),
		rgb: parseRgbTriplet(body.dataset.cameraRgb || "180 180 180", [180, 180, 180]),
	};
}

function resolveTorusProfile(canvas) {
	const bodyStyles = getComputedStyle(document.body);
	const landType = canvas.dataset.landType || document.body.dataset.landProgram || "collective";
	const lambda = Number.parseFloat(canvas.dataset.lambda || document.body.dataset.landLambda || "548");
	const mood = canvas.dataset.streamMood || "calm";
	const base = lambdaToRgb(lambda);
	const accent = parseRgbTriplet(bodyStyles.getPropertyValue("--land-accent-rgb"), base);
	const secondary = parseRgbTriplet(bodyStyles.getPropertyValue("--land-secondary-rgb"), accent);
	const glow = parseRgbTriplet(bodyStyles.getPropertyValue("--land-glow-rgb"), secondary);
	const cameraState = readCameraReactiveState();
	let profile;

	if (landType === "dur3rb") {
		const luminance = Math.round(base[0] * 0.299 + base[1] * 0.587 + base[2] * 0.114);
		const grayscale = [luminance, luminance, luminance];
		profile = {
			primary: mixRgb(grayscale, accent, 0.12),
			secondary: mixRgb(grayscale, glow, 0.08),
			glow,
			waveStrength: 0.58,
			pulseStrength: 0.44,
			signalMode: false,
			motion: mood === "dense" ? 1.08 : 0.9,
		};
	} else if (landType === "tocu") {
		profile = {
			primary: mixRgb(base, accent, 0.48),
			secondary: mixRgb(base, secondary, 0.4),
			glow,
			waveStrength: 1.05,
			pulseStrength: 0.92,
			signalMode: true,
			signalColors: [
				[255, 78, 58],
				[255, 122, 200],
				[246, 226, 76],
			],
			motion: mood === "nocturnal" ? 1.18 : 1.32,
		};
	} else if (landType === "culbu1on") {
		profile = {
			primary: mixRgb(base, accent, 0.52),
			secondary: mixRgb(base, secondary, 0.7),
			glow,
			waveStrength: 0.88,
			pulseStrength: 0.62,
			signalMode: false,
			motion: mood === "calm" ? 0.98 : 1.1,
		};
	} else {
		profile = {
			primary: mixRgb(base, accent, 0.32),
			secondary: mixRgb(base, secondary, 0.46),
			glow,
			waveStrength: mood === "nocturnal" ? 0.74 : 0.68,
			pulseStrength: mood === "dense" ? 0.68 : 0.52,
			signalMode: false,
			motion: mood === "nocturnal" ? 1.06 : 0.92,
		};
	}

	if (!cameraState.ready) {
		return profile;
	}

	const chromaMix = clampNumber(0.12 + cameraState.motion * 0.28 + cameraState.luma * 0.12, 0.12, 0.46);
	const glowMix = clampNumber(0.16 + cameraState.motion * 0.34 + cameraState.luma * 0.08, 0.16, 0.54);

	return {
		...profile,
		primary: mixRgb(profile.primary, cameraState.rgb, chromaMix),
		secondary: mixRgb(profile.secondary, cameraState.rgb, chromaMix * 0.82),
		glow: mixRgb(profile.glow, cameraState.rgb, glowMix),
		waveStrength: clampNumber(profile.waveStrength + cameraState.motion * 0.34 + cameraState.luma * 0.08, 0.48, 1.48),
		pulseStrength: clampNumber(profile.pulseStrength + cameraState.motion * 0.28 + cameraState.luma * 0.14, 0.38, 1.36),
		motion: clampNumber(profile.motion + cameraState.motion * 0.36 + cameraState.luma * 0.08, 0.82, 1.64),
		signalMode: profile.signalMode || cameraState.motion > 0.62,
	};
}

function distanceBetweenPoints(left, right) {
	const deltaX = left.x - right.x;
	const deltaY = left.y - right.y;
	return Math.hypot(deltaX, deltaY);
}

function isSecretCircleGesture(points, totalDistance) {
	if (!Array.isArray(points) || points.length < 12 || totalDistance < 140) {
		return false;
	}

	let minX = Number.POSITIVE_INFINITY;
	let maxX = Number.NEGATIVE_INFINITY;
	let minY = Number.POSITIVE_INFINITY;
	let maxY = Number.NEGATIVE_INFINITY;

	points.forEach((point) => {
		minX = Math.min(minX, point.x);
		maxX = Math.max(maxX, point.x);
		minY = Math.min(minY, point.y);
		maxY = Math.max(maxY, point.y);
	});

	const width = maxX - minX;
	const height = maxY - minY;
	if (width < 34 || height < 34) {
		return false;
	}

	const ratio = width / Math.max(height, 1);
	if (ratio < 0.58 || ratio > 1.72) {
		return false;
	}

	const center = {
		x: minX + width * 0.5,
		y: minY + height * 0.5,
	};

	const radii = points.map((point) => distanceBetweenPoints(point, center));
	const meanRadius = radii.reduce((sum, radius) => sum + radius, 0) / radii.length;
	if (meanRadius < 16) {
		return false;
	}

	const averageDeviation = radii.reduce((sum, radius) => sum + Math.abs(radius - meanRadius), 0) / radii.length;
	if (averageDeviation / meanRadius > 0.34) {
		return false;
	}

	const closureDistance = distanceBetweenPoints(points[0], points[points.length - 1]);
	if (closureDistance > Math.max(22, meanRadius * 0.8)) {
		return false;
	}

	const quadrants = new Set();
	points.forEach((point) => {
		const horizontal = point.x >= center.x ? "r" : "l";
		const vertical = point.y >= center.y ? "b" : "t";
		quadrants.add(`${horizontal}${vertical}`);
	});

	return quadrants.size >= 4;
}

function isNearViewportEdge(event, margin = 28) {
	const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
	const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;

	return (
		event.clientX <= margin ||
		event.clientY <= margin ||
		event.clientX >= viewportWidth - margin ||
		event.clientY >= viewportHeight - margin
	);
}

function detectCardinalDirection(deltaX, deltaY, distance, minimumDistance = 24, dominanceRatio = 1.24) {
	if (distance < minimumDistance) {
		return null;
	}

	const absX = Math.abs(deltaX);
	const absY = Math.abs(deltaY);

	if (absX >= absY * dominanceRatio) {
		return deltaX > 0 ? "right" : "left";
	}

	if (absY >= absX * dominanceRatio) {
		return deltaY > 0 ? "down" : "up";
	}

	return null;
}

function detectSwipeDirection(deltaX, deltaY, distance, duration) {
	const minimumDistance = 88;
	const maximumDuration = 720;

	if (duration > maximumDuration) {
		return null;
	}

	return detectCardinalDirection(deltaX, deltaY, distance, minimumDistance, 1.24);
}

function swipeDestination(direction) {
	switch (direction) {
		case "left":
			return "/signal";
		case "right":
			return "/aza.php";
		case "up":
			return "/#str3m-quotidien";
		case "down":
			return "/";
		default:
			return null;
	}
}

function navigateToStr3mSurface() {
	if (window.location.pathname === "/") {
		const target = document.getElementById("str3m-quotidien");
		if (target) {
			target.scrollIntoView({ behavior: reducedMotion ? "auto" : "smooth", block: "start" });
			window.history.replaceState(null, "", "/#str3m-quotidien");
			return true;
		}
	}

	window.location.assign("/#str3m-quotidien");
	return true;
}

function navigateToCoreSurface() {
	const current = `${window.location.pathname}${window.location.hash}`;
	if (current === "/" || current === "/#" || current === "/#str3m-quotidien") {
		window.scrollTo({ top: 0, behavior: reducedMotion ? "auto" : "smooth" });
		window.history.replaceState(null, "", "/");
		return true;
	}

	window.location.assign("/");
	return true;
}

function navigateFromSwipe(direction) {
	const destination = swipeDestination(direction);
	if (!destination) {
		return false;
	}

	if (direction === "up") {
		return navigateToStr3mSurface();
	}

	if (direction === "down") {
		return navigateToCoreSurface();
	}

	const current = `${window.location.pathname}${window.location.hash}`;
	if (current !== destination) {
		window.location.assign(destination);
		return true;
	}

	return false;
}

function initStr3mArchipelago() {
	const container = document.querySelector("[data-str3m-archipelago]");
	if (!(container instanceof HTMLElement)) {
		return;
	}

	const scene = container.querySelector(".archipelago-scene");
	if (!(scene instanceof HTMLElement)) {
		return;
	}

	const wrappers = Array.from(scene.querySelectorAll(".archipelago-card-wrapper"))
		.filter((wrapper) => wrapper instanceof HTMLElement);
	const nodes = Array.from(scene.querySelectorAll(".archipelago-node"))
		.filter((node) => node instanceof HTMLElement);
	const hint = container.querySelector("[data-str3m-archipelago-hint]");
	const setHint = (copy) => {
		if (hint instanceof HTMLElement) {
			hint.textContent = copy;
		}
	};

	nodes.forEach((node) => {
		const x = Number(node.dataset.archipelagoX || 0);
		const y = Number(node.dataset.archipelagoY || 0);
		const z = Number(node.dataset.archipelagoZ || 0);
		node.style.transform = `translate3d(${x}px, ${y}px, ${z}px)`;
	});

	const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
	let rotX = 5;
	let rotY = 0;
	let posZ = -500;
	let targetRotX = 5;
	let targetRotY = 0;
	let targetPosZ = -500;
	let pointerId = null;
	let pointerType = "";
	let active = false;
	let armed = false;
	let longTouchTimer = 0;
	let startX = 0;
	let startY = 0;
	let lastX = 0;
	let lastY = 0;

	const clearLongTouchTimer = () => {
		if (!longTouchTimer) {
			return;
		}

		window.clearTimeout(longTouchTimer);
		longTouchTimer = 0;
	};

	const releasePointer = () => {
		if (pointerId !== null && container.hasPointerCapture(pointerId)) {
			container.releasePointerCapture(pointerId);
		}
	};

	const resetGesture = () => {
		clearLongTouchTimer();
		releasePointer();
		pointerId = null;
		pointerType = "";
		active = false;
		armed = false;
		container.classList.remove("is-navigating", "is-touch-arming");
		setHint(coarsePointer ? "Appui long puis glisse · relâche pour stabiliser" : "Glisser pour pivoter · molette pour avancer");
	};

	const activateGesture = () => {
		if (pointerId === null) {
			return;
		}

		active = true;
		armed = false;
		container.classList.remove("is-touch-arming");
		container.classList.add("is-navigating");
		container.setPointerCapture(pointerId);
		setHint("Navigation armée · gauche/droite pivote · haut/bas traverse");
	};

	const moveStr3m = (clientX, clientY) => {
		const deltaX = clientX - lastX;
		const deltaY = clientY - lastY;
		lastX = clientX;
		lastY = clientY;

		targetRotY += deltaX * 0.24;
		targetRotX -= deltaY * 0.12;
		targetPosZ += deltaY * 2.8;
		targetRotX = clamp(targetRotX, -25, 25);
		targetPosZ = clamp(targetPosZ, -4000, 800);
	};

	container.addEventListener("pointerdown", (event) => {
		if ((event.target instanceof Element) && event.target.closest("a, button, input, textarea, select")) {
			return;
		}

		resetGesture();
		pointerId = event.pointerId;
		pointerType = event.pointerType || "";
		startX = event.clientX;
		startY = event.clientY;
		lastX = event.clientX;
		lastY = event.clientY;

		if (pointerType === "touch") {
			armed = true;
			container.classList.add("is-touch-arming");
			setHint("Maintiens... puis glisse dans le courant");
			longTouchTimer = window.setTimeout(activateGesture, 340);
			return;
		}

		activateGesture();
	});

	container.addEventListener("pointermove", (event) => {
		if (pointerId === null || event.pointerId !== pointerId) {
			return;
		}

		const travel = Math.hypot(event.clientX - startX, event.clientY - startY);
		if (armed && travel > 12) {
			resetGesture();
			return;
		}

		if (!active) {
			return;
		}

		event.preventDefault();
		moveStr3m(event.clientX, event.clientY);
	});

	["pointerup", "pointercancel", "pointerleave"].forEach((eventName) => {
		container.addEventListener(eventName, (event) => {
			if (pointerId !== null && event.pointerId === pointerId) {
				resetGesture();
			}
		});
	});

	container.addEventListener("wheel", (event) => {
		event.preventDefault();
		targetPosZ += event.deltaY * 1.5;
		targetPosZ = clamp(targetPosZ, -4000, 800);
	}, { passive: false });

	const render = () => {
		rotX += (targetRotX - rotX) * 0.08;
		rotY += (targetRotY - rotY) * 0.08;
		posZ += (targetPosZ - posZ) * 0.08;
		scene.style.transform = `translateZ(${posZ}px) rotateX(${rotX}deg) rotateY(${rotY}deg)`;

		wrappers.forEach((wrapper) => {
			wrapper.style.transform = `translate(-50%, -50%) rotateY(${-rotY}deg) rotateX(${-rotX}deg)`;
		});

		window.requestAnimationFrame(render);
	};

	resetGesture();
	window.requestAnimationFrame(render);
}

function initStr3mParallax() {
	const str3mImage = document.querySelector(".str3m-image");
	const str3mFigure = document.querySelector(".str3m-figure");
	if (!(str3mImage instanceof HTMLElement) || !(str3mFigure instanceof HTMLElement)) {
		return;
	}

	let currentY = 0;
	let targetY = 0;

	const renderParallax = () => {
		const rect = str3mFigure.getBoundingClientRect();
		if (rect.top < window.innerHeight && rect.bottom > 0) {
			const centerOffset = (window.innerHeight / 2) - (rect.top + rect.height / 2);
			targetY = centerOffset * 0.15;
		}

		currentY += (targetY - currentY) * 0.08;
		str3mImage.style.transform = `translateY(${-currentY}px)`;
		window.requestAnimationFrame(renderParallax);
	};

	window.requestAnimationFrame(renderParallax);
}

function ensureTorusTouchHint() {
	if (!document.body) {
		return null;
	}

	const existing = document.querySelector("[data-torus-touch-hint]");
	if (existing instanceof HTMLDivElement) {
		return existing;
	}

	const hint = document.createElement("div");
	hint.className = "torus-touch-hint";
	hint.dataset.torusTouchHint = "";
	hint.setAttribute("aria-hidden", "true");
	document.body.appendChild(hint);
	return hint;
}

function initTorusCloud(canvas) {
	const context = canvas.getContext("2d");
	if (!context) {
		return;
	}

	const isPassiveCanvas = canvas.dataset.torusPassive === "1";
	const touchHint = isPassiveCanvas ? null : ensureTorusTouchHint();

	const state = {
		width: 0,
		height: 0,
		devicePixelRatio: 1,
		autoScale: 1,
		autoLiftY: 0,
		animationFrame: 0,
		points: [],
		pointerId: null,
		lastX: 0,
		lastY: 0,
		dragging: false,
		yaw: 0,
		pitch: 0.92,
		roll: 0.1,
		panX: 0,
		panY: 0,
		zoom: 11,
		velocityYaw: 0,
		velocityPitch: 0,
		velocityRoll: 0,
		velocityPanX: 0,
		velocityPanY: 0,
		velocityZoom: 0,
		keys: new Set(),
		pointerStartX: 0,
		pointerStartY: 0,
		pointerStartedAt: 0,
		pointerType: "",
		pointerNearEdge: false,
		gesturePoints: [],
		gestureDistance: 0,
		longPressTimer: 0,
		longPressActive: false,
		longPressDirection: "",
		longPressEligible: false,
	};

	const torusScale = 11;
	const ringCount = 96;
	const tubeCount = 42;
	const majorRadius = 1.9 * torusScale;
	const minorRadius = 0.78 * torusScale;
	const zoomMin = 6.5;
	const zoomMax = 17.5;

	if (isPassiveCanvas) {
		state.pitch = 0.82;
		state.roll = 0.04;
		state.zoom = 10.4;
	}

	for (let ringIndex = 0; ringIndex < ringCount; ringIndex += 1) {
		for (let tubeIndex = 0; tubeIndex < tubeCount; tubeIndex += 1) {
			const theta = (ringIndex / ringCount) * Math.PI * 2;
			const phi = (tubeIndex / tubeCount) * Math.PI * 2;
			const jitter = (Math.sin(ringIndex * 3.11 + tubeIndex * 1.73) + 1) * 0.5;
			const radiusDrift = minorRadius + (jitter - 0.5) * (0.16 * torusScale);

			state.points.push({
				theta,
				phi,
				radiusDrift,
				phase: (ringIndex * 0.19 + tubeIndex * 0.13) % (Math.PI * 2),
				density: 0.82 + jitter * 0.44,
			});
		}
	}

	canvas.dataset.torusScale = String(torusScale);

	function resize() {
		const rect = canvas.getBoundingClientRect();
		state.devicePixelRatio = Math.max(1, Math.min(window.devicePixelRatio || 1, 2));
		state.width = Math.max(1, Math.round(rect.width));
		state.height = Math.max(1, Math.round(rect.height));
		const viewportRatio = state.width / Math.max(state.height, 1);
		const isPortraitViewport = state.height > state.width * 1.04;
		state.autoScale = isPortraitViewport
			? clampNumber(0.74 + viewportRatio * 0.26, 0.78, 0.92)
			: clampNumber(0.94 + viewportRatio * 0.05, 0.94, 1.04);
		state.autoLiftY = isPortraitViewport ? Math.round(state.height * -0.035) : 0;
		canvas.width = Math.round(state.width * state.devicePixelRatio);
		canvas.height = Math.round(state.height * state.devicePixelRatio);
		context.setTransform(state.devicePixelRatio, 0, 0, state.devicePixelRatio, 0, 0);
		canvas.style.setProperty("--torus-autoscale", state.autoScale.toFixed(3));
		canvas.style.setProperty("--torus-center-lift", `${state.autoLiftY}px`);
		updateTouchHint();
	}

	function clamp(value, min, max) {
		return Math.min(max, Math.max(min, value));
	}

	function normalizeKey(key) {
		return key.length === 1 ? key.toLowerCase() : key;
	}

	function recordGesturePoint(clientX, clientY) {
		const point = { x: clientX, y: clientY };
		const previous = state.gesturePoints[state.gesturePoints.length - 1];
		if (previous) {
			const distance = distanceBetweenPoints(previous, point);
			if (distance < 6) {
				return;
			}

			state.gestureDistance += distance;
		}

		state.gesturePoints.push(point);
	}

	function getCanvasPoint(event) {
		const rect = canvas.getBoundingClientRect();
		return {
			x: event.clientX - rect.left,
			y: event.clientY - rect.top,
		};
	}

	function setHintState(message = "", isActive = false) {
		if (!(touchHint instanceof HTMLDivElement)) {
			return;
		}

		touchHint.textContent = message;
		touchHint.classList.toggle("is-visible", Boolean(message));
		touchHint.classList.toggle("is-active", Boolean(message) && isActive);
	}

	function updateTouchHint(direction = "") {
		if (isPassiveCanvas) {
			return;
		}

		const shouldShow = coarsePointer && window.innerWidth <= 820;
		if (!shouldShow) {
			setHintState("", false);
			return;
		}

		if (state.longPressActive) {
			const messageByDirection = {
				left: "Relâche pour Signal",
				right: "Relâche pour aZa",
				up: "Relâche pour Str3m",
				down: "Relâche pour le noyau",
			};
			setHintState(messageByDirection[direction] || "Glisse : ← Signal · ↑ Str3m · → aZa · ↓ Noyau", true);
			return;
		}

		setHintState("Appui long + glisse : Signal · Str3m · aZa · Noyau", false);
	}

	function clearLongPressTimer() {
		if (!state.longPressTimer) {
			return;
		}

		window.clearTimeout(state.longPressTimer);
		state.longPressTimer = 0;
	}

	function setLongPressDirection(direction = "") {
		state.longPressDirection = direction;
		if (direction) {
			canvas.dataset.navDirection = direction;
		} else {
			delete canvas.dataset.navDirection;
		}
		updateTouchHint(direction);
	}

	function resetLongPressState() {
		clearLongPressTimer();
		state.longPressActive = false;
		state.longPressEligible = false;
		setLongPressDirection("");
		canvas.classList.remove("is-nav-armed");
		document.body.classList.remove("torus-nav-active");
		updateTouchHint();
	}

	function queueLongPress(pointerId) {
		clearLongPressTimer();
		if (!state.longPressEligible) {
			return;
		}

		state.longPressTimer = window.setTimeout(() => {
			if (!state.dragging || state.pointerId !== pointerId) {
				return;
			}

			state.longPressActive = true;
			state.velocityYaw *= 0.4;
			state.velocityPitch *= 0.4;
			state.velocityPanX *= 0.2;
			state.velocityPanY *= 0.2;
			canvas.classList.add("is-nav-armed");
			document.body.classList.add("torus-nav-active");
			canvas.setPointerCapture(pointerId);
			updateTouchHint();
		}, 360);
	}

	function triggerSecretAccess() {
		canvas.classList.add("is-secret-open");
		const guidePath = "/0wlslw0";
		window.setTimeout(() => {
			canvas.classList.remove("is-secret-open");
			if (window.location.pathname === guidePath) {
				toggleInversionAndGuideVoiceMute();
				return;
			}

			window.location.assign(guidePath);
		}, 900);
	}

	function shouldToggleFromCenterClick(event) {
		const travel = Math.hypot(event.clientX - state.pointerStartX, event.clientY - state.pointerStartY);
		if (travel > 16) {
			return false;
		}

		const point = getCanvasPoint(event);
		const centerX = state.width * 0.5;
		const centerY = state.height * 0.5;
		const distanceFromCenter = Math.hypot(point.x - centerX, point.y - centerY);
		return distanceFromCenter <= Math.min(state.width, state.height) * 0.14;
	}

	function shouldToggleFromSecretGesture() {
		return isSecretCircleGesture(state.gesturePoints, state.gestureDistance);
	}

	function shouldTriggerSwipe(event) {
		if (state.pointerType !== "touch" || state.pointerNearEdge) {
			return null;
		}

		const deltaX = event.clientX - state.pointerStartX;
		const deltaY = event.clientY - state.pointerStartY;
		const distance = Math.hypot(deltaX, deltaY);
		const duration = Math.max(0, event.timeStamp - state.pointerStartedAt);
		return detectSwipeDirection(deltaX, deltaY, distance, duration);
	}

	function applyKeyboardNavigation() {
		if (!state.keys.size) {
			return;
		}

		if (state.keys.has("ArrowLeft") || state.keys.has("a")) {
			state.velocityYaw -= 0.0028;
		}

		if (state.keys.has("ArrowRight") || state.keys.has("d")) {
			state.velocityYaw += 0.0028;
		}

		if (state.keys.has("ArrowUp") || state.keys.has("w")) {
			state.velocityPitch -= 0.0022;
		}

		if (state.keys.has("ArrowDown") || state.keys.has("s")) {
			state.velocityPitch += 0.0022;
		}

		if (state.keys.has("q")) {
			state.velocityPanX -= 0.028;
		}

		if (state.keys.has("e")) {
			state.velocityPanX += 0.028;
		}

		if (state.keys.has("z") || state.keys.has("+")) {
			state.velocityZoom += 0.06;
		}

		if (state.keys.has("x") || state.keys.has("-") || state.keys.has("_")) {
			state.velocityZoom -= 0.06;
		}
	}

	function stepNavigation() {
		applyKeyboardNavigation();

		if (!state.dragging && !reducedMotion) {
			state.velocityYaw += 0.00024;
			state.velocityRoll += 0.00004;
		}

		state.yaw += state.velocityYaw;
		state.pitch = clamp(state.pitch + state.velocityPitch, -1.35, 1.35);
		state.roll = clamp(state.roll + state.velocityRoll, -0.9, 0.9);
		state.panX = clamp(state.panX + state.velocityPanX, -9.5, 9.5);
		state.panY = clamp(state.panY + state.velocityPanY, -9.5, 9.5);
		state.zoom = clamp(state.zoom + state.velocityZoom, zoomMin, zoomMax);

		state.velocityYaw *= 0.92;
		state.velocityPitch *= 0.9;
		state.velocityRoll *= 0.9;
		state.velocityPanX *= 0.84;
		state.velocityPanY *= 0.84;
		state.velocityZoom *= 0.86;
	}

	function drawFrame(time = 0) {
		const width = state.width;
		const height = state.height;
		if (!width || !height) {
			return;
		}

		const profile = resolveTorusProfile(canvas);
		stepNavigation();

		const centerX = width * 0.5 + state.panX * (width * 0.018);
		const centerY = height * 0.5 + state.autoLiftY + state.panY * (height * 0.018);
		const camera = 39 - state.zoom * 1.12;
		const scale = Math.min(width, height) * (0.7 + state.zoom * 0.078) * state.autoScale;
		const spinY = state.yaw + time * 0.00006;
		const spinX = state.pitch + Math.sin(time * 0.00012) * 0.05;
		const spinZ = state.roll + Math.cos(time * 0.00009) * 0.03;

		context.clearRect(0, 0, width, height);

		const rendered = state.points.map((point) => {
			const ripple = Math.sin(time * 0.0012 * profile.motion + point.phase) * (0.028 * torusScale);
			const tide =
				Math.sin(time * 0.00085 * profile.motion + point.theta * 2.4 + point.phase) * (0.054 * torusScale * profile.waveStrength) +
				Math.cos(time * 0.00115 * profile.motion + point.phi * 2.1 + point.phase * 0.8) * (0.032 * torusScale * profile.waveStrength);
			const radial = point.radiusDrift + ripple + tide * 0.34;
			const localX = (majorRadius + radial * Math.cos(point.phi)) * Math.cos(point.theta);
			const localY = (majorRadius + radial * Math.cos(point.phi)) * Math.sin(point.theta);
			const localZ = radial * Math.sin(point.phi) + tide;

			const rotateYx = localX * Math.cos(spinY) + localZ * Math.sin(spinY);
			const rotateYz = -localX * Math.sin(spinY) + localZ * Math.cos(spinY);
			const rotateXy = localY * Math.cos(spinX) - rotateYz * Math.sin(spinX);
			const rotateXz = localY * Math.sin(spinX) + rotateYz * Math.cos(spinX);
			const finalX = rotateYx * Math.cos(spinZ) - rotateXy * Math.sin(spinZ);
			const finalY = rotateYx * Math.sin(spinZ) + rotateXy * Math.cos(spinZ);
			const depth = rotateXz + camera;
			const perspective = scale / Math.max(depth, 3.2);
			const depthFactor = clamp((48 - depth) / 34, 0, 1);
			const shimmer = Math.sin(time * 0.0018 * profile.motion + point.phase * 2.2 + depth * 0.1);

			return {
				x: centerX + finalX * perspective,
				y: centerY + finalY * perspective,
				alpha: clamp(0.08 + depthFactor * (0.72 + profile.pulseStrength * 0.18) * point.density + shimmer * 0.06, 0.05, 0.94),
				radius: clamp(0.24 + depthFactor * (4.4 + profile.pulseStrength * 1.1) * point.density, 0.2, 6.2),
				depth,
				mix: clamp(0.18 + depthFactor * 0.6 + shimmer * 0.14, 0, 1),
				signalSeed: point.phase + point.theta + point.phi,
			};
		});

		rendered.sort((left, right) => right.depth - left.depth);

		rendered.forEach((point) => {
			let color = mixRgb(profile.primary, profile.secondary, point.mix);
			if (profile.signalMode && Array.isArray(profile.signalColors)) {
				const trigger = Math.sin(time * 0.0024 + point.signalSeed * 3.6 + point.depth * 0.08);
				if (trigger > 0.74) {
					const index = Math.abs(Math.floor((point.signalSeed * 10 + time * 0.004) % profile.signalColors.length));
					color = profile.signalColors[index] || color;
				}
			}

			context.beginPath();
			context.fillStyle = `rgba(${color[0]}, ${color[1]}, ${color[2]}, ${point.alpha})`;
			context.arc(point.x, point.y, point.radius, 0, Math.PI * 2);
			context.fill();
		});
	}

	function stop() {
		if (!state.animationFrame) {
			return;
		}

		window.cancelAnimationFrame(state.animationFrame);
		state.animationFrame = 0;
	}

	function tick(time) {
		drawFrame(time);
		state.animationFrame = window.requestAnimationFrame(tick);
	}

	function start() {
		stop();
		if (document.hidden) {
			drawFrame(0);
			return;
		}

		state.animationFrame = window.requestAnimationFrame(tick);
	}

	function refreshStaticFrame() {
		if (reducedMotion && !state.animationFrame) {
			drawFrame(0);
		}
	}

	function releasePointer(event) {
		if (event && state.pointerId !== null && canvas.hasPointerCapture(state.pointerId)) {
			canvas.releasePointerCapture(state.pointerId);
		}

		clearLongPressTimer();
		state.pointerId = null;
		state.pointerType = "";
		state.pointerStartedAt = 0;
		state.pointerNearEdge = false;
		state.longPressEligible = false;
		state.dragging = false;
		canvas.classList.remove("is-dragging");
		refreshStaticFrame();
	}

	if (!isPassiveCanvas) {
		canvas.addEventListener("pointerdown", (event) => {
			resetLongPressState();
			state.pointerId = event.pointerId;
			state.pointerType = event.pointerType || "";
			state.lastX = event.clientX;
			state.lastY = event.clientY;
			state.pointerStartX = event.clientX;
			state.pointerStartY = event.clientY;
			state.pointerStartedAt = event.timeStamp;
			state.pointerNearEdge = isNearViewportEdge(event);
			state.gesturePoints = [];
			state.gestureDistance = 0;
			state.longPressEligible = state.pointerType === "touch" && !state.pointerNearEdge;
			state.longPressActive = false;
			setLongPressDirection("");
			recordGesturePoint(event.clientX, event.clientY);
			state.dragging = true;
			canvas.focus({ preventScroll: true });
			if (state.pointerType !== "touch") {
				canvas.classList.add("is-dragging");
				canvas.setPointerCapture(event.pointerId);
			}
			queueLongPress(event.pointerId);
		});

		canvas.addEventListener("pointermove", (event) => {
			if (!state.dragging || state.pointerId !== event.pointerId) {
				return;
			}

			const travelX = event.clientX - state.pointerStartX;
			const travelY = event.clientY - state.pointerStartY;
			const travelDistance = Math.hypot(travelX, travelY);
			if (!state.longPressActive && travelDistance > 14) {
				clearLongPressTimer();
				if (state.pointerType === "touch") {
					releasePointer(event);
					return;
				}
			}

			if (state.longPressActive) {
				event.preventDefault();
				const direction = detectCardinalDirection(travelX, travelY, travelDistance, 18, 1.08);
				setLongPressDirection(direction || "");
				return;
			}

			if (state.pointerType === "touch") {
				return;
			}

			const deltaX = event.clientX - state.lastX;
			const deltaY = event.clientY - state.lastY;
			state.lastX = event.clientX;
			state.lastY = event.clientY;
			recordGesturePoint(event.clientX, event.clientY);
			state.velocityYaw += deltaX * 0.00058;
			state.velocityPitch += deltaY * 0.00042;
			state.velocityPanX += deltaX * 0.0022;
			state.velocityPanY += deltaY * 0.0016;
			refreshStaticFrame();
		});

		canvas.addEventListener("pointerup", (event) => {
			recordGesturePoint(event.clientX, event.clientY);
			if (state.longPressActive) {
				const deltaX = event.clientX - state.pointerStartX;
				const deltaY = event.clientY - state.pointerStartY;
				const direction = state.longPressDirection || detectCardinalDirection(deltaX, deltaY, Math.hypot(deltaX, deltaY), 18, 1.08);
				resetLongPressState();
				releasePointer(event);

				if (direction) {
					navigateFromSwipe(direction);
				}
				return;
			}

			const swipeDirection = shouldTriggerSwipe(event);

			if (swipeDirection) {
				navigateFromSwipe(swipeDirection);
			} else if (shouldToggleFromCenterClick(event) || shouldToggleFromSecretGesture()) {
				triggerSecretAccess();
			}

			releasePointer(event);
		});
		canvas.addEventListener("pointercancel", (event) => {
			resetLongPressState();
			releasePointer(event);
		});
		canvas.addEventListener("pointerleave", () => {
			if (!state.dragging) {
				canvas.classList.remove("is-dragging");
			}
		});

		canvas.addEventListener(
			"wheel",
			(event) => {
				event.preventDefault();
				state.velocityZoom += event.deltaY > 0 ? -0.26 : 0.26;
				refreshStaticFrame();
			},
			{ passive: false }
		);

		canvas.addEventListener("keydown", (event) => {
			const key = normalizeKey(event.key);
			if (![
				"ArrowLeft",
				"ArrowRight",
				"ArrowUp",
				"ArrowDown",
				"a",
				"d",
				"w",
				"s",
				"q",
				"e",
				"z",
				"x",
				"+",
				"-",
				"_",
			].includes(key)) {
				return;
			}

			event.preventDefault();
			state.keys.add(key);
			refreshStaticFrame();
		});

		canvas.addEventListener("keyup", (event) => {
			state.keys.delete(normalizeKey(event.key));
			refreshStaticFrame();
		});

		canvas.addEventListener("blur", () => {
			state.keys.clear();
			resetLongPressState();
			releasePointer();
		});
	}

	resize();
	updateTouchHint();
	if (reducedMotion) {
		drawFrame(0);
	} else {
		start();
	}

	window.addEventListener("resize", () => {
		resize();
		refreshStaticFrame();
	});

	document.addEventListener("visibilitychange", () => {
		if (document.hidden) {
			stop();
			drawFrame(0);
			return;
		}

		if (reducedMotion) {
			drawFrame(0);
			return;
		}

		start();
	});
}

torusCanvases.forEach((canvas) => initTorusCloud(canvas));

if ("IntersectionObserver" in window && reveals.length && !reducedMotion) {
	const observer = new IntersectionObserver(
		(entries) => {
			entries.forEach((entry) => {
				if (entry.isIntersecting) {
					entry.target.classList.add("on");
					observer.unobserve(entry.target);
				}
			});
		},
		{ threshold: 0.08 }
	);

	// Mark elements AFTER setting up the observer so they default to visible
	// if the observer never fires (avoids permanent black/empty screen).
	reveals.forEach((element) => {
		element.classList.add("will-animate");
		observer.observe(element);
	});

	// Safety net: reveal anything still hidden after 1.5 s (slow load, hidden overflow, etc.).
	window.setTimeout(() => {
		reveals.forEach((el) => el.classList.add("on"));
	}, 1500);
} else {
	reveals.forEach((element) => element.classList.add("on"));
}

function slugify(value) {
	const trimmed = value.trim();
	if (!trimmed) {
		return "terre";
	}

	const normalized = typeof trimmed.normalize === "function" ? trimmed.normalize("NFD") : trimmed;

	return (
		normalized
			.replace(/[\u0300-\u036f]/g, "")
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, "-")
			.replace(/^-+|-+$/g, "")
			.slice(0, 42) || "terre"
	);
}

function formatClock(timezone) {
	const now = new Date();

	return {
		time: new Intl.DateTimeFormat("fr-FR", {
			timeZone: timezone,
			hour: "2-digit",
			minute: "2-digit",
			second: "2-digit",
			hour12: false,
		}).format(now),
		date: new Intl.DateTimeFormat("fr-FR", {
			timeZone: timezone,
			weekday: "long",
			day: "2-digit",
			month: "long",
		}).format(now),
	};
}

function isValidTimezone(timezone) {
	try {
		formatClock(timezone);
		return true;
	} catch {
		return false;
	}
}

function renderClock(card) {
	if (!card) {
		return;
	}

	const timezone = card.dataset.timezone || DEFAULT_TIMEZONE;
	const label = card.querySelector("[data-clock-label]");
	const time = card.querySelector("[data-clock-time]");
	const date = card.querySelector("[data-clock-date]");

	try {
		const formatted = formatClock(timezone);

		if (label) {
			label.textContent = `Fuseau : ${timezone}`;
		}

		if (time) {
			time.textContent = formatted.time;
		}

		if (date) {
			date.textContent = formatted.date;
		}
	} catch {
		if (label) {
			label.textContent = "Fuseau : invalide";
		}

		if (time) {
			time.textContent = "--:--:--";
		}

		if (date) {
			date.textContent = "corrige le fuseau";
		}
	}
}

function refreshClocks() {
	document.querySelectorAll("[data-live-clock]").forEach((card) => renderClock(card));
}

function selectedSignupProgramInput() {
	return signupProgramInputs.find((input) => input.checked) || signupProgramInputs[0] || null;
}

function syncSignupProgramCards(activeInput) {
	signupProgramCards.forEach((card) => {
		const input = card.querySelector("[data-signup-program-input]");
		card.classList.toggle("is-selected", input === activeInput);
	});
}

function syncSignupSpectrum(resetLambda = false) {
	const activeInput = selectedSignupProgramInput();
	if (!activeInput) {
		return null;
	}

	const programKey = activeInput.value || "culbu1on";
	const programLabel = activeInput.dataset.programLabel || programKey;
	const programTone = activeInput.dataset.programTone || "";
	const lambdaMin = Number.parseInt(activeInput.dataset.lambdaMin || "440", 10);
	const lambdaMax = Number.parseInt(activeInput.dataset.lambdaMax || "560", 10);
	const defaultLambda = Number.parseInt(activeInput.dataset.lambdaDefault || String(Math.round((lambdaMin + lambdaMax) / 2)), 10);

	syncSignupProgramCards(activeInput);

	let lambda = defaultLambda;
	if (signupLambdaInput instanceof HTMLInputElement) {
		signupLambdaInput.min = String(lambdaMin);
		signupLambdaInput.max = String(lambdaMax);
		const currentLambda = Number.parseInt(signupLambdaInput.value || "", 10);
		const shouldReset = resetLambda || !Number.isFinite(currentLambda) || currentLambda < lambdaMin || currentLambda > lambdaMax;
		lambda = shouldReset ? defaultLambda : currentLambda;
		lambda = clampNumber(lambda, lambdaMin, lambdaMax);
		signupLambdaInput.value = String(lambda);
	}

	signupProgramLabelOutputs.forEach((node) => {
		node.textContent = programLabel;
	});

	signupProgramToneOutputs.forEach((node) => {
		node.textContent = programTone;
	});

	signupLambdaValueOutputs.forEach((node) => {
		node.textContent = String(lambda);
	});

	if (signupLambdaRangeOutput) {
		signupLambdaRangeOutput.textContent = `${lambdaMin}–${lambdaMax} nm`;
	}

	document.body.dataset.landProgram = programKey;
	document.body.dataset.landTone = programTone;
	document.body.dataset.landLambda = String(lambda);
	torusCanvases.forEach((canvas) => {
		canvas.dataset.landType = programKey;
		canvas.dataset.lambda = String(lambda);
	});
	emitLandSignatureChange();

	return { programKey, programLabel, programTone, lambda };
}

function renderSignupPreview(options = {}) {
	if (!previewShell) {
		return;
	}

	const { resetLambda = false } = options;
	const slug = slugify(usernameInput?.value || "");
	const timezone = timezoneInput?.value.trim() || DEFAULT_TIMEZONE;
	const originBase = previewShell.dataset.originBase || window.location.origin;
	const slugOutput = previewShell.querySelector("[data-slug-output]");
	const emailOutput = previewShell.querySelector("[data-email-output]");
	const linkOutput = previewShell.querySelector("[data-land-link-output]");
	const timezoneOutput = previewShell.querySelector("[data-preview-timezone]");
	const timezoneIsValid = isValidTimezone(timezone);

	if (slugOutput) {
		slugOutput.textContent = slug;
	}

	if (emailOutput) {
		emailOutput.textContent = `${slug}@o.local`;
	}

	if (linkOutput) {
		linkOutput.textContent = `${originBase}/land.php?u=${slug}`;
	}

	if (timezoneOutput) {
		timezoneOutput.textContent = timezone;
	}

	if (timezoneStatus) {
		timezoneStatus.classList.toggle("is-warning", !timezoneIsValid);
		timezoneStatus.classList.toggle("is-success", timezoneIsValid);
		timezoneStatus.textContent = timezoneIsValid
			? "Fuseau valide. L’heure affichée ci-dessous sera cohérente avec cette terre."
			: "Ce fuseau n’est pas reconnu. Essaie un format comme Europe/Paris ou utilise la détection locale.";
	}

	if (timezoneInput) {
		timezoneInput.classList.toggle("is-invalid", !timezoneIsValid);
	}

	timezoneChips.forEach((chip) => {
		chip.classList.toggle("is-active", chip.dataset.timezoneChip === timezone);
	});

	if (previewClock) {
		previewClock.dataset.timezone = timezone;
		renderClock(previewClock);
	}

	syncSignupSpectrum(resetLambda);
}

if (timezoneInput && previewClock) {
	const guessedTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

	if (!timezoneInput.value.trim() && guessedTimezone) {
		timezoneInput.value = guessedTimezone;
		previewClock.dataset.timezone = guessedTimezone;
	}

	timezoneInput.addEventListener("input", renderSignupPreview);
}

if (usernameInput) {
	usernameInput.addEventListener("input", renderSignupPreview);
}

signupProgramInputs.forEach((input) => {
	input.addEventListener("change", () => {
		renderSignupPreview({ resetLambda: true });
	});
});

if (signupLambdaInput instanceof HTMLInputElement) {
	signupLambdaInput.addEventListener("input", () => {
		renderSignupPreview();
	});
}

if (useLocalTimezoneButton && timezoneInput) {
	useLocalTimezoneButton.addEventListener("click", () => {
		const guessedTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
		if (!guessedTimezone) {
			return;
		}

		timezoneInput.value = guessedTimezone;
		renderSignupPreview();
	});
}

timezoneChips.forEach((chip) => {
	chip.addEventListener("click", () => {
		if (!timezoneInput) {
			return;
		}

		timezoneInput.value = chip.dataset.timezoneChip || DEFAULT_TIMEZONE;
		renderSignupPreview();
	});
});

renderSignupPreview();
refreshClocks();
window.setInterval(refreshClocks, 1000);

document.addEventListener("keydown", (event) => {
	if (event.key.toLowerCase() !== "i") {
		return;
	}

	const target = event.target;
	if (
		target instanceof HTMLElement
		&& (["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName) || target.isContentEditable)
	) {
		return;
	}

	toggleInversionAndGuideVoiceMute();
});

document.addEventListener("dblclick", (event) => {
	const target = event.target;
	if (!(target instanceof Element) || !canToggleInversionFromTarget(target)) {
		return;
	}

	if (
		target === document.body ||
		target.classList.contains("noise") ||
		target.classList.contains("aurora") ||
		target === document.documentElement
	) {
		toggleInversionAndGuideVoiceMute();
	}
});

if (coarsePointer) {
	let inversionLongTouchTimer = 0;
	let inversionLongTouchPointerId = null;
	let inversionLongTouchStartX = 0;
	let inversionLongTouchStartY = 0;

	const clearInversionLongTouch = () => {
		if (inversionLongTouchTimer) {
			window.clearTimeout(inversionLongTouchTimer);
			inversionLongTouchTimer = 0;
		}
		inversionLongTouchPointerId = null;
	};

	document.addEventListener("pointerdown", (event) => {
		if ((event.pointerType || "") !== "touch" || !canToggleInversionFromTarget(event.target)) {
			return;
		}

		inversionLongTouchStartX = event.clientX;
		inversionLongTouchStartY = event.clientY;
		clearInversionLongTouch();
		inversionLongTouchPointerId = event.pointerId;
		inversionLongTouchTimer = window.setTimeout(() => {
			toggleInversionAndGuideVoiceMute();
			clearInversionLongTouch();
		}, 580);
	}, true);

	document.addEventListener("pointermove", (event) => {
		if (inversionLongTouchPointerId === null || event.pointerId !== inversionLongTouchPointerId) {
			return;
		}

		const travel = Math.hypot(
			event.clientX - inversionLongTouchStartX,
			event.clientY - inversionLongTouchStartY
		);
		if (travel > 16) {
			clearInversionLongTouch();
		}
	}, true);

	["pointerup", "pointercancel", "pointerleave"].forEach((eventName) => {
		document.addEventListener(eventName, (event) => {
			if (inversionLongTouchPointerId !== null && event.pointerId === inversionLongTouchPointerId) {
				clearInversionLongTouch();
			}
		}, true);
	});

	window.addEventListener("scroll", clearInversionLongTouch, true);
}

if (bootline) {
	const fullText = bootline.textContent;

	if (reducedMotion) {
		bootline.textContent = fullText;
	} else {
		let index = 0;
		bootline.textContent = "";

		const typer = window.setInterval(() => {
			bootline.textContent += fullText[index] ?? "";
			index += 1;

			if (index >= fullText.length) {
				window.clearInterval(typer);
			}
		}, 28);
	}
}



copyButtons.forEach((button) => {
	button.addEventListener("click", async () => {
		const text = button.dataset.copyLink;
		if (!text) {
			return;
		}

		try {
			await navigator.clipboard.writeText(text);
			button.textContent = "Lien copie";
		} catch {
			button.textContent = "Copie manuelle";
		}

		window.setTimeout(() => {
			button.textContent = "Copier l'adresse";
		}, 1800);
	});
});

const SPECTRAL_TUNER_KEY = "o-spectral-tuner-v1";
const SPECTRAL_TUNER_TTL = 24 * 60 * 60 * 1000;
const spectralModes = [
	{
		index: 0,
		name: "brume",
		mood: "brume",
		lambda: 432,
		copy: "Brûme basse, presque silencieuse. Idéal si tu veux rester poreux sans trop de contraste.",
	},
	{
		index: 1,
		name: "écume",
		mood: "écume",
		lambda: 486,
		copy: "Écume mobile, attentive, en bord de phrase. Tu captes sans encore trancher.",
	},
	{
		index: 2,
		name: "clair",
		mood: "clair",
		lambda: 548,
		copy: "Clair stable, net mais encore doux. Le bon axe quand tu veux traverser sans bruit.",
	},
	{
		index: 3,
		name: "braise",
		mood: "braise",
		lambda: 612,
		copy: "Braise active, plus franche, prête à écrire ou décider. Le flux commence à chauffer.",
	},
	{
		index: 4,
		name: "nuit chaude",
		mood: "nuit chaude",
		lambda: 684,
		copy: "Nuit chaude, dense, presque infra. À choisir quand le monde te traverse déjà fort.",
	},
];

function getSpectralModeByIndex(index) {
	const numericIndex = clampNumber(Number.parseInt(index, 10), 0, spectralModes.length - 1);
	return spectralModes[numericIndex] || spectralModes[2];
}

function readSpectralTunerState() {
	try {
		const raw = window.localStorage.getItem(SPECTRAL_TUNER_KEY);
		if (!raw) {
			return null;
		}

		const parsed = JSON.parse(raw);
		if (!parsed || typeof parsed !== "object") {
			return null;
		}

		const expiresAt = Number(parsed.expiresAt || 0);
		if (!Number.isFinite(expiresAt) || expiresAt <= Date.now()) {
			window.localStorage.removeItem(SPECTRAL_TUNER_KEY);
			return null;
		}

		const mode = getSpectralModeByIndex(parsed.index ?? 2);
		return {
			...mode,
			expiresAt,
		};
	} catch {
		return null;
	}
}

function writeSpectralTunerState(mode) {
	if (!mode) {
		return;
	}

	try {
		window.localStorage.setItem(
			SPECTRAL_TUNER_KEY,
			JSON.stringify({
				index: mode.index,
				expiresAt: Date.now() + SPECTRAL_TUNER_TTL,
			})
		);
	} catch {
		// Ignore storage failures.
	}
}

function clearSpectralTunerState() {
	try {
		window.localStorage.removeItem(SPECTRAL_TUNER_KEY);
	} catch {
		// Ignore storage failures.
	}
}

function formatSpectralTimeLeft(expiresAt) {
	const remaining = Math.max(0, expiresAt - Date.now());
	const totalMinutes = Math.round(remaining / 60000);
	const hours = Math.floor(totalMinutes / 60);
	const minutes = totalMinutes % 60;

	if (hours <= 0) {
		return `${minutes} min`;
	}

	if (minutes === 0) {
		return `${hours} h`;
	}

	return `${hours} h ${minutes.toString().padStart(2, "0")}`;
}

function initSpectralTuner() {
	const root = document.querySelector("[data-spectral-tuner]");
	if (!(root instanceof HTMLElement)) {
		return;
	}

	const range = root.querySelector("[data-spectral-range]");
	const saveButton = root.querySelector("[data-spectral-save]");
	const resetButton = root.querySelector("[data-spectral-reset]");
	const lambdaNodes = Array.from(document.querySelectorAll("[data-spectral-lambda]"));
	const modeNameNodes = Array.from(document.querySelectorAll("[data-spectral-mode-name]"));
	const moodLabelNodes = Array.from(document.querySelectorAll("[data-spectral-mood-label]"));
	const copyNode = root.querySelector("[data-spectral-copy]");
	const expiryNode = root.querySelector("[data-spectral-expiry]");
	const torusCanvas = document.getElementById("torus-ambient");
	const defaultLambda = Number.parseInt(root.dataset.defaultLambda || document.body.dataset.landLambda || "548", 10) || 548;
	const defaultMood = root.dataset.defaultMood || "calm";
	const fallbackMode = spectralModes.reduce((closest, mode) => {
		return Math.abs(mode.lambda - defaultLambda) < Math.abs(closest.lambda - defaultLambda) ? mode : closest;
	}, spectralModes[2]);
	const activeStored = readSpectralTunerState();
	let currentMode = activeStored || fallbackMode;
	let expiryTimer = 0;

	if (!(range instanceof HTMLInputElement) || !(saveButton instanceof HTMLElement) || !(resetButton instanceof HTMLElement)) {
		return;
	}

	function applyMode(mode, options = {}) {
		currentMode = mode;
		range.value = String(mode.index);
		lambdaNodes.forEach((node) => {
			node.textContent = String(mode.lambda);
		});
		modeNameNodes.forEach((node) => {
			node.textContent = mode.name;
		});
		moodLabelNodes.forEach((node) => {
			node.textContent = options.saved ? `${mode.mood} · 24h` : mode.mood;
		});
		if (copyNode instanceof HTMLElement) {
			copyNode.textContent = options.saved
				? `${mode.copy} Réglage validé ici pour les prochaines 24h.`
				: mode.copy;
		}
		if (expiryNode instanceof HTMLElement) {
			expiryNode.textContent = options.expiresAt
				? `tenu ${formatSpectralTimeLeft(options.expiresAt)}`
				: "mode instantané";
		}
		document.body.dataset.landLambda = String(mode.lambda);
		if (torusCanvas instanceof HTMLCanvasElement) {
			torusCanvas.dataset.lambda = String(mode.lambda);
		}
		emitLandSignatureChange();
	}

	function syncExpiryBadge() {
		const persisted = readSpectralTunerState();
		if (!persisted) {
			window.clearInterval(expiryTimer);
			expiryTimer = 0;
			applyMode(getSpectralModeByIndex(range.value), { saved: false });
			return;
		}

		applyMode(persisted, { saved: true, expiresAt: persisted.expiresAt });
	}

	range.addEventListener("input", () => {
		const nextMode = getSpectralModeByIndex(range.value);
		const persisted = readSpectralTunerState();
		const isCurrentSaved = Boolean(persisted && persisted.index === nextMode.index);
		applyMode(nextMode, isCurrentSaved ? { saved: true, expiresAt: persisted.expiresAt } : { saved: false });
	});

	saveButton.addEventListener("click", () => {
		const mode = getSpectralModeByIndex(range.value);
		writeSpectralTunerState(mode);
		const persisted = readSpectralTunerState();
		if (persisted) {
			applyMode(persisted, { saved: true, expiresAt: persisted.expiresAt });
		}
		if (!expiryTimer) {
			expiryTimer = window.setInterval(syncExpiryBadge, 60000);
		}
	});

	resetButton.addEventListener("click", () => {
		clearSpectralTunerState();
		window.clearInterval(expiryTimer);
		expiryTimer = 0;
		const neutralMode = spectralModes.reduce((closest, mode) => {
			return Math.abs(mode.lambda - defaultLambda) < Math.abs(closest.lambda - defaultLambda) ? mode : closest;
		}, spectralModes[2]);
		applyMode(neutralMode, { saved: false });
		moodLabelNodes.forEach((node) => {
			node.textContent = defaultMood;
		});
		if (copyNode instanceof HTMLElement) {
			copyNode.textContent = "Retour à la fréquence native de cette surface. Tu peux refaire un réglage à tout moment.";
		}
		if (expiryNode instanceof HTMLElement) {
			expiryNode.textContent = "mode instantané";
		}
	});

	if (activeStored) {
		applyMode(activeStored, { saved: true, expiresAt: activeStored.expiresAt });
		expiryTimer = window.setInterval(syncExpiryBadge, 60000);
	} else {
		applyMode(fallbackMode, { saved: false });
		moodLabelNodes.forEach((node) => {
			node.textContent = defaultMood;
		});
	}
}

if ("serviceWorker" in navigator) {
	window.addEventListener("load", () => {
		navigator.serviceWorker.register("/site-sw.js").catch(() => {
			// Fail silently: the site still works as a regular document.
		});
	});
}

const GUIDE_VOICE_SESSION_KEY = "o-guide-voice-session-v1";

function initMappingGenie() {
	const root = document.querySelector("[data-mapping-genie]");
	if (!(root instanceof HTMLElement)) {
		return;
	}

	const cards = Array.from(root.querySelectorAll("[data-mapping-card]"));
	const activeLabel = root.querySelector("[data-mapping-active-label]");
	const activeWhisper = root.querySelector("[data-mapping-active-whisper]");
	const activeSummary = root.querySelector("[data-mapping-active-summary]");
	if (!cards.length) {
		return;
	}

	let cycleTimer = 0;
	let cycleIndex = 0;

	const updateChorus = (card) => {
		if (!(card instanceof HTMLElement)) {
			return;
		}

		const tone = card.dataset.mappingTone || "real";
		const label = card.dataset.mappingLabel || card.querySelector("strong")?.textContent || "";
		const whisper = card.dataset.mappingWhisper || "";
		const summary = card.dataset.mappingSummary || "";

		root.dataset.mappingTheme = tone;
		if (activeLabel instanceof HTMLElement) {
			activeLabel.textContent = label;
		}
		if (activeWhisper instanceof HTMLElement) {
			activeWhisper.textContent = whisper;
		}
		if (activeSummary instanceof HTMLElement) {
			activeSummary.textContent = summary;
		}
	};

	const stopCycle = () => {
		if (!cycleTimer) {
			return;
		}
		window.clearInterval(cycleTimer);
		cycleTimer = 0;
	};

	const startCycle = () => {
		if (coarsePointer || cards.length < 2 || cycleTimer) {
			return;
		}

		cycleTimer = window.setInterval(() => {
			cycleIndex = (cycleIndex + 1) % cards.length;
			setActiveCard(cards[cycleIndex]);
		}, 4200);
	};

	const setActiveCard = (nextCard, { collapseOnSecondTap = false } = {}) => {
		let chosenCard = nextCard;
		cards.forEach((card) => {
			const isActive = card === nextCard;
			if (collapseOnSecondTap && isActive && card.classList.contains("is-active")) {
				card.classList.remove("is-active");
				card.setAttribute("aria-expanded", "false");
				chosenCard = null;
				return;
			}

			card.classList.toggle("is-active", isActive);
			card.setAttribute("aria-expanded", isActive ? "true" : "false");
		});

		if (!chosenCard) {
			root.dataset.mappingTheme = "real";
			return;
		}

		cycleIndex = Math.max(0, cards.indexOf(chosenCard));
		updateChorus(chosenCard);
	};

	const activeCard = cards.find((card) => card.classList.contains("is-active")) || cards[0] || null;
	if (activeCard) {
		setActiveCard(activeCard);
	}

	cards.forEach((card) => {
		card.addEventListener("pointerenter", () => {
			if (coarsePointer) {
				return;
			}
			stopCycle();
			setActiveCard(card);
		});

		card.addEventListener("focus", () => {
			stopCycle();
			setActiveCard(card);
		});

		card.addEventListener("click", () => {
			stopCycle();
			setActiveCard(card, { collapseOnSecondTap: coarsePointer });
		});
	});

	root.addEventListener("pointerleave", () => {
		startCycle();
	});

	root.addEventListener("keydown", (event) => {
		if (event.key !== "Escape") {
			return;
		}

		cards.forEach((card) => {
			card.classList.remove("is-active");
			card.setAttribute("aria-expanded", "false");
		});
		root.dataset.mappingTheme = "real";
	});

	document.addEventListener("pointerdown", (event) => {
		if (!coarsePointer) {
			return;
		}

		if (!(event.target instanceof Element) || event.target.closest("[data-mapping-genie]")) {
			return;
		}

		cards.forEach((card) => {
			card.classList.remove("is-active");
			card.setAttribute("aria-expanded", "false");
		});
		if (cards[0]) {
			cards[0].classList.add("is-active");
			cards[0].setAttribute("aria-expanded", "true");
			updateChorus(cards[0]);
		}
	}, true);

	startCycle();
}

function initXyzSurface() {
	const root = document.querySelector("[data-xyz-surface]");
	if (!(root instanceof HTMLElement)) {
		return;
	}

	const applyDrift = (clientX, clientY) => {
		const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 1;
		const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 1;
		const offsetX = ((clientX / viewportWidth) - 0.5) * 14;
		const offsetY = ((clientY / viewportHeight) - 0.5) * 12;
		root.style.setProperty("--xyz-drift-x", `${offsetX.toFixed(2)}px`);
		root.style.setProperty("--xyz-drift-y", `${offsetY.toFixed(2)}px`);
	};

	const resetDrift = () => {
		root.style.setProperty("--xyz-drift-x", "0px");
		root.style.setProperty("--xyz-drift-y", "0px");
	};

	if (!reducedMotion && !coarsePointer) {
		window.addEventListener("pointermove", (event) => {
			if ((event.pointerType || "") === "touch") {
				return;
			}
			applyDrift(event.clientX, event.clientY);
		});

		window.addEventListener("pointerleave", resetDrift);
	}

	if (reducedMotion || coarsePointer) {
		resetDrift();
	}
}

function initXyzCamera() {
	const root = document.querySelector("[data-xyz-camera-root]");
	const video = document.querySelector("[data-xyz-camera-video]");
	const startButton = document.querySelector("[data-xyz-camera-start]");
	const stopButton = document.querySelector("[data-xyz-camera-stop]");
	const statusNode = document.querySelector("[data-xyz-camera-status]");
	const titleNode = document.querySelector("[data-xyz-camera-title]");

	if (!(root instanceof HTMLElement) || !(video instanceof HTMLVideoElement) || !(startButton instanceof HTMLElement) || !(stopButton instanceof HTMLElement)) {
		return;
	}

	let stream = null;
	let analysisFrame = 0;
	let analysisLastAt = 0;
	let previousSamples = null;
	const analysisCanvas = document.createElement("canvas");
	analysisCanvas.width = 48;
	analysisCanvas.height = 36;
	const analysisContext = analysisCanvas.getContext("2d", { willReadFrequently: true });

	const setReactiveCssState = (luma = 0, motion = 0, rgb = [180, 180, 180], flavor = "neutral") => {
		const safeLuma = clampNumber(luma, 0, 1);
		const safeMotion = clampNumber(motion, 0, 1);
		const safeRgb = Array.isArray(rgb) && rgb.length >= 3
			? rgb.map((value) => clampNumber(Math.round(Number(value) || 0), 0, 255))
			: [180, 180, 180];

		document.body.dataset.cameraLuma = safeLuma.toFixed(3);
		document.body.dataset.cameraMotion = safeMotion.toFixed(3);
		document.body.dataset.cameraRgb = safeRgb.join(" ");
		document.body.dataset.cameraFlavor = flavor;
		document.body.style.setProperty("--camera-luma", safeLuma.toFixed(3));
		document.body.style.setProperty("--camera-motion", safeMotion.toFixed(3));
		document.body.style.setProperty("--camera-rgb", safeRgb.join(" "));
	};

	const resetReactiveCssState = () => {
		previousSamples = null;
		setReactiveCssState(0, 0, [180, 180, 180], "neutral");
	};

	const describeCameraFlavor = (luma, motion) => {
		if (motion > 0.48 && luma > 0.54) {
			return {
				key: "sap",
				title: "La surface mâche une sève vive.",
				message: "Le flux est lumineux et mobile : le tore devient plus nerveux, presque juteux, comme s’il goûtait des reflets frais et des gestes rapides.",
			};
		}

		if (motion > 0.5) {
			return {
				key: "pulse",
				title: "Le tore avale du mouvement.",
				message: "Quelque chose remue dans le champ. La membrane épaissit ses pulsations et laisse le réel la traverser par secousses tendres.",
			};
		}

		if (luma < 0.32) {
			return {
				key: "velvet",
				title: "La membrane boit une nuit douce.",
				message: "Le monde est plus sombre, plus velouté. Le tore retient les noirs, les peaux basses, les ombres comme une pulpe lente.",
			};
		}

		if (luma > 0.62) {
			return {
				key: "gloss",
				title: "Le monde nourrit la surface de clarté.",
				message: "La lumière monte. Le tore s’éclaircit, absorbe les reflets et rend la membrane presque translucide, sucrée de présence.",
			};
		}

		return {
			key: "soft",
			title: "Le monde nourrit maintenant la surface.",
			message: "La peau caméra est ouverte. Le tore lit maintenant la lumière réelle comme une matière presque comestible : grain, souffle, reflets, présence.",
		};
	};

	const stopAnalysis = () => {
		if (analysisFrame) {
			window.cancelAnimationFrame(analysisFrame);
			analysisFrame = 0;
		}
		analysisLastAt = 0;
		resetReactiveCssState();
	};

	const analyzeFrame = (time = 0) => {
		if (!stream || !analysisContext) {
			return;
		}

		analysisFrame = window.requestAnimationFrame(analyzeFrame);
		if (time - analysisLastAt < 120) {
			return;
		}
		analysisLastAt = time;

		if (video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA || video.videoWidth === 0 || video.videoHeight === 0) {
			return;
		}

		analysisContext.drawImage(video, 0, 0, analysisCanvas.width, analysisCanvas.height);
		const imageData = analysisContext.getImageData(0, 0, analysisCanvas.width, analysisCanvas.height);
		const { data } = imageData;
		const sampleCount = analysisCanvas.width * analysisCanvas.height;
		const currentSamples = new Float32Array(sampleCount);
		let totalLuma = 0;
		let totalR = 0;
		let totalG = 0;
		let totalB = 0;
		let totalMotion = 0;

		for (let offset = 0, sampleIndex = 0; offset < data.length; offset += 4, sampleIndex += 1) {
			const red = data[offset];
			const green = data[offset + 1];
			const blue = data[offset + 2];
			const luma = (red * 0.299 + green * 0.587 + blue * 0.114) / 255;

			currentSamples[sampleIndex] = luma;
			totalLuma += luma;
			totalR += red;
			totalG += green;
			totalB += blue;

			if (previousSamples) {
				totalMotion += Math.abs(luma - previousSamples[sampleIndex]);
			}
		}

		previousSamples = currentSamples;
		const averageLuma = totalLuma / sampleCount;
		const averageMotion = previousSamples
			? clampNumber((totalMotion / sampleCount) * 3.6, 0, 1)
			: 0;
		const averageRgb = [
			Math.round(totalR / sampleCount),
			Math.round(totalG / sampleCount),
			Math.round(totalB / sampleCount),
		];

		const flavor = describeCameraFlavor(averageLuma, averageMotion);
		setReactiveCssState(averageLuma, averageMotion, averageRgb, flavor.key);

		if (titleNode instanceof HTMLElement) {
			titleNode.textContent = flavor.title;
		}
		if (statusNode instanceof HTMLElement) {
			statusNode.textContent = flavor.message;
		}
	};

	const setUiState = (state, message = "", title = "") => {
		document.body.classList.toggle("is-camera-ready", state === "live");
		startButton.classList.toggle("hidden", state === "live");
		stopButton.classList.toggle("hidden", state !== "live");

		if (statusNode instanceof HTMLElement && message) {
			statusNode.textContent = message;
		}

		if (titleNode instanceof HTMLElement && title) {
			titleNode.textContent = title;
		}
	};

	const stopStream = () => {
		stopAnalysis();
		if (stream) {
			stream.getTracks().forEach((track) => track.stop());
			stream = null;
		}
		video.srcObject = null;
		setUiState(
			"idle",
			"Ocam est refermé. Le tore continue de respirer sur une mémoire synthétique, sans capter le monde en direct.",
			"Ocam peut nourrir la surface."
		);
	};

	const constraints = {
		audio: false,
		video: {
			facingMode: coarsePointer ? { ideal: "environment" } : "user",
			width: { ideal: 1280 },
			height: { ideal: 720 },
		},
	};

	startButton.addEventListener("click", async () => {
		if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== "function") {
			setUiState(
				"unsupported",
				"Ocam ne trouve pas de caméra disponible ici. La membrane garde donc un monde synthétique, comme une pulpe de secours.",
				"Ocam indisponible sur cette surface."
			);
			return;
		}

		setUiState(
			"loading",
			"Le tore demande à Ocam la permission de goûter le réel : lumière, grain, textures. Rien n’est envoyé au serveur depuis cette couche.",
			"Ouverture d’Ocam…"
		);

		try {
			stream = await navigator.mediaDevices.getUserMedia(constraints);
			video.srcObject = stream;
			await video.play().catch(() => undefined);
			stopAnalysis();
			analysisFrame = window.requestAnimationFrame(analyzeFrame);
			setUiState(
				"live",
				"Ocam est ouvert. Le tore lit maintenant la lumière réelle comme une matière presque comestible : grain, souffle, reflets, présence.",
				"Ocam nourrit maintenant la surface."
			);
		} catch (error) {
			stopStream();
			setUiState(
				"error",
				"Permission refusée ou caméra inaccessible. La membrane revient à son rêve interne, sans captation directe.",
				"La surface garde son rêve sans Ocam."
			);
		}
	});

	stopButton.addEventListener("click", () => {
		stopStream();
	});

	window.addEventListener("beforeunload", () => {
		stopStream();
	});
}

function readGuideVoiceSession() {
	try {
		const raw = window.sessionStorage.getItem(GUIDE_VOICE_SESSION_KEY);
		if (!raw) {
			return {};
		}

		const parsed = JSON.parse(raw);
		return parsed && typeof parsed === "object" ? parsed : {};
	} catch {
		return {};
	}
}

initMappingGenie();
initXyzSurface();
initXyzCamera();

function writeGuideVoiceSession(session) {
	if (!session || typeof session !== "object") {
		return;
	}

	try {
		window.sessionStorage.setItem(GUIDE_VOICE_SESSION_KEY, JSON.stringify(session));
	} catch {
		// Ignore storage failures.
	}
}

function clearGuideVoiceSession() {
	try {
		window.sessionStorage.removeItem(GUIDE_VOICE_SESSION_KEY);
	} catch {
		// Ignore storage failures.
	}
}

let cornerDocksBooted = false;

function isCompactCornerDockViewport() {
	return window.innerWidth <= 720;
}

function registerCornerDock(dock) {
	if (!(dock instanceof HTMLDetailsElement) || dock.dataset.cornerDockRegistered === "1") {
		return;
	}

	dock.dataset.cornerDockRegistered = "1";
}

function syncCornerDocks(force = false) {
	const compact = isCompactCornerDockViewport();
	const docks = Array.from(document.querySelectorAll("[data-corner-dock]"));

	docks.forEach((dock) => {
		if (!(dock instanceof HTMLDetailsElement)) {
			return;
		}

		registerCornerDock(dock);
		const shouldStayOpen = dock.dataset.cornerDockActive === "1"
			|| (dock.id && window.location.hash === `#${dock.id}`)
			|| dock.contains(document.activeElement);

		if (!compact) {
			dock.open = true;
			dock.dataset.cornerDockCompact = "0";
			return;
		}

		dock.dataset.cornerDockCompact = "1";
		const isPrimaryDock = dock.dataset.cornerDockPriority === "primary";
		const isGuideVoiceDock = dock.dataset.guideVoiceDock === "1";
		if (force || dock.dataset.cornerDockInitialized !== "1") {
			dock.open = shouldStayOpen || (isPrimaryDock && !isGuideVoiceDock);
		} else if (shouldStayOpen) {
			dock.open = true;
		} else if (isGuideVoiceDock) {
			dock.open = false;
		}

		dock.dataset.cornerDockInitialized = "1";
	});
}

function initCornerDocks() {
	if (cornerDocksBooted) {
		syncCornerDocks();
		return;
	}

	cornerDocksBooted = true;
	syncCornerDocks(true);

	window.addEventListener("resize", () => {
		syncCornerDocks();
	});

	window.addEventListener("hashchange", () => {
		syncCornerDocks(true);
	});

	document.addEventListener("pointerdown", (event) => {
		if (!isCompactCornerDockViewport()) {
			return;
		}

		const target = event.target;
		if (!(target instanceof Element)) {
			return;
		}

		document.querySelectorAll("[data-corner-dock]").forEach((dock) => {
			if (!(dock instanceof HTMLDetailsElement) || !dock.open || dock.dataset.cornerDockActive === "1") {
				return;
			}

			if (!dock.contains(target)) {
				dock.open = false;
			}
		});
	}, true);

	document.addEventListener("keydown", (event) => {
		if (event.key !== "Escape" || !isCompactCornerDockViewport()) {
			return;
		}

		document.querySelectorAll("[data-corner-dock]").forEach((dock) => {
			if (dock instanceof HTMLDetailsElement && dock.dataset.cornerDockActive !== "1") {
				dock.open = false;
			}
		});
	});
}

async function fetchGuideVoiceState(apiPath = "/0wlslw0/voice") {
	try {
		const response = await fetch(apiPath, {
			method: "GET",
			headers: {
				Accept: "application/json",
			},
			credentials: "same-origin",
			cache: "no-store",
		});

		if (!response.ok) {
			return null;
		}

		const payload = await response.json().catch(() => ({}));
		const state = payload && typeof payload.state === "object" ? payload.state : null;
		return state && typeof state === "object" ? state : null;
	} catch {
		return null;
	}
}

function createGuideVoiceDock(config = {}) {
	if (!(document.body instanceof HTMLBodyElement)) {
		return null;
	}

	const existing = document.querySelector("[data-guide-voice-dock]");
	if (existing instanceof HTMLElement) {
		return existing;
	}

	const shell = document.createElement("details");
	shell.className = "panel reveal on guide-panel guide-voice-shell guide-voice-dock";
	shell.open = false;
	shell.dataset.guideVoice = "";
	shell.dataset.guideVoiceDock = "1";
	shell.dataset.cornerDock = "";
	shell.dataset.cornerDockSide = "right";
	shell.dataset.cornerDockPriority = "secondary";
	shell.dataset.cornerDockActive = "0";
	shell.dataset.guideVoiceApi = config.api_path || "/0wlslw0/voice";
	shell.dataset.guideVoiceCsrf = config.csrf_token || "";
	shell.dataset.guideVoiceGreeting = config.greeting || "Je suis 0wlslw0. Je te suis d’une page à l’autre.";
	shell.dataset.guideVoiceUpstream = config.upstream_configured ? "1" : "0";
	shell.dataset.guideVoiceChatUrl = config.chat_url || "";
	shell.dataset.guideVoiceProgram = config.land_program || document.body?.dataset?.landProgram || "collective";
	shell.dataset.guideVoiceLabel = config.land_label || document.body?.dataset?.landLabel || "collectif";
	shell.dataset.guideVoiceLambda = String(config.land_lambda || document.body?.dataset?.landLambda || "548");
	shell.dataset.guideVoiceTone = config.land_tone || document.body?.dataset?.landTone || "";
	shell.dataset.voiceState = "idle";
	shell.dataset.voiceMuted = readGuideVoiceMutedState() ? "1" : "0";
	shell.setAttribute("aria-label", "Dock vocal 0wlslw0");
	shell.innerHTML = `
		<summary class="guide-voice-dock__toggle">
			<span class="corner-dock-toggle__kicker">0wlslw0</span>
			<strong data-guide-voice-dock-label>voix persistante</strong>
			<span class="corner-dock-toggle__meta" data-guide-voice-dock-state>ouvrir</span>
		</summary>
		<div class="guide-voice-dock-head">
			<p class="eyebrow"><strong>0wlslw0</strong> <span>voix persistante</span></p>
			<span class="badge">suivi</span>
		</div>
		<div class="guide-voice-stage">
			<div class="guide-voice-orb" aria-hidden="true">
				<span class="guide-voice-orb-core"></span>
				<span class="guide-voice-orb-ring"></span>
			</div>
			<p class="guide-voice-status" data-guide-voice-status>0wlslw0 peut te suivre ici.</p>
			<p class="guide-voice-transcript" data-guide-voice-transcript>La continuité vocale se réamorce après navigation.</p>
			<p class="guide-voice-reply" data-guide-voice-reply>Active la voix une fois, puis continue ta traversée.</p>
			<div class="guide-voice-signature" aria-live="polite">
				<span class="summary-label">Signature vocale</span>
				<strong data-guide-voice-signature>Voix spectrale · λ 548 nm</strong>
				<span class="guide-voice-profile" data-guide-voice-profile>tempo ajusté · collectif</span>
				<span class="guide-voice-mute-indicator" data-guide-voice-mute-indicator>voix active · I inverse + voix · appui long tactile</span>
			</div>
			<div class="action-row guide-voice-actions">
				<button type="button" class="pill-link" data-guide-voice-start>Réactiver la voix</button>
				<button type="button" class="ghost-link" data-guide-voice-stop hidden>Couper</button>
			</div>
			<a class="ghost-link guide-voice-route-link" href="#" data-guide-voice-route hidden>Continuer</a>
		</div>
	`;

	document.body.classList.add("has-guide-voice-dock");
	document.body.appendChild(shell);
	registerCornerDock(shell);
	syncCornerDocks(true);
	return shell;
}

function normalizeGuideVoiceRoute(route) {
	if (!route || typeof route !== "object" || typeof route.href !== "string" || !route.href) {
		return null;
	}

	return {
		href: route.href,
		label: typeof route.label === "string" && route.label ? route.label : "Continuer",
		auto_navigate: Boolean(route.auto_navigate),
	};
}

function normalizeGuideVoiceText(value) {
	const input = typeof value === "string" ? value : "";
	const normalized = typeof input.normalize === "function" ? input.normalize("NFD") : input;
	return normalized
		.replace(/[\u0300-\u036f]/g, "")
		.toLowerCase()
		.replace(/[^a-z0-9#\s/-]+/g, " ")
		.replace(/\s+/g, " ")
		.trim();
}

function currentGuideVoicePageInfo() {
	const path = window.location.pathname;
	const hash = window.location.hash || "";
	const heading = document.querySelector("h1 strong, h1")?.textContent?.trim() || "";
	const landSlug = new URLSearchParams(window.location.search).get("u") || "";

	if (path === "/" && hash === "#str3m-quotidien") {
		return {
			key: "str3m",
			label: "le Str3m quotidien",
			hint: "Tu peux dire signal, aZa, echo, map, noyau, ou guide.",
		};
	}

	switch (path) {
		case "/":
			return {
				key: "home",
				label: "le noyau",
				hint: "Tu peux dire Str3m, Signal, aZa, Echo, map, ou guide.",
			};
		case "/signal":
			return {
				key: "signal",
				label: "Signal",
				hint: "Tu peux dire noyau, Str3m, aZa, Echo, map, ou guide.",
			};
		case "/str3m":
			return {
				key: "str3m",
				label: "Str3m",
				hint: "Tu peux dire noyau, Signal, aZa, Echo, map, ou guide.",
			};
		case "/aza":
			return {
				key: "aza",
				label: "aZa",
				hint: "Tu peux dire noyau, Signal, Str3m, Echo, map, ou guide.",
			};
		case "/echo":
			return {
				key: "echo",
				label: "Echo",
				hint: "Tu peux dire noyau, Signal, Str3m, aZa, map, ou guide.",
			};
		case "/map":
			return {
				key: "map",
				label: "la map du tore vivant",
				hint: "Tu peux dire noyau, Signal, Str3m, aZa, Echo, ou guide.",
			};
		case "/0wlslw0":
			return {
				key: "guide",
				label: "0wlslw0",
				hint: "Tu peux dire noyau, Signal, Str3m, aZa, Echo, ou map.",
			};
		case "/land":
			return {
				key: "land",
				label: heading || (landSlug ? `la terre ${landSlug}` : "une terre"),
				hint: "Tu peux dire noyau, Signal, Str3m, aZa, Echo, map, ou guide.",
			};
		default:
			return {
				key: "unknown",
				label: heading || "cette page",
				hint: "Tu peux dire noyau, Signal, Str3m, aZa, Echo, map, ou guide.",
			};
	}
}

function findGuideVoiceLandRoute() {
	const preferredAnchors = Array.from(document.querySelectorAll("a[href]"));
	const preferred = preferredAnchors.find((anchor) => {
		if (!(anchor instanceof HTMLAnchorElement)) {
			return false;
		}

		const text = normalizeGuideVoiceText(anchor.textContent || "");
		return text.includes("ma terre") || text.includes("ouvrir ma terre");
	});

	if (preferred instanceof HTMLAnchorElement && preferred.href) {
		const parsed = new URL(preferred.href, window.location.origin);
		return {
			href: `${parsed.pathname}${parsed.search}${parsed.hash}`,
			label: "Ma terre",
			auto_navigate: true,
		};
	}

	if (window.location.pathname === "/land") {
		return {
			href: `${window.location.pathname}${window.location.search}${window.location.hash}`,
			label: "Cette terre",
			auto_navigate: true,
		};
	}

	return null;
}

function guideVoiceNavigationCatalog() {
	const landRoute = findGuideVoiceLandRoute();
	const routes = [
		{
			key: "home",
			label: "le noyau",
			href: "/",
			confirm: "Je te ramène vers le noyau.",
			matchers: ["noyau", "accueil", "centre", "retour noyau", "retour accueil", "home"],
		},
		{
			key: "signal",
			label: "Signal",
			href: "/signal",
			confirm: "Je t’emmène vers Signal.",
			matchers: ["signal", "inbox", "boite", "boite aux lettres", "adresse"],
		},
		{
			key: "str3m",
			label: "Str3m",
			href: "/str3m",
			confirm: "Je t’emmène vers Str3m.",
			matchers: ["str3m", "stream", "courant", "courant public", "public"],
		},
		{
			key: "aza",
			label: "aZa",
			href: "/aza",
			confirm: "Je t’emmène vers aZa.",
			matchers: ["aza", "archive", "archives", "memoire", "memoires"],
		},
		{
			key: "echo",
			label: "Echo",
			href: "/echo",
			confirm: "Je t’emmène vers Echo.",
			matchers: ["echo", "echoo", "liaison", "resonance", "resonnance"],
		},
		{
			key: "map",
			label: "la map",
			href: "/map",
			confirm: "Je t’emmène vers la map du tore vivant.",
			matchers: ["map", "carte", "tore", "torus", "courants", "courant chaud"],
		},
		{
			key: "guide",
			label: "0wlslw0",
			href: "/0wlslw0",
			confirm: "Je te ramène vers 0wlslw0.",
			matchers: ["0wlslw0", "guide", "hibou", "owl"],
		},
	];

	if (landRoute) {
		routes.push({
			key: "land",
			label: landRoute.label,
			href: landRoute.href,
			confirm: "Je t’emmène vers ta terre.",
			matchers: ["ma terre", "mon ile", "mon ile", "mon espace", "ma page"],
		});
	}

	return routes;
}

function guideVoiceLooksLikeNavigation(text) {
	if (!text) {
		return false;
	}

	const verbs = [
		"va",
		"aller",
		"ouvre",
		"emmene",
		"conduis",
		"guide",
		"navigue",
		"ramene",
		"retour",
		"direction",
		"cap sur",
		"go",
	];

	return verbs.some((verb) => text.includes(verb));
}

function resolveGuideVoiceNativeCommand(utterance) {
	const text = normalizeGuideVoiceText(utterance);
	const page = currentGuideVoicePageInfo();
	if (!text) {
		return null;
	}

	if (["ou suis je", "ou on est", "on est ou", "quelle page", "ou sommes nous"].some((phrase) => text.includes(phrase))) {
		return {
			type: "info",
			reply: `Nous sommes dans ${page.label}. ${page.hint}`,
		};
	}

	if (["tais toi", "tais-toi", "coupe la voix", "arrete la voix", "stop voix", "silence"].some((phrase) => text.includes(normalizeGuideVoiceText(phrase)))) {
		return {
			type: "stop",
			reply: "Je coupe la voix. Tu peux me relancer quand tu veux.",
		};
	}

	const routes = guideVoiceNavigationCatalog();
	const navigationIntent = guideVoiceLooksLikeNavigation(text);
	const exactShortCommand = text.split(" ").length <= 3;

	for (const route of routes) {
		const match = route.matchers.some((matcher) => text.includes(normalizeGuideVoiceText(matcher)));
		if (!match) {
			continue;
		}

		if (!navigationIntent && !exactShortCommand) {
			continue;
		}

		const href = route.href;
		const parsed = new URL(href, window.location.origin);
		const destination = `${parsed.pathname}${parsed.search}${parsed.hash}`;
		const current = `${window.location.pathname}${window.location.search}${window.location.hash}`;

		if (current === destination) {
			return {
				type: "info",
				reply: `On est déjà dans ${route.label}. ${page.hint}`,
				route: {
					href: destination,
					label: route.label,
					auto_navigate: false,
				},
			};
		}

		return {
			type: "navigate",
			reply: route.confirm,
			route: {
				href: destination,
				label: route.label,
				auto_navigate: true,
			},
		};
	}

	return null;
}

function navigateToGuideVoiceRoute(href) {
	if (!href) {
		return false;
	}

	if (href === "/#str3m-quotidien") {
		return navigateToStr3mSurface();
	}

	if (href === "/") {
		return navigateToCoreSurface();
	}

	const current = `${window.location.pathname}${window.location.search}${window.location.hash}`;
	if (current === href) {
		return false;
	}

	window.location.assign(href);
	return true;
}

function mountGuideVoice(root) {
	if (!(root instanceof HTMLElement) || root.dataset.guideVoiceMounted === "1") {
		return;
	}

	root.dataset.guideVoiceMounted = "1";

	const startButton = root.querySelector("[data-guide-voice-start]");
	const stopButton = root.querySelector("[data-guide-voice-stop]");
	const statusNode = root.querySelector("[data-guide-voice-status]");
	const transcriptNode = root.querySelector("[data-guide-voice-transcript]");
	const replyNode = root.querySelector("[data-guide-voice-reply]");
	const signatureNode = root.querySelector("[data-guide-voice-signature]");
	const profileNode = root.querySelector("[data-guide-voice-profile]");
	const muteIndicatorNode = root.querySelector("[data-guide-voice-mute-indicator]");
	const routeLink = root.querySelector("[data-guide-voice-route]");
	const dockLabelNode = root.querySelector("[data-guide-voice-dock-label]");
	const dockStateNode = root.querySelector("[data-guide-voice-dock-state]");
	const RecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition || null;
	const synth = "speechSynthesis" in window ? window.speechSynthesis : null;
	const greeting = root.dataset.guideVoiceGreeting || "Je suis 0wlslw0. Dis-moi ce que tu veux faire.";
	const apiPath = root.dataset.guideVoiceApi || "/0wlslw0/voice";
	const csrfToken = root.dataset.guideVoiceCsrf || "";
	const chatUrl = root.dataset.guideVoiceChatUrl || "";
	const currentPath = `${window.location.pathname}${window.location.search}${window.location.hash}`;
	const isDock = root.dataset.guideVoiceDock === "1";
	const persisted = readGuideVoiceSession();
	let recognition = null;
	let isActive = Boolean(persisted.active);
	let isMuted = readGuideVoiceMutedState();
	let isSpeaking = false;
	let isWaitingReply = false;
	let interactionsBound = false;

	function syncDockState() {
		if (!isDock) {
			return;
		}

		root.dataset.cornerDockActive = isActive ? "1" : "0";
		if (dockLabelNode instanceof HTMLElement) {
			dockLabelNode.textContent = isActive ? "voix active" : "voix persistante";
		}

		if (dockStateNode instanceof HTMLElement) {
			let compactLabel = "ouvrir";
			if (isActive && isMuted) {
				compactLabel = "muette";
			} else if (isWaitingReply) {
				compactLabel = "analyse";
			} else if (isSpeaking) {
				compactLabel = "répond";
			} else if (root.dataset.voiceState === "listening") {
				compactLabel = "écoute";
			} else if (isActive) {
				compactLabel = "veille";
			}

			dockStateNode.textContent = compactLabel;
		}

		if (isCompactCornerDockViewport()) {
			root.open = isActive || root.open;
		}

		syncCornerDocks();
	}

	function persistSession(overrides = {}) {
		const previous = readGuideVoiceSession();
		const route = routeLink instanceof HTMLAnchorElement && !routeLink.hidden
			? {
				href: routeLink.getAttribute("href") || "#",
				label: routeLink.textContent || "Continuer",
				auto_navigate: routeLink.dataset.autoNavigate === "1",
			}
			: null;

		writeGuideVoiceSession({
			...previous,
			active: isActive,
			everActivated: Boolean(previous.everActivated || isActive || overrides.everActivated),
			status: statusNode instanceof HTMLElement ? statusNode.textContent || "" : "",
			transcript: transcriptNode instanceof HTMLElement ? transcriptNode.textContent || "" : "",
			reply: replyNode instanceof HTMLElement ? replyNode.textContent || "" : "",
			route,
			apiPath,
			csrfToken,
			chatUrl,
			greeting,
			lastPath: currentPath,
			autoResume: isActive,
			updatedAt: Date.now(),
			...overrides,
		});
	}

	function updateMuteUI(nextMuted = isMuted) {
		isMuted = Boolean(nextMuted);
		root.dataset.voiceMuted = isMuted ? "1" : "0";
		if (muteIndicatorNode instanceof HTMLElement) {
			muteIndicatorNode.textContent = isMuted
				? "voix muette · I ou appui long pour la relancer"
				: "voix active · I inverse + voix · appui long tactile";
		}
		syncDockState();
	}

	function applyGuideVoiceSignature() {
		const spectral = readGuideVoiceSpectralProfile(root);
		root.dataset.guideVoiceProgram = spectral.program;
		root.dataset.guideVoiceLabel = spectral.label;
		root.dataset.guideVoiceLambda = String(Math.round(spectral.lambda));
		root.dataset.guideVoiceTone = spectral.tone;
		root.style.setProperty("--guide-voice-pulse-duration", `${spectral.orbPulseDuration.toFixed(2)}s`);
		root.style.setProperty("--guide-voice-core-scale", spectral.coreScale.toFixed(3));

		if (signatureNode instanceof HTMLElement) {
			const register = spectral.registerLabel.charAt(0).toUpperCase() + spectral.registerLabel.slice(1);
			signatureNode.textContent = `${register} · λ ${Math.round(spectral.lambda)} nm`;
		}

		if (profileNode instanceof HTMLElement) {
			profileNode.textContent = `${spectral.tempoLabel} · ${spectral.label}`;
		}

		updateMuteUI(isMuted);
		return spectral;
	}

	function setState(state, statusText = "") {
		root.dataset.voiceState = state;
		if (statusNode instanceof HTMLElement && statusText) {
			statusNode.textContent = statusText;
		}
		persistSession();
		syncDockState();
	}

	function setTranscript(text) {
		if (transcriptNode instanceof HTMLElement && text) {
			transcriptNode.textContent = text;
		}
		persistSession();
	}

	function setReply(text) {
		if (replyNode instanceof HTMLElement && text) {
			replyNode.textContent = text;
		}
		persistSession();
	}

	function hideRoute() {
		if (!(routeLink instanceof HTMLAnchorElement)) {
			return;
		}

		routeLink.hidden = true;
		routeLink.textContent = "Continuer";
		routeLink.setAttribute("href", "#");
		routeLink.dataset.autoNavigate = "0";
		persistSession();
	}

	function showRoute(route) {
		const normalized = normalizeGuideVoiceRoute(route);
		if (!(routeLink instanceof HTMLAnchorElement) || !normalized) {
			hideRoute();
			return;
		}

		routeLink.hidden = false;
		routeLink.href = normalized.href;
		routeLink.textContent = normalized.label || "Continuer";
		routeLink.dataset.autoNavigate = normalized.auto_navigate ? "1" : "0";
		persistSession({ route: normalized });
	}

	function stopListening() {
		if (!recognition) {
			return;
		}

		try {
			recognition.stop();
		} catch {
			// Ignore stop races.
		}
	}

	function prepareNavigation(destination = "") {
		if (!isActive) {
			return;
		}

		persistSession({
			active: true,
			autoResume: true,
			pendingNavigation: destination,
			carryMessage: "Je te suis sur la page suivante.",
		});
	}

	function beginListening() {
		if (!recognition || !isActive || isSpeaking || isWaitingReply) {
			return;
		}

		try {
			recognition.start();
			setState("listening", "J’écoute. Parle naturellement.");
		} catch {
			// Recognition may already be starting; ignore duplicate starts.
		}
	}

	function speakReply(text, onDone) {
		const spectral = applyGuideVoiceSignature();
		if (!text) {
			onDone?.();
			return;
		}

		if (isMuted || !synth || typeof window.SpeechSynthesisUtterance !== "function") {
			if (isMuted) {
				setState("idle", "Voix muette. 0wlslw0 continue en texte lisible.");
			}
			onDone?.();
			return;
		}

		isSpeaking = true;
		setState("speaking", "Je te réponds à voix haute.");
		synth.cancel();

		const languageCode = detectGuideSpeechLanguage(text);
		const utterance = new window.SpeechSynthesisUtterance(text);
		utterance.lang = languageCode;
		utterance.voice = pickGuideSpeechVoice(synth, languageCode);
		utterance.rate = spectral.rate;
		utterance.pitch = spectral.pitch;
		utterance.volume = spectral.volume;
		utterance.onend = () => {
			isSpeaking = false;
			onDone?.();
		};
		utterance.onerror = () => {
			isSpeaking = false;
			onDone?.();
		};

		synth.speak(utterance);
	}

	function stopGuide(message = "Micro coupé.") {
		isActive = false;
		isSpeaking = false;
		isWaitingReply = false;
		stopListening();
		if (synth) {
			synth.cancel();
		}
		if (startButton instanceof HTMLElement) {
			startButton.hidden = false;
			startButton.textContent = isDock ? "Réactiver la voix" : "Activer la voix";
		}
		if (stopButton instanceof HTMLElement) {
			stopButton.hidden = true;
		}
		setState("idle", message);
		clearGuideVoiceSession();
		if (isDock && isCompactCornerDockViewport()) {
			root.open = false;
		}
		syncDockState();
	}

	function resumeAfterReply(delay = 260) {
		if (isActive) {
			window.setTimeout(beginListening, delay);
		} else {
			setState("idle", "Prêt si tu veux reprendre.");
		}
	}

	function announceCurrentPage(afterAnnouncement) {
		const page = currentGuideVoicePageInfo();
		const announcement = `Nous sommes dans ${page.label}. ${page.hint}`;
		setReply(announcement);
		speakReply(announcement, () => {
			persistSession({ carryMessage: "", pendingNavigation: "" });
			afterAnnouncement?.();
		});
	}

	function handleNativeCommand(utterance) {
		const command = resolveGuideVoiceNativeCommand(utterance);
		if (!command) {
			return false;
		}

		setTranscript(`Tu as dit : ${utterance}`);

		if (command.type === "stop") {
			setReply(command.reply);
			stopGuide("Voix coupée localement.");
			return true;
		}

		if (command.type === "info") {
			setReply(command.reply);
			showRoute(command.route || null);
			speakReply(command.reply, () => {
				resumeAfterReply(220);
			});
			return true;
		}

		if (command.type === "navigate") {
			const normalizedRoute = normalizeGuideVoiceRoute(command.route);
			setReply(command.reply);
			showRoute(normalizedRoute);
			speakReply(command.reply, () => {
				if (normalizedRoute && normalizedRoute.href) {
					prepareNavigation(normalizedRoute.href);
					navigateToGuideVoiceRoute(normalizedRoute.href);
					return;
				}

				resumeAfterReply(220);
			});
			return true;
		}

		return false;
	}

	async function sendUtterance(utterance) {
		isWaitingReply = true;
		setState("thinking", "Je cherche la bonne porte.");
		setTranscript(`Tu as dit : ${utterance}`);

		try {
			const response = await fetch(apiPath, {
				method: "POST",
				headers: {
					"Content-Type": "application/json",
					"X-CSRF-Token": csrfToken,
				},
				body: JSON.stringify({
					utterance,
					csrf_token: csrfToken,
				}),
			});

			const payload = await response.json().catch(() => ({}));
			const reply = typeof payload.reply === "string" && payload.reply.trim()
				? payload.reply.trim()
				: "Le passage vocal hésite un instant. Réessaie avec une phrase plus courte.";
			const route = payload && typeof payload === "object" ? payload.route : null;

			setReply(reply);
			showRoute(route);
			isWaitingReply = false;

			speakReply(reply, () => {
				const normalizedRoute = normalizeGuideVoiceRoute(route);
				if (normalizedRoute && normalizedRoute.auto_navigate && normalizedRoute.href) {
					prepareNavigation(normalizedRoute.href);
					window.location.assign(normalizedRoute.href);
					return;
				}

				resumeAfterReply(260);
			});
		} catch {
			isWaitingReply = false;
			setReply(chatUrl
				? "Le relais local a décroché. Tu peux utiliser le relais externe pendant que je me réveille."
				: "Le relais local a décroché. Réessaie dans un instant.");
			setState("idle", "Connexion vocale brouillée.");
			if (isActive) {
				window.setTimeout(beginListening, 600);
			}
		}
	}

	function bindNavigationCarry() {
		if (interactionsBound) {
			return;
		}

		interactionsBound = true;
		document.addEventListener("click", (event) => {
			if (!isActive) {
				return;
			}

			const target = event.target;
			if (!(target instanceof Element)) {
				return;
			}

			const anchor = target.closest("a[href]");
			if (!(anchor instanceof HTMLAnchorElement)) {
				return;
			}

			const rawHref = anchor.getAttribute("href") || "";
			if (!rawHref || rawHref.startsWith("#") || rawHref.startsWith("mailto:") || rawHref.startsWith("tel:")) {
				return;
			}

			if (anchor.target && anchor.target !== "_self") {
				return;
			}

			let destination = "";
			try {
				const parsed = new URL(anchor.href, window.location.origin);
				if (parsed.origin !== window.location.origin) {
					return;
				}

				destination = `${parsed.pathname}${parsed.search}${parsed.hash}`;
			} catch {
				return;
			}

			prepareNavigation(destination);
		}, true);

		window.addEventListener("beforeunload", () => {
			if (isActive) {
				prepareNavigation("");
			}
		});
	}

	if (!(startButton instanceof HTMLElement) || !(stopButton instanceof HTMLElement)) {
		return;
	}

	if (synth && typeof synth.getVoices === "function") {
		synth.getVoices();
	}

	applyGuideVoiceSignature();
	hideRoute();
	bindNavigationCarry();
	syncDockState();

	const restoredRoute = normalizeGuideVoiceRoute(persisted.route || null);
	if (restoredRoute) {
		showRoute(restoredRoute);
	}

	if (typeof persisted.transcript === "string" && persisted.transcript) {
		setTranscript(persisted.transcript);
	}

	if (typeof persisted.reply === "string" && persisted.reply) {
		setReply(persisted.reply);
	}

	if (!RecognitionCtor) {
		startButton.setAttribute("disabled", "disabled");
		setReply(chatUrl
			? "Ce navigateur ne propose pas la reconnaissance vocale Web. Tu peux ouvrir le relais externe en attendant."
			: "Ce navigateur ne propose pas la reconnaissance vocale Web. Essaie Safari ou Chrome récents.");
		setState("idle", "Reconnaissance vocale indisponible dans ce navigateur.");
		return;
	}

	recognition = new RecognitionCtor();
	recognition.lang = "fr-FR";
	recognition.continuous = false;
	recognition.interimResults = true;
	recognition.maxAlternatives = 1;

	recognition.onresult = (event) => {
		let interim = "";
		let finalTranscript = "";

		for (let index = event.resultIndex; index < event.results.length; index += 1) {
			const result = event.results[index];
			const transcript = result[0]?.transcript?.trim() || "";
			if (!transcript) {
				continue;
			}

			if (result.isFinal) {
				finalTranscript += `${transcript} `;
			} else {
				interim += `${transcript} `;
			}
		}

		if (interim.trim()) {
			setTranscript(`J’entends : ${interim.trim()}`);
		}

		if (finalTranscript.trim()) {
			stopListening();
			if (handleNativeCommand(finalTranscript.trim())) {
				return;
			}
			sendUtterance(finalTranscript.trim());
		}
	};

	recognition.onerror = (event) => {
		if (!isActive) {
			return;
		}

		const errorCode = event?.error || "unknown";
		if (errorCode === "not-allowed" || errorCode === "service-not-allowed") {
			stopGuide("Le micro doit être réautorisé sur cette page. Réactive la voix pour reprendre.");
			return;
		}

		if (errorCode === "no-speech") {
			setState("idle", "Je n’ai rien capté. Réessaie quand tu veux.");
			window.setTimeout(beginListening, 420);
			return;
		}

		setState("idle", "Le micro a décroché. Je relance l’écoute.");
		window.setTimeout(beginListening, 680);
	};

	recognition.onend = () => {
		if (isActive && !isSpeaking && !isWaitingReply) {
			window.setTimeout(beginListening, 240);
		}
	};

	startButton.addEventListener("click", () => {
		const session = readGuideVoiceSession();
		const firstActivation = !session.everActivated;
		isActive = true;
		isWaitingReply = false;
		if (isDock && isCompactCornerDockViewport()) {
			root.open = true;
		}
		hideRoute();
		startButton.hidden = true;
		stopButton.hidden = false;
		setReply(firstActivation
			? "0wlslw0 répondra ici puis lira sa réponse à voix haute."
			: "0wlslw0 reste avec toi et reprend l’écoute sur cette page.");
		persistSession({ active: true, autoResume: true, everActivated: true, pendingNavigation: "" });

		if (firstActivation) {
			speakReply(greeting, () => {
				if (isActive) {
					window.setTimeout(beginListening, 260);
				}
			});
			return;
		}

		setState("idle", "Je suis là. On reprend.");
		window.setTimeout(beginListening, 180);
	});

	stopButton.addEventListener("click", () => {
		stopGuide("Voix coupée. Tu peux relancer quand tu veux.");
	});

	if (routeLink instanceof HTMLAnchorElement) {
		routeLink.addEventListener("click", () => {
			prepareNavigation(routeLink.href || "");
		});
	}

	window.addEventListener("o:land-signature-change", () => {
		applyGuideVoiceSignature();
	});

	window.addEventListener("o:guide-voice-mute-change", (event) => {
		const nextMuted = Boolean(event?.detail?.muted ?? readGuideVoiceMutedState());
		const wasSpeaking = isSpeaking;
		updateMuteUI(nextMuted);
		if (nextMuted && synth) {
			synth.cancel();
			isSpeaking = false;
		}

		if (statusNode instanceof HTMLElement) {
			statusNode.textContent = nextMuted
				? "Voix muette. 0wlslw0 reste lisible et continue d’écouter."
				: (isActive ? "Voix audible. 0wlslw0 peut repasser du grave à l’aigu." : "Voix audible. Active-la si tu veux l’entendre.");
		}
		persistSession();

		if (wasSpeaking && nextMuted && isActive && !isWaitingReply) {
			window.setTimeout(beginListening, 140);
		}

		syncDockState();
	});

	const navigationCarry = Boolean(persisted.active);
	if (navigationCarry) {
		startButton.hidden = true;
		stopButton.hidden = false;
		const crossedPageBoundary = Boolean(persisted.lastPath && persisted.lastPath !== currentPath);
		setState(
			"idle",
			crossedPageBoundary
				? "Je te suis ici. Je reprends la navigation vocale."
				: (persisted.status || (isDock ? "0wlslw0 reste actif ici." : "La voix reste active."))
		);
		if (!(replyNode instanceof HTMLElement) || !replyNode.textContent) {
			setReply(isDock
				? "0wlslw0 est toujours là. Continue simplement à parler."
				: "0wlslw0 reste actif pendant la traversée.");
		}
		if (crossedPageBoundary) {
			window.setTimeout(() => {
				announceCurrentPage(() => {
					resumeAfterReply(260);
				});
			}, 180);
		} else {
			window.setTimeout(beginListening, 320);
		}
		persistSession({ active: true, autoResume: true, pendingNavigation: "", carryMessage: "" });
		syncDockState();
		return;
	}

	setState("idle", isDock
		? "0wlslw0 peut te suivre ici si tu actives la voix depuis son passage."
		: "Prêt. Active la voix puis parle naturellement.");
	if (!(replyNode instanceof HTMLElement) || !replyNode.textContent) {
		setReply(isDock
			? "Active la voix depuis 0wlslw0, puis elle te suivra pendant la navigation."
			: "0wlslw0 répondra ici puis lira sa réponse à voix haute.");
	}
	if (startButton instanceof HTMLElement) {
		startButton.textContent = isDock ? "Réactiver la voix" : "Activer la voix";
	}
	syncDockState();
}

function initGuideVoice() {
	const existingRoot = document.querySelector("[data-guide-voice]");
	const persisted = readGuideVoiceSession();

	const boot = async () => {
		let root = existingRoot instanceof HTMLElement ? existingRoot : null;
		if (!root && !persisted.active) {
			return;
		}

		if (!root) {
			const state = await fetchGuideVoiceState(persisted.apiPath || "/0wlslw0/voice");
			if (!state) {
				return;
			}
			root = createGuideVoiceDock(state);
		}

		mountGuideVoice(root);
	};

	boot();
}

function initSignalFlow() {
	const filterInput = document.querySelector("[data-signal-contact-filter]");
	const contactItems = Array.from(document.querySelectorAll("[data-signal-contact-item]"));
	const openInput = document.querySelector("[data-signal-open-input]");
	const history = document.getElementById("signal-history");
	const liveRoot = document.querySelector("[data-message-live]");
	const liveHistory = liveRoot?.querySelector("[data-message-live-history]");
	const liveIndicator = liveRoot?.querySelector("[data-message-live-indicator]");
	const echoContactsRoot = liveRoot?.querySelector("[data-echo-contacts-list]");
	const unreadLabels = Array.from(document.querySelectorAll("[data-signal-unread-label]"));
	const composeForms = Array.from(document.querySelectorAll("[data-signal-compose]"));
	const recipientInputs = Array.from(document.querySelectorAll("[data-signal-recipient-input]"));
	const optionNodes = Array.from(document.querySelectorAll("#signal-contact-options option"));
	const algoraStorageKey = "o-signal-algora-mode";
	const algoraCopy = {
		douceur: {
			fallbackHint: "algoRa en douceur : chercher les accords avant de pousser le flux.",
			openPlaceholder: "slug ou nom d’une terre en douceur",
			recipientPlaceholder: "slug ou nom d’une terre en douceur",
			subjectPlaceholder: "Premier contact en douceur (optionnel)",
			threadSubjectPlaceholder: "Objet du message en douceur (optionnel)",
			body: {
				"phase-locked": "Écrire en prolongeant l’accord déjà là...",
				harmonic: "Écrire en gardant de la souplesse entre vos ondes...",
				interference: "Entrer doucement dans l’écart créatif...",
				drift: "Ralentir un peu pour rencontrer l’autre fréquence...",
				inertia: "Approcher lentement cette distance fertile...",
			},
		},
		confrontation: {
			fallbackHint: "algoRa en confrontation : préférer les écarts productifs et les tensions claires.",
			openPlaceholder: "slug ou nom d’une terre à confronter",
			recipientPlaceholder: "slug ou nom d’une terre à confronter",
			subjectPlaceholder: "Point de friction à ouvrir (optionnel)",
			threadSubjectPlaceholder: "Nœud de confrontation (optionnel)",
			body: {
				"phase-locked": "Nommer franchement ce qui résiste malgré la proximité...",
				harmonic: "Faire apparaître le désaccord utile sans rompre le lien...",
				interference: "Entrer dans la tension féconde sans l’adoucir trop tôt...",
				drift: "Attraper le décalage et le rendre explicite...",
				inertia: "Forer la distance sans contourner ce qui frotte...",
			},
		},
		ecoute: {
			fallbackHint: "algoRa en écoute : laisser l’autre fréquence se dire avant de conclure.",
			openPlaceholder: "slug ou nom d’une terre à écouter",
			recipientPlaceholder: "slug ou nom d’une terre à écouter",
			subjectPlaceholder: "Question d’écoute (optionnel)",
			threadSubjectPlaceholder: "Ce que tu veux entendre (optionnel)",
			body: {
				"phase-locked": "Écrire en laissant de l’espace à ce qui répond déjà...",
				harmonic: "Écrire avec attention aux nuances entre vos rythmes...",
				interference: "Accueillir le contraste avant de le résoudre...",
				drift: "Suivre le décalage pour entendre ce qu’il révèle...",
				inertia: "Laisser le temps et la profondeur faire remonter la voix de l’autre...",
			},
		},
	};

	const normalizeSignalRecipient = (value) => value.toLowerCase().trim();
	const recipientDirectory = optionNodes.map((option) => ({
		value: option.getAttribute("value") || "",
		slug: option.dataset.slug || option.getAttribute("value") || "",
		username: option.dataset.username || option.textContent?.trim() || option.getAttribute("value") || "",
		phase: option.dataset.phase || "drift",
		phaseLabel: option.dataset.phaseLabel || "déphasage léger",
		summary: option.dataset.summary || "",
		lambda: option.dataset.lambda || "548",
		gap: option.dataset.gap || "0",
	}));

	const findRecipientMatch = (query) => {
		const normalized = normalizeSignalRecipient(query);
		if (!normalized) {
			return null;
		}

		const exactMatch = recipientDirectory.find((entry) => {
			return [entry.value, entry.slug, entry.username].some((candidate) => normalizeSignalRecipient(candidate) === normalized);
		});
		if (exactMatch) {
			return exactMatch;
		}

		return recipientDirectory.find((entry) => {
			return [entry.value, entry.slug, entry.username].some((candidate) => normalizeSignalRecipient(candidate).includes(normalized));
		}) || null;
	};

	const preferredPhasesByMode = {
		douceur: ["phase-locked", "harmonic"],
		confrontation: ["interference", "drift"],
		ecoute: ["inertia", "harmonic", "drift"],
	};

	const getSavedAlgoraMode = () => {
		try {
			const stored = window.localStorage.getItem(algoraStorageKey);
			return stored && algoraCopy[stored] ? stored : "douceur";
		} catch {
			return "douceur";
		}
	};

	const saveAlgoraMode = (mode) => {
		try {
			window.localStorage.setItem(algoraStorageKey, mode);
		} catch {
			// Ignore persistence failures.
		}
	};

	if (history) {
		history.scrollTop = history.scrollHeight;
	}

	const renderUnreadLabel = (count) => {
		const unreadCount = Math.max(0, Number.parseInt(count, 10) || 0);
		return `${unreadCount} message${unreadCount > 1 ? "s" : ""} non lu${unreadCount > 1 ? "s" : ""}`;
	};

	const updateUnreadLabels = (count) => {
		unreadLabels.forEach((node) => {
			if (node instanceof HTMLElement) {
				node.textContent = renderUnreadLabel(count);
			}
		});
	};

	const updateLiveIndicator = (message) => {
		if (liveIndicator instanceof HTMLElement && typeof message === "string" && message.trim()) {
			liveIndicator.textContent = message;
		}
	};

	if (filterInput instanceof HTMLInputElement && contactItems.length > 0) {
		const applyFilter = () => {
			const query = filterInput.value.toLowerCase().trim();
			let visibleCount = 0;

			contactItems.forEach((item) => {
				const haystack = [
					item.getAttribute("data-signal-contact-name") || "",
					item.getAttribute("data-signal-contact-slug") || "",
					item.getAttribute("data-signal-contact-last") || "",
				].join(" ");
				const visible = query === "" || haystack.includes(query);
				item.hidden = !visible;
				if (visible) {
					visibleCount += 1;
				}
			});

			const list = document.querySelector("[data-signal-contact-list]");
			if (list instanceof HTMLElement) {
				list.dataset.empty = visibleCount === 0 ? "1" : "0";
			}
		};

		filterInput.addEventListener("input", applyFilter);
		applyFilter();
	}

	if (openInput instanceof HTMLInputElement && !openInput.value) {
		const activeContact = document.querySelector("[data-signal-contact-item].is-active strong");
		if (activeContact instanceof HTMLElement) {
			openInput.value = activeContact.textContent.trim();
		}
	}

	recipientInputs.forEach((input) => {
		if (!(input instanceof HTMLInputElement)) {
			return;
		}

		const form = input.closest("form");
		const hintNode = form?.querySelector("[data-signal-recipient-hint]");
		const choiceNodes = Array.from(form?.querySelectorAll("[data-signal-recipient-choice]") || []);
		const algoraNodes = Array.from(form?.querySelectorAll("[data-signal-algora-choice]") || []);
		const subjectInput = form?.querySelector("[data-signal-draft-subject]");
		const bodyInput = form?.querySelector("[data-signal-draft-body]");
		let algoraMode = getSavedAlgoraMode();
		const defaultHint = hintNode instanceof HTMLElement ? hintNode.textContent : "";

		const applyPlaceholders = (phase = null) => {
			const copy = algoraCopy[algoraMode] || algoraCopy.douceur;
			const resolvedPhase = phase || "phase-locked";
			const bodyPlaceholder = copy.body[resolvedPhase] || copy.body.drift;

			if (input.dataset.signalOpenInput !== undefined || input.hasAttribute("data-signal-open-input")) {
				input.placeholder = copy.openPlaceholder;
			} else {
				input.placeholder = copy.recipientPlaceholder;
			}

			if (subjectInput instanceof HTMLInputElement && !subjectInput.value) {
				subjectInput.placeholder = form?.dataset.draftScope === "new"
					? copy.subjectPlaceholder
					: copy.threadSubjectPlaceholder;
			}

			if (bodyInput instanceof HTMLTextAreaElement && !bodyInput.value) {
				bodyInput.placeholder = bodyPlaceholder;
			}
		};

		const refreshSuggestionPriority = () => {
			const preferredPhases = preferredPhasesByMode[algoraMode] || [];
			choiceNodes.forEach((node, index) => {
				if (!(node instanceof HTMLElement)) {
					return;
				}

				const phase = recipientDirectory.find((entry) => entry.slug === (node.dataset.recipientValue || ""))?.phase || "drift";
				const preferredIndex = preferredPhases.indexOf(phase);
				node.classList.toggle("is-algora-preferred", preferredIndex !== -1);
				node.style.order = String(preferredIndex !== -1 ? preferredIndex : preferredPhases.length + index);
			});
		};

		const renderRecipientHint = () => {
			if (!(hintNode instanceof HTMLElement)) {
				return;
			}

			const match = findRecipientMatch(input.value);
			if (!match) {
				hintNode.textContent = (algoraCopy[algoraMode] || algoraCopy.douceur).fallbackHint || defaultHint || "Choisis une terre et la phase apparaîtra ici.";
				applyPlaceholders(null);
			} else {
				hintNode.textContent = `${match.username} · λ ${match.lambda} nm · Δ ${match.gap} nm · ${match.phaseLabel} — ${match.summary}`;
				applyPlaceholders(match.phase);
			}

			const query = normalizeSignalRecipient(input.value);
			choiceNodes.forEach((node) => {
				if (!(node instanceof HTMLElement)) {
					return;
				}

				const haystack = normalizeSignalRecipient(node.dataset.recipientSearch || node.dataset.recipientValue || "");
				const visible = query === "" || haystack.includes(query);
				node.hidden = !visible;
			});
		};

		algoraNodes.forEach((node) => {
			if (!(node instanceof HTMLButtonElement)) {
				return;
			}

			const nodeMode = node.dataset.algoraMode || "douceur";
			node.classList.toggle("is-active", nodeMode === algoraMode);
			node.addEventListener("click", () => {
				algoraMode = nodeMode;
				saveAlgoraMode(algoraMode);
				algoraNodes.forEach((otherNode) => {
					if (otherNode instanceof HTMLButtonElement) {
						otherNode.classList.toggle("is-active", (otherNode.dataset.algoraMode || "") === algoraMode);
					}
				});
				refreshSuggestionPriority();
				renderRecipientHint();
			});
		});

		choiceNodes.forEach((node) => {
			if (!(node instanceof HTMLButtonElement)) {
				return;
			}

			node.addEventListener("click", () => {
				input.value = node.dataset.recipientValue || "";
				input.dispatchEvent(new Event("input", { bubbles: true }));
				input.focus();
			});
		});

		refreshSuggestionPriority();
		input.addEventListener("input", renderRecipientHint);
		input.addEventListener("change", renderRecipientHint);
		renderRecipientHint();
	});

	composeForms.forEach((form) => {
		if (!(form instanceof HTMLFormElement)) {
			return;
		}

		const subjectInput = form.querySelector("[data-signal-draft-subject]");
		const bodyInput = form.querySelector("[data-signal-draft-body]");
		const receiverInput = form.querySelector('input[name="receiver_slug"]');
		const statusNode = form.querySelector("[data-signal-draft-status]");
		const draftScope = form.dataset.draftScope || `${window.location.pathname}${window.location.search}`;
		const storageKey = `o-signal-draft:${draftScope}`;

		const renderStatus = (message) => {
			if (statusNode instanceof HTMLElement && message) {
				statusNode.textContent = message;
			}
		};

		const restoreDraft = () => {
			try {
				const raw = window.localStorage.getItem(storageKey);
				if (!raw) {
					return;
				}

				const draft = JSON.parse(raw);
				if (subjectInput instanceof HTMLInputElement && typeof draft.subject === "string" && !subjectInput.value) {
					subjectInput.value = draft.subject;
				}
				if (bodyInput instanceof HTMLTextAreaElement && typeof draft.body === "string" && !bodyInput.value) {
					bodyInput.value = draft.body;
				}
				if (receiverInput instanceof HTMLInputElement && typeof draft.receiver === "string" && !receiverInput.value) {
					receiverInput.value = draft.receiver;
				}
				if ((draft.subject || draft.body || draft.receiver)) {
					renderStatus("Brouillon restauré localement. ⌘/Ctrl + Entrée envoie.");
				}
			} catch {
				// Ignore draft restore failures.
			}
		};

		const persistDraft = () => {
			try {
				const subject = subjectInput instanceof HTMLInputElement ? subjectInput.value : "";
				const body = bodyInput instanceof HTMLTextAreaElement ? bodyInput.value : "";
				const receiver = receiverInput instanceof HTMLInputElement ? receiverInput.value : "";
				if (!subject.trim() && !body.trim() && !receiver.trim()) {
					window.localStorage.removeItem(storageKey);
					renderStatus("Brouillon vide. ⌘/Ctrl + Entrée envoie.");
					return;
				}

				window.localStorage.setItem(storageKey, JSON.stringify({
					subject,
					body,
					receiver,
					updatedAt: Date.now(),
				}));
				renderStatus("Brouillon gardé localement. ⌘/Ctrl + Entrée envoie.");
			} catch {
				// Ignore draft persistence failures.
			}
		};

		restoreDraft();

		[subjectInput, bodyInput, receiverInput].forEach((field) => {
			if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLTextAreaElement)) {
				return;
			}

			field.addEventListener("input", persistDraft);
		});

		if (bodyInput instanceof HTMLTextAreaElement) {
			bodyInput.addEventListener("keydown", (event) => {
				if ((event.metaKey || event.ctrlKey) && event.key === "Enter") {
					event.preventDefault();
					renderStatus("Transmission en cours...");
					form.requestSubmit();
				}
			});
		}

		form.addEventListener("submit", () => {
			try {
				window.localStorage.removeItem(storageKey);
			} catch {
				// Ignore cleanup failures.
			}
			renderStatus("Transmission en cours...");
		});
	});

	if (liveRoot instanceof HTMLElement && liveHistory instanceof HTMLElement) {
		const apiPath = liveRoot.dataset.liveApi || "/signal_live.php";
		const liveView = liveRoot.dataset.liveView || "signal";
		const pollInterval = Math.max(1400, Number.parseInt(liveRoot.dataset.liveInterval || "2500", 10) || 2500);
		let liveHash = liveRoot.dataset.liveHash || "";
		let liveMessageCount = Number.parseInt(liveRoot.dataset.liveMessageCount || "0", 10) || 0;
		let inflight = false;
		let timerId = 0;

		const shouldStickToBottom = () => {
			return (liveHistory.scrollHeight - liveHistory.scrollTop - liveHistory.clientHeight) < 72;
		};

		const scrollLiveHistoryToBottom = () => {
			liveHistory.scrollTop = liveHistory.scrollHeight;
		};

		const currentTarget = () => (liveRoot.dataset.liveTarget || "").trim();

		const pollLiveThread = async () => {
			const target = currentTarget();
			if (!target || inflight || document.hidden) {
				return;
			}

			inflight = true;
			updateLiveIndicator("direct · synchro");

			try {
				const url = new URL(apiPath, window.location.origin);
				url.searchParams.set("view", liveView);
				url.searchParams.set("u", target);

				const response = await fetch(url.toString(), {
					method: "GET",
					headers: { Accept: "application/json" },
					credentials: "same-origin",
					cache: "no-store",
				});

				if (!response.ok) {
					throw new Error(`HTTP ${response.status}`);
				}

				const payload = await response.json();
				if (!payload || payload.ok === false) {
					throw new Error("invalid-payload");
				}

				const wasNearBottom = shouldStickToBottom();
				const nextHash = typeof payload.history_hash === "string" ? payload.history_hash : "";
				const nextMessageCount = Number.parseInt(String(payload.message_count ?? liveMessageCount), 10) || 0;
				const messageCountIncreased = nextMessageCount > liveMessageCount;

				if (typeof payload.history_html === "string" && nextHash !== liveHash) {
					liveHistory.innerHTML = payload.history_html;
					liveHash = nextHash;
					liveRoot.dataset.liveHash = nextHash;
				}

				liveMessageCount = nextMessageCount;
				liveRoot.dataset.liveMessageCount = String(nextMessageCount);

				if (typeof payload.unread_total !== "undefined") {
					updateUnreadLabels(payload.unread_total);
				}

				if (liveView === "echo" && echoContactsRoot instanceof HTMLElement && typeof payload.echo_contacts_html === "string") {
					echoContactsRoot.innerHTML = payload.echo_contacts_html;
				}

				if (payload.target && typeof payload.target.slug === "string" && payload.target.slug) {
					liveRoot.dataset.liveTarget = payload.target.slug;
				}

				if (wasNearBottom || messageCountIncreased) {
					scrollLiveHistoryToBottom();
				}

				updateLiveIndicator("direct · temps réel");
			} catch (error) {
				console.error("Impossible de rafraîchir la messagerie en direct", error);
				updateLiveIndicator("direct · interrompu");
			} finally {
				inflight = false;
			}
		};

		if (currentTarget()) {
			scrollLiveHistoryToBottom();
			pollLiveThread();
			timerId = window.setInterval(pollLiveThread, pollInterval);
			document.addEventListener("visibilitychange", () => {
				if (!document.hidden) {
					pollLiveThread();
				}
			});
			window.addEventListener("focus", pollLiveThread);
			window.addEventListener("beforeunload", () => {
				if (timerId) {
					window.clearInterval(timerId);
				}
			});
		} else {
			updateLiveIndicator("direct · en attente");
		}
	}
}

initCornerDocks();
initGuideVoice();
initSignalFlow();
initSpectralTuner();
initStr3mArchipelago();
initStr3mParallax();

function initAzaTabs() {
	const tabs = document.querySelectorAll('.aza-tab[data-tab]');
	if (!tabs.length) return;

	tabs.forEach((tab) => {
		tab.addEventListener('click', () => {
			const targetId = tab.dataset.tab;
			tabs.forEach((t) => {
				t.classList.toggle('aza-tab-active', t === tab);
				t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
			});
			document.querySelectorAll('.aza-tab-panel').forEach((panel) => {
				panel.classList.toggle('aza-tab-panel-hidden', panel.id !== targetId);
			});
		});
	});
}

initAzaTabs();

function initB0t3() {
	// Poetic substitution map — noise that keeps meaning partial
	const subs = {
		a: ['@','ä','à','â','α','∂'],
		e: ['3','ë','è','ê','ε','∃'],
		i: ['1','ï','î','ι','|','!'],
		o: ['0','ö','ô','ø','ο','°'],
		u: ['ü','û','υ','μ','∪'],
		s: ['5','$','ş','ś','∫'],
		n: ['η','ñ','∩','~'],
		t: ['τ','+','†','⊤'],
		r: ['г','ŗ','√','®'],
		l: ['ł','|','λ','ℓ'],
		c: ['¢','ç','©','⌀'],
		p: ['þ','ρ','℗','π'],
		m: ['μ','ṁ','∓'],
		g: ['9','ĝ','γ'],
		b: ['β','ƀ','6'],
		d: ['δ','∂','ð'],
		f: ['ƒ','φ'],
		h: ['ħ','η','#'],
		k: ['κ','ķ'],
		v: ['ν','√','∨'],
		w: ['ω','ŵ','∧'],
		x: ['×','χ','ξ'],
		y: ['ψ','ÿ','¥'],
		z: ['ζ','ż','2'],
	};

	function brouille(char, instability) {
		if (char === ' ' || char === '\n') return char;
		if (Math.random() > instability) return char;
		const lower = char.toLowerCase();
		const pool  = subs[lower];
		if (!pool) return char;
		const sub = pool[Math.floor(Math.random() * pool.length)];
		return char === char.toUpperCase() ? sub.toUpperCase() : sub;
	}

	function renderLine(el, text, instability) {
		el.textContent = text.split('').map(c => brouille(c, instability)).join('');
	}

	function burstDeform(el, text, instability) {
		let frame = 0;
		const id = setInterval(() => {
			renderLine(el, text, 0.85);
			if (++frame >= 12) {
				clearInterval(id);
				renderLine(el, text, instability * 0.4);
			}
		}, 40);
	}

	document.querySelectorAll('[data-b0t3]').forEach(el => {
		const text        = (el.dataset.b0t3 || '').trim();
		const instability = parseFloat(el.dataset.b0t3Instability || '0.25');
		if (!text) return;

		// Ambient drift — gentle, slow
		renderLine(el, text, instability * 0.08);
		setInterval(() => renderLine(el, text, instability * 0.08), 1800 + Math.random() * 1200);

		// Deform on long press or click
		let pressTimer = null;
		let pressing   = false;

		el.style.cursor = 'pointer';
		el.style.userSelect = 'none';

		el.addEventListener('pointerdown', () => {
			pressing   = true;
			pressTimer = setTimeout(() => {
				if (pressing) burstDeform(el, text, instability);
			}, 420);
		});

		el.addEventListener('pointerup',     () => { pressing = false; clearTimeout(pressTimer); });
		el.addEventListener('pointerleave',  () => { pressing = false; clearTimeout(pressTimer); });
		el.addEventListener('click',         () => burstDeform(el, text, instability));
	});

	// Live preview in deposit form
	const input = document.querySelector('.b0t3-input');
	if (input) {
		let previewEl = document.querySelector('.b0t3-preview');
		if (!previewEl) {
			previewEl = document.createElement('span');
			previewEl.className = 'b0t3-preview b0t3-line';
			input.parentNode.insertBefore(previewEl, input.nextSibling);
		}
		input.addEventListener('input', () => {
			const val = input.value;
			previewEl.dataset.b0t3 = val;
			previewEl.dataset.b0t3Instability = document.querySelector('.b0t3-instability-range')?.value || '0.25';
			previewEl.textContent = val;
			// re-init this element
			previewEl.removeAttribute('data-b0t3-init');
			initB0t3SingleEl(previewEl);
		});
	}
}

function initB0t3SingleEl(el) {
	const text        = (el.dataset.b0t3 || '').trim();
	const instability = parseFloat(el.dataset.b0t3Instability || '0.25');
	if (!text) return;
	el.textContent = text.split('').map(c => {
		if (c === ' ') return c;
		const subs = { a:'@',e:'3',i:'1',o:'0',s:'5',t:'τ',n:'η' };
		return Math.random() < instability * 0.08 ? (subs[c.toLowerCase()] || c) : c;
	}).join('');
}

initB0t3();
