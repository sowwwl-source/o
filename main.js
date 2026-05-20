const DEFAULT_TIMEZONE = "Europe/Paris";
const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
const coarsePointer = window.matchMedia("(pointer: coarse)").matches || (navigator.maxTouchPoints || 0) > 0;
const THEME_KEY = "o-theme-inverted";
const GUIDE_VOICE_MUTE_KEY = "o-guide-voice-muted-v1";
const DEVICE_SILENCE_INTENT_KEY = "o-device-silence-intent-v1";
const DEVICE_VOLUME_LEVEL_KEY = "o-device-volume-level-v1";
const XYZ_VOICE_ECHO_KEY = "o-xyz-voice-echo-v1";
const AUDIO_DEFAULTS_VERSION_KEY = "o-audio-defaults-v2";
const RA_MODULATION_SESSION_KEY = "o-ra-modulation-v1";
const WORLD_INSTRUMENT_SESSION_KEY = "o-world-instrument-v1";
const RA_MODULATION_SESSION_TTL = 6 * 60 * 60 * 1000;
const WORLD_INSTRUMENT_SESSION_TTL = 6 * 60 * 60 * 1000;

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

function isIoSurfaceView() {
	return document.body.classList.contains("io-surface-view");
}

function prefersSpatialHeadsetMode() {
	return document.body.classList.contains("io-headset-mode");
}

function readRuntimeMeta(name, fallback = "") {
	const meta = document.querySelector(`meta[name="${name}"]`);
	if (!(meta instanceof HTMLMetaElement)) {
		return fallback;
	}

	const value = meta.content || "";
	return value !== "" ? value : fallback;
}

if (typeof window.__O_BRIDGE_PREFIX__ !== "string") {
	window.__O_BRIDGE_PREFIX__ = readRuntimeMeta("o-bridge-prefix", "");
}

if (typeof window.__O_DISABLE_SW__ !== "boolean") {
	window.__O_DISABLE_SW__ = readRuntimeMeta("o-disable-sw", "false") === "true";
}

function bridgePrefix() {
	return typeof window.__O_BRIDGE_PREFIX__ === "string"
		? window.__O_BRIDGE_PREFIX__.replace(/\/+$/, "")
		: "";
}

function withBridgePrefix(path = "/") {
	const value = typeof path === "string" && path !== "" ? path : "/";
	if (/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(value)) {
		return value;
	}

	const hashIndex = value.indexOf("#");
	const hash = hashIndex >= 0 ? value.slice(hashIndex) : "";
	const beforeHash = hashIndex >= 0 ? value.slice(0, hashIndex) : value;
	const queryIndex = beforeHash.indexOf("?");
	const search = queryIndex >= 0 ? beforeHash.slice(queryIndex) : "";
	const pathnameRaw = queryIndex >= 0 ? beforeHash.slice(0, queryIndex) : beforeHash;
	const pathname = pathnameRaw.startsWith("/") ? pathnameRaw : `/${pathnameRaw}`;
	const prefix = bridgePrefix();

	if (!prefix) {
		return `${pathname}${search}${hash}`;
	}

	if (pathname === prefix || pathname.startsWith(`${prefix}/`)) {
		return `${pathname}${search}${hash}`;
	}

	return `${pathname === "/" ? `${prefix}/` : `${prefix}${pathname}`}${search}${hash}`;
}

function currentSurfaceContextParams() {
	const params = new URLSearchParams();
	let currentUrl = null;

	try {
		currentUrl = new URL(window.location.href);
	} catch {
		currentUrl = null;
	}

	const querySurface = currentUrl?.searchParams.get("surface") || "";
	const host = (window.location.hostname || "").toLowerCase();
	let surface = "";

	if (["xyz", "io", "lab"].includes(querySurface)) {
		surface = querySurface;
	} else if (!["sowwwl.xyz", "www.sowwwl.xyz", "sowwwl.io", "www.sowwwl.io", "lab.sowwwl.cloud", "www.lab.sowwwl.cloud"].includes(host)) {
		if (document.body.classList.contains("io-surface-view")) {
			surface = "io";
		} else if (document.body.classList.contains("xyz-surface-view")) {
			surface = "xyz";
		} else if (document.body.classList.contains("lab-console-view")) {
			surface = "lab";
		}
	}

	if (surface) {
		params.set("surface", surface);
	}

	const explicitSpatialMode = currentUrl?.searchParams.get("spatial") || "";
	const spatialSurfaceActive = surface === "io" || isIoSurfaceView() || host === "sowwwl.io" || host === "www.sowwwl.io";
	if (spatialSurfaceActive && (explicitSpatialMode === "headset" || prefersSpatialHeadsetMode())) {
		params.set("spatial", "headset");
	}

	return params;
}

function withSurfaceContext(path = "/") {
	const base = withBridgePrefix(path);
	if (/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(base)) {
		return base;
	}

	try {
		const url = new URL(base, window.location.origin);
		const previewParams = currentSurfaceContextParams();
		previewParams.forEach((value, key) => {
			if (!url.searchParams.has(key)) {
				url.searchParams.set(key, value);
			}
		});
		const query = url.searchParams.toString();
		return `${url.pathname}${query ? `?${query}` : ""}${url.hash}`;
	} catch {
		return base;
	}
}

function readRaModulationSession() {
	try {
		const raw = window.sessionStorage.getItem(RA_MODULATION_SESSION_KEY);
		if (!raw) {
			return null;
		}

		const parsed = JSON.parse(raw);
		if (!parsed || typeof parsed !== "object") {
			return null;
		}

		const timestamp = Number(parsed.timestamp || 0);
		if (!Number.isFinite(timestamp) || timestamp + RA_MODULATION_SESSION_TTL < Date.now()) {
			window.sessionStorage.removeItem(RA_MODULATION_SESSION_KEY);
			return null;
		}

		return parsed;
	} catch {
		return null;
	}
}

function writeRaModulationSession(state) {
	if (!state || typeof state !== "object") {
		return;
	}

	try {
		window.sessionStorage.setItem(
			RA_MODULATION_SESSION_KEY,
			JSON.stringify({
				...state,
				timestamp: Date.now(),
			})
		);
	} catch {
		// Ignore storage failures.
	}
}

function readWorldInstrumentSession() {
	try {
		const raw = window.sessionStorage.getItem(WORLD_INSTRUMENT_SESSION_KEY);
		if (!raw) {
			return null;
		}

		const parsed = JSON.parse(raw);
		if (!parsed || typeof parsed !== "object") {
			return null;
		}

		const timestamp = Number(parsed.timestamp || 0);
		if (!Number.isFinite(timestamp) || timestamp + WORLD_INSTRUMENT_SESSION_TTL < Date.now()) {
			window.sessionStorage.removeItem(WORLD_INSTRUMENT_SESSION_KEY);
			return null;
		}

		return parsed;
	} catch {
		return null;
	}
}

function writeWorldInstrumentSession(state) {
	if (!state || typeof state !== "object") {
		return;
	}

	try {
		window.sessionStorage.setItem(
			WORLD_INSTRUMENT_SESSION_KEY,
			JSON.stringify({
				...state,
				timestamp: Date.now(),
			})
		);
	} catch {
		// Ignore storage failures.
	}
}

function readActiveIoRaSession() {
	const state = readRaModulationSession();
	if (!state || state.surface !== "io") {
		return null;
	}

	return state;
}

function readActiveIoWorldInstrumentSession() {
	const state = readWorldInstrumentSession();
	if (!state || state.surface !== "io") {
		return null;
	}

	return state;
}

function signalAlgoraModeFromRaState(state) {
	if (!state || typeof state !== "object") {
		return "";
	}

	switch (state.mode) {
		case "anchor":
			return "ecoute";
		case "translate":
			return "douceur";
		case "loop":
			return "confrontation";
		case "weave":
		default:
			if (state.dominant === "torus") {
				return "confrontation";
			}
			if (state.dominant === "plasma") {
				return "ecoute";
			}
			return "douceur";
	}
}

function mapRaProfileFromState(state) {
	if (!state || typeof state !== "object") {
		return null;
	}

	switch (state.mode) {
		case "anchor":
			return {
				query: "terres",
				zoom: 0.94,
				note: "Régime ancré: relis d abord les terres et les repères avant de laisser monter les courants.",
			};
		case "translate":
			return {
				query: "courants",
				zoom: 1.02,
				note: "Régime traduit: suis d abord les courants, les passages et les médiations entre les terres.",
			};
		case "loop":
			return {
				query: "chaud",
				zoom: 1.14,
				note: "Régime bouclé: le tore cherche ses foyers chauds, ses prises et ses nœuds déjà assez denses pour orienter l action.",
			};
		case "weave":
		default:
			if (state.dominant === "plasma") {
				return {
					query: "courants",
					zoom: 1.04,
					note: "Régime tressé, plasma dominant: le flux sert de couture principale entre terres, gestes et surface.",
				};
			}
			if (state.dominant === "torus") {
				return {
					query: "chaud",
					zoom: 1.1,
					note: "Régime tressé, tore dominant: repère les nœuds chauds où la surface commence déjà à prendre.",
				};
			}
			return {
				query: "terres",
				zoom: 0.98,
				note: "Régime tressé, réalité dominante: la carte garde les terres visibles au premier plan avant la montée des autres couches.",
			};
	}
}

function mapWorldProfileFromState(state) {
	if (!state || typeof state !== "object") {
		return null;
	}

	const cameraFacing = state.cameraFacing === "environment" ? "environment" : "user";
	const sceneEnergy = clampNumber(Number(state.sceneEnergy) || 0, 0, 1);
	const touchEnergy = clampNumber(Number(state.touchEnergy) || 0, 0, 1);
	const activeHands = Math.max(0, Number.parseInt(state.activeHands, 10) || 0);
	const focusLabel = String(state.focusLabel || "").toLowerCase();
	const lightLabel = String(state.lightLabel || "").toLowerCase();
	const hasReflections = focusLabel.includes("reflets") || focusLabel.includes("dehors");
	const isWalking = focusLabel.includes("marche") || focusLabel.includes("horizon") || sceneEnergy > 0.52;

	if (cameraFacing === "environment") {
		if (isWalking) {
			return {
				tone: "landscape",
				query: "courants",
				zoom: 0.9,
				yawBias: 10,
				pitchBias: -8,
				nav: "paysage : marche large · horizon · scroll = zoom · glisse = derive",
				note: "Paysage actif: la carte s ouvre pour laisser lire les courants, la marche et les prises qui se deplacent avec toi.",
			};
		}

		if (hasReflections || lightLabel.includes("clair")) {
			return {
				tone: "landscape",
				query: "chaud",
				zoom: 0.96,
				yawBias: 6,
				pitchBias: -5,
				nav: "paysage : reflets · detail · scroll = zoom · glisse = visee",
				note: "Paysage lumineux: les reflets, facades et points chauds deviennent les premieres prises de lecture.",
			};
		}

		return {
			tone: "landscape",
			query: "terres",
			zoom: 0.94,
			yawBias: 4,
			pitchBias: -4,
			nav: "paysage : terrain · terres visibles · scroll = zoom · glisse = horizon",
			note: "Paysage tenu: la carte garde les terres visibles et laisse l horizon orienter la lecture avant la derive.",
		};
	}

	if (activeHands >= 2 || touchEnergy > 0.34) {
		return {
			tone: "face",
			query: "chaud",
			zoom: 1.08,
			yawBias: 0,
			pitchBias: 4,
			nav: "proximite : terre + mine · detail proche · scroll = zoom · glisse = torsion",
			note: "Proximite active: deux mains ou une frappe nette poussent la carte vers les points chauds, les details et les noeuds proches.",
		};
	}

	return {
		tone: "face",
		query: "terres",
		zoom: 1.03,
		yawBias: 0,
		pitchBias: 2,
		nav: "proximite : visage · souffle · scroll = zoom · glisse = derive",
		note: "Proximite tenue: la carte reste plus pres des terres et du relief immediat avant d ouvrir plus loin.",
	};
}

function composeMapSpatialProfile(raProfile, worldProfile) {
	if (!raProfile && !worldProfile) {
		return null;
	}

	const tone = typeof worldProfile?.tone === "string" ? worldProfile.tone : "";
	const query = tone === "landscape"
		? (worldProfile?.query || raProfile?.query || "")
		: (raProfile?.query || worldProfile?.query || "");
	const zoomBase = raProfile?.zoom ?? worldProfile?.zoom ?? 1;
	const zoom = worldProfile
		? clampNumber((zoomBase * 0.42) + ((worldProfile.zoom ?? zoomBase) * 0.58), 0.72, 1.9)
		: clampNumber(zoomBase, 0.72, 1.9);

	return {
		query,
		zoom,
		yawBias: clampNumber(Number(worldProfile?.yawBias) || 0, -24, 24),
		pitchBias: clampNumber(Number(worldProfile?.pitchBias) || 0, -16, 16),
		tone,
		nav: worldProfile?.nav || "scroll = zoom · clic/glisse = derive · appui long tactile",
		note: [worldProfile?.note, raProfile?.note].filter(Boolean).join(" "),
	};
}

function mapSecondaryQueryForProfile(profile) {
	if (!profile) {
		return "";
	}

	if (profile.tone === "landscape") {
		if (profile.query === "courants") {
			return "chaud";
		}
		if (profile.query === "chaud") {
			return "courants";
		}
		return "courants";
	}

	if (profile.query === "chaud") {
		return "terres";
	}
	if (profile.query === "terres") {
		return "chaud";
	}
	return "terres";
}

function mapLegendToneLabel(profile) {
	if (!profile) {
		return "";
	}

	if (profile.tone === "landscape") {
		if (profile.query === "courants") {
			return "mode paysage · marche";
		}
		if (profile.query === "chaud") {
			return "mode paysage · reflets";
		}
		return "mode paysage · terrain";
	}

	if (profile.query === "chaud") {
		return "mode proche · detail";
	}
	return "mode proche · souffle";
}

function mapListTitlesForProfile(profile) {
	if (!profile) {
		return {
			lands: "Terres chaudes",
			currents: "Courants chauds",
		};
	}

	if (profile.tone === "landscape") {
		if (profile.query === "courants") {
			return {
				lands: "Terres reperes",
				currents: "Courants de marche",
			};
		}
		if (profile.query === "chaud") {
			return {
				lands: "Terres-reflets",
				currents: "Passages lumineux",
			};
		}
		return {
			lands: "Terres de terrain",
			currents: "Courants visibles",
		};
	}

	if (profile.query === "chaud") {
		return {
			lands: "Terres proches",
			currents: "Noeuds chauds",
		};
	}

	return {
		lands: "Terres chaudes",
		currents: "Courants chauds",
	};
}

function str3mRaProfileFromState(state) {
	if (!state || typeof state !== "object") {
		return null;
	}

	switch (state.mode) {
		case "anchor":
			return {
				focus: "lands",
				note: "Régime ancré: relis les terres visibles avant de pousser plus loin le courant public.",
			};
		case "translate":
			return {
				focus: "signals",
				note: "Régime traduit: les signaux publics et les matières du jour deviennent la meilleure couture entre réel et surface.",
			};
		case "loop":
			return {
				focus: "gestures",
				note: "Régime bouclé: les gestes, preuves et secousses du public prennent la main pour ouvrir des prises plus franches.",
			};
		case "weave":
		default:
			if (state.dominant === "plasma") {
				return {
					focus: "signals",
					note: "Régime tressé, plasma dominant: lis d abord les signaux, puis laisse la surface reprendre la dérive.",
				};
			}
			if (state.dominant === "torus") {
				return {
					focus: "gestures",
					note: "Régime tressé, tore dominant: les gestes en circulation indiquent déjà où la surface veut prendre appui.",
				};
			}
			return {
				focus: "lands",
				note: "Régime tressé, réalité dominante: les présences visibles restent la meilleure entrée avant les flux plus denses.",
			};
	}
}

function str3mWorldProfileFromState(state) {
	if (!state || typeof state !== "object") {
		return null;
	}

	const cameraFacing = state.cameraFacing === "environment" ? "environment" : "user";
	const sceneEnergy = clampNumber(Number(state.sceneEnergy) || 0, 0, 1);
	const touchEnergy = clampNumber(Number(state.touchEnergy) || 0, 0, 1);
	const activeHands = Math.max(0, Number.parseInt(state.activeHands, 10) || 0);
	const focusLabel = String(state.focusLabel || "").toLowerCase();
	const lightLabel = String(state.lightLabel || "").toLowerCase();
	const touchLabel = String(state.touchLabel || "").toLowerCase();
	const hasDuetHands = activeHands >= 2 || touchLabel.includes("terre + mine");
	const hasReflections = focusLabel.includes("reflets") || focusLabel.includes("dehors") || lightLabel.includes("clair");
	const isWalking = focusLabel.includes("marche") || focusLabel.includes("horizon") || sceneEnergy > 0.52;

	if (cameraFacing === "environment") {
		if (isWalking) {
			return {
				focus: "gestures",
				tone: "landscape",
				playerKey: "landscape-walk",
				note: "Paysage actif: Str3m lit maintenant le flux comme une marche. Les gestes publics et les passages deviennent les premieres prises.",
				playerNote: "Mode paysage: la lecture gagne de l air, du pas et une ouverture plus vive pour que le dehors reste un instrument.",
				status: "preset paysage",
				rateDelta: 0.06,
				preservePitch: false,
				bassDelta: 0.8,
				midDelta: -0.2,
				trebleDelta: 1.6,
				gainDelta: 6,
			};
		}

		if (hasReflections) {
			return {
				focus: "signals",
				tone: "landscape",
				playerKey: "landscape-light",
				note: "Paysage lumineux: les signaux, reflets et matieres de surface deviennent l entree la plus juste pour ecouter dehors.",
				playerNote: "Mode reflets: le lecteur s eclaircit, prend un peu d air et laisse mieux passer les coutures, les brillances et les details du terrain.",
				status: "preset reflets",
				rateDelta: 0.03,
				preservePitch: true,
				bassDelta: -0.4,
				midDelta: 0.6,
				trebleDelta: 1.9,
				gainDelta: 4,
			};
		}

		return {
			focus: "lands",
			tone: "landscape",
			playerKey: "landscape-anchor",
			note: "Paysage tenu: les terres visibles, les presences et les reperes gardent la meilleure profondeur avant les accelerations du flux.",
			playerNote: "Mode terrain: la lecture se pose plus large, garde du bas et laisse l horizon porter les presences publiques.",
			status: "preset terrain",
			rateDelta: 0.01,
			preservePitch: true,
			bassDelta: 0.6,
			midDelta: 0.2,
			trebleDelta: 0.4,
			gainDelta: 5,
		};
	}

	if (hasDuetHands || touchEnergy > 0.34) {
		return {
			focus: "gestures",
			tone: "face",
			playerKey: "face-duet",
			note: "Proximite active: Terre et Mine frappent deja la surface. Les gestes, preuves et secousses publiques deviennent l entree la plus directe.",
			playerNote: "Mode duo: le lecteur garde la chaleur du corps, un peu plus de densite mediane et une relance souple pour accompagner la frappe.",
			status: "preset duo",
			rateDelta: 0.02,
			preservePitch: true,
			bassDelta: 0.7,
			midDelta: 0.8,
			trebleDelta: 0.5,
			gainDelta: 5,
		};
	}

	return {
		focus: "lands",
		tone: "face",
		playerKey: "face-breath",
		note: "Proximite tenue: Str3m reste plus proche du souffle, des presences visibles et du bord de peau avant d ouvrir le flux.",
		playerNote: "Mode proche: la lecture garde une tenue plus douce, un peu plus lente, pour laisser le visage, la voix et la lumiere respirer.",
		status: "preset proche",
		rateDelta: -0.02,
		preservePitch: true,
		bassDelta: 0.2,
		midDelta: 0.4,
		trebleDelta: -0.3,
		gainDelta: 3,
	};
}

function echoRaProfileFromState(state) {
	if (!state || typeof state !== "object") {
		return null;
	}

	if (state.mode === "translate" || (state.mode === "weave" && state.dominant === "plasma")) {
		return {
			focus: "contacts",
			primary: "signal",
			note: "Régime traduit: laisse encore Signal cadrer l’adresse et la nuance avant de couper plus court en direct.",
			emptyNote: "Le plasma traduit encore la prise: choisis d’abord la terre, relis le fil si besoin, puis tranche en direct seulement quand la liaison se ferme clairement.",
			threadNote: "La liaison reste directe, mais garde Signal proche pour relire la mémoire et la nuance autour du passage.",
			composeNote: "Transmission encore fine: fais passer ce qui doit traverser sans casser la couture entre les deux terres.",
			placeholder: "Écris ce qui doit traverser la liaison sans la brusquer…",
		};
	}

	if (state.mode === "loop" || (state.mode === "weave" && state.dominant === "torus")) {
		return {
			focus: "direct",
			primary: "echo",
			note: "Régime bouclé: la prise est déjà assez nette pour qu’Écho relance la même terre sans détour.",
			emptyNote: "Le tore boucle déjà la prise: si la terre est connue, ouvre-la à gauche puis frappe court, net, en direct.",
			threadNote: "Le tore serre déjà la liaison: Écho peut repartir court et franc, puis renvoyer vers Signal seulement pour la mémoire longue.",
			composeNote: "Transmission franche: peu de préambule, un geste clair, une relance nette.",
			placeholder: "Relance la terre, net, sans détour…",
		};
	}

	return {
		focus: "direct",
		primary: "echo",
		note: "Régime ancré: Écho tient bien une prise déjà nommée. Si le nom manque encore, Signal garde l’adresse avant le direct.",
		emptyNote: "La réalité tient déjà la relation: nomme la terre, ouvre-la, puis laisse Écho garder une transmission simple et stable.",
		threadNote: "La réalité porte déjà la liaison: parle simplement, garde le direct stable, puis reviens à Signal si la mémoire doit s’élargir.",
		composeNote: "Transmission ancrée: une phrase nette, posée, assez simple pour que la terre la reçoive d’un bloc.",
		placeholder: "Écris une relance simple, posée, ancrée…",
	};
}

function str3mPlayerPresetFromRaState(state) {
	if (!state || typeof state !== "object") {
		return null;
	}

	if (state.mode === "translate" || (state.mode === "weave" && state.dominant === "plasma")) {
		return {
			key: "translate",
			rate: 1.03,
			preservePitch: true,
			bass: -0.5,
			mid: 1.5,
			treble: 1.2,
			gain: 106,
			status: "preset traduit",
			note: "Régime traduit: le lecteur s’éclaircit légèrement pour laisser mieux passer les coutures, les voix et les matières intermédiaires.",
		};
	}

	if (state.mode === "loop" || (state.mode === "weave" && state.dominant === "torus")) {
		return {
			key: "loop",
			rate: 1.08,
			preservePitch: false,
			bass: 2.2,
			mid: 0.8,
			treble: 0.5,
			gain: 110,
			status: "preset bouclé",
			note: "Régime bouclé: le lecteur pousse un peu l’élan, le bas et la relance pour rendre les prises publiques plus franches.",
		};
	}

	return {
		key: "anchor",
		rate: 0.96,
		preservePitch: true,
		bass: 1.2,
		mid: -0.4,
		treble: -0.8,
		gain: 104,
		status: "preset ancré",
		note: "Régime ancré: le lecteur ralentit légèrement et garde une tenue plus dense pour laisser apparaître les terres et les repères.",
	};
}

function str3mPlayerPresetFromSpatialState(raState, worldState) {
	const base = str3mPlayerPresetFromRaState(raState) || {
		key: "anchor",
		rate: 1,
		preservePitch: true,
		bass: 0,
		mid: 0,
		treble: 0,
		gain: 104,
		status: "preset stable",
		note: "",
	};
	const world = str3mWorldProfileFromState(worldState);
	if (!world) {
		return {
			...base,
			worldKey: "",
			tone: "",
		};
	}

	return {
		...base,
		worldKey: world.playerKey || "",
		tone: world.tone || "",
		rate: clampNumber((base.rate ?? 1) + (world.rateDelta ?? 0), 0.5, 2),
		preservePitch: typeof world.preservePitch === "boolean" ? world.preservePitch : base.preservePitch,
		bass: clampNumber((base.bass ?? 0) + (world.bassDelta ?? 0), -12, 12),
		mid: clampNumber((base.mid ?? 0) + (world.midDelta ?? 0), -12, 12),
		treble: clampNumber((base.treble ?? 0) + (world.trebleDelta ?? 0), -12, 12),
		gain: clampNumber((base.gain ?? 100) + (world.gainDelta ?? 0), 0, 150),
		status: world.status || base.status,
		note: [world.playerNote, base.note].filter(Boolean).join(" "),
	};
}

function composeStr3mSpatialProfile(raState, worldState) {
	const raProfile = str3mRaProfileFromState(raState);
	const worldProfile = str3mWorldProfileFromState(worldState);
	const focus = worldProfile?.focus || raProfile?.focus || "";

	return {
		raProfile,
		worldProfile,
		focus,
		note: [worldProfile?.note, raProfile?.note].filter(Boolean).join(" "),
		playerPreset: str3mPlayerPresetFromSpatialState(raState, worldState),
	};
}

function pickIslandReaderKey(candidates, availableKeys, excludedKeys = []) {
	if (!Array.isArray(candidates) || !Array.isArray(availableKeys) || !availableKeys.length) {
		return "";
	}

	const excluded = new Set(
		Array.isArray(excludedKeys)
			? excludedKeys.filter(Boolean)
			: []
	);

	for (const candidate of candidates) {
		const key = typeof candidate === "string" ? candidate.trim() : "";
		if (!key || excluded.has(key)) {
			continue;
		}
		if (availableKeys.includes(key)) {
			return key;
		}
	}

	return "";
}

function islandRaProfileFromState(state) {
	if (!state || typeof state !== "object") {
		return null;
	}

	if (state.mode === "translate" || (state.mode === "weave" && state.dominant === "plasma")) {
		return {
			focus: "translate",
			primaryCandidates: ["audio", "text", "data", "archive"],
			secondaryCandidates: ["pdf", "image", "svg"],
			note: "Regime traduit: la station relit d abord voix, texte, donnee et memoire avant les surfaces les plus immediates.",
		};
	}

	if (state.mode === "loop" || (state.mode === "weave" && state.dominant === "torus")) {
		return {
			focus: "loop",
			primaryCandidates: ["model", "svg", "design", "archive"],
			secondaryCandidates: ["video", "image", "pdf"],
			note: "Regime boucle: la station privilegie relief, trace, structure et objet avant la simple illustration.",
		};
	}

	return {
		focus: "anchor",
		primaryCandidates: ["image", "video", "pdf", "svg"],
		secondaryCandidates: ["audio", "text", "data"],
		note: "Regime ancre: la station garde d abord les surfaces visibles, les reperes et les documents avant les couches plus abstraites.",
	};
}

function islandWorldProfileFromState(state) {
	if (!state || typeof state !== "object") {
		return null;
	}

	const cameraFacing = state.cameraFacing === "environment" ? "environment" : "user";
	const sceneEnergy = clampNumber(Number(state.sceneEnergy) || 0, 0, 1);
	const touchEnergy = clampNumber(Number(state.touchEnergy) || 0, 0, 1);
	const activeHands = Math.max(0, Number.parseInt(state.activeHands, 10) || 0);
	const focusLabel = String(state.focusLabel || "").toLowerCase();
	const lightLabel = String(state.lightLabel || "").toLowerCase();
	const touchLabel = String(state.touchLabel || "").toLowerCase();
	const hasDuetHands = activeHands >= 2 || touchLabel.includes("terre + mine");
	const hasReflections = focusLabel.includes("reflets") || focusLabel.includes("dehors") || lightLabel.includes("clair");
	const isWalking = focusLabel.includes("marche") || focusLabel.includes("horizon") || sceneEnergy > 0.52;

	if (cameraFacing === "environment") {
		if (isWalking) {
			return {
				tone: "landscape",
				primaryCandidates: ["video", "image", "pdf"],
				secondaryCandidates: ["svg", "data"],
				note: "Paysage actif: la station pousse les matieres qui gardent marche, horizon, reflets et terrain lisibles.",
			};
		}

		if (hasReflections) {
			return {
				tone: "landscape",
				primaryCandidates: ["image", "video", "svg"],
				secondaryCandidates: ["design", "pdf"],
				note: "Paysage lumineux: la station avance les surfaces qui retiennent reflets, dehors et details de peau du monde.",
			};
		}

		return {
			tone: "landscape",
			primaryCandidates: ["image", "pdf", "text"],
			secondaryCandidates: ["video", "data"],
			note: "Paysage tenu: la station garde une lecture large, stable et documentee avant de densifier la matiere.",
		};
	}

	if (hasDuetHands || touchEnergy > 0.34) {
		return {
			tone: "face",
			primaryCandidates: ["audio", "model", "design"],
			secondaryCandidates: ["text", "archive"],
			note: "Proximite active: Terre et Mine poussent la station vers les matieres jouables, tactiles ou sculpturales.",
		};
	}

	return {
		tone: "face",
		primaryCandidates: ["audio", "text", "image"],
		secondaryCandidates: ["pdf", "data"],
		note: "Proximite tenue: la station garde une lecture proche, entre voix, texte et surface visible.",
	};
}

function composeIslandSpatialProfile(raState, worldState, availableKeys = []) {
	const raProfile = islandRaProfileFromState(raState);
	const worldProfile = islandWorldProfileFromState(worldState);
	const primary = pickIslandReaderKey(
		[
			...(worldProfile?.primaryCandidates || []),
			...(raProfile?.primaryCandidates || []),
			...availableKeys,
		],
		availableKeys
	) || (availableKeys[0] || "");
	const secondary = pickIslandReaderKey(
		[
			...(raProfile?.secondaryCandidates || []),
			...(worldProfile?.secondaryCandidates || []),
			...availableKeys,
		],
		availableKeys,
		primary ? [primary] : []
	);

	return {
		raProfile,
		worldProfile,
		primary,
		secondary,
		note: [worldProfile?.note, raProfile?.note].filter(Boolean).join(" "),
	};
}

function normalizeRoutePathForComparison(value) {
	const source = typeof value === "string" ? value : "";
	if (!source) {
		return "";
	}

	try {
		const url = new URL(source, window.location.origin);
		return `${url.pathname}${url.search}`;
	} catch {
		return source;
	}
}

function withoutBridgePrefix(pathname = "/") {
	const value = typeof pathname === "string" && pathname !== "" ? pathname : "/";
	const prefix = bridgePrefix();
	if (!prefix) {
		return value;
	}

	if (value === prefix) {
		return "/";
	}

	if (value.startsWith(`${prefix}/`)) {
		return value.slice(prefix.length) || "/";
	}

	return value;
}

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

const displayModeMedia = typeof window.matchMedia === "function"
	? window.matchMedia("(display-mode: standalone)")
	: null;

let deferredInstallPrompt = null;
const nativeDeviceState = {
	silenceMode: "unknown",
	volume: null,
	route: "",
	source: "web",
};
let currentDeviceBridgeState = null;

function normalizeNativeSilenceMode(value) {
	const normalized = typeof value === "string" ? value.trim().toLowerCase() : "";
	if (["silent", "vibrate", "ring"].includes(normalized)) {
		return normalized;
	}

	return "unknown";
}

function normalizeNativeVolume(value) {
	if (!Number.isFinite(Number(value))) {
		return null;
	}

	return clampNumber(Number(value), 0, 1);
}

function readDeviceSilenceIntent() {
	return window.localStorage.getItem(DEVICE_SILENCE_INTENT_KEY) === "1";
}

function writeDeviceSilenceIntent(isSilent) {
	window.localStorage.setItem(DEVICE_SILENCE_INTENT_KEY, isSilent ? "1" : "0");
	syncDeviceBridgeState();
}

function readDeviceVolumeLevel() {
	const raw = Number(window.localStorage.getItem(DEVICE_VOLUME_LEVEL_KEY) || "0.94");
	return clampNumber(Number.isFinite(raw) ? raw : 0.94, 0, 1);
}

function writeDeviceVolumeLevel(level) {
	window.localStorage.setItem(DEVICE_VOLUME_LEVEL_KEY, String(clampNumber(level, 0, 1)));
	syncDeviceBridgeState();
}

function readXyzVoiceEchoLevel() {
	const raw = Number(window.localStorage.getItem(XYZ_VOICE_ECHO_KEY) || "0.08");
	return clampNumber(Number.isFinite(raw) ? raw : 0.08, 0, 1);
}

function writeXyzVoiceEchoLevel(level) {
	window.localStorage.setItem(XYZ_VOICE_ECHO_KEY, String(clampNumber(level, 0, 1)));
}

function migrateAudioDefaults() {
	try {
		if (window.localStorage.getItem(AUDIO_DEFAULTS_VERSION_KEY) === "2") {
			return;
		}

		const storedVolume = Number(window.localStorage.getItem(DEVICE_VOLUME_LEVEL_KEY) || "");
		if (!Number.isFinite(storedVolume) || storedVolume <= 0.82) {
			window.localStorage.setItem(DEVICE_VOLUME_LEVEL_KEY, "0.94");
		}

		const storedEcho = Number(window.localStorage.getItem(XYZ_VOICE_ECHO_KEY) || "");
		if (!Number.isFinite(storedEcho) || storedEcho >= 0.18) {
			window.localStorage.setItem(XYZ_VOICE_ECHO_KEY, "0.08");
		}

		window.localStorage.setItem(AUDIO_DEFAULTS_VERSION_KEY, "2");
	} catch {
		// Ignore storage migration failures.
	}
}

migrateAudioDefaults();

function deviceIsStandalone() {
	return Boolean(displayModeMedia?.matches || window.navigator.standalone === true);
}

function readNativeDeviceSeed() {
	const seed = window.__O_NATIVE_DEVICE__;
	if (!seed || typeof seed !== "object") {
		return null;
	}

	return seed;
}

function updateNativeDeviceState(partial = {}) {
	if (!partial || typeof partial !== "object") {
		return getCurrentDeviceBridgeState();
	}

	nativeDeviceState.silenceMode = normalizeNativeSilenceMode(partial.silenceMode ?? partial.ringerMode ?? nativeDeviceState.silenceMode);
	nativeDeviceState.volume = normalizeNativeVolume(partial.volume ?? nativeDeviceState.volume);
	nativeDeviceState.route = typeof partial.route === "string"
		? partial.route.trim().slice(0, 32)
		: nativeDeviceState.route;
	nativeDeviceState.source = typeof partial.source === "string" && partial.source.trim()
		? partial.source.trim().slice(0, 24)
		: nativeDeviceState.source;

	return syncDeviceBridgeState();
}

function syncNativeDeviceSeed() {
	const seed = readNativeDeviceSeed();
	if (!seed) {
		return;
	}

	updateNativeDeviceState(seed);
}

function computeDeviceBridgeState() {
	const silenceIntent = readDeviceSilenceIntent();
	const volumeIntent = readDeviceVolumeLevel();
	const webVolume = clampNumber(volumeIntent, 0, 1);
	const nativeSilenceMode = normalizeNativeSilenceMode(nativeDeviceState.silenceMode);
	const nativeVolume = normalizeNativeVolume(nativeDeviceState.volume);
	const nativeSource = nativeDeviceState.source || "web";
	const nativeRoute = nativeDeviceState.route || "";
	const nativeAudioTrusted = (
		nativeSilenceMode !== "unknown"
		|| nativeRoute !== ""
		|| (nativeSource && nativeSource !== "web")
	);
	const effectiveVolume = clampNumber(((nativeAudioTrusted ? nativeVolume : null) ?? 1) * webVolume, 0, 1);
	const muted = silenceIntent || nativeSilenceMode === "silent" || webVolume <= 0.01;

	return {
		silenceIntent,
		volumeIntent,
		webVolume,
		effectiveVolume,
		muted,
		nativeSilenceMode,
		nativeVolume,
		nativeAudioTrusted,
		nativeRoute,
		nativeSource,
		visibility: document.hidden ? "hidden" : "visible",
		standalone: deviceIsStandalone(),
		hapticsAvailable: typeof navigator.vibrate === "function",
		shareAvailable: typeof navigator.share === "function",
		installAvailable: Boolean(deferredInstallPrompt),
		orientationLockAvailable: Boolean(screen.orientation && typeof screen.orientation.lock === "function"),
	};
}

