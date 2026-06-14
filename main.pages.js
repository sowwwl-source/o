function initGuideVoice() {
	const existingRoot = document.querySelector("[data-guide-voice]");
	const persisted = readGuideVoiceSession();

	const boot = async () => {
		let root = existingRoot instanceof HTMLElement ? existingRoot : null;
		if (!root && !persisted.active) {
			return;
		}

		if (!root) {
			const state = await fetchGuideVoiceState(persisted.apiPath || withBridgePrefix("/0wlslw0/voice"));
			if (!state) {
				return;
			}
			root = createGuideVoiceDock(state);
		}

		mountGuideVoice(root);
	};

	boot();
}

function initMapSurface() {
	const surfaceRoot = document.getElementById("sowwwl-map-surface");
	if (!(surfaceRoot instanceof HTMLElement)) {
		return;
	}

	const pointsUrl = withBridgePrefix("/map/points");
	const note = document.getElementById("map-note");
	const lexicalForm = document.querySelector("[data-map-lexical-form]");
	const lexicalInput = document.querySelector("[data-map-lexical-input]");
	const lexicalOutput = document.querySelector("[data-map-lexical-output]");
	const lexicalChips = Array.from(document.querySelectorAll("[data-map-lexical-chip]"));
	let currentPayload = null;
	let currentLexicalQuery = "";
	let renderFrame = 0;
	let raProfile = mapRaProfileFromState(readActiveIoRaSession());
	let worldProfile = mapWorldProfileFromState(readActiveIoWorldInstrumentSession());
	let spatialProfile = composeMapSpatialProfile(raProfile, worldProfile);
	let autoLexicalQuery = "";
	let lexicalUserOverride = false;
	let lastWorldFacing = readActiveIoWorldInstrumentSession()?.cameraFacing || "";
	const mapNavigationState = {
		yaw: 0,
		pitch: 0,
		zoom: 1,
		pointerId: null,
		armed: false,
		active: false,
		moved: false,
		suppressClick: false,
		longTouchTimer: 0,
		startX: 0,
		startY: 0,
		lastX: 0,
		lastY: 0,
		userControlled: false,
	};

	const applySpatialMapState = (raState, worldState) => {
		raProfile = mapRaProfileFromState(raState);
		worldProfile = mapWorldProfileFromState(worldState);
		spatialProfile = composeMapSpatialProfile(raProfile, worldProfile);
		delete surfaceRoot.dataset.raMode;
		delete surfaceRoot.dataset.raDominant;
		delete surfaceRoot.dataset.mapWorldTone;
		delete surfaceRoot.dataset.cameraFacing;
		if (raState && typeof raState === "object") {
			surfaceRoot.dataset.raMode = typeof raState.mode === "string" ? raState.mode : "";
			surfaceRoot.dataset.raDominant = typeof raState.dominant === "string" ? raState.dominant : "";
		}
		if (spatialProfile?.tone) {
			surfaceRoot.dataset.mapWorldTone = spatialProfile.tone;
		}
		if (worldState && typeof worldState === "object" && typeof worldState.cameraFacing === "string") {
			surfaceRoot.dataset.cameraFacing = worldState.cameraFacing;
			if (lastWorldFacing && lastWorldFacing !== worldState.cameraFacing) {
				mapNavigationState.userControlled = false;
			}
			lastWorldFacing = worldState.cameraFacing;
		}

		lexicalChips.forEach((chip) => {
			if (!(chip instanceof HTMLElement)) {
				return;
			}
			delete chip.dataset.raRecommended;
			delete chip.dataset.worldRecommended;
			if (spatialProfile && chip.dataset.mapLexicalChip === spatialProfile.query) {
				chip.dataset.raRecommended = "1";
				return;
			}
			if (spatialProfile && chip.dataset.mapLexicalChip === mapSecondaryQueryForProfile(spatialProfile)) {
				chip.dataset.worldRecommended = "1";
			}
		});

		if (lexicalInput instanceof HTMLInputElement) {
			lexicalInput.placeholder = spatialProfile?.tone === "landscape"
				? `${spatialProfile.query} · horizon · @slug · fragment de marche`
				: spatialProfile
					? `${spatialProfile.query} · chaud · @slug · fragment lexical`
				: "chaud · terres · courants · @slug · fragment lexical";
		}

		if (spatialProfile?.query && lexicalInput instanceof HTMLInputElement) {
			const trimmed = lexicalInput.value.trim();
			if (!lexicalUserOverride && (!trimmed || trimmed === autoLexicalQuery)) {
				autoLexicalQuery = spatialProfile.query;
				currentLexicalQuery = spatialProfile.query;
				lexicalInput.value = spatialProfile.query;
			}
		}

		if (!mapNavigationState.active && !mapNavigationState.userControlled) {
			mapNavigationState.zoom = spatialProfile ? spatialProfile.zoom : 1;
			mapNavigationState.yaw = spatialProfile?.yawBias ?? 0;
			mapNavigationState.pitch = spatialProfile?.pitchBias ?? 0;
		}

		if (currentPayload) {
			renderSurface(currentPayload, currentLexicalQuery);
		} else if (note instanceof HTMLElement && spatialProfile?.note) {
			note.textContent = spatialProfile.note;
		}
	};

	const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));

	const hashSeed = (value) => {
		const input = String(value || "o-map");
		let hash = 2166136261;
		for (let index = 0; index < input.length; index += 1) {
			hash ^= input.charCodeAt(index);
			hash = Math.imul(hash, 16777619);
		}
		return hash >>> 0;
	};

	const makeRng = (seed) => {
		let state = hashSeed(seed) || 1;
		return () => {
			state = (Math.imul(state, 1664525) + 1013904223) >>> 0;
			return state / 4294967295;
		};
	};

	const lerp = (left, right, factor) => left + ((right - left) * factor);

	const escapeHtml = (value) => String(value)
		.replaceAll("&", "&amp;")
		.replaceAll("<", "&lt;")
		.replaceAll(">", "&gt;")
		.replaceAll("\"", "&quot;")
		.replaceAll("'", "&#039;");

	const formatPercent = (value) => `${Math.round(Number(value || 0) * 100)}%`;
	const normalizeLexeme = (value) => String(value || "")
		.normalize("NFD")
		.replace(/[\u0300-\u036f]/g, "")
		.toLowerCase()
		.trim();

	const fetchPoints = async () => {
		const response = await fetch(pointsUrl, {
			method: "GET",
			headers: { Accept: "application/json" },
			credentials: "same-origin",
			cache: "no-store",
		});

		if (!response.ok) {
			throw new Error(`HTTP ${response.status}`);
		}

		return response.json();
	};

	const wrapLongitude = (lng) => ((((lng + 180) % 360) + 360) % 360) - 180;

	const setNavigationMode = (mode) => {
		surfaceRoot.classList.toggle("is-map-arming", mode === "arming");
		surfaceRoot.classList.toggle("is-map-navigating", mode === "navigating");
	};

	const scheduleSurfaceRender = () => {
		if (!currentPayload || renderFrame) {
			return;
		}

		renderFrame = window.requestAnimationFrame(() => {
			renderFrame = 0;
			renderSurface(currentPayload, currentLexicalQuery);
		});
	};

	const projectPoint = (lng, lat, width, height) => {
		const safeLng = Number.isFinite(Number(lng)) ? Number(lng) : 0;
		const safeLat = Number.isFinite(Number(lat)) ? Number(lat) : 0;
		const navigatedLng = wrapLongitude(safeLng + mapNavigationState.yaw);
		const navigatedLat = clamp(safeLat + mapNavigationState.pitch, -84, 84);
		const baseX = ((navigatedLng + 180) / 360) * width;
		const baseY = ((90 - navigatedLat) / 180) * height;
		const centerX = width / 2;
		const centerY = height / 2;
		const zoom = mapNavigationState.zoom;
		const torusDepth = 1 + (Math.cos((navigatedLng / 180) * Math.PI) * 0.045);
		const x = centerX + ((baseX - centerX) * zoom * torusDepth);
		const y = centerY + ((baseY - centerY) * zoom);
		return [Math.max(-80, Math.min(width + 80, x)), Math.max(-80, Math.min(height + 80, y))];
	};

	const buildTorusDust = (seed, width, height, count = 540) => {
		const rng = makeRng(`torus-dust|${seed}`);
		const centerX = width / 2;
		const centerY = height / 2;
		const particles = [];

		for (let index = 0; index < count; index += 1) {
			const theta = rng() * Math.PI * 2;
			const phi = rng() * Math.PI * 2;
			const majorRadius = 242 + ((rng() - 0.5) * 44);
			const minorRadius = 72 + (rng() * 48);
			const x = centerX + Math.cos(theta) * (majorRadius + (Math.cos(phi) * minorRadius * 0.46));
			const y = centerY + (Math.sin(theta) * 126) + (Math.sin(phi) * (36 + rng() * 20));
			const size = 0.34 + (rng() * 1.18);
			const opacity = 0.025 + (rng() * 0.18);
			const hueShift = Math.round(180 + (rng() * 35));
			const speedClass = index % 5 === 0 ? "map-particle--fast" : (index % 3 === 0 ? "map-particle--slow" : "");
			particles.push(`<circle class="map-particle ${speedClass}" cx="${x.toFixed(2)}" cy="${y.toFixed(2)}" r="${size.toFixed(2)}" fill="hsla(${hueShift}, 72%, 84%, ${opacity.toFixed(3)})" />`);
		}

		return particles.join("");
	};

	const densityAnchorsForKind = (kind) => {
		switch (kind) {
			case "person":
				return [
					[0, -0.96, 0.18],
					[0, -0.58, 0.22],
					[-0.34, -0.26, 0.2],
					[0.34, -0.26, 0.2],
					[0, 0.06, 0.26],
					[-0.18, 0.58, 0.18],
					[0.18, 0.58, 0.18],
					[-0.62, -0.06, 0.14],
					[0.62, -0.06, 0.14],
				];
			case "place":
				return [
					[0, -0.94, 0.12],
					[-0.62, -0.34, 0.15],
					[0.62, -0.34, 0.15],
					[-0.52, 0.2, 0.22],
					[0.52, 0.2, 0.22],
					[0, 0.44, 0.24],
					[0, 0.02, 0.16],
					[0, 0.72, 0.18],
				];
			default:
				return [
					[-0.72, -0.08, 0.14],
					[-0.4, -0.42, 0.16],
					[0, -0.52, 0.18],
					[0.42, -0.26, 0.16],
					[0.7, 0.04, 0.14],
					[0.42, 0.34, 0.16],
					[0, 0.5, 0.18],
					[-0.42, 0.34, 0.16],
					[0, 0.02, 0.22],
				];
		}
	};

	const buildDensityFigure = (kind, centerX, centerY, heat, seed) => {
		const rng = makeRng(`density-figure|${kind}|${seed}`);
		const anchors = densityAnchorsForKind(kind);
		const count = Math.round(56 + (heat * 120));
		const scaleX = 18 + (heat * 28);
		const scaleY = 24 + (heat * 34);
		const particles = [];

		for (let index = 0; index < count; index += 1) {
			const anchor = anchors[Math.floor(rng() * anchors.length)] || anchors[0];
			const spread = anchor[2] || 0.18;
			const jitterX = (rng() - 0.5) * scaleX * spread * 2.4;
			const jitterY = (rng() - 0.5) * scaleY * spread * 2.4;
			const x = centerX + (anchor[0] * scaleX) + jitterX;
			const y = centerY + (anchor[1] * scaleY) + jitterY;
			const size = 0.55 + (rng() * 1.45) + (heat * 0.72);
			const opacity = 0.12 + (rng() * 0.34) + (heat * 0.16);
			const color = kind === "person"
				? `rgba(255, 245, 214, ${opacity.toFixed(3)})`
				: (kind === "place"
					? `rgba(159, 226, 195, ${opacity.toFixed(3)})`
					: `rgba(194, 232, 255, ${opacity.toFixed(3)})`);
			const speedClass = index % 4 === 0 ? "map-particle--fast" : "";
			particles.push(`<circle class="map-particle ${speedClass}" cx="${x.toFixed(2)}" cy="${y.toFixed(2)}" r="${size.toFixed(2)}" fill="${color}" />`);
		}

		return particles.join("");
	};

	const buildLandParticleCloud = (lands, width, height) => {
		const cloud = [];
		const figures = [];
		const kinds = ["person", "place", "object"];

		lands.forEach((feature, index) => {
			const properties = feature?.properties || {};
			const coords = Array.isArray(feature?.geometry?.coordinates) ? feature.geometry.coordinates : [];
			const [x, y] = projectPoint(coords[0], coords[1], width, height);
			const heat = clamp(Number(properties.activity_heat || 0.18), 0.18, 1);
			const rng = makeRng(`land-cloud|${properties.slug || index}`);
			const count = Math.round(96 + (heat * 260));
			const radiusX = 16 + (heat * 48);
			const radiusY = 11 + (heat * 34);

			for (let particleIndex = 0; particleIndex < count; particleIndex += 1) {
				const angle = rng() * Math.PI * 2;
				const radius = Math.pow(rng(), 1.85);
				const orbit = 1 + (Math.sin(angle * 3 + rng() * 2) * 0.08);
				const driftX = Math.cos(angle) * radiusX * radius * orbit;
				const driftY = Math.sin(angle) * radiusY * radius;
				const px = x + driftX;
				const py = y + driftY;
				const coreBias = 1 - radius;
				const size = 0.28 + (rng() * 1.25) + (coreBias * heat * 1.45);
				const opacity = 0.045 + (rng() * 0.22) + (coreBias * heat * 0.4);
				const speedClass = particleIndex % 6 === 0 ? "map-particle--slow" : "";
				cloud.push(`<circle class="map-particle ${speedClass}" cx="${px.toFixed(2)}" cy="${py.toFixed(2)}" r="${size.toFixed(2)}" fill="rgba(191, 255, 228, ${opacity.toFixed(3)})" />`);
			}

			const kind = kinds[hashSeed(properties.slug || String(index)) % kinds.length] || "object";
			figures.push(buildDensityFigure(kind, x, y - (10 + heat * 18), heat, properties.slug || index));
		});

		return {
			cloud: cloud.join(""),
			figures: figures.join(""),
		};
	};

	const buildCurrentParticleCloud = (currents, width, height) => {
		const particles = [];
		const veils = [];

		currents.forEach((feature, currentIndex) => {
			const coords = Array.isArray(feature?.geometry?.coordinates) ? feature.geometry.coordinates : [];
			const projected = coords
				.filter((point) => Array.isArray(point) && point.length >= 2)
				.map((point) => projectPoint(point[0], point[1], width, height));

			if (projected.length < 2) {
				return;
			}

			const heat = clamp(Number(feature?.properties?.activity_heat || 0.18), 0.18, 1);
			const rng = makeRng(`current-cloud|${feature?.properties?.from_slug || currentIndex}|${feature?.properties?.to_slug || currentIndex}`);
			const count = Math.round(88 + (heat * 230));

			for (let particleIndex = 0; particleIndex < count; particleIndex += 1) {
				const segmentIndex = Math.min(projected.length - 2, Math.floor(rng() * (projected.length - 1)));
				const start = projected[segmentIndex];
				const end = projected[segmentIndex + 1];
				const factor = rng();
				const baseX = lerp(start[0], end[0], factor);
				const baseY = lerp(start[1], end[1], factor);
				const dx = end[0] - start[0];
				const dy = end[1] - start[1];
				const length = Math.max(1, Math.hypot(dx, dy));
				const normalX = -dy / length;
				const normalY = dx / length;
				const centerPull = Math.pow(rng(), 2.35);
				const spread = (rng() - 0.5) * (10 + heat * 34) * centerPull;
				const px = baseX + (normalX * spread);
				const py = baseY + (normalY * spread);
				const size = 0.22 + (rng() * 1.2) + ((1 - centerPull) * heat * 0.9);
				const opacity = 0.035 + (rng() * 0.22) + ((1 - centerPull) * heat * 0.28);
				particles.push(`<circle class="map-current-particle" cx="${px.toFixed(2)}" cy="${py.toFixed(2)}" r="${size.toFixed(2)}" fill="rgba(217, 255, 240, ${opacity.toFixed(3)})" />`);
			}

			projected.forEach((point, pointIndex) => {
				if (pointIndex % 2 !== 0) {
					return;
				}

				const veilRadius = (14 + heat * 32 + rng() * 18).toFixed(2);
				const veilOpacity = (0.018 + heat * 0.055).toFixed(3);
				veils.push(`<circle cx="${point[0].toFixed(2)}" cy="${point[1].toFixed(2)}" r="${veilRadius}" fill="rgba(217,255,240,${veilOpacity})" />`);
			});
		});

		return {
			particles: particles.join(""),
			veils: veils.join(""),
		};
	};

	const lexicalMatchesFeature = (feature, query) => {
		const normalized = normalizeLexeme(query);
		if (normalized === "") {
			return true;
		}

		const properties = feature?.properties || {};
		const kind = String(properties.kind || "");
		const haystack = normalizeLexeme([
			properties.slug,
			properties.username,
			properties.from_slug,
			properties.to_slug,
			properties.from_username,
			properties.to_username,
			properties.activity_label,
			properties.timezone,
			kind,
		].filter(Boolean).join(" "));

		if (normalized === "aide" || normalized === "?") {
			return true;
		}

		if (normalized === "terres") {
			return kind === "land";
		}

		if (normalized === "courants") {
			return kind === "current";
		}

		if (normalized === "chaud" || normalized === "chaude" || normalized === "hot") {
			return Number(properties.activity_heat || 0) >= 0.42;
		}

		if (normalized.startsWith("@")) {
			const slugNeedle = normalized.slice(1);
			return normalizeLexeme(properties.slug || properties.from_slug || "").includes(slugNeedle)
				|| normalizeLexeme(properties.to_slug || "").includes(slugNeedle);
		}

		return haystack.includes(normalized);
	};

	const renderLexicalOutput = (payload, query) => {
		if (!(lexicalOutput instanceof HTMLElement)) {
			return;
		}

		const normalized = normalizeLexeme(query);
		const features = Array.isArray(payload?.features) ? payload.features : [];
		if (normalized === "" || normalized === "aide" || normalized === "?") {
			lexicalOutput.innerHTML = "<p>Commandes : <strong>chaud</strong>, <strong>terres</strong>, <strong>courants</strong>, <strong>@slug</strong>, ou n’importe quel fragment lexical.</p>";
			return;
		}

		const matches = features.filter((feature) => lexicalMatchesFeature(feature, query)).slice(0, 8);
		if (!matches.length) {
			lexicalOutput.innerHTML = `<p>Aucun nœud ne répond à <strong>${escapeHtml(query)}</strong>. Essaie une racine plus courte.</p>`;
			return;
		}

		lexicalOutput.innerHTML = matches.map((feature) => {
			const properties = feature?.properties || {};
			if (properties.kind === "land") {
				return `<p>terre · <a href="${escapeHtml(properties.land_url || "/land")}">${escapeHtml(properties.username || properties.slug || "inconnue")}</a> · chaleur ${formatPercent(properties.activity_heat)}</p>`;
			}

			return `<p>courant · ${escapeHtml(properties.from_username || properties.from_slug || "origine")} → ${escapeHtml(properties.to_username || properties.to_slug || "destination")} · chaleur ${formatPercent(properties.activity_heat)}</p>`;
		}).join("");
	};

	const renderSurface = (payload, query = "") => {
		const features = Array.isArray(payload?.features) ? payload.features : [];
		const lands = features.filter((feature) => feature?.properties?.kind === "land");
		const currents = features.filter((feature) => feature?.properties?.kind === "current");
		const hasLexicalQuery = normalizeLexeme(query) !== "" && normalizeLexeme(query) !== "aide" && normalizeLexeme(query) !== "?";
		const matchingFeatures = hasLexicalQuery ? features.filter((feature) => lexicalMatchesFeature(feature, query)) : features;
		const matchingLandSlugs = new Set(matchingFeatures
			.filter((feature) => feature?.properties?.kind === "land")
			.map((feature) => String(feature?.properties?.slug || "")));
		const matchingCurrentKeys = new Set(matchingFeatures
			.filter((feature) => feature?.properties?.kind === "current")
			.map((feature) => `${feature?.properties?.from_slug || ""}|${feature?.properties?.to_slug || ""}`));
		const svgWidth = 960;
		const svgHeight = 540;
		const dust = buildTorusDust(`${lands.length}|${currents.length}`, svgWidth, svgHeight);
		const landParticles = buildLandParticleCloud(lands, svgWidth, svgHeight);
		const currentParticles = buildCurrentParticleCloud(currents, svgWidth, svgHeight);

		const currentPaths = currents.map((feature) => {
			const coords = Array.isArray(feature?.geometry?.coordinates) ? feature.geometry.coordinates : [];
			if (!coords.length) {
				return "";
			}

			const [firstLng, firstLat] = Array.isArray(coords[0]) ? coords[0] : [0, 0];
			const [startX, startY] = projectPoint(firstLng, firstLat, svgWidth, svgHeight);
			const segments = coords.slice(1).map((point) => {
				const [lng, lat] = Array.isArray(point) ? point : [0, 0];
				const [x, y] = projectPoint(lng, lat, svgWidth, svgHeight);
				return `L ${x.toFixed(2)} ${y.toFixed(2)}`;
			}).join(" ");
			const heat = Math.max(0.18, Math.min(1, Number(feature?.properties?.activity_heat || 0.18)));
			const opacity = (0.025 + heat * 0.08).toFixed(3);
			const strokeWidth = (0.5 + heat * 1.35).toFixed(2);
			const currentKey = `${feature?.properties?.from_slug || ""}|${feature?.properties?.to_slug || ""}`;
			const matchClass = hasLexicalQuery && matchingCurrentKeys.has(currentKey) ? " map-line-ghost--match" : "";
			return `<path class="map-line-ghost${matchClass}" d="M ${startX.toFixed(2)} ${startY.toFixed(2)} ${segments}" fill="none" stroke="rgba(217,255,240,${opacity})" stroke-width="${strokeWidth}" stroke-linecap="round" stroke-linejoin="round" />`;
		}).join("");

		const landDots = lands.map((feature) => {
			const coords = Array.isArray(feature?.geometry?.coordinates) ? feature.geometry.coordinates : [];
			const [lng, lat] = coords;
			const [x, y] = projectPoint(lng, lat, svgWidth, svgHeight);
			const heat = Math.max(0.18, Math.min(1, Number(feature?.properties?.activity_heat || 0.18)));
			const radius = (2.2 + heat * 4.2).toFixed(2);
			const glow = (18 + heat * 42).toFixed(2);
			const slug = escapeHtml(feature?.properties?.slug || "terre");
			const username = escapeHtml(feature?.properties?.username || slug);
			const matchClass = hasLexicalQuery && matchingLandSlugs.has(String(feature?.properties?.slug || "")) ? " map-core-node--match" : "";
			return `
				<g>
					<circle cx="${x.toFixed(2)}" cy="${y.toFixed(2)}" r="${glow}" fill="rgba(159,226,195,${(0.028 + heat * 0.055).toFixed(3)})" />
					<a href="${escapeHtml(feature?.properties?.land_url || "/land")}" aria-label="ouvrir la terre ${username}">
						<circle class="map-core-node${matchClass}" cx="${x.toFixed(2)}" cy="${y.toFixed(2)}" r="${radius}" fill="rgba(236,255,248,0.74)" stroke="rgba(255,255,255,0.2)" stroke-width="0.8" />
					</a>
					<title>${username} · @${slug}</title>
				</g>
			`;
		}).join("");

		const topLands = lands
			.slice()
			.sort((left, right) => Number(right?.properties?.activity_heat || 0) - Number(left?.properties?.activity_heat || 0))
			.slice(0, 6)
			.map((feature) => {
				const properties = feature?.properties || {};
				return `
					<article class="map-fallback__item">
						<a href="${escapeHtml(properties.land_url || "/land")}"><strong>${escapeHtml(properties.username || properties.slug || "Terre")} · @${escapeHtml(properties.slug || "inconnue")}</strong></a>
						<p>${escapeHtml(properties.activity_label || "latente")} · chaleur ${formatPercent(properties.activity_heat)} · ${Number(properties.signal_public_count || 0)} signal(s) public(s)</p>
						<p>Fuseau · ${escapeHtml(properties.timezone || "n/a")}</p>
					</article>
				`;
			}).join("");

		const hotCurrents = currents
			.slice()
			.sort((left, right) => Number(right?.properties?.activity_heat || 0) - Number(left?.properties?.activity_heat || 0))
			.slice(0, 6)
			.map((feature) => {
				const properties = feature?.properties || {};
				return `
					<article class="map-fallback__item">
						<strong>${escapeHtml(properties.from_username || properties.from_slug || "origine")} → ${escapeHtml(properties.to_username || properties.to_slug || "destination")}</strong>
						<p>${escapeHtml(properties.activity_label || "en circulation")} · chaleur ${formatPercent(properties.activity_heat)}</p>
						<p>${Number(properties.passage_count || 0)} passage(s) observé(s)</p>
					</article>
				`;
			}).join("");
		const listTitles = mapListTitlesForProfile(spatialProfile);
		const legendTone = mapLegendToneLabel(spatialProfile);

		surfaceRoot.innerHTML = lands.length > 0
			? `
				<div class="map-fallback__legend">
					<span><span class="map-fallback__dot"></span> <strong>${lands.length}</strong> terre(s)</span>
					<span><span class="map-fallback__line"></span> <strong>${currents.length}</strong> courant(s)</span>
					<span>rendu local autonome</span>
					${legendTone ? `<span class="map-fallback__tone">${escapeHtml(legendTone)}</span>` : ""}
					<span class="map-fallback__nav">${escapeHtml(spatialProfile?.nav || "scroll = zoom · clic/glisse = dérive · appui long tactile")}</span>
				</div>
				<div class="map-fallback__frame">
					<svg class="map-fallback__svg" viewBox="0 0 ${svgWidth} ${svgHeight}" role="img" aria-label="Vue torique simplifiée des terres actives">
						<defs>
							<radialGradient id="torusCore" cx="50%" cy="50%" r="50%">
								<stop offset="0%" stop-color="rgba(159,226,195,0.18)" />
								<stop offset="55%" stop-color="rgba(159,226,195,0.05)" />
								<stop offset="100%" stop-color="rgba(159,226,195,0)" />
							</radialGradient>
							<radialGradient id="torusDenseGlow" cx="50%" cy="50%" r="50%">
								<stop offset="0%" stop-color="rgba(220,255,244,0.22)" />
								<stop offset="100%" stop-color="rgba(220,255,244,0)" />
							</radialGradient>
						</defs>
						<rect width="${svgWidth}" height="${svgHeight}" fill="rgba(4,7,9,0.88)" />
						<rect width="${svgWidth}" height="${svgHeight}" fill="url(#torusDenseGlow)" opacity="0.65" />
						<ellipse cx="${svgWidth / 2}" cy="${svgHeight / 2}" rx="300" ry="124" fill="none" stroke="rgba(217,255,240,0.045)" stroke-width="1" />
						<ellipse cx="${svgWidth / 2}" cy="${svgHeight / 2}" rx="188" ry="68" fill="none" stroke="rgba(217,255,240,0.028)" stroke-width="0.8" />
						<ellipse cx="${svgWidth / 2}" cy="${svgHeight / 2}" rx="156" ry="54" fill="url(#torusCore)" />
						${dust}
						<g class="map-current-field">
							${currentParticles.veils}
							${currentPaths}
							${currentParticles.particles}
						</g>
						<g class="map-density-field">
							${landParticles.figures}
							${landParticles.cloud}
						</g>
						${landDots}
					</svg>
				</div>
				<div class="map-fallback__lists">
					<section class="map-fallback__list" aria-labelledby="map-top-lands-title">
						<h2 id="map-top-lands-title">${escapeHtml(listTitles.lands)}</h2>
						<div class="map-fallback__items">${topLands || '<p class="map-fallback__empty">Aucune terre publique visible.</p>'}</div>
					</section>
					<section class="map-fallback__list" aria-labelledby="map-top-currents-title">
						<h2 id="map-top-currents-title">${escapeHtml(listTitles.currents)}</h2>
						<div class="map-fallback__items">${hotCurrents || '<p class="map-fallback__empty">Aucun courant observé pour l’instant.</p>'}</div>
					</section>
				</div>
			`
			: `
				<div class="map-fallback__empty-state">
					<p class="map-fallback__empty">Aucune terre publique n’alimente encore la surface.</p>
					<p class="map-fallback__empty-copy">Le tore local tient déjà, mais il attend ses premières terres visibles.</p>
					<div class="action-row map-fallback__actions">
						<a class="pill-link" href="${withSurfaceContext("/str3m")}">Lire Str3m</a>
						<a class="ghost-link" href="${withSurfaceContext("/rejoindre")}">Poser une terre</a>
						<a class="ghost-link" href="${withSurfaceContext("/0wlslw0")}">Passer par 0wlslw0</a>
					</div>
				</div>
			`;

		if (note instanceof HTMLElement) {
			const baseNote = lands.length > 0
				? `Tore local dense : ${lands.length} terre(s), ${currents.length} courant(s), zoom ${mapNavigationState.zoom.toFixed(2)}x, console lexicale ${hasLexicalQuery ? "active" : "en veille"}.`
				: "Tore local actif, mais aucune terre publique n’alimente encore la surface.";
			note.textContent = spatialProfile?.note ? `${baseNote} ${spatialProfile.note}` : baseNote;
		}

		renderLexicalOutput(payload, query);
	};

	const bootSurface = async () => {
		try {
			const payload = await fetchPoints();
			currentPayload = payload;
			renderSurface(payload, currentLexicalQuery);
		} catch (error) {
			console.error("Impossible de charger la surface torique locale", error);
			surfaceRoot.innerHTML = `
				<div class="map-fallback__empty-state">
					<p class="map-fallback__empty">Le tore local n’a pas pu se déplier.</p>
					<p class="map-fallback__empty-copy">Tu peux revenir au noyau, lire le courant, puis réessayer.</p>
					<div class="action-row map-fallback__actions">
						<a class="pill-link" href="${withSurfaceContext("/")}">Revenir au noyau</a>
						<a class="ghost-link" href="${withSurfaceContext("/str3m")}">Lire Str3m</a>
						<a class="ghost-link" href="${withSurfaceContext("/0wlslw0")}">Passer par 0wlslw0</a>
					</div>
				</div>
			`;
			if (note instanceof HTMLElement) {
				note.textContent = "Erreur de chargement du tore vivant. Reviens au noyau ou réessaie dans un instant.";
			}
		}
	};

	const endNavigationGesture = () => {
		const shouldSuppressClick = mapNavigationState.active && mapNavigationState.moved;
		if (mapNavigationState.pointerId !== null && surfaceRoot.hasPointerCapture?.(mapNavigationState.pointerId)) {
			surfaceRoot.releasePointerCapture(mapNavigationState.pointerId);
		}

		window.clearTimeout(mapNavigationState.longTouchTimer);
		mapNavigationState.longTouchTimer = 0;
		mapNavigationState.pointerId = null;
		mapNavigationState.armed = false;
		mapNavigationState.active = false;
		mapNavigationState.moved = false;
		mapNavigationState.suppressClick = shouldSuppressClick;
		setNavigationMode("");

		if (shouldSuppressClick) {
			window.setTimeout(() => {
				mapNavigationState.suppressClick = false;
			}, 0);
		}
	};

	const activateNavigationGesture = () => {
		if (mapNavigationState.pointerId === null) {
			return;
		}

		mapNavigationState.armed = false;
		mapNavigationState.active = true;
		setNavigationMode("navigating");
	};

	const updateNavigationFromDelta = (deltaX, deltaY) => {
		mapNavigationState.userControlled = true;
		mapNavigationState.yaw = wrapLongitude(mapNavigationState.yaw + (deltaX * 0.18 / mapNavigationState.zoom));
		mapNavigationState.pitch = clamp(mapNavigationState.pitch - (deltaY * 0.12 / mapNavigationState.zoom), -46, 46);
		scheduleSurfaceRender();
	};

	const bindMapNavigation = () => {
		surfaceRoot.addEventListener("wheel", (event) => {
			const frame = event.target instanceof Element ? event.target.closest(".map-fallback__frame") : null;
			if (!frame) {
				return;
			}

			event.preventDefault();
			const direction = event.deltaY > 0 ? -1 : 1;
			const nextZoom = mapNavigationState.zoom * (direction > 0 ? 1.08 : 0.92);
			mapNavigationState.userControlled = true;
			mapNavigationState.zoom = clamp(nextZoom, 0.72, 1.9);
			scheduleSurfaceRender();
		}, { passive: false });

		surfaceRoot.addEventListener("pointerdown", (event) => {
			const target = event.target instanceof Element ? event.target : null;
			if (!target || !target.closest(".map-fallback__frame") || target.closest("a")) {
				return;
			}

			mapNavigationState.pointerId = event.pointerId;
			mapNavigationState.startX = event.clientX;
			mapNavigationState.startY = event.clientY;
			mapNavigationState.lastX = event.clientX;
			mapNavigationState.lastY = event.clientY;
			mapNavigationState.moved = false;
			surfaceRoot.setPointerCapture?.(event.pointerId);

			if (event.pointerType === "touch") {
				mapNavigationState.armed = true;
				setNavigationMode("arming");
				mapNavigationState.longTouchTimer = window.setTimeout(activateNavigationGesture, 333);
				return;
			}

			event.preventDefault();
			activateNavigationGesture();
		});

		surfaceRoot.addEventListener("pointermove", (event) => {
			if (mapNavigationState.pointerId !== event.pointerId) {
				return;
			}

			const deltaFromStart = Math.hypot(
				event.clientX - mapNavigationState.startX,
				event.clientY - mapNavigationState.startY
			);

			if (mapNavigationState.armed && deltaFromStart > 12) {
				endNavigationGesture();
				return;
			}

			if (!mapNavigationState.active) {
				return;
			}

			event.preventDefault();
			const deltaX = event.clientX - mapNavigationState.lastX;
			const deltaY = event.clientY - mapNavigationState.lastY;
			mapNavigationState.lastX = event.clientX;
			mapNavigationState.lastY = event.clientY;
			mapNavigationState.moved = true;
			updateNavigationFromDelta(deltaX, deltaY);
		});

		surfaceRoot.addEventListener("click", (event) => {
			const target = event.target instanceof Element ? event.target : null;
			const frame = target ? target.closest(".map-fallback__frame") : null;
			if (!frame || target?.closest("a")) {
				return;
			}

			if (mapNavigationState.suppressClick) {
				event.preventDefault();
				mapNavigationState.suppressClick = false;
				return;
			}

			const rect = frame.getBoundingClientRect();
			const offsetX = event.clientX - (rect.left + (rect.width / 2));
			const offsetY = event.clientY - (rect.top + (rect.height / 2));
			mapNavigationState.userControlled = true;
			mapNavigationState.yaw = wrapLongitude(mapNavigationState.yaw + (offsetX * 0.018));
			mapNavigationState.pitch = clamp(mapNavigationState.pitch - (offsetY * 0.012), -46, 46);
			scheduleSurfaceRender();
		});

		surfaceRoot.addEventListener("pointerup", endNavigationGesture);
		surfaceRoot.addEventListener("pointercancel", endNavigationGesture);
		surfaceRoot.addEventListener("lostpointercapture", endNavigationGesture);
	};

	bindMapNavigation();
	applySpatialMapState(readActiveIoRaSession(), readActiveIoWorldInstrumentSession());
	bootSurface();

	if (lexicalForm instanceof HTMLFormElement && lexicalInput instanceof HTMLInputElement) {
		lexicalForm.addEventListener("submit", (event) => {
			event.preventDefault();
			currentLexicalQuery = lexicalInput.value;
			const trimmed = currentLexicalQuery.trim();
			lexicalUserOverride = trimmed !== "" && trimmed !== autoLexicalQuery;
			if (currentPayload) {
				renderSurface(currentPayload, currentLexicalQuery);
			}
		});

		lexicalInput.addEventListener("input", () => {
			currentLexicalQuery = lexicalInput.value;
			const trimmed = currentLexicalQuery.trim();
			lexicalUserOverride = trimmed !== "" && trimmed !== autoLexicalQuery;
			if (currentPayload) {
				renderSurface(currentPayload, currentLexicalQuery);
			}
		});
	}

	window.addEventListener("o:ra-modulation", (event) => {
		const detail = event instanceof CustomEvent ? event.detail : null;
		applySpatialMapState(detail, readActiveIoWorldInstrumentSession());
	});
	window.addEventListener("o:world-instrument", (event) => {
		const detail = event instanceof CustomEvent ? event.detail : null;
		applySpatialMapState(readActiveIoRaSession(), detail);
	});
}

