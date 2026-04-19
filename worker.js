const SITE_DOMAIN = "sowwwl.xyz";
const SITE_TAGLINE = "Just the Three of Us";
const DEFAULT_TIMEZONE = "Europe/Paris";
const CREATE_LAND_RATE_LIMIT_MAX_ATTEMPTS = 6;
const CREATE_LAND_RATE_LIMIT_WINDOW_SECONDS = 600;
const FORM_COOKIE_NAME = "sowwwl_form";
const FORM_TOKEN_TTL_SECONDS = 60 * 60 * 2;
const MIN_FORM_AGE_SECONDS = 2;
const STORAGE_OBJECT_NAME = "sowwwl-ingress";
const TIMEZONE_SUGGESTIONS = [
	"Europe/Paris",
	"Europe/London",
	"America/New_York",
	"America/Los_Angeles",
	"America/Montreal",
	"Africa/Casablanca",
	"Asia/Tokyo",
	"Asia/Bangkok",
];

const NO_STORE_CACHE = "no-store, private, max-age=0";
const DYNAMIC_SECURITY_HEADERS = {
	"Cache-Control": NO_STORE_CACHE,
	Pragma: "no-cache",
	"Content-Security-Policy":
		"default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; manifest-src 'self'; worker-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'",
	"Cross-Origin-Opener-Policy": "same-origin",
	"Cross-Origin-Resource-Policy": "same-origin",
	"Referrer-Policy": "same-origin",
	"X-Content-Type-Options": "nosniff",
	"X-Permitted-Cross-Domain-Policies": "none",
};

export default {
	async fetch(request, env) {
		const url = new URL(request.url);

		if (url.hostname === `www.${SITE_DOMAIN}`) {
			return redirectResponse(`https://${SITE_DOMAIN}${url.pathname}${url.search}`, 308);
		}

		if (request.method === "GET" && (url.pathname === "/" || url.pathname === "/index.php")) {
			return renderHomeResponse(request, env, url, {
				form: {
					username: "",
					timezone: DEFAULT_TIMEZONE,
				},
			});
		}

		if (request.method === "POST" && (url.pathname === "/" || url.pathname === "/index.php")) {
			return handleCreateLand(request, env, url);
		}

		if (request.method === "GET" && url.pathname === "/land.php") {
			return handleLandPage(request, env, url);
		}

		return serveStaticOrNotFound(request, env, url);
	},
};

export class Storage {
	constructor(state) {
		this.state = state;
	}

	async fetch(request) {
		const url = new URL(request.url);

		if (request.method === "GET" && url.pathname === "/pulse") {
			return jsonResponse(await this.readPulse());
		}

		if (request.method === "GET" && url.pathname === "/land") {
			const identifier = url.searchParams.get("slug") || "";
			const land = await this.findLand(identifier);

			if (!land) {
				return jsonResponse({ land: null }, 404);
			}

			return jsonResponse({ land });
		}

		if (request.method === "POST" && url.pathname === "/create") {
			const payload = await request.json();

			try {
				const land = await this.createLand(payload);
				return jsonResponse({ land }, 201);
			} catch (error) {
				const status = typeof error?.status === "number" ? error.status : 400;
				return jsonResponse({ error: error.message || "Erreur inattendue." }, status);
			}
		}

		return jsonResponse({ error: "Not found." }, 404);
	}

	async createLand(payload) {
		const username = normalizeUsername(payload?.username || "");
		const timezone = validateTimezone(payload?.timezone || "");
		const slug = normalizeSlug(username);
		const ip = normalizeIp(payload?.ip || "unknown");
		await this.enforceRateLimit(ip);

		const existing = await this.state.storage.get(landStorageKey(slug));
		if (existing) {
			throw responseError("Cette terre existe deja.", 409);
		}

		const createdAt = new Date().toISOString();
		const land = {
			username,
			slug,
			email_virtual: `${slug}@o.local`,
			timezone,
			zone_code: timezone,
			created_at: createdAt,
		};

		await this.state.storage.put(landStorageKey(slug), land);

		const stats = (await this.state.storage.get("stats")) || defaultPulse();
		const timezoneKey = seenTimezoneKey(timezone);
		const timezoneSeen = await this.state.storage.get(timezoneKey);

		if (!timezoneSeen) {
			await this.state.storage.put(timezoneKey, true);
			stats.timezones += 1;
		}

		stats.count += 1;
		stats.latest_created_at = createdAt;
		stats.latest_timezone = timezone;
		stats.latest_slug = slug;
		stats.latest_created_label = humanCreatedLabel(createdAt);
		stats.latest_summary = `Dernier seuil depuis ${timezone}.`;
		await this.state.storage.put("stats", stats);

		return land;
	}

