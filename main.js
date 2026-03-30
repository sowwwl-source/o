const SYSTEM = { mode: "silent", domain: "sowwwl.xyz" };

const reveals = Array.from(document.querySelectorAll(".reveal"));
const tzInput = document.querySelector('input[name="timezone"]');
const tzLabel = document.getElementById("tz-label");
const tzTime = document.getElementById("tz-time");
const bootline = document.getElementById("bootline");

if ("IntersectionObserver" in window && reveals.length) {
	const observer = new IntersectionObserver(
		(entries) => {
			entries.forEach((entry) => {
				if (entry.isIntersecting) {
					entry.target.classList.add("on");
					observer.unobserve(entry.target);
				}
			});
		},
		{ threshold: 0.2 }
	);

	reveals.forEach((el) => observer.observe(el));
} else {
	reveals.forEach((el) => el.classList.add("on"));
}

function formatTimeInZone(timezone) {
	try {
		const now = new Date();
		const formatted = new Intl.DateTimeFormat("fr-FR", {
			timeZone: timezone,
			hour: "2-digit",
			minute: "2-digit",
			second: "2-digit",
			hour12: false,
		}).format(now);

		tzLabel.textContent = `Fuseau : ${timezone}`;
		tzTime.textContent = formatted;
		return true;
	} catch {
		tzLabel.textContent = "Fuseau : invalide";
		tzTime.textContent = "--:--:--";
		return false;
	}
}

let activeTimezone = "Europe/Paris";
formatTimeInZone(activeTimezone);

if (tzInput) {
	tzInput.addEventListener("input", (event) => {
		const value = event.target.value.trim();
		if (!value) {
			activeTimezone = "Europe/Paris";
			formatTimeInZone(activeTimezone);
			return;
		}

		if (formatTimeInZone(value)) {
			activeTimezone = value;
		}
	});
}

if (bootline) {
	const fullText = bootline.textContent;
	let i = 0;
	bootline.textContent = "";

	const typer = setInterval(() => {
		bootline.textContent += fullText[i] ?? "";
		i += 1;
		if (i >= fullText.length) {
			clearInterval(typer);
		}
	}, 28);
}

setInterval(() => {
	formatTimeInZone(activeTimezone);
}, 1000);

document.addEventListener("keydown", (event) => {
	if (event.key.toLowerCase() === "n") {
		document.documentElement.classList.toggle("negative");
	}

	if (event.key.toLowerCase() === "v") {
		document.body.classList.toggle("vortex-shift");
	}
});

let drift = 0;
setInterval(() => {
	drift += 0.015;
	document.documentElement.style.setProperty("--drift", `${Math.sin(drift) * 4}px`);
}, 120);