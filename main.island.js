function initIslandReaderStation() {
	const shell = document.querySelector("[data-island-reader-shell]");
	if (!(shell instanceof HTMLElement) || shell.dataset.islandReaderBound === "1") {
		return;
	}
	shell.dataset.islandReaderBound = "1";
	const isSpatialIoView = document.body.classList.contains("io-surface-view");

	const tabs = Array.from(shell.querySelectorAll("[data-island-reader-tab]"))
		.filter((tab) => tab instanceof HTMLButtonElement);
	const navItems = Array.from(shell.querySelectorAll("[data-island-reader-nav]"))
		.filter((item) => item instanceof HTMLButtonElement);
	const panels = Array.from(shell.querySelectorAll("[data-island-reader-panel]"))
		.filter((panel) => panel instanceof HTMLElement);
	const previousButton = shell.querySelector("[data-island-reader-prev]");
	const nextButton = shell.querySelector("[data-island-reader-next]");
	const autoplayButton = shell.querySelector("[data-island-reader-autoplay]");
	const counter = shell.querySelector("[data-island-reader-counter]");
	const currentLabel = shell.querySelector("[data-island-reader-current-label]");
	const currentMeta = shell.querySelector("[data-island-reader-current-meta]");
	const curatorCopy = shell.querySelector("[data-island-reader-curator-copy]");
	const recommendationLabel = shell.querySelector("[data-island-reader-recommendation-label]");
	const recommendationCopy = shell.querySelector("[data-island-reader-recommendation-copy]");

	if (!tabs.length || !panels.length) {
		return;
	}

	const availableKeys = tabs
		.filter((tab) => tab.dataset.islandReaderEmpty !== "1")
		.map((tab) => tab.dataset.islandReaderTab || "")
		.filter(Boolean);
	const autoplayDelayMs = 12000;
	let currentKey = "";
	let autoplayEnabled = false;
	let autoplayTimer = null;
	let userSteered = false;
	let spatialProfile = null;
	let latestRaState = readActiveIoRaSession();
	let latestWorldState = readActiveIoWorldInstrumentSession();
	const defaultCuratorCopy = curatorCopy instanceof HTMLElement
		? curatorCopy.textContent?.trim() || "La station garde le fil et peut deriver vers la matiere suivante."
		: "La station garde le fil et peut deriver vers la matiere suivante.";

	const formatCounter = (value, size) => String(value).padStart(2, "0") + " / " + String(size).padStart(2, "0");

	const getReaderMeta = (key) => {
		const tab = tabs.find((candidate) => candidate.dataset.islandReaderTab === key) || null;
		const navItem = navItems.find((candidate) => candidate.dataset.islandReaderNav === key) || null;
		const format = tab?.querySelector("small")?.textContent?.trim() || "veille";
		const source = navItem?.querySelector(".island-reader-playlist__line--meta small:last-child")?.textContent?.trim() || "Veille";
		const label = tab?.querySelector("span")?.textContent?.trim() || navItem?.querySelector("strong")?.textContent?.trim() || key;
		return { label, format, source };
	};

	const syncCurator = (key) => {
		if (!(currentLabel instanceof HTMLElement) && !(counter instanceof HTMLElement) && !(currentMeta instanceof HTMLElement)) {
			return;
		}

		const meta = getReaderMeta(key);
		const availableIndex = availableKeys.indexOf(key);
		const nextKey = availableIndex >= 0 && availableKeys.length
			? availableKeys[(availableIndex + 1) % availableKeys.length]
			: (availableKeys[0] || "");
		const nextMeta = nextKey ? getReaderMeta(nextKey) : null;
		const spatialPrimaryMeta = spatialProfile?.primary ? getReaderMeta(spatialProfile.primary) : null;
		const spatialSecondaryMeta = spatialProfile?.secondary ? getReaderMeta(spatialProfile.secondary) : null;

		if (currentLabel instanceof HTMLElement) {
			currentLabel.textContent = meta.label || "Veille";
		}

		if (currentMeta instanceof HTMLElement) {
			currentMeta.textContent = [meta.format, meta.source].filter(Boolean).join(" · ");
		}

		if (counter instanceof HTMLElement) {
			counter.textContent = availableIndex >= 0 ? formatCounter(availableIndex + 1, Math.max(availableKeys.length, 1)) : "veille";
		}

		if (recommendationLabel instanceof HTMLElement) {
			recommendationLabel.textContent = spatialPrimaryMeta?.label || nextMeta?.label || "Aucune suite";
		}

		if (recommendationCopy instanceof HTMLElement) {
			if (spatialPrimaryMeta) {
				const lead = spatialProfile?.primary === key
					? `Prise tenue : ${spatialPrimaryMeta.label} · ${spatialPrimaryMeta.format}.`
					: `Prise conseillee : ${spatialPrimaryMeta.label} · ${spatialPrimaryMeta.format}.`;
				const tail = spatialSecondaryMeta
					? ` Ensuite : ${spatialSecondaryMeta.label} · ${spatialSecondaryMeta.format}.`
					: (nextMeta ? ` Ensuite : ${nextMeta.label} · ${nextMeta.format}.` : "");
				recommendationCopy.textContent = `${lead}${tail}`.trim();
			} else {
				recommendationCopy.textContent = nextMeta
					? `Ensuite : ${nextMeta.label} · ${nextMeta.format}`
					: "Aucune matiere active recommandee pour l instant.";
			}
		}

		if (curatorCopy instanceof HTMLElement) {
			curatorCopy.textContent = spatialProfile?.note || defaultCuratorCopy;
		}

		if (previousButton instanceof HTMLButtonElement) {
			previousButton.disabled = availableKeys.length <= 1;
		}

		if (nextButton instanceof HTMLButtonElement) {
			nextButton.disabled = availableKeys.length <= 1;
		}

		if (autoplayButton instanceof HTMLButtonElement) {
			autoplayButton.disabled = availableKeys.length <= 1;
			autoplayButton.textContent = `parcours auto · ${autoplayEnabled ? "on" : "off"}`;
			autoplayButton.setAttribute("aria-pressed", autoplayEnabled ? "true" : "false");
		}
	};

	const syncSpatialRecommendations = () => {
		[...tabs, ...navItems, ...panels].forEach((node) => {
			if (node instanceof HTMLElement) {
				delete node.dataset.raRecommended;
			}
		});

		if (!isSpatialIoView || !spatialProfile) {
			delete shell.dataset.islandRaMode;
			delete shell.dataset.islandRaDominant;
			delete shell.dataset.islandWorldTone;
			delete document.body.dataset.islandRaMode;
			delete document.body.dataset.islandRaDominant;
			delete document.body.dataset.islandWorldTone;
			delete document.body.dataset.islandCameraFacing;
			return;
		}

		const setRecommendation = (key, value) => {
			if (!key) {
				return;
			}

			[tabs, navItems, panels].forEach((collection) => {
				collection.forEach((node) => {
					if (!(node instanceof HTMLElement)) {
						return;
					}
					const nodeKey = node.dataset.islandReaderTab || node.dataset.islandReaderNav || node.dataset.islandReaderPanel || "";
					if (nodeKey === key) {
						node.dataset.raRecommended = value;
					}
				});
			});
		};

		shell.dataset.islandRaMode = typeof latestRaState?.mode === "string" ? latestRaState.mode : "";
		shell.dataset.islandRaDominant = typeof latestRaState?.dominant === "string" ? latestRaState.dominant : "";
		shell.dataset.islandWorldTone = spatialProfile.worldProfile?.tone || "";
		document.body.dataset.islandRaMode = typeof latestRaState?.mode === "string" ? latestRaState.mode : "";
		document.body.dataset.islandRaDominant = typeof latestRaState?.dominant === "string" ? latestRaState.dominant : "";
		document.body.dataset.islandWorldTone = spatialProfile.worldProfile?.tone || "";
		document.body.dataset.islandCameraFacing = typeof latestWorldState?.cameraFacing === "string" ? latestWorldState.cameraFacing : "";
		setRecommendation(spatialProfile.primary, "primary");
		setRecommendation(spatialProfile.secondary, "secondary");
	};

	const clearAutoplay = () => {
		if (autoplayTimer !== null) {
			window.clearTimeout(autoplayTimer);
			autoplayTimer = null;
		}
	};

	const activate = (key, options = {}) => {
		const { focusTarget = null, fromAutoplay = false } = options;
		currentKey = key;

		tabs.forEach((tab) => {
			const isActive = tab.dataset.islandReaderTab === key;
			tab.classList.toggle("is-active", isActive);
			tab.setAttribute("aria-selected", isActive ? "true" : "false");
			tab.tabIndex = isActive ? 0 : -1;
		});

		panels.forEach((panel) => {
			const isActive = panel.dataset.islandReaderPanel === key;
			panel.classList.toggle("is-open", isActive);
			panel.hidden = !isActive;
		});

		navItems.forEach((item) => {
			const isActive = item.dataset.islandReaderNav === key;
			item.classList.toggle("is-active", isActive);
			item.setAttribute("aria-current", isActive ? "true" : "false");
			item.tabIndex = isActive ? 0 : -1;
		});

		const activeNavItem = navItems.find((item) => item.dataset.islandReaderNav === key);
		if (activeNavItem instanceof HTMLElement) {
			try {
				activeNavItem.scrollIntoView({
					block: "nearest",
					inline: "nearest",
					behavior: fromAutoplay ? "auto" : "smooth",
				});
			} catch {
				activeNavItem.scrollIntoView();
			}
		}

		syncSpatialRecommendations();
		syncCurator(key);

		if (!fromAutoplay) {
			scheduleAutoplay();
		}

		if (focusTarget === "tab") {
			const activeTab = tabs.find((tab) => tab.dataset.islandReaderTab === key);
			activeTab?.focus();
		}

		if (focusTarget === "nav") {
			activeNavItem?.focus();
		}
	};

	const stepAvailable = (direction = 1, focus = false) => {
		if (availableKeys.length <= 1) {
			return;
		}

		const activeIndex = availableKeys.indexOf(currentKey);
		const safeIndex = activeIndex >= 0 ? activeIndex : 0;
		const nextIndex = (safeIndex + direction + availableKeys.length) % availableKeys.length;
		const nextKey = availableKeys[nextIndex] || availableKeys[0];
		activate(nextKey, { focusTarget: focus ? "tab" : null, fromAutoplay: false });
	};

	const scheduleAutoplay = () => {
		clearAutoplay();
		if (!autoplayEnabled || availableKeys.length <= 1) {
			return;
		}

		autoplayTimer = window.setTimeout(() => {
			stepAvailable(1, false);
			scheduleAutoplay();
		}, autoplayDelayMs);
	};

	const applyIslandSpatialState = (raState, worldState) => {
		latestRaState = raState;
		latestWorldState = worldState;

		if (!isSpatialIoView) {
			spatialProfile = null;
			syncSpatialRecommendations();
			syncCurator(currentKey || availableKeys[0] || tabs[0]?.dataset.islandReaderTab || "");
			return;
		}

		spatialProfile = composeIslandSpatialProfile(raState, worldState, availableKeys);
		syncSpatialRecommendations();

		const preferredKey = spatialProfile?.primary || "";
		if (!currentKey) {
			activate(preferredKey || availableKeys[0] || tabs[0]?.dataset.islandReaderTab || "", { fromAutoplay: false });
			return;
		}

		if (!userSteered && preferredKey && currentKey !== preferredKey) {
			activate(preferredKey, { fromAutoplay: false });
			return;
		}

		syncCurator(currentKey);
	};

	tabs.forEach((tab) => {
		tab.addEventListener("click", () => {
			userSteered = true;
			activate(tab.dataset.islandReaderTab || "", { fromAutoplay: false });
		});

		tab.addEventListener("keydown", (event) => {
			const currentIndex = tabs.indexOf(tab);
			if (currentIndex < 0) {
				return;
			}

			let nextIndex = currentIndex;
			if (event.key === "ArrowRight" || event.key === "ArrowDown") {
				nextIndex = (currentIndex + 1) % tabs.length;
			} else if (event.key === "ArrowLeft" || event.key === "ArrowUp") {
				nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
			} else if (event.key === "Home") {
				nextIndex = 0;
			} else if (event.key === "End") {
				nextIndex = tabs.length - 1;
			} else {
				return;
			}

			event.preventDefault();
			const nextTab = tabs[nextIndex];
			userSteered = true;
			activate(nextTab.dataset.islandReaderTab || "", { focusTarget: "tab", fromAutoplay: false });
		});
	});

	navItems.forEach((item) => {
		item.addEventListener("click", () => {
			userSteered = true;
			activate(item.dataset.islandReaderNav || "", { fromAutoplay: false });
		});

		item.addEventListener("keydown", (event) => {
			const currentIndex = navItems.indexOf(item);
			if (currentIndex < 0) {
				return;
			}

			let nextIndex = currentIndex;
			if (event.key === "ArrowRight" || event.key === "ArrowDown") {
				nextIndex = (currentIndex + 1) % navItems.length;
			} else if (event.key === "ArrowLeft" || event.key === "ArrowUp") {
				nextIndex = (currentIndex - 1 + navItems.length) % navItems.length;
			} else if (event.key === "Home") {
				nextIndex = 0;
			} else if (event.key === "End") {
				nextIndex = navItems.length - 1;
			} else {
				return;
			}

			event.preventDefault();
			const nextItem = navItems[nextIndex];
			userSteered = true;
			activate(nextItem.dataset.islandReaderNav || "", { focusTarget: "nav", fromAutoplay: false });
		});
	});

	previousButton?.addEventListener("click", () => {
		userSteered = true;
		stepAvailable(-1, true);
	});

	nextButton?.addEventListener("click", () => {
		userSteered = true;
		stepAvailable(1, true);
	});

	autoplayButton?.addEventListener("click", () => {
		autoplayEnabled = !autoplayEnabled;
		syncCurator(currentKey || availableKeys[0] || tabs[0]?.dataset.islandReaderTab || "");
		scheduleAutoplay();
	});

	shell.addEventListener("pointerdown", () => {
		if (autoplayEnabled) {
			scheduleAutoplay();
		}
	});

	document.addEventListener("keydown", (event) => {
		const isHovered = typeof shell.matches === "function" ? shell.matches(":hover") : false;
		if (!shell.contains(document.activeElement) && !isHovered) {
			return;
		}

		if (event.target instanceof HTMLElement) {
			const tagName = event.target.tagName;
			if (tagName === "INPUT" || tagName === "TEXTAREA" || event.target.isContentEditable) {
				return;
			}
		}

		if (event.key === "PageDown") {
			event.preventDefault();
			userSteered = true;
			stepAvailable(1, true);
		} else if (event.key === "PageUp") {
			event.preventDefault();
			userSteered = true;
			stepAvailable(-1, true);
		}
	});

	const initiallyActive = tabs.find((tab) => tab.classList.contains("is-active")) || tabs[0];
	activate(initiallyActive.dataset.islandReaderTab || "", { fromAutoplay: false });
	applyIslandSpatialState(latestRaState, latestWorldState);
	if (isSpatialIoView) {
		window.addEventListener("o:ra-modulation", (event) => {
			const detail = event instanceof CustomEvent ? event.detail : null;
			applyIslandSpatialState(detail, latestWorldState);
		});
		window.addEventListener("o:world-instrument", (event) => {
			const detail = event instanceof CustomEvent ? event.detail : null;
			applyIslandSpatialState(latestRaState, detail);
		});
	}
}

