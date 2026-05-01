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
    document.dispatchEvent(new CustomEvent('o:blocked'));
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

  // Show a persistent, dismissible message in the viewer.
  function showViewerMessage({ html, actions = [] }) {
    const viewer = document.querySelector('.viewer-inner');
    if (!viewer) return;

    // Remove any existing message first.
    viewer.querySelectorAll('.aza-lock-msg').forEach(el => el.remove());

    const msg = document.createElement('div');
    msg.className = 'aza-lock-msg';
    msg.setAttribute('role', 'status');
    Object.assign(msg.style, {
      marginBottom: '1rem',
      padding: '0.75rem 0.9rem',
      border: '1px solid var(--o-line)',
      background: 'var(--o-fill)',
      boxShadow: 'var(--o-shadow)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: '0.75rem',
    });

    const box = document.createElement('div');
    Object.assign(box.style, {
      display: 'grid',
      gap: '0.6rem',
      justifyItems: 'start',
    });

    const text = document.createElement('span');
    text.innerHTML = html;
    box.appendChild(text);

    if (actions.length > 0) {
      const row = document.createElement('div');
      Object.assign(row.style, {
        display: 'flex',
        gap: '0.5rem',
        flexWrap: 'wrap',
      });

      for (const action of actions) {
        if (!action || !action.href || !action.label) continue;
        const link = document.createElement('a');
        link.href = action.href;
        link.textContent = action.label;
        link.setAttribute('data-o-layer', '');
        Object.assign(link.style, {
          padding: '0.45rem 0.7rem',
          border: '1px solid var(--o-line)',
          textDecoration: 'none',
          color: 'inherit',
          background: 'var(--o-bg)',
          fontSize: '0.82rem',
          letterSpacing: '0.08em',
          textTransform: 'uppercase',
        });
        row.appendChild(link);
      }

      if (row.childElementCount > 0) box.appendChild(row);
    }

    const close = document.createElement('button');
    close.textContent = '×';
    Object.assign(close.style, {
      background: 'none',
      border: 'none',
      cursor: 'pointer',
      fontSize: '1.1rem',
      lineHeight: '1',
      opacity: '0.5',
      padding: '0',
      flexShrink: '0',
    });
    close.setAttribute('aria-label', 'Fermer');
    close.addEventListener('click', () => msg.remove());

    msg.appendChild(box);
    msg.appendChild(close);
    viewer.prepend(msg);
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
      a.title = `Portail verrouillé — ouvre d'abord le portail ${String(unlocked).padStart(2, '0')}.`;
    } else {
      a.setAttribute('aria-disabled', 'false');
      a.setAttribute('data-o-layer', '');
      delete a.dataset.locked;
      a.removeAttribute('title');
    }
  }

  // Guidance when user clicks a locked portal.
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

      showViewerMessage({
        html: `Portail verrouillé. Ouvre d'abord le portail <strong>${String(required).padStart(2, '0')}</strong>.`,
        actions: [
          { label: `Portail ${String(required).padStart(2, '0')}`, href: `/aza/${required}` },
          { label: 'LAND', href: '/land' },
        ],
      });
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
      a.removeAttribute('title');
      continue;
    }
    a.setAttribute('aria-disabled', 'true');
    a.removeAttribute('data-o-layer');
    a.dataset.locked = '1';
    a.title = `Accès verrouillé — ouvre les ${String(COUNT).padStart(2, '0')} portails pour entrer.`;
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

      const remaining = COUNT - visitedMax;
      showViewerMessage({
        html: `Accès verrouillé. Il reste <strong>${remaining}</strong> portail${remaining > 1 ? 's' : ''} à ouvrir.`,
        actions: [
          { label: `Portail ${String(clamp(visitedMax + 1, 1, COUNT)).padStart(2, '0')}`, href: `/aza/${clamp(visitedMax + 1, 1, COUNT)}` },
          { label: 'LAND', href: '/land' },
        ],
      });
    },
    { capture: true }
  );
})();