	async findLand(identifier) {
		const trimmed = String(identifier || "").trim();

		if (!trimmed) {
			return null;
		}

		let slug = trimmed;

		try {
			slug = normalizeSlug(trimmed);
		} catch {
			return null;
		}

		return (await this.state.storage.get(landStorageKey(slug))) || null;
	}

	async readPulse() {
		const stats = await this.state.storage.get("stats");
		return stats || defaultPulse();
	}

	async enforceRateLimit(ip) {
		const key = rateLimitKey(ip);
		const timestamps = (await this.state.storage.get(key)) || [];
		const now = Date.now();
		const threshold = now - CREATE_LAND_RATE_LIMIT_WINDOW_SECONDS * 1000;
		const kept = timestamps.filter((value) => Number(value) >= threshold);

		if (kept.length >= CREATE_LAND_RATE_LIMIT_MAX_ATTEMPTS) {
			throw responseError(
				"Trop de tentatives depuis cette connexion. Reessaie dans quelques minutes.",
				429
			);
		}

		kept.push(now);
		await this.state.storage.put(key, kept);
	}
}

async function handleCreateLand(request, env, url) {
	const secret = requireAppSecret(env);
	const formData = await request.formData();
	const form = {
		username: String(formData.get("username") || "").trim(),
		timezone: String(formData.get("timezone") || "").trim(),
	};
	const csrfCandidate = String(formData.get("csrf_token") || "");
	const honeypot = String(formData.get("website") || "");

	if (form.username === "" || form.timezone === "") {
		return renderHomeResponse(request, env, url, {
			form,
			message: "Rien n'est obligatoire, mais quelque chose est necessaire.",
			messageType: "warning",
		});
	}

	try {
		await guardLandCreationRequest(request, url, secret, csrfCandidate, honeypot);
		const land = await createLandInStorage(env, {
			username: form.username,
			timezone: form.timezone,
			ip: clientIp(request),
		});

		const headers = new Headers({
			Location: `/land.php?u=${encodeURIComponent(land.slug)}&created=1`,
		});
		headers.append(
			"Set-Cookie",
			buildCookie(FORM_COOKIE_NAME, "", {
				path: "/",
				maxAge: 0,
				httpOnly: true,
				sameSite: "Lax",
				secure: true,
			})
		);

		return new Response(null, {
			status: 303,
			headers: withDynamicHeaders(headers),
		});
	} catch (error) {
		return renderHomeResponse(request, env, url, {
			form,
			message: error.message || "Impossible de poser cette terre pour le moment.",
			messageType: "warning",
		});
	}
}

async function handleLandPage(request, env, url) {
	const identifier = url.searchParams.get("u") || "";
	const land = await findLandInStorage(env, identifier);
	const created = url.searchParams.get("created") === "1";
	const originBase = url.origin;

	if (!land) {
		return htmlResponse(
			renderLandPage({
				land: null,
				created,
				originBase,
			}),
			404
		);
	}

	return htmlResponse(
		renderLandPage({
			land,
			created,
			originBase,
		})
	);
}

async function renderHomeResponse(request, env, url, state) {
	const secret = requireAppSecret(env);
	const pulse = await readPulse(env);
	const token = await issueFormToken(secret);
	const form = state.form || { username: "", timezone: DEFAULT_TIMEZONE };
	const html = renderHomePage({
		form,
		message: state.message || "",
		messageType: state.messageType || "info",
		pulse,
		csrfToken: token,
		originBase: url.origin,
	});
	const headers = new Headers();

	headers.append(
		"Set-Cookie",
		buildCookie(FORM_COOKIE_NAME, token, {
			path: "/",
			httpOnly: true,
			maxAge: FORM_TOKEN_TTL_SECONDS,
			sameSite: "Lax",
			secure: true,
		})
	);

	return htmlResponse(html, 200, headers);
}