function initIslandReaderFullscreen() {
	const buttons = Array.from(document.querySelectorAll("[data-island-reader-fullscreen]"))
		.filter((button) => button instanceof HTMLButtonElement);

	if (!buttons.length) {
		return;
	}

	const getFullscreenElement = () => document.fullscreenElement || document.webkitFullscreenElement || null;
	const requestFullscreen = async (element) => {
		if (element.requestFullscreen) {
			await element.requestFullscreen();
			return;
		}

		if (element.webkitRequestFullscreen) {
			element.webkitRequestFullscreen();
		}
	};

	const exitFullscreen = async () => {
		if (document.exitFullscreen) {
			await document.exitFullscreen();
			return;
		}

		if (document.webkitExitFullscreen) {
			document.webkitExitFullscreen();
		}
	};

	const syncButtons = () => {
		const fullscreenElement = getFullscreenElement();
		buttons.forEach((button) => {
			const stage = button.closest(".island-reader-stage");
			const isActive = stage instanceof HTMLElement && fullscreenElement === stage;
			button.textContent = isActive ? "quitter" : "plein cadre";
			button.setAttribute("aria-pressed", isActive ? "true" : "false");
		});
	};

	buttons.forEach((button) => {
		button.addEventListener("click", async () => {
			const stage = button.closest(".island-reader-stage");
			if (!(stage instanceof HTMLElement)) {
				return;
			}

			const fullscreenElement = getFullscreenElement();
			if (fullscreenElement === stage) {
				await exitFullscreen().catch(() => {});
				syncButtons();
				return;
			}

			await requestFullscreen(stage).catch(() => {});
			syncButtons();
		});
	});

	document.addEventListener("fullscreenchange", syncButtons);
	document.addEventListener("webkitfullscreenchange", syncButtons);
	syncButtons();
}