const SIGNAL_ALGORA_STORAGE_KEY = "o-signal-algora-mode";
const SIGNAL_ALGORA_COPY = {
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

const SIGNAL_PREFERRED_PHASES_BY_MODE = {
	douceur: ["phase-locked", "harmonic"],
	confrontation: ["interference", "drift"],
	ecoute: ["inertia", "harmonic", "drift"],
};

function normalizeSignalRecipient(value) {
	return String(value || "").toLowerCase().trim();
}

function buildSignalRecipientDirectory(optionNodes) {
	return optionNodes.map((option) => ({
		value: option.getAttribute("value") || "",
		slug: option.dataset.slug || option.getAttribute("value") || "",
		username: option.dataset.username || option.textContent?.trim() || option.getAttribute("value") || "",
		phase: option.dataset.phase || "drift",
		phaseLabel: option.dataset.phaseLabel || "déphasage léger",
		summary: option.dataset.summary || "",
		lambda: option.dataset.lambda || "548",
		gap: option.dataset.gap || "0",
	}));
}

function createSignalRecipientMatcher(recipientDirectory) {
	return (query) => {
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
}

function readStoredSignalAlgoraMode() {
	try {
		const stored = window.localStorage.getItem(SIGNAL_ALGORA_STORAGE_KEY);
		return stored && SIGNAL_ALGORA_COPY[stored] ? stored : "";
	} catch {
		return "";
	}
}

function getSavedSignalAlgoraMode() {
	return readStoredSignalAlgoraMode() || "douceur";
}

function applySignalRaState(state) {
	const noteNode = document.querySelector("[data-signal-ra-note]");
	const composeNoteNode = document.querySelector("[data-signal-ra-compose-note]");
	const signalCard = document.querySelector('[data-signal-ra-card="signal"]');
	const echoCard = document.querySelector('[data-signal-ra-card="echo"]');
	const prefersEcho = Boolean(state && typeof state === "object" && (state.mode === "loop" || (state.mode === "weave" && state.dominant === "torus")));

	[signalCard, echoCard].forEach((card) => {
		if (card instanceof HTMLElement) {
			delete card.dataset.raRecommended;
		}
	});

	if (!(state && typeof state === "object")) {
		return;
	}

	document.body.dataset.signalRaMode = typeof state.mode === "string" ? state.mode : "";
	document.body.dataset.signalRaDominant = typeof state.dominant === "string" ? state.dominant : "";

	if (noteNode instanceof HTMLElement) {
		noteNode.textContent = prefersEcho
			? "Régime bouclé: Écho peut reprendre la même liaison quand la destination est déjà claire et que la prise doit être directe."
			: (state.mode === "translate"
				? "Régime traduit: Signal garde mieux le fil quand il faut laisser passer nuance, mémoire et médiation avant le direct."
				: "Régime ancré ou tressé: Signal garde le fil, l’adresse et la reprise avant une éventuelle bascule en direct.");
	}

	if (composeNoteNode instanceof HTMLElement) {
		composeNoteNode.textContent = prefersEcho
			? "Le tore boucle déjà la prise: si la destination est nette, Écho peut aller droit au direct sans casser la liaison."
			: (state.mode === "translate"
				? "Le plasma tient encore la couture: ouvre d abord le fil, laisse la relation se formuler, puis passe en direct si la tension devient claire."
				: "La réalité ou le tressage gardent la main: commence par le fil, clarifie la terre, puis décide ensuite si le direct s impose.");
	}

	if (signalCard instanceof HTMLElement) {
		signalCard.dataset.raRecommended = prefersEcho ? "secondary" : "primary";
	}
	if (echoCard instanceof HTMLElement) {
		echoCard.dataset.raRecommended = prefersEcho ? "primary" : "secondary";
	}
}

function applyEchoRaState(state) {
	const noteNode = document.querySelector("[data-echo-ra-note]");
	const emptyNoteNodes = Array.from(document.querySelectorAll("[data-echo-ra-empty-note]"));
	const threadNoteNode = document.querySelector("[data-echo-ra-thread-note]");
	const composeNoteNode = document.querySelector("[data-echo-ra-compose-note]");
	const composeTextarea = document.querySelector("[data-echo-ra-textarea]");
	const signalCard = document.querySelector('[data-echo-ra-card="signal"]');
	const echoCard = document.querySelector('[data-echo-ra-card="echo"]');
	const contactsZone = document.querySelector('[data-echo-ra-zone="contacts"]');
	const directZone = document.querySelector('[data-echo-ra-zone="direct"]');
	const profile = echoRaProfileFromState(state);

	[signalCard, echoCard, contactsZone, directZone].forEach((node) => {
		if (node instanceof HTMLElement) {
			delete node.dataset.raRecommended;
		}
	});

	if (!(state && typeof state === "object") || !profile) {
		delete document.body.dataset.echoRaMode;
		delete document.body.dataset.echoRaDominant;
		delete document.body.dataset.echoRaFocus;
		return;
	}

	document.body.dataset.echoRaMode = typeof state.mode === "string" ? state.mode : "";
	document.body.dataset.echoRaDominant = typeof state.dominant === "string" ? state.dominant : "";
	document.body.dataset.echoRaFocus = profile.focus;

	if (noteNode instanceof HTMLElement) {
		noteNode.textContent = profile.note;
	}
	emptyNoteNodes.forEach((node) => {
		if (node instanceof HTMLElement) {
			node.textContent = profile.emptyNote;
		}
	});
	if (threadNoteNode instanceof HTMLElement) {
		threadNoteNode.textContent = profile.threadNote;
	}
	if (composeNoteNode instanceof HTMLElement) {
		composeNoteNode.textContent = profile.composeNote;
	}
	if (composeTextarea instanceof HTMLTextAreaElement) {
		composeTextarea.placeholder = profile.placeholder;
	}

	if (signalCard instanceof HTMLElement) {
		signalCard.dataset.raRecommended = profile.primary === "signal" ? "primary" : "secondary";
	}
	if (echoCard instanceof HTMLElement) {
		echoCard.dataset.raRecommended = profile.primary === "echo" ? "primary" : "secondary";
	}
	if (contactsZone instanceof HTMLElement) {
		contactsZone.dataset.raRecommended = profile.focus === "contacts" ? "primary" : "secondary";
	}
	if (directZone instanceof HTMLElement) {
		directZone.dataset.raRecommended = profile.focus === "direct" ? "primary" : "secondary";
	}
}

function applyStr3mRaState(state) {
	const noteNode = document.querySelector("[data-str3m-ra-note]");
	const playerNoteNode = document.querySelector("[data-str3m-player-ra-note]");
	const playerRoot = document.querySelector("[data-str3m-player]");
	const cards = Array.from(document.querySelectorAll("[data-str3m-ra-card]"));
	const worldState = readActiveIoWorldInstrumentSession();
	const profile = composeStr3mSpatialProfile(state, worldState);
	const preset = profile.playerPreset;
	const secondaryFocus = profile.raProfile?.focus && profile.raProfile.focus !== profile.focus
		? profile.raProfile.focus
		: "";

	cards.forEach((card) => {
		if (card instanceof HTMLElement) {
			delete card.dataset.raRecommended;
			delete card.dataset.worldRecommended;
		}
	});

	if (!profile.raProfile && !profile.worldProfile) {
		delete document.body.dataset.str3mRaFocus;
		delete document.body.dataset.str3mWorldTone;
		delete document.body.dataset.str3mCameraFacing;
		return;
	}

	document.body.dataset.str3mRaFocus = profile.focus || "";
	document.body.dataset.str3mWorldTone = profile.worldProfile?.tone || "";
	document.body.dataset.str3mCameraFacing = worldState?.cameraFacing || "";
	if (noteNode instanceof HTMLElement && profile.note) {
		noteNode.textContent = profile.note;
	}
	if (playerNoteNode instanceof HTMLElement && preset?.note) {
		playerNoteNode.textContent = preset.note;
	}
	if (playerRoot instanceof HTMLElement) {
		playerRoot.dataset.str3mPlayerRaPreset = preset?.key || "";
		playerRoot.dataset.str3mPlayerWorldPreset = preset?.worldKey || "";
		playerRoot.dataset.str3mPlayerWorldTone = preset?.tone || "";
	}

	cards.forEach((card) => {
		if (!(card instanceof HTMLElement)) {
			return;
		}
		if (card.dataset.str3mRaCard === profile.focus) {
			card.dataset.raRecommended = "1";
			return;
		}
		if (secondaryFocus && card.dataset.str3mRaCard === secondaryFocus) {
			card.dataset.worldRecommended = "1";
		}
	});
}

function initEchoRaSurface() {
	if (!document.body.classList.contains("signal-view") || !document.querySelector("[data-echo-ra-note]")) {
		return;
	}

	applyEchoRaState(readActiveIoRaSession());
	window.addEventListener("o:ra-modulation", (event) => {
		const detail = event instanceof CustomEvent ? event.detail : null;
		applyEchoRaState(detail);
	});
}

function saveSignalAlgoraMode(mode) {
	try {
		window.localStorage.setItem(SIGNAL_ALGORA_STORAGE_KEY, mode);
	} catch {
		// Ignore persistence failures.
	}
}

function renderSignalUnreadLabel(count) {
	const unreadCount = Math.max(0, Number.parseInt(count, 10) || 0);
	return `${unreadCount} message${unreadCount > 1 ? "s" : ""} non lu${unreadCount > 1 ? "s" : ""}`;
}

function createSignalUnreadUpdater(unreadLabels) {
	return (count) => {
		unreadLabels.forEach((node) => {
			if (node instanceof HTMLElement) {
				node.textContent = renderSignalUnreadLabel(count);
			}
		});
	};
}

function createSignalLiveIndicatorUpdater(liveIndicator) {
	return (message) => {
		if (liveIndicator instanceof HTMLElement && typeof message === "string" && message.trim()) {
			liveIndicator.textContent = message;
		}
	};
}

function initSignalContactFilter(filterInput, contactItems) {
	if (!(filterInput instanceof HTMLInputElement) || !contactItems.length) {
		return;
	}

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

function syncSignalOpenInput(openInput) {
	if (!(openInput instanceof HTMLInputElement) || openInput.value) {
		return;
	}

	const activeContact = document.querySelector("[data-signal-contact-item].is-active strong");
	if (activeContact instanceof HTMLElement) {
		openInput.value = activeContact.textContent.trim();
	}
}

function createSignalRecipientPlaceholderApplier({
	input,
	form,
	subjectInput,
	bodyInput,
	algoraCopy,
	getAlgoraMode,
}) {
	return (phase = null) => {
		const copy = algoraCopy[getAlgoraMode()] || algoraCopy.douceur;
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
}

function refreshSignalRecipientSuggestionPriority({
	choiceNodes,
	preferredPhasesByMode,
	getAlgoraMode,
	recipientDirectory,
}) {
	const preferredPhases = preferredPhasesByMode[getAlgoraMode()] || [];
	choiceNodes.forEach((node, index) => {
		if (!(node instanceof HTMLElement)) {
			return;
		}

		const phase = recipientDirectory.find((entry) => entry.slug === (node.dataset.recipientValue || ""))?.phase || "drift";
		const preferredIndex = preferredPhases.indexOf(phase);
		node.classList.toggle("is-algora-preferred", preferredIndex !== -1);
		node.style.order = String(preferredIndex !== -1 ? preferredIndex : preferredPhases.length + index);
	});
}

function updateSignalRecipientChoiceVisibility({ choiceNodes, query, normalizeSignalRecipient }) {
	const normalizedQuery = normalizeSignalRecipient(query);
	choiceNodes.forEach((node) => {
		if (!(node instanceof HTMLElement)) {
			return;
		}

		const haystack = normalizeSignalRecipient(node.dataset.recipientSearch || node.dataset.recipientValue || "");
		node.hidden = normalizedQuery !== "" && !haystack.includes(normalizedQuery);
	});
}

function createSignalRecipientPreviewRenderer(previewNode) {
	if (!(previewNode instanceof HTMLElement)) {
		return () => {};
	}

	const titleNode = previewNode.querySelector("[data-signal-preview-title]");
	const copyNode = previewNode.querySelector("[data-signal-preview-copy]");
	const kickerNode = previewNode.querySelector("[data-signal-preview-kicker]");
	const spectrumNode = previewNode.querySelector("[data-signal-preview-spectrum]");
	const lambdaNode = previewNode.querySelector("[data-signal-preview-lambda]");
	const phaseNode = previewNode.querySelector("[data-signal-preview-phase]");
	const gapNode = previewNode.querySelector("[data-signal-preview-gap]");
	const actionsNode = previewNode.querySelector("[data-signal-preview-actions]");
	const openLink = previewNode.querySelector("[data-signal-preview-open]");
	const echoLink = previewNode.querySelector("[data-signal-preview-echo]");
	const emptyTitle = previewNode.dataset.previewEmptyTitle || "Aucune terre retenue";
	const emptyCopy = previewNode.dataset.previewEmptyCopy || "Choisis une terre pour afficher son contexte.";
	const signalBase = previewNode.dataset.previewSignalBase || withSurfaceContext("/signal");
	const echoBase = previewNode.dataset.previewEchoBase || withSurfaceContext("/echo");

	return (match) => {
		if (!(titleNode instanceof HTMLElement) || !(copyNode instanceof HTMLElement)) {
			return;
		}

		if (!match) {
			previewNode.classList.add("is-empty");
			titleNode.textContent = emptyTitle;
			copyNode.textContent = emptyCopy;
			if (kickerNode instanceof HTMLElement) {
				kickerNode.textContent = "Aperçu de liaison";
			}
			if (spectrumNode instanceof HTMLElement) {
				spectrumNode.hidden = true;
			}
			if (actionsNode instanceof HTMLElement) {
				actionsNode.hidden = true;
			}
			return;
		}

		previewNode.classList.remove("is-empty");
		titleNode.textContent = match.username || match.slug || match.value || "terre reconnue";
		copyNode.textContent = `@${match.slug} · ${match.phaseLabel} — ${match.summary || "Le fil peut s’ouvrir ou passer en direct."}`;

		if (kickerNode instanceof HTMLElement) {
			kickerNode.textContent = "Terre reconnue";
		}
		if (lambdaNode instanceof HTMLElement) {
			lambdaNode.textContent = `λ ${match.lambda} nm`;
		}
		if (phaseNode instanceof HTMLElement) {
			phaseNode.textContent = match.phaseLabel;
			phaseNode.className = `signal-spectrum-pill signal-spectrum-pill--${match.phase || "drift"}`;
		}
		if (gapNode instanceof HTMLElement) {
			gapNode.textContent = `Δ ${match.gap} nm`;
		}
		if (spectrumNode instanceof HTMLElement) {
			spectrumNode.hidden = false;
		}
		if (openLink instanceof HTMLAnchorElement) {
			openLink.href = `${signalBase}?u=${encodeURIComponent(match.slug || match.value || "")}`;
		}
		if (echoLink instanceof HTMLAnchorElement) {
			echoLink.href = `${echoBase}?u=${encodeURIComponent(match.username || match.slug || match.value || "")}`;
		}
		if (actionsNode instanceof HTMLElement) {
			actionsNode.hidden = false;
		}
	};
}

function createSignalRecipientHintRenderer({
	input,
	hintNode,
	defaultHint,
	algoraCopy,
	getAlgoraMode,
	findRecipientMatch,
	applyPlaceholders,
	normalizeSignalRecipient,
	choiceNodes,
	renderPreview,
}) {
	return () => {
		if (!(hintNode instanceof HTMLElement)) {
			return;
		}

		const match = findRecipientMatch(input.value);
		if (!match) {
			hintNode.textContent = (algoraCopy[getAlgoraMode()] || algoraCopy.douceur).fallbackHint || defaultHint || "Choisis une terre et la phase apparaîtra ici.";
			applyPlaceholders(null);
			renderPreview(null);
		} else {
			hintNode.textContent = `${match.username} · λ ${match.lambda} nm · Δ ${match.gap} nm · ${match.phaseLabel} — ${match.summary}`;
			applyPlaceholders(match.phase);
			renderPreview(match);
		}

		updateSignalRecipientChoiceVisibility({
			choiceNodes,
			query: input.value,
			normalizeSignalRecipient,
		});
	};
}

function bindSignalAlgoraModeButtons({ algoraNodes, getAlgoraMode, setAlgoraMode, onModeChange }) {
	algoraNodes.forEach((node) => {
		if (!(node instanceof HTMLButtonElement)) {
			return;
		}

		const nodeMode = node.dataset.algoraMode || "douceur";
		node.classList.toggle("is-active", nodeMode === getAlgoraMode());
		node.addEventListener("click", () => {
			setAlgoraMode(nodeMode);
			algoraNodes.forEach((otherNode) => {
				if (otherNode instanceof HTMLButtonElement) {
					otherNode.classList.toggle("is-active", (otherNode.dataset.algoraMode || "") === getAlgoraMode());
				}
			});
			onModeChange();
		});
	});
}

function bindSignalRecipientChoiceButtons({ choiceNodes, input, bodyInput }) {
	choiceNodes.forEach((node) => {
		if (!(node instanceof HTMLButtonElement)) {
			return;
		}

		node.addEventListener("click", () => {
			input.value = node.dataset.recipientValue || "";
			input.dispatchEvent(new Event("input", { bubbles: true }));
			if (bodyInput instanceof HTMLTextAreaElement) {
				bodyInput.focus();
				return;
			}

			input.focus();
		});
	});
}

function createSignalDraftStatusRenderer(statusNode) {
	return (message) => {
		if (statusNode instanceof HTMLElement && message) {
			statusNode.textContent = message;
		}
	};
}

function readSignalDraft(storageKey) {
	try {
		const raw = window.localStorage.getItem(storageKey);
		if (!raw) {
			return null;
		}

		const draft = JSON.parse(raw);
		return draft && typeof draft === "object" ? draft : null;
	} catch {
		return null;
	}
}

function clearSignalDraft(storageKey) {
	try {
		window.localStorage.removeItem(storageKey);
	} catch {
		// Ignore cleanup failures.
	}
}

function applySignalDraftToFields({ draft, subjectInput, bodyInput, receiverInput }) {
	if (!draft || typeof draft !== "object") {
		return false;
	}

	if (subjectInput instanceof HTMLInputElement && typeof draft.subject === "string" && !subjectInput.value) {
		subjectInput.value = draft.subject;
	}
	if (bodyInput instanceof HTMLTextAreaElement && typeof draft.body === "string" && !bodyInput.value) {
		bodyInput.value = draft.body;
	}
	if (receiverInput instanceof HTMLInputElement && typeof draft.receiver === "string" && !receiverInput.value) {
		receiverInput.value = draft.receiver;
	}

	return Boolean(draft.subject || draft.body || draft.receiver);
}

function createSignalDraftPersister({ storageKey, subjectInput, bodyInput, receiverInput, renderStatus }) {
	return () => {
		try {
			const subject = subjectInput instanceof HTMLInputElement ? subjectInput.value : "";
			const body = bodyInput instanceof HTMLTextAreaElement ? bodyInput.value : "";
			const receiver = receiverInput instanceof HTMLInputElement ? receiverInput.value : "";
			if (!subject.trim() && !body.trim() && !receiver.trim()) {
				clearSignalDraft(storageKey);
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
}

function initSignalRecipientAssist({
	recipientInputs,
	algoraCopy,
	preferredPhasesByMode,
	getSavedAlgoraMode,
	saveAlgoraMode,
	normalizeSignalRecipient,
	findRecipientMatch,
	recipientDirectory,
}) {
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
		const previewNode = form?.querySelector("[data-signal-recipient-preview]");
		const storedAlgoraMode = readStoredSignalAlgoraMode();
		const recommendedAlgoraMode = signalAlgoraModeFromRaState(readActiveIoRaSession());
		let algoraMode = storedAlgoraMode || recommendedAlgoraMode || getSavedAlgoraMode();
		const defaultHint = hintNode instanceof HTMLElement ? hintNode.textContent : "";
		const getAlgoraMode = () => algoraMode;
		const setAlgoraMode = (nextMode) => {
			algoraMode = nextMode;
			saveAlgoraMode(algoraMode);
		};
		const applyPlaceholders = createSignalRecipientPlaceholderApplier({
			input,
			form,
			subjectInput,
			bodyInput,
			algoraCopy,
			getAlgoraMode,
		});
		const refreshSuggestionPriority = () => refreshSignalRecipientSuggestionPriority({
			choiceNodes,
			preferredPhasesByMode,
			getAlgoraMode,
			recipientDirectory,
		});
		const renderRecipientHint = createSignalRecipientHintRenderer({
			input,
			hintNode,
			defaultHint,
			algoraCopy,
			getAlgoraMode,
			findRecipientMatch,
			applyPlaceholders,
			normalizeSignalRecipient,
			choiceNodes,
			renderPreview: createSignalRecipientPreviewRenderer(previewNode),
		});

		bindSignalAlgoraModeButtons({
			algoraNodes,
			getAlgoraMode,
			setAlgoraMode,
			onModeChange: () => {
				refreshSuggestionPriority();
				renderRecipientHint();
			},
		});

		bindSignalRecipientChoiceButtons({ choiceNodes, input, bodyInput });

		refreshSuggestionPriority();
		input.addEventListener("input", renderRecipientHint);
		input.addEventListener("change", renderRecipientHint);
		renderRecipientHint();
	});
}

function initSignalDraftHelpers(composeForms) {
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
		const renderStatus = createSignalDraftStatusRenderer(statusNode);
		const persistDraft = createSignalDraftPersister({
			storageKey,
			subjectInput,
			bodyInput,
			receiverInput,
			renderStatus,
		});
		const restoredDraft = readSignalDraft(storageKey);

		if (applySignalDraftToFields({ draft: restoredDraft, subjectInput, bodyInput, receiverInput })) {
			renderStatus("Brouillon restauré localement. ⌘/Ctrl + Entrée envoie.");
		}

		if (receiverInput instanceof HTMLInputElement && bodyInput instanceof HTMLTextAreaElement && receiverInput.type !== "hidden") {
			receiverInput.addEventListener("keydown", (event) => {
				if (event.key === "Enter" && !event.metaKey && !event.ctrlKey && !event.altKey && !event.shiftKey) {
					event.preventDefault();
					bodyInput.focus();
					renderStatus("Destination retenue. Écris le message puis ⌘/Ctrl + Entrée pour transmettre.");
				}
			});
		}

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
			clearSignalDraft(storageKey);
			renderStatus("Transmission en cours...");
		});
	});
}

function initSignalHistoryNavigation({ history, composeForm }) {
	if (!(history instanceof HTMLElement)) {
		return;
	}

	const jumpButtons = Array.from(document.querySelectorAll("[data-signal-history-jump]"));
	if (!jumpButtons.length) {
		return;
	}

	const resolveTarget = (mode) => {
		if (mode === "composer") {
			return composeForm?.querySelector("[data-signal-history-composer]") || null;
		}

		if (mode === "first") {
			return history.querySelector("[data-signal-history-first]") || history.querySelector("[data-signal-history-item]");
		}

		return history.querySelector("[data-signal-history-last]") || history.querySelector("[data-signal-history-item]:last-of-type");
	};

	jumpButtons.forEach((button) => {
		if (!(button instanceof HTMLButtonElement)) {
			return;
		}

		const mode = button.dataset.signalHistoryJump || "latest";
		button.addEventListener("click", () => {
			const target = resolveTarget(mode);
			if (!(target instanceof HTMLElement)) {
				return;
			}

			if (mode === "composer") {
				target.focus();
				target.scrollIntoView({ block: "center", behavior: "smooth" });
				return;
			}

			target.scrollIntoView({
				block: mode === "first" ? "start" : "end",
				behavior: "smooth",
			});
		});
	});
}

function shouldSignalLiveHistoryStick(liveHistory) {
	return (liveHistory.scrollHeight - liveHistory.scrollTop - liveHistory.clientHeight) < 72;
}

function scrollSignalLiveHistoryToBottom(liveHistory) {
	liveHistory.scrollTop = liveHistory.scrollHeight;
}

function getSignalLiveTarget(liveRoot) {
	return (liveRoot.dataset.liveTarget || "").trim();
}

function applySignalLivePayload({
	payload,
	liveRoot,
	liveHistory,
	echoContactsRoot,
	liveView,
	state,
	updateUnreadLabels,
	updateLiveIndicator,
}) {
	const wasNearBottom = shouldSignalLiveHistoryStick(liveHistory);
	const nextHash = typeof payload.history_hash === "string" ? payload.history_hash : "";
	const nextMessageCount = Number.parseInt(String(payload.message_count ?? state.liveMessageCount), 10) || 0;
	const messageCountIncreased = nextMessageCount > state.liveMessageCount;

	if (typeof payload.history_html === "string" && nextHash !== state.liveHash) {
		liveHistory.innerHTML = payload.history_html;
		state.liveHash = nextHash;
		liveRoot.dataset.liveHash = nextHash;
	}

	state.liveMessageCount = nextMessageCount;
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
		scrollSignalLiveHistoryToBottom(liveHistory);
	}

	updateLiveIndicator("direct · temps réel");
}

