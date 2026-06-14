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
	const str3mBundleMeta = doc.querySelector('meta[name="o-main-str3m-bundle"]');
	const str3mBundleSrc = str3mBundleMeta ? str3mBundleMeta.getAttribute("content") || "" : "";
	const sceptreBundleMeta = doc.querySelector('meta[name="o-main-sceptre-bundle"]');
	const sceptreBundleSrc = sceptreBundleMeta ? sceptreBundleMeta.getAttribute("content") || "" : "";
	const landscapeBundleMeta = doc.querySelector('meta[name="o-main-landscape-bundle"]');
	const landscapeBundleSrc = landscapeBundleMeta ? landscapeBundleMeta.getAttribute("content") || "" : "";
	const islandBundleMeta = doc.querySelector('meta[name="o-main-island-bundle"]');
	const islandBundleSrc = islandBundleMeta ? islandBundleMeta.getAttribute("content") || "" : "";
	const pageBundleMeta = doc.querySelector('meta[name="o-main-pages-bundle"]');
	const pageBundleSrc = pageBundleMeta ? pageBundleMeta.getAttribute("content") || "" : "";
	if (fullBundleSrc === "") {
		activateReveals();
		return;
	}

	let fullBundleLoaded = false;
	const appendScript = (src, datasetKey, onLoad) => {
		if (!src) {
			if (typeof onLoad === "function") {
				onLoad();
			}
			return;
		}

		const script = doc.createElement("script");
		script.defer = true;
		script.async = false;
		script.src = src;
		script.dataset[datasetKey] = "1";
		if (typeof onLoad === "function") {
			script.addEventListener("load", onLoad, { once: true });
		}
		doc.head.appendChild(script);
	};
	const loadFullBundle = () => {
		if (fullBundleLoaded) {
			return;
		}

		fullBundleLoaded = true;
		appendScript(fullBundleSrc, "sowwwlEscalated", () => {
			const loadSceptreBundle = () => {
				const loadLandscapeBundle = () => {
					const loadIslandBundle = () => {
						const loadPageBundle = () => {
							if (pageBundleSrc && pageBundleSrc !== fullBundleSrc && pageBundleSrc !== str3mBundleSrc && pageBundleSrc !== sceptreBundleSrc && pageBundleSrc !== landscapeBundleSrc && pageBundleSrc !== islandBundleSrc) {
								appendScript(pageBundleSrc, "sowwwlEscalatedPages");
							}
						};

						if (islandBundleSrc && islandBundleSrc !== fullBundleSrc && islandBundleSrc !== str3mBundleSrc && islandBundleSrc !== sceptreBundleSrc && islandBundleSrc !== landscapeBundleSrc) {
							appendScript(islandBundleSrc, "sowwwlEscalatedIsland", loadPageBundle);
							return;
						}

						loadPageBundle();
					};

					if (landscapeBundleSrc && landscapeBundleSrc !== fullBundleSrc && landscapeBundleSrc !== str3mBundleSrc && landscapeBundleSrc !== sceptreBundleSrc) {
						appendScript(landscapeBundleSrc, "sowwwlEscalatedLandscape", loadIslandBundle);
						return;
					}

					loadIslandBundle();
				};

				if (sceptreBundleSrc && sceptreBundleSrc !== fullBundleSrc && sceptreBundleSrc !== str3mBundleSrc) {
					appendScript(sceptreBundleSrc, "sowwwlEscalatedSceptre", loadLandscapeBundle);
					return;
				}

				loadLandscapeBundle();
			};

			if (str3mBundleSrc && str3mBundleSrc !== fullBundleSrc) {
				appendScript(str3mBundleSrc, "sowwwlEscalatedStr3m", loadSceptreBundle);
				return;
			}

			loadSceptreBundle();
		});
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

	activateReveals();
	registerEscalation();
})();