async function serveStaticOrNotFound(request, env, url) {
	const assetResponse = await env.ASSETS.fetch(request);

	if (assetResponse.status !== 404) {
		return assetResponse;
	}

	if (wantsHtml(request)) {
		return htmlResponse(renderGenericNotFoundPage(url), 404);
	}

	return assetResponse;
}

function renderHomePage({ form, message, messageType, pulse, csrfToken, originBase }) {
	const previewSlug = previewLandSlug(form.username);
	const previewTimezone = form.timezone || DEFAULT_TIMEZONE;
	const flash = message
		? `<div class="flash flash-${escapeHtml(messageType)}" aria-live="polite"><p>${escapeHtml(message)}</p></div>`
		: "";
	const timezoneOptions = TIMEZONE_SUGGESTIONS.map(
		(timezone) => `<option value="${escapeHtml(timezone)}"></option>`
	).join("");
	const timezoneChips = TIMEZONE_SUGGESTIONS.map(
		(timezone) =>
			`<button type="button" class="timezone-chip" data-timezone-chip="${escapeHtml(timezone)}">${escapeHtml(timezone)}</button>`
	).join("");

	return `<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="sowwwl.xyz - Just the Three of Us. O.n0uSnoImenT.">
	<meta name="theme-color" content="#09090b">
	<title>sowwwl.xyz - O.</title>
	<link rel="icon" href="/favicon.svg" type="image/svg+xml">
	<link rel="manifest" href="/manifest.json">
	<link rel="stylesheet" href="/styles.css">
	<script defer src="/main.js"></script>
</head>
<body class="experience home">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

<main class="layout">
	<header class="hero reveal">
		<span class="eyebrow eyebrow-pill">sowwwl.xyz / user ingress</span>
		<div class="hero-grid">
			<section class="hero-copy">
				<h1><span>Pose ta terre.</span> <em>Garde ton rythme.</em></h1>
				<p class="vortex" aria-hidden="true">(.0.)</p>
				<p class="lead">
					Une porte plus intime que sowwwl.cloud, plus simple qu'un produit complet.
					Tu poses un nom, et ton espace existe deja.
				</p>
				<div class="hero-actions">
					<a class="pill-link" href="#poser">Creer mon espace</a>
					<a class="ghost-link" href="#surface">Voir la surface</a>
				</div>

				<nav class="hero-nav" aria-label="Promesse du shell">
					<a class="nav-card" href="#poser">
						<strong>1 champ</strong>
						<span>Ton nom d'usage. Rien de plus pour entrer.</span>
					</a>
					<a class="nav-card" href="#surface">
						<strong>Temps vivant</strong>
						<span>Le shell te montre immediatement ton heure locale.</span>
					</a>
					<a class="nav-card" href="#pulse">
						<strong>Lien direct</strong>
						<span>Une terre personnelle et partageable, prete tout de suite.</span>
					</a>
				</nav>
			</section>

			<aside class="hero-aside">
				<div class="status-card status-card-primary">
					<div class="status-label">Mode actif</div>
					<div class="status-value"><strong>Private shell</strong> sans tunnel inutile</div>
					<p class="status-meta">
						Inspire par sowwwl.cloud pour la clarte et 0.user.o.sowwwl.cloud
						pour l'intimite du shell. Ici, l'inscription est la page.
					</p>
				</div>

				<section class="signup-shell" id="poser" aria-labelledby="install-title">
					<div class="signup-head">
						<div>
							<h2 id="install-title">Inscription calme</h2>
							<p class="panel-copy">Une terre legere, une porte propre, un rythme a toi.</p>
						</div>
						<span class="badge badge-warm">sans mot de passe</span>
					</div>

					${flash}

					<form method="post" class="land-form" autocomplete="off">
						<input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}">

						<div class="form-trap" aria-hidden="true">
							<label>
								Site web
								<input type="text" name="website" tabindex="-1" autocomplete="off">
							</label>
						</div>

						<label>
							Nom d'usage
							<input
								type="text"
								name="username"
								placeholder="ex: nox"
								required
								minlength="2"
								maxlength="42"
								value="${escapeHtml(form.username)}"
								data-username-input
							>
							<span class="input-hint">Choisis un nom simple, memorable, vivant.</span>
						</label>

<input
                                                        type="hidden"
                                                        name="timezone"
                                                        value="${escapeHtml(previewTimezone)}"
                                                        data-timezone-input
                                                >

						<button type="submit">Entrer dans O.</button>
					</form>

					<div
						class="signup-preview"
						data-origin-base="${escapeHtml(originBase)}"
						data-preview-shell
					>
						<span class="summary-label">Apercu immediat</span>
						<strong class="preview-title" data-slug-output>${escapeHtml(previewSlug)}</strong>
						<div class="preview-grid">
							<p><span>Lien</span><code data-land-link-output>${escapeHtml(`${originBase}/land.php?u=${previewSlug}`)}</code></p>
							<p><span>Email virtuel</span><code data-email-output>${escapeHtml(`${previewSlug}@o.local`)}</code></p>
							<p><span>Fuseau</span><strong data-preview-timezone>${escapeHtml(previewTimezone)}</strong></p>
						</div>
					</div>
				</section>
			</aside>
		</div>
	</header>

	<section class="panel reveal surface-panel" id="surface" aria-labelledby="surface-title">
		<div class="section-topline">
			<div>
				<h2 id="surface-title">Surface de controle</h2>
				<p class="panel-copy">Une lecture simple du noyau, du temps et de la promesse d'entree.</p>
			</div>
			<span class="badge">${escapeHtml(SITE_DOMAIN)}</span>
		</div>

		<div class="surface-grid">
			<section class="telemetry-block" aria-labelledby="telemetry-title">
				<h3 id="telemetry-title">Noyau</h3>
				<div class="data-grid telemetry-grid">
					<p>&gt; INITIALISATION : <span class="highlight">H.°bO</span></p>
					<p>&gt; DOMAINE : <span class="highlight">${escapeHtml(SITE_DOMAIN)}</span></p>
					<p>&gt; PASSERELLE : <span class="highlight">0.user.o.sowwwl.cloud -&gt; sowwwl.xyz</span></p>
					<p>&gt; MODE : <span class="highlight">workers + durable storage</span> | SECURITE : <span class="highlight">xXx</span></p>
					<p class="bootline" id="bootline">[ L'aspiration est en cours... George Duke is ON. ]</p>
				</div>
			</section>

			<section class="clock-shell" aria-labelledby="signals-title">
				<div>
					<h3 id="signals-title">Signal vivant</h3>
					<p class="panel-copy">Previsualisation locale du temps selon le fuseau saisi.</p>
				</div>
				<div
					class="clock"
					aria-live="polite"
					data-live-clock
					data-preview-clock
					data-timezone="${escapeHtml(previewTimezone)}"
				>
					<p class="clock-label" data-clock-label>Fuseau : -</p>
					<p class="clock-time" data-clock-time>--:--:--</p>
					<p class="clock-date" data-clock-date>--</p>
				</div>
			</section>
		</div>
	</section>

	<section class="panel reveal flow-panel" aria-labelledby="flow-title">
		<div class="section-topline">
			<div>
				<h2 id="flow-title">Ce que l'inscription fait vraiment</h2>
				<p class="panel-copy">On enleve le bruit: juste les elements necessaires pour qu'une terre existe.</p>
			</div>
		</div>

		<div class="steps-grid">
			<article class="step-card">
				<span class="step-index">01</span>
				<h3>Nommer</h3>
				<p>Ton nom d'usage devient un slug propre et une porte stable dans le shell.</p>
			</article>
			<article class="step-card">
				<span class="step-index">02</span>
				<h3>Cadencer</h3>
				<p>Le fuseau donne un rythme vivant a la page et prepare l'espace a t'accueillir.</p>
			</article>
			<article class="step-card">
				<span class="step-index">03</span>
				<h3>Entrer</h3>
				<p>Une fois posee, la terre a deja son lien, son temps local et son identite minimale.</p>
			</article>
		</div>
	</section>

	<section class="panel reveal pulse" id="pulse" aria-labelledby="pulse-title">
		<div class="section-topline">
			<div>
				<h2 id="pulse-title">Pouls de la constellation</h2>
				<p class="panel-copy">Une mesure legere, sans base de donnees externe, juste assez pour sentir la presence.</p>
			</div>
			<span class="badge">${Number(pulse.count)} terres</span>
		</div>

		<div class="metric-grid">
			<article class="metric-card">
				<span class="metric-label">Terres posees</span>
				<strong class="metric-value">${Number(pulse.count)}</strong>
				<p>Le reseau reste petit, mais il tient.</p>
			</article>
			<article class="metric-card">
				<span class="metric-label">Fuseaux actifs</span>
				<strong class="metric-value">${Number(pulse.timezones)}</strong>
				<p>Chaque terre garde son propre rythme.</p>
			</article>
			<article class="metric-card">
				<span class="metric-label">Dernier signal</span>
				<strong class="metric-value metric-value-small">${escapeHtml(pulse.latest_created_label || "en attente")}</strong>
				<p>${escapeHtml(pulse.latest_summary)}</p>
			</article>
		</div>
	</section>

	<footer class="site-footer reveal">
		<p>sowwwl.xyz tient maintenant comme une vraie porte d'entree: plus clair, plus desirable, plus pret a etre partage.</p>
	</footer>
</main>
</body>
</html>`;
}