function createSignalLivePoller({
	liveRoot,
	liveHistory,
	echoContactsRoot,
	updateLiveIndicator,
	updateUnreadLabels,
	apiPath,
	liveView,
	state,
}) {
	const redirectToLiveError = (errorCode) => {
		if (!errorCode || typeof window === "undefined") {
			return;
		}

		const targetUrl = new URL(window.location.href);
		targetUrl.searchParams.set("error", errorCode);
		window.location.assign(targetUrl.toString());
	};

	return async () => {
		const target = getSignalLiveTarget(liveRoot);
		if (!target || state.inflight || document.hidden) {
			return;
		}

		state.inflight = true;
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

			let payload = null;
			try {
				payload = await response.json();
			} catch (parseError) {
				payload = null;
			}

			if (!response.ok) {
				const payloadError = typeof payload?.error === "string" ? payload.error : "";
				if (response.status === 401 || payloadError === "auth-required") {
					redirectToLiveError("session");
					return;
				}

				if (response.status === 503 || payloadError === "messaging-not-ready") {
					redirectToLiveError("messaging");
					return;
				}

				throw new Error(`HTTP ${response.status}`);
			}

			if (!payload || payload.ok === false) {
				const payloadError = typeof payload?.error === "string" ? payload.error : "";
				if (payloadError === "auth-required") {
					redirectToLiveError("session");
					return;
				}

				if (payloadError === "messaging-not-ready") {
					redirectToLiveError("messaging");
					return;
				}

				throw new Error("invalid-payload");
			}

			applySignalLivePayload({
				payload,
				liveRoot,
				liveHistory,
				echoContactsRoot,
				liveView,
				state,
				updateUnreadLabels,
				updateLiveIndicator,
			});
		} catch (error) {
			console.error("Impossible de rafraîchir la messagerie en direct", error);
			updateLiveIndicator("direct · interrompu");
		} finally {
			state.inflight = false;
		}
	};
}

