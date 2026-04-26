/* O kernel: layered navigation + exponential zoom */

(() => {
  const STORAGE_PREFIX = 'o:layer:';
  const LAST_KEY = 'o:layer:last';
  const NEGATIVE_KEY = 'o:layer:negative';
  const APPARITIONS_LAST_AT_KEY = 'o:apparitions:last_at';
  const FLASH_LAST_BLACK_AT_KEY = 'o:flash:last_black_at';

  const SCORE_START_AT_KEY = 'o:score:start_at';
  const SCORE_NAV_COUNT_KEY = 'o:score:nav_count';
  const SCORE_NAV_FAST_KEY = 'o:score:nav_fast';
  const SCORE_LAST_NAV_AT_KEY = 'o:score:last_nav_at';
  const SCORE_BLOCKED_KEY = 'o:score:blocked';
  const SOUND_MUTED_KEY = 'o:sound:muted';

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  let baseParity = '0';
  let apparitionTimer = null;
  let apparitionHideTimer = null;
  let audioContext = null;
  let audioMaster = null;

  function forcedParityFromDom() {
    const v = document.body?.dataset?.oParity;
    if (v === '0' || v === '1') return v;
    return null;
  }

  function canonicalKey(url) {
    return STORAGE_PREFIX + url.pathname + url.search; // ignore hash for stability
  }

  function readParityForUrl(url) {
    const key = canonicalKey(url);
    let parity = sessionStorage.getItem(key);
    if (parity !== '0' && parity !== '1') {
      parity = sessionStorage.getItem(LAST_KEY);
      if (parity !== '0' && parity !== '1') parity = '0';
      sessionStorage.setItem(key, parity);
    }
    sessionStorage.setItem(LAST_KEY, parity);
    return parity;
  }

  function readNegative() {
    return sessionStorage.getItem(NEGATIVE_KEY) === '1' ? 1 : 0;
  }

  function writeNegative(n) {
    sessionStorage.setItem(NEGATIVE_KEY, n ? '1' : '0');
  }

  function applyParity(parity) {
    // Visual parity can be flipped by the "negative" toggle (XOR).
    const negative = readNegative();
    const inverted = (parity === '1') !== (negative === 1);
    document.body.classList.toggle('o-inverted', inverted);
    document.body.classList.toggle('inverted', inverted); // backwards-compatible alias
  }

  function toggleNegative() {
    const next = 1 - readNegative();
    writeNegative(next);
    applyParity(baseParity);
  }

  function isModifiedClick(e) {
    return e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey;
  }

  function isInteractiveTarget(target) {
    const el = target instanceof Element ? target : null;
    if (!el) return false;
    return Boolean(
      el.closest('a[href], button, input, textarea, select, label, summary, [role="button"], [onclick]')
    );
  }

  function cancelZoomArtifacts() {
    // When coming back from bfcache, the old animation/overlay can still exist.
    const body = document.body;
    const animations = body && typeof body.getAnimations === 'function' ? body.getAnimations() : [];
    animations.forEach((a) => a.cancel());
    document.querySelectorAll('[data-o-zoom-overlay]').forEach((el) => el.remove());
    document.getElementById('o-apparitions')?.remove();
    document.body.classList.remove('o-zooming');
    document.body.style.transform = '';
    document.body.style.filter = '';
  }

  function isTextEntryFocused() {
    const el = document.activeElement;
    if (!el || !(el instanceof HTMLElement)) return false;
    if (el.isContentEditable) return true;
    const tag = el.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
  }

  function randomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
  }

  function clamp(n, lo, hi) {
    return Math.max(lo, Math.min(hi, n));
  }

  function clamp01(n) {
    return clamp(n, 0, 1);
  }

  function readNumber(key) {
    const raw = sessionStorage.getItem(key);
    const n = Number(raw);
    return Number.isFinite(n) ? n : 0;
  }

  function writeNumber(key, n) {
    sessionStorage.setItem(key, String(Number.isFinite(n) ? n : 0));
  }

  function bumpInt(key, delta = 1) {
    const next = Math.floor(readNumber(key) || 0) + delta;
    writeNumber(key, next);
    return next;
  }

  function isSoundMuted() {
    return sessionStorage.getItem(SOUND_MUTED_KEY) === '1';
  }

  function setSoundMuted(muted) {
    sessionStorage.setItem(SOUND_MUTED_KEY, muted ? '1' : '0');
  }

  function ensureAudio() {
    if (isSoundMuted()) return null;
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return null;
    if (!audioContext || audioContext.state === 'closed') {
      audioContext = new Ctx();
      audioMaster = audioContext.createGain();
      audioMaster.gain.value = 0.055;
      audioMaster.connect(audioContext.destination);
    }
    return audioContext;
  }

  function unlockAudio() {
    const ctx = ensureAudio();
    if (!ctx) return Promise.resolve(false);
    if (ctx.state !== 'suspended') return Promise.resolve(ctx.state === 'running');
    return ctx
      .resume()
      .then(() => ctx.state === 'running')
      .catch(() => false);
  }

  function playTone({ freq, durationSec, type, gain, offsetSec = 0 }) {
    if (isSoundMuted()) return;
    const ctx = ensureAudio();
    if (!ctx || !audioMaster) return;

    const startAt = ctx.currentTime + Math.max(0, offsetSec);
    const endAt = startAt + Math.max(0.02, durationSec);

    const osc = ctx.createOscillator();
    osc.type = type;
    osc.frequency.setValueAtTime(freq, startAt);

    const g = ctx.createGain();
    g.gain.setValueAtTime(0.0001, startAt);
    g.gain.exponentialRampToValueAtTime(Math.max(0.0002, gain), startAt + 0.01);
    g.gain.exponentialRampToValueAtTime(0.0001, endAt);

    osc.connect(g);
    g.connect(audioMaster);

    osc.start(startAt);
    osc.stop(endAt + 0.03);
  }

  function ensureSessionStartAt() {
    const existing = readNumber(SCORE_START_AT_KEY);
    if (existing > 0) return existing;
    const now = Date.now();
    writeNumber(SCORE_START_AT_KEY, now);
    return now;
  }

  function entrySeenKey(id) {
    return `o:apparitions:seen:${id}`;
  }

  function readEntrySeenAt(id) {
    const raw = sessionStorage.getItem(entrySeenKey(id));
    const n = Number(raw);
    return Number.isFinite(n) ? n : 0;
  }

  function writeEntrySeenAt(id, ms) {
    sessionStorage.setItem(entrySeenKey(id), String(ms));
  }

  function rarityWeight(rarity) {
    if (rarity === 'common') return 12;
    if (rarity === 'uncommon') return 5;
    if (rarity === 'rare') return 1.5;
    if (rarity === 'mythic') return 0.35;
    return 1;
  }

  function rarityChance(rarity) {
    if (rarity === 'common') return 1;
    if (rarity === 'uncommon') return 0.65;
    if (rarity === 'rare') return 0.22;
    if (rarity === 'mythic') return 0.06;
    return 0.25;
  }

  function rarityCooldownMs(rarity) {
    if (rarity === 'common') return 45_000;
    if (rarity === 'uncommon') return 90_000;
    if (rarity === 'rare') return 4 * 60_000;
    if (rarity === 'mythic') return 12 * 60_000;
    return 2 * 60_000;
  }

  function sampleWeightedWithoutReplacement(items, count) {
    const pool = items.slice();
    const picked = [];
    const safeCount = Math.max(0, Math.min(count, pool.length));

    for (let k = 0; k < safeCount; k++) {
      let total = 0;
      for (const it of pool) total += Math.max(0, it.weight || 0);
      if (!(total > 0)) {
        const fallback = pool.pop();
        if (fallback) picked.push(fallback);
        continue;
      }

      let r = Math.random() * total;
      let idx = 0;
      for (; idx < pool.length; idx++) {
        r -= Math.max(0, pool[idx].weight || 0);
        if (r <= 0) break;
      }
      if (idx >= pool.length) idx = pool.length - 1;
      picked.push(pool.splice(idx, 1)[0]);
    }

    return picked;
  }

  function readLastApparitionAt() {
    const raw = sessionStorage.getItem(APPARITIONS_LAST_AT_KEY);
    const n = Number(raw);
    return Number.isFinite(n) ? n : 0;
  }

  function writeLastApparitionAt(ms) {
    sessionStorage.setItem(APPARITIONS_LAST_AT_KEY, String(ms));
  }

  function pickApparitionTargets() {
    const entrypoints = [
      { id: 'install', label: 'INSTALL', href: '/install', rarity: 'common' },
      { id: 'land', label: 'LAND', href: '/land', rarity: 'common' },
      { id: 'shore', label: 'SHORE', href: '/shore', rarity: 'common' },
      { id: 'bato', label: 'BATO', href: '/bato', rarity: 'uncommon' },
      { id: 'dashboard', label: 'DASHBOARD', href: '/dashboard', rarity: 'uncommon' },
      { id: 'aza', label: 'AZA', href: '/aza', rarity: 'rare' },
      { id: 'silence', label: 'SILENCE', href: '/silence', rarity: 'rare' },
    ];

    const now = Date.now();
    const path = window.location.pathname.replace(/\/+$/, '') || '/';
    const normalized = path === '/' ? '/install' : path;

    const filtered = entrypoints.filter((e) => {
      if (normalized === e.href) return false;
      if (normalized.startsWith('/aza/') && e.href === '/aza') return false;
      return true;
    });

    const targetCount = window.matchMedia('(max-width: 520px)').matches ? 2 : randomInt(2, 4);

    function materialize(candidates) {
      return candidates.map((e) => ({
        ...e,
        weight: rarityWeight(e.rarity),
      }));
    }

    function gatedCandidates(candidates, { ignoreCooldown, ignoreChance }) {
      const out = [];
      for (const e of candidates) {
        if (!ignoreCooldown) {
          const lastSeen = readEntrySeenAt(e.id);
          const cooldownMs = rarityCooldownMs(e.rarity);
          if (now - lastSeen < cooldownMs) continue;
        }
        if (!ignoreChance) {
          const chance = rarityChance(e.rarity);
          if (chance < 1 && Math.random() > chance) continue;
        }
        out.push(e);
      }
      return out;
    }

    let candidates = gatedCandidates(filtered, { ignoreCooldown: false, ignoreChance: false });
    if (candidates.length === 0) candidates = gatedCandidates(filtered, { ignoreCooldown: false, ignoreChance: true });
    if (candidates.length === 0) candidates = gatedCandidates(filtered, { ignoreCooldown: true, ignoreChance: true });

    const picked = sampleWeightedWithoutReplacement(materialize(candidates), targetCount);
    return picked;
  }

  function mountApparitions(targets) {
    const existing = document.getElementById('o-apparitions');
    if (existing) existing.remove();

    const root = document.createElement('div');
    root.id = 'o-apparitions';
    root.className = 'o-apparitions is-hidden';
    root.setAttribute('role', 'region');
    root.setAttribute('aria-label', 'Apparitions');

    const sig = document.createElement('div');
    sig.className = 'o-apparitions__sig';
    sig.textContent = '⋯';
    root.appendChild(sig);

    const list = document.createElement('div');
    list.className = 'o-apparitions__list';
    root.appendChild(list);

    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    const glyphs = chars.split('');
    const intervalMs = 28;
    const staggerMs = 55;

    function buildAnimatedText(container, text) {
      container.textContent = '';

      const spans = [];
      for (const ch of text) {
        const s = document.createElement('span');
        s.className = 'o-flip-char';
        s.textContent = ch === ' ' ? ' ' : ' ';
        spans.push(s);
        container.appendChild(s);
      }

      if (prefersReducedMotion) {
        spans.forEach((s, i) => {
          s.textContent = text[i] ?? '';
          s.classList.add('is-locked');
        });
        return;
      }

      spans.forEach((span, i) => {
        const target = text[i] ?? '';
        window.setTimeout(() => {
          if (target === ' ') {
            span.textContent = ' ';
            span.classList.add('is-locked');
            return;
          }

          let ticks = 0;
          const maxTicks = randomInt(6, 11);
          const id = window.setInterval(() => {
            ticks++;
            if (ticks >= maxTicks) {
              span.textContent = target;
              span.classList.add('is-locked');
              window.clearInterval(id);
              return;
            }
            span.textContent = glyphs[Math.floor(Math.random() * glyphs.length)];
          }, intervalMs);
        }, i * staggerMs);
      });
    }

    for (const t of targets) {
      const a = document.createElement('a');
      a.className = 'o-apparition-link';
      a.href = t.href;
      a.setAttribute('data-o-layer', '');
      a.setAttribute('aria-label', t.label);

      const sr = document.createElement('span');
      sr.className = 'o-visually-hidden';
      sr.textContent = t.label;
      a.appendChild(sr);

      const anim = document.createElement('span');
      anim.className = 'o-flip';
      anim.setAttribute('aria-hidden', 'true');
      a.appendChild(anim);

      list.appendChild(a);
      buildAnimatedText(anim, t.label);
    }

    const stamp = Date.now();
    for (const t of targets) {
      if (t.id) writeEntrySeenAt(t.id, stamp);
    }

    document.body.appendChild(root);

    requestAnimationFrame(() => {
      root.classList.remove('is-hidden');
    });

    const onKey = (e) => {
      if (e.key === 'Escape') hide();
    };
    window.addEventListener('keydown', onKey);

    let dismissed = false;
    function hide() {
      if (dismissed) return;
      dismissed = true;

      window.removeEventListener('keydown', onKey);
      if (apparitionHideTimer) {
        window.clearTimeout(apparitionHideTimer);
        apparitionHideTimer = null;
      }

      root.classList.add('is-hidden');
      window.setTimeout(() => root.remove(), 260);
    }

    const ttlMs = 11000;
    apparitionHideTimer = window.setTimeout(hide, ttlMs);
  }

  function scheduleApparitions() {
    if (apparitionTimer) window.clearTimeout(apparitionTimer);
    if (apparitionHideTimer) {
      window.clearTimeout(apparitionHideTimer);
      apparitionHideTimer = null;
    }

    const lastAt = readLastApparitionAt();
    const now = Date.now();
    const minGapMs = 18000;
    const initialDelayMs = randomInt(2600, 7200);
    const waitMs = Math.max(initialDelayMs, minGapMs - (now - lastAt));

    apparitionTimer = window.setTimeout(() => {
      // Skip if user is typing or tab is hidden; reschedule soon.
      if (document.hidden || isTextEntryFocused()) {
        scheduleApparitions();
        return;
      }

      writeLastApparitionAt(Date.now());
      mountApparitions(pickApparitionTargets());

      // Next apparition in ~25–55s.
      apparitionTimer = window.setTimeout(scheduleApparitions, randomInt(25000, 55000));
    }, waitMs);
  }

  function pickFlashTheme() {
    const now = Date.now();
    const lastBlackAt = readNumber(FLASH_LAST_BLACK_AT_KEY);

    const blackChance = 0.06; // rare
    const blackCooldownMs = 2 * 60_000;
    const canBlack = now - lastBlackAt >= blackCooldownMs;

    const isBlack = canBlack && Math.random() < blackChance;
    if (isBlack) writeNumber(FLASH_LAST_BLACK_AT_KEY, now);

    if (isBlack) {
      return {
        mode: 'black',
        bg: '#000',
        fg: 'rgb(var(--o-cream-rgb))',
        accent: 'rgb(var(--o-cream-rgb))',
        hudOpacity: 0.92,
      };
    }
    return {
      mode: 'white',
      bg: '#fff',
      fg: 'rgb(var(--o-emerald-rgb))',
      accent: 'rgb(var(--o-emerald-rgb))',
      hudOpacity: 0.78,
    };
  }

  function playFlashSound(themeMode) {
    unlockAudio()
      .then((ok) => {
        if (!ok) return;
        if (themeMode === 'black') {
          playTone({ freq: 92, durationSec: 0.09, type: 'sine', gain: 0.06 });
          playTone({ freq: 184, durationSec: 0.07, type: 'sine', gain: 0.035, offsetSec: 0.05 });
          return;
        }
        playTone({ freq: 880, durationSec: 0.055, type: 'triangle', gain: 0.03 });
      })
      .catch(() => {});
  }

  function computeNote() {
    const startAt = ensureSessionStartAt();
    const minutes = Math.max(0, (Date.now() - startAt) / 60_000);

    const navCount = Math.max(0, Math.floor(readNumber(SCORE_NAV_COUNT_KEY)));
    const fastCount = Math.max(0, Math.floor(readNumber(SCORE_NAV_FAST_KEY)));
    const blockedCount = Math.max(0, Math.floor(readNumber(SCORE_BLOCKED_KEY)));

    const blockedPenalty = Math.min(0.62, blockedCount * 0.12);
    const fastPenalty = Math.min(0.55, fastCount * 0.10);
    const timeBonus = Math.min(0.20, minutes * 0.03);
    const learningBonus = Math.min(0.40, navCount * 0.05);

    const comprehension = clamp01(0.95 - blockedPenalty);
    const learning = clamp01(0.50 + learningBonus - blockedPenalty * 0.45);
    const patience = clamp01(0.60 + timeBonus - fastPenalty);
    const courtesy = clamp01(0.70 - blockedPenalty * 0.35 - fastPenalty * 0.18);

    const overall = clamp01(0.34 * comprehension + 0.26 * learning + 0.24 * patience + 0.16 * courtesy);

    return { overall, comprehension, learning, patience, courtesy };
  }

  function formatNote(n) {
    return clamp01(n).toFixed(2);
  }

  function playZoomToPoint({ x, y, color }) {
    return new Promise((resolve) => {
      const theme = pickFlashTheme();
      const note = computeNote();

      const overlay = document.createElement('div');
      overlay.setAttribute('data-o-zoom-overlay', '1');
      overlay.setAttribute('aria-hidden', 'true');
      overlay.style.position = 'fixed';
      overlay.style.inset = '0';
      overlay.style.pointerEvents = 'none';
      overlay.style.zIndex = '100000';
      overlay.style.background = theme.bg;
      overlay.style.color = theme.fg;
      document.documentElement.appendChild(overlay);

      const hud = document.createElement('div');
      hud.style.position = 'absolute';
      hud.style.inset = '0';
      hud.style.display = 'grid';
      hud.style.placeItems = 'center';
      hud.style.padding = '1.5rem';
      hud.style.fontFamily = 'var(--o-font)';
      hud.style.letterSpacing = '0.18em';
      hud.style.textTransform = 'uppercase';
      hud.style.textAlign = 'center';
      hud.style.userSelect = 'none';
      hud.style.whiteSpace = 'pre-line';
      hud.style.opacity = String(theme.hudOpacity);
      hud.textContent =
        `LECTURE\n` +
        `NOTE ${formatNote(note.overall)}\n` +
        `COMPR ${formatNote(note.comprehension)}  APP ${formatNote(note.learning)}\n` +
        `PAT ${formatNote(note.patience)}   COUR ${formatNote(note.courtesy)}`;
      overlay.appendChild(hud);

      const dot = document.createElement('div');
      dot.style.position = 'absolute';
      dot.style.left = `${x}px`;
      dot.style.top = `${y}px`;
      dot.style.width = '12px';
      dot.style.height = '12px';
      dot.style.borderRadius = '9999px';
      dot.style.transform = 'translate(-50%, -50%) scale(1)';
      // Keep the dot readable on white (avoid cream-on-white on inverted pages).
      dot.style.background = theme.accent;
      overlay.appendChild(dot);

      playFlashSound(theme.mode);

      const scale = 18;
      const cx = window.innerWidth / 2;
      const cy = window.innerHeight / 2;
      const tx = cx - x * scale;
      const ty = cy - y * scale;

      document.body.classList.add('o-zooming');

      const durationMs = 520;
      const easing = 'cubic-bezier(0.05, 0.78, 0.12, 1)';

      document.body.animate(
        [
          { transform: 'translate(0px, 0px) scale(1)', filter: 'blur(0px)' },
          { transform: `translate(${tx}px, ${ty}px) scale(${scale})`, filter: 'blur(1.5px)' },
        ],
        { duration: durationMs, easing, fill: 'forwards' }
      );

      dot.animate(
        [
          { transform: 'translate(-50%, -50%) scale(1)', opacity: 1 },
          { transform: 'translate(-50%, -50%) scale(20)', opacity: 1, offset: 0.35 },
          { transform: 'translate(-50%, -50%) scale(140)', opacity: 1 },
        ],
        { duration: durationMs, easing, fill: 'forwards' }
      );

      window.setTimeout(resolve, durationMs + 30);
    });
  }

  // Apply the layer parity for this page load.
  const currentUrl = new URL(window.location.href);
  const forced = forcedParityFromDom();
  if (forced) {
    baseParity = forced;
    sessionStorage.setItem(canonicalKey(currentUrl), baseParity);
    sessionStorage.setItem(LAST_KEY, baseParity);
    applyParity(baseParity);
  } else {
    baseParity = readParityForUrl(currentUrl);
    applyParity(baseParity);
  }

  // Internal recommended links: zoom -> navigate; next page loads with inverted palette.
  // Opt-in via data-o-layer so we can keep the ritual calm on "utility" links.
  document.addEventListener(
    'click',
    (e) => {
      const target = e.target instanceof Element ? e.target : e.target instanceof Node ? e.target.parentElement : null;
      const a = target ? target.closest('a[href]') : null;
      if (!a) return;
      if (!a.hasAttribute('data-o-layer')) return;
      if (a.hasAttribute('data-o-nozoom')) return;
      if (a.hasAttribute('download')) return;
      if (a.target && a.target !== '' && a.target !== '_self') return;
      if (isModifiedClick(e)) return;

      const href = a.getAttribute('href');
      if (!href || href.startsWith('#')) return;

      const url = new URL(href, window.location.href);
      if (url.origin !== window.location.origin) return;

      const now = Date.now();
      bumpInt(SCORE_NAV_COUNT_KEY, 1);
      const lastNavAt = readNumber(SCORE_LAST_NAV_AT_KEY);
      if (lastNavAt > 0 && now - lastNavAt < 900) bumpInt(SCORE_NAV_FAST_KEY, 1);
      writeNumber(SCORE_LAST_NAV_AT_KEY, now);

      // Decide next layer and persist it for the destination URL.
      const nextParity = String(1 - Number(baseParity));
      sessionStorage.setItem(canonicalKey(url), nextParity);
      sessionStorage.setItem(LAST_KEY, nextParity);

      if (prefersReducedMotion || typeof document.body?.animate !== 'function') {
        window.location.href = url.href;
        return;
      }

      e.preventDefault();
      const color = window.getComputedStyle(a).color;
      playZoomToPoint({ x: e.clientX, y: e.clientY, color })
        .catch(() => {})
        .finally(() => {
          window.location.href = url.href;
        });
    },
    { capture: true }
  );

  // NEGATIVE: flip palette on demand (without affecting the step parity).
  document.addEventListener('click', (e) => {
    if (e.defaultPrevented) return;
    if (isModifiedClick(e)) return;
    if (e.target instanceof Element && e.target.closest('#o-apparitions')) return;
    if (isInteractiveTarget(e.target)) return;
    toggleNegative();
  });

  document.addEventListener('keydown', (e) => {
    if ((e.key === 'm' || e.key === 'M') && !isTextEntryFocused()) {
      setSoundMuted(!isSoundMuted());
      return;
    }
    if (e.key !== 'n' && e.key !== 'N') return;
    const target = e.target;
    if (
      target instanceof HTMLElement &&
      (target.isContentEditable || target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT')
    ) {
      return;
    }
    toggleNegative();
  });

  // Back/forward cache: restore parity and clean up.
  window.addEventListener('pageshow', () => {
    cancelZoomArtifacts();
    const url = new URL(window.location.href);
    const forced = forcedParityFromDom();
    if (forced) {
      baseParity = forced;
      sessionStorage.setItem(canonicalKey(url), baseParity);
      sessionStorage.setItem(LAST_KEY, baseParity);
      applyParity(baseParity);
      scheduleApparitions();
      return;
    }
    baseParity = readParityForUrl(url);
    applyParity(baseParity);
    scheduleApparitions();
  });

  // Try to unlock audio as early as possible (still requires a user gesture in most browsers).
  const tryUnlock = () => {
    if (isSoundMuted()) return;
    unlockAudio().then((ok) => {
      if (!ok) return;
      window.removeEventListener('pointerdown', tryUnlock, true);
      window.removeEventListener('keydown', tryUnlock, true);
    });
  };
  window.addEventListener('pointerdown', tryUnlock, { capture: true, passive: true });
  window.addEventListener('keydown', tryUnlock, true);

  scheduleApparitions();
})();
