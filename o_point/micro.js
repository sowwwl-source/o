// Micro-interactions poétiques O.point
// 1. Lien : vague lumineuse au hover
// 2. Focus input : halo doux
// 3. Transition lente sur navigation
// 4. Animation douce sur apparition bouton

// 1. Lien : vague lumineuse
const links = document.querySelectorAll('a');
links.forEach(link => {
  link.addEventListener('mouseenter', e => {
    link.style.boxShadow = '0 0 18px 2px var(--sowl-txt, #b6ffb3)';
    link.style.transition = 'box-shadow 0.5s cubic-bezier(.4,2,.6,1)';
  });
  link.addEventListener('mouseleave', e => {
    link.style.boxShadow = '';
  });
});

// 2. Focus input : halo
const inputs = document.querySelectorAll('input, textarea, select');
inputs.forEach(inp => {
  inp.addEventListener('focus', e => {
    inp.style.boxShadow = '0 0 0 2px var(--sowl-accent1, #00ff99), 0 0 12px 2px var(--sowl-btn-glow, #ffb34788)';
    inp.style.transition = 'box-shadow 0.4s cubic-bezier(.4,2,.6,1)';
  });
  inp.addEventListener('blur', e => {
    inp.style.boxShadow = '';
  });
});

// 3. Transition lente navigation (liens internes)
document.querySelectorAll('a[href^="/o_point/"]').forEach(link => {
  link.addEventListener('click', function(e) {
    if (link.target || link.hasAttribute('download') || e.metaKey || e.ctrlKey) return;
    e.preventDefault();
    const t = document.createElement('div');
    t.className = 'sowl-transition is-active';
    document.body.appendChild(t);
    setTimeout(() => { window.location = link.href; }, 520);
  });
});

// 4. Apparition douce boutons
window.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('button, .btn').forEach(btn => {
    btn.style.opacity = '0';
    btn.style.transform = 'translateY(18px)';
    setTimeout(() => {
      btn.style.transition = 'opacity 0.7s cubic-bezier(.4,2,.6,1), transform 0.7s cubic-bezier(.4,2,.6,1)';
      btn.style.opacity = '1';
      btn.style.transform = 'none';
    }, 120);
  });
});
