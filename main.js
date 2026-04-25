const DEFAULT_TIMEZONE = "Europe/Paris";
const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
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

function initTorusCloud(canvas) {
	const context = canvas.getContext("2d");
	if (!context) {
		return;
	}

	const state = {
		width: 0,
		height: 0,
		devicePixelRatio: 1,
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
	};

	const torusScale = 11;
	const ringCount = 96;
	const tubeCount = 42;
	const majorRadius = 1.9 * torusScale;
	const minorRadius = 0.78 * torusScale;
	const zoomMin = 6.5;
	const zoomMax = 17.5;

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
		canvas.width = Math.round(state.width * state.devicePixelRatio);
		canvas.height = Math.round(state.height * state.devicePixelRatio);
		context.setTransform(state.devicePixelRatio, 0, 0, state.devicePixelRatio, 0, 0);
	}

	function readPalette() {
		const styles = getComputedStyle(document.body);
		const fallback = document.body.classList.contains("is-inverted") ? [18, 69, 52] : [227, 219, 200];
		return parseRgbTriplet(styles.getPropertyValue("--fg"), parseRgbTriplet(styles.color, fallback));
	}

	function clamp(value, min, max) {
		return Math.min(max, Math.max(min, value));
	}

	function normalizeKey(key) {
		return key.length === 1 ? key.toLowerCase() : key;
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

		const [red, green, blue] = readPalette();
		stepNavigation();

		const centerX = width * 0.5 + state.panX * (width * 0.018);
		const centerY = height * 0.5 + state.panY * (height * 0.018);
		const camera = 39 - state.zoom * 1.12;
		const scale = Math.min(width, height) * (0.7 + state.zoom * 0.078);
		const spinY = state.yaw + time * 0.00006;
		const spinX = state.pitch + Math.sin(time * 0.00012) * 0.05;
		const spinZ = state.roll + Math.cos(time * 0.00009) * 0.03;

		context.clearRect(0, 0, width, height);

		const rendered = state.points.map((point) => {
			const ripple = Math.sin(time * 0.0012 + point.phase) * (0.028 * torusScale);
			const radial = point.radiusDrift + ripple;
			const localX = (majorRadius + radial * Math.cos(point.phi)) * Math.cos(point.theta);
			const localY = (majorRadius + radial * Math.cos(point.phi)) * Math.sin(point.theta);
			const localZ = radial * Math.sin(point.phi);

			const rotateYx = localX * Math.cos(spinY) + localZ * Math.sin(spinY);
			const rotateYz = -localX * Math.sin(spinY) + localZ * Math.cos(spinY);
			const rotateXy = localY * Math.cos(spinX) - rotateYz * Math.sin(spinX);
			const rotateXz = localY * Math.sin(spinX) + rotateYz * Math.cos(spinX);
			const finalX = rotateYx * Math.cos(spinZ) - rotateXy * Math.sin(spinZ);
			const finalY = rotateYx * Math.sin(spinZ) + rotateXy * Math.cos(spinZ);
			const depth = rotateXz + camera;
			const perspective = scale / Math.max(depth, 3.2);
			const depthFactor = clamp((48 - depth) / 34, 0, 1);

			return {
				x: centerX + finalX * perspective,
				y: centerY + finalY * perspective,
				alpha: clamp(0.06 + depthFactor * 0.84 * point.density, 0.05, 0.92),
				radius: clamp(0.2 + depthFactor * 4.8 * point.density, 0.2, 5.8),
				depth,
			};
		});

		rendered.sort((left, right) => right.depth - left.depth);

		rendered.forEach((point) => {
			context.beginPath();
			context.fillStyle = `rgba(${red}, ${green}, ${blue}, ${point.alpha})`;
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

	canvas.addEventListener("pointerdown", (event) => {
		state.pointerId = event.pointerId;
		state.lastX = event.clientX;
		state.lastY = event.clientY;
		state.dragging = true;
		canvas.classList.add("is-dragging");
		canvas.focus({ preventScroll: true });
		canvas.setPointerCapture(event.pointerId);
	});

	canvas.addEventListener("pointermove", (event) => {
		if (!state.dragging || state.pointerId !== event.pointerId) {
			return;
		}

		const deltaX = event.clientX - state.lastX;
		const deltaY = event.clientY - state.lastY;
		state.lastX = event.clientX;
		state.lastY = event.clientY;
		state.velocityYaw += deltaX * 0.00058;
		state.velocityPitch += deltaY * 0.00042;
		state.velocityPanX += deltaX * 0.0022;
		state.velocityPanY += deltaY * 0.0016;
		refreshStaticFrame();
	});

	function releasePointer(event) {
		if (event && state.pointerId !== null && canvas.hasPointerCapture(state.pointerId)) {
			canvas.releasePointerCapture(state.pointerId);
		}

		state.pointerId = null;
		state.dragging = false;
		canvas.classList.remove("is-dragging");
		refreshStaticFrame();
	}

	canvas.addEventListener("pointerup", releasePointer);
	canvas.addEventListener("pointercancel", releasePointer);
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
		releasePointer();
	});

	resize();
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
