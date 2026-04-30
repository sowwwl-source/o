(() => {
  // ─── CONFIG ────────────────────────────────────────────────
  const SPHERE_R  = 2.4;
  const CAM_DIST  = 6.0;
  const PT_BASE   = 5;
  const BLINK_SPD = 0.72;
  const AUTO_ROT  = 0.0005;
  const FRICTION  = 0.90;
  const FLY_EASE  = 0.058;

  // ─── DATA ──────────────────────────────────────────────────
  const LANDS    = window.STR3M_LANDS    || [];
  const LIAISONS = window.STR3M_LIAISONS || {};
  const ZIPS     = window.STR3M_ZIPS     || {};
  const SAVED_FLOWS = window.STR3M_FLOWS || [];

  // ─── STATE ─────────────────────────────────────────────────
  let rotX = 0.25, rotY = 0;
  let velX = 0, velY = 0;
  let dragging = false, hasDragged = false;
  let dragStartX = 0, dragStartY = 0, lastMX = 0, lastMY = 0;
  let selected = null;
  let projected = [];

  // ─── FL0W STATE ────────────────────────────────────────────
  const flow = {
    mode: 'off',       // 'off' | 'building' | 'touring'
    steps: [],         // usernames in sequence (building / touring)
    step: 0,           // current step index in touring
    flyTargetX: null,
    flyTargetY: null,
    flyDone: false,
  };

  // ─── CANVAS ────────────────────────────────────────────────
  const canvas = document.getElementById('str3m-canvas');
  const ctx    = canvas.getContext('2d');
  let W, H, CX, CY, scale;

  function resize() {
    const dpr = window.devicePixelRatio || 1;
    W = canvas.offsetWidth; H = canvas.offsetHeight;
    canvas.width = W * dpr; canvas.height = H * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    CX = W / 2; CY = H / 2;
    scale = Math.min(W, H) * 0.36;
  }

  // ─── FIBONACCI SPHERE ──────────────────────────────────────
  const GOLDEN = Math.PI * (3 - Math.sqrt(5));

  function buildPoints() {
    const n = LANDS.length;
    return LANDS.map((land, i) => {
      const y     = n < 2 ? 0 : 1 - (i / (n - 1)) * 2;
      const r     = Math.sqrt(Math.max(0, 1 - y * y));
      const theta = GOLDEN * i;
      return {
        x: r * Math.cos(theta) * SPHERE_R,
        y: y * SPHERE_R,
        z: r * Math.sin(theta) * SPHERE_R,
        phase: (i * 1.618) % (Math.PI * 2),
        land,
        // ZIP orbit particles (pre-computed polar offsets relative to point)
        zipOrbit: (ZIPS[land.username] || []).map((z, j, arr) => ({
          ...z,
          angle: (j / Math.max(1, arr.length)) * Math.PI * 2,
          speed: 0.4 + (j % 3) * 0.15,
        })),
      };
    });
  }

  const points = buildPoints();

  // ─── MATH ──────────────────────────────────────────────────
  function rotate(x, y, z, rx, ry) {
    const x1 =  x * Math.cos(ry) + z * Math.sin(ry);
    const z1 = -x * Math.sin(ry) + z * Math.cos(ry);
    const y2 = y  * Math.cos(rx) - z1 * Math.sin(rx);
    const z2 = y  * Math.sin(rx) + z1 * Math.cos(rx);
    return { x: x1, y: y2, z: z2 };
  }

  function project(x, y, z) {
    const d = CAM_DIST + z;
    if (d < 0.05) return null;
    return {
      sx:    CX + (x / d) * scale,
      sy:    CY - (y / d) * scale,
      depth: (z + SPHERE_R + CAM_DIST) / (CAM_DIST * 2 + SPHERE_R * 2),
    };
  }

  function fgColor(alpha) {
    const raw = getComputedStyle(document.documentElement)
      .getPropertyValue('--o-fg-rgb').trim() || '11 107 74';
    const [r, g, b] = raw.split(' ');
    return `rgba(${r},${g},${b},${alpha.toFixed(3)})`;
  }

  // ─── FLY-TO ────────────────────────────────────────────────
  function targetAnglesFor(p) {
    // Rotate view so point is at front-center of sphere
    const ry = -Math.atan2(p.x, p.z);
    const rx =  Math.atan2(p.y, Math.sqrt(p.x * p.x + p.z * p.z));
    return { rx, ry };
  }

  function flyTo(username) {
    const pt = points.find(p => p.land.username === username);
    if (!pt) return;
    const { rx, ry } = targetAnglesFor(pt);
    flow.flyTargetX = rx;
    flow.flyTargetY = ry;
    flow.flyDone = false;
  }

  // ─── DRAW ──────────────────────────────────────────────────
  function draw(ts) {
    requestAnimationFrame(draw);
    const t = ts * 0.001;

    // Fly animation
    if (flow.flyTargetX !== null) {
      let dY = flow.flyTargetY - rotY;
      while (dY >  Math.PI) dY -= 2 * Math.PI;
      while (dY < -Math.PI) dY += 2 * Math.PI;
      rotX += (flow.flyTargetX - rotX) * FLY_EASE;
      rotY += dY * FLY_EASE;
      velX = velY = 0;
      if (Math.abs(flow.flyTargetX - rotX) < 0.004 && Math.abs(dY) < 0.004) {
        rotX = flow.flyTargetX;
        rotY += dY;
        flow.flyTargetX = flow.flyTargetY = null;
        if (!flow.flyDone) {
          flow.flyDone = true;
          if (flow.mode === 'touring') showCurrentTourPanel();
        }
      }
    } else if (!dragging && flow.mode === 'off') {
      rotY += AUTO_ROT + velY;
      rotX += velX;
      velX *= FRICTION; velY *= FRICTION;
    }

    ctx.clearRect(0, 0, W, H);
    if (points.length === 0) return;

    const liaisOnUsers = new Set(
      Object.entries(LIAISONS)
        .filter(([, v]) => v.status === 'on')
        .map(([k]) => k)
    );

    // Project all points
    const frame = points.map(p => {
      const rv   = rotate(p.x, p.y, p.z, rotX, rotY);
      const proj = project(rv.x, rv.y, rv.z);
      if (!proj) return null;
      const blink  = 0.35 + 0.65 * (0.5 + 0.5 * Math.sin(t * BLINK_SPD + p.phase));
      const radius = PT_BASE * (0.55 + 0.75 * proj.depth);
      const alpha  = blink * (0.4 + 0.6 * proj.depth);
      return { p, proj, radius, alpha, depth: proj.depth };
    }).filter(Boolean);

    frame.sort((a, b) => a.depth - b.depth);
    projected = frame;

    const flowStepSet = new Set(flow.steps);
    const currentTourUser = flow.mode === 'touring' ? flow.steps[flow.step] : null;

    for (const f of frame) {
      const uname      = f.p.land.username;
      const isSelected = selected && selected.p.land.username === uname;
      const isLinked   = liaisOnUsers.has(uname);
      const inFlow     = flowStepSet.has(uname);
      const isTourHere = uname === currentTourUser;

      ctx.save();
      if (isSelected || isLinked) {
        ctx.shadowBlur  = isSelected ? 18 : 7;
        ctx.shadowColor = fgColor(isSelected ? 0.55 : 0.25);
      }
      const r = f.radius * (isTourHere ? 2.8 : isSelected ? 2.2 : isLinked ? 1.5 : inFlow ? 1.6 : 1);
      ctx.beginPath();
      ctx.arc(f.proj.sx, f.proj.sy, r, 0, Math.PI * 2);
      ctx.fillStyle = fgColor(isSelected || isTourHere ? Math.min(1, f.alpha * 1.7) : f.alpha);
      ctx.fill();
      ctx.restore();

      // ZIP orbit particles (liaisOn lands only)
      if (isLinked && f.p.zipOrbit.length > 0) {
        for (const z of f.p.zipOrbit) {
          const angle  = z.angle + t * z.speed;
          const orbitR = f.radius * 3.5;
          const zx     = f.proj.sx + Math.cos(angle) * orbitR;
          const zy     = f.proj.sy + Math.sin(angle) * orbitR * 0.55;
          ctx.save();
          ctx.beginPath();
          ctx.arc(zx, zy, 2, 0, Math.PI * 2);
          ctx.fillStyle = fgColor(0.38 * f.depth);
          ctx.fill();
          ctx.restore();
        }
      }

      // fl0w step number badge (build/tour modes)
      if (inFlow || isTourHere) {
        const idx = flow.steps.indexOf(uname);
        const label = String(idx + 1).padStart(2, '0');
        ctx.font = `10px "Share Tech Mono", monospace`;
        ctx.fillStyle = fgColor(0.75);
        ctx.fillText(label, f.proj.sx + r + 3, f.proj.sy - r + 2);
      }
    }
  }

  // ─── HIT TEST ──────────────────────────────────────────────
  function hitTest(mx, my) {
    for (let i = projected.length - 1; i >= 0; i--) {
      const f  = projected[i];
      const dx = mx - f.proj.sx, dy = my - f.proj.sy;
      const hr = Math.max(f.radius * 2.5, 14);
      if (dx * dx + dy * dy <= hr * hr) return f;
    }
    return null;
  }

  // ─── PANEL ─────────────────────────────────────────────────
  const panel    = document.getElementById('str3m-panel');
  const elName   = panel.querySelector('.sp-username');
  const elShore  = panel.querySelector('.sp-shore');
  const elStatus = document.getElementById('sp-status');
  const elTocForm   = document.getElementById('sp-toc-form');
  const elTocTarget = document.getElementById('sp-toc-target');
  const elEchoLink  = document.getElementById('sp-echo-link');
  const elPortLink  = document.getElementById('sp-port-link');
  const elZipsBox   = document.getElementById('sp-zips');
  const elZipsList  = document.getElementById('sp-zips-list');
  const hint        = document.getElementById('str3m-hint');

  function showPanel(f) {
    selected = f;
    const land = f.p.land;

    elName.textContent = land.username;
    const shore = (land.shore_text || '').trim() || 'Silence.';
    elShore.textContent = shore.length > 320 ? shore.slice(0, 320) + '…' : shore;

    // ZIPs (c0re files from this land)
    const zips = ZIPS[land.username] || [];
    if (zips.length > 0) {
      elZipsList.innerHTML = zips.map(z =>
        `<a class="sp-zip-link" href="${encodeURIComponent(z.url)}" download="${encodeURI(z.name)}">↓ ${z.name}</a>`
      ).join('');
      elZipsBox.style.display = '';
    } else {
      elZipsBox.style.display = 'none';
    }

    elEchoLink.href = 'echo.php?u=' + encodeURIComponent(land.username);
    elEchoLink.style.display = '';

    // Liaison state
    const old = panel.querySelector('.sp-reply-link');
    if (old) old.remove();
    elTocForm.style.display = 'none';
    elPortLink.style.display = 'none';
    elStatus.textContent = '';

    const li = LIAISONS[land.username];
    if (!li) {
      elTocForm.style.display = '';
      elTocTarget.value = land.username;
    } else if (li.status === 'pending' && li.is_sender) {
      elStatus.textContent = 't0c envoyé · en attente';
    } else if (li.status === 'pending' && !li.is_sender) {
      elStatus.textContent = 't0c reçu ·';
      const a = document.createElement('a');
      a.href = 'land.php#liaisons'; a.textContent = 'répondre';
      a.className = 'sp-reply-link';
      a.style.cssText = 'font-size:.76rem;letter-spacing:.12em;padding:.3rem .65rem;border:1px solid var(--o-line);border-radius:3px;text-decoration:none;color:inherit';
      elStatus.after(a);
    } else if (li.status === 'on') {
      elStatus.textContent = 'liaisOn';
      if (li.port_slug) {
        elPortLink.href = 'port.php?slug=' + encodeURIComponent(li.port_slug);
        elPortLink.style.display = '';
      }
    } else if (li.status === 'off') {
      elTocForm.style.display = '';
      elTocTarget.value = land.username;
      elStatus.textContent = 'liaison coupée';
    }

    panel.classList.remove('is-hidden');
    if (hint) hint.classList.add('is-hidden');
  }

  function hidePanel() {
    selected = null;
    panel.classList.add('is-hidden');
    const old = panel.querySelector('.sp-reply-link');
    if (old) old.remove();
    if (hint && flow.mode === 'off') hint.classList.remove('is-hidden');
  }

  // ─── TOUR HELPERS ──────────────────────────────────────────
  function showCurrentTourPanel() {
    const uname = flow.steps[flow.step];
    const f = projected.find(p => p.p.land.username === uname);
    if (f) showPanel(f);
  }

  function updateTourUI() {
    document.getElementById('flow-tour-land').textContent     = flow.steps[flow.step] || '';
    document.getElementById('flow-tour-progress').textContent = `${String(flow.step + 1).padStart(2,'0')} / ${String(flow.steps.length).padStart(2,'0')}`;
    document.getElementById('flow-prev-btn').disabled = flow.step === 0;
    document.getElementById('flow-next-btn').disabled = flow.step === flow.steps.length - 1;
  }

  // ─── FL0W BUILD ────────────────────────────────────────────
  function flowStartBuilding() {
    hidePanel();
    flow.mode = 'building';
    flow.steps = [];
    document.getElementById('flow-build-panel').classList.remove('is-hidden');
    document.getElementById('flow-mode-btn').classList.add('active');
    updateBuildUI();
  }

  function flowAddStep(username) {
    if (flow.steps.includes(username) || flow.steps.length >= 20) return;
    flow.steps.push(username);
    updateBuildUI();
  }

  function flowRemoveStep(index) {
    flow.steps.splice(index, 1);
    updateBuildUI();
  }

  function updateBuildUI() {
    const list = document.getElementById('flow-steps-list');
    if (flow.steps.length === 0) {
      list.innerHTML = '<li class="fbp-empty">Aucune terre sélectionnée.</li>';
    } else {
      list.innerHTML = flow.steps.map((u, i) =>
        `<li><span><span class="step-num">${String(i+1).padStart(2,'0')}</span>${u}</span><button class="step-rm" data-i="${i}">×</button></li>`
      ).join('');
      list.querySelectorAll('.step-rm').forEach(btn =>
        btn.addEventListener('click', () => flowRemoveStep(Number(btn.dataset.i)))
      );
    }
    const empty = flow.steps.length === 0;
    document.getElementById('flow-launch-btn').disabled = empty;
    document.getElementById('flow-save-btn').disabled   = empty;
  }

  function flowLaunch(steps) {
    if (!steps || steps.length === 0) return;
    flow.mode  = 'touring';
    flow.steps = [...steps];
    flow.step  = 0;
    document.getElementById('flow-build-panel').classList.add('is-hidden');
    document.getElementById('flow-tour-controls').classList.remove('is-hidden');
    document.getElementById('flow-mode-btn').classList.remove('active');
    hidePanel();
    updateTourUI();
    flyTo(flow.steps[0]);
  }

  function flowNext() {
    if (flow.step < flow.steps.length - 1) {
      flow.step++;
      hidePanel();
      updateTourUI();
      flyTo(flow.steps[flow.step]);
    }
  }

  function flowPrev() {
    if (flow.step > 0) {
      flow.step--;
      hidePanel();
      updateTourUI();
      flyTo(flow.steps[flow.step]);
    }
  }

  function flowExitTour() {
    flow.mode = 'off';
    flow.flyTargetX = flow.flyTargetY = null;
    document.getElementById('flow-tour-controls').classList.add('is-hidden');
    document.getElementById('flow-mode-btn').classList.remove('active');
    document.getElementById('flow-mode-btn').style.display = '';
    hidePanel();
  }

  async function flowSave() {
    const name  = document.getElementById('flow-name-input').value.trim() || null;
    const steps = [...flow.steps];
    if (!steps.length) return;

    const btn = document.getElementById('flow-save-btn');
    btn.disabled = true;

    try {
      const res = await fetch('fl0w_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: window.STR3M_CSRF, action: 'save', name, steps }),
      });
      const data = await res.json();
      if (data.ok) {
        btn.textContent = 'Sauvegardé ✓';
        SAVED_FLOWS.unshift({ slug: data.slug, name: name || '', steps });
        setTimeout(() => { btn.textContent = 'Sauvegarder'; btn.disabled = false; }, 2200);
      } else {
        btn.disabled = false;
      }
    } catch (e) { btn.disabled = false; }
  }

  async function flowDelete(slug) {
    await fetch('fl0w_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf_token: window.STR3M_CSRF, action: 'delete', slug }),
    });
    const el = document.querySelector(`.fbp-saved-item[data-slug="${slug}"]`);
    if (el) el.remove();
    const idx = SAVED_FLOWS.findIndex(f => f.slug === slug);
    if (idx !== -1) SAVED_FLOWS.splice(idx, 1);
  }

  // ─── EVENTS ────────────────────────────────────────────────
  function canvasCoords(e) {
    const rect = canvas.getBoundingClientRect();
    return { x: e.clientX - rect.left, y: e.clientY - rect.top };
  }

  function handleClick(mx, my) {
    const hit = hitTest(mx, my);
    if (flow.mode === 'building') {
      if (hit) flowAddStep(hit.p.land.username);
      return;
    }
    if (flow.mode === 'touring') {
      if (hit) showPanel(hit);
      return;
    }
    if (hit) showPanel(hit);
    else hidePanel();
  }

  // Mouse
  canvas.addEventListener('mousedown', e => {
    dragging = true; hasDragged = false;
    dragStartX = e.clientX; dragStartY = e.clientY;
    lastMX = e.clientX; lastMY = e.clientY;
  });
  window.addEventListener('mousemove', e => {
    if (!dragging) return;
    const dx = e.clientX - lastMX, dy = e.clientY - lastMY;
    if (Math.abs(e.clientX - dragStartX) > 4 || Math.abs(e.clientY - dragStartY) > 4) hasDragged = true;
    rotY += dx * 0.005; rotX += dy * 0.005;
    velY = dx * 0.003;  velX = dy * 0.003;
    if (flow.flyTargetX !== null) { flow.flyTargetX = flow.flyTargetY = null; }
    lastMX = e.clientX; lastMY = e.clientY;
  });
  window.addEventListener('mouseup', e => {
    if (!dragging) return;
    dragging = false;
    if (!hasDragged) handleClick(...Object.values(canvasCoords(e)));
  });

  // Touch
  let touchOrigin = null;
  canvas.addEventListener('touchstart', e => {
    const t = e.touches[0];
    touchOrigin = { x: t.clientX, y: t.clientY, moved: false };
    lastMX = t.clientX; lastMY = t.clientY;
  }, { passive: true });
  canvas.addEventListener('touchmove', e => {
    if (!touchOrigin) return; e.preventDefault();
    const t = e.touches[0], dx = t.clientX - lastMX, dy = t.clientY - lastMY;
    if (Math.abs(t.clientX - touchOrigin.x) > 5 || Math.abs(t.clientY - touchOrigin.y) > 5) touchOrigin.moved = true;
    rotY += dx * 0.005; rotX += dy * 0.005;
    velY = dx * 0.003;  velX = dy * 0.003;
    if (flow.flyTargetX !== null) { flow.flyTargetX = flow.flyTargetY = null; }
    lastMX = t.clientX; lastMY = t.clientY;
  }, { passive: false });
  canvas.addEventListener('touchend', e => {
    if (!touchOrigin) return;
    if (!touchOrigin.moved) {
      const t = e.changedTouches[0];
      const rect = canvas.getBoundingClientRect();
      handleClick(t.clientX - rect.left, t.clientY - rect.top);
    }
    touchOrigin = null;
  });

  // Dismiss hint on first drag
  canvas.addEventListener('mousedown', () => { if (hint) hint.classList.add('is-hidden'); }, { once: true });

  // Panel close
  document.getElementById('str3m-close').addEventListener('click', hidePanel);

  // fl0w build
  document.getElementById('flow-mode-btn').addEventListener('click', flowStartBuilding);
  document.getElementById('flow-build-close').addEventListener('click', () => {
    flow.mode = 'off'; flow.steps = [];
    document.getElementById('flow-build-panel').classList.add('is-hidden');
    document.getElementById('flow-mode-btn').classList.remove('active');
  });
  document.getElementById('flow-save-btn').addEventListener('click', flowSave);
  document.getElementById('flow-launch-btn').addEventListener('click', () => flowLaunch(flow.steps));

  // Saved fl0ws: play + delete
  document.querySelectorAll('.fbp-saved-item').forEach(item => {
    const slug = item.dataset.slug;
    item.querySelector('.play-saved-btn')?.addEventListener('click', () => {
      const sf = SAVED_FLOWS.find(f => f.slug === slug);
      if (sf) flowLaunch(sf.steps);
    });
    item.querySelector('.del-btn')?.addEventListener('click', () => flowDelete(slug));
  });

  // fl0w tour
  document.getElementById('flow-prev-btn').addEventListener('click', flowPrev);
  document.getElementById('flow-next-btn').addEventListener('click', flowNext);
  document.getElementById('flow-tour-exit').addEventListener('click', flowExitTour);

  window.addEventListener('resize', resize);

  // ─── INIT ──────────────────────────────────────────────────
  resize();
  requestAnimationFrame(draw);
})();