function renderLandPage({ land, created, originBase }) {
	if (!land) {
		return `<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="sowwwl.xyz - espace personnel.">
	<meta name="theme-color" content="#09090b">
	<title>Terre introuvable - sowwwl.xyz</title>
	<link rel="icon" href="/favicon.svg" type="image/svg+xml">
	<link rel="manifest" href="/manifest.json">
	<link rel="stylesheet" href="/styles.css">
	<script defer src="/main.js"></script>
</head>
<body class="experience land-view">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

<main class="layout page-shell">
	<section class="hero page-header reveal">
		<p class="eyebrow">terre introuvable</p>
		<h1>Cette porte ne mene nulle part.</h1>
		<p class="lead">
			Le lien est vide, abime, ou la terre n'a jamais ete posee.
		</p>
		<div class="hero-actions">
			<a class="pill-link" href="/">Revenir a l'accueil</a>
		</div>
	</section>
</main>
</body>
</html>`;
	}

	const shareUrl = `${originBase}/land.php?u=${encodeURIComponent(land.slug)}`;
	const createdFlash = created
		? `<div class="flash flash-success" aria-live="polite"><p>Votre terre est posee.</p></div>`
		: "";
	const createdBadge = created ? `<span class="badge">terre posee</span>` : "";

	return `<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="sowwwl.xyz - espace personnel.">
	<meta name="theme-color" content="#09090b">
	<title>${escapeHtml(land.username)} - sowwwl.xyz</title>
	<link rel="icon" href="/favicon.svg" type="image/svg+xml">
	<link rel="manifest" href="/manifest.json">
	<link rel="stylesheet" href="/styles.css">
	<script defer src="/main.js"></script>
</head>
<body class="experience land-view">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

<main class="layout page-shell">
	<header class="hero page-header reveal">
		<p class="eyebrow"><strong>terre active</strong> <span>${escapeHtml(land.slug)}</span></p>
		<h1 class="land-title">
			<strong>${escapeHtml(land.username)}</strong>
			<span>${escapeHtml(SITE_TAGLINE)}</span>
		</h1>
		<p class="lead">
			Ta terre est posee. Elle garde ton fuseau, ton nom d'usage, et une porte simple pour revenir.
		</p>

		<div class="land-meta">
			<span class="meta-pill">${escapeHtml(land.timezone)}</span>
			<span class="meta-pill">${escapeHtml(land.email_virtual)}</span>
			<span class="meta-pill">${escapeHtml(humanCreatedLabel(land.created_at) || "maintenant")}</span>
		</div>
	</header>

	<section class="panel-shell">
		<section class="panel reveal" aria-labelledby="clock-title">
			<div class="section-topline">
				<div>
					<h2 id="clock-title">Temps local</h2>
					<p class="panel-copy">Le temps de ta terre, calcule en direct dans le navigateur.</p>
				</div>
				${createdBadge}
			</div>

			${createdFlash}

			<div
				class="clock"
				aria-live="polite"
				data-live-clock
				data-timezone="${escapeHtml(land.timezone)}"
			>
				<p class="clock-label" data-clock-label>Fuseau : -</p>
				<p class="clock-time" data-clock-time>--:--:--</p>
				<p class="clock-date" data-clock-date>--</p>
			</div>

			<div class="summary-grid">
				<article class="summary-card">
					<span class="summary-label">Nom d'usage</span>
					<strong class="summary-value">${escapeHtml(land.username)}</strong>
					<p>Le nom visible de ta presence sur cette terre.</p>
				</article>
				<article class="summary-card">
					<span class="summary-label">Code zone</span>
					<strong class="summary-value summary-value-small">${escapeHtml(land.zone_code)}</strong>
					<p>Le repere utilise pour synchroniser le temps vivant.</p>
				</article>
				<article class="summary-card">
					<span class="summary-label">Ouverture</span>
					<strong class="summary-value summary-value-small">${escapeHtml(humanCreatedLabel(land.created_at) || "maintenant")}</strong>
					<p>Premiere apparition de cette terre dans la constellation.</p>
				</article>
			</div>
		</section>

		<aside class="panel reveal" aria-labelledby="ritual-title">
			<h2 id="ritual-title">Rituel</h2>
			<p class="panel-copy">Une terre minuscule a besoin de peu pour rester habitable.</p>
			<p class="land-note">
				Garde le lien, garde le fuseau, et reviens quand tu veux. Le reste peut grandir plus tard,
				sans casser la base.
			</p>
			<div class="action-row">
				<a class="pill-link" href="/">Retour au noyau</a>
				<button
					type="button"
					class="copy-button"
					data-copy-link="${escapeHtml(shareUrl)}"
				>Copier l'adresse</button>
			</div>
		</aside>
	</section>
</main>
</body>
</html>`;
}

