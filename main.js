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