function initSignalLiveHelpers({
	liveRoot,
	liveHistory,
	echoContactsRoot,
	updateLiveIndicator,
	updateUnreadLabels,
}) {
	if (!(liveRoot instanceof HTMLElement) || !(liveHistory instanceof HTMLElement)) {
		return;
	}

	const apiPath = liveRoot.dataset.liveApi || withBridgePrefix("/signal_live.php");
	const liveView = liveRoot.dataset.liveView || "signal";
	const pollInterval = Math.max(1400, Number.parseInt(liveRoot.dataset.liveInterval || "2500", 10) || 2500);
	const state = {
		liveHash: liveRoot.dataset.liveHash || "",
		liveMessageCount: Number.parseInt(liveRoot.dataset.liveMessageCount || "0", 10) || 0,
		inflight: false,
	};
	let timerId = 0;
	const pollLiveThread = createSignalLivePoller({
		liveRoot,
		liveHistory,
		echoContactsRoot,
		updateLiveIndicator,
		updateUnreadLabels,
		apiPath,
		liveView,
		state,
	});

	if (getSignalLiveTarget(liveRoot)) {
		scrollSignalLiveHistoryToBottom(liveHistory);
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

function initSignalFlow() {
	applySignalRaState(readActiveIoRaSession());
	window.addEventListener("o:ra-modulation", (event) => {
		const detail = event instanceof CustomEvent ? event.detail : null;
		applySignalRaState(detail);
	});

	const filterInput = document.querySelector("[data-signal-contact-filter]");
	const contactItems = Array.from(document.querySelectorAll("[data-signal-contact-item]"));
	const openInput = document.querySelector("[data-signal-open-input]");
	const history = document.getElementById("signal-history");
	const activeComposeForm = document.querySelector('[data-signal-compose][data-draft-scope^="thread:"]');
	const liveRoot = document.querySelector("[data-message-live]");
	const liveHistory = liveRoot?.querySelector("[data-message-live-history]");
	const liveIndicator = liveRoot?.querySelector("[data-message-live-indicator]");
	const echoContactsRoot = liveRoot?.querySelector("[data-echo-contacts-list]");
	const unreadLabels = Array.from(document.querySelectorAll("[data-signal-unread-label]"));
	const composeForms = Array.from(document.querySelectorAll("[data-signal-compose]"));
	const recipientInputs = Array.from(document.querySelectorAll("[data-signal-recipient-input]"));
	const optionNodes = Array.from(document.querySelectorAll("#signal-contact-options option"));
	const recipientDirectory = buildSignalRecipientDirectory(optionNodes);
	const findRecipientMatch = createSignalRecipientMatcher(recipientDirectory);

	if (history) {
		history.scrollTop = history.scrollHeight;
	}

	const updateUnreadLabels = createSignalUnreadUpdater(unreadLabels);
	const updateLiveIndicator = createSignalLiveIndicatorUpdater(liveIndicator);

	initSignalHistoryNavigation({ history, composeForm: activeComposeForm });
	initSignalContactFilter(filterInput, contactItems);
	syncSignalOpenInput(openInput);

	initSignalRecipientAssist({
		recipientInputs,
		algoraCopy: SIGNAL_ALGORA_COPY,
		preferredPhasesByMode: SIGNAL_PREFERRED_PHASES_BY_MODE,
		getSavedAlgoraMode: getSavedSignalAlgoraMode,
		saveAlgoraMode: saveSignalAlgoraMode,
		normalizeSignalRecipient,
		findRecipientMatch,
		recipientDirectory,
	});

	initSignalDraftHelpers(composeForms);

	initSignalLiveHelpers({
		liveRoot,
		liveHistory,
		echoContactsRoot,
		updateLiveIndicator,
		updateUnreadLabels,
	});
}

function initStr3mRaSurface() {
	if (!document.body.classList.contains("str3m-view")) {
		return;
	}

	applyStr3mRaState(readActiveIoRaSession());
	window.addEventListener("o:ra-modulation", (event) => {
		const detail = event instanceof CustomEvent ? event.detail : null;
		applyStr3mRaState(detail);
	});
	window.addEventListener("o:world-instrument", () => {
		applyStr3mRaState(readActiveIoRaSession());
	});
}

runPageInit("pageAccessibility", initPageAccessibility);
runPageInit("spatialContext", initSpatialContext);
runPageInit("continuityDome", initContinuityDome);
runPageInit("nucleusBanner", initNucleusBanner);
runPageInit("cornerDocks", initCornerDocks);
runPageInit("guideVoice", initGuideVoice);
runPageInit("mapSurface", initMapSurface);
runPageInit("signalFlow", initSignalFlow);
runPageInit("echoRaSurface", initEchoRaSurface);
runPageInit("spectralTuner", initSpectralTuner);
runPageInit("str3mRaSurface", initStr3mRaSurface);
runPageInit("str3mArchipelago", initStr3mArchipelago);
runPageInit("str3mParallax", initStr3mParallax);
runPageInit("str3mShellFutureBridge", initStr3mShellFutureBridge);
runPageInit("str3mGhostShellDock", initStr3mGhostShellDock);
runPageInit("str3mIntegratedPlayer", initStr3mIntegratedPlayer);
runPageInit("pocketCameraPanels", initPocketCameraPanels);
runPageInit("sceptreConsole", initSceptreConsole);
runPageInit("landscapeChoirs", initLandscapeChoirs);
runPageInit("islandReaderStation", initIslandReaderStation);
runPageInit("islandReaderFullscreen", initIslandReaderFullscreen);

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

runPageInit("azaTabs", initAzaTabs);

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

runPageInit("b0t3", initB0t3);
