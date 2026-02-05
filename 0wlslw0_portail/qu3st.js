// JS pour charger et explorer la qu3st YAML (arbre, progression, mise en lumière)
// Utilise js-yaml (CDN) pour parser le YAML

const questContainer = document.getElementById('quest-step');
const questActions = document.getElementById('quest-actions');
const questProgress = document.getElementById('quest-progress');
let questData = null;
let route = [];

function highlightRoute() {
  questProgress.innerHTML = route.map((id, i) => `<span style="color:${i===route.length-1?'#ffb347':'#b6ffb3'};">${id}</span>`).join(' <b>→</b> ');
}

function showNode(nodeId) {
  const node = questData[nodeId];
  if (!node) return;
  questContainer.textContent = node.label;
  questActions.innerHTML = '';
  if (node.options && node.options.length) {
    node.options.forEach(opt => {
      const btn = document.createElement('button');
      btn.className = 'quest-btn';
      btn.textContent = opt.label;
      btn.onclick = () => {
        route.push(opt.id);
        highlightRoute();
        showNode(opt.id);
      };
      questActions.appendChild(btn);
    });
  } else {
    // Fin de la route
    const btn = document.createElement('button');
    btn.className = 'quest-btn';
    btn.textContent = 'Recommencer';
    btn.onclick = () => { route = ['start']; highlightRoute(); showNode('start'); };
    questActions.appendChild(btn);
  }
}

function loadQuestYAML() {
  fetch('qu3st.yaml')
    .then(r => r.text())
    .then(yamlText => {
      questData = window.jsyaml.load(yamlText);
      route = ['start'];
      highlightRoute();
      showNode('start');
    });
}

// Charge js-yaml puis la qu3st
document.addEventListener('DOMContentLoaded', () => {
  const script = document.createElement('script');
  script.src = 'https://cdn.jsdelivr.net/npm/js-yaml@4.1.0/dist/js-yaml.min.js';
  script.onload = loadQuestYAML;
  document.body.appendChild(script);
});