function syncDeviceBridgeState() {
	currentDeviceBridgeState = computeDeviceBridgeState();
	const body = document.body;
	if (body instanceof HTMLBodyElement) {
		body.dataset.deviceSilenceIntent = currentDeviceBridgeState.silenceIntent ? "1" : "0";
		body.dataset.deviceVolume = currentDeviceBridgeState.webVolume.toFixed(3);
		body.dataset.deviceNativeSilence = currentDeviceBridgeState.nativeSilenceMode;
		body.dataset.deviceVisibility = currentDeviceBridgeState.visibility;
		body.dataset.deviceStandalone = currentDeviceBridgeState.standalone ? "1" : "0";
		body.dataset.deviceNativeRoute = currentDeviceBridgeState.nativeRoute || "web";
	}

	window.dispatchEvent(new CustomEvent("o:device-bridge-change", {
		detail: currentDeviceBridgeState,
	}));

	return currentDeviceBridgeState;
}

function getCurrentDeviceBridgeState() {
	return currentDeviceBridgeState || syncDeviceBridgeState();
}

function readDeviceAudioProfile() {
	const state = getCurrentDeviceBridgeState();
	return {
		muted: Boolean(state.muted),
		volume: clampNumber(state.webVolume, 0, 1),
		mixedVolume: clampNumber(state.effectiveVolume, 0, 1),
		silenceIntent: Boolean(state.silenceIntent),
		nativeSilenceMode: state.nativeSilenceMode,
		nativeVolume: state.nativeVolume,
		route: state.nativeRoute,
	};
}

function pulseDeviceHaptics(pattern = "soft") {
	if (typeof navigator.vibrate !== "function" || document.hidden) {
		return false;
	}

	const resolvedPattern = {
		soft: 12,
		medium: [18, 28, 18],
		deep: [22, 36, 22, 36, 28],
	}[pattern] || 12;

	return navigator.vibrate(resolvedPattern);
}

async function promptDeviceInstall() {
	if (!deferredInstallPrompt) {
		return false;
	}

	const promptEvent = deferredInstallPrompt;
	deferredInstallPrompt = null;
	try {
		await promptEvent.prompt();
		await promptEvent.userChoice.catch(() => null);
	} catch {
		// Ignore install prompt failures.
	}

	syncDeviceBridgeState();
	return true;
}

async function shareCurrentDeviceSurface({ title = document.title, text = "", url = window.location.href } = {}) {
	if (typeof navigator.share !== "function") {
		return false;
	}

	try {
		await navigator.share({ title, text, url });
		return true;
	} catch {
		return false;
	}
}

async function requestDeviceOrientationLock(mode = "portrait-primary") {
	if (!coarsePointer || !screen.orientation || typeof screen.orientation.lock !== "function") {
		return false;
	}

	try {
		await screen.orientation.lock(mode);
		return true;
	} catch {
		return false;
	}
}

function releaseDeviceOrientationLock() {
	if (!screen.orientation || typeof screen.orientation.unlock !== "function") {
		return;
	}

	try {
		screen.orientation.unlock();
	} catch {
		// Ignore unlock failures.
	}
}

window.addEventListener("o:native-device-state", (event) => {
	updateNativeDeviceState(event?.detail || {});
});

window.OBridgeNativeDevice = {
	updateState: updateNativeDeviceState,
	readState: () => getCurrentDeviceBridgeState(),
};

window.addEventListener("beforeinstallprompt", (event) => {
	event.preventDefault();
	deferredInstallPrompt = event;
	syncDeviceBridgeState();
});

window.addEventListener("appinstalled", () => {
	deferredInstallPrompt = null;
	syncDeviceBridgeState();
});

document.addEventListener("visibilitychange", () => {
	syncDeviceBridgeState();
});
window.addEventListener("focus", () => {
	syncDeviceBridgeState();
});
window.addEventListener("pageshow", () => {
	syncNativeDeviceSeed();
	syncDeviceBridgeState();
});

if (displayModeMedia) {
	if (typeof displayModeMedia.addEventListener === "function") {
		displayModeMedia.addEventListener("change", () => {
			syncDeviceBridgeState();
		});
	} else if (typeof displayModeMedia.addListener === "function") {
		displayModeMedia.addListener(() => {
			syncDeviceBridgeState();
		});
	}
}

syncNativeDeviceSeed();
syncDeviceBridgeState();

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
	let pitch = 0.8 + frequencyBias * 0.22;
	let rate = 0.84 + frequencyBias * 0.11;
	let volume = 0.84;

	switch ((program || "collective").toLowerCase()) {
		case "dur3rb":
			pitch -= 0.05;
			rate -= 0.05;
			volume += 0.01;
			break;
		case "tocu":
			pitch += 0.04;
			rate += 0.03;
			break;
		case "culbu1on":
			pitch += 0.02;
			rate += 0.01;
			break;
		default:
			break;
	}

	pitch = clampNumber(pitch, 0.74, 1.05);
	rate = clampNumber(rate, 0.82, 1.02);
	volume = clampNumber(volume, 0.76, 0.9);

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
			cameraReady: false,
			luma: 0,
			motion: 0,
			rgb: [180, 180, 180],
			audioLevel: 0,
			lightLevel: 0,
			tiltX: 0,
			tiltY: 0,
			motionSensor: 0,
			presence: 0,
		};
	}

	const cameraReady = body.classList.contains("is-camera-ready");
	const membraneReady = body.classList.contains("is-membrane-live");
	const luma = clampNumber(Number.parseFloat(body.dataset.cameraLuma || "0"), 0, 1);
	const motion = clampNumber(Number.parseFloat(body.dataset.cameraMotion || "0"), 0, 1);
	const audioLevel = clampNumber(Number.parseFloat(body.dataset.membraneAudio || "0"), 0, 1);
	const lightLevel = clampNumber(Number.parseFloat(body.dataset.membraneLight || String(luma)), 0, 1);
	const tiltX = clampNumber(Number.parseFloat(body.dataset.membraneTiltX || "0"), -1, 1);
	const tiltY = clampNumber(Number.parseFloat(body.dataset.membraneTiltY || "0"), -1, 1);
	const motionSensor = clampNumber(Number.parseFloat(body.dataset.membraneMotion || "0"), 0, 1);
	const presence = clampNumber(Number.parseFloat(body.dataset.membranePresence || String(Math.max(luma, audioLevel))), 0, 1);

	return {
		ready: cameraReady || membraneReady,
		cameraReady,
		luma,
		motion,
		rgb: parseRgbTriplet(body.dataset.cameraRgb || "180 180 180", [180, 180, 180]),
		audioLevel,
		lightLevel,
		tiltX,
		tiltY,
		motionSensor,
		presence,
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
	const cameraFacing = document.body.dataset.cameraFacing === "environment" ? "environment" : "user";
	const worldFocus = document.body.dataset.worldInstrumentFocus === "landscape" ? "landscape" : "face";
	const worldEnergy = clampNumber(Number.parseFloat(bodyStyles.getPropertyValue("--xyz-world-energy")) || 0, 0, 1);
	const touchEnergy = clampNumber(Number.parseFloat(bodyStyles.getPropertyValue("--xyz-touch-energy")) || 0, 0, 1);
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
		const idleWorldMix = cameraFacing === "environment"
			? clampNumber(0.24 + worldEnergy * 0.28, 0.24, 0.58)
			: clampNumber(0.12 + touchEnergy * 0.24, 0.12, 0.34);
		const idlePrimary = cameraFacing === "environment"
			? mixRgb(profile.primary, [134, 223, 255], idleWorldMix)
			: mixRgb(profile.primary, [255, 178, 132], idleWorldMix * 0.72);
		const idleSecondary = cameraFacing === "environment"
			? mixRgb(profile.secondary, [132, 255, 213], idleWorldMix * 0.82)
			: mixRgb(profile.secondary, [255, 214, 168], idleWorldMix * 0.56);
		const idleGlow = cameraFacing === "environment"
			? mixRgb(profile.glow, [178, 234, 255], idleWorldMix * 0.92)
			: mixRgb(profile.glow, [255, 185, 146], idleWorldMix * 0.62);
		return {
			...profile,
			primary: idlePrimary,
			secondary: idleSecondary,
			glow: idleGlow,
			waveStrength: clampNumber(profile.waveStrength + (cameraFacing === "environment" ? 0.16 : 0.04), 0.48, 1.72),
			pulseStrength: clampNumber(profile.pulseStrength + (cameraFacing === "environment" ? 0.04 : touchEnergy * 0.18), 0.38, 1.52),
			haloStrength: clampNumber(0.28 + idleWorldMix * 0.3, 0.28, 0.9),
			haloColor: mixRgb(idleGlow, idleSecondary, cameraFacing === "environment" ? 0.46 : 0.32),
			streakMix: clampNumber((profile.signalMode ? 0.34 : 0.18) + idleWorldMix * (cameraFacing === "environment" ? 0.18 : 0.08), 0.18, 0.78),
			signalMode: profile.signalMode || cameraFacing === "environment",
			signalColors: Array.isArray(profile.signalColors) && profile.signalColors.length
				? profile.signalColors
				: [
					mixRgb(idlePrimary, idleSecondary, cameraFacing === "environment" ? 0.44 : 0.28),
					mixRgb(idleSecondary, idleGlow, cameraFacing === "environment" ? 0.52 : 0.42),
					mixRgb(idlePrimary, [255, 255, 255], cameraFacing === "environment" ? 0.24 : 0.16),
				],
		};
	}

	const motionEnergy = clampNumber(Math.max(cameraState.motion, cameraState.motionSensor * 0.92), 0, 1);
	const audioEnergy = clampNumber(cameraState.audioLevel, 0, 1);
	const tiltEnergy = clampNumber((Math.abs(cameraState.tiltX) + Math.abs(cameraState.tiltY)) * 0.5, 0, 1);
	const sensorEnergy = clampNumber(
		cameraState.motion * 0.34
		+ cameraState.motionSensor * 0.22
		+ cameraState.audioLevel * 0.26
		+ cameraState.lightLevel * 0.14
		+ Math.abs(cameraState.tiltX) * 0.12
		+ Math.abs(cameraState.tiltY) * 0.14,
		0,
		1
	);
	const coolAccent = mixRgb(
		cameraState.rgb,
		[96, 232, 255],
		clampNumber(0.24 + cameraState.lightLevel * 0.44 + cameraState.luma * 0.18, 0.24, 0.72)
	);
	const warmAccent = mixRgb(
		cameraState.rgb,
		[255, 110, 166],
		clampNumber(0.22 + motionEnergy * 0.5 + audioEnergy * 0.18, 0.22, 0.78)
	);
	const kineticAccent = mixRgb(
		coolAccent,
		warmAccent,
		clampNumber(0.38 + motionEnergy * 0.44 + audioEnergy * 0.18 - cameraState.lightLevel * 0.12, 0, 1)
	);
	const chromaMix = clampNumber(0.18 + cameraState.motion * 0.28 + cameraState.motionSensor * 0.22 + cameraState.luma * 0.08 + audioEnergy * 0.12, 0.18, 0.72);
	const secondaryMix = clampNumber(chromaMix * 0.88 + tiltEnergy * 0.12, 0.16, 0.78);
	const glowMix = clampNumber(0.22 + sensorEnergy * 0.42 + cameraState.presence * 0.16 + motionEnergy * 0.12, 0.22, 0.86);
	const kineticMix = clampNumber(0.18 + motionEnergy * 0.48 + audioEnergy * 0.16, 0.18, 0.72);
	const environmentMix = cameraFacing === "environment"
		? clampNumber(0.22 + worldEnergy * 0.34 + cameraState.lightLevel * 0.16, 0.22, 0.78)
		: 0;
	const duetMix = worldFocus === "face"
		? clampNumber(0.14 + touchEnergy * 0.42 + cameraState.audioLevel * 0.12, 0.14, 0.62)
		: 0;
	const worldPrimary = cameraFacing === "environment"
		? mixRgb(kineticAccent, [154, 228, 255], environmentMix)
		: mixRgb(kineticAccent, [255, 176, 138], duetMix * 0.76);
	const worldSecondary = cameraFacing === "environment"
		? mixRgb(coolAccent, [132, 255, 213], environmentMix * 0.72)
		: mixRgb(coolAccent, [255, 226, 188], duetMix * 0.42);
	const worldGlow = cameraFacing === "environment"
		? mixRgb(glow, [176, 236, 255], environmentMix * 0.82)
		: mixRgb(glow, [255, 168, 150], duetMix * 0.54);

	return {
		...profile,
		primary: mixRgb(mixRgb(profile.primary, cameraState.rgb, chromaMix), worldPrimary, kineticMix + environmentMix * 0.18 + duetMix * 0.08),
		secondary: mixRgb(
			mixRgb(profile.secondary, cameraState.rgb, secondaryMix),
			worldSecondary,
			clampNumber(0.14 + cameraState.lightLevel * 0.32 + cameraState.luma * 0.18 + environmentMix * 0.18, 0.14, 0.72)
		),
		glow: mixRgb(
			mixRgb(profile.glow, cameraState.rgb, glowMix),
			worldGlow,
			clampNumber(0.12 + motionEnergy * 0.4 + audioEnergy * 0.18 + environmentMix * 0.2 + duetMix * 0.08, 0.12, 0.82)
		),
		waveStrength: clampNumber(profile.waveStrength + cameraState.motion * 0.24 + cameraState.motionSensor * 0.16 + cameraState.audioLevel * 0.22 + cameraState.lightLevel * 0.12 + environmentMix * 0.36, 0.48, 1.78),
		pulseStrength: clampNumber(profile.pulseStrength + cameraState.motion * 0.2 + cameraState.motionSensor * 0.16 + cameraState.audioLevel * 0.28 + cameraState.presence * 0.12 + duetMix * 0.24, 0.38, 1.58),
		motion: clampNumber(profile.motion + cameraState.motion * 0.24 + cameraState.motionSensor * 0.2 + cameraState.audioLevel * 0.24 + sensorEnergy * 0.12 + environmentMix * 0.18, 0.82, 1.9),
		haloStrength: clampNumber(0.26 + sensorEnergy * 0.72 + motionEnergy * 0.16 + environmentMix * 0.14 + duetMix * 0.08, 0.26, 1),
		haloColor: mixRgb(worldPrimary, worldGlow, cameraFacing === "environment" ? 0.42 : 0.34),
		streakMix: clampNumber(0.18 + motionEnergy * 0.44 + audioEnergy * 0.22 + environmentMix * 0.18 + duetMix * 0.1, 0.18, 0.86),
		signalMode: profile.signalMode || cameraFacing === "environment" || motionEnergy > 0.34 || audioEnergy > 0.22 || cameraState.presence > 0.5,
		signalColors: [
			cameraFacing === "environment" ? mixRgb(worldPrimary, [182, 234, 255], 0.42) : warmAccent,
			cameraFacing === "environment" ? mixRgb(worldSecondary, worldGlow, 0.48) : kineticAccent,
			mixRgb(worldGlow, [255, 255, 255], 0.18 + cameraState.luma * 0.28 + environmentMix * 0.08),
		],
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
			return "/aza";
		case "up":
			return "/#str3m-quotidien";
		case "down":
			return "/";
		default:
			return null;
	}
}

function navigateToStr3mSurface() {
	if (withoutBridgePrefix(window.location.pathname) === "/") {
		const target = document.getElementById("str3m-quotidien");
		if (target) {
			target.scrollIntoView({ behavior: reducedMotion ? "auto" : "smooth", block: "start" });
			window.history.replaceState(null, "", withSurfaceContext("/#str3m-quotidien"));
			return true;
		}
	}

	window.location.assign(withSurfaceContext("/#str3m-quotidien"));
	return true;
}

function navigateToCoreSurface() {
	const current = `${withoutBridgePrefix(window.location.pathname)}${window.location.hash}`;
	if (current === "/" || current === "/#" || current === "/#str3m-quotidien") {
		window.scrollTo({ top: 0, behavior: reducedMotion ? "auto" : "smooth" });
		window.history.replaceState(null, "", withSurfaceContext("/"));
		return true;
	}

	window.location.assign(withSurfaceContext("/"));
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

	const current = `${withoutBridgePrefix(window.location.pathname)}${window.location.hash}`;
	if (current !== destination) {
		window.location.assign(withSurfaceContext(destination));
		return true;
	}

	return false;
}

