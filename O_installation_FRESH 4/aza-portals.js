(() => {
  const COUNT = Number(document.body?.dataset?.azaCount || 5);
  const KEY_VISITED_MAX = 'o:aza:visited_max';
  const SCORE_BLOCKED_KEY = 'o:score:blocked';

  function clamp(n, lo, hi) {
    return Math.max(lo, Math.min(hi, n));
  }

  function bumpBlocked() {
    const raw = sessionStorage.getItem(SCORE_BLOCKED_KEY);
    const n = Number(raw);
    const next = (Number.isFinite(n) ? Math.floor(n) : 0) + 1;
    sessionStorage.setItem(SCORE_BLOCKED_KEY, String(next));
  }

  function currentPortal() {
    const p = Number(document.body?.dataset?.azaPortal || 0);
    if (!Number.isFinite(p)) return 0;
    return clamp(p, 0, COUNT);
  }

  function readVisitedMax() {
    const raw = sessionStorage.getItem(KEY_VISITED_MAX);
    const n = Number(raw);
    if (!Number.isFinite(n) || n < 0) return 0;
    return clamp(n, 0, COUNT);
  }

  function writeVisitedMax(n) {
    sessionStorage.setItem(KEY_VISITED_MAX, String(clamp(n, 0, COUNT)));
  }

  const p = currentPortal();
  let visitedMax = readVisitedMax();
  let unlocked = clamp(visitedMax + 1, 1, COUNT);

  // Progress rule: visiting portal N unlocks N+1.
  if (p > 0 && p <= unlocked) {
    visitedMax = Math.max(visitedMax, p);
    writeVisitedMax(visitedMax);
    unlocked = clamp(visitedMax + 1, 1, COUNT);
  }

  // Lock/unlock links by adding/removing data-o-layer (main.js only animates those).
  const links = Array.from(document.querySelectorAll('a[data-aza-portal]'));
  for (const a of links) {
    const n = Number(a.getAttribute('data-aza-portal') || 0);
    const allowed = Number.isFinite(n) && n <= unlocked;

    if (!allowed) {
      a.setAttribute('aria-disabled', 'true');
      a.removeAttribute('data-o-layer');
      a.dataset.locked = '1';
    } else {
      a.setAttribute('aria-disabled', 'false');
      a.setAttribute('data-o-layer', '');
      delete a.dataset.locked;
    }
  }

  // Optional: soft guidance if user tries to click a locked portal.
  document.addEventListener(
    'click',
    (e) => {
      const target = e.target instanceof Element ? e.target : e.target instanceof Node ? e.target.parentElement : null;
      const a = target ? target.closest('a[data-aza-portal]') : null;
      if (!a) return;
      if (a.dataset.locked !== '1') return;

      e.preventDefault();
      bumpBlocked();
      const required = unlocked;
      if (typeof a.animate === 'function') {
        a.animate(
          [
            { transform: 'translateX(0px)' },
            { transform: 'translateX(-4px)' },
            { transform: 'translateX(3px)' },
            { transform: 'translateX(0px)' },
          ],
          { duration: 260, easing: 'ease-out' }
        );
      }

      const msg = document.createElement('div');
      msg.setAttribute('role', 'status');
      msg.style.marginTop = '1rem';
      msg.style.padding = '0.75rem 0.9rem';
      msg.style.border = '1px solid var(--o-line)';
      msg.style.background = 'var(--o-fill)';
      msg.style.boxShadow = 'var(--o-shadow)';
      msg.textContent = `Ouvre d’abord le portail ${String(required).padStart(2, '0')}.`;

      const viewer = document.querySelector('.viewer-inner');
      if (viewer) {
        viewer.prepend(msg);
        window.setTimeout(() => msg.remove(), 1800);
      }
    },
    { capture: true }
  );

  // Gate "enter machine" until all portals have been opened (visitedMax === COUNT).
  const requiresComplete = Array.from(document.querySelectorAll('a[data-aza-requires="complete"]'));
  const isComplete = visitedMax >= COUNT;
  for (const a of requiresComplete) {
    if (isComplete) {
      a.setAttribute('aria-disabled', 'false');
      a.setAttribute('data-o-layer', '');
      delete a.dataset.locked;
      continue;
    }
    a.setAttribute('aria-disabled', 'true');
    a.removeAttribute('data-o-layer');
    a.dataset.locked = '1';
  }

  document.addEventListener(
    'click',
    (e) => {
      const target = e.target instanceof Element ? e.target : e.target instanceof Node ? e.target.parentElement : null;
      const a = target ? target.closest('a[data-aza-requires="complete"]') : null;
      if (!a) return;
      if (a.dataset.locked !== '1') return;
      e.preventDefault();
      bumpBlocked();

      const msg = document.createElement('div');
      msg.setAttribute('role', 'status');
      msg.style.marginTop = '1rem';
      msg.style.padding = '0.75rem 0.9rem';
      msg.style.border = '1px solid var(--o-line)';
      msg.style.background = 'var(--o-fill)';
      msg.style.boxShadow = 'var(--o-shadow)';
      msg.textContent = `Ouvre d’abord les ${String(COUNT).padStart(2, '0')} portails.`;

      const viewer = document.querySelector('.viewer-inner');
      if (viewer) {
        viewer.prepend(msg);
        window.setTimeout(() => msg.remove(), 1800);
      }
    },
    { capture: true }
  );
})();
