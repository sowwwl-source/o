(() => {
	const doc = document;
	const win = window;
	const body = doc.body;

	if (!(body instanceof HTMLElement)) {
		return;
	}

	const APPARITIONS_LAST_AT_KEY = "o:apparitions:last_at";
	const APPARITION_MEMBER_CONTEXT_KEY = "o:apparitions:member_context";
	const prefersReducedMotion = win.matchMedia("(prefers-reduced-motion: reduce)").matches;
	const eligiblePaths = new Set([
		"/",
		"/rejoindre",
		"/0wlslw0",
		"/str3m",
		"/signal",
		"/map",
		"/land",
		"/island",
		"/sh0re",
		"/echo",
	]);

	let apparitionTimer = 0;
	let apparitionHideTimer = 0;

	function readRuntimeMeta(name, fallback = "") {
		const meta = doc.querySelector(`meta[name="${name}"]`);
		if (!(meta instanceof HTMLMetaElement)) {
			return fallback;
		}

		const value = meta.content || "";
		return value !== "" ? value : fallback;
	}

	function bridgePrefix() {
		return readRuntimeMeta("o-bridge-prefix", "").replace(/\/+$/, "");
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

	function withoutBridgePrefix(pathname = win.location.pathname) {
		const prefix = bridgePrefix();
		const raw = typeof pathname === "string" && pathname !== "" ? pathname : "/";

		if (!prefix) {
			return raw;
		}

		if (raw === prefix) {
			return "/";
		}

		if (raw.startsWith(`${prefix}/`)) {
			return raw.slice(prefix.length) || "/";
		}

		return raw;
	}

	function normalizedPathname() {
		const normalized = withoutBridgePrefix(win.location.pathname).replace(/\/+$/, "") || "/";
		return normalized === "" ? "/" : normalized;
	}

	function currentIdentifier() {
		try {
			const url = new URL(win.location.href);
			return (url.searchParams.get("u") || "").trim();
		} catch {
			return "";
		}
	}

	function pageIsEligible(pathname) {
		if (pathname.startsWith("/aza")) {
			return true;
		}

		return eligiblePaths.has(pathname);
	}

	function readSessionValue(key) {
		try {
			return win.sessionStorage.getItem(key);
		} catch {
			return null;
		}
	}

	function writeSessionValue(key, value) {
		try {
			win.sessionStorage.setItem(key, value);
		} catch {
			// Ignore storage failures.
		}
	}

	function removeSessionValue(key) {
		try {
			win.sessionStorage.removeItem(key);
		} catch {
			// Ignore storage failures.
		}
	}

	function readNumber(key) {
		const value = Number(readSessionValue(key));
		return Number.isFinite(value) ? value : 0;
	}

	function writeNumber(key, value) {
		writeSessionValue(key, String(Number.isFinite(value) ? value : 0));
	}

	function hasMemberContext() {
		return readSessionValue(APPARITION_MEMBER_CONTEXT_KEY) === "1";
	}

	function writeMemberContext(active) {
		if (active) {
			writeSessionValue(APPARITION_MEMBER_CONTEXT_KEY, "1");
			return;
		}

		removeSessionValue(APPARITION_MEMBER_CONTEXT_KEY);
	}

	function hasLinkedPresence() {
		if (body.dataset.userCloudHost === "1") {
			return true;
		}

		if (doc.querySelector(".connection-meter.is-linked")) {
			return true;
		}

		const path = normalizedPathname();
		const identifier = currentIdentifier();
		if ((path === "/land" || path === "/island") && identifier === "") {
			return true;
		}

		return false;
	}

	function inferAudience(pathname, identifier) {
		if (pathname === "/rejoindre") {
			writeMemberContext(false);
			return "guest";
		}

		if (hasLinkedPresence()) {
			writeMemberContext(true);
			return "member";
		}

		if ((pathname === "/land" || pathname === "/island" || pathname === "/sh0re" || pathname === "/echo") && identifier !== "") {
			return "mixed";
		}

		if (hasMemberContext()) {
			return "member";
		}

		return pathname === "/" ? "mixed" : "mixed";
	}

	function rarityWeight(rarity) {
		if (rarity === "common") return 9;
		if (rarity === "uncommon") return 5;
		if (rarity === "rare") return 3.5;
		if (rarity === "mythic") return 1.25;
		return 1;
	}

	function rarityChance(rarity) {
		if (rarity === "common") return 1;
		if (rarity === "uncommon") return 0.72;
		if (rarity === "rare") return 0.54;
		if (rarity === "mythic") return 0.2;
		return 0.25;
	}

	function rarityCooldownMs(rarity) {
		if (rarity === "common") return 45_000;
		if (rarity === "uncommon") return 90_000;
		if (rarity === "rare") return 4 * 60_000;
		if (rarity === "mythic") return 12 * 60_000;
		return 2 * 60_000;
	}

	function randomInt(minimum, maximum) {
		return Math.floor(Math.random() * ((maximum - minimum) + 1)) + minimum;
	}

	function sampleWeightedWithoutReplacement(items, count) {
		const pool = items.slice();
		const picked = [];
		const safeCount = Math.max(0, Math.min(count, pool.length));

		for (let index = 0; index < safeCount; index += 1) {
			let total = 0;
			for (const item of pool) {
				total += Math.max(0, item.weight || 0);
			}

			if (!(total > 0)) {
				const fallback = pool.pop();
				if (fallback) {
					picked.push(fallback);
				}
				continue;
			}

			let cursor = Math.random() * total;
			let pickedIndex = 0;
			for (; pickedIndex < pool.length; pickedIndex += 1) {
				cursor -= Math.max(0, pool[pickedIndex].weight || 0);
				if (cursor <= 0) {
					break;
				}
			}

			if (pickedIndex >= pool.length) {
				pickedIndex = pool.length - 1;
			}

			picked.push(pool.splice(pickedIndex, 1)[0]);
		}

		return picked;
	}

	function keepClusterQuota(candidates, cluster, maxCount) {
		if (!cluster || maxCount < 0) {
			return candidates.slice();
		}

		const grouped = candidates.filter((candidate) => candidate.cluster === cluster);
		if (grouped.length <= maxCount) {
			return candidates.slice();
		}

		const keptIds = new Set(sampleWeightedWithoutReplacement(grouped, maxCount).map((candidate) => candidate.id));
		return candidates.filter((candidate) => candidate.cluster !== cluster || keptIds.has(candidate.id));
	}

	function buildEntrypoints(identifier) {
		const encodedIdentifier = identifier !== "" ? encodeURIComponent(identifier) : "";
		const contextHref = (route) => identifier !== "" ? `${route}?u=${encodedIdentifier}` : route;
		const entries = [
			{ id: "rejoindre", label: "REJOINDRE", href: "/rejoindre", rarity: "common", audiences: ["guest", "mixed"], cluster: "threshold" },
			{ id: "0wlslw0", label: "0WLSLW0", href: "/0wlslw0", rarity: "uncommon", audiences: ["guest", "mixed", "member"], cluster: "guide" },
			{ id: "str3m", label: "STR3M", href: "/str3m", rarity: "rare", audiences: ["guest", "mixed", "member"], cluster: "public" },
			{ id: "signal", label: "SIGNAL", href: contextHref("/signal"), rarity: "rare", audiences: ["guest", "mixed", "member"], cluster: "public" },
			{ id: "aza", label: "AZA", href: contextHref("/aza"), rarity: "uncommon", audiences: ["guest", "mixed", "member"], cluster: "memory" },
			{ id: "map", label: "MAP", href: "/map", rarity: "mythic", audiences: ["guest", "mixed", "member"], cluster: "cosmos" },
			{ id: identifier !== "" ? `sh0re:${identifier}` : "sh0re", label: "SH0RE", href: contextHref("/sh0re"), rarity: "uncommon", audiences: ["mixed", "member"], cluster: "profile" },
		];

		if (identifier !== "") {
			entries.push(
				{ id: `land:${identifier}`, label: "LAND", href: contextHref("/land"), rarity: "common", audiences: ["mixed", "member"], cluster: "profile" },
				{ id: `island:${identifier}`, label: "ISLAND", href: contextHref("/island"), rarity: "uncommon", audiences: ["mixed", "member"], cluster: "profile" },
				{ id: `echo:${identifier}`, label: "ECHO", href: contextHref("/echo"), rarity: "rare", audiences: ["mixed", "member"], cluster: "profile" },
			);
			return entries;
		}

		entries.push({
			id: "echo",
			label: "ECHO",
			href: "/echo",
			rarity: "mythic",
			audiences: ["member"],
			cluster: "profile",
		});

		return entries;
	}

	function entryWeight(entry, audience, hasIdentifier) {
		let weight = rarityWeight(entry.rarity);

		if (audience === "guest") {
			if (entry.id === "rejoindre") weight *= 1.65;
			if (entry.id === "0wlslw0") weight *= 1.25;
			if (entry.id === "str3m") weight *= 1.15;
		}

		if (audience === "mixed") {
			if (entry.id === "signal" || entry.id === "str3m") weight *= 1.2;
			if (entry.cluster === "profile" && hasIdentifier) weight *= 1.35;
		}

		if (audience === "member") {
			if (entry.id === "signal") weight *= 1.35;
			if (entry.id === "aza") weight *= 1.2;
			if (entry.label === "ECHO") weight *= 1.3;
		}

		if (hasIdentifier && entry.cluster === "profile") {
			weight *= 1.45;
		}

		if (entry.id === "map") {
			weight *= 0.78;
		}

		return weight;
	}

	function entrySeenKey(id) {
		return `o:apparitions:seen:${id}`;
	}

	function readEntrySeenAt(id) {
		return readNumber(entrySeenKey(id));
	}

	function writeEntrySeenAt(id, at) {
		writeNumber(entrySeenKey(id), at);
	}

	function readLastApparitionAt() {
		return readNumber(APPARITIONS_LAST_AT_KEY);
	}

	function writeLastApparitionAt(at) {
		writeNumber(APPARITIONS_LAST_AT_KEY, at);
	}

	function isTextEntryFocused() {
		const active = doc.activeElement;
		if (!(active instanceof HTMLElement)) {
			return false;
		}

		if (active.isContentEditable) {
			return true;
		}

		return active.tagName === "INPUT" || active.tagName === "TEXTAREA" || active.tagName === "SELECT";
	}

	function pickTargets(pathname, identifier, audience) {
		const now = Date.now();
		const entries = buildEntrypoints(identifier);
		const currentPath = pathname.startsWith("/aza/") ? "/aza" : pathname;
		const filtered = entries.filter((entry) => {
			if (Array.isArray(entry.audiences) && entry.audiences.length > 0 && !entry.audiences.includes(audience)) {
				return false;
			}

			const entryPath = entry.href.split("?")[0];
			if (entryPath === currentPath) {
				return false;
			}

			return true;
		});

		function materialize(candidates) {
			return candidates.map((entry) => ({
				...entry,
				weight: entryWeight(entry, audience, identifier !== ""),
			}));
		}

		function gate(candidates, { ignoreCooldown, ignoreChance }) {
			const visible = [];
			for (const entry of candidates) {
				if (!ignoreCooldown) {
					const cooldown = rarityCooldownMs(entry.rarity);
					if ((now - readEntrySeenAt(entry.id)) < cooldown) {
						continue;
					}
				}

				if (!ignoreChance) {
					const chance = rarityChance(entry.rarity);
					if (chance < 1 && Math.random() > chance) {
						continue;
					}
				}

				visible.push(entry);
			}

			return visible;
		}

		let candidates = gate(filtered, { ignoreCooldown: false, ignoreChance: false });
		if (candidates.length === 0) {
			candidates = gate(filtered, { ignoreCooldown: false, ignoreChance: true });
		}
		if (candidates.length === 0) {
			candidates = gate(filtered, { ignoreCooldown: true, ignoreChance: true });
		}

		let curated = keepClusterQuota(materialize(candidates), "profile", 1);
		curated = keepClusterQuota(curated, "public", 1);
		const targetCount = win.matchMedia("(max-width: 640px)").matches ? 1 : randomInt(1, 2);
		return sampleWeightedWithoutReplacement(curated, targetCount);
	}

	function buildAnimatedText(container, text) {
		container.textContent = "";
		const glyphs = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789".split("");
		const spans = [];

		for (const char of text) {
			const span = doc.createElement("span");
			span.className = "o-flip-char";
			span.textContent = char === " " ? " " : " ";
			spans.push(span);
			container.appendChild(span);
		}

		if (prefersReducedMotion) {
			spans.forEach((span, index) => {
				span.textContent = text[index] || "";
				span.classList.add("is-locked");
			});
			return;
		}

		spans.forEach((span, index) => {
			const target = text[index] || "";
			win.setTimeout(() => {
				if (target === " ") {
					span.textContent = " ";
					span.classList.add("is-locked");
					return;
				}

				let ticks = 0;
				const maxTicks = randomInt(5, 10);
				const intervalId = win.setInterval(() => {
					ticks += 1;
					if (ticks >= maxTicks) {
						span.textContent = target;
						span.classList.add("is-locked");
						win.clearInterval(intervalId);
						return;
					}

					span.textContent = glyphs[Math.floor(Math.random() * glyphs.length)];
				}, 28);
			}, index * 52);
		});
	}

	function mountApparitions(targets) {
		if (!Array.isArray(targets) || targets.length === 0) {
			return;
		}

		const existing = doc.getElementById("o-apparitions");
		if (existing) {
			existing.remove();
		}

		const root = doc.createElement("div");
		root.id = "o-apparitions";
		root.className = "o-apparitions is-hidden";
		root.setAttribute("role", "region");
		root.setAttribute("aria-label", "Apparitions");

		const sig = doc.createElement("div");
		sig.className = "o-apparitions__sig";
		sig.textContent = "⋯";
		root.appendChild(sig);

		const list = doc.createElement("div");
		list.className = "o-apparitions__list";
		root.appendChild(list);

		for (const target of targets) {
			const link = doc.createElement("a");
			link.className = "o-apparition-link";
			link.href = withBridgePrefix(target.href);
			link.setAttribute("aria-label", target.label);

			const sr = doc.createElement("span");
			sr.className = "sr-only";
			sr.textContent = target.label;
			link.appendChild(sr);

			const anim = doc.createElement("span");
			anim.className = "o-flip";
			anim.setAttribute("aria-hidden", "true");
			link.appendChild(anim);
			buildAnimatedText(anim, target.label);

			list.appendChild(link);
		}

		const stamp = Date.now();
		targets.forEach((target) => {
			writeEntrySeenAt(target.id, stamp);
		});

		body.appendChild(root);
		win.requestAnimationFrame(() => {
			root.classList.remove("is-hidden");
		});

		let dismissed = false;
		const onKeydown = (event) => {
			if (event.key === "Escape") {
				hide();
			}
		};

		function hide() {
			if (dismissed) {
				return;
			}

			dismissed = true;
			win.removeEventListener("keydown", onKeydown);
			if (apparitionHideTimer) {
				win.clearTimeout(apparitionHideTimer);
				apparitionHideTimer = 0;
			}

			root.classList.add("is-hidden");
			win.setTimeout(() => {
				root.remove();
			}, 260);
		}

		root.querySelectorAll("a").forEach((link) => {
			link.addEventListener("click", hide, { once: true });
		});

		win.addEventListener("keydown", onKeydown);
		apparitionHideTimer = win.setTimeout(hide, 11_000);
	}

	function scheduleApparitions() {
		if (apparitionTimer) {
			win.clearTimeout(apparitionTimer);
		}
		if (apparitionHideTimer) {
			win.clearTimeout(apparitionHideTimer);
			apparitionHideTimer = 0;
		}

		const pathname = normalizedPathname();
		if (!pageIsEligible(pathname)) {
			return;
		}

		const identifier = currentIdentifier();
		const audience = inferAudience(pathname, identifier);
		const now = Date.now();
		const minGapMs = 18_000;
		const initialDelayMs = randomInt(2_400, 6_400);
		const waitMs = Math.max(initialDelayMs, minGapMs - (now - readLastApparitionAt()));

		apparitionTimer = win.setTimeout(() => {
			if (doc.hidden || isTextEntryFocused()) {
				scheduleApparitions();
				return;
			}

			const targets = pickTargets(pathname, identifier, audience);
			if (targets.length === 0) {
				apparitionTimer = win.setTimeout(scheduleApparitions, randomInt(9_000, 18_000));
				return;
			}

			writeLastApparitionAt(Date.now());
			mountApparitions(targets);
			apparitionTimer = win.setTimeout(scheduleApparitions, randomInt(26_000, 52_000));
		}, waitMs);
	}

	scheduleApparitions();
})();