function renderGenericNotFoundPage(url) {
	return `<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="sowwwl.xyz - page introuvable.">
	<meta name="theme-color" content="#09090b">
	<title>404 - sowwwl.xyz</title>
	<link rel="icon" href="/favicon.svg" type="image/svg+xml">
	<link rel="manifest" href="/manifest.json">
	<link rel="stylesheet" href="/styles.css">
	<script defer src="/main.js"></script>
</head>
<body class="experience land-view">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

<main class="layout page-shell">
	<section class="hero page-header reveal">
		<p class="eyebrow">404 / shell vide</p>
		<h1>Cette route n'existe pas.</h1>
		<p class="lead">
			Le chemin <code>${escapeHtml(url.pathname)}</code> ne correspond a aucun asset ni a aucune terre.
		</p>
		<div class="hero-actions">
			<a class="pill-link" href="/">Retour a l'accueil</a>
		</div>
	</section>
</main>
</body>
</html>`;
}

async function guardLandCreationRequest(request, url, secret, csrfCandidate, honeypot) {
	if (!sameOriginSubmission(request, url)) {
		throw responseError("La demande a ete rejetee.", 403);
	}

	if (honeypot.trim() !== "") {
		throw responseError("La demande a ete rejetee.", 400);
	}

	const cookieToken = readCookie(request, FORM_COOKIE_NAME);
	if (!cookieToken || cookieToken !== csrfCandidate) {
		throw responseError("La session a expire. Recharge la page avant de recommencer.", 403);
	}

	const parsed = await verifyFormToken(secret, csrfCandidate);
	if (!parsed.valid) {
		throw responseError("La session a expire. Recharge la page avant de recommencer.", 403);
	}

	const ageSeconds = Math.floor(Date.now() / 1000) - parsed.payload.issuedAt;
	if (ageSeconds < MIN_FORM_AGE_SECONDS) {
		throw responseError("Prends une seconde pour verifier les infos avant de continuer.", 400);
	}
}

