(() => {
	const doc = document;
	const win = window;
	const body = doc.body;

	if (!body) {
		return;
	}

	const activateReveals = () => {
		const nodes = doc.querySelectorAll(".reveal");
		if (!nodes.length) {
			return;
		}

		win.requestAnimationFrame(() => {
			nodes.forEach((node) => node.classList.add("on"));
		});
	};

	const fullBundleMeta = doc.querySelector('meta[name="o-main-bundle"]');
	const fullBundleSrc = fullBundleMeta ? fullBundleMeta.getAttribute("content") || "" : "";
	if (fullBundleSrc === "") {
		activateReveals();
		return;
	}

	let fullBundleLoaded = false;
	const loadFullBundle = () => {
		if (fullBundleLoaded) {
			return;
		}

		fullBundleLoaded = true;
		const script = doc.createElement("script");
		script.defer = true;
		script.src = fullBundleSrc;
		script.dataset.sowwwlEscalated = "1";
		doc.head.appendChild(script);
	};

	const escalationSelectors = [
		"[data-torus-cloud]",
		"[data-preview-shell]",
		"[data-signup-program-input]",
		"[data-signup-lambda-input]",
		".signup-journey-form",
	];

	const registerEscalation = () => {
		const targets = doc.querySelectorAll(escalationSelectors.join(","));
		if (!targets.length) {
			return;
		}

		const once = { once: true };
		targets.forEach((target) => {
			target.addEventListener("pointerdown", loadFullBundle, once);
			target.addEventListener("focusin", loadFullBundle, once);
			target.addEventListener("keydown", loadFullBundle, once);
			target.addEventListener("touchstart", loadFullBundle, once);
			target.addEventListener("input", loadFullBundle, once);
		});
	};

	const scheduleAmbientEscalation = () => {
		const torusSurface = doc.querySelector("[data-torus-cloud]");
		if (!(torusSurface instanceof HTMLElement)) {
			return;
		}

		if (typeof win.requestIdleCallback === "function") {
			win.requestIdleCallback(() => loadFullBundle(), { timeout: 900 });
			return;
		}

		win.setTimeout(loadFullBundle, 180);
	};

	activateReveals();
	registerEscalation();
	scheduleAmbientEscalation();
})();