function initNucleusBanner() {
	const banner = document.querySelector("[data-nucleus-banner]");
	if (!(banner instanceof HTMLAnchorElement)) {
		return;
	}

	const note = banner.querySelector("[data-nucleus-banner-note]");
	const state = {
		pointerId: null,
		pointerType: "",
		startX: 0,
		startY: 0,
		longTouchTimer: 0,
		longTouchActive: false,
		longTouchDirection: "",
		suppressClick: false,
	};

	const setNote = (direction = "") => {
		if (!(note instanceof HTMLElement)) {
			return;
		}

		if (state.longTouchActive) {
			const messageByDirection = {
				left: "Relache pour Signal",
				right: "Relache pour aZa",
				up: "Relache pour Str3m",
				down: "Relache pour le noyau",
			};
			note.textContent = messageByDirection[direction] || "Glisse : Signal · Str3m · aZa · Noyau";
			return;
		}

		note.textContent = coarsePointer
			? "clic : noyau · appui long tactile : glisse"
			: "clic : noyau";
	};

	const clearTimer = () => {
		if (!state.longTouchTimer) {
			return;
		}

		window.clearTimeout(state.longTouchTimer);
		state.longTouchTimer = 0;
	};

	const setDirection = (direction = "") => {
		state.longTouchDirection = direction;
		if (direction) {
			banner.dataset.navDirection = direction;
		} else {
			delete banner.dataset.navDirection;
		}
		setNote(direction);
	};

	const releasePointer = () => {
		if (state.pointerId !== null && banner.hasPointerCapture?.(state.pointerId)) {
			banner.releasePointerCapture(state.pointerId);
		}

		state.pointerId = null;
		state.pointerType = "";
	};

	const resetGesture = () => {
		clearTimer();
		state.longTouchActive = false;
		banner.classList.remove("is-nav-armed");
		setDirection("");
		setNote();
	};

	const armLongTouch = (pointerId) => {
		clearTimer();
		state.longTouchTimer = window.setTimeout(() => {
			if (state.pointerId !== pointerId || state.pointerType !== "touch") {
				return;
			}

			state.longTouchActive = true;
			banner.classList.add("is-nav-armed");
			banner.setPointerCapture?.(pointerId);
			setNote();
		}, 360);
	};

	banner.addEventListener("pointerdown", (event) => {
		resetGesture();
		state.pointerId = event.pointerId;
		state.pointerType = event.pointerType || "";
		state.startX = event.clientX;
		state.startY = event.clientY;

		if (state.pointerType === "touch") {
			armLongTouch(event.pointerId);
		}
	});

	banner.addEventListener("pointermove", (event) => {
		if (state.pointerId !== event.pointerId || state.pointerType !== "touch") {
			return;
		}

		const deltaX = event.clientX - state.startX;
		const deltaY = event.clientY - state.startY;
		const distance = Math.hypot(deltaX, deltaY);

		if (!state.longTouchActive && distance > 14) {
			clearTimer();
			releasePointer();
			return;
		}

		if (!state.longTouchActive) {
			return;
		}

		event.preventDefault();
		const direction = detectCardinalDirection(deltaX, deltaY, distance, 18, 1.08);
		setDirection(direction || "");
	});

	banner.addEventListener("pointerup", (event) => {
		if (state.pointerId !== event.pointerId) {
			return;
		}

		const deltaX = event.clientX - state.startX;
		const deltaY = event.clientY - state.startY;
		const distance = Math.hypot(deltaX, deltaY);
		const wasLongTouch = state.longTouchActive;
		const direction = state.longTouchDirection || detectCardinalDirection(deltaX, deltaY, distance, 18, 1.08);

		resetGesture();
		releasePointer();

		if (!wasLongTouch) {
			return;
		}

		event.preventDefault();
		state.suppressClick = true;
		window.setTimeout(() => {
			state.suppressClick = false;
		}, 0);

		if (direction) {
			navigateFromSwipe(direction);
			return;
		}

		navigateToCoreSurface();
	});

	banner.addEventListener("pointercancel", () => {
		resetGesture();
		releasePointer();
	});

	banner.addEventListener("click", (event) => {
		if (!state.suppressClick) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();
	});

	setNote();
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
	const motionProfiles = {
		present: { sway: 5, bob: 8, depth: 10, tilt: 1.4, scale: 1.02, speed: 0.0011 },
		near: { sway: 8, bob: 12, depth: 14, tilt: 1.9, scale: 1.01, speed: 0.00135 },
		roaming: { sway: 14, bob: 18, depth: 22, tilt: 2.8, scale: 1.005, speed: 0.0018 },
		asleep: { sway: 3, bob: 5, depth: 6, tilt: 0.9, scale: 0.992, speed: 0.00072 },
		unknown: { sway: 4, bob: 6, depth: 8, tilt: 1.1, scale: 1, speed: 0.00094 },
	};
	const hint = container.querySelector("[data-str3m-archipelago-hint]");
	const setHint = (copy) => {
		if (hint instanceof HTMLElement) {
			hint.textContent = copy;
		}
	};

	const nodeEntries = nodes.map((node, index) => {
		const x = Number(node.dataset.archipelagoX || 0);
		const y = Number(node.dataset.archipelagoY || 0);
		const z = Number(node.dataset.archipelagoZ || 0);
		const presence = node.dataset.presence || "unknown";
		const wrapper = node.querySelector(".archipelago-card-wrapper");
		const profile = motionProfiles[presence] || motionProfiles.unknown;
		const phase = index * 1.61803398875;

		node.style.transform = `translate3d(${x}px, ${y}px, ${z}px)`;

		return {
			node,
			wrapper: wrapper instanceof HTMLElement ? wrapper : null,
			baseX: x,
			baseY: y,
			baseZ: z,
			presence,
			profile,
			phase,
		};
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

	const render = (time = 0) => {
		rotX += (targetRotX - rotX) * 0.08;
		rotY += (targetRotY - rotY) * 0.08;
		posZ += (targetPosZ - posZ) * 0.08;
		scene.style.transform = `translateZ(${posZ}px) rotateX(${rotX}deg) rotateY(${rotY}deg)`;

		nodeEntries.forEach((entry) => {
			const motionFactor = reducedMotion ? 0.14 : 1;
			const primaryPhase = time * entry.profile.speed + entry.phase;
			const secondaryPhase = time * (entry.profile.speed * 0.63) + entry.phase * 0.5;
			const tertiaryPhase = time * (entry.profile.speed * 0.42) + entry.phase * 1.24;

			const swayX = Math.sin(primaryPhase) * entry.profile.sway * motionFactor;
			const bobY = Math.cos(secondaryPhase) * entry.profile.bob * motionFactor;
			const driftZ = Math.sin(tertiaryPhase) * entry.profile.depth * motionFactor;
			entry.node.style.transform = `translate3d(${entry.baseX + swayX}px, ${entry.baseY + bobY}px, ${entry.baseZ + driftZ}px)`;

			if (!(entry.wrapper instanceof HTMLElement)) {
				return;
			}

			const tiltX = Math.sin(secondaryPhase) * entry.profile.tilt * motionFactor;
			const tiltY = Math.cos(primaryPhase) * entry.profile.tilt * motionFactor;
			const scale = 1 + ((entry.profile.scale - 1) * motionFactor) + (Math.sin(primaryPhase) * 0.008 * motionFactor);
			entry.wrapper.style.transform = `translate(-50%, -50%) rotateY(${-rotY + tiltY}deg) rotateX(${-rotX + tiltX}deg) scale(${scale.toFixed(4)})`;
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

function initStr3mShellFutureBridge() {
	const shellCards = Array.from(document.querySelectorAll("[data-shell-future='land']"))
		.filter((node) => node instanceof HTMLElement);

	if (!shellCards.length) {
		return;
	}

	const buildDetail = (card) => ({
		landSlug: card.dataset.landSlug || "",
		landLabel: card.dataset.landLabel || "",
		state: card.dataset.shellState || card.dataset.presence || "unknown",
		source: card.dataset.shellSource || "str3m",
		route: card.dataset.shellRoute || "",
		manifestRoute: card.dataset.shellManifestRoute || "",
	});

	const announce = (eventName, card) => {
		const detail = buildDetail(card);
		document.body.dataset.str3mShellLand = detail.landSlug;
		document.body.dataset.str3mShellState = detail.state;
		window.dispatchEvent(new CustomEvent(eventName, { detail }));
	};

	shellCards.forEach((card) => {
		card.addEventListener("pointerenter", () => {
			card.classList.add("is-shell-armed");
			announce("o:str3m-shell-preview", card);
		});

		card.addEventListener("pointerleave", () => {
			card.classList.remove("is-shell-armed");
				announce("o:str3m-shell-rest", card);
		});

		card.addEventListener("focusin", () => {
			card.classList.add("is-shell-armed");
			announce("o:str3m-shell-preview", card);
		});

		card.addEventListener("focusout", () => {
			card.classList.remove("is-shell-armed");
				announce("o:str3m-shell-rest", card);
		});

		card.addEventListener("pointerdown", () => {
			card.classList.add("is-shell-pressed");
			announce("o:str3m-shell-intent", card);
		});

		card.addEventListener("pointerup", () => {
			card.classList.remove("is-shell-pressed");
		});

		card.addEventListener("pointercancel", () => {
			card.classList.remove("is-shell-pressed");
		});

		card.addEventListener("keydown", (event) => {
			if (event.key === "Enter" || event.key === " ") {
				card.classList.add("is-shell-pressed");
				announce("o:str3m-shell-intent", card);
			}
		});

		card.addEventListener("keyup", () => {
			card.classList.remove("is-shell-pressed");
		});
	});
}

function initStr3mGhostShellDock() {
	const dock = document.querySelector("[data-str3m-shell-ghost]");
	if (!(dock instanceof HTMLElement)) {
		return;
	}

	const labelNode = dock.querySelector("[data-str3m-shell-ghost-label]");
	const stateNode = dock.querySelector("[data-str3m-shell-ghost-state]");
	const metaNode = dock.querySelector("[data-str3m-shell-ghost-meta]");
	const modeNode = dock.querySelector("[data-str3m-shell-ghost-mode]");
	   const birdNode = dock.querySelector("[data-str3m-shell-ghost-bird]");
	   // Select each glyph for compass logic
	   const glyphs = {
		   direction: birdNode?.querySelector('[data-boussole="direction"]'),
		   energyLeft: birdNode?.querySelector('[data-boussole="energy-left"]'),
		   mood: birdNode?.querySelector('[data-boussole="mood"]'),
		   energyRight: birdNode?.querySelector('[data-boussole="energy-right"]'),
		   return: birdNode?.querySelector('[data-boussole="return"]'),
	   };
	const routeNode = dock.querySelector("[data-str3m-shell-ghost-route]");
	const manifestNode = dock.querySelector("[data-str3m-shell-ghost-manifest]");

	let previewTimer = 0;
	let restTimer = 0;
	let currentDetail = null;

	const clearTimers = () => {
		if (previewTimer) {
			window.clearTimeout(previewTimer);
			previewTimer = 0;
		}
		if (restTimer) {
			window.clearTimeout(restTimer);
			restTimer = 0;
		}
	};

	const setBirdMood = (mood = "rest") => {
		dock.dataset.shellGhostMood = mood;
		if (birdNode instanceof HTMLElement) {
			birdNode.dataset.shellGhostMood = mood;
		}
		// Update each glyph's compass state.
		if (glyphs.direction) glyphs.direction.dataset.state = (mood === "intent" ? "active" : "idle");
		if (glyphs.energyLeft) glyphs.energyLeft.dataset.state = (mood === "present" || mood === "preview" ? "high" : "low");
		if (glyphs.mood) glyphs.mood.dataset.state = mood;
		if (glyphs.energyRight) glyphs.energyRight.dataset.state = (mood === "present" || mood === "preview" ? "high" : "low");
		if (glyphs.return) glyphs.return.dataset.state = (mood === "rest" || mood === "sleep" ? "active" : "idle");
	};

	const applyDetail = (detail, mode = "en veille") => {
		currentDetail = detail;
		dock.hidden = false;
		dock.classList.add("is-visible");
		dock.dataset.shellGhostState = detail?.state || "unknown";
		setBirdMood(mode === "armé" ? "intent" : "preview");
		if (modeNode instanceof HTMLElement) {
			modeNode.textContent = mode;
		}
		if (labelNode instanceof HTMLElement) {
			labelNode.textContent = detail?.landLabel || detail?.landSlug || "aucune terre armée";
		}
		if (stateNode instanceof HTMLElement) {
			stateNode.textContent = detail
				? `État ${detail.state || "unknown"} · source ${detail.source || "str3m"} · le shell pourra s’ouvrir ici après repos.`
				: "Survole ou touche une terre visible pour préparer un futur shell porté.";
		}
		if (metaNode instanceof HTMLElement) {
			metaNode.textContent = detail
				? `${detail.landSlug || "terre"} · ${detail.route || "/land"} · ${detail.manifestRoute || "/n0de"}`
				: "manifest n0de · route · état public";
		}
		if (routeNode instanceof HTMLAnchorElement) {
			routeNode.href = detail?.route || withSurfaceContext("/str3m");
			routeNode.textContent = detail?.route ? "Ouvrir la route" : "Rester dans le courant";
		}
		if (manifestNode instanceof HTMLAnchorElement) {
			manifestNode.href = detail?.manifestRoute || withSurfaceContext("/n0de");
		}
	};

	const setResting = () => {
		clearTimers();
		if (!(modeNode instanceof HTMLElement)) {
			return;
		}
		modeNode.textContent = "après repos";
		setBirdMood("rest");
		restTimer = window.setTimeout(() => {
			dock.classList.remove("is-visible", "is-intent");
			dock.dataset.shellGhostState = "rest";
			setBirdMood("sleep");
			if (currentDetail && labelNode instanceof HTMLElement) {
				labelNode.textContent = currentDetail.landLabel || currentDetail.landSlug || "aucune terre armée";
			}
			if (stateNode instanceof HTMLElement) {
				stateNode.textContent = "Le shell fantôme reste là, mais se rend doucement tant qu’aucune nouvelle terre n’est appelée.";
			}
		}, 2200);
	};

	window.addEventListener("o:str3m-shell-preview", (event) => {
		clearTimers();
		const detail = event.detail || {};
		setBirdMood("preview");
		previewTimer = window.setTimeout(() => {
			dock.classList.remove("is-intent");
			applyDetail(detail, "après repos");
		}, 420);
	});

	window.addEventListener("o:str3m-shell-intent", (event) => {
		clearTimers();
		dock.classList.add("is-intent", "is-visible");
		setBirdMood("intent");
		applyDetail(event.detail || {}, "armé");
	});

	window.addEventListener("o:str3m-shell-rest", () => {
		setResting();
	});

	setBirdMood("sleep");
	document.body.classList.add("has-str3m-shell-ghost");
}

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
	let userCustomizedSettings = Boolean(storedSettings);

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

			bass.type = "lowshelf";
			bass.frequency.value = 180;
			mid.type = "peaking";
			mid.frequency.value = 1000;
			mid.Q.value = 0.85;
			treble.type = "highshelf";
			treble.frequency.value = 3200;

			source.connect(bass);
			bass.connect(mid);
			mid.connect(treble);
			treble.connect(gain);
			gain.connect(context.destination);

			graph = { context, bass, mid, treble, gain };
			disableNativeAudioFallback();
			applyEqSettings();

			if (eqStateOutput instanceof HTMLElement) {
				eqStateOutput.textContent = "actif";
			}
			setEngineState("eq web");
			setOutputMode("intégrée");

			if (context.state === "suspended") {
				await context.resume().catch(() => {});
			}

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

	if (preservePitchInput instanceof HTMLInputElement) {
		preservePitchInput.checked = Boolean(settings.preservePitch);
	}
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
					setStatus("en lecture");
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
			const delta = Number(button.dataset.str3mPlayerRateStep || 0);
			settings.rate = clamp((Number(settings.rate) || 1) + delta, 0.5, 2);
			syncRate();
			saveSettings();
			setStatus(`vitesse ${audio.playbackRate.toFixed(2)}×`);
		});
	});

	if (preservePitchInput instanceof HTMLInputElement) {
		preservePitchInput.addEventListener("change", () => {
			userCustomizedSettings = true;
			settings.preservePitch = preservePitchInput.checked;
			setPreservePitch(settings.preservePitch);
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
		setStatus("en lecture");
	});
	audio.addEventListener("pause", () => {
		syncToggleLabel();
		if (audio.ended) {
			setStatus("terminé");
			return;
		}
		setStatus("pause");
	});
	audio.addEventListener("ended", () => {
		syncToggleLabel();
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
			settings.rate = clamp((Number(settings.rate) || 1) - 0.25, 0.5, 2);
			syncRate();
			saveSettings();
			return;
		}

		if (event.key === "+" || event.key === "=") {
			event.preventDefault();
			userCustomizedSettings = true;
			settings.rate = clamp((Number(settings.rate) || 1) + 0.25, 0.5, 2);
			syncRate();
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
		pitch: 0.46,
		roll: 0.1,
		panX: 0,
		panY: 0,
		zoom: 10.35,
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
		state.pitch = 0.38;
		state.roll = 0.04;
		state.zoom = 9.8;
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
				density: 0.76 + jitter * 0.3,
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
		const guidePath = withSurfaceContext("/0wlslw0");
		window.setTimeout(() => {
			canvas.classList.remove("is-secret-open");
			if (`${window.location.pathname}${window.location.search}` === guidePath) {
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
	const membrane = readCameraReactiveState();
		const isXyzScene = document.body.classList.contains("xyz-surface-view");
		const mobileLayoutBias = isXyzScene && width < 720
			? clamp((720 - width) / 360, 0, 1)
			: 0;
		stepNavigation();

		const centerX = width * (0.5 + mobileLayoutBias * 0.16) + state.panX * (width * 0.018) + membrane.tiltX * width * 0.042;
		const centerY = height * (0.5 + mobileLayoutBias * 0.045) + state.autoLiftY + state.panY * (height * 0.018) + membrane.tiltY * height * 0.038;
		const camera = 39 - state.zoom * 1.12 - membrane.presence * 1.8;
		const scale = Math.min(width, height) * (0.09 + state.zoom * 0.01) * state.autoScale * (1 - mobileLayoutBias * 0.18);
		const spinY = state.yaw + time * 0.00006 + membrane.tiltX * 0.24;
		const spinX = state.pitch + Math.sin(time * 0.00012) * 0.05 + membrane.tiltY * 0.28;
		const spinZ = state.roll + Math.cos(time * 0.00009) * 0.03 + (membrane.audioLevel - 0.08) * 0.16;

		context.clearRect(0, 0, width, height);
		const haloColor = Array.isArray(profile.haloColor) && profile.haloColor.length >= 3 ? profile.haloColor : profile.glow;
		const haloRadius = Math.min(width, height) * (0.16 + profile.haloStrength * 0.24 + membrane.presence * 0.08);
		const haloGradient = context.createRadialGradient(centerX, centerY, 0, centerX, centerY, haloRadius);
		haloGradient.addColorStop(0, `rgba(${haloColor[0]}, ${haloColor[1]}, ${haloColor[2]}, ${clamp(0.08 + profile.haloStrength * 0.08 + membrane.audioLevel * 0.05, 0.06, 0.22)})`);
		haloGradient.addColorStop(0.46, `rgba(${profile.glow[0]}, ${profile.glow[1]}, ${profile.glow[2]}, ${clamp(0.04 + profile.haloStrength * 0.06, 0.03, 0.12)})`);
		haloGradient.addColorStop(1, `rgba(${profile.glow[0]}, ${profile.glow[1]}, ${profile.glow[2]}, 0)`);
		context.fillStyle = haloGradient;
		context.fillRect(0, 0, width, height);

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
				alpha: clamp(0.055 + depthFactor * (0.6 + profile.pulseStrength * 0.14) * point.density + shimmer * 0.04, 0.035, 0.84),
				radius: clamp(0.18 + depthFactor * (3.58 + profile.pulseStrength * 0.82) * point.density, 0.16, 5.1),
				depth,
				mix: clamp(0.2 + depthFactor * 0.56 + shimmer * 0.1, 0, 1),
				signalSeed: point.phase + point.theta + point.phi,
			};
		});

		rendered.sort((left, right) => right.depth - left.depth);

		rendered.forEach((point) => {
			let color = mixRgb(profile.primary, profile.secondary, point.mix);
			color = mixRgb(color, haloColor, profile.streakMix * clamp(point.mix * 0.36 + membrane.presence * 0.18, 0, 0.48));
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
	const landRouteBase = previewShell.dataset.landRouteBase || withSurfaceContext("/land");

	if (slugOutput) {
		slugOutput.textContent = slug;
	}

	if (emailOutput) {
		emailOutput.textContent = `${slug}@o.local`;
	}

	if (linkOutput) {
		linkOutput.textContent = `${originBase}${landRouteBase}?u=${slug}`;
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

if ("serviceWorker" in navigator && window.__O_DISABLE_SW__ !== true) {
	window.addEventListener("load", () => {
		navigator.serviceWorker.register(withBridgePrefix("/site-sw.js")).catch(() => {
			// Fail silently: the site still works as a regular document.
		});
	});
}

const GUIDE_VOICE_SESSION_KEY = "o-guide-voice-session-v1";
const GUIDE_VOICE_HISTORY_LIMIT = 5;

function initMappingGenie() {
	const root = document.querySelector("[data-mapping-genie]");
	if (!(root instanceof HTMLElement)) {
		return;
	}

	const headsetMode = prefersSpatialHeadsetMode();
	const cards = Array.from(root.querySelectorAll("[data-mapping-card]"));
	const activeLabel = root.querySelector("[data-mapping-active-label]");
	const activeWhisper = root.querySelector("[data-mapping-active-whisper]");
	const activeSummary = root.querySelector("[data-mapping-active-summary]");
	const raNote = root.querySelector("[data-mapping-ra-note]");
	if (!cards.length) {
		return;
	}

	let cycleTimer = 0;
	let cycleIndex = 0;
	let dwellTimer = 0;
	let manualOverrideUntil = 0;

	root.dataset.mappingNavMode = headsetMode ? "headset" : (coarsePointer ? "touch" : "hover");

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

	const clearDwell = () => {
		if (!dwellTimer) {
			return;
		}
		window.clearTimeout(dwellTimer);
		dwellTimer = 0;
	};

	const stopCycle = () => {
		if (!cycleTimer) {
			return;
		}
		window.clearInterval(cycleTimer);
		cycleTimer = 0;
	};

	const startCycle = () => {
		if (coarsePointer || headsetMode || cards.length < 2 || cycleTimer || root.dataset.mappingAutoSource === "ra") {
			return;
		}

		cycleTimer = window.setInterval(() => {
			cycleIndex = (cycleIndex + 1) % cards.length;
			setActiveCard(cards[cycleIndex]);
		}, 4200);
	};

		const setActiveCard = (nextCard, { collapseOnSecondTap = false, source = "manual" } = {}) => {
			if (source === "manual") {
				manualOverrideUntil = Date.now() + (headsetMode ? 4200 : 7600);
			}
			let shouldCollapse = collapseOnSecondTap && !headsetMode;
			let chosenCard = nextCard;
			cards.forEach((card) => {
				const isActive = card === nextCard;
				if (shouldCollapse && isActive && card.classList.contains("is-active")) {
					card.classList.remove("is-active");
					card.setAttribute("aria-expanded", "false");
					card.setAttribute("aria-pressed", "false");
					chosenCard = null;
					return;
				}

				card.classList.toggle("is-active", isActive);
				card.setAttribute("aria-expanded", isActive ? "true" : "false");
				card.setAttribute("aria-pressed", isActive ? "true" : "false");
			});

			if (!chosenCard) {
				if (headsetMode && cards[0]) {
					chosenCard = cards[0];
				shouldCollapse = false;
					cards.forEach((card) => {
						const isActive = card === chosenCard;
						card.classList.toggle("is-active", isActive);
						card.setAttribute("aria-expanded", isActive ? "true" : "false");
						card.setAttribute("aria-pressed", isActive ? "true" : "false");
					});
				} else {
					root.dataset.mappingTheme = "real";
					return;
				}
			}

		cycleIndex = Math.max(0, cards.indexOf(chosenCard));
		updateChorus(chosenCard);
	};

	const findCardByTone = (tone) => cards.find((card) => card.dataset.mappingTone === tone) || null;

	window.addEventListener("o:ra-modulation", (event) => {
		const detail = event instanceof CustomEvent ? event.detail : null;
		if (!detail || typeof detail !== "object") {
			return;
		}

		root.dataset.raMode = detail.mode || "";
		root.dataset.raDominant = detail.dominant || "";
		const primaryLabel = detail?.primary?.label || "";
		if (raNote instanceof HTMLElement) {
			if (detail.live || detail.demo) {
				raNote.textContent = `${detail.dominantLabel || "La couche"} mène en régime ${detail.modeLabel || detail.mode || "actif"}. ${primaryLabel ? `${primaryLabel} prolonge la lecture.` : "La cartographie suit cette couche pour garder la prise."}`;
			} else {
				raNote.textContent = "Quand la membrane s ouvre, la couche dominante peut reprendre la main ici pour garder la lecture située.";
			}
		}

		if (detail.live || detail.demo) {
			root.dataset.mappingAutoSource = "ra";
			stopCycle();
			if (headsetMode || Date.now() >= manualOverrideUntil) {
				const targetCard = findCardByTone(detail.dominant || "");
				if (targetCard) {
					setActiveCard(targetCard, { source: "auto" });
				}
			}
			return;
		}

		root.dataset.mappingAutoSource = "";
		startCycle();
	});

	const scheduleActivation = (card, source = "hover") => {
		if (!(card instanceof HTMLElement)) {
			return;
		}

		clearDwell();
		if (!headsetMode || source === "click") {
			setActiveCard(card, { collapseOnSecondTap: coarsePointer && !headsetMode });
			return;
		}

		dwellTimer = window.setTimeout(() => {
			setActiveCard(card);
		}, source === "focus" ? 120 : 260);
	};

	const activeCard = cards.find((card) => card.classList.contains("is-active")) || cards[0] || null;
	if (activeCard) {
		setActiveCard(activeCard);
	}

	cards.forEach((card) => {
		card.addEventListener("pointerenter", () => {
			if (coarsePointer && !headsetMode) {
				return;
			}
			stopCycle();
			root.dataset.mappingAutoSource = "";
			scheduleActivation(card);
		});

		card.addEventListener("focus", () => {
			stopCycle();
			root.dataset.mappingAutoSource = "";
			scheduleActivation(card, "focus");
		});

		card.addEventListener("click", () => {
			stopCycle();
			clearDwell();
			root.dataset.mappingAutoSource = "";
			setActiveCard(card, { collapseOnSecondTap: coarsePointer && !headsetMode });
			card.focus({ preventScroll: true });
		});

		card.addEventListener("pointerleave", clearDwell);
		card.addEventListener("blur", clearDwell);
	});

	root.addEventListener("pointerleave", () => {
		clearDwell();
		startCycle();
	});

	root.addEventListener("keydown", (event) => {
		const activeIndex = Math.max(0, cards.findIndex((card) => card.classList.contains("is-active")));

		if (event.key === "Escape") {
			clearDwell();
			root.dataset.mappingAutoSource = "";
			if (cards[0]) {
				setActiveCard(cards[0]);
				cards[0].focus({ preventScroll: true });
			}
			return;
		}

		if (!headsetMode) {
			return;
		}

		if (["ArrowRight", "ArrowDown"].includes(event.key)) {
			event.preventDefault();
			root.dataset.mappingAutoSource = "";
			const nextIndex = (activeIndex + 1) % cards.length;
			setActiveCard(cards[nextIndex]);
			cards[nextIndex].focus({ preventScroll: true });
			return;
		}

		if (["ArrowLeft", "ArrowUp"].includes(event.key)) {
			event.preventDefault();
			root.dataset.mappingAutoSource = "";
			const nextIndex = (activeIndex - 1 + cards.length) % cards.length;
			setActiveCard(cards[nextIndex]);
			cards[nextIndex].focus({ preventScroll: true });
			return;
		}

		if (event.key === "Home") {
			event.preventDefault();
			root.dataset.mappingAutoSource = "";
			setActiveCard(cards[0]);
			cards[0].focus({ preventScroll: true });
			return;
		}

		if (event.key === "End") {
			event.preventDefault();
			root.dataset.mappingAutoSource = "";
			const lastCard = cards[cards.length - 1];
			setActiveCard(lastCard);
			lastCard.focus({ preventScroll: true });
			return;
		}

		if (event.key === " " || event.key === "Enter") {
			event.preventDefault();
			if (cards[activeIndex]) {
				root.dataset.mappingAutoSource = "";
				setActiveCard(cards[activeIndex]);
			}
		}
	});

		document.addEventListener("pointerdown", (event) => {
			if (!coarsePointer || headsetMode) {
				return;
			}

		if (!(event.target instanceof Element) || event.target.closest("[data-mapping-genie]")) {
			return;
		}

			cards.forEach((card) => {
				card.classList.remove("is-active");
				card.setAttribute("aria-expanded", "false");
				card.setAttribute("aria-pressed", "false");
			});
			if (cards[0]) {
				cards[0].classList.add("is-active");
				cards[0].setAttribute("aria-expanded", "true");
				cards[0].setAttribute("aria-pressed", "true");
				updateChorus(cards[0]);
			}
		}, true);

	startCycle();
}

function initDeviceBridgePanels() {
	const roots = Array.from(document.querySelectorAll("[data-device-bridge-root]"));
	if (!roots.length) {
		return;
	}

	const panels = roots
		.map((root) => {
			if (!(root instanceof HTMLElement)) {
				return null;
			}

			return {
				root,
				context: root.dataset.deviceContext || "surface",
				silenceStatus: root.querySelector("[data-device-silence-status]"),
				volumeStatus: root.querySelector("[data-device-volume-status]"),
				hapticsStatus: root.querySelector("[data-device-haptics-status]"),
				visibilityStatus: root.querySelector("[data-device-visibility-status]"),
				standaloneStatus: root.querySelector("[data-device-standalone-status]"),
				nativeStatus: root.querySelector("[data-device-native-status]"),
				silenceToggle: root.querySelector("[data-device-silence-toggle]"),
				volumeInput: root.querySelector("[data-device-volume-input]"),
				volumeReadout: root.querySelector("[data-device-volume-readout]"),
				installButton: root.querySelector("[data-device-install]"),
				shareButton: root.querySelector("[data-device-share]"),
				nativeNote: root.querySelector("[data-device-native-note]"),
			};
		})
		.filter(Boolean);

	if (!panels.length) {
		return;
	}

	const setNodeText = (node, text) => {
		if (node instanceof HTMLElement) {
			node.textContent = text;
		}
	};

	const renderPanels = (state = getCurrentDeviceBridgeState()) => {
		panels.forEach((panel) => {
			if (!panel) {
				return;
			}

			const spatialHeadsetMode = prefersSpatialHeadsetMode() && panel.context === "xyz";
			const silenceLabel = state.nativeSilenceMode === "silent"
				? "silence natif"
				: (state.silenceIntent ? "silence web" : (state.nativeSilenceMode === "vibrate" ? "vibreur natif" : "web sonore"));
			const webVolumeLabel = `${Math.round(state.webVolume * 100)}%`;
			const nativeVolumeLabel = state.nativeVolume !== null
				? `${Math.round(state.nativeVolume * 100)}%`
				: null;
			const volumeLabel = state.nativeAudioTrusted && nativeVolumeLabel
				? `${webVolumeLabel} web · natif ${nativeVolumeLabel}`
				: webVolumeLabel;
			const hapticsLabel = state.hapticsAvailable ? "prête" : "absente";
			const visibilityLabel = state.visibility === "hidden" ? "arrière-plan" : "visible";
			const standaloneLabel = state.standalone ? "installée" : (state.installAvailable ? "installable" : "navigateur");
			const nativeLabel = state.nativeAudioTrusted || state.nativeVolume !== null
				? `${state.nativeSource}${state.nativeRoute ? ` · ${state.nativeRoute}` : ""}`
				: (spatialHeadsetMode ? "preview web" : "web seul");
			const note = state.nativeAudioTrusted
				? `Pont natif reçu: ${state.nativeSource}${state.nativeRoute ? ` · ${state.nativeRoute}` : ""}. Le thérémin local garde le niveau O. (${webVolumeLabel}) et le téléphone signale en plus un volume système à ${nativeVolumeLabel || "?"}.`
				: (state.nativeVolume !== null
					? "Le navigateur a reçu une valeur de volume isolée, mais elle n'est pas assez fiable pour couper le thérémin local. Le niveau O. garde donc la main."
					: (spatialHeadsetMode
						? (state.standalone
							? "Cette surface tourne comme une app installée. Le web garde ici partage, lecture et niveau O., puis attend un client spatial natif pour l ancrage, le silence système et le vrai passthrough."
							: "Le web pilote ici partage, lecture et niveau O. Le vrai silence système, l ancrage spatial et le passthrough viendront avec le client natif visionOS ou Quest.")
						: (state.standalone
						? "Cette surface tourne comme une app installée. Le web garde ici veille, haptique, partage et niveau O., puis attend un pont natif pour le vrai silence système."
						: "Le web pilote ici silence, niveau, haptique, partage et mode app. Un wrapper natif pourra ensuite donner le silence et le volume réels du téléphone.")));

			setNodeText(panel.silenceStatus, silenceLabel);
			setNodeText(panel.volumeStatus, volumeLabel);
			setNodeText(panel.hapticsStatus, hapticsLabel);
			setNodeText(panel.visibilityStatus, visibilityLabel);
			setNodeText(panel.standaloneStatus, standaloneLabel);
			setNodeText(panel.nativeStatus, nativeLabel);
			setNodeText(panel.volumeReadout, webVolumeLabel);
			setNodeText(panel.nativeNote, note);

			if (panel.volumeInput instanceof HTMLInputElement) {
				panel.volumeInput.value = String(Math.round(state.volumeIntent * 100));
			}

			if (panel.installButton instanceof HTMLElement) {
				panel.installButton.hidden = !state.installAvailable || state.standalone;
			}

			if (panel.shareButton instanceof HTMLElement) {
				panel.shareButton.hidden = !state.shareAvailable;
			}

			if (panel.silenceToggle instanceof HTMLElement) {
				panel.silenceToggle.textContent = state.silenceIntent ? "Réactiver le son web" : "Silence web";
			}
		});
	};

	panels.forEach((panel) => {
		if (!panel) {
			return;
		}

		if (panel.silenceToggle instanceof HTMLElement) {
			panel.silenceToggle.addEventListener("click", () => {
				const nextSilent = !readDeviceSilenceIntent();
				writeDeviceSilenceIntent(nextSilent);
				pulseDeviceHaptics(nextSilent ? "soft" : "medium");
			});
		}

		if (panel.volumeInput instanceof HTMLInputElement) {
			panel.volumeInput.addEventListener("input", () => {
				writeDeviceVolumeLevel((Number(panel.volumeInput.value) || 0) / 100);
			});
		}

		if (panel.installButton instanceof HTMLElement) {
			panel.installButton.addEventListener("click", () => {
				void promptDeviceInstall();
			});
		}

		if (panel.shareButton instanceof HTMLElement) {
			panel.shareButton.addEventListener("click", () => {
				const text = panel.context === "lab"
					? "Le lab du tore écoute ce téléphone et rejoue ses capteurs."
					: "La membrane du tore lit ce téléphone en direct.";
				void shareCurrentDeviceSurface({ text });
			});
		}
	});

	renderPanels();
	window.addEventListener("o:device-bridge-change", (event) => {
		renderPanels(event?.detail || getCurrentDeviceBridgeState());
	});
}

function initXyzSurface() {
	const root = document.querySelector("[data-xyz-surface]");
	if (!(root instanceof HTMLElement)) {
		return;
	}

	const headsetMode = prefersSpatialHeadsetMode();
	root.dataset.xyzInteractionMode = headsetMode ? "headset" : (coarsePointer ? "touch" : "pointer");

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

	if (!reducedMotion && !coarsePointer && !headsetMode) {
		window.addEventListener("pointermove", (event) => {
			if ((event.pointerType || "") === "touch") {
				return;
			}
			applyDrift(event.clientX, event.clientY);
		});

		window.addEventListener("pointerleave", resetDrift);
	}

	if (reducedMotion || coarsePointer || headsetMode) {
		resetDrift();
	}
}

function initXyzCamera() {
	const root = document.querySelector("[data-xyz-camera-root]");
	const video = document.querySelector("[data-xyz-camera-video]");
	const startButton = document.querySelector("[data-xyz-camera-start]");
	const demoButton = document.querySelector("[data-xyz-camera-demo]");
	const stopButton = document.querySelector("[data-xyz-camera-stop]");
	const statusNode = document.querySelector("[data-xyz-camera-status]");
	const titleNode = document.querySelector("[data-xyz-camera-title]");
	const orientationNode = document.querySelector("[data-xyz-sensor-orientation]");
	const motionNode = document.querySelector("[data-xyz-sensor-motion]");
	const lightNode = document.querySelector("[data-xyz-sensor-light]");
	const audioNode = document.querySelector("[data-xyz-sensor-audio]");
	const cameraNode = document.querySelector("[data-xyz-sensor-camera]");
	const wakeNode = document.querySelector("[data-xyz-sensor-wake]");
	const voiceEchoInput = document.querySelector("[data-xyz-voice-echo-input]");
	const voiceEchoReadout = document.querySelector("[data-xyz-voice-echo-readout]");
	const musicModeNode = document.querySelector("[data-xyz-music-mode]");
	const musicNoteNode = document.querySelector("[data-xyz-music-note]");
	const musicRhythmNode = document.querySelector("[data-xyz-music-rhythm]");
	const handTerreStateNode = document.querySelector("[data-xyz-hand-terre-state]");
	const handMineStateNode = document.querySelector("[data-xyz-hand-mine-state]");
	const handTerreTitleNode = document.querySelector("[data-xyz-hand-terre-title]");
	const handMineTitleNode = document.querySelector("[data-xyz-hand-mine-title]");
	const handTerreCopyNode = document.querySelector("[data-xyz-hand-terre-copy]");
	const handMineCopyNode = document.querySelector("[data-xyz-hand-mine-copy]");
	const duetStateNode = document.querySelector("[data-xyz-duet-state]");
	const musicGuideNode = document.querySelector("[data-xyz-music-guide]");
	const instrumentRoot = document.querySelector("[data-xyz-instrument-root]");
	const instrumentViewNode = document.querySelector("[data-xyz-instrument-view]");
	const instrumentFocusNode = document.querySelector("[data-xyz-instrument-focus]");
	const instrumentBodyNode = document.querySelector("[data-xyz-instrument-body]");
	const instrumentTouchNode = document.querySelector("[data-xyz-instrument-touch]");
	const instrumentLightNode = document.querySelector("[data-xyz-instrument-light]");
	const instrumentStage = document.querySelector("[data-xyz-instrument-stage]");
	const instrumentStageCopyNode = document.querySelector("[data-xyz-instrument-stage-copy]");
	const worldCopyNode = document.querySelector("[data-xyz-world-copy]");
	const cameraFacingButtons = Array.from(document.querySelectorAll("[data-xyz-camera-facing-button]"));
	const arModulationRoot = document.querySelector("[data-xyz-ar-modulation]");
	const arTitleNode = document.querySelector("[data-xyz-ar-title]");
	const arStatusNode = document.querySelector("[data-xyz-ar-status]");
	const arDirectiveNode = document.querySelector("[data-xyz-ar-directive]");
	const arPilotTitleNode = document.querySelector("[data-xyz-ar-pilot-title]");
	const arPilotCopyNode = document.querySelector("[data-xyz-ar-pilot-copy]");
	const arPrimaryLinkNode = document.querySelector("[data-xyz-ar-primary-link]");
	const arSecondaryLinkNode = document.querySelector("[data-xyz-ar-secondary-link]");
	const arUsageNode = document.querySelector("[data-xyz-ar-usage]");
	const arModeButtons = Array.from(document.querySelectorAll("[data-xyz-ar-mode-button]"));
	const arLayerNodes = {
		real: {
			card: document.querySelector('[data-xyz-ar-layer="real"]'),
			value: document.querySelector("[data-xyz-ar-real-value]"),
			meter: document.querySelector("[data-xyz-ar-real-meter]"),
			copy: document.querySelector("[data-xyz-ar-real-copy]"),
		},
		plasma: {
			card: document.querySelector('[data-xyz-ar-layer="plasma"]'),
			value: document.querySelector("[data-xyz-ar-plasma-value]"),
			meter: document.querySelector("[data-xyz-ar-plasma-meter]"),
			copy: document.querySelector("[data-xyz-ar-plasma-copy]"),
		},
		torus: {
			card: document.querySelector('[data-xyz-ar-layer="torus"]'),
			value: document.querySelector("[data-xyz-ar-torus-value]"),
			meter: document.querySelector("[data-xyz-ar-torus-meter]"),
			copy: document.querySelector("[data-xyz-ar-torus-copy]"),
		},
	};
	const mappingPanel = document.querySelector("[data-mapping-genie]");

	if (
		!(root instanceof HTMLElement)
		|| !(video instanceof HTMLVideoElement)
		|| !(startButton instanceof HTMLElement)
		|| !(stopButton instanceof HTMLElement)
		|| !(demoButton instanceof HTMLElement)
	) {
		return;
	}

	const plasmaBridgeUrl = root.dataset.xyzPlasmaBridge || "";
	const membraneLandSlug = root.dataset.xyzPlasmaLand || "";
	const isAndroidSurface = /\bAndroid\b/i.test(window.navigator?.userAgent || "");
	const isSpatialHeadsetSurface = prefersSpatialHeadsetMode();
	const arModeKeys = ["anchor", "translate", "loop", "weave"];
	const arModeStorageKey = document.body.classList.contains("io-surface-view")
		? "o-io-ra-mode-v1"
		: "o-xyz-ra-mode-v1";
	const readStoredArMode = () => {
		try {
			const value = window.sessionStorage.getItem(arModeStorageKey) || "";
			return arModeKeys.includes(value) ? value : "";
		} catch {
			return "";
		}
	};
	const writeStoredArMode = (value) => {
		try {
			if (!value) {
				window.sessionStorage.removeItem(arModeStorageKey);
				return;
			}
			window.sessionStorage.setItem(arModeStorageKey, value);
		} catch {
			// Ignore storage failures.
		}
	};
	const cameraFacingStorageKey = document.body.classList.contains("io-surface-view")
		? "o-io-camera-facing-v1"
		: "o-xyz-camera-facing-v1";
	const readStoredCameraFacing = () => {
		try {
			const value = window.sessionStorage.getItem(cameraFacingStorageKey) || "";
			return value === "environment" || value === "user" ? value : "";
		} catch {
			return "";
		}
	};
	const writeStoredCameraFacing = (value) => {
		try {
			if (!value) {
				window.sessionStorage.removeItem(cameraFacingStorageKey);
				return;
			}
			window.sessionStorage.setItem(cameraFacingStorageKey, value);
		} catch {
			// Ignore storage failures.
		}
	};

	let stream = null;
	let audioStream = null;
	let analysisFrame = 0;
	let audioFrame = 0;
	let demoFrame = 0;
	let demoStartedAt = 0;
	let demoPhaseIndex = -1;
	let analysisLastAt = 0;
	let previousSamples = null;
	let audioContext = null;
	let audioSource = null;
	let audioAnalyser = null;
	let motionVoiceOscillator = null;
	let motionVoiceHarmonicOscillator = null;
	let motionVoiceSubOscillator = null;
	let motionVoiceGain = null;
	let motionVoiceHarmonicGain = null;
	let motionVoiceSubGain = null;
	let motionVoiceFilter = null;
	let motionVoicePanner = null;
	let motionVoiceLfo = null;
	let motionVoiceLfoGain = null;
	let vocoderInputGain = null;
	let vocoderHighpass = null;
	let vocoderCompressor = null;
	let vocoderBandpass = null;
	let vocoderDirectGain = null;
	let vocoderColorFilter = null;
	let vocoderCarrierOscillator = null;
	let vocoderCarrierHarmonicOscillator = null;
	let vocoderCarrierGain = null;
	let vocoderCarrierHarmonicGain = null;
	let vocoderModDepth = null;
	let vocoderHarmonicModDepth = null;
	let vocoderWetGain = null;
	let vocoderEchoSend = null;
	let vocoderEchoDelay = null;
	let vocoderEchoFeedback = null;
	let vocoderEchoReturn = null;
	let vocoderTuneFilter = null;
	let vocoderTuneHarmonicFilter = null;
	let vocoderTuneGain = null;
	let vocoderTuneHarmonicGain = null;
	let wakeLock = null;
	let lightSensor = null;
	let orientationBound = false;
	let motionBound = false;
	let bridgePulseTimer = 0;
	let bridgeInFlight = false;
	let bridgeLastSignature = "";
	let bridgeLastAt = 0;
	let sensorFeedbackTimer = 0;
	let shakerNoiseBuffer = null;
	let lastShakeAt = 0;
	let lastMotionMagnitude = 0;
	let lastDemoShakeBurst = 0;
	let currentToreMode = "minor";
	let lastQuantizedMidi = 40;
	let cameraFacingMode = readStoredCameraFacing() || (coarsePointer ? "environment" : "user");
	const initialArMode = readStoredArMode() || (arModulationRoot instanceof HTMLElement
		? (arModulationRoot.dataset.xyzArMode || (isSpatialHeadsetSurface ? "anchor" : "weave"))
		: "weave");
	let arModulationMode = arModeKeys.includes(initialArMode) ? initialArMode : "weave";
	let orientationSignalSeen = false;
	let motionSignalSeen = false;
	const voiceFx = {
			echoAmount: readXyzVoiceEchoLevel(),
		};
	const analysisCanvas = document.createElement("canvas");
	analysisCanvas.width = 48;
	analysisCanvas.height = 36;
	const analysisContext = analysisCanvas.getContext("2d", { willReadFrequently: true });
	const membrane = {
			luma: 0,
			cameraMotion: 0,
			audioLevel: 0,
			lightLevel: 0,
			tiltX: 0,
			tiltY: 0,
			motionSensor: 0,
			shake: 0,
		};
	const instrument = {
		pointers: new Map(),
		terreX: 0.3,
		terreY: 0.62,
		terreEnergy: 0,
		mineX: 0.72,
		mineY: 0.38,
		mineEnergy: 0,
		activeHands: 0,
		touchEnergy: 0,
		lastImpulseAt: 0,
		keyboardTimer: 0,
	};

	const cameraFacingLabel = () => cameraFacingMode === "environment" ? "paysage" : "visage";
	const instrumentTouchEnergy = () => clampNumber(
		Math.max(instrument.terreEnergy, instrument.mineEnergy) * 0.68
		+ Math.min(instrument.terreEnergy, instrument.mineEnergy) * 0.32,
		0,
		1
	);
	const instrumentBodyEnergy = () => clampNumber(
		instrumentTouchEnergy() * 0.54
		+ Math.abs(instrument.terreY - instrument.mineY) * 0.34
		+ Math.abs(instrument.terreX - instrument.mineX) * 0.12
		+ Math.abs(membrane.tiltX) * 0.18
		+ membrane.motionSensor * 0.2,
		0,
		1
	);
	const instrumentSceneEnergy = () => clampNumber(
		cameraFacingMode === "environment"
			? membrane.cameraMotion * 0.44 + membrane.motionSensor * 0.24 + membrane.lightLevel * 0.2 + instrumentTouchEnergy() * 0.12
			: membrane.audioLevel * 0.28 + membrane.luma * 0.18 + instrumentTouchEnergy() * 0.34 + Math.abs(membrane.tiltY) * 0.14,
		0,
		1
	);
	const instrumentPitchBias = () => clampNumber(
		((instrument.mineX - 0.5) * 0.82) + ((instrument.terreX - 0.5) * 0.28),
		-0.72,
		0.72
	);
	const instrumentTextureBias = () => clampNumber(
		((0.5 - instrument.terreY) * 0.54) + ((0.5 - instrument.mineY) * 0.26),
		-0.72,
		0.72
	);

	const renderWorldInstrument = () => {
		const bodyEnergy = instrumentBodyEnergy();
		const touchEnergy = instrumentTouchEnergy();
		const sceneEnergy = instrumentSceneEnergy();
		const lightTone = clampNumber(Math.max(membrane.lightLevel, membrane.luma), 0, 1);
		const activeHands = instrument.activeHands;
		const viewLabel = cameraFacingLabel();
		let focusLabel = cameraFacingMode === "environment" ? "horizon tenu" : "souffle proche";
		if (cameraFacingMode === "environment") {
			focusLabel = membrane.cameraMotion > 0.44
				? "marche / paysage"
				: (lightTone > 0.62 ? "reflets / dehors" : "détail / terrain");
		} else if (membrane.audioLevel > 0.18) {
			focusLabel = "visage + voix";
		} else if (sceneEnergy > 0.32) {
			focusLabel = "proximité / peau";
		}

		const bodyLabel = bodyEnergy > 0.68
			? "corps en traversée"
			: (bodyEnergy > 0.34 ? "corps en torsion" : "corps tenu");
		const touchLabel = activeHands >= 2
			? "terre + mine"
			: (activeHands === 1
				? (instrument.terreEnergy >= instrument.mineEnergy ? "terre seule" : "mine seule")
				: "aucune prise");
		const lightLabel = lightTone > 0.68
			? "clair majeur"
			: (lightTone < 0.32 ? "ombre mineure" : "lueur mixte");
		const stageCopy = cameraFacingMode === "environment"
			? "Retourne la caméra et laisse le dehors jouer. Terre tient l horizon, Mine taille un détail, une route ou un reflet. Fleches ou glisse pour garder la prise."
			: "Approche visage, mains ou torse. Terre pose le fond, Mine ouvre l accent, puis la voix et la lumière prennent le relais. WASD ou glisse si tu veux jouer sans quitter l écran.";
		let worldCopy = "Le monde reste un instrument: visage, corps, lumière, paysage et toucher peuvent tous nourrir le tore.";
		if (cameraFacingMode === "environment") {
			worldCopy = touchEnergy > 0.24
				? "Le paysage répond maintenant à tes mains. Tu peux marcher, viser, pivoter et laisser les reflets, la rue ou le ciel nourrir le tore comme un instrument vivant."
				: "Passe en paysage pour faire jouer le dehors. Le monde devient matière: horizon, marche, reflets, façades, arbres, vitesse et lumière.";
		} else if (touchEnergy > 0.26 || membrane.audioLevel > 0.16) {
			worldCopy = "Le visage, le souffle et les mains sont maintenant dans la boucle. Le tore peut tenir une note, ouvrir un rythme puis colorer la lumière autour de toi.";
		}

		document.body.dataset.cameraFacing = cameraFacingMode;
		if (root instanceof HTMLElement) {
			root.dataset.cameraFacing = cameraFacingMode;
		}
		if (instrumentRoot instanceof HTMLElement) {
			instrumentRoot.dataset.cameraFacing = cameraFacingMode;
		}
		document.body.style.setProperty("--xyz-world-energy", sceneEnergy.toFixed(3));
		document.body.style.setProperty("--xyz-touch-energy", touchEnergy.toFixed(3));
		document.body.style.setProperty("--xyz-terre-x", instrument.terreX.toFixed(3));
		document.body.style.setProperty("--xyz-terre-y", instrument.terreY.toFixed(3));
		document.body.style.setProperty("--xyz-terre-energy", instrument.terreEnergy.toFixed(3));
		document.body.style.setProperty("--xyz-mine-x", instrument.mineX.toFixed(3));
		document.body.style.setProperty("--xyz-mine-y", instrument.mineY.toFixed(3));
		document.body.style.setProperty("--xyz-mine-energy", instrument.mineEnergy.toFixed(3));
		document.body.dataset.worldInstrumentFocus = cameraFacingMode === "environment" ? "landscape" : "face";

		setSensorText(instrumentViewNode, viewLabel);
		setSensorText(instrumentFocusNode, focusLabel);
		setSensorText(instrumentBodyNode, bodyLabel);
		setSensorText(instrumentTouchNode, touchLabel);
		setSensorText(instrumentLightNode, lightLabel);
		setSensorText(instrumentStageCopyNode, stageCopy);
		setSensorText(worldCopyNode, worldCopy);
		const worldState = {
			surface: isIoSurfaceView() ? "io" : "xyz",
			cameraFacing: cameraFacingMode,
			viewLabel,
			focusLabel,
			bodyLabel,
			touchLabel,
			lightLabel,
			worldCopy,
			stageCopy,
			sceneEnergy,
			touchEnergy,
			activeHands,
		};
		writeWorldInstrumentSession(worldState);
		window.dispatchEvent(new CustomEvent("o:world-instrument", { detail: worldState }));

		cameraFacingButtons.forEach((button) => {
			if (button instanceof HTMLElement) {
				button.setAttribute("aria-pressed", button.dataset.xyzCameraFacingButton === cameraFacingMode ? "true" : "false");
			}
		});
	};

	const updateInstrumentFromPointers = () => {
		const values = Array.from(instrument.pointers.values());
		const terreCandidates = values.filter((entry) => entry.hand === "terre");
		const mineCandidates = values.filter((entry) => entry.hand === "mine");
		const pickCandidate = (entries, fallbackX, fallbackY) => {
			if (!entries.length) {
				return { x: fallbackX, y: fallbackY, energy: 0 };
			}
			const total = entries.reduce((sum, entry) => sum + (entry.energy || 1), 0) || 1;
			return {
				x: clampNumber(entries.reduce((sum, entry) => sum + (entry.x * (entry.energy || 1)), 0) / total, 0.06, 0.94),
				y: clampNumber(entries.reduce((sum, entry) => sum + (entry.y * (entry.energy || 1)), 0) / total, 0.08, 0.92),
				energy: clampNumber(entries.reduce((sum, entry) => sum + (entry.energy || 1), 0) / entries.length, 0, 1),
			};
		};
		const terre = pickCandidate(terreCandidates, 0.3, 0.62);
		const mine = pickCandidate(mineCandidates, 0.72, 0.38);
		instrument.terreX = terre.x;
		instrument.terreY = terre.y;
		instrument.terreEnergy = terre.energy;
		instrument.mineX = mine.x;
		instrument.mineY = mine.y;
		instrument.mineEnergy = mine.energy;
		instrument.activeHands = (terre.energy > 0 ? 1 : 0) + (mine.energy > 0 ? 1 : 0);
		instrument.touchEnergy = instrumentTouchEnergy();
		renderWorldInstrument();
		syncMembraneReactiveState();
	};

	const registerInstrumentPointer = (event) => {
		if (!(instrumentStage instanceof HTMLElement)) {
			return;
		}

		const rect = instrumentStage.getBoundingClientRect();
		if (rect.width <= 0 || rect.height <= 0) {
			return;
		}

		const x = clampNumber((event.clientX - rect.left) / rect.width, 0, 1);
		const y = clampNumber((event.clientY - rect.top) / rect.height, 0, 1);
		const hand = x < 0.5 ? "terre" : "mine";
		instrument.pointers.set(event.pointerId, { x, y, hand, energy: 1 });
		if (hand === "mine") {
			const now = performance.now();
			if (now - instrument.lastImpulseAt > 120) {
				instrument.lastImpulseAt = now;
				void triggerShaker(0.34 + x * 0.18 + (1 - y) * 0.14);
			}
		}
		updateInstrumentFromPointers();
	};

	const releaseInstrumentPointer = (pointerId) => {
		if (!instrument.pointers.has(pointerId)) {
			return;
		}
		instrument.pointers.delete(pointerId);
		updateInstrumentFromPointers();
	};

	const pulseInstrumentKeyboard = (hand, deltaX = 0, deltaY = 0) => {
		const prefix = hand === "terre" ? "terre" : "mine";
		const xKey = `${prefix}X`;
		const yKey = `${prefix}Y`;
		const energyKey = `${prefix}Energy`;
		instrument[xKey] = clampNumber((instrument[xKey] || (hand === "terre" ? 0.3 : 0.72)) + deltaX, 0.08, 0.92);
		instrument[yKey] = clampNumber((instrument[yKey] || (hand === "terre" ? 0.62 : 0.38)) + deltaY, 0.08, 0.92);
		instrument[energyKey] = 1;
		instrument.activeHands = Math.max(instrument.activeHands, 1);
		if (instrument.keyboardTimer) {
			window.clearTimeout(instrument.keyboardTimer);
		}
		instrument.keyboardTimer = window.setTimeout(() => {
			instrument.terreEnergy = 0;
			instrument.mineEnergy = 0;
			instrument.activeHands = 0;
			updateInstrumentFromPointers();
		}, 640);
		renderWorldInstrument();
		syncMembraneReactiveState();
	};

	const isMembraneLive = () => document.body.classList.contains("is-membrane-live");
	const isMembraneDemo = () => document.body.classList.contains("is-membrane-demo");
	const isMembraneAudible = () => isMembraneLive() || isMembraneDemo();
	const toreRootMidi = 40;
	const toreScaleSteps = {
			major: [0, 2, 4, 5, 7, 9, 11],
			minor: [0, 2, 3, 5, 7, 8, 10],
		};
	const toreModeLabels = {
			major: "Mi majeur",
			minor: "Mi mineur",
		};
	const toreNoteNames = ["Do", "Do#", "Re", "Re#", "Mi", "Fa", "Fa#", "Sol", "Sol#", "La", "La#", "Si"];
	const arModeCatalog = {
			anchor: {
				label: "ancrer",
				title: "Le tore se pose sur le monde.",
				status: "La réalité garde le plan principal. Le plasma annote, le tore n incise qu une fois les bords stabilisés.",
				usage: "Usage RA: garder les plans, les corps et les obstacles lisibles avant d ouvrir des seuils plus denses.",
				bias: { real: 0.18, plasma: -0.03, torus: -0.08 },
			},
			translate: {
				label: "traduire",
				title: "Le plasma prend la traduction.",
				status: "Les signes, la mémoire, la météo et la voix gagnent du terrain. Le tore reste au contact sans recouvrir le monde.",
				usage: "Usage RA: laisser monter les flux, les annotations, les traces sonores et les directions avant d ouvrir la route.",
				bias: { real: -0.06, plasma: 0.22, torus: -0.04 },
			},
			loop: {
				label: "boucler",
				title: "Le tore replie le lieu en interface.",
				status: "Les seuils, les routes et la prise spatiale passent devant. La réalité reste visible, mais le tore mène la lecture.",
				usage: "Usage RA: ouvrir des routes, zones, prises et nœuds directement dans l espace perçu.",
				bias: { real: -0.12, plasma: -0.04, torus: 0.24 },
			},
			weave: {
				label: "tresser",
				title: "Les trois couches se tiennent ensemble.",
				status: "La réalité porte, le plasma relie, le tore boucle: aucun plan ne doit écraser les deux autres.",
				usage: "Usage RA: faire tenir le monde, ses flux et la surface dans une même lecture sans rupture.",
				bias: { real: 0.04, plasma: 0.05, torus: 0.05 },
			},
		};
	const demoPhases = [
			{
				until: 0.24,
				key: "velvet",
				title: "Terre tient, Mine écoute.",
				message: "Le tore commence bas, velours, presque minéral. Terre installe le fond mineur pendant que Mine attend encore son incision.",
				haptic: "soft",
			},
			{
				until: 0.52,
				key: "gloss",
				title: "Terre ouvre, Mine taille.",
				message: "La lumière synthétique ouvre le champ et pousse vers le majeur. Terre élargit la pièce, Mine affine déjà une note plus claire.",
				haptic: "soft",
			},
			{
				until: 0.8,
				key: "pulse",
				title: "Terre tient, Mine frappe.",
				message: "Inclinaison, secousse et souffle s’épaississent. Terre garde le socle pendant que Mine ouvre le shaker et impose le rythme.",
				haptic: "medium",
			},
			{
				until: 1,
				key: "sap",
				title: "Terre porte, Mine chante.",
				message: "Dernière montée: lumière, voix, shaker et mouvement se nouent. La surface devient presque animale, comme si les deux mains jouaient enfin ensemble.",
				haptic: "deep",
			},
		];

	const setSensorText = (node, text) => {
		if (node instanceof HTMLElement) {
			node.textContent = text;
		}
	};

	const stopMediaTracks = (mediaStream) => {
		if (!mediaStream || typeof mediaStream.getTracks !== "function") {
			return;
		}

		mediaStream.getTracks().forEach((track) => track.stop());
	};

	const resetSensorFeedback = () => {
		if (sensorFeedbackTimer) {
			window.clearTimeout(sensorFeedbackTimer);
			sensorFeedbackTimer = 0;
		}
		orientationSignalSeen = false;
		motionSignalSeen = false;
	};

	const queueSensorFeedback = ({ orientationReady = false, motionReady = false } = {}) => {
		resetSensorFeedback();
		if (!orientationReady && !motionReady) {
			return;
		}

		sensorFeedbackTimer = window.setTimeout(() => {
			if (!isMembraneLive()) {
				return;
			}

			if (orientationReady && !orientationSignalSeen) {
				setSensorText(orientationNode, isAndroidSurface ? "bouge ou autorise" : "en attente du geste");
			}

			if (motionReady && !motionSignalSeen) {
				setSensorText(motionNode, isAndroidSurface ? "bouge ou autorise" : "en attente du mouvement");
			}
		}, isAndroidSurface ? 1800 : 2400);
	};

	const renderVoiceEchoControls = () => {
		const percent = Math.round(clampNumber(voiceFx.echoAmount, 0, 1) * 100);
	if (voiceEchoInput instanceof HTMLInputElement) {
			voiceEchoInput.value = String(percent);
		}
		if (voiceEchoReadout instanceof HTMLElement) {
			voiceEchoReadout.textContent = `${percent}%`;
		}
	};

	renderVoiceEchoControls();
	if (voiceEchoInput instanceof HTMLInputElement && voiceEchoInput.dataset.bound !== "1") {
			voiceEchoInput.dataset.bound = "1";
			voiceEchoInput.addEventListener("input", () => {
				voiceFx.echoAmount = clampNumber((Number(voiceEchoInput.value) || 0) / 100, 0, 1);
				writeXyzVoiceEchoLevel(voiceFx.echoAmount);
				renderVoiceEchoControls();
				updateMotionVoice();
			});
		}

	const normalizeLayerWeights = (weights) => {
		const real = Math.max(0.001, Number(weights.real) || 0);
		const plasma = Math.max(0.001, Number(weights.plasma) || 0);
		const torus = Math.max(0.001, Number(weights.torus) || 0);
		const total = real + plasma + torus;
		return {
			real: real / total,
			plasma: plasma / total,
			torus: torus / total,
		};
	};

	const arDominantLayer = (weights) => {
		if (weights.real >= weights.plasma && weights.real >= weights.torus) {
			return "real";
		}
		if (weights.plasma >= weights.torus) {
			return "plasma";
		}
		return "torus";
	};

	const arLayerCopyFor = (layer, weight, { live = false, demo = false } = {}) => {
		const percent = Math.round(clampNumber(weight, 0, 1) * 100);
		switch (layer) {
			case "real":
				return percent >= 42
					? "Le monde tient devant: plans, lumière, proximité et obstacles restent le premier support de lecture."
					: (live
						? "La réalité reste présente, mais elle laisse déjà place à l inscription des flux et des prises."
						: "La réalité garde encore le cadre minimal avant la montée des autres couches.");
			case "plasma":
				return percent >= 40
					? "Le plasma devient lisible: mémoire, météo, voix, respiration et signes relient déjà le lieu au tore."
					: (demo
						? "Le plasma prépare la traduction, même si la montée reste encore rejouée localement."
						: "Le plasma reste discret: il annote sans encore prendre le dessus.");
			case "torus":
			default:
				return percent >= 40
					? "Le tore prend la main: seuils, routes, zones et points d accroche deviennent déjà des objets spatiaux."
					: (live
						? "Le tore s installe sans recouvrir tout le monde: il ouvre des prises plutôt qu une peau totale."
					: "Le tore reste en veille haute: il attend d ouvrir des prises plus nettes dans l espace.");
		}
	};

	const arLayerLabel = (layer) => {
		switch (layer) {
			case "real":
				return "Réalité";
			case "plasma":
				return "Plasma";
			case "torus":
			default:
				return "Tore";
		}
	};

	const arPilotStateFor = ({ mode, dominant, live, demo }) => {
		if (!live && !demo) {
			return {
				title: "Prise active: préparer le champ.",
				copy: "Cadre d abord le volume et les plans. Le tore n ouvre pas encore de prise forte tant que la membrane ne tient pas vraiment.",
				primary: { label: "Ouvrir Map", href: withSurfaceContext("/map") },
				secondary: { label: "Passer par 0wlslw0", href: withSurfaceContext("/0wlslw0") },
			};
		}

		if (mode === "anchor") {
			return {
				title: "Prise active: ancrer le monde.",
				copy: "Le régime d ancrage garde les bords, corps, obstacles et orientations au premier plan avant toute densification du tore.",
				primary: { label: "Ouvrir Map", href: withSurfaceContext("/map") },
				secondary: { label: "Passer par 0wlslw0", href: withSurfaceContext("/0wlslw0") },
			};
		}

		if (mode === "translate") {
			return {
				title: "Prise active: faire passer le flux.",
				copy: "Le plasma prend la main pour relier météo, mémoire, voix et traces publiques sans casser la lecture située.",
				primary: { label: "Lire Str3m", href: withSurfaceContext("/str3m") },
				secondary: { label: "Passer par 0wlslw0", href: withSurfaceContext("/0wlslw0") },
			};
		}

		if (mode === "loop") {
			return {
				title: "Prise active: ouvrir une prise située.",
				copy: "Le tore replie le lieu en interface. On peut maintenant entrer dans un fil, une zone ou une accroche sans perdre le plan.",
				primary: { label: "Ouvrir Signal", href: withSurfaceContext("/signal") },
				secondary: { label: "Relire Map", href: withSurfaceContext("/map") },
			};
		}

		if (dominant === "real") {
			return {
				title: "Prise active: tresser depuis la réalité.",
				copy: "La réalité porte encore le tressage. Garde le terrain lisible, puis laisse le plasma et le tore monter par touches.",
				primary: { label: "Ouvrir Map", href: withSurfaceContext("/map") },
				secondary: { label: "Lire Str3m", href: withSurfaceContext("/str3m") },
			};
		}

		if (dominant === "plasma") {
			return {
				title: "Prise active: tresser depuis le plasma.",
				copy: "Le flux devient la couture principale: annotations, rythmes, mémoire et voix relient maintenant le lieu au tore.",
				primary: { label: "Lire Str3m", href: withSurfaceContext("/str3m") },
				secondary: { label: "Passer par 0wlslw0", href: withSurfaceContext("/0wlslw0") },
			};
		}

		return {
			title: "Prise active: tresser depuis le tore.",
			copy: "Le tore prend le relief: seuils, routes et prises deviennent assez nets pour orienter une action située.",
			primary: { label: "Ouvrir Signal", href: withSurfaceContext("/signal") },
			secondary: { label: "Relire Map", href: withSurfaceContext("/map") },
		};
	};

	const setArPilotLink = (node, config) => {
		if (!(node instanceof HTMLAnchorElement) || !config) {
			return;
		}

		node.textContent = config.label || "";
		node.href = config.href || withSurfaceContext("/");
	};

	const renderArModulation = ({
		movement = clampNumber(Math.max(membrane.motionSensor, membrane.cameraMotion), 0, 1),
		lightTone = clampNumber(membrane.lightLevel * 0.58 + membrane.luma * 0.42, 0, 1),
		ambient = clampNumber(membrane.audioLevel, 0, 1),
		shakeLevel = clampNumber(membrane.shake, 0, 1),
		presence = clampNumber(
			Math.max(
				membrane.luma,
				membrane.cameraMotion,
				membrane.motionSensor,
				membrane.audioLevel,
				membrane.lightLevel,
				Math.abs(membrane.tiltX) * 0.8,
				Math.abs(membrane.tiltY) * 0.9
			),
			0,
			1
		),
		handOpen = clampNumber(((1 - membrane.tiltY) * 0.5) * 0.74 + presence * 0.18 + movement * 0.08, 0, 1),
		live = isMembraneLive(),
		demo = isMembraneDemo(),
	} = {}) => {
		if (!(arModulationRoot instanceof HTMLElement)) {
			return;
		}

		const touchEnergy = instrumentTouchEnergy();
		const bodyEnergy = instrumentBodyEnergy();
		const sceneEnergy = instrumentSceneEnergy();
		const tiltEnergy = clampNumber((Math.abs(membrane.tiltX) + Math.abs(membrane.tiltY)) * 0.5, 0, 1);
		const modeConfig = arModeCatalog[arModulationMode] || arModeCatalog.weave;
		const baseWeights = {
			real: 0.34 + lightTone * 0.22 + presence * 0.14 + movement * 0.08 + sceneEnergy * (cameraFacingMode === "environment" ? 0.12 : 0.04) + (live ? 0.08 : 0.02),
			plasma: 0.3 + ambient * 0.22 + movement * 0.08 + voiceFx.echoAmount * 0.12 + sceneEnergy * (cameraFacingMode === "environment" ? 0.04 : 0.12) + (plasmaBridgeUrl ? 0.06 : 0.02),
			torus: 0.31 + tiltEnergy * 0.22 + handOpen * 0.16 + touchEnergy * 0.18 + bodyEnergy * 0.08 + shakeLevel * 0.14 + (demo ? 0.08 : 0.02),
		};

		if (!live && !demo) {
			baseWeights.real += 0.12;
			baseWeights.plasma -= 0.04;
			baseWeights.torus -= 0.05;
		}

		const weights = normalizeLayerWeights({
			real: baseWeights.real + (modeConfig.bias?.real || 0),
			plasma: baseWeights.plasma + (modeConfig.bias?.plasma || 0),
			torus: baseWeights.torus + (modeConfig.bias?.torus || 0),
		});
		const dominant = arDominantLayer(weights);
		const dominantLabel = arLayerLabel(dominant);
		const pilotState = arPilotStateFor({ mode: arModulationMode, dominant, live, demo });
		const directive = dominant === "real"
			? "Directive: garder le plan du monde stable, puis faire monter les signes et les seuils seulement là où ils s accrochent vraiment."
			: (dominant === "plasma"
				? "Directive: laisser le plasma traduire voix, mémoire, météo et trajectoires, puis donner au tore juste assez de prise pour guider."
				: "Directive: ouvrir le tore comme peau active du lieu, mais sans casser la lecture des corps, bords et flux déjà présents.");

		document.body.dataset.raMode = arModulationMode;
		document.body.dataset.raDominantLayer = dominant;
		document.body.style.setProperty("--ra-real-strength", weights.real.toFixed(3));
		document.body.style.setProperty("--ra-plasma-strength", weights.plasma.toFixed(3));
		document.body.style.setProperty("--ra-tore-strength", weights.torus.toFixed(3));
		arModulationRoot.dataset.xyzArMode = arModulationMode;
		arModulationRoot.dataset.xyzArDominant = dominant;
		if (mappingPanel instanceof HTMLElement) {
			mappingPanel.dataset.raDominant = dominant;
		}

		setSensorText(arTitleNode, modeConfig.title);
		setSensorText(arStatusNode, `${dominantLabel} dominant · ${modeConfig.status}`);
		setSensorText(arDirectiveNode, directive);
		setSensorText(arPilotTitleNode, pilotState.title);
		setSensorText(arPilotCopyNode, pilotState.copy);
		setArPilotLink(arPrimaryLinkNode, pilotState.primary);
		setArPilotLink(arSecondaryLinkNode, pilotState.secondary);
		setSensorText(arUsageNode, `${modeConfig.usage} Raccourcis: R ancre, P traduit, T boucle, M tresse.`);

		["real", "plasma", "torus"].forEach((layerKey) => {
			const layer = arLayerNodes[layerKey];
			const percent = Math.round(clampNumber(weights[layerKey], 0, 1) * 100);
			if (layer.card instanceof HTMLElement) {
				layer.card.dataset.layerActive = dominant === layerKey ? "1" : "0";
				layer.card.style.setProperty("--xyz-ar-layer-value", weights[layerKey].toFixed(3));
			}
			setSensorText(layer.value, `${percent}%`);
			setSensorText(layer.copy, arLayerCopyFor(layerKey, weights[layerKey], { live, demo }));
			if (layer.meter instanceof HTMLElement) {
				layer.meter.style.setProperty("--xyz-ar-layer-fill", `${percent}%`);
			}
		});

		window.dispatchEvent(new CustomEvent("o:ra-modulation", {
			detail: {
				mode: arModulationMode,
				modeLabel: modeConfig.label,
				dominant,
				dominantLabel,
				live,
				demo,
				weights,
				primary: pilotState.primary,
				secondary: pilotState.secondary,
				surface: isIoSurfaceView() ? "io" : "xyz",
			},
		}));
		writeRaModulationSession({
			mode: arModulationMode,
			modeLabel: modeConfig.label,
			dominant,
			dominantLabel,
			live,
			demo,
			primary: pilotState.primary,
			secondary: pilotState.secondary,
			surface: isIoSurfaceView() ? "io" : "xyz",
		});
	};

	const setArModulationMode = (nextMode) => {
		if (!(nextMode in arModeCatalog)) {
			return;
		}

		arModulationMode = nextMode;
		writeStoredArMode(nextMode);
		arModeButtons.forEach((button) => {
			if (!(button instanceof HTMLElement)) {
				return;
			}
			button.setAttribute("aria-pressed", button.dataset.xyzArModeButton === nextMode ? "true" : "false");
		});
		renderArModulation();
	};

	arModeButtons.forEach((button) => {
		if (!(button instanceof HTMLElement)) {
			return;
		}

		button.addEventListener("click", () => {
			setArModulationMode(button.dataset.xyzArModeButton || "weave");
		});
	});

	window.addEventListener("keydown", (event) => {
		if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey || isEditableFocusTarget(document.activeElement)) {
			return;
		}

		const key = (event.key || "").toLowerCase();
		const modeByKey = {
			r: "anchor",
			p: "translate",
			t: "loop",
			m: "weave",
		};
		const nextMode = modeByKey[key];
		if (!nextMode || !(arModulationRoot instanceof HTMLElement)) {
			return;
		}

		setArModulationMode(nextMode);
		event.preventDefault();
	});
	setArModulationMode(arModulationMode);

	const midiToFrequency = (midi) => 440 * Math.pow(2, (midi - 69) / 12);
	const frequencyToMidi = (frequency) => 69 + (12 * Math.log2(Math.max(1, frequency) / 440));
	const formatToreNoteLabel = (midi) => {
			const roundedMidi = Math.round(Number(midi) || toreRootMidi);
			const noteIndex = ((roundedMidi % 12) + 12) % 12;
			const octave = Math.floor(roundedMidi / 12) - 1;
			return `${toreNoteNames[noteIndex]}${octave}`;
		};
	const resolveToreMode = (lightTone, flavor = "") => {
			if (flavor === "velvet") {
				currentToreMode = "minor";
			} else if (flavor === "gloss" || flavor === "sap") {
				currentToreMode = "major";
			} else if (lightTone <= 0.42) {
				currentToreMode = "minor";
			} else if (lightTone >= 0.58) {
				currentToreMode = "major";
			}
			document.body.dataset.musicalMode = currentToreMode;
			return currentToreMode;
		};
	const quantizeToreMidi = (rawMidi, mode = currentToreMode) => {
			const scale = toreScaleSteps[mode] || toreScaleSteps.minor;
			let bestMidi = lastQuantizedMidi;
			let bestDistance = Number.POSITIVE_INFINITY;
			for (let octave = -1; octave <= 4; octave += 1) {
				const baseMidi = toreRootMidi + (octave * 12);
				for (const step of scale) {
					const candidateMidi = baseMidi + step;
					if (candidateMidi < 40 || candidateMidi > 79) {
						continue;
					}
					const distance = Math.abs(rawMidi - candidateMidi);
					if (distance < bestDistance) {
						bestDistance = distance;
						bestMidi = candidateMidi;
					}
				}
			}
			if (Math.abs(rawMidi - lastQuantizedMidi) < 0.46) {
				return lastQuantizedMidi;
			}
			if (Math.abs(bestMidi - lastQuantizedMidi) <= 1 && Math.abs(rawMidi - lastQuantizedMidi) < 0.84) {
				return lastQuantizedMidi;
			}
			lastQuantizedMidi = bestMidi;
			return bestMidi;
		};
	const renderToreGuide = ({
			mode = currentToreMode,
			noteLabel = formatToreNoteLabel(lastQuantizedMidi),
			shakeLevel = 0,
			movement = 0,
			lightTone = 0,
			ambient = 0,
		} = {}) => {
			const safeShake = clampNumber(shakeLevel, 0, 1);
			const safeMovement = clampNumber(movement, 0, 1);
			const safeLight = clampNumber(lightTone, 0, 1);
			const safeAmbient = clampNumber(ambient, 0, 1);
			const rhythmLabel = safeShake > 0.56
				? "shaker ouvert"
				: (safeMovement > 0.38
					? "pulsation mobile"
					: (isMembraneDemo() ? "mine lente" : "drone stable"));
			const terreState = mode === "major"
				? (safeLight > 0.66 ? "ouvre / éclaire" : "garde l ouverture")
				: (safeAmbient > 0.22 ? "tient / assombrit" : "veille / retient");
			const mineState = safeShake > 0.56
				? "frappe / relance"
				: (safeMovement > 0.38
					? "cherche / incline"
					: (isMembraneDemo() ? "creuse / répète" : "trace / tient"));
			let terreTitle = "Elle porte.";
			let mineTitle = "Elle creuse.";
			let terreCopy = "Elle stabilise le mode, ouvre ou ferme la lumière, puis garde le drone respirable.";
			let mineCopy = "Elle tient la note, déclenche l accent, cherche la voix et relance le shaker.";
			let duetCopy = "Terre porte le seuil, Mine y ouvre un trajet.";
			let duetPhase = "hold";
			let duetDominant = "terre";
			let guideText = "Incline pour tenir une note, parle pour que la voix se cale dessus, puis secoue par impulsions courtes pour marquer le shaker.";
			if (!isMembraneAudible()) {
				guideText = "Ouvre la membrane ou Terre & Mine pour installer un drone stable, puis construis avec la lumière, la voix accordée et la secousse.";
				terreTitle = "Elle prépare.";
				mineTitle = "Elle attend.";
				terreCopy = "Terre pose un fond stable avant l entrée des flux. Elle décide du sol, pas encore de l accent.";
				mineCopy = "Mine reste suspendue tant qu il n y a ni note tenue, ni secousse, ni voix accrochée.";
				duetCopy = "Terre prépare le champ, Mine n a pas encore mordu dedans.";
				duetPhase = "prepare";
				duetDominant = "terre";
			} else if (safeShake > 0.56) {
				guideText = "Le shaker est ouvert: garde des secousses courtes et régulières, puis stabilise la main pour laisser la voix accordée respirer au-dessus.";
				terreTitle = "Elle tient le socle.";
				mineTitle = "Elle frappe.";
				terreCopy = "Terre garde la matière compacte pour éviter que le shaker casse la lecture du tore.";
				mineCopy = "Mine marque la surface par impulsions courtes, relance le rythme et ouvre l accent.";
				duetCopy = "Terre tient, Mine frappe, puis la voix peut passer entre les deux.";
				duetPhase = "strike";
				duetDominant = "mine";
			} else if (mode === "major") {
				guideText = safeLight > 0.66
					? "La lumière pousse vers le majeur. Tiens l’inclinaison pour garder la note claire, puis parle pour accrocher ta voix à l’accord."
					: "Le majeur est là mais encore fragile. Ouvre un peu plus la lumière ou lève le téléphone pour élargir la couleur.";
				terreTitle = "Elle ouvre.";
				mineTitle = "Elle taille clair.";
				terreCopy = "Terre élargit le champ, éclaire le mode et garde la base en majeur sans l aplatir.";
				mineCopy = "Mine affine la note, cherche une ligne plus nette et laisse la voix se poser sans dureté.";
				duetCopy = "Terre ouvre la pièce, Mine taille dedans une forme plus claire.";
				duetPhase = "open";
				duetDominant = "terre";
			} else if (safeAmbient > 0.22) {
				guideText = "Le mineur tient le terrain. Parle, souffle ou fais grésiller la pièce pour verrouiller la voix sur la note avant d’ajouter le shaker.";
				terreTitle = "Elle retient.";
				mineTitle = "Elle creuse plus bas.";
				terreCopy = "Terre ferme un peu la lumière, garde le mode mineur et donne au tore une densité plus grave.";
				mineCopy = "Mine insiste sur la note, fait naître une veine plus sombre et attend la voix avant l accent.";
				duetCopy = "Terre retient la lumière, Mine creuse un passage plus grave.";
				duetPhase = "grave";
				duetDominant = "mine";
			} else if (isMembraneDemo()) {
				terreTitle = "Elle rejoue.";
				mineTitle = "Elle répète.";
				terreCopy = "Terre garde le cycle lisible pendant que la démo alterne ouverture, ombre et montée.";
				mineCopy = "Mine rejoue l accent, la note et la secousse pour faire sentir la partition sans capteurs.";
				duetCopy = "Terre montre la forme, Mine la répète jusqu à ce qu elle devienne évidente.";
				duetPhase = "repeat";
				duetDominant = "mine";
			}
			document.body.dataset.musicalMode = mode;
			document.body.dataset.duetPhase = duetPhase;
			document.body.dataset.duetDominant = duetDominant;
			if (musicGuideNode instanceof HTMLElement) {
				musicGuideNode.dataset.duetPhase = duetPhase;
				musicGuideNode.dataset.duetDominant = duetDominant;
			}
			setSensorText(musicModeNode, toreModeLabels[mode] || toreModeLabels.minor);
			setSensorText(musicNoteNode, noteLabel);
			setSensorText(musicRhythmNode, rhythmLabel);
			setSensorText(handTerreStateNode, terreState);
			setSensorText(handMineStateNode, mineState);
			setSensorText(handTerreTitleNode, terreTitle);
			setSensorText(handMineTitleNode, mineTitle);
			setSensorText(handTerreCopyNode, terreCopy);
			setSensorText(handMineCopyNode, mineCopy);
			setSensorText(duetStateNode, duetCopy);
			setSensorText(musicGuideNode, guideText);
		};
	const ensureShakerNoiseBuffer = (context) => {
			if (shakerNoiseBuffer && shakerNoiseBuffer.sampleRate === context.sampleRate) {
				return shakerNoiseBuffer;
			}
			const frameCount = Math.max(1, Math.round(context.sampleRate * 0.18));
			const buffer = context.createBuffer(1, frameCount, context.sampleRate);
			const channel = buffer.getChannelData(0);
			for (let index = 0; index < channel.length; index += 1) {
				channel[index] = (Math.random() * 2) - 1;
			}
			shakerNoiseBuffer = buffer;
			return shakerNoiseBuffer;
		};
	const triggerShaker = async (intensity = 0.4) => {
			const context = await ensureReactiveAudioContext();
			if (!context) {
				return false;
			}
			const deviceProfile = readDeviceAudioProfile();
			if (deviceProfile.muted || document.hidden || !isMembraneAudible()) {
				return false;
			}
			const nowStamp = performance.now();
			if (nowStamp - lastShakeAt < 92) {
				return false;
			}
			lastShakeAt = nowStamp;
			const buffer = ensureShakerNoiseBuffer(context);
			const source = context.createBufferSource();
			const highpass = context.createBiquadFilter();
			const bandpass = context.createBiquadFilter();
			const gain = context.createGain();
			source.buffer = buffer;
			highpass.type = "highpass";
			highpass.frequency.value = 1800 + (intensity * 2200);
			bandpass.type = "bandpass";
			bandpass.frequency.value = 2800 + (intensity * 2400);
			bandpass.Q.value = 0.8 + (intensity * 2.2);
			gain.gain.value = 0.0001;
			source.connect(highpass);
			highpass.connect(bandpass);
			bandpass.connect(gain);
			gain.connect(context.destination);
			const now = context.currentTime;
			const peak = clampNumber((0.018 + (intensity * 0.072)) * deviceProfile.volume, 0.016, 0.11);
			gain.gain.setValueAtTime(0.0001, now);
			gain.gain.linearRampToValueAtTime(peak, now + 0.01);
			gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.16 + (intensity * 0.1));
			source.start(now);
			source.stop(now + 0.22 + (intensity * 0.12));
			return true;
		};
	renderToreGuide();

	const demoPhasePalette = (key) => {
			switch (key) {
				case "velvet":
					return [96, 92, 166];
				case "gloss":
					return [122, 226, 255];
				case "pulse":
					return [255, 118, 172];
				case "sap":
					return [255, 210, 110];
				default:
					return [180, 180, 180];
			}
		};

	const demoPhaseMetaAt = (progress) => {
		const safeProgress = clampNumber(progress, 0, 1);
		for (let index = 0; index < demoPhases.length; index += 1) {
			const phase = demoPhases[index];
			if (safeProgress <= phase.until) {
				const previousEdge = index === 0 ? 0 : demoPhases[index - 1].until;
				const span = Math.max(phase.until - previousEdge, 0.0001);
				return {
					phase,
					index,
					localProgress: clampNumber((safeProgress - previousEdge) / span, 0, 1),
					previousEdge,
				};
			}
		}

		const phase = demoPhases[demoPhases.length - 1];
		return {
			phase,
			index: demoPhases.length - 1,
			localProgress: 1,
			previousEdge: demoPhases[demoPhases.length - 2]?.until || 0,
		};
	};

	const syncMembraneReactiveState = () => {
		const presence = clampNumber(
			Math.max(
				membrane.luma,
				membrane.cameraMotion,
				membrane.motionSensor,
				membrane.audioLevel,
				membrane.lightLevel,
				Math.abs(membrane.tiltX) * 0.8,
				Math.abs(membrane.tiltY) * 0.9
			),
			0,
			1
		);
		renderWorldInstrument();
		document.body.dataset.membraneAudio = membrane.audioLevel.toFixed(3);
			document.body.dataset.membraneLight = membrane.lightLevel.toFixed(3);
			document.body.dataset.membraneTiltX = membrane.tiltX.toFixed(3);
			document.body.dataset.membraneTiltY = membrane.tiltY.toFixed(3);
			document.body.dataset.membraneMotion = membrane.motionSensor.toFixed(3);
			document.body.dataset.membraneShake = clampNumber(membrane.shake, 0, 1).toFixed(3);
			document.body.dataset.membranePresence = presence.toFixed(3);
			document.body.style.setProperty("--membrane-audio", membrane.audioLevel.toFixed(3));
			document.body.style.setProperty("--membrane-light", membrane.lightLevel.toFixed(3));
			document.body.style.setProperty("--membrane-tilt-x", membrane.tiltX.toFixed(3));
			document.body.style.setProperty("--membrane-tilt-y", membrane.tiltY.toFixed(3));
			document.body.style.setProperty("--membrane-motion", membrane.motionSensor.toFixed(3));
			document.body.style.setProperty("--membrane-shake", clampNumber(membrane.shake, 0, 1).toFixed(3));
			document.body.style.setProperty("--membrane-presence", presence.toFixed(3));
			renderArModulation({
				movement: clampNumber(Math.max(membrane.motionSensor, membrane.cameraMotion), 0, 1),
				lightTone: clampNumber(membrane.lightLevel * 0.58 + membrane.luma * 0.42, 0, 1),
				ambient: clampNumber(membrane.audioLevel, 0, 1),
				shakeLevel: clampNumber(membrane.shake, 0, 1),
				presence,
				handOpen: clampNumber(((1 - membrane.tiltY) * 0.5) * 0.52 + instrumentTouchEnergy() * 0.3 + instrumentBodyEnergy() * 0.1 + presence * 0.08, 0, 1),
				live: isMembraneLive(),
				demo: isMembraneDemo(),
			});
			updateMotionVoice();
		};

	const resetMembraneReactiveState = () => {
		membrane.luma = 0;
		membrane.cameraMotion = 0;
		membrane.audioLevel = 0;
			membrane.lightLevel = 0;
			membrane.tiltX = 0;
			membrane.tiltY = 0;
			membrane.motionSensor = 0;
			membrane.shake = 0;
			syncMembraneReactiveState();
		};

	const membraneMetricsSnapshot = () => ({
		...(function () {
			const deviceState = getCurrentDeviceBridgeState();
			return {
				device_volume: clampNumber(readDeviceAudioProfile().volume, 0, 1),
				silence_intent: deviceState.silenceIntent ? 1 : 0,
				native_silence: deviceState.nativeSilenceMode === "silent" ? 1 : 0,
				visibility_hidden: deviceState.visibility === "hidden" ? 1 : 0,
				standalone: deviceState.standalone ? 1 : 0,
			};
		})(),
		presence: clampNumber(
			Math.max(
				membrane.luma,
				membrane.cameraMotion,
				membrane.motionSensor,
				membrane.audioLevel,
				membrane.lightLevel,
				Math.abs(membrane.tiltX) * 0.8,
				Math.abs(membrane.tiltY) * 0.9
			),
			0,
			1
		),
		motion: clampNumber(Math.max(membrane.cameraMotion, membrane.motionSensor), 0, 1),
		audio: clampNumber(membrane.audioLevel, 0, 1),
		light: clampNumber(Math.max(membrane.lightLevel, membrane.luma), 0, 1),
		luma: clampNumber(membrane.luma, 0, 1),
		tilt_x: clampNumber(membrane.tiltX, -1, 1),
		tilt_y: clampNumber(membrane.tiltY, -1, 1),
	});

	const membraneSignature = (metrics) => [
		Math.round(clampNumber(metrics.presence, 0, 1) * 10),
		Math.round(clampNumber(metrics.motion, 0, 1) * 10),
		Math.round(clampNumber(metrics.audio, 0, 1) * 10),
		Math.round(clampNumber(metrics.light, 0, 1) * 10),
		Math.round(clampNumber(metrics.device_volume, 0, 1) * 10),
		metrics.silence_intent > 0.5 ? 1 : 0,
		Math.round((clampNumber(metrics.tilt_x, -1, 1) + 1) * 5),
		Math.round((clampNumber(metrics.tilt_y, -1, 1) + 1) * 5),
	].join(":");

	const membraneBridgeCopy = (metrics) => `présence ${Math.round(metrics.presence * 100)}% · mouvement ${Math.round(metrics.motion * 100)}% · souffle ${Math.round(metrics.audio * 100)}% · lumière ${Math.round(metrics.light * 100)}% · niveau ${Math.round(metrics.device_volume * 100)}%${metrics.silence_intent > 0.5 ? " · silence web" : ""}${metrics.native_silence > 0.5 ? " · silence natif" : ""}`;

	const sendMembraneBridge = async (eventName, message, { force = false, keepalive = false } = {}) => {
		if (!plasmaBridgeUrl || bridgeInFlight) {
			return false;
		}

		const metrics = membraneMetricsSnapshot();
		const signature = membraneSignature(metrics);
		const now = Date.now();
		if (!force && signature === bridgeLastSignature && now - bridgeLastAt < 11000) {
			return false;
		}

		bridgeInFlight = true;
		try {
			const response = await fetch(plasmaBridgeUrl, {
				method: "POST",
				mode: "cors",
				cache: "no-store",
				keepalive,
				headers: {
					"Content-Type": "application/json",
				},
				body: JSON.stringify({
					event: eventName,
					surface: "xyz",
					camera: document.body.dataset.cameraFlavor || "membrane",
					land_slug: membraneLandSlug || "",
					timestamp: new Date().toISOString(),
					message,
					metrics,
				}),
			});
			if (!response.ok) {
				return false;
			}

			bridgeLastSignature = signature;
			bridgeLastAt = now;
			return true;
		} catch {
			return false;
		} finally {
			bridgeInFlight = false;
		}
	};

	const stopBridgePulse = () => {
		if (bridgePulseTimer) {
			window.clearInterval(bridgePulseTimer);
			bridgePulseTimer = 0;
		}
	};

	const startBridgePulse = () => {
		if (bridgePulseTimer || !plasmaBridgeUrl) {
			return;
		}

		bridgePulseTimer = window.setInterval(() => {
			if (!isMembraneLive()) {
				return;
			}

			const metrics = membraneMetricsSnapshot();
			void sendMembraneBridge("membrane_pulse", membraneBridgeCopy(metrics));
		}, 12000);
	};

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
		membrane.luma = safeLuma;
		membrane.cameraMotion = safeMotion;
		if (!(lightSensor && isMembraneLive())) {
			membrane.lightLevel = safeLuma;
		}
		syncMembraneReactiveState();
	};

	const resetReactiveCssState = () => {
		previousSamples = null;
		setReactiveCssState(0, 0, [180, 180, 180], "neutral");
		resetMembraneReactiveState();
	};

	const describeCameraFlavor = (luma, motion) => {
		if (cameraFacingMode === "environment") {
			if (motion > 0.46 && luma > 0.52) {
				return {
					key: "sap",
					title: "Le paysage joue avec le tore.",
					message: "Le dehors entre fort: marche, reflets, ciel, sols et façades deviennent une matière vive que le tore replie en rythme et en couleur.",
				};
			}

			if (motion > 0.5) {
				return {
					key: "pulse",
					title: "La marche plie le paysage.",
					message: "Le monde bouge dans le cadre. Le tore prend l horizon, les bords et les écarts comme une percussion de traversée.",
				};
			}

			if (luma > 0.62) {
				return {
					key: "gloss",
					title: "Les reflets nourrissent la surface.",
					message: "Le paysage s ouvre par lumière. Le tore boit les clairs, les surfaces et les reflets comme une nappe mobile.",
				};
			}
		}

		if (motion > 0.42 && membrane.audioLevel > 0.24) {
			return {
				key: "sap",
				title: "La membrane boit voix et lumière.",
				message: "Le téléphone remonte du grain, du souffle et de l’inclinaison. Le tore devient plus nerveux, presque mastiqué par la présence.",
			};
		}

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

	const stopAudio = () => {
		if (audioFrame) {
			window.cancelAnimationFrame(audioFrame);
			audioFrame = 0;
		}
		audioSource = null;
		audioAnalyser = null;
		motionVoiceOscillator = null;
		motionVoiceHarmonicOscillator = null;
		motionVoiceSubOscillator = null;
		motionVoiceGain = null;
		motionVoiceHarmonicGain = null;
		motionVoiceSubGain = null;
		motionVoiceFilter = null;
		motionVoicePanner = null;
		motionVoiceLfo = null;
		motionVoiceLfoGain = null;
		vocoderInputGain = null;
		vocoderHighpass = null;
		vocoderCompressor = null;
		vocoderBandpass = null;
		vocoderDirectGain = null;
		vocoderColorFilter = null;
		vocoderCarrierOscillator = null;
		vocoderCarrierHarmonicOscillator = null;
		vocoderCarrierGain = null;
		vocoderCarrierHarmonicGain = null;
		vocoderModDepth = null;
		vocoderHarmonicModDepth = null;
		vocoderWetGain = null;
		vocoderEchoSend = null;
		vocoderEchoDelay = null;
		vocoderEchoFeedback = null;
		vocoderEchoReturn = null;
		vocoderTuneFilter = null;
		vocoderTuneHarmonicFilter = null;
		vocoderTuneGain = null;
		vocoderTuneHarmonicGain = null;
			if (audioContext && typeof audioContext.close === "function") {
				audioContext.close().catch(() => {});
			}
			audioContext = null;
			shakerNoiseBuffer = null;
			lastShakeAt = 0;
			lastMotionMagnitude = 0;
			lastDemoShakeBurst = 0;
			currentToreMode = "minor";
			lastQuantizedMidi = toreRootMidi;
			membrane.audioLevel = 0;
			membrane.shake = 0;
			syncMembraneReactiveState();
			renderToreGuide({
				mode: currentToreMode,
				noteLabel: formatToreNoteLabel(lastQuantizedMidi),
				shakeLevel: 0,
				movement: 0,
				lightTone: 0,
				ambient: 0,
			});
		};

	const ensureReactiveAudioContext = async () => {
		const AudioContextClass = window.AudioContext || window.webkitAudioContext;
		if (!AudioContextClass) {
			return null;
		}

		if (!audioContext) {
			audioContext = new AudioContextClass();
		}

		if (audioContext.state === "suspended") {
			await audioContext.resume().catch(() => {});
		}

		return audioContext;
	};

		function updateMotionVoice(forceMute = false) {
			if (
				!audioContext
			|| !motionVoiceGain
			|| !motionVoiceOscillator
			|| !motionVoiceHarmonicOscillator
			|| !motionVoiceSubOscillator
		) {
			return;
			}

			const deviceProfile = readDeviceAudioProfile();
			const flavor = document.body.dataset.cameraFlavor || "";
			membrane.shake = clampNumber(membrane.shake * (isMembraneDemo() ? 0.96 : 0.88), 0, 1);
			const shakeLevel = clampNumber(membrane.shake, 0, 1);
			const motionEnergy = clampNumber(Math.max(membrane.motionSensor, membrane.cameraMotion * 0.92), 0, 1);
			const tiltEnergy = clampNumber((Math.abs(membrane.tiltX) + Math.abs(membrane.tiltY)) * 0.5, 0, 1);
			const lightTone = clampNumber(membrane.lightLevel * 0.58 + membrane.luma * 0.42, 0, 1);
			const ambient = clampNumber(membrane.audioLevel, 0, 1);
			const touchEnergy = instrumentTouchEnergy();
			const bodyPlay = instrumentBodyEnergy();
			const sceneEnergy = instrumentSceneEnergy();
			const orientationX = clampNumber(((membrane.tiltX + 1) * 0.5) * 0.68 + instrument.mineX * 0.32, 0, 1);
			const orientationY = clampNumber(((1 - membrane.tiltY) * 0.5) * 0.62 + instrument.terreY * 0.38, 0, 1);
			const harmonicMode = resolveToreMode(lightTone, flavor);
			const shimmer = clampNumber(ambient * 0.36 + lightTone * 0.24 + membrane.cameraMotion * 0.14 + motionEnergy * 0.08 + touchEnergy * 0.12 + shakeLevel * 0.18, 0, 1);
			const movement = clampNumber(motionEnergy * 0.46 + membrane.cameraMotion * 0.12 + tiltEnergy * 0.12 + bodyPlay * 0.18 + touchEnergy * 0.14 + shakeLevel * 0.28, 0, 1);
			const presence = clampNumber(
				Math.max(
					membrane.luma,
				membrane.cameraMotion,
				membrane.motionSensor,
				membrane.audioLevel,
				membrane.lightLevel,
				Math.abs(membrane.tiltX) * 0.8,
				Math.abs(membrane.tiltY) * 0.9
			),
			0,
			1
		);
		const handOpen = clampNumber(orientationY * 0.52 + touchEnergy * 0.28 + presence * 0.12 + movement * 0.08, 0, 1);
		const movementGate = clampNumber((movement - 0.015) / 0.985, 0, 1);
		const orientationGate = clampNumber(handOpen * 0.56 + lightTone * 0.14 + Math.abs(membrane.tiltX) * 0.08 + touchEnergy * 0.22, 0, 1);
			const gate = clampNumber(
				Math.max(movementGate * 0.82, orientationGate * 0.68),
				0,
			1
		) * clampNumber(0.42 + presence * 0.58, 0, 1);
			const feedbackSafety = clampNumber(1 - ambient * 0.28, 0.62, 1);
			const audible = !forceMute && !document.hidden && isMembraneAudible() && !deviceProfile.muted;
			const demoPulseFloor = audible && isMembraneDemo()
				? clampNumber((0.02 + handOpen * 0.016 + lightTone * 0.01 + shakeLevel * 0.008) * deviceProfile.volume * feedbackSafety, 0.02, 0.044)
				: 0;
			const pitchOctaves = 0.3 + orientationX * 1.68 + lightTone * 0.42 + ambient * 0.12 + (instrumentPitchBias() * 0.18);
			const rawMidi = toreRootMidi + (pitchOctaves * 12) + (instrumentPitchBias() * 4.2);
			const quantizedMidi = quantizeToreMidi(rawMidi, harmonicMode);
			const noteLabel = formatToreNoteLabel(quantizedMidi);
			const targetFrequency = midiToFrequency(quantizedMidi);
			const harmonicFrequency = midiToFrequency(Math.min(quantizedMidi + (harmonicMode === "major" ? 16 : 15), 91));
			const subFrequency = midiToFrequency(Math.max(quantizedMidi - 12, 28));
			const targetDetune = (membrane.tiltX * 26) - (membrane.tiltY * 16) + (ambient * 4) + (shakeLevel * 6) - (movement * 3) + (instrumentTextureBias() * 18);
			const droneFloor = audible
				? clampNumber((0.014 + handOpen * 0.012 + touchEnergy * 0.01 + sceneEnergy * 0.008 + lightTone * 0.008) * deviceProfile.volume * feedbackSafety, 0, 0.038)
				: 0;
			const targetGain = audible
				? clampNumber(
					gate * (handOpen * 0.82 + touchEnergy * 0.24) * deviceProfile.volume * feedbackSafety * 0.132 + droneFloor + demoPulseFloor,
					0,
					isMembraneDemo() ? 0.168 : 0.148
				)
				: 0;
			const targetHarmonicGain = audible
				? clampNumber(targetGain * (0.4 + lightTone * 0.22 + shakeLevel * 0.16), 0, isMembraneDemo() ? 0.082 : 0.072)
				: 0;
			const targetSubGain = audible
				? clampNumber(targetGain * (0.3 + (1 - lightTone) * 0.22 + movement * 0.1), 0, isMembraneDemo() ? 0.064 : 0.054)
				: 0;
			const targetPan = clampNumber(membrane.tiltX * 0.62 + ((instrument.mineX - instrument.terreX) * 0.9) + (orientationX - 0.5) * 0.14, -1, 1);
			const tunePresence = audible ? clampNumber(((ambient - 0.015) / 0.52) + handOpen * 0.16 + touchEnergy * 0.18 + movement * 0.08 + lightTone * 0.06, 0, 1) : 0;
			const targetVoiceDrive = audible ? clampNumber(0.98 + presence * 0.56 + handOpen * 0.18 + ambient * 0.12 - ambient * 0.06, 0.9, 1.72) : 0.84;
			const targetVoiceModDepth = audible ? clampNumber(0.16 + gate * 0.24 + lightTone * 0.08 + ambient * 0.04 + tunePresence * 0.08, 0.12, 0.48) : 0.02;
			const targetVoiceHarmonicDepth = audible ? clampNumber(targetVoiceModDepth * (0.3 + lightTone * 0.18 + ambient * 0.04), 0.05, 0.24) : 0.01;
			const targetVoiceDirect = audible ? clampNumber(gate * deviceProfile.volume * feedbackSafety * (0.08 + handOpen * 0.06) * (1 - tunePresence * 0.34), 0, 0.14) : 0;
			const targetVoiceWet = audible ? clampNumber(deviceProfile.volume * feedbackSafety * (0.16 + handOpen * 0.12 + movement * 0.08 + ambient * 0.04 + tunePresence * 0.04), 0, 0.34) : 0;
			const targetVoiceBandpass = clampNumber(targetFrequency * (0.94 + lightTone * 0.36), 140, 2600);
			const targetVoiceBandpassQ = 0.72 + ambient * 0.48 + movement * 0.34;
			const targetVoiceColor = clampNumber(340 + lightTone * 1920 + ambient * 380 + movement * 240, 320, 4200);
			const targetVoiceTuneFrequency = clampNumber(Math.max(targetFrequency * 2, targetFrequency + 72), 140, 2800);
			const targetVoiceTuneHarmonicFrequency = clampNumber(Math.max(harmonicFrequency, targetFrequency * 3), 220, 4200);
			const targetVoiceTuneQ = 5.8 + handOpen * 2.8 + ambient * 1.8 + lightTone * 1.2 - movement * 0.8;
			const targetVoiceTuneHarmonicQ = 4.8 + lightTone * 2.8 + ambient * 1.8 + shakeLevel * 1.2;
			const targetVoiceTuneGain = audible ? clampNumber(deviceProfile.volume * feedbackSafety * (0.02 + tunePresence * 0.08 + ambient * 0.04 + handOpen * 0.03), 0, isMembraneDemo() ? 0.14 : 0.12) : 0;
			const targetVoiceTuneHarmonicGain = audible ? clampNumber(targetVoiceTuneGain * (0.26 + lightTone * 0.16 + ambient * 0.1 + shakeLevel * 0.06), 0, isMembraneDemo() ? 0.08 : 0.07) : 0;
			const targetVoiceEchoSend = audible ? clampNumber(voiceFx.echoAmount * (0.08 + handOpen * 0.08 + touchEnergy * 0.04 + lightTone * 0.04 + tunePresence * 0.03) * feedbackSafety, 0, 0.1) : 0;
			const targetVoiceEchoFeedback = audible ? clampNumber(0.08 + voiceFx.echoAmount * 0.18 + ambient * 0.03, 0.08, 0.26) : 0.08;
			const targetVoiceEchoReturn = audible ? clampNumber(voiceFx.echoAmount * (0.12 + lightTone * 0.08), 0, 0.1) : 0;
			renderToreGuide({
				mode: harmonicMode,
				noteLabel,
				shakeLevel,
				movement,
				lightTone,
				ambient,
			});
			const now = audioContext.currentTime;

		motionVoiceOscillator.frequency.setTargetAtTime(targetFrequency, now, 0.09);
		motionVoiceHarmonicOscillator.frequency.setTargetAtTime(harmonicFrequency, now, 0.11);
		motionVoiceSubOscillator.frequency.setTargetAtTime(subFrequency, now, 0.12);
		motionVoiceOscillator.detune.setTargetAtTime(targetDetune, now, 0.12);
		motionVoiceHarmonicOscillator.detune.setTargetAtTime(targetDetune * 0.42, now, 0.14);
		motionVoiceSubOscillator.detune.setTargetAtTime(targetDetune * -0.12, now, 0.16);
		motionVoiceGain.gain.cancelScheduledValues(now);
		motionVoiceGain.gain.setTargetAtTime(targetGain, now, audible ? 0.06 : 0.02);
		if (motionVoiceHarmonicGain) {
			motionVoiceHarmonicGain.gain.setTargetAtTime(targetHarmonicGain, now, audible ? 0.08 : 0.02);
		}
		if (motionVoiceSubGain) {
			motionVoiceSubGain.gain.setTargetAtTime(targetSubGain, now, audible ? 0.08 : 0.02);
		}

			if (motionVoiceFilter) {
				motionVoiceFilter.frequency.setTargetAtTime(220 + lightTone * 1680 + ambient * 540 + movement * 360 + shakeLevel * 520, now, 0.12);
				motionVoiceFilter.Q.setTargetAtTime(0.7 + ambient * 1.8 + movement * 0.6 + shakeLevel * 0.8, now, 0.12);
			}

		if (motionVoicePanner) {
			motionVoicePanner.pan.setTargetAtTime(targetPan, now, 0.14);
		}

			if (motionVoiceLfo) {
				motionVoiceLfo.frequency.setTargetAtTime(0.12 + movement * 2.4 + ambient * 0.7 + shakeLevel * 1.2 + lightTone * 0.22, now, 0.18);
			}

			if (motionVoiceLfoGain) {
				motionVoiceLfoGain.gain.setTargetAtTime(2.6 + movement * 15 + ambient * 7 + shakeLevel * 10 + Math.abs(membrane.tiltX) * 5, now, 0.18);
			}

		if (vocoderCarrierOscillator) {
			vocoderCarrierOscillator.frequency.setTargetAtTime(targetFrequency, now, 0.08);
		}

		if (vocoderCarrierHarmonicOscillator) {
			vocoderCarrierHarmonicOscillator.frequency.setTargetAtTime(harmonicFrequency, now, 0.1);
		}

			if (vocoderCarrierGain) {
				vocoderCarrierGain.gain.setTargetAtTime(audible ? 0.022 + gate * 0.03 + lightTone * 0.008 : 0, now, audible ? 0.12 : 0.04);
			}

			if (vocoderCarrierHarmonicGain) {
				vocoderCarrierHarmonicGain.gain.setTargetAtTime(audible ? 0.014 + lightTone * 0.022 + ambient * 0.006 + shakeLevel * 0.004 : 0, now, audible ? 0.12 : 0.04);
			}

		if (vocoderInputGain) {
			vocoderInputGain.gain.setTargetAtTime(targetVoiceDrive, now, 0.18);
		}

		if (vocoderBandpass) {
			vocoderBandpass.frequency.setTargetAtTime(targetVoiceBandpass, now, 0.12);
			vocoderBandpass.Q.setTargetAtTime(targetVoiceBandpassQ, now, 0.12);
		}

		if (vocoderColorFilter) {
			vocoderColorFilter.frequency.setTargetAtTime(targetVoiceColor, now, 0.16);
			vocoderColorFilter.Q.setTargetAtTime(0.72 + lightTone * 0.6 + ambient * 0.18, now, 0.16);
		}

		if (vocoderTuneFilter) {
			vocoderTuneFilter.frequency.setTargetAtTime(targetVoiceTuneFrequency, now, 0.1);
			vocoderTuneFilter.Q.setTargetAtTime(targetVoiceTuneQ, now, 0.12);
		}

		if (vocoderTuneHarmonicFilter) {
			vocoderTuneHarmonicFilter.frequency.setTargetAtTime(targetVoiceTuneHarmonicFrequency, now, 0.12);
			vocoderTuneHarmonicFilter.Q.setTargetAtTime(targetVoiceTuneHarmonicQ, now, 0.14);
		}

		if (vocoderModDepth) {
			vocoderModDepth.gain.setTargetAtTime(targetVoiceModDepth, now, audible ? 0.12 : 0.04);
		}

		if (vocoderHarmonicModDepth) {
			vocoderHarmonicModDepth.gain.setTargetAtTime(targetVoiceHarmonicDepth, now, audible ? 0.12 : 0.04);
		}

		if (vocoderDirectGain) {
			vocoderDirectGain.gain.setTargetAtTime(targetVoiceDirect, now, audible ? 0.12 : 0.04);
		}

		if (vocoderTuneGain) {
			vocoderTuneGain.gain.setTargetAtTime(targetVoiceTuneGain, now, audible ? 0.12 : 0.04);
		}

		if (vocoderTuneHarmonicGain) {
			vocoderTuneHarmonicGain.gain.setTargetAtTime(targetVoiceTuneHarmonicGain, now, audible ? 0.12 : 0.04);
		}

		if (vocoderWetGain) {
			vocoderWetGain.gain.setTargetAtTime(targetVoiceWet, now, audible ? 0.12 : 0.04);
		}

		if (vocoderEchoSend) {
			vocoderEchoSend.gain.setTargetAtTime(targetVoiceEchoSend, now, audible ? 0.16 : 0.04);
		}

		if (vocoderEchoFeedback) {
			vocoderEchoFeedback.gain.setTargetAtTime(targetVoiceEchoFeedback, now, audible ? 0.18 : 0.04);
		}

		if (vocoderEchoReturn) {
			vocoderEchoReturn.gain.setTargetAtTime(targetVoiceEchoReturn, now, audible ? 0.18 : 0.04);
		}
	}

	const ensureMotionVoice = async () => {
		const context = await ensureReactiveAudioContext();
		if (!context) {
			return false;
		}

		if (motionVoiceOscillator && motionVoiceGain) {
			updateMotionVoice();
			return true;
		}

		motionVoiceOscillator = context.createOscillator();
		motionVoiceHarmonicOscillator = context.createOscillator();
		motionVoiceSubOscillator = context.createOscillator();
		motionVoiceFilter = context.createBiquadFilter();
		motionVoiceGain = context.createGain();
		motionVoiceHarmonicGain = context.createGain();
		motionVoiceSubGain = context.createGain();
		motionVoicePanner = typeof context.createStereoPanner === "function" ? context.createStereoPanner() : null;
		motionVoiceLfo = context.createOscillator();
		motionVoiceLfoGain = context.createGain();

		motionVoiceOscillator.type = "sine";
		motionVoiceHarmonicOscillator.type = "triangle";
		motionVoiceSubOscillator.type = "sine";
		motionVoiceOscillator.frequency.value = 164;
		motionVoiceHarmonicOscillator.frequency.value = 246;
		motionVoiceSubOscillator.frequency.value = 82;
		motionVoiceFilter.type = "lowpass";
		motionVoiceFilter.frequency.value = 360;
		motionVoiceFilter.Q.value = 1.2;
		motionVoiceGain.gain.value = 0;
		motionVoiceHarmonicGain.gain.value = 0;
		motionVoiceSubGain.gain.value = 0;
		if (motionVoicePanner) {
			motionVoicePanner.pan.value = 0;
		}
		motionVoiceLfo.type = "sine";
		motionVoiceLfo.frequency.value = 0.28;
		motionVoiceLfoGain.gain.value = 8;

		motionVoiceLfo.connect(motionVoiceLfoGain);
		motionVoiceLfoGain.connect(motionVoiceOscillator.detune);
		motionVoiceLfoGain.connect(motionVoiceHarmonicOscillator.detune);
		motionVoiceOscillator.connect(motionVoiceFilter);
		motionVoiceHarmonicOscillator.connect(motionVoiceHarmonicGain);
		motionVoiceHarmonicGain.connect(motionVoiceFilter);
		motionVoiceSubOscillator.connect(motionVoiceSubGain);
		motionVoiceSubGain.connect(motionVoiceFilter);
		if (motionVoicePanner) {
			motionVoiceFilter.connect(motionVoicePanner);
			motionVoicePanner.connect(motionVoiceGain);
		} else {
			motionVoiceFilter.connect(motionVoiceGain);
		}
		motionVoiceGain.connect(context.destination);
		motionVoiceOscillator.start();
		motionVoiceHarmonicOscillator.start();
		motionVoiceSubOscillator.start();
		motionVoiceLfo.start();
		updateMotionVoice();
		return true;
	};

	const ensureVoiceVocoder = async () => {
		const context = await ensureReactiveAudioContext();
		if (!context || !audioSource) {
			return false;
		}

		if (vocoderCarrierOscillator && vocoderWetGain) {
			updateMotionVoice();
			return true;
		}

		vocoderInputGain = context.createGain();
		vocoderHighpass = context.createBiquadFilter();
		vocoderCompressor = context.createDynamicsCompressor();
		vocoderBandpass = context.createBiquadFilter();
		vocoderDirectGain = context.createGain();
		vocoderColorFilter = context.createBiquadFilter();
		vocoderCarrierOscillator = context.createOscillator();
		vocoderCarrierHarmonicOscillator = context.createOscillator();
		vocoderCarrierGain = context.createGain();
		vocoderCarrierHarmonicGain = context.createGain();
		vocoderModDepth = context.createGain();
		vocoderHarmonicModDepth = context.createGain();
		vocoderTuneFilter = context.createBiquadFilter();
		vocoderTuneHarmonicFilter = context.createBiquadFilter();
		vocoderTuneGain = context.createGain();
		vocoderTuneHarmonicGain = context.createGain();
		vocoderWetGain = context.createGain();
		vocoderEchoSend = context.createGain();
		vocoderEchoDelay = context.createDelay(0.9);
		vocoderEchoFeedback = context.createGain();
		vocoderEchoReturn = context.createGain();

		vocoderInputGain.gain.value = 0.96;
		vocoderHighpass.type = "highpass";
		vocoderHighpass.frequency.value = 180;
		vocoderHighpass.Q.value = 0.78;
		vocoderCompressor.threshold.value = -26;
		vocoderCompressor.knee.value = 16;
		vocoderCompressor.ratio.value = 4.4;
		vocoderCompressor.attack.value = 0.004;
		vocoderCompressor.release.value = 0.16;
		vocoderBandpass.type = "bandpass";
		vocoderBandpass.frequency.value = 360;
		vocoderBandpass.Q.value = 0.92;
		vocoderDirectGain.gain.value = 0;
		vocoderColorFilter.type = "lowpass";
		vocoderColorFilter.frequency.value = 860;
		vocoderColorFilter.Q.value = 0.84;
		vocoderCarrierOscillator.type = "sawtooth";
		vocoderCarrierHarmonicOscillator.type = "triangle";
		vocoderCarrierOscillator.frequency.value = 164;
		vocoderCarrierHarmonicOscillator.frequency.value = 246;
		vocoderCarrierGain.gain.value = 0;
		vocoderCarrierHarmonicGain.gain.value = 0;
		vocoderModDepth.gain.value = 0.16;
		vocoderHarmonicModDepth.gain.value = 0.08;
		vocoderTuneFilter.type = "bandpass";
		vocoderTuneFilter.frequency.value = 220;
		vocoderTuneFilter.Q.value = 6.6;
		vocoderTuneHarmonicFilter.type = "bandpass";
		vocoderTuneHarmonicFilter.frequency.value = 440;
		vocoderTuneHarmonicFilter.Q.value = 5.2;
		vocoderTuneGain.gain.value = 0;
		vocoderTuneHarmonicGain.gain.value = 0;
		vocoderWetGain.gain.value = 0;
		vocoderEchoSend.gain.value = 0;
		vocoderEchoDelay.delayTime.value = 0.24;
		vocoderEchoFeedback.gain.value = 0.16;
		vocoderEchoReturn.gain.value = 0;

		audioSource.connect(vocoderInputGain);
		vocoderInputGain.connect(vocoderHighpass);
		vocoderHighpass.connect(vocoderCompressor);
		vocoderCompressor.connect(vocoderBandpass);
		vocoderCompressor.connect(vocoderTuneFilter);
		vocoderCompressor.connect(vocoderTuneHarmonicFilter);
		vocoderBandpass.connect(vocoderModDepth);
		vocoderBandpass.connect(vocoderHarmonicModDepth);
		vocoderBandpass.connect(vocoderDirectGain);
		vocoderTuneFilter.connect(vocoderTuneGain);
		vocoderTuneHarmonicFilter.connect(vocoderTuneHarmonicGain);
		vocoderModDepth.connect(vocoderCarrierGain.gain);
		vocoderHarmonicModDepth.connect(vocoderCarrierHarmonicGain.gain);
		vocoderCarrierOscillator.connect(vocoderCarrierGain);
		vocoderCarrierHarmonicOscillator.connect(vocoderCarrierHarmonicGain);
		vocoderDirectGain.connect(vocoderColorFilter);
		vocoderTuneGain.connect(vocoderColorFilter);
		vocoderTuneHarmonicGain.connect(vocoderColorFilter);
		vocoderCarrierGain.connect(vocoderColorFilter);
		vocoderCarrierHarmonicGain.connect(vocoderColorFilter);
		vocoderColorFilter.connect(vocoderWetGain);
		vocoderWetGain.connect(context.destination);
		vocoderWetGain.connect(vocoderEchoSend);
		vocoderEchoSend.connect(vocoderEchoDelay);
		vocoderEchoDelay.connect(vocoderEchoFeedback);
		vocoderEchoFeedback.connect(vocoderEchoDelay);
		vocoderEchoDelay.connect(vocoderEchoReturn);
		vocoderEchoReturn.connect(context.destination);
		vocoderCarrierOscillator.start();
		vocoderCarrierHarmonicOscillator.start();
		updateMotionVoice();
		return true;
	};

	const cueMotionVoice = (intensity = 1) => {
		if (!audioContext || !motionVoiceGain) {
			return;
		}

		const deviceProfile = readDeviceAudioProfile();
		if (deviceProfile.muted || document.hidden || !isMembraneAudible()) {
			return;
		}

			const now = audioContext.currentTime;
			const isDemoCue = isMembraneDemo();
			const peak = isDemoCue
				? clampNumber(0.066 + deviceProfile.volume * 0.072 * intensity, 0.054, 0.16)
				: clampNumber(0.042 + deviceProfile.volume * 0.048 * intensity, 0.03, 0.098);
			const sustain = isDemoCue
				? clampNumber(peak * 0.46, 0.02, 0.06)
				: clampNumber(peak * 0.38, 0.012, 0.038);
		motionVoiceGain.gain.cancelScheduledValues(now);
		motionVoiceGain.gain.setValueAtTime(Math.max(motionVoiceGain.gain.value, 0.002), now);
		motionVoiceGain.gain.linearRampToValueAtTime(peak, now + 0.06);
		motionVoiceGain.gain.exponentialRampToValueAtTime(sustain, now + 0.42);

			if (motionVoiceHarmonicGain) {
				motionVoiceHarmonicGain.gain.cancelScheduledValues(now);
				motionVoiceHarmonicGain.gain.setValueAtTime(Math.max(motionVoiceHarmonicGain.gain.value, 0.001), now);
				motionVoiceHarmonicGain.gain.linearRampToValueAtTime(clampNumber(peak * 0.58, 0.014, 0.05), now + 0.08);
				motionVoiceHarmonicGain.gain.exponentialRampToValueAtTime(clampNumber(sustain * 0.58, 0.006, 0.022), now + 0.44);
			}
	};

	const analyzeAudio = () => {
		if (!audioAnalyser) {
			return;
		}

		audioFrame = window.requestAnimationFrame(analyzeAudio);
		if (!isMembraneLive()) {
			return;
		}

		const buffer = new Uint8Array(audioAnalyser.fftSize);
		audioAnalyser.getByteTimeDomainData(buffer);
		let energy = 0;
		for (let index = 0; index < buffer.length; index += 1) {
			const sample = (buffer[index] - 128) / 128;
			energy += sample * sample;
		}
		membrane.audioLevel = clampNumber(Math.sqrt(energy / buffer.length) * 2.6, 0, 1);
		syncMembraneReactiveState();
		setSensorText(
			audioNode,
			membrane.audioLevel > 0.03
				? `${Math.round(membrane.audioLevel * 100)}% · voix accordée`
				: "souffle bas"
		);
	};

	const ensureAudioAnalyser = async (mediaStream) => {
		const context = await ensureReactiveAudioContext();
		if (!context) {
			setSensorText(audioNode, "natif");
			return false;
		}

		audioSource = context.createMediaStreamSource(mediaStream);
		audioAnalyser = context.createAnalyser();
		audioAnalyser.fftSize = 512;
		audioSource.connect(audioAnalyser);
		audioFrame = window.requestAnimationFrame(analyzeAudio);
		await ensureVoiceVocoder();
		await ensureMotionVoice();
		return true;
	};

	const stopDemoMode = () => {
		if (demoFrame) {
			window.cancelAnimationFrame(demoFrame);
			demoFrame = 0;
		}
		demoStartedAt = 0;
		demoPhaseIndex = -1;
	};

		const startDemoMembrane = async () => {
			stopDemoMode();
			setUiState(
				"demo",
				"Terre et Mine ouvrent un atelier local a deux mains: Terre porte le champ, Mine y creuse note, accent et voix, puis le tore les rejoue sans capteurs.",
				"Terre et Mine ouvrent la matière du tore."
			);
			setReactiveCssState(0.28, 0.14, [112, 122, 196], "velvet");
			membrane.audioLevel = 0.08;
			membrane.motionSensor = 0.18;
			membrane.shake = 0.12;
			membrane.tiltX = 0.12;
			membrane.tiltY = 0.16;
			syncMembraneReactiveState();
		const voiceReady = await ensureMotionVoice().catch(() => false);
		if (voiceReady) {
			updateMotionVoice();
			cueMotionVoice(1.22);
		}
		setSensorText(cameraNode, "synthèse locale");
		setSensorText(wakeNode, "démo locale");
		pulseDeviceHaptics("soft");
		demoStartedAt = performance.now();

		const renderDemoFrame = (time) => {
			const elapsed = Math.max(0, time - demoStartedAt);
			const progress = (elapsed % 28000) / 28000;
			const seconds = elapsed / 1000;
			const wave = (Math.sin(seconds * 0.84) + 1) / 2;
			const undertow = (Math.cos(seconds * 0.31 + 1.2) + 1) / 2;
			const shimmer = (Math.sin(seconds * 2.8 + progress * Math.PI * 6) + 1) / 2;
			const lift = Math.pow(Math.sin(progress * Math.PI), 1.28);
			const burst = Math.pow(Math.max(0, Math.sin(progress * Math.PI * 2.1 - 0.65)), 2.2);
			const phaseMeta = demoPhaseMetaAt(progress);
			const phase = phaseMeta.phase;
			const nextPhase = demoPhases[(phaseMeta.index + 1) % demoPhases.length] || phase;
			const blend = clampNumber(phaseMeta.localProgress, 0, 1);
			const baseRgb = mixRgb(demoPhasePalette(phase.key), demoPhasePalette(nextPhase.key), blend * 0.72);
			const accentRgb = mixRgb([255, 255, 255], [78, 232, 255], undertow * 0.52);
			const rgb = mixRgb(baseRgb, accentRgb, 0.12 + shimmer * 0.16);
			const luma = clampNumber(0.14 + progress * 0.32 + wave * 0.14 + undertow * 0.08 + burst * 0.1, 0.1, 0.94);
			const motion = clampNumber(0.06 + lift * 0.34 + shimmer * 0.18 + burst * 0.22, 0.04, 0.98);
			const audio = clampNumber(0.05 + motion * 0.28 + shimmer * 0.18 + burst * 0.16, 0.04, 0.92);
			const lightLevel = clampNumber(luma * 0.76 + undertow * 0.18 + (phase.key === "gloss" ? 0.08 : 0), 0.08, 1);
			const motionSensor = clampNumber(motion * 0.84 + wave * 0.1, 0.04, 1);
			const tiltX = Math.sin(seconds * (0.42 + motion * 0.72)) * (0.08 + motion * 0.54);
			const tiltY = Math.cos(seconds * (0.36 + motion * 0.64) + 0.6) * (0.07 + burst * 0.42 + undertow * 0.16);

				setReactiveCssState(luma, motion, rgb, phase.key);
				membrane.lightLevel = lightLevel;
				membrane.audioLevel = audio;
				membrane.motionSensor = motionSensor;
				membrane.shake = clampNumber((burst * 0.82) + (motion * 0.12), 0, 1);
				membrane.tiltX = tiltX;
				membrane.tiltY = tiltY;
				syncMembraneReactiveState();

			setSensorText(
				orientationNode,
				`α ${Math.round(((tiltX + 1) * 90) + shimmer * 14)}° · β ${Math.round((tiltY * 54) + wave * 8)}° · demo`
			);
			setSensorText(motionNode, `${Math.round(motionSensor * 100)}% · demo`);
				setSensorText(
					lightNode,
					`${Math.round(60 + lightLevel * 860)} lux · ${currentToreMode === "major" ? "majeur" : "mineur"}`
				);
			setSensorText(audioNode, `${Math.round(audio * 100)}% d’amplitude`);
			setSensorText(cameraNode, `${phase.key} synthétique`);

				if (phaseMeta.index !== demoPhaseIndex) {
					demoPhaseIndex = phaseMeta.index;
					setUiState("demo", phase.message, phase.title);
					pulseDeviceHaptics(phase.haptic);
					cueMotionVoice(0.82 + blend * 0.18);
				}
				if (burst > 0.6 && lastDemoShakeBurst <= 0.6) {
					void triggerShaker(0.42 + (burst * 0.34));
				}
				lastDemoShakeBurst = burst;

				demoFrame = window.requestAnimationFrame(renderDemoFrame);
			};

		demoFrame = window.requestAnimationFrame(renderDemoFrame);
	};

	const requestWakeLock = async () => {
		if (!("wakeLock" in navigator) || typeof navigator.wakeLock?.request !== "function") {
			setSensorText(wakeNode, "indisponible");
			return false;
		}

		try {
			wakeLock = await navigator.wakeLock.request("screen");
			wakeLock.addEventListener("release", () => {
				if (isMembraneLive()) {
					setSensorText(wakeNode, "relâchée");
				}
			});
			setSensorText(wakeNode, "active");
			return true;
		} catch {
			setSensorText(wakeNode, "refusée");
			return false;
		}
	};

	const releaseWakeLock = async () => {
		if (!wakeLock) {
			return;
		}
		try {
			await wakeLock.release();
		} catch {
			// Ignore release failures.
		}
		wakeLock = null;
	};

	const ensureAmbientLightSensor = () => {
		if (lightSensor || !("AmbientLightSensor" in window)) {
			if (!("AmbientLightSensor" in window)) {
				setSensorText(lightNode, "fallback Ocam");
			}
			return false;
		}

		try {
			lightSensor = new window.AmbientLightSensor();
				lightSensor.addEventListener("reading", () => {
					if (!isMembraneLive()) {
						return;
					}
					membrane.lightLevel = clampNumber(Math.log10(Math.max(1, Number(lightSensor.illuminance) || 1)) / 3, 0, 1);
					syncMembraneReactiveState();
					setSensorText(
						lightNode,
						`${Math.round(Number(lightSensor.illuminance) || 0)} lux · ${currentToreMode === "major" ? "majeur" : "mineur"}`
					);
				});
			lightSensor.addEventListener("error", () => {
				if (isMembraneLive()) {
					setSensorText(lightNode, "fallback Ocam");
				}
			});
			lightSensor.start();
			return true;
		} catch {
			setSensorText(lightNode, "fallback Ocam");
			return false;
		}
	};

	const requestMotionPermissions = async () => {
		let orientationReady = false;
		let motionReady = false;

		if ("DeviceOrientationEvent" in window) {
			if (typeof DeviceOrientationEvent.requestPermission === "function") {
				try {
					orientationReady = (await DeviceOrientationEvent.requestPermission()) === "granted";
				} catch {
					orientationReady = false;
				}
			} else {
				orientationReady = true;
			}
		}

		if ("DeviceMotionEvent" in window) {
			if (typeof DeviceMotionEvent.requestPermission === "function") {
				try {
					motionReady = (await DeviceMotionEvent.requestPermission()) === "granted";
				} catch {
					motionReady = false;
				}
			} else {
				motionReady = true;
			}
		}

		if (orientationReady && !orientationBound) {
			window.addEventListener("deviceorientation", (event) => {
				if (!isMembraneLive()) {
					return;
				}
				orientationSignalSeen = true;
				membrane.tiltX = clampNumber((Number(event.gamma) || 0) / 46, -1, 1);
				membrane.tiltY = clampNumber((Number(event.beta) || 0) / 64, -1, 1);
				syncMembraneReactiveState();
				setSensorText(
					orientationNode,
					`α ${Math.round(Number(event.alpha) || 0)}° · β ${Math.round(Number(event.beta) || 0)}°`
				);
			});
			orientationBound = true;
		} else if ("DeviceOrientationEvent" in window) {
			setSensorText(orientationNode, "refusée");
		} else {
			setSensorText(orientationNode, "absente");
		}

		if (motionReady && !motionBound) {
			window.addEventListener("devicemotion", (event) => {
				if (!isMembraneLive()) {
					return;
				}
				motionSignalSeen = true;
				const source = event.accelerationIncludingGravity || event.acceleration || {};
				const x = Number(source.x || 0);
				const y = Number(source.y || 0);
				const z = Number(source.z || 0);
				const magnitude = Math.sqrt(x * x + y * y + z * z);
				const motionDelta = Math.abs(magnitude - lastMotionMagnitude);
				lastMotionMagnitude = magnitude;
				const shakeImpulse = clampNumber((motionDelta - 1.8) / 9.8, 0, 1);
				membrane.motionSensor = clampNumber(magnitude / 24, 0, 1);
				membrane.shake = clampNumber(Math.max(shakeImpulse, membrane.shake * 0.72), 0, 1);
				syncMembraneReactiveState();
				if (shakeImpulse > 0.14) {
					void triggerShaker(0.36 + (shakeImpulse * 0.5));
				}
				setSensorText(
					motionNode,
					shakeImpulse > 0.12
						? `${Math.round(membrane.motionSensor * 100)}% · shaker`
						: `${Math.round(membrane.motionSensor * 100)}%`
				);
			});
			motionBound = true;
		} else if ("DeviceMotionEvent" in window) {
			setSensorText(motionNode, "refusé");
		} else {
			setSensorText(motionNode, "absent");
		}

		if (orientationReady) {
			setSensorText(orientationNode, isAndroidSurface ? "bouge le tel" : "prêt au geste");
		}
		if (motionReady) {
			setSensorText(motionNode, isAndroidSurface ? "bouge le tel" : "prêt au geste");
		}

		return { orientationReady, motionReady };
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
		const isDemo = state === "demo";
		const isLiveLike = state === "live" || state === "partial" || isDemo;
		document.body.classList.toggle("is-camera-ready", state === "live" || isDemo);
		document.body.classList.toggle("is-membrane-live", isLiveLike);
			document.body.classList.toggle("is-membrane-demo", isDemo);
			startButton.classList.toggle("hidden", isLiveLike);
			stopButton.classList.toggle("hidden", !isLiveLike);
			demoButton.setAttribute("aria-pressed", isDemo ? "true" : "false");
			demoButton.textContent = isDemo ? "Quitter Terre & Mine" : "Terre & Mine";
			stopButton.textContent = isDemo ? "Couper Terre & Mine" : "Relâcher la membrane";

		if (statusNode instanceof HTMLElement && message) {
			statusNode.textContent = message;
		}

		if (titleNode instanceof HTMLElement && title) {
			titleNode.textContent = title;
		}
	};

	const stopStream = ({ quiet = false } = {}) => {
		const shouldNotifyBridge = !quiet && isMembraneLive() && !isMembraneDemo();
		const closeMetrics = membraneMetricsSnapshot();
		stopBridgePulse();
		stopDemoMode();
		resetSensorFeedback();
		if (shouldNotifyBridge) {
			pulseDeviceHaptics("soft");
			void sendMembraneBridge("membrane_close", `La membrane se relâche · ${membraneBridgeCopy(closeMetrics)}`, { force: true, keepalive: true });
		}
		stopAnalysis();
		stopAudio();
		stopMediaTracks(stream);
		stopMediaTracks(audioStream);
		stream = null;
		audioStream = null;
		video.srcObject = null;
		if (lightSensor && typeof lightSensor.stop === "function") {
			try {
				lightSensor.stop();
			} catch {
				// Ignore stop failures.
			}
		}
		lightSensor = null;
		void releaseWakeLock();
		releaseDeviceOrientationLock();
		setSensorText(orientationNode, "en attente");
		setSensorText(motionNode, "en attente");
		setSensorText(lightNode, "en attente");
		setSensorText(audioNode, "en attente");
		setSensorText(cameraNode, `en attente · ${cameraFacingLabel()}`);
		setSensorText(wakeNode, "en attente");
		setUiState(
			"idle",
			"La membrane se relâche. Le tore continue de respirer sur une mémoire synthétique, sans écouter le téléphone en direct.",
			"La membrane attend un geste."
		);
		renderWorldInstrument();
	};

	const buildSharedVideoTrackConstraints = () => ({
		facingMode: cameraFacingMode === "environment"
			? { ideal: "environment" }
			: "user",
		width: { ideal: 1280 },
		height: { ideal: 720 },
	});

	const buildFullCaptureConstraints = () => ({
		audio: true,
		video: buildSharedVideoTrackConstraints(),
	});

	const buildVideoConstraints = () => ({
		audio: false,
		video: buildSharedVideoTrackConstraints(),
	});

	const audioConstraints = {
		audio: {
			echoCancellation: true,
			noiseSuppression: true,
			autoGainControl: true,
		},
		video: false,
	};

	const startVideoCapture = async () => {
		try {
			stream = await navigator.mediaDevices.getUserMedia(buildVideoConstraints());
			video.srcObject = stream;
			await video.play().catch(() => undefined);
			stopAnalysis();
			analysisFrame = window.requestAnimationFrame(analyzeFrame);
			setSensorText(cameraNode, `ouverte · ${cameraFacingLabel()}`);
			return true;
		} catch {
			setSensorText(cameraNode, `refusée · ${cameraFacingLabel()}`);
			return false;
		}
	};

	const startCombinedCapture = async () => {
		try {
			stream = await navigator.mediaDevices.getUserMedia(buildFullCaptureConstraints());
			audioStream = stream;
			video.srcObject = stream;
			await video.play().catch(() => undefined);
			const analyserReady = await ensureAudioAnalyser(stream);
			stopAnalysis();
			analysisFrame = window.requestAnimationFrame(analyzeFrame);
			setSensorText(cameraNode, `ouverte · ${cameraFacingLabel()}`);
			setSensorText(audioNode, analyserReady ? "ouvert" : "micro ouvert");
			return { videoReady: true, audioReady: true };
		} catch {
			stopMediaTracks(stream);
			stream = null;
			audioStream = null;
			video.srcObject = null;
			return null;
		}
	};

	const startAudioCapture = async ({ optional = false } = {}) => {
		try {
			audioStream = await navigator.mediaDevices.getUserMedia(audioConstraints);
			const analyserReady = await ensureAudioAnalyser(audioStream);
			setSensorText(audioNode, analyserReady ? "ouvert" : "micro ouvert");
			return true;
		} catch {
			setSensorText(audioNode, optional ? "refusé · optionnel" : "refusé");
			return false;
		}
	};

	const startLiveMembrane = async ({ restarting = false } = {}) => {
		if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== "function") {
			setUiState(
				"unsupported",
				"La membrane ne trouve pas de caméra ou de micro disponibles ici. Le tore garde donc un monde synthétique comme pulpe de secours.",
				"Membrane incomplète sur cette surface."
			);
			setSensorText(cameraNode, "indisponible");
			setSensorText(audioNode, "indisponible");
			return;
		}

		setUiState(
			"loading",
			isSpatialHeadsetSurface
				? (cameraFacingMode === "environment"
					? "La couche spatiale ouvre le paysage d abord. Le but est de laisser marche, reflets, voix et présence nourrir le tore sans casser la lecture du casque."
					: "La couche spatiale ouvre d abord visage, voix et présence. Le but est de stabiliser la lecture avant la future passe native.")
				: (isAndroidSurface
				? (cameraFacingMode === "environment"
					? "Sur Android, la membrane ouvre d abord le paysage, puis tente micro et capteurs sans bloquer toute la surface si un flux manque."
					: "Sur Android, la membrane ouvre d’abord le visage, puis tente le micro et les capteurs sans bloquer toute la surface si un flux manque.")
				: (cameraFacingMode === "environment"
					? "Le tore demande au téléphone paysage, lumière, souffle, mouvement et présence. Le dehors doit pouvoir jouer comme un instrument entier."
					: "Le tore demande au téléphone lumière, souffle, mouvement et présence. La réaction sonore, les modes majeur/mineur et la voix accordée restent locales à ce navigateur.")),
			restarting
				? (cameraFacingMode === "environment"
					? "Le monde se retourne vers le paysage…"
					: "Le monde revient vers le visage…")
				: "Activation de la membrane…"
		);
		setSensorText(orientationNode, "demande");
		setSensorText(motionNode, "demande");
		setSensorText(lightNode, "demande");
		setSensorText(audioNode, "demande");
		setSensorText(cameraNode, "demande");
		setSensorText(wakeNode, "demande");

		const { orientationReady, motionReady } = await requestMotionPermissions();
		const sensorReady = orientationReady || motionReady;
		const lightReady = ensureAmbientLightSensor();
		const wakeReady = await requestWakeLock();
		await ensureMotionVoice().catch(() => false);
		await requestDeviceOrientationLock();
		queueSensorFeedback({ orientationReady, motionReady });

		let videoReady = false;
		let audioReady = false;
		const combinedCapture = isAndroidSurface ? null : await startCombinedCapture();
		if (combinedCapture) {
			videoReady = combinedCapture.videoReady;
			audioReady = combinedCapture.audioReady;
		} else {
			videoReady = await startVideoCapture();
			audioReady = await startAudioCapture({ optional: videoReady || sensorReady || lightReady || wakeReady });
		}

		if (!lightReady) {
			setSensorText(lightNode, "fallback Ocam");
		}
		if (!sensorReady && !("DeviceMotionEvent" in window) && !("DeviceOrientationEvent" in window)) {
			setSensorText(orientationNode, "absente");
			setSensorText(motionNode, "absent");
		}

		if (videoReady) {
			setUiState(
				"live",
				isSpatialHeadsetSurface
					? (cameraFacingMode === "environment"
						? (audioReady
							? "La couche spatiale parcourt maintenant le dehors. Marche, reflets, perspective, souffle et gestes nourrissent le tore comme une peau musicale du paysage."
							: "La couche spatiale ouvre déjà le paysage. Même sans micro, dehors, lumière, marche et cadrage font respirer le tore.")
						: (audioReady
							? "La couche spatiale est ouverte. Visage, voix, mains et présence locale modulent déjà le tore en lecture stable pour le casque."
							: "La couche spatiale voit déjà ton visage et la présence proche. La voix et les capteurs plus fins pourront venir ensuite sans casser la lecture."))
					: (cameraFacingMode === "environment"
						? (audioReady
							? "La membrane parcourt maintenant le dehors. Marche, reflets, perspective, souffle et gestes nourrissent le tore comme un instrument de paysage."
							: "La membrane ouvre déjà le paysage. Même sans micro, dehors, lumière, marche et cadrage font respirer le tore.")
						: (audioReady
							? "La membrane est ouverte. Visage, mains, lumière, inclinaison, secousse et souffle jouent maintenant ensemble dans le tore."
							: "La membrane voit déjà la surface. Sur Android, le micro peut rester optionnel: lumière, inclinaison et présence suffisent déjà à faire respirer le tore.")),
				isSpatialHeadsetSurface
					? (cameraFacingMode === "environment"
						? (audioReady
							? "Le paysage joue maintenant avec le tore."
							: "Le paysage tient déjà la surface.")
						: (audioReady
							? "Le visage joue maintenant avec le tore."
							: "Le visage tient déjà la surface."))
					: (cameraFacingMode === "environment"
						? (audioReady
							? "Le paysage joue maintenant avec le tore."
							: "Le paysage tient déjà la surface.")
						: (audioReady
							? "La membrane nourrit maintenant la surface."
							: "La membrane voit déjà la surface."))
			);
			updateMotionVoice();
			cueMotionVoice(audioReady ? 1 : 0.9);
			pulseDeviceHaptics("medium");
			startBridgePulse();
			void sendMembraneBridge(
				"membrane_open",
				`Ouverture membrane xyz · ${membraneBridgeCopy(membraneMetricsSnapshot())}`,
				{ force: true }
			);
			return;
		}

		if (audioReady || sensorReady || lightReady || wakeReady) {
			setUiState(
				"partial",
				isSpatialHeadsetSurface
					? (cameraFacingMode === "environment"
						? (audioReady
							? "Le paysage n est pas encore entièrement visible, mais la voix et la présence locale suffisent déjà pour régler le rythme du tore."
							: "La couche spatiale reste partielle ici. On garde tout de même une lecture stable du paysage sans promettre encore le vrai volume natif.")
						: (audioReady
							? "La couche spatiale n a pas encore toute l image, mais la voix et la présence locale suffisent déjà pour régler le rythme du tore."
							: "La couche spatiale reste partielle ici. On garde une lecture stable sans promettre encore le vrai volume natif."))
					: (cameraFacingMode === "environment"
						? (audioReady
							? "La membrane ne tient pas encore toute l image du dehors, mais le tore écoute déjà souffle, lumière, marche ou veille et peut rester vivant."
							: "La membrane ne capte pas encore tout le paysage, mais elle lit déjà mouvement, lumière ou présence de veille et peut déjà faire jouer le tore.")
						: (audioReady
							? "La membrane n’a pas encore d’image, mais le tore écoute déjà souffle, mouvement, lumière ou veille et peut rester vivant sur Android."
							: "La membrane ne capte pas encore toute l’image ou tout le souffle, mais elle lit déjà mouvement, lumière ou présence de veille et peut déjà faire jouer le thérémin du tore.")),
				isSpatialHeadsetSurface
					? (cameraFacingMode === "environment"
						? (audioReady
							? "Le paysage chante sans image complète."
							: "Le paysage dérive en mode partiel.")
						: (audioReady
							? "La couche spatiale écoute sans image complète."
							: "La couche spatiale dérive en mode partiel."))
					: (cameraFacingMode === "environment"
						? (audioReady
							? "Le paysage chante sans image complète."
							: "Le paysage dérive en mode partiel.")
						: (audioReady
							? "La membrane écoute sans image."
							: "La membrane dérive en mode partiel."))
			);
			updateMotionVoice();
			cueMotionVoice(audioReady ? 0.94 : 0.82);
			pulseDeviceHaptics("soft");
			startBridgePulse();
			void sendMembraneBridge(
				"membrane_partial",
				`Membrane partielle xyz · ${membraneBridgeCopy(membraneMetricsSnapshot())}`,
				{ force: true }
			);
			return;
		}

		stopStream({ quiet: true });
		setSensorText(cameraNode, `refusée · ${cameraFacingLabel()}`);
		setSensorText(audioNode, "refusé");
		if (!sensorReady) {
			setSensorText(orientationNode, "refusée ou absente");
			setSensorText(motionNode, "refusé ou absent");
		}
		setUiState(
			"error",
			isSpatialHeadsetSurface
				? "Cette surface n a encore ouvert ni voix, ni image, ni présence exploitable. Le tore revient donc à une lecture locale plus simple en attendant le client natif."
				: (isAndroidSurface
				? "Android n’a encore laissé passer ni caméra, ni micro, ni capteur. La membrane revient à son rêve interne jusqu’à une nouvelle autorisation."
				: "Permission refusée ou capteur inaccessible. La membrane revient à son rêve interne, sans captation directe."),
			isSpatialHeadsetSurface ? "La surface spatiale reste en preview." : "La surface garde son rêve sans membrane."
		);
	};

	const switchCameraFacingMode = async (nextMode) => {
		if ((nextMode !== "environment" && nextMode !== "user") || nextMode === cameraFacingMode) {
			return;
		}

		const shouldRestart = isMembraneLive() && !isMembraneDemo();
		cameraFacingMode = nextMode;
		writeStoredCameraFacing(nextMode);
		renderWorldInstrument();
		setSensorText(cameraNode, shouldRestart ? `rotation · ${cameraFacingLabel()}` : `en attente · ${cameraFacingLabel()}`);
		pulseDeviceHaptics("soft");

		if (shouldRestart) {
			stopStream({ quiet: true });
			await startLiveMembrane({ restarting: true });
		}
	};

	startButton.addEventListener("click", async () => {
		await startLiveMembrane();
	});

	cameraFacingButtons.forEach((button) => {
		if (!(button instanceof HTMLElement)) {
			return;
		}

		button.addEventListener("click", () => {
			const nextMode = button.dataset.xyzCameraFacingButton || "user";
			void switchCameraFacingMode(nextMode);
		});
	});

	if (instrumentStage instanceof HTMLElement) {
		const handleInstrumentPointerMove = (event) => {
			if (!instrument.pointers.has(event.pointerId)) {
				return;
			}
			registerInstrumentPointer(event);
		};
		const handleInstrumentPointerRelease = (event) => {
			if (instrumentStage.hasPointerCapture?.(event.pointerId)) {
				try {
					instrumentStage.releasePointerCapture(event.pointerId);
				} catch {
					// Ignore capture release failures.
				}
			}
			releaseInstrumentPointer(event.pointerId);
		};

		instrumentStage.addEventListener("pointerdown", (event) => {
			instrumentStage.focus({ preventScroll: true });
			if (typeof instrumentStage.setPointerCapture === "function") {
				try {
					instrumentStage.setPointerCapture(event.pointerId);
				} catch {
					// Ignore pointer capture failures.
				}
			}
			registerInstrumentPointer(event);
			event.preventDefault();
		});
		instrumentStage.addEventListener("pointermove", handleInstrumentPointerMove);
		instrumentStage.addEventListener("pointerup", handleInstrumentPointerRelease);
		instrumentStage.addEventListener("pointercancel", handleInstrumentPointerRelease);
		instrumentStage.addEventListener("pointerleave", (event) => {
			if (event.pointerType !== "mouse" || instrumentStage.hasPointerCapture?.(event.pointerId)) {
				return;
			}
			releaseInstrumentPointer(event.pointerId);
		});
		instrumentStage.addEventListener("keydown", (event) => {
			if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey) {
				return;
			}

			const key = (event.key || "").toLowerCase();
			const step = event.shiftKey ? 0.08 : 0.045;
			let handled = true;
			switch (key) {
				case "w":
					pulseInstrumentKeyboard("terre", 0, -step);
					break;
				case "s":
					pulseInstrumentKeyboard("terre", 0, step);
					break;
				case "a":
					pulseInstrumentKeyboard("terre", -step, 0);
					break;
				case "d":
					pulseInstrumentKeyboard("terre", step, 0);
					break;
				case "arrowup":
					pulseInstrumentKeyboard("mine", 0, -step);
					break;
				case "arrowdown":
					pulseInstrumentKeyboard("mine", 0, step);
					break;
				case "arrowleft":
					pulseInstrumentKeyboard("mine", -step, 0);
					break;
				case "arrowright":
					pulseInstrumentKeyboard("mine", step, 0);
					break;
				case " ":
				case "spacebar":
					pulseInstrumentKeyboard("mine", 0, 0);
					void triggerShaker(0.58);
					break;
				case "f":
				case "v":
					void switchCameraFacingMode(cameraFacingMode === "environment" ? "user" : "environment");
					break;
				default:
					handled = false;
			}

			if (handled) {
				event.preventDefault();
			}
		});
	}

	stopButton.addEventListener("click", () => {
		stopStream();
	});

	demoButton.addEventListener("click", async () => {
		if (isMembraneDemo()) {
			stopStream();
			return;
		}

		if (stream || isMembraneLive()) {
			stopStream();
		}

		await startDemoMembrane();
	});

	window.addEventListener("beforeunload", () => {
		stopStream();
	});

	window.addEventListener("o:device-bridge-change", () => {
		updateMotionVoice();
	});

	document.addEventListener("visibilitychange", () => {
		updateMotionVoice(document.hidden);
	});

	setSensorText(orientationNode, isSpatialHeadsetSurface ? "geste a venir" : "prête");
	setSensorText(motionNode, isSpatialHeadsetSurface ? "presence a venir" : "prêt");
	setSensorText(lightNode, "capteur ou Ocam");
	setSensorText(audioNode, isSpatialHeadsetSurface ? "voix locale" : "prêt");
	setSensorText(cameraNode, `${isSpatialHeadsetSurface ? "preview locale" : "prête"} · ${cameraFacingLabel()}`);
	setSensorText(wakeNode, "sur demande");
	resetMembraneReactiveState();

	try {
		const params = new URL(window.location.href).searchParams;
		if (params.get("demo") === "1") {
			void startDemoMembrane();
		}
	} catch {
		// Ignore malformed runtime URLs.
	}
}

function initLabConsole() {
	const root = document.querySelector("[data-lab-console]");
	if (!(root instanceof HTMLElement)) {
		return;
	}

	const activateButton = root.querySelector("[data-lab-activate]");
	const replayButton = root.querySelector("[data-lab-replay]");
	const activationStatus = root.querySelector("[data-lab-activation-status]");
	const sensorBadge = root.querySelector("[data-lab-sensor-badge]");
	const orientationStatus = root.querySelector("[data-lab-orientation-status]");
	const motionStatus = root.querySelector("[data-lab-motion-status]");
	const lightStatus = root.querySelector("[data-lab-light-status]");
	const audioStatus = root.querySelector("[data-lab-audio-status]");
	const cameraStatus = root.querySelector("[data-lab-camera-status]");
	const wakeStatus = root.querySelector("[data-lab-wake-status]");
	const cameraPreview = root.querySelector("[data-lab-camera-preview]");
	const cameraFallback = root.querySelector("[data-lab-camera-fallback]");
	const pocketStatus = root.querySelector("[data-lab-pocket-status]");
	const pocketNote = root.querySelector("[data-lab-pocket-note]");
	const apiStatus = root.querySelector("[data-lab-api-status]");
	const plasmaStatus = root.querySelector("[data-lab-plasma-status]");
	const plasmaBadge = root.querySelector("[data-lab-plasma-badge]");
	const plasmaWeatherCopy = root.querySelector("[data-lab-plasma-weather-copy]");
	const runtimeTraceList = root.querySelector("[data-lab-runtime-traces]");
	const deliveryStatus = root.querySelector("[data-lab-delivery-status]");
	const sessionTraceList = root.querySelector("[data-lab-session-traces]");

	if (!(activateButton instanceof HTMLElement) || !(replayButton instanceof HTMLElement)) {
		return;
	}

	const isAndroidSurface = /\bAndroid\b/i.test(window.navigator?.userAgent || "");

	const cardByName = {
		sensor: root.querySelector('[data-lab-card="sensor"]'),
		pocket: root.querySelector('[data-lab-card="pocket"]'),
		api: root.querySelector('[data-lab-card="api"]'),
		plasma: root.querySelector('[data-lab-card="plasma"]'),
		delivery: root.querySelector('[data-lab-card="delivery"]'),
	};

	const state = {
		orientationBound: false,
		motionBound: false,
		replayTimer: 0,
		replayTick: 0,
		cameraFrame: 0,
		audioFrame: 0,
		lastMotionTraceAt: 0,
		lastAudioTraceAt: 0,
		lastCameraTraceAt: 0,
		lastPocketState: "idle",
		wakeLock: null,
		lightSensor: null,
		lightSensorLive: false,
		cameraFallbackLight: false,
		stream: null,
		audioStream: null,
		audioContext: null,
		audioAnalyser: null,
		plasmaPollTimer: 0,
		sensorFeedbackTimer: 0,
		orientationSeen: false,
		motionSeen: false,
	};

	const cameraCanvas = document.createElement("canvas");
	cameraCanvas.width = 36;
	cameraCanvas.height = 24;
	const cameraContext = cameraCanvas.getContext("2d", { willReadFrequently: true });

	const setText = (node, text) => {
		if (node instanceof HTMLElement) {
			node.textContent = text;
		}
	};

	const stopMediaTracks = (mediaStream) => {
		if (!mediaStream || typeof mediaStream.getTracks !== "function") {
			return;
		}

		mediaStream.getTracks().forEach((track) => track.stop());
	};

	const resetSensorFeedback = () => {
		if (state.sensorFeedbackTimer) {
			window.clearTimeout(state.sensorFeedbackTimer);
			state.sensorFeedbackTimer = 0;
		}
		state.orientationSeen = false;
		state.motionSeen = false;
	};

	const queueSensorFeedback = ({ orientationReady = false, motionReady = false } = {}) => {
		resetSensorFeedback();
		if (!orientationReady && !motionReady) {
			return;
		}

		state.sensorFeedbackTimer = window.setTimeout(() => {
			if (root.dataset.labReplay === "1") {
				return;
			}

			if (orientationReady && !state.orientationSeen) {
				setText(orientationStatus, isAndroidSurface ? "bouge ou autorise" : "en attente du geste");
			}

			if (motionReady && !state.motionSeen) {
				setText(motionStatus, isAndroidSurface ? "bouge ou autorise" : "en attente du mouvement");
			}
		}, isAndroidSurface ? 1800 : 2400);
	};

	const setCardState = (name, nextState) => {
		const card = cardByName[name];
		if (!(card instanceof HTMLElement)) {
			return;
		}

		card.dataset.labState = nextState;
	};

	const appendSessionTrace = (eventName, sourceLabel, message) => {
		if (!(sessionTraceList instanceof HTMLOListElement)) {
			return;
		}

		const item = document.createElement("li");
		const label = document.createElement("span");
		const strong = document.createElement("strong");
		const copy = document.createElement("span");

		label.className = "summary-label";
		label.textContent = eventName;
		strong.textContent = sourceLabel;
		copy.textContent = message;

		item.append(label, strong, copy);
		sessionTraceList.prepend(item);
		sessionTraceList.hidden = false;

		while (sessionTraceList.children.length > 4) {
			sessionTraceList.removeChild(sessionTraceList.lastElementChild);
		}
	};

	const renderRuntimeTraces = (events) => {
		if (!(runtimeTraceList instanceof HTMLOListElement)) {
			return;
		}

		runtimeTraceList.innerHTML = "";
		if (!Array.isArray(events) || events.length === 0) {
			const item = document.createElement("li");
			const label = document.createElement("span");
			const strong = document.createElement("strong");
			const copy = document.createElement("span");
			label.className = "summary-label";
			label.textContent = "veille";
			strong.textContent = "runtime";
			copy.textContent = "Le premier ping capteur apparaîtra ici dès qu’un événement traversera le pont plasma.";
			item.append(label, strong, copy);
			runtimeTraceList.append(item);
			return;
		}

		events.slice(0, 6).forEach((event) => {
			const item = document.createElement("li");
			const label = document.createElement("span");
			const strong = document.createElement("strong");
			const copy = document.createElement("span");
			label.className = "summary-label";
			label.textContent = event && typeof event.event === "string" && event.event ? event.event : "signal";
			strong.textContent = event && typeof event.source === "string" && event.source
				? event.source
				: (event && typeof event.camera === "string" && event.camera ? event.camera : "runtime");
			copy.textContent = event && typeof event.message === "string" && event.message
				? event.message
				: (event && typeof event.timestamp === "string" && event.timestamp ? event.timestamp : "trace sans message");
			item.append(label, strong, copy);
			runtimeTraceList.append(item);
		});
	};

	const applyPlasmaWeather = (weather, events) => {
		if (weather && typeof weather === "object") {
			setText(plasmaStatus, typeof weather.lead === "string" ? weather.lead : "Météo plasma reçue.");
			setText(plasmaBadge, typeof weather.badge === "string" ? weather.badge : "plasma");
			setText(plasmaWeatherCopy, typeof weather.detail === "string" ? weather.detail : "");
			if (typeof weather.tone === "string" && weather.tone) {
				setCardState("plasma", weather.tone);
			}
		}

		renderRuntimeTraces(Array.isArray(events) ? events : []);
	};

	const setActivationCopy = (text) => {
		setText(activationStatus, text);
	};

	const updatePocketState = (statusText, noteText, tone = "idle") => {
		setText(pocketStatus, statusText);
		setText(pocketNote, noteText);
		setCardState("pocket", tone);
		state.lastPocketState = tone;
	};

	const updateDeliveryState = (text, tone = "idle") => {
		setText(deliveryStatus, text);
		setCardState("delivery", tone);
	};

	const emitTrace = (eventName, sourceLabel, message, { channel = "plasma" } = {}) => {
		setText(plasmaStatus, message);
		setCardState("plasma", "live");
		appendSessionTrace(eventName, sourceLabel, message);
		if (channel === "delivery") {
			updateDeliveryState(message, "live");
		}
	};

	const stopCameraLoop = () => {
		if (state.cameraFrame) {
			window.cancelAnimationFrame(state.cameraFrame);
			state.cameraFrame = 0;
		}
	};

	const stopAudioLoop = () => {
		if (state.audioFrame) {
			window.cancelAnimationFrame(state.audioFrame);
			state.audioFrame = 0;
		}
	};

	const releaseWakeLock = async () => {
		if (!state.wakeLock) {
			return;
		}
		try {
			await state.wakeLock.release();
		} catch {
			// Ignore release errors on unload.
		}
		state.wakeLock = null;
	};

	const describeOrientation = (alpha, beta, gamma) => {
		const safeAlpha = Number.isFinite(alpha) ? Math.round(alpha) : 0;
		const safeBeta = Number.isFinite(beta) ? Math.round(beta) : 0;
		const safeGamma = Number.isFinite(gamma) ? Math.round(gamma) : 0;
		return `α ${safeAlpha}° · β ${safeBeta}° · γ ${safeGamma}°`;
	};

	const setSensorBadge = (text) => {
		setText(sensorBadge, text);
	};

	const updateOrientation = (alpha, beta, gamma, { source = "live" } = {}) => {
		setText(orientationStatus, describeOrientation(alpha, beta, gamma));
		setCardState("sensor", "live");
		if (source === "replay") {
			return;
		}
	};

	const updateMotion = (intensity, detail = "", { source = "live" } = {}) => {
		const percent = Math.round(clampNumber(intensity, 0, 1) * 100);
		setText(motionStatus, detail ? `${percent}% · ${detail}` : `${percent}%`);
		setCardState("sensor", percent > 4 ? "live" : "idle");
		if (source === "live" && percent >= 36 && Date.now() - state.lastMotionTraceAt > 2600) {
			state.lastMotionTraceAt = Date.now();
			emitTrace("motion", "accelerometer", `Secousse lisible dans le tore · ${percent}% d’intensité.`);
		}
	};

	const updateLight = (text, { tone = "live" } = {}) => {
		setText(lightStatus, text);
		if (tone) {
			setCardState("sensor", tone);
		}
	};

	const updateAudio = (level, { source = "live" } = {}) => {
		const percent = Math.round(clampNumber(level, 0, 1) * 100);
		setText(audioStatus, percent > 0 ? `${percent}% d’amplitude` : "souffle bas");
		if (source === "live" && percent >= 22 && Date.now() - state.lastAudioTraceAt > 3200) {
			state.lastAudioTraceAt = Date.now();
			emitTrace("voice", "micro", `Le micro pousse une houle courte · ${percent}% d’amplitude.`);
		}
	};

	const updateCamera = (luma, rgb, { source = "live" } = {}) => {
		const safeLuma = Math.round(clampNumber(luma, 0, 1) * 100);
		const safeRgb = Array.isArray(rgb) && rgb.length >= 3
			? rgb.map((value) => clampNumber(Math.round(Number(value) || 0), 0, 255))
			: [180, 180, 180];
		setText(cameraStatus, `luma ${safeLuma}% · rgb ${safeRgb.join("/")}`);
		if (!state.lightSensorLive) {
			updateLight(`fallback Ocam · ${safeLuma}%`, { tone: "live" });
			state.cameraFallbackLight = true;
		}
		if (source === "live" && safeLuma >= 68 && Date.now() - state.lastCameraTraceAt > 3600) {
			state.lastCameraTraceAt = Date.now();
			emitTrace("light", "camera", `La peau caméra remonte une clarté nette · ${safeLuma}% de luma.`);
		}
	};

	const analyzeCameraFrame = () => {
		if (!(cameraPreview instanceof HTMLVideoElement) || !state.stream || !cameraContext) {
			return;
		}

		state.cameraFrame = window.requestAnimationFrame(analyzeCameraFrame);
		if (root.dataset.labReplay === "1") {
			return;
		}

		if (cameraPreview.readyState < HTMLMediaElement.HAVE_CURRENT_DATA || cameraPreview.videoWidth === 0 || cameraPreview.videoHeight === 0) {
			return;
		}

		cameraContext.drawImage(cameraPreview, 0, 0, cameraCanvas.width, cameraCanvas.height);
		const imageData = cameraContext.getImageData(0, 0, cameraCanvas.width, cameraCanvas.height);
		const sampleCount = cameraCanvas.width * cameraCanvas.height;
		let totalLuma = 0;
		let totalR = 0;
		let totalG = 0;
		let totalB = 0;

		for (let offset = 0; offset < imageData.data.length; offset += 4) {
			const red = imageData.data[offset];
			const green = imageData.data[offset + 1];
			const blue = imageData.data[offset + 2];
			totalR += red;
			totalG += green;
			totalB += blue;
			totalLuma += (red * 0.299 + green * 0.587 + blue * 0.114) / 255;
		}

		updateCamera(
			totalLuma / sampleCount,
			[
				Math.round(totalR / sampleCount),
				Math.round(totalG / sampleCount),
				Math.round(totalB / sampleCount),
			]
		);
	};

	const analyzeAudioFrame = () => {
		if (!state.audioAnalyser) {
			return;
		}

		state.audioFrame = window.requestAnimationFrame(analyzeAudioFrame);
		if (root.dataset.labReplay === "1") {
			return;
		}

		const buffer = new Uint8Array(state.audioAnalyser.fftSize);
		state.audioAnalyser.getByteTimeDomainData(buffer);
		let energy = 0;
		for (let index = 0; index < buffer.length; index += 1) {
			const sample = (buffer[index] - 128) / 128;
			energy += sample * sample;
		}

		updateAudio(Math.sqrt(energy / buffer.length) * 2.8);
	};

	const ensureAudioAnalyser = async (stream) => {
		const AudioContextClass = window.AudioContext || window.webkitAudioContext;
		if (!AudioContextClass) {
			setText(audioStatus, "natif uniquement");
			return false;
		}

		if (!state.audioContext) {
			state.audioContext = new AudioContextClass();
		}

		if (state.audioContext.state === "suspended") {
			await state.audioContext.resume().catch(() => {});
		}

		const source = state.audioContext.createMediaStreamSource(stream);
		const analyser = state.audioContext.createAnalyser();
		analyser.fftSize = 512;
		source.connect(analyser);
		state.audioAnalyser = analyser;
		stopAudioLoop();
		state.audioFrame = window.requestAnimationFrame(analyzeAudioFrame);
		return true;
	};

	const startMediaCapture = async () => {
		if (state.stream || state.audioStream) {
			return {
				videoReady: Boolean(state.stream),
				audioReady: Boolean(state.audioStream),
			};
		}

		if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== "function") {
			setText(cameraStatus, "API absente");
			setText(audioStatus, "API absente");
			return { videoReady: false, audioReady: false };
		}

		let videoReady = false;
		let audioReady = false;
		const sharedVideoTrackConstraints = {
			facingMode: coarsePointer ? { ideal: "environment" } : "user",
			width: { ideal: 1280 },
			height: { ideal: 720 },
		};

		if (!isAndroidSurface) {
			try {
				state.stream = await navigator.mediaDevices.getUserMedia({
					audio: true,
					video: sharedVideoTrackConstraints,
				});
				state.audioStream = state.stream;
				if (cameraPreview instanceof HTMLVideoElement) {
					cameraPreview.srcObject = state.stream;
					await cameraPreview.play().catch(() => undefined);
				}
				if (cameraFallback instanceof HTMLElement) {
					cameraFallback.hidden = true;
				}
				const analyserReady = await ensureAudioAnalyser(state.stream);
				stopCameraLoop();
				state.cameraFrame = window.requestAnimationFrame(analyzeCameraFrame);
				setText(cameraStatus, "ouverte");
				setText(audioStatus, analyserReady ? "ouvert" : "micro ouvert");
				videoReady = true;
				audioReady = true;
			} catch {
				stopMediaTracks(state.stream);
				state.stream = null;
				state.audioStream = null;
			}
		}

		if (!videoReady && !audioReady) {
			try {
				state.stream = await navigator.mediaDevices.getUserMedia({
					audio: false,
					video: sharedVideoTrackConstraints,
				});
				if (cameraPreview instanceof HTMLVideoElement) {
					cameraPreview.srcObject = state.stream;
					await cameraPreview.play().catch(() => undefined);
				}
				if (cameraFallback instanceof HTMLElement) {
					cameraFallback.hidden = true;
				}
				stopCameraLoop();
				state.cameraFrame = window.requestAnimationFrame(analyzeCameraFrame);
				setText(cameraStatus, "ouverte");
				videoReady = true;
			} catch {
				setText(cameraStatus, "permission refusée");
			}

			try {
				state.audioStream = await navigator.mediaDevices.getUserMedia({
					audio: {
						echoCancellation: true,
						noiseSuppression: true,
						autoGainControl: true,
					},
					video: false,
				});
				const analyserReady = await ensureAudioAnalyser(state.audioStream);
				setText(audioStatus, analyserReady ? "ouvert" : "micro ouvert");
				audioReady = true;
			} catch {
				setText(audioStatus, videoReady ? "refusé · optionnel" : "permission refusée");
			}
		}

		if (videoReady || audioReady) {
			setCardState("sensor", "live");
		}

		return { videoReady, audioReady };
	};

	const bindOrientationListeners = () => {
		if (state.orientationBound || !("DeviceOrientationEvent" in window)) {
			return false;
		}

		window.addEventListener("deviceorientation", (event) => {
			if (root.dataset.labReplay === "1") {
				return;
			}
			state.orientationSeen = true;
			updateOrientation(Number(event.alpha), Number(event.beta), Number(event.gamma));
		});
		state.orientationBound = true;
		return true;
	};

	const bindMotionListeners = () => {
		if (state.motionBound || !("DeviceMotionEvent" in window)) {
			return false;
		}

		window.addEventListener("devicemotion", (event) => {
			if (root.dataset.labReplay === "1") {
				return;
			}
			state.motionSeen = true;
			const source = event.accelerationIncludingGravity || event.acceleration || {};
			const x = Number(source.x || 0);
			const y = Number(source.y || 0);
			const z = Number(source.z || 0);
			const magnitude = Math.sqrt(x * x + y * y + z * z);
			updateMotion(clampNumber(magnitude / 22, 0, 1), `${x.toFixed(1)} ${y.toFixed(1)} ${z.toFixed(1)}`);
		});
		state.motionBound = true;
		return true;
	};

	const requestSensorPermissions = async () => {
		let orientationReady = false;
		let motionReady = false;

		if ("DeviceOrientationEvent" in window) {
			if (typeof DeviceOrientationEvent.requestPermission === "function") {
				try {
					orientationReady = (await DeviceOrientationEvent.requestPermission()) === "granted";
				} catch {
					orientationReady = false;
				}
			} else {
				orientationReady = true;
			}
		}

		if ("DeviceMotionEvent" in window) {
			if (typeof DeviceMotionEvent.requestPermission === "function") {
				try {
					motionReady = (await DeviceMotionEvent.requestPermission()) === "granted";
				} catch {
					motionReady = false;
				}
			} else {
				motionReady = true;
			}
		}

		if (orientationReady) {
			bindOrientationListeners();
		} else if ("DeviceOrientationEvent" in window) {
			setText(orientationStatus, "refusée ou absente");
		} else {
			setText(orientationStatus, "absente");
		}

		if (motionReady) {
			bindMotionListeners();
		} else if ("DeviceMotionEvent" in window) {
			setText(motionStatus, "refusé ou absent");
		} else {
			setText(motionStatus, "absent");
		}

		if (orientationReady) {
			setText(orientationStatus, isAndroidSurface ? "bouge le tel" : "prêt au geste");
		}
		if (motionReady) {
			setText(motionStatus, isAndroidSurface ? "bouge le tel" : "prêt au geste");
		}

		return { orientationReady, motionReady };
	};

	const requestWakeLock = async () => {
		if (state.wakeLock) {
			setText(wakeStatus, "actif");
			return true;
		}

		if (!("wakeLock" in navigator) || typeof navigator.wakeLock?.request !== "function") {
			setText(wakeStatus, "indisponible");
			return false;
		}

		try {
			state.wakeLock = await navigator.wakeLock.request("screen");
			state.wakeLock.addEventListener("release", () => {
				if (root.dataset.labReplay !== "1") {
					setText(wakeStatus, "relâché");
				}
			});
			setText(wakeStatus, "actif");
			return true;
		} catch {
			setText(wakeStatus, "refusé");
			return false;
		}
	};

	const startAmbientLightSensor = async () => {
		if (state.lightSensor) {
			return true;
		}

		if (!("AmbientLightSensor" in window)) {
			setText(lightStatus, "fallback Ocam");
			state.lightSensorLive = false;
			return false;
		}

		try {
			state.lightSensor = new window.AmbientLightSensor();
			state.lightSensor.addEventListener("reading", () => {
				if (root.dataset.labReplay === "1") {
					return;
				}
				state.lightSensorLive = true;
				state.cameraFallbackLight = false;
				updateLight(`${Math.round(Number(state.lightSensor.illuminance) || 0)} lux`);
			});
			state.lightSensor.addEventListener("error", () => {
				state.lightSensorLive = false;
				updateLight("fallback Ocam");
			});
			state.lightSensor.start();
			return true;
		} catch {
			state.lightSensorLive = false;
			updateLight("fallback Ocam");
			return false;
		}
	};

	const stopReplay = ({ preserveStatus = false } = {}) => {
		if (state.replayTimer) {
			window.clearInterval(state.replayTimer);
			state.replayTimer = 0;
		}
		root.dataset.labReplay = "0";
		replayButton.textContent = "Mode replay";
		if (!preserveStatus) {
			setActivationCopy("Le replay est arrêté. Tu peux repartir sur les capteurs réels.");
		}
		if (state.lastPocketState === "replay") {
			updatePocketState(
				"En veille douce. Le replay peut le faire dériver.",
				"Ouvre la route pocket, ou laisse le mode replay alterner sommeil, roaming et retour.",
				"idle"
			);
		}
	};

	const startReplay = () => {
		stopReplay({ preserveStatus: true });
		root.dataset.labReplay = "1";
		replayButton.textContent = "Arrêter replay";
		setActivationCopy("Le replay fait dériver pocket, lumière, voix et mouvement sans attendre un téléphone complet.");
		pulseDeviceHaptics("soft");
		setSensorBadge("replay");
		setCardState("sensor", "replay");
		setCardState("pocket", "replay");
		setCardState("delivery", "replay");
		state.lastPocketState = "replay";
		state.replayTick = 0;

		state.replayTimer = window.setInterval(() => {
			state.replayTick += 1;
			const phase = state.replayTick;
			const wave = (Math.sin(phase / 1.7) + 1) / 2;
			const drift = (Math.cos(phase / 2.4) + 1) / 2;
			const pocketState = phase % 6 < 2 ? "présent" : (phase % 6 < 4 ? "roaming" : "endormi");
			const pocketCopy = pocketState === "présent"
				? "Pocket revient dans le champ et rouvre une dérive courte."
				: (pocketState === "roaming"
					? "Pocket circule encore. Le réseau retient une présence basse mais continue."
					: "Pocket dort. La livraison glisse vers le différé.");

			updateOrientation(wave * 180, drift * 42 - 21, wave * 28 - 14, { source: "replay" });
			updateMotion(0.22 + wave * 0.58, pocketState, { source: "replay" });
			updateLight(`${Math.round(40 + wave * 520)} lux · replay`, { tone: "replay" });
			updateAudio(0.08 + drift * 0.42, { source: "replay" });
			updateCamera(0.18 + wave * 0.62, [84 + wave * 120, 110 + drift * 70, 156 + wave * 56], { source: "replay" });
			updatePocketState(`${pocketState}.`, pocketCopy, "replay");
			updateDeliveryState(
				pocketState === "endormi"
					? "Le tore garde un paquet en suspens jusqu’au retour du pocket."
					: "Le différé se relâche: le pocket revient assez près pour reprendre le passage.",
				"replay"
			);
			setText(apiStatus, "API rejouée depuis la console.");
			setCardState("api", "replay");
			setText(wakeStatus, "simulé");

				if (phase % 3 === 0) {
					emitTrace(
						pocketState === "endormi" ? "deferred" : "replay",
						"console",
						pocketState === "endormi"
							? "Le replay pousse pocket hors champ: le plasma garde la trace pour plus tard."
							: "Le replay fait revenir pocket: la reprise devient lisible dans le tore.",
						{ channel: pocketState === "endormi" ? "delivery" : "plasma" }
					);
				}
		}, 1100);
	};

	const probeApi = async () => {
		const apiUrl = root.dataset.labApiUrl || "";
		if (!apiUrl) {
			return;
		}

		setText(apiStatus, "Sondage en cours…");
		try {
			const response = await fetch(apiUrl, { cache: "no-store", mode: "cors" });
			if (!response.ok) {
				throw new Error("bad status");
			}

			setText(apiStatus, "API disponible et joignable depuis la console.");
			setCardState("api", "live");
		} catch {
			setText(apiStatus, "API non confirmée depuis ce navigateur.");
			setCardState("api", "warning");
		}
	};

	const pollPlasmaFeed = async () => {
		const feedUrl = root.dataset.labPlasmaFeed || "";
		if (!feedUrl) {
			return;
		}

		try {
			const response = await fetch(`${feedUrl}?limit=6`, { cache: "no-store", mode: "cors" });
			if (!response.ok) {
				throw new Error("bad status");
			}

			const payload = await response.json();
			applyPlasmaWeather(payload && typeof payload.weather === "object" ? payload.weather : null, Array.isArray(payload?.events) ? payload.events : []);
		} catch {
			if (state.plasmaPollTimer === 0) {
				setText(plasmaWeatherCopy, "Le flux public du lab n’a pas encore répondu à cette console.");
			}
		}
	};

	const activateSensors = async () => {
		activateButton.setAttribute("aria-busy", "true");
		activateButton.setAttribute("disabled", "disabled");
		stopReplay({ preserveStatus: true });
		setActivationCopy(isAndroidSurface
			? "Ouverture du champ sensoriel Android… la console tente caméra, micro puis capteurs sans tout bloquer d’un coup."
			: "Ouverture du champ sensoriel…");
		pulseDeviceHaptics("medium");

		const { orientationReady, motionReady } = await requestSensorPermissions();
		queueSensorFeedback({ orientationReady, motionReady });
		const { videoReady, audioReady } = await startMediaCapture();
		const lightReady = await startAmbientLightSensor();
		const wakeReady = await requestWakeLock();
		await requestDeviceOrientationLock();
		const liveCount = [orientationReady, motionReady, videoReady, audioReady, lightReady, wakeReady].filter(Boolean).length;

		if (liveCount > 0) {
			setSensorBadge(`${liveCount} flux actifs`);
			setActivationCopy(videoReady
				? (audioReady
					? "Le lab lit maintenant ce téléphone. Bouge, parle, ouvre la caméra ou laisse le replay prendre la relève."
					: "Le lab voit déjà le téléphone. Sur Android, le micro peut rester optionnel pendant que caméra et capteurs travaillent.")
				: (audioReady
					? "Le lab écoute déjà ce téléphone même sans image. Les capteurs et le replay peuvent continuer la passe."
					: "Le lab n’a pas toute l’image, mais il a ouvert assez de flux pour travailler."));
			setCardState("sensor", "live");
			emitTrace("session", "browser", "Le tore a ouvert une session capteur locale.");
		} else {
			setSensorBadge("fallback");
			setActivationCopy("Aucun flux complet n’a pu s’ouvrir ici. Le mode replay reste disponible pour tester la console.");
			setCardState("sensor", "warning");
		}

		activateButton.removeAttribute("aria-busy");
		activateButton.removeAttribute("disabled");
		activateButton.textContent = "Capteurs actifs";
	};

	if (!(cameraPreview instanceof HTMLVideoElement)) {
		setText(cameraStatus, "aperçu indisponible");
	}

	setText(orientationStatus, "prêt si autorisé");
	setText(motionStatus, "prêt si autorisé");
	setText(lightStatus, "capteur ou fallback");
	setText(audioStatus, "prêt si autorisé");
	setText(cameraStatus, "prête si autorisée");
	setText(wakeStatus, "sur demande");
	updatePocketState(
		"En veille douce. Le replay peut le faire dériver.",
		"Ouvre la route pocket, ou laisse le mode replay alterner sommeil, roaming et retour.",
		"idle"
	);
	updateDeliveryState("Réveil non rejoué. Le tore attend encore une première séquence.", "idle");
	probeApi();
	void pollPlasmaFeed();
	state.plasmaPollTimer = window.setInterval(() => {
		void pollPlasmaFeed();
	}, 12000);

	activateButton.addEventListener("click", () => {
		void activateSensors();
	});

	replayButton.addEventListener("click", () => {
		if (root.dataset.labReplay === "1") {
			stopReplay();
			return;
		}
		startReplay();
	});

	window.addEventListener("beforeunload", () => {
		if (state.plasmaPollTimer) {
			window.clearInterval(state.plasmaPollTimer);
			state.plasmaPollTimer = 0;
		}
		resetSensorFeedback();
		stopReplay({ preserveStatus: true });
		stopCameraLoop();
		stopAudioLoop();
		stopMediaTracks(state.stream);
		stopMediaTracks(state.audioStream);
		state.stream = null;
		state.audioStream = null;
		if (state.lightSensor && typeof state.lightSensor.stop === "function") {
			try {
				state.lightSensor.stop();
			} catch {
				// Ignore stop failures on unload.
			}
		}
		if (state.audioContext && typeof state.audioContext.close === "function") {
			state.audioContext.close().catch(() => {});
		}
		releaseDeviceOrientationLock();
		void releaseWakeLock();
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

function runPageInit(label, init) {
	if (typeof init !== "function") {
		return;
	}

	try {
		const result = init();
		if (result && typeof result.then === "function") {
			result.catch((error) => {
				console.error(`Init failed: ${label}`, error);
			});
		}
	} catch (error) {
		console.error(`Init failed: ${label}`, error);
	}
}

runPageInit("mappingGenie", initMappingGenie);
runPageInit("deviceBridgePanels", initDeviceBridgePanels);
runPageInit("labConsole", initLabConsole);
runPageInit("xyzSurface", initXyzSurface);
runPageInit("xyzCamera", initXyzCamera);

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

function normalizeGuideVoiceSource(value, { upstreamConfigured = false, upstreamState = "" } = {}) {
	const source = typeof value === "string" ? value.trim().toLowerCase() : "";
	if (["local", "remote", "remote-ready", "auth-missing", "unavailable"].includes(source)) {
		return source;
	}

	if (typeof upstreamState === "string" && upstreamState.trim()) {
		return normalizeGuideVoiceSource(upstreamState.trim().toLowerCase(), { upstreamConfigured });
	}

	return upstreamConfigured ? "remote-ready" : "local";
}

function guideVoiceSourceLabel(source) {
	switch (source) {
		case "remote":
			return "remote";
		case "remote-ready":
			return "remote configuré";
		case "auth-missing":
			return "amont incomplet";
		case "unavailable":
			return "indispo";
		default:
			return "local";
	}
}

function normalizeGuideVoiceChannel(channel) {
	const value = typeof channel === "string" ? channel.trim().toLowerCase() : "";
	if (value === "text" || value === "texte") {
		return "texte";
	}

	if (value === "suggestion" || value === "impulsion") {
		return "impulsion";
	}

	return "voix";
}

function compactGuideVoiceText(value) {
	const text = typeof value === "string" ? value.replace(/\s+/g, " ").trim() : "";
	if (text.length <= 280) {
		return text;
	}

	return `${text.slice(0, 277).trimEnd()}…`;
}

function normalizeGuideVoiceHistory(entries) {
	if (!Array.isArray(entries)) {
		return [];
	}

	return entries
		.filter((entry) => entry && typeof entry === "object")
		.map((entry) => {
			const utterance = typeof entry.utterance === "string" ? entry.utterance.trim() : "";
			const reply = typeof entry.reply === "string" ? entry.reply.trim() : "";
			if (!utterance || !reply) {
				return null;
			}

			return {
				utterance: compactGuideVoiceText(utterance),
				reply: compactGuideVoiceText(reply),
				channel: normalizeGuideVoiceChannel(entry.channel),
				source: normalizeGuideVoiceSource(entry.source),
			};
		})
		.filter(Boolean)
		.slice(-GUIDE_VOICE_HISTORY_LIMIT);
}

let pageAccessibilityBooted = false;
let cornerDocksBooted = false;

function focusElementWithoutScroll(element) {
	if (!(element instanceof HTMLElement)) {
		return;
	}

	try {
		element.focus({ preventScroll: true });
	} catch {
		element.focus();
	}
}

function resolveSkipTarget(hash) {
	if (typeof hash !== "string" || !hash.startsWith("#") || hash.length <= 1) {
		return null;
	}

	const candidate = document.getElementById(hash.slice(1));
	if (!(candidate instanceof HTMLElement) || candidate.dataset.skipTarget === undefined) {
		return null;
	}

	return candidate;
}

function focusSkipTarget(hash) {
	const target = resolveSkipTarget(hash);
	if (!target) {
		return false;
	}

	focusElementWithoutScroll(target);
	return true;
}

function initPageAccessibility() {
	if (pageAccessibilityBooted) {
		return;
	}

	pageAccessibilityBooted = true;

	document.addEventListener("click", (event) => {
		const target = event.target;
		if (!(target instanceof Element)) {
			return;
		}

		const link = target.closest("[data-skip-link]");
		if (!(link instanceof HTMLAnchorElement)) {
			return;
		}

		const href = link.getAttribute("href") || "";
		if (!href.startsWith("#")) {
			return;
		}

		window.setTimeout(() => {
			focusSkipTarget(href);
		}, 0);
	});

	window.addEventListener("hashchange", () => {
		focusSkipTarget(window.location.hash);
	});

	if (window.location.hash) {
		window.requestAnimationFrame(() => {
			focusSkipTarget(window.location.hash);
		});
	}
}

function isEditableFocusTarget(target) {
	return target instanceof HTMLInputElement
		|| target instanceof HTMLTextAreaElement
		|| target instanceof HTMLSelectElement
		|| (target instanceof HTMLElement && target.isContentEditable);
}

function initSpatialContext() {
	const contexts = Array.from(document.querySelectorAll("[data-spatial-context]"));
	if (!contexts.length) {
		return;
	}

	const contextModels = contexts
		.map((context) => {
			if (!(context instanceof HTMLElement)) {
				return null;
			}

			return {
				root: context,
				raNote: context.querySelector("[data-spatial-ra-note]"),
				raActions: context.querySelector("[data-spatial-ra-actions]"),
				raPrimary: context.querySelector("[data-spatial-ra-primary]"),
				raSecondary: context.querySelector("[data-spatial-ra-secondary]"),
				worldView: context.querySelector("[data-spatial-world-view]"),
				worldFocus: context.querySelector("[data-spatial-world-focus]"),
				worldBody: context.querySelector("[data-spatial-world-body]"),
				worldHands: context.querySelector("[data-spatial-world-hands]"),
				worldLight: context.querySelector("[data-spatial-world-light]"),
				worldCopy: context.querySelector("[data-spatial-world-copy]"),
				nativeLinks: Array.from(context.querySelectorAll("[data-spatial-link]")),
				defaultNote: context.querySelector("[data-spatial-ra-note]")?.textContent?.trim() || "",
				defaultWorldCopy: context.querySelector("[data-spatial-world-copy]")?.textContent?.trim() || "",
			};
		})
		.filter(Boolean);

	const setContextActionLink = (node, link) => {
		if (!(node instanceof HTMLAnchorElement)) {
			return;
		}

		node.textContent = link?.label || "Revenir au noyau";
		node.href = link?.href || withSurfaceContext("/");
	};

	const setContextText = (node, text) => {
		if (node instanceof HTMLElement) {
			node.textContent = text;
		}
	};

	const highlightContextRoutes = (model, primary, secondary) => {
		if (!model) {
			return;
		}

		const primaryPath = normalizeRoutePathForComparison(primary?.href || "");
		const secondaryPath = normalizeRoutePathForComparison(secondary?.href || "");
		model.nativeLinks.forEach((link) => {
			if (!(link instanceof HTMLElement)) {
				return;
			}

			delete link.dataset.spatialRecommended;
			const linkPath = normalizeRoutePathForComparison(link.getAttribute("href") || "");
			if (primaryPath && linkPath === primaryPath) {
				link.dataset.spatialRecommended = "primary";
				return;
			}
			if (secondaryPath && linkPath === secondaryPath) {
				link.dataset.spatialRecommended = "secondary";
			}
		});
	};

	const applyRaStateToContext = (state) => {
		contextModels.forEach((model) => {
			if (!model || !(model.root instanceof HTMLElement)) {
				return;
			}

			if (!state || typeof state !== "object") {
				delete model.root.dataset.spatialRaMode;
				delete model.root.dataset.spatialRaDominant;
				if (model.raNote instanceof HTMLElement) {
					model.raNote.textContent = model.defaultNote;
				}
				if (model.raActions instanceof HTMLElement) {
					model.raActions.hidden = true;
				}
				highlightContextRoutes(model, null, null);
				return;
			}

			const modeLabel = typeof state.modeLabel === "string" && state.modeLabel.trim() ? state.modeLabel.trim() : (state.mode || "actif");
			const dominantLabel = typeof state.dominantLabel === "string" && state.dominantLabel.trim() ? state.dominantLabel.trim() : "Le tore";
			const primary = state.primary && typeof state.primary === "object" ? state.primary : null;
			const secondary = state.secondary && typeof state.secondary === "object" ? state.secondary : null;
			model.root.dataset.spatialRaMode = typeof state.mode === "string" ? state.mode : "";
			model.root.dataset.spatialRaDominant = typeof state.dominant === "string" ? state.dominant : "";
			if (model.raNote instanceof HTMLElement) {
				model.raNote.textContent = primary?.label
					? `${dominantLabel} mène encore en régime ${modeLabel}. ${primary.label} reste la meilleure prise depuis ici.`
					: `${dominantLabel} mène encore en régime ${modeLabel}.`;
			}
			if (model.raActions instanceof HTMLElement) {
				model.raActions.hidden = !(primary || secondary);
			}
			setContextActionLink(model.raPrimary, primary);
			setContextActionLink(model.raSecondary, secondary);
			highlightContextRoutes(model, primary, secondary);
		});
	};

	const applyWorldStateToContext = (state) => {
		contextModels.forEach((model) => {
			if (!model || !(model.root instanceof HTMLElement)) {
				return;
			}

			if (!state || typeof state !== "object") {
				delete model.root.dataset.spatialCameraFacing;
				setContextText(model.worldView, "mémoire calme");
				setContextText(model.worldFocus, "aucune prise récente");
				setContextText(model.worldBody, "corps en réserve");
				setContextText(model.worldHands, "terre et mine au repos");
				setContextText(model.worldLight, "lueur en mémoire");
				if (model.worldCopy instanceof HTMLElement) {
					model.worldCopy.textContent = model.defaultWorldCopy || "Le dernier monde instrument rejouable se posera ici quand la membrane aura parlé.";
				}
				return;
			}

			const cameraFacing = typeof state.cameraFacing === "string" ? state.cameraFacing : "";
			model.root.dataset.spatialCameraFacing = cameraFacing;
			setContextText(model.worldView, typeof state.viewLabel === "string" && state.viewLabel.trim() ? state.viewLabel.trim() : "monde actif");
			setContextText(model.worldFocus, typeof state.focusLabel === "string" && state.focusLabel.trim() ? state.focusLabel.trim() : "prise diffuse");
			setContextText(model.worldBody, typeof state.bodyLabel === "string" && state.bodyLabel.trim() ? state.bodyLabel.trim() : "corps en jeu");
			setContextText(model.worldHands, typeof state.touchLabel === "string" && state.touchLabel.trim() ? state.touchLabel.trim() : "terre et mine actives");
			setContextText(model.worldLight, typeof state.lightLabel === "string" && state.lightLabel.trim() ? state.lightLabel.trim() : "lumière active");
			if (model.worldCopy instanceof HTMLElement) {
				const copy = typeof state.worldCopy === "string" && state.worldCopy.trim()
					? state.worldCopy.trim()
					: (model.defaultWorldCopy || "Le monde reste un instrument.");
				model.worldCopy.textContent = copy;
			}
		});
	};

	applyRaStateToContext(readRaModulationSession());
	applyWorldStateToContext(readWorldInstrumentSession());
	window.addEventListener("o:ra-modulation", (event) => {
		const detail = event instanceof CustomEvent ? event.detail : null;
		applyRaStateToContext(detail);
	});
	window.addEventListener("o:world-instrument", (event) => {
		const detail = event instanceof CustomEvent ? event.detail : null;
		applyWorldStateToContext(detail);
	});

	const focusSpatialLink = (link) => {
		if (!(link instanceof HTMLElement)) {
			return false;
		}

		focusElementWithoutScroll(link);
		link.scrollIntoView({ block: "nearest", inline: "nearest", behavior: reducedMotion ? "auto" : "smooth" });
		return true;
	};

	document.addEventListener("keydown", (event) => {
		if (!prefersSpatialHeadsetMode() || event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey) {
			return;
		}

		const active = document.activeElement;
		if (isEditableFocusTarget(active)) {
			return;
		}

		const primaryContext = contexts.find((context) => context instanceof HTMLElement && !context.hidden);
		if (!(primaryContext instanceof HTMLElement)) {
			return;
		}

		const panels = Array.from(primaryContext.querySelectorAll("[data-spatial-panel]"));
		const links = Array.from(primaryContext.querySelectorAll("[data-spatial-link]"));
		if (!panels.length || !links.length) {
			return;
		}

		if (/^[123]$/.test(event.key)) {
			const panel = panels[Number.parseInt(event.key, 10) - 1];
			const targetLink = panel?.querySelector("[data-spatial-link]");
			if (focusSpatialLink(targetLink)) {
				event.preventDefault();
			}
			return;
		}

		const activeLink = active instanceof Element ? active.closest("[data-spatial-link]") : null;
		if (!(activeLink instanceof HTMLElement) || !primaryContext.contains(activeLink)) {
			return;
		}

		const currentIndex = links.indexOf(activeLink);
		if (currentIndex < 0) {
			return;
		}

		if (event.key === "ArrowRight" || event.key === "ArrowLeft") {
			const direction = event.key === "ArrowRight" ? 1 : -1;
			const nextIndex = (currentIndex + direction + links.length) % links.length;
			if (focusSpatialLink(links[nextIndex])) {
				event.preventDefault();
			}
			return;
		}

		if (event.key === "Home" || event.key === "End") {
			const targetLink = event.key === "Home" ? links[0] : links[links.length - 1];
			if (focusSpatialLink(targetLink)) {
				event.preventDefault();
			}
		}
	});
}

function isCompactCornerDockViewport() {
	return window.innerWidth <= 720;
}

function findCornerDockSummary(dock) {
	if (!(dock instanceof HTMLDetailsElement)) {
		return null;
	}

	const firstChild = dock.firstElementChild;
	if (firstChild instanceof HTMLElement && firstChild.tagName === "SUMMARY") {
		return firstChild;
	}

	return dock.querySelector("summary");
}

function syncCornerDockAccessibility(dock) {
	if (!(dock instanceof HTMLDetailsElement)) {
		return;
	}

	const summary = findCornerDockSummary(dock);
	if (summary instanceof HTMLElement) {
		summary.setAttribute("aria-expanded", dock.open ? "true" : "false");
	}
}

function closeCornerDock(dock, { restoreFocus = false } = {}) {
	if (!(dock instanceof HTMLDetailsElement)) {
		return;
	}

	const summary = findCornerDockSummary(dock);
	dock.open = false;
	syncCornerDockAccessibility(dock);
	if (restoreFocus && summary instanceof HTMLElement) {
		window.requestAnimationFrame(() => {
			focusElementWithoutScroll(summary);
		});
	}
}

function registerCornerDock(dock) {
	if (!(dock instanceof HTMLDetailsElement) || dock.dataset.cornerDockRegistered === "1") {
		return;
	}

	dock.dataset.cornerDockRegistered = "1";
	syncCornerDockAccessibility(dock);
	dock.addEventListener("toggle", () => {
		syncCornerDockAccessibility(dock);
	});
}

function syncCornerDocks(force = false) {
	const compact = isCompactCornerDockViewport();
	const docks = Array.from(document.querySelectorAll("[data-corner-dock]"));

	docks.forEach((dock) => {
		if (!(dock instanceof HTMLDetailsElement)) {
			return;
		}

		const isPrimaryDock = dock.dataset.cornerDockPriority === "primary";
		const isGuideVoiceDock = dock.dataset.guideVoiceDock === "1";
		registerCornerDock(dock);
		const shouldStayOpen = (!isGuideVoiceDock && dock.dataset.cornerDockActive === "1")
			|| (dock.id && window.location.hash === `#${dock.id}`)
			|| dock.contains(document.activeElement);

		if (!compact) {
			dock.open = true;
			dock.dataset.cornerDockCompact = "0";
			syncCornerDockAccessibility(dock);
			return;
		}

		dock.dataset.cornerDockCompact = "1";
		if (force || dock.dataset.cornerDockInitialized !== "1") {
			dock.open = shouldStayOpen || (isPrimaryDock && !isGuideVoiceDock);
		} else if (shouldStayOpen) {
			dock.open = true;
		} else if (isGuideVoiceDock) {
			dock.open = false;
		}

		dock.dataset.cornerDockInitialized = "1";
		syncCornerDockAccessibility(dock);
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
			if (!(dock instanceof HTMLDetailsElement) || !dock.open) {
				return;
			}

			if (dock.dataset.cornerDockActive === "1" && dock.dataset.guideVoiceDock !== "1") {
				return;
			}

			if (!dock.contains(target)) {
				closeCornerDock(dock);
			}
		});
	}, true);

	document.addEventListener("keydown", (event) => {
		if (event.key !== "Escape" || !isCompactCornerDockViewport()) {
			return;
		}

		document.querySelectorAll("[data-corner-dock]").forEach((dock) => {
			if (!(dock instanceof HTMLDetailsElement)) {
				return;
			}

			if (dock.dataset.cornerDockActive === "1" && dock.dataset.guideVoiceDock !== "1") {
				return;
			}

			const restoreFocus = dock.contains(document.activeElement);
			closeCornerDock(dock, { restoreFocus });
		});
	});
}

async function fetchGuideVoiceState(apiPath = withBridgePrefix("/0wlslw0/voice")) {
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
	shell.dataset.guideVoiceApi = config.api_path || withBridgePrefix("/0wlslw0/voice");
	shell.dataset.guideVoiceCsrf = config.csrf_token || "";
	shell.dataset.guideVoiceGreeting = config.greeting || "Je suis 0wlslw0 et j'ouvre la bonne porte vers le peuple de l'O.";
	shell.dataset.guideVoiceUpstream = config.upstream_configured ? "1" : "0";
	shell.dataset.guideVoiceUpstreamState = config.upstream_state || (config.upstream_configured ? "remote-ready" : "local");
	shell.dataset.guideVoiceUpstreamLabel = config.upstream_label || guideVoiceSourceLabel(shell.dataset.guideVoiceUpstreamState);
	shell.dataset.guideVoiceChatUrl = config.chat_url || "";
	shell.dataset.guideVoiceProgram = config.land_program || document.body?.dataset?.landProgram || "collective";
	shell.dataset.guideVoiceLabel = config.land_label || document.body?.dataset?.landLabel || "collectif";
	shell.dataset.guideVoiceLambda = String(config.land_lambda || document.body?.dataset?.landLambda || "548");
	shell.dataset.guideVoiceTone = config.land_tone || document.body?.dataset?.landTone || "";
	shell.dataset.guideVoiceStarterPrompts = JSON.stringify(normalizeGuideVoiceSuggestions(config.starter_prompts));
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
				<div class="guide-voice-dock-head__copy">
					<p class="eyebrow"><strong>0wlslw0</strong> <span>voix persistante</span></p>
				</div>
				<div class="guide-voice-dock-head__actions">
					<span class="badge">suivi</span>
					<button type="button" class="guide-voice-dock-collapse" data-guide-voice-collapse aria-label="Masquer le dock 0wlslw0">Masquer</button>
				</div>
			</div>
		<div class="guide-voice-stage">
			<div class="guide-voice-orb" aria-hidden="true">
				<span class="guide-voice-orb-core"></span>
				<span class="guide-voice-orb-ring"></span>
			</div>
			<p class="guide-voice-status" data-guide-voice-status role="status" aria-live="polite" aria-atomic="true">0wlslw0 peut te suivre ici.</p>
			<p class="guide-voice-transcript" data-guide-voice-transcript>La continuité vocale se réamorce après navigation.</p>
			<p class="guide-voice-reply" data-guide-voice-reply aria-live="polite" aria-atomic="true">Active la voix une fois, puis continue ta traversée.</p>
			<div class="guide-voice-meta" aria-live="polite">
				<span class="guide-voice-origin-badge" data-guide-voice-origin data-guide-voice-origin-state="${shell.dataset.guideVoiceUpstreamState}">${shell.dataset.guideVoiceUpstreamLabel}</span>
				<span class="guide-voice-meta-copy">texte disponible · historique court</span>
			</div>
			<ol class="guide-voice-history" data-guide-voice-history aria-label="Historique récent avec 0wlslw0" hidden></ol>
			<form class="guide-voice-form" data-guide-voice-form>
				<label class="sr-only" for="guide-voice-dock-text-input">Écrire à 0wlslw0</label>
				<input id="guide-voice-dock-text-input" type="text" name="guide_voice_text" maxlength="280" autocomplete="off" placeholder="Écris ici si tu préfères le silence." data-guide-voice-input>
				<button type="submit" class="pill-link guide-voice-submit" data-guide-voice-submit>Envoyer</button>
			</form>
			<p class="guide-voice-input-hint" data-guide-voice-input-hint>Le texte garde le passage ouvert, même sans micro Web.</p>
			<div class="guide-voice-suggestions" data-guide-voice-suggestions aria-label="Impulsions proposées par 0wlslw0"></div>
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

function normalizeGuideVoiceSuggestions(value) {
	if (!Array.isArray(value)) {
		return [];
	}

	return value
		.filter((item) => item && typeof item === "object")
		.map((item) => {
			const utterance = typeof item.utterance === "string" ? item.utterance.trim() : "";
			const label = typeof item.label === "string" ? item.label.trim() : "";
			if (!utterance) {
				return null;
			}

			return {
				utterance,
				label: label || utterance,
			};
		})
		.filter(Boolean)
		.slice(0, 4);
}

function mergeGuideVoiceSuggestions(...groups) {
	const merged = [];
	const seen = new Set();

	groups.forEach((group) => {
		normalizeGuideVoiceSuggestions(group).forEach((item) => {
			const utterance = typeof item.utterance === "string" ? item.utterance.trim() : "";
			const key = normalizeGuideVoiceText(utterance);
			if (!utterance || !key || seen.has(key)) {
				return;
			}

			seen.add(key);
			merged.push(item);
		});
	});

	return merged.slice(0, 4);
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
	const path = withoutBridgePrefix(window.location.pathname);
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
		case "/island":
			return {
				key: "island",
				label: heading || (landSlug ? `l'île ${landSlug}` : "une île"),
				hint: "Tu peux dire matière suivante, matière précédente, Str3m, noyau, ou guide.",
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

	if (withoutBridgePrefix(window.location.pathname) === "/land") {
		return {
			href: `${window.location.pathname}${window.location.search}${window.location.hash}`,
			label: "Cette terre",
			auto_navigate: true,
		};
	}

	return null;
}

function guideVoiceCompactPageLabel(page = currentGuideVoicePageInfo()) {
	switch (page.key) {
		case "home":
			return "noyau";
		case "signal":
			return "signal";
		case "str3m":
			return "str3m";
		case "aza":
			return "aZa";
		case "echo":
			return "echo";
		case "map":
			return "map";
		case "guide":
			return "guide";
		case "land":
			return "terre";
		case "island":
			return "île";
		default:
			return "ouvrir";
	}
}

function guideVoicePageSuggestions(page = currentGuideVoicePageInfo()) {
	const hasLandRoute = Boolean(findGuideVoiceLandRoute());

	switch (page.key) {
		case "home":
			return hasLandRoute
				? [
					{ utterance: "Ouvre ma terre.", label: "Rouvrir ma terre" },
					{ utterance: "Guide-moi vers Signal.", label: "Aller à Signal" },
					{ utterance: "Ramène-moi vers Str3m.", label: "Relire Str3m" },
				]
				: [
					{ utterance: "Je veux visiter publiquement.", label: "Visiter publiquement" },
					{ utterance: "Je veux poser une terre.", label: "Poser une terre" },
					{ utterance: "Aide-moi à choisir la bonne porte.", label: "Aide-moi à choisir" },
				];
		case "signal":
			return hasLandRoute
				? [
					{ utterance: "Écris à une autre terre.", label: "Écrire maintenant" },
					{ utterance: "Rouvre ma terre.", label: "Retour terre" },
					{ utterance: "Ramène-moi vers Str3m.", label: "Retour Str3m" },
				]
				: [
					{ utterance: "Explique-moi Signal.", label: "Expliquer Signal" },
					{ utterance: "Je veux poser une terre.", label: "Poser une terre" },
					{ utterance: "Ramène-moi vers le noyau.", label: "Retour noyau" },
				];
		case "str3m":
			return [
				{ utterance: "Je veux regarder sans compte.", label: "Regarder sans compte" },
				{ utterance: "Guide-moi vers Signal.", label: "Aller à Signal" },
				{ utterance: "Explique-moi la différence entre Str3m et aZa.", label: "Str3m ou aZa" },
			];
		case "aza":
			return [
				{ utterance: "Explique-moi aZa.", label: "Expliquer aZa" },
				{ utterance: "Montre-moi le public d'abord.", label: "Voir le public" },
				{ utterance: "Ramène-moi au noyau.", label: "Retour noyau" },
			];
		case "echo":
			return [
				{ utterance: "Explique-moi Echo.", label: "Expliquer Echo" },
				{ utterance: "Guide-moi vers Signal.", label: "Aller à Signal" },
				{ utterance: "Rouvre ma terre.", label: "Retour terre" },
			];
		case "map":
			return [
				{ utterance: "Explique-moi la map.", label: "Expliquer la map" },
				{ utterance: "Guide-moi vers Str3m.", label: "Aller à Str3m" },
				{ utterance: "Ramène-moi au noyau.", label: "Retour noyau" },
			];
		case "land":
			return [
				{ utterance: "Rouvre ma terre.", label: "Rouvrir ma terre" },
				{ utterance: "Guide-moi vers Signal.", label: "Aller à Signal" },
				{ utterance: "Explique-moi la différence entre Signal et aZa.", label: "Signal ou aZa" },
			];
		case "island":
			return [
				{ utterance: "Passe à la matière suivante.", label: "Matière suivante" },
				{ utterance: "Reviens à la matière précédente.", label: "Matière précédente" },
				{ utterance: "Ramène-moi vers Str3m.", label: "Retour Str3m" },
			];
		case "guide":
			return [
				{ utterance: "Je veux comprendre le projet.", label: "Comprendre O." },
				{ utterance: "Je veux visiter publiquement.", label: "Visiter publiquement" },
				{ utterance: "Je veux poser une terre.", label: "Poser une terre" },
			];
		default:
			return [];
	}
}

function guideVoiceNavigationCatalog() {
	const landRoute = findGuideVoiceLandRoute();
	const routes = [
		{
			key: "home",
			label: "le noyau",
			href: withSurfaceContext("/"),
			confirm: "Je te ramène vers le noyau.",
			matchers: ["noyau", "accueil", "centre", "retour noyau", "retour accueil", "home"],
		},
		{
			key: "signal",
			label: "Signal",
			href: withSurfaceContext("/signal"),
			confirm: "Je t’emmène vers Signal.",
			matchers: ["signal", "inbox", "boite", "boite aux lettres", "adresse"],
		},
		{
			key: "str3m",
			label: "Str3m",
			href: withSurfaceContext("/str3m"),
			confirm: "Je t’emmène vers Str3m.",
			matchers: ["str3m", "stream", "courant", "courant public", "public"],
		},
		{
			key: "aza",
			label: "aZa",
			href: withSurfaceContext("/aza"),
			confirm: "Je t’emmène vers aZa.",
			matchers: ["aza", "archive", "archives", "memoire", "memoires"],
		},
		{
			key: "echo",
			label: "Echo",
			href: withSurfaceContext("/echo"),
			confirm: "Je t’emmène vers Echo.",
			matchers: ["echo", "echoo", "liaison", "resonance", "resonnance"],
		},
		{
			key: "map",
			label: "la map",
			href: withSurfaceContext("/map"),
			confirm: "Je t’emmène vers la map du tore vivant.",
			matchers: ["map", "carte", "tore", "torus", "courants", "courant chaud"],
		},
		{
			key: "guide",
			label: "0wlslw0",
			href: withSurfaceContext("/0wlslw0"),
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

function triggerIslandReaderTraversal(direction = 1) {
	const shell = document.querySelector("[data-island-reader-shell]");
	if (!(shell instanceof HTMLElement)) {
		return false;
	}

	const selector = direction >= 0 ? "[data-island-reader-next]" : "[data-island-reader-prev]";
	const control = shell.querySelector(selector);
	if (!(control instanceof HTMLButtonElement) || control.disabled) {
		return false;
	}

	control.click();
	return true;
}

function resolveGuideVoiceIslandReaderCommand(utterance) {
	const page = currentGuideVoicePageInfo();
	if (page.key !== "island") {
		return null;
	}

	const text = normalizeGuideVoiceText(utterance);
	const nextPatterns = [
		"matiere suivante",
		"matiere d apres",
		"matiere d'apres",
		"lecture suivante",
		"lecteur suivant",
		"trace suivante",
	];
	const previousPatterns = [
		"matiere precedente",
		"matiere d avant",
		"matiere d'avant",
		"lecture precedente",
		"lecteur precedent",
		"trace precedente",
	];

	if (nextPatterns.some((pattern) => text.includes(pattern)) || text === "suivant") {
		return {
			type: "reader",
			direction: 1,
			reply: "Je passe à la matière suivante de l’île.",
		};
	}

	if (previousPatterns.some((pattern) => text.includes(pattern)) || text === "precedent") {
		return {
			type: "reader",
			direction: -1,
			reply: "Je reviens à la matière précédente de l’île.",
		};
	}

	return null;
}

function resolveGuideVoiceNativeCommand(utterance) {
	const text = normalizeGuideVoiceText(utterance);
	const page = currentGuideVoicePageInfo();
	if (!text) {
		return null;
	}

	const islandReaderCommand = resolveGuideVoiceIslandReaderCommand(utterance);
	if (islandReaderCommand) {
		return islandReaderCommand;
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

	if (href === withSurfaceContext("/#str3m-quotidien")) {
		return navigateToStr3mSurface();
	}

	if (href === withSurfaceContext("/")) {
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
	const originNode = root.querySelector("[data-guide-voice-origin]");
	const historyNode = root.querySelector("[data-guide-voice-history]");
	const inputForm = root.querySelector("[data-guide-voice-form]");
	const inputNode = root.querySelector("[data-guide-voice-input]");
	const inputHintNode = root.querySelector("[data-guide-voice-input-hint]");
	const suggestionsNode = root.querySelector("[data-guide-voice-suggestions]");
	const signatureNode = root.querySelector("[data-guide-voice-signature]");
	const profileNode = root.querySelector("[data-guide-voice-profile]");
	const muteIndicatorNode = root.querySelector("[data-guide-voice-mute-indicator]");
	const routeLink = root.querySelector("[data-guide-voice-route]");
	const dockLabelNode = root.querySelector("[data-guide-voice-dock-label]");
	const dockStateNode = root.querySelector("[data-guide-voice-dock-state]");
	const dockCollapseButton = root.querySelector("[data-guide-voice-collapse]");
	const RecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition || null;
	const hasRecognition = Boolean(RecognitionCtor);
	const synth = "speechSynthesis" in window ? window.speechSynthesis : null;
	const greeting = root.dataset.guideVoiceGreeting || "Je suis 0wlslw0 et j'ouvre la bonne porte vers le peuple de l'O.";
	const apiPath = root.dataset.guideVoiceApi || withBridgePrefix("/0wlslw0/voice");
	const csrfToken = root.dataset.guideVoiceCsrf || "";
	const chatUrl = root.dataset.guideVoiceChatUrl || "";
	const starterPrompts = (() => {
		const raw = root.dataset.guideVoiceStarterPrompts || "";
		if (!raw) {
			return [];
		}

		try {
			const parsed = JSON.parse(raw);
			return normalizeGuideVoiceSuggestions(parsed);
		} catch {
			return [];
		}
	})();
	const currentPath = `${window.location.pathname}${window.location.search}${window.location.hash}`;
	const pageSuggestions = guideVoicePageSuggestions();
	const baselineSuggestions = mergeGuideVoiceSuggestions(pageSuggestions, starterPrompts);
	const isDock = root.dataset.guideVoiceDock === "1";
	const upstreamConfigured = root.dataset.guideVoiceUpstream === "1";
	const upstreamState = root.dataset.guideVoiceUpstreamState || (upstreamConfigured ? "remote-ready" : "local");
	const persisted = readGuideVoiceSession();
	const persistedSuggestions = normalizeGuideVoiceSuggestions(persisted.suggestions);
	const persistedHistory = normalizeGuideVoiceHistory(persisted.history);
	let recognition = null;
	let isActive = Boolean(persisted.active);
	let isMuted = readGuideVoiceMutedState();
	let isSpeaking = false;
	let isWaitingReply = false;
	let interactionsBound = false;
	let activeSuggestions = mergeGuideVoiceSuggestions(pageSuggestions, persistedSuggestions, starterPrompts);
	let history = persistedHistory;
	let lastSource = normalizeGuideVoiceSource(persisted.lastSource, { upstreamConfigured, upstreamState });
	
	// --- Visuel & Audio (Breather) ---
	const breatherEl = root.querySelector("[data-guide-voice-breather]");
	const orbEl = root.querySelector(".guide-voice-orb");
	const audioStart = root.dataset.guideVoiceSoundStart ? new Audio(root.dataset.guideVoiceSoundStart) : null;
	const audioStop = root.dataset.guideVoiceSoundStop ? new Audio(root.dataset.guideVoiceSoundStop) : null;
	const audioLoop = root.dataset.guideVoiceSoundLoop ? new Audio(root.dataset.guideVoiceSoundLoop) : null;
	const readGuideVoiceOutputProfile = () => {
		const deviceProfile = readDeviceAudioProfile();
		return {
			deviceProfile,
			muted: isMuted || deviceProfile.muted,
			volume: clampNumber(deviceProfile.volume, 0, 1),
		};
	};
	const syncGuideVoiceCueLevels = () => {
		const profile = readGuideVoiceOutputProfile();
		if (audioStart) audioStart.volume = 0.36 * profile.volume;
		if (audioStop) audioStop.volume = 0.34 * profile.volume;
		if (audioLoop) {
			audioLoop.volume = 0.2 * profile.volume;
			audioLoop.loop = true;
		}
	};
	syncGuideVoiceCueLevels();
	let breatherInterval = 0;
	const breatherFrames = ['0', '.', 'O', '.'];

	const startBreather = () => {
		if (!breatherEl) return;
		const outputProfile = readGuideVoiceOutputProfile();
		syncGuideVoiceCueLevels();
		if (!outputProfile.muted && audioStart) { audioStart.currentTime = 0; audioStart.play().catch(()=>{}); }
		if (!outputProfile.muted && audioLoop) audioLoop.play().catch(()=>{});
		let frameIdx = 0;
		breatherEl.hidden = false;
		breatherEl.classList.add('is-breathing');
		if (orbEl) orbEl.classList.add('has-breather');
		window.clearInterval(breatherInterval);
		breatherInterval = window.setInterval(() => {
			frameIdx = (frameIdx + 1) % breatherFrames.length;
			breatherEl.textContent = breatherFrames[frameIdx];
		}, 600);
	};

	const stopBreather = () => {
		if (!breatherEl) return;
		const outputProfile = readGuideVoiceOutputProfile();
		syncGuideVoiceCueLevels();
		if (!outputProfile.muted && audioStop && breatherEl.classList.contains('is-breathing')) {
			audioStop.currentTime = 0;
			audioStop.play().catch(()=>{});
		}
		if (audioLoop) audioLoop.pause();
		breatherEl.hidden = true;
		breatherEl.classList.remove('is-breathing');
		if (orbEl) orbEl.classList.remove('has-breather');
		window.clearInterval(breatherInterval);
	};

	function syncDockState() {
		if (!isDock) {
			return;
		}

		root.dataset.cornerDockActive = "0";
		if (dockLabelNode instanceof HTMLElement) {
			dockLabelNode.textContent = isActive
				? (hasRecognition ? "voix active" : "texte actif")
				: (hasRecognition ? "voix persistante" : "texte persistant");
		}

		if (dockStateNode instanceof HTMLElement) {
			let compactLabel = guideVoiceCompactPageLabel();
			if (isActive && isMuted) {
				compactLabel = "muette";
			} else if (isWaitingReply) {
				compactLabel = "analyse";
			} else if (isSpeaking) {
				compactLabel = "répond";
			} else if (root.dataset.voiceState === "listening") {
				compactLabel = "écoute";
			} else if (isActive && !hasRecognition) {
				compactLabel = "texte";
			} else if (isActive) {
				compactLabel = "veille";
			}

			dockStateNode.textContent = compactLabel;
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
			suggestions: activeSuggestions,
			history,
			lastSource,
			lastPath: currentPath,
			autoResume: isActive,
			updatedAt: Date.now(),
			...overrides,
		});
	}

	function updateMuteUI(nextMuted = isMuted) {
		isMuted = Boolean(nextMuted);
		const deviceProfile = readDeviceAudioProfile();
		root.dataset.voiceMuted = isMuted ? "1" : "0";
		if (muteIndicatorNode instanceof HTMLElement) {
			muteIndicatorNode.textContent = isMuted
				? "voix muette · I ou appui long pour la relancer"
				: (deviceProfile.nativeSilenceMode === "silent"
					? "appareil silencieux · la voix reste lisible"
					: (deviceProfile.silenceIntent
						? "silence web · la voix reste lisible"
						: "voix active · I inverse + voix · appui long tactile"));
		}
		syncGuideVoiceCueLevels();
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

	function updateOriginBadge(nextSource = lastSource) {
		lastSource = normalizeGuideVoiceSource(nextSource, { upstreamConfigured, upstreamState });
		if (originNode instanceof HTMLElement) {
			originNode.dataset.guideVoiceOriginState = lastSource;
			originNode.textContent = guideVoiceSourceLabel(lastSource);
		}
		persistSession({ lastSource });
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

	function renderHistory() {
		if (!(historyNode instanceof HTMLElement)) {
			return;
		}

		historyNode.innerHTML = "";
		if (!history.length) {
			historyNode.hidden = true;
			persistSession();
			return;
		}

		historyNode.hidden = false;
		history.forEach((entry) => {
			const item = document.createElement("li");
			item.className = "guide-voice-history-item";

			const userLine = document.createElement("div");
			userLine.className = "guide-voice-history-line guide-voice-history-line--user";
			const userRole = document.createElement("span");
			userRole.className = "guide-voice-history-role";
			userRole.textContent = "toi";
			const userText = document.createElement("p");
			userText.className = "guide-voice-history-text";
			userText.textContent = entry.utterance;
			const channel = document.createElement("span");
			channel.className = "guide-voice-history-chip guide-voice-history-chip--channel";
			channel.textContent = entry.channel;
			userLine.append(userRole, userText, channel);

			const owlLine = document.createElement("div");
			owlLine.className = "guide-voice-history-line guide-voice-history-line--owl";
			const owlRole = document.createElement("span");
			owlRole.className = "guide-voice-history-role";
			owlRole.textContent = "0wlslw0";
			const owlText = document.createElement("p");
			owlText.className = "guide-voice-history-text";
			owlText.textContent = entry.reply;
			const source = document.createElement("span");
			source.className = "guide-voice-history-chip guide-voice-history-chip--source";
			source.dataset.guideVoiceOriginState = entry.source;
			source.textContent = guideVoiceSourceLabel(entry.source);
			owlLine.append(owlRole, owlText, source);

			item.append(userLine, owlLine);
			historyNode.appendChild(item);
		});

		persistSession();
	}

	function rememberExchange({ utterance, reply, channel = "voice", source = lastSource } = {}) {
		const cleanUtterance = typeof utterance === "string" ? utterance.trim() : "";
		const cleanReply = typeof reply === "string" ? reply.trim() : "";
		if (!cleanUtterance || !cleanReply) {
			return;
		}

		history = normalizeGuideVoiceHistory([
			...history,
			{
				utterance: cleanUtterance,
				reply: cleanReply,
				channel,
				source,
			},
		]);
		updateOriginBadge(source);
		renderHistory();
	}

	function describeUtterance(utterance, channel = "voice") {
		if (channel === "text") {
			return `Tu écris : ${utterance}`;
		}

		if (channel === "suggestion") {
			return `Impulsion : ${utterance}`;
		}

		return `Tu as dit : ${utterance}`;
	}

	function renderSuggestions(suggestions) {
		if (!(suggestionsNode instanceof HTMLElement)) {
			return;
		}

		const normalized = normalizeGuideVoiceSuggestions(suggestions);
		const explicitSuggestions = Array.isArray(suggestions);
		const fallbackSuggestions = mergeGuideVoiceSuggestions(activeSuggestions, baselineSuggestions);
		const items = normalized.length
			? normalized
			: explicitSuggestions
				? []
				: fallbackSuggestions;

		activeSuggestions = normalized.length
			? normalized
			: (explicitSuggestions ? [] : fallbackSuggestions);
		suggestionsNode.innerHTML = "";
		items.forEach((item) => {
			const button = document.createElement("button");
			button.type = "button";
			button.className = "guide-voice-suggestion";
			button.dataset.guideVoiceSuggestion = "1";
			button.dataset.utterance = item.utterance.trim();
			button.textContent = typeof item.label === "string" && item.label.trim()
				? item.label.trim()
				: item.utterance.trim();
			suggestionsNode.appendChild(button);
		});
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

	function startGuideVoiceButtonLabel() {
		if (!hasRecognition) {
			return "Écrire à 0wlslw0";
		}

		return isDock ? "Réactiver la voix" : "Activer la voix";
	}

	function focusGuideVoiceInput() {
		if (isDock && isCompactCornerDockViewport()) {
			root.open = true;
		}

		if (!(inputNode instanceof HTMLInputElement)) {
			return;
		}

		window.requestAnimationFrame(() => {
			inputNode.focus({ preventScroll: false });
			const caret = typeof inputNode.value === "string" ? inputNode.value.length : 0;
			if (typeof inputNode.setSelectionRange === "function") {
				inputNode.setSelectionRange(caret, caret);
			}
		});
	}

	function activateGuideVoiceTextMode(firstActivation = false) {
		isActive = true;
		isWaitingReply = false;
		hideRoute();
		startButton.hidden = true;
		stopButton.hidden = false;
		renderSuggestions(starterPrompts);
		persistSession({ active: true, autoResume: true, everActivated: true, pendingNavigation: "" });

		setReply(firstActivation
			? "Le micro Web manque ici. Écris-moi, je reste présent et je peux quand même lire mes réponses."
			: "Le texte prend le relais ici. Écris-moi quand tu veux.");
		setState("idle", "Micro Web indisponible. Le texte reste ouvert.");

		if (firstActivation && synth) {
			speakReply(greeting, () => {
				if (!isActive) {
					return;
				}

				setState("idle", "Micro Web indisponible. Le texte reste ouvert.");
				focusGuideVoiceInput();
			});
			return;
		}

		focusGuideVoiceInput();
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
		const deviceProfile = readDeviceAudioProfile();
		if (!text) {
			onDone?.();
			return;
		}

		if (isMuted || deviceProfile.muted || !synth || typeof window.SpeechSynthesisUtterance !== "function") {
			if (isMuted || deviceProfile.muted) {
				setState("idle", deviceProfile.nativeSilenceMode === "silent"
					? "Appareil silencieux. 0wlslw0 continue en texte lisible."
					: "Voix muette. 0wlslw0 continue en texte lisible.");
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
		utterance.rate = clampNumber(spectral.rate * 0.97, 0.82, 1);
		utterance.pitch = clampNumber(spectral.pitch * 0.96, 0.72, 1.02);
		utterance.volume = clampNumber(spectral.volume * deviceProfile.volume * 0.92, 0, 0.88);
		utterance.onstart = () => {
			startBreather();
		};
		utterance.onend = () => {
			isSpeaking = false;
			stopBreather();
			onDone?.();
		};
		utterance.onerror = () => {
			isSpeaking = false;
			stopBreather();
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
			stopBreather();
		}
		if (startButton instanceof HTMLElement) {
			startButton.hidden = false;
			startButton.textContent = startGuideVoiceButtonLabel();
		}
		if (stopButton instanceof HTMLElement) {
			stopButton.hidden = true;
		}
		setState("idle", message);
		persistSession({ active: false, autoResume: false, pendingNavigation: "", carryMessage: "" });
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

	function handleNativeCommand(utterance, { channel = "voice" } = {}) {
		const command = resolveGuideVoiceNativeCommand(utterance);
		if (!command) {
			return false;
		}

		setTranscript(describeUtterance(utterance, channel));

		if (command.type === "stop") {
			setReply(command.reply);
			rememberExchange({ utterance, reply: command.reply, channel, source: "local" });
			stopGuide("Voix coupée localement.");
			return true;
		}

		if (command.type === "reader") {
			const moved = triggerIslandReaderTraversal(command.direction);
			const reply = moved
				? command.reply
				: "La station n’a pas d’autre matière active à ouvrir pour l’instant.";
			setReply(reply);
			rememberExchange({ utterance, reply, channel, source: "local" });
			speakReply(reply, () => {
				resumeAfterReply(220);
			});
			return true;
		}

		if (command.type === "info") {
			setReply(command.reply);
			showRoute(command.route || null);
			rememberExchange({ utterance, reply: command.reply, channel, source: "local" });
			speakReply(command.reply, () => {
				resumeAfterReply(220);
			});
			return true;
		}

		if (command.type === "navigate") {
			const normalizedRoute = normalizeGuideVoiceRoute(command.route);
			setReply(command.reply);
			showRoute(normalizedRoute);
			rememberExchange({ utterance, reply: command.reply, channel, source: "local" });
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

	async function sendUtterance(utterance, { channel = "voice" } = {}) {
		isWaitingReply = true;
		setState("thinking", "Je cherche la bonne porte.");
		setTranscript(describeUtterance(utterance, channel));

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
			const suggestions = payload && typeof payload === "object" ? payload.suggestions : null;
			const source = payload && typeof payload === "object" ? payload.source : "local";

			setReply(reply);
			showRoute(route);
			renderSuggestions(suggestions);
			isWaitingReply = false;
			rememberExchange({ utterance, reply, channel, source });

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
			const fallbackReply = chatUrl
				? "Le relais local a décroché. Tu peux utiliser le relais externe pendant que je me réveille."
				: "Le relais local a décroché. Réessaie dans un instant.";
			setReply(fallbackReply);
			setState("idle", "Connexion vocale brouillée.");
			rememberExchange({ utterance, reply: fallbackReply, channel, source: "unavailable" });
			if (isActive) {
				window.setTimeout(beginListening, 600);
			}
		}
	}

	function submitGuideUtterance(rawUtterance, { channel = "voice" } = {}) {
		const utterance = typeof rawUtterance === "string" ? rawUtterance.trim() : "";
		if (!utterance || isWaitingReply) {
			return false;
		}

		if (inputNode instanceof HTMLInputElement && channel === "text") {
			inputNode.value = "";
		}

		if (synth) {
			synth.cancel();
		}
		isSpeaking = false;
		stopListening();
		hideRoute();

		if (handleNativeCommand(utterance, { channel })) {
			return true;
		}

		sendUtterance(utterance, { channel });
		return true;
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
	updateOriginBadge(lastSource);
	renderHistory();

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

	renderSuggestions();

	if (inputHintNode instanceof HTMLElement) {
		inputHintNode.textContent = RecognitionCtor
			? "Tu peux écrire à tout moment."
			: "Le texte prend le relais ici.";
	}

	const bindSuggestionClicks = () => {
		if (!(suggestionsNode instanceof HTMLElement) || suggestionsNode.dataset.bound === "1") {
			return;
		}

		suggestionsNode.dataset.bound = "1";
		suggestionsNode.addEventListener("click", (event) => {
			const target = event.target;
			if (!(target instanceof Element)) {
				return;
			}

			const button = target.closest("[data-guide-voice-suggestion]");
			if (!(button instanceof HTMLButtonElement)) {
				return;
			}

			const utterance = (button.dataset.utterance || "").trim();
			if (!utterance || isWaitingReply) {
				return;
			}

			submitGuideUtterance(utterance, { channel: "suggestion" });
		});
	};

	bindSuggestionClicks();

	if (inputForm instanceof HTMLFormElement) {
		inputForm.addEventListener("submit", (event) => {
			event.preventDefault();
			if (!(inputNode instanceof HTMLInputElement)) {
				return;
			}

			submitGuideUtterance(inputNode.value, { channel: "text" });
		});
	}

	if (hasRecognition) {
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
				submitGuideUtterance(finalTranscript.trim(), { channel: "voice" });
			}
		};

		recognition.onerror = (event) => {
			if (!isActive) {
				return;
			}

			const errorCode = event?.error || "unknown";
			if (errorCode === "not-allowed" || errorCode === "service-not-allowed") {
				stopGuide("Le micro doit être réautorisé sur cette page.");
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
	}

	startButton.addEventListener("click", () => {
		const session = readGuideVoiceSession();
		const firstActivation = !session.everActivated;
		if (startButton instanceof HTMLButtonElement) {
			startButton.disabled = false;
		}

		if (!hasRecognition) {
			activateGuideVoiceTextMode(firstActivation);
			return;
		}

		isActive = true;
		isWaitingReply = false;
		if (isDock && isCompactCornerDockViewport()) {
			root.open = true;
		}
		hideRoute();
		startButton.hidden = true;
		stopButton.hidden = false;
		setReply(firstActivation
			? "0wlslw0 répondra ici puis lira sa réponse."
			: "0wlslw0 reprend l’écoute ici.");
		renderSuggestions(starterPrompts);
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

	if (dockCollapseButton instanceof HTMLButtonElement) {
		dockCollapseButton.addEventListener("click", () => {
			closeCornerDock(root);
		});
	}

	if (routeLink instanceof HTMLAnchorElement) {
		routeLink.addEventListener("click", () => {
			prepareNavigation(routeLink.href || "");
		});
	}

	bindSuggestionClicks();

	window.addEventListener("o:land-signature-change", () => {
		applyGuideVoiceSignature();
	});

	window.addEventListener("o:guide-voice-mute-change", (event) => {
		const nextMuted = Boolean(event?.detail?.muted ?? readGuideVoiceMutedState());
		const wasSpeaking = isSpeaking;
		const deviceProfile = readDeviceAudioProfile();
		updateMuteUI(nextMuted);
		if (nextMuted && synth) {
			synth.cancel();
			isSpeaking = false;
		}

		if (statusNode instanceof HTMLElement) {
			statusNode.textContent = nextMuted
				? (hasRecognition
					? "Voix muette. 0wlslw0 reste lisible et continue d’écouter."
					: "Voix muette. 0wlslw0 reste lisible et disponible en texte.")
				: (deviceProfile.nativeSilenceMode === "silent"
					? "Appareil silencieux. 0wlslw0 reste lisible et continue d’écouter."
					: (isActive
						? (hasRecognition
							? "Voix audible. 0wlslw0 peut repasser du grave à l’aigu."
							: "Lecture audible. 0wlslw0 peut reparler depuis le texte.")
						: (hasRecognition
							? "Voix audible. Active-la si tu veux l’entendre."
							: "Lecture audible. Ouvre le texte si tu veux l’entendre.")));
		}
		persistSession();

		if (wasSpeaking && nextMuted && isActive && !isWaitingReply) {
			window.setTimeout(beginListening, 140);
		}

		syncDockState();
	});

	window.addEventListener("o:device-bridge-change", () => {
		const deviceProfile = readDeviceAudioProfile();
		const wasSpeaking = isSpeaking;
		updateMuteUI(isMuted);
		if (deviceProfile.muted && synth) {
			synth.cancel();
			isSpeaking = false;
			if (statusNode instanceof HTMLElement) {
				statusNode.textContent = deviceProfile.nativeSilenceMode === "silent"
					? "Appareil silencieux. 0wlslw0 continue en texte lisible."
					: "Silence web actif. 0wlslw0 continue en texte lisible.";
			}
			if (wasSpeaking && isActive && !isWaitingReply) {
				window.setTimeout(beginListening, 140);
			}
			return;
		}

		if (statusNode instanceof HTMLElement && !isMuted && !isWaitingReply && !isSpeaking) {
			statusNode.textContent = isActive
				? (hasRecognition
					? "Voix audible. 0wlslw0 suit maintenant le profil du téléphone."
					: "Lecture audible. 0wlslw0 répondra depuis le texte.")
				: (hasRecognition
					? "Voix audible. Active-la si tu veux l’entendre."
					: "Lecture audible. Ouvre le texte si tu veux l’entendre.");
		}
	});

	const navigationCarry = Boolean(persisted.active);
	if (navigationCarry) {
		startButton.hidden = true;
		stopButton.hidden = false;
		const crossedPageBoundary = Boolean(persisted.lastPath && persisted.lastPath !== currentPath);
		setState(
			"idle",
			crossedPageBoundary
				? (hasRecognition ? "Je te suis ici. Je reprends la navigation vocale." : "Je te suis ici. Le texte reprend le relais.")
				: (persisted.status || (isDock
					? (hasRecognition ? "0wlslw0 reste actif ici." : "0wlslw0 reste disponible ici en texte.")
					: (hasRecognition ? "La voix reste active." : "Le texte reste actif pendant la traversée.")))
		);
		if (!(replyNode instanceof HTMLElement) || !replyNode.textContent) {
			setReply(isDock
				? (hasRecognition
					? "0wlslw0 est toujours là. Continue simplement à parler."
					: "0wlslw0 est toujours là. Continue simplement à écrire.")
				: (hasRecognition
					? "0wlslw0 reste actif pendant la traversée."
					: "0wlslw0 reste actif en texte pendant la traversée."));
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
		? (hasRecognition
			? "0wlslw0 peut te suivre ici si tu actives la voix depuis son passage."
			: "0wlslw0 peut te suivre ici si tu ouvres le texte depuis son passage.")
		: (hasRecognition
			? "Prêt. Active la voix puis parle naturellement."
			: "Prêt. Écris à 0wlslw0 si le micro Web manque ici."));
	if (!(replyNode instanceof HTMLElement) || !replyNode.textContent) {
		setReply(isDock
			? (hasRecognition
				? "Active la voix depuis 0wlslw0, puis elle te suivra pendant la navigation."
				: "Ouvre le texte depuis 0wlslw0, puis il te suivra pendant la navigation.")
			: (hasRecognition
				? "0wlslw0 répondra ici puis lira sa réponse."
				: (chatUrl
					? "Le micro Web manque ici. Écris à 0wlslw0, le relais externe reste disponible."
					: "Le micro Web manque ici. Écris à 0wlslw0, il répondra ici et lira si possible.")));
	}
	if (startButton instanceof HTMLElement) {
		startButton.disabled = false;
		startButton.textContent = startGuideVoiceButtonLabel();
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