async function createLandInStorage(env, payload) {
	const response = await storageFetch(env, "/create", {
		method: "POST",
		body: JSON.stringify(payload),
		headers: {
			"Content-Type": "application/json",
		},
	});

	if (!response.ok) {
		const failure = await response.json();
		throw responseError(
			failure.error || "Impossible de poser cette terre pour le moment.",
			response.status
		);
	}

	const data = await response.json();
	return data.land;
}

async function findLandInStorage(env, identifier) {
	let slug = identifier;

	try {
		slug = normalizeSlug(identifier);
	} catch {
		return null;
	}

	const response = await storageFetch(env, `/land?slug=${encodeURIComponent(slug)}`);
	if (response.status === 404) {
		return null;
	}

	const data = await response.json();
	return data.land || null;
}

async function readPulse(env) {
	const response = await storageFetch(env, "/pulse");
	if (!response.ok) {
		return defaultPulse();
	}

	return response.json();
}

function storageFetch(env, path, init = {}) {
	const id = env.STORAGE.idFromName(STORAGE_OBJECT_NAME);
	const stub = env.STORAGE.get(id);
	return stub.fetch(`https://storage${path}`, init);
}

function htmlResponse(html, status = 200, headers = new Headers()) {
	const merged = withDynamicHeaders(headers);
	merged.set("Content-Type", "text/html; charset=utf-8");
	return new Response(html, { status, headers: merged });
}

