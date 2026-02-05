// JS pour la personnalisation de palette quadri (à inclure sur toutes les pages)
(function() {
  const btn = document.getElementById('palette-btn');
  const panel = document.getElementById('palette-panel');
  const colors = [
    { id: 'bg', var: '--sowl-bg', def: '#101c14' },
    { id: 'txt', var: '--sowl-txt', def: '#b6ffb3' },
    { id: 'accent1', var: '--sowl-accent1', def: '#00ff99' },
    { id: 'accent2', var: '--sowl-accent2', def: '#a259ff' }
  ];
  function setVar(id, val) {
    document.documentElement.style.setProperty(colors.find(c=>c.id===id).var, val);
    document.getElementById('preview-'+id).style.background = val;
    localStorage.setItem('palette-'+id, val);
  }
  colors.forEach(c => {
    const input = document.getElementById('color-'+c.id);
    const saved = localStorage.getItem('palette-'+c.id) || c.def;
    input.value = saved;
    setVar(c.id, saved);
    input.addEventListener('input', e => setVar(c.id, e.target.value));
  });
  btn.onclick = () => panel.classList.toggle('active');
})();
