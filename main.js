const DEFAULT_TIMEZONE = "Europe/Paris";
const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
const coarsePointer = window.matchMedia("(pointer: coarse)").matches || (navigator.maxTouchPoints || 0) > 0;
const THEME_KEY = "o-theme-inverted";

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

applyThemeState(readThemeState());

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

function resolveTorusProfile(canvas) {
	const bodyStyles = getComputedStyle(document.body);
	const landType = canvas.dataset.landType || document.body.dataset.landProgram || "collective";
	const lambda = Number.parseFloat(canvas.dataset.lambda || document.body.dataset.landLambda || "548");
	const mood = canvas.dataset.streamMood || "calm";
	const base = lambdaToRgb(lambda);
	const accent = parseRgbTriplet(bodyStyles.getPropertyValue("--land-accent-rgb"), base);
	const secondary = parseRgbTriplet(bodyStyles.getPropertyValue("--land-secondary-rgb"), accent);
	const glow = parseRgbTriplet(bodyStyles.getPropertyValue("--land-glow-rgb"), secondary);

	if (landType === "dur3rb") {
		const luminance = Math.round(base[0] * 0.299 + base[1] * 0.587 + base[2] * 0.114);
		const grayscale = [luminance, luminance, luminance];
		return {
			primary: mixRgb(grayscale, accent, 0.12),
			secondary: mixRgb(grayscale, glow, 0.08),
			glow,
			waveStrength: 0.58,
			pulseStrength: 0.44,
			signalMode: false,
			motion: mood === "dense" ? 1.08 : 0.9,
		};
	}

	if (landType === "tocu") {
		return {
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
	}

	if (landType === "culbu1on") {
		return {
			primary: mixRgb(base, accent, 0.52),
			secondary: mixRgb(base, secondary, 0.7),
			glow,
			waveStrength: 0.88,
			pulseStrength: 0.62,
			signalMode: false,
			motion: mood === "calm" ? 0.98 : 1.1,
		};
	}

	return {
		primary: mixRgb(base, accent, 0.32),
		secondary: mixRgb(base, secondary, 0.46),
		glow,
		waveStrength: mood === "nocturnal" ? 0.74 : 0.68,
		pulseStrength: mood === "dense" ? 0.68 : 0.52,
		signalMode: false,
		motion: mood === "nocturnal" ? 1.06 : 0.92,
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
			updateTouchHint();
		}, 360);
	}

	function triggerSecretAccess() {
		canvas.classList.add("is-secret-open");
		const guidePath = "/0wlslw0";
		window.setTimeout(() => {
			canvas.classList.remove("is-secret-open");
			if (window.location.pathname === guidePath) {
				toggleThemeState();
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
			canvas.classList.add("is-dragging");
			canvas.focus({ preventScroll: true });
			canvas.setPointerCapture(event.pointerId);
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
			}

			if (state.longPressActive) {
				const direction = detectCardinalDirection(travelX, travelY, travelDistance, 18, 1.08);
				setLongPressDirection(direction || "");
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

function renderSignupPreview() {
	if (!previewShell) {
		return;
	}

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
	if (target instanceof HTMLElement && ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName)) {
		return;
	}

	toggleThemeState();
});

document.addEventListener("dblclick", (event) => {
	const target = event.target;
	if (!(target instanceof Element)) {
		return;
	}

	if (target.closest("a, button, input, textarea, select, summary, label")) {
		return;
	}

	if (
		target === document.body ||
		target.classList.contains("noise") ||
		target.classList.contains("aurora") ||
		target === document.documentElement
	) {
		toggleThemeState();
	}
});

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

if ("serviceWorker" in navigator) {
	window.addEventListener("load", () => {
		navigator.serviceWorker.register("/site-sw.js").catch(() => {
			// Fail silently: the site still works as a regular document.
		});
	});
}

function initGuideVoice() {
	const root = document.querySelector("[data-guide-voice]");
	if (!(root instanceof HTMLElement)) {
		return;
	}

	const startButton = root.querySelector("[data-guide-voice-start]");
	const stopButton = root.querySelector("[data-guide-voice-stop]");
	const statusNode = root.querySelector("[data-guide-voice-status]");
	const transcriptNode = root.querySelector("[data-guide-voice-transcript]");
	const replyNode = root.querySelector("[data-guide-voice-reply]");
	const routeLink = root.querySelector("[data-guide-voice-route]");
	const RecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition || null;
	const synth = "speechSynthesis" in window ? window.speechSynthesis : null;
	const greeting = root.dataset.guideVoiceGreeting || "Je suis 0wlslw0. Dis-moi ce que tu veux faire.";
	const apiPath = root.dataset.guideVoiceApi || "/0wlslw0/voice";
	const csrfToken = root.dataset.guideVoiceCsrf || "";
	const chatUrl = root.dataset.guideVoiceChatUrl || "";
	let recognition = null;
	let isActive = false;
	let isSpeaking = false;
	let isWaitingReply = false;

	function setState(state, statusText = "") {
		root.dataset.voiceState = state;
		if (statusNode instanceof HTMLElement && statusText) {
			statusNode.textContent = statusText;
		}
	}

	function setTranscript(text) {
		if (transcriptNode instanceof HTMLElement && text) {
			transcriptNode.textContent = text;
		}
	}

	function setReply(text) {
		if (replyNode instanceof HTMLElement && text) {
			replyNode.textContent = text;
		}
	}

	function hideRoute() {
		if (!(routeLink instanceof HTMLAnchorElement)) {
			return;
		}

		routeLink.hidden = true;
		routeLink.textContent = "Continuer";
		routeLink.setAttribute("href", "#");
	}

	function showRoute(route) {
		if (!(routeLink instanceof HTMLAnchorElement) || !route || !route.href) {
			hideRoute();
			return;
		}

		routeLink.hidden = false;
		routeLink.href = route.href;
		routeLink.textContent = route.label || "Continuer";
	}

	function beginListening() {
		if (!recognition || !isActive || isSpeaking || isWaitingReply) {
			return;
		}

		try {
			recognition.start();
			setState("listening", "J’écoute. Parle naturellement.");
		} catch {
			// Recognition may already be starting; ignore the duplicate start.
		}
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

	function speakReply(text, onDone) {
		if (!text || !synth || typeof window.SpeechSynthesisUtterance !== "function") {
			onDone?.();
			return;
		}

		isSpeaking = true;
		setState("speaking", "Je te réponds à voix haute.");
		synth.cancel();

		const utterance = new window.SpeechSynthesisUtterance(text);
		utterance.lang = "fr-FR";
		utterance.rate = 1;
		utterance.pitch = 1;
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
		}
		if (stopButton instanceof HTMLElement) {
			stopButton.hidden = true;
		}
		setState("idle", message);
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
				if (route && route.auto_navigate && route.href) {
					window.location.assign(route.href);
					return;
				}

				if (isActive) {
					window.setTimeout(beginListening, 260);
				} else {
					setState("idle", "Prêt si tu veux reprendre.");
				}
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

	if (!(startButton instanceof HTMLElement) || !(stopButton instanceof HTMLElement)) {
		return;
	}

	hideRoute();
	setState("idle", "Prêt. Active la voix puis parle naturellement.");

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
			sendUtterance(finalTranscript.trim());
		}
	};

	recognition.onerror = (event) => {
		if (!isActive) {
			return;
		}

		const errorCode = event?.error || "unknown";
		if (errorCode === "not-allowed" || errorCode === "service-not-allowed") {
			stopGuide("Le micro est bloqué. Autorise le micro puis réessaie.");
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
		isActive = true;
		isWaitingReply = false;
		hideRoute();
		startButton.hidden = true;
		stopButton.hidden = false;
		setReply("0wlslw0 répondra ici puis lira sa réponse à voix haute.");

		speakReply(greeting, () => {
			if (isActive) {
				window.setTimeout(beginListening, 260);
			}
		});
	});

	stopButton.addEventListener("click", () => {
		stopGuide("Voix coupée. Tu peux relancer quand tu veux.");
	});

	if (routeLink instanceof HTMLAnchorElement) {
		routeLink.addEventListener("click", () => {
			stopGuide("Navigation en cours.");
		});
	}
}

initGuideVoice();