function jsonResponse(data, status = 200) {
	const headers = withDynamicHeaders(new Headers());
	headers.set("Content-Type", "application/json; charset=utf-8");
	return new Response(JSON.stringify(data), { status, headers });
}

function redirectResponse(location, status = 302) {
	const headers = withDynamicHeaders(new Headers({ Location: location }));
	return new Response(null, { status, headers });
}

function withDynamicHeaders(headers) {
	const merged = new Headers(headers);
	for (const [name, value] of Object.entries(DYNAMIC_SECURITY_HEADERS)) {
		if (!merged.has(name)) {
			merged.set(name, value);
		}
	}
	return merged;
}

function defaultPulse() {
	return {
		count: 0,
		timezones: 0,
		latest_created_at: null,
		latest_created_label: null,
		latest_slug: null,
		latest_timezone: null,
		latest_summary: "Aucune terre posee pour l'instant.",
	};
}

function normalizeUsername(username) {
	const collapsed = String(username || "")
		.replace(/\s+/g, " ")
		.trim();
	const length = Array.from(collapsed).length;

	if (length < 2 || length > 42) {
		throw responseError("Le nom d'usage doit tenir entre 2 et 42 caracteres.", 400);
	}

	return collapsed;
}

function previewLandSlug(username) {
	try {
		return normalizeSlug(username);
	} catch {
		return "terre";
	}
}

function normalizeSlug(value) {
	const candidate = String(value || "").trim();
	const normalized =
		typeof candidate.normalize === "function" ? candidate.normalize("NFD") : candidate;
	const ascii = normalized.replace(/[\u0300-\u036f]/g, "");
	const slug = ascii
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, "-")
		.replace(/^-+|-+$/g, "")
		.slice(0, 42);

	if (!slug) {
		throw responseError("Choisis un nom d'usage lisible en lettres ou en chiffres.", 400);
	}

	return slug;
}

function validateTimezone(timezone) {
	const candidate = String(timezone || "").trim();

	if (!candidate) {
		throw responseError("Le fuseau horaire manque encore.", 400);
	}

	try {
		new Intl.DateTimeFormat("fr-FR", { timeZone: candidate }).format(new Date());
	} catch {
		throw responseError("Ce fuseau horaire ne ressemble pas a un fuseau valide.", 400);
	}

	return candidate;
}

function humanCreatedLabel(createdAt) {
	if (!createdAt) {
		return null;
	}

	const date = new Date(createdAt);
	if (Number.isNaN(date.getTime())) {
		return null;
	}

	const formatter = new Intl.DateTimeFormat("fr-FR", {
		timeZone: DEFAULT_TIMEZONE,
		day: "2-digit",
		month: "2-digit",
		year: "numeric",
		hour: "2-digit",
		minute: "2-digit",
		hour12: false,
	});
	const parts = Object.fromEntries(
		formatter.formatToParts(date).map((part) => [part.type, part.value])
	);
	return `${parts.day}.${parts.month}.${parts.year}, ${parts.hour}:${parts.minute}`;
}

function landStorageKey(slug) {
	return `land:${slug}`;
}

function seenTimezoneKey(timezone) {
	return `timezone:${timezone}`;
}

function rateLimitKey(ip) {
	return `rate-limit:${ip}`;
}

function normalizeIp(ip) {
	return String(ip || "unknown")
		.split(",")[0]
		.trim()
		.replace(/[^a-fA-F0-9:\.]/g, "") || "unknown";
}

