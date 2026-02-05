<?php
require_once __DIR__.'/../o_point/config.php';
require_once __DIR__.'/../o_point/utils.php';
start_secure_session();
// Auth admin simple (à renforcer en prod)
if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== 'admin' || $_SERVER['PHP_AUTH_PW'] !== getenv('ADMIN_CONSOWWWL_PASS')) {
    header('WWW-Authenticate: Basic realm="c0nsOwwwl"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Accès refusé.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>c0nsOwwwl — Concepteur de qu3st</title>
  <link rel="stylesheet" href="/styles.css">
  <style>
    .quest-editor { max-width: 900px; margin: 2.5em auto; background: rgba(0,0,0,0.18); border-radius: 18px; box-shadow: 0 8px 32px #000a; border: 1.5px solid #3a4a3a; padding: 2.5em; }
    textarea { width: 100%; min-height: 340px; font-family: 'Share Tech Mono', monospace; font-size: 1em; border-radius: 8px; border: 1px solid #b6ffb3; background: #101c14; color: #b6ffb3; margin-bottom: 1.2em; }
    .quest-actions { display: flex; gap: 1em; }
    .quest-btn { color: #101c14; background: #b6ffb3; border-radius: 8px; padding: 0.7em 1.3em; text-decoration: none; font-weight: 600; box-shadow: 0 0 12px #b6ffb355; border: none; font-size: 1.1em; cursor: pointer; transition: background 0.3s, color 0.3s; }
    .quest-btn:hover { background: #101c14; color: #b6ffb3; box-shadow: 0 0 24px #b6ffb3cc; }
    .quest-preview { margin-top: 2em; background: #181f1a; border-radius: 12px; padding: 1.5em; color: #b6ffb3; }
    .quest-error { color: #ffb347; margin: 1em 0; }
  </style>
</head>
<body class="bain">
  <main class="quest-editor">
    <h1>Concepteur de qu3st (YAML)</h1>
    <form id="quest-form" onsubmit="return false;">
      <label for="yaml-input">YAML de la qu3st :</label>
      <textarea id="yaml-input" spellcheck="false"></textarea>
      <div class="quest-actions">
        <button class="quest-btn" onclick="previewQuest()">Prévisualiser</button>
        <button class="quest-btn" onclick="saveQuest()">Enregistrer</button>
      </div>
      <div id="quest-error" class="quest-error"></div>
    </form>
    <div id="quest-preview" class="quest-preview"></div>
    <a href="/o_point/h0me.php" class="btn btn-secondary">Retour au h0me</a>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/js-yaml@4.1.0/dist/js-yaml.min.js"></script>
  <script>
    // Charge le YAML existant
    fetch('/0wlslw0_portail/qu3st.yaml').then(r=>r.text()).then(txt=>{
      document.getElementById('yaml-input').value = txt;
    });
    function previewQuest() {
      const yaml = document.getElementById('yaml-input').value;
      let data;
      try {
        data = window.jsyaml.load(yaml);
        document.getElementById('quest-error').textContent = '';
      } catch(e) {
        document.getElementById('quest-error').textContent = 'Erreur YAML : ' + e.message;
        document.getElementById('quest-preview').innerHTML = '';
        return;
      }
      // Affiche l’arbre sous forme de liste
      let html = '<b>Points d’entrée :</b><ul>';
      if(data.start && data.start.options) {
        data.start.options.forEach(opt => {
          html += `<li>${opt.label} <span style='color:#ffb347'>(id: ${opt.id})</span></li>`;
        });
      }
      html += '</ul><b>Noeuds :</b><ul>';
      Object.keys(data).forEach(k => {
        if(k!=='start') html += `<li><b>${k}</b> : ${data[k].label}</li>`;
      });
      html += '</ul>';
      document.getElementById('quest-preview').innerHTML = html;
    }
    function saveQuest() {
      const yaml = document.getElementById('yaml-input').value;
      fetch('/0wlslw0_portail/qu3st.yaml', {
        method: 'POST',
        headers: { 'Content-Type': 'text/plain' },
        body: yaml
      }).then(r=>{
        if(r.ok) alert('Qu3st enregistrée !');
        else alert('Erreur lors de l’enregistrement.');
      });
    }
  </script>
</body>
</html>