function clientIp(request) {
	return normalizeIp(
		request.headers.get("CF-Connecting-IP") ||
			request.headers.get("X-Forwarded-For") ||
			request.headers.get("True-Client-IP") ||
			"unknown"
	);
}

function wantsHtml(request) {
	const accept = request.headers.get("Accept") || "";
	return accept.includes("text/html");
}

function sameOriginSubmission(request, url) {
	const origin = request.headers.get("Origin");
	if (origin && origin !== url.origin) {
		return false;
	}

	const referer = request.headers.get("Referer");
	if (!origin && referer) {
		try {
			return new URL(referer).origin === url.origin;
		} catch {
			return false;
		}
	}

	return true;
}

function readCookie(request, name) {
	const raw = request.headers.get("Cookie") || "";
	const entries = raw.split(/;\s*/);

	for (const entry of entries) {
		const [key, ...rest] = entry.split("=");
		if (key === name) {
			return decodeURIComponent(rest.join("="));
		}
	}

	return "";
}

async function issueFormToken(secret) {
	const payload = {
		issuedAt: Math.floor(Date.now() / 1000),
		nonce: crypto.randomUUID().replace(/-/g, ""),
	};
	const encodedPayload = encodeBase64Url(JSON.stringify(payload));
	const signature = await signValue(secret, encodedPayload);
	return `${encodedPayload}.${signature}`;
}

async function verifyFormToken(secret, token) {
	const [encodedPayload, signature] = String(token || "").split(".");

	if (!encodedPayload || !signature) {
		return { valid: false };
	}

	const expected = await signValue(secret, encodedPayload);
	if (!timingSafeEqual(expected, signature)) {
		return { valid: false };
	}

	try {
		const payload = JSON.parse(decodeBase64Url(encodedPayload));
		if (!payload || typeof payload.issuedAt !== "number") {
			return { valid: false };
		}

		const now = Math.floor(Date.now() / 1000);
		if (now - payload.issuedAt > FORM_TOKEN_TTL_SECONDS) {
			return { valid: false };
		}

		return { valid: true, payload };
	} catch {
		return { valid: false };
	}
}

async function signValue(secret, value) {
	const key = await crypto.subtle.importKey(
		"raw",
		new TextEncoder().encode(secret),
		{ name: "HMAC", hash: "SHA-256" },
		false,
		["sign"]
	);
	const signature = await crypto.subtle.sign("HMAC", key, new TextEncoder().encode(value));
	return encodeBase64UrlBytes(new Uint8Array(signature));
}

function encodeBase64Url(value) {
	return btoa(value).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
}

function decodeBase64Url(value) {
	const normalized = value.replace(/-/g, "+").replace(/_/g, "/");
	const padded = normalized + "=".repeat((4 - (normalized.length % 4 || 4)) % 4);
	return atob(padded);
}

function encodeBase64UrlBytes(bytes) {
	let binary = "";
	for (const byte of bytes) {
		binary += String.fromCharCode(byte);
	}
	return encodeBase64Url(binary);
}

function timingSafeEqual(left, right) {
	if (left.length !== right.length) {
		return false;
	}

	let mismatch = 0;
	for (let index = 0; index < left.length; index += 1) {
		mismatch |= left.charCodeAt(index) ^ right.charCodeAt(index);
	}
	return mismatch === 0;
}

function buildCookie(name, value, options) {
	const parts = [`${name}=${encodeURIComponent(value)}`];

	if (options.maxAge !== undefined) {
		parts.push(`Max-Age=${options.maxAge}`);
	}

	if (options.path) {
		parts.push(`Path=${options.path}`);
	}

	if (options.sameSite) {
		parts.push(`SameSite=${options.sameSite}`);
	}

	if (options.httpOnly) {
		parts.push("HttpOnly");
	}

	if (options.secure) {
		parts.push("Secure");
	}

	return parts.join("; ");
}

function escapeHtml(value) {
	return String(value ?? "")
		.replace(/&/g, "&amp;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;")
		.replace(/"/g, "&quot;")
		.replace(/'/g, "&#039;");
}

function requireAppSecret(env) {
	if (!env.APP_SECRET) {
		throw new Error("Le secret APP_SECRET manque dans la configuration Workers.");
	}

	return env.APP_SECRET;
}

function responseError(message, status) {
	const error = new Error(message);
	error.status = status;
	return error;
}
