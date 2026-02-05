<?php
require_once __DIR__.'/../o_point/config.php';
require_once __DIR__.'/../o_point/utils.php';
start_secure_session();
require_login();
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT username, email_virtual FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$me = $stmt->fetch();
$stmt = $pdo->prepare('SELECT id, username, email_virtual FROM users WHERE id != ? ORDER BY username');
$stmt->execute([$user_id]);
$users = $stmt->fetchAll();
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Messagerie — O.point</title>
  <link rel="stylesheet" href="/styles.css">
  <style>
    .messagerie-tabs { display: flex; gap: 1.2em; margin-bottom: 1.2em; }
    .messagerie-tabs button { font-size: 1.1em; padding: 0.5em 1.2em; border-radius: 8px; border: 1px solid var(--sowl-dim); background: var(--sowl-bg); color: var(--sowl-txt); cursor: pointer; }
    .messagerie-tabs button.active { background: var(--sowl-txt); color: var(--sowl-bg); }
    .messagerie-panel { display: none; }
    .messagerie-panel.active { display: block; }
    .user-list { margin: 1em 0; }
    .user-list li { margin-bottom: 0.5em; }
    .msg-thread { border: 1px solid var(--sowl-dim); border-radius: 8px; padding: 1em; min-height: 180px; background: rgba(0,0,0,0.08); margin-bottom: 1em; }
    .msg-input { width: 100%; padding: 0.6em; border-radius: 6px; border: 1px solid var(--sowl-dim); margin-bottom: 0.5em; }
    .msg-send { padding: 0.5em 1.2em; border-radius: 6px; border: 1px solid var(--sowl-txt); background: var(--sowl-txt); color: var(--sowl-bg); font-weight: 600; cursor: pointer; }
    .visio-box { border: 1px solid var(--sowl-dim); border-radius: 8px; padding: 1em; background: rgba(0,0,0,0.08); text-align: center; }
    .visio-video { width: 220px; height: 140px; background: #222; border-radius: 8px; margin-bottom: 0.7em; }
  </style>
</head>
<body class="AeiouuoieA">
  <main style="max-width:700px;margin:2em auto;">
    <h1>Messagerie & Appels</h1>
    <div class="messagerie-tabs">
      <button id="tab-msg" class="active" onclick="showTab('msg')">Messages</button>
      <button id="tab-call" onclick="showTab('call')">Appel</button>
      <button id="tab-visio" onclick="showTab('visio')">Visio</button>
    </div>
    <div id="panel-msg" class="messagerie-panel active">
      <h2>Envoyer un message</h2>
      <div>Ton mail O.point : <b><?=e($me['email_virtual'])?></b></div>
      <ul class="user-list">
        <?php foreach($users as $u): ?>
          <li><button onclick="openThread('<?=e($u['username'])?>')">Discuter avec <?=e($u['username'])?></button></li>
        <?php endforeach; ?>
      </ul>
      <div id="msg-thread" class="msg-thread">Sélectionne un utilisateur pour commencer une conversation.</div>
      <input id="msg-input" class="msg-input" type="text" placeholder="Écris un message..." style="display:none;">
      <button id="msg-send" class="msg-send" style="display:none;">Envoyer</button>
    </div>
    <div id="panel-call" class="messagerie-panel">
      <h2>Appel audio</h2>
      <div class="user-list">
        <?php foreach($users as $u): ?>
          <li><button onclick="startCall('<?=e($u['username'])?>')">Appeler <?=e($u['username'])?></button></li>
        <?php endforeach; ?>
      </div>
      <div id="call-status" class="meta"></div>
    </div>
    <div id="panel-visio" class="messagerie-panel">
      <h2>Visio</h2>
      <div class="user-list">
        <?php foreach($users as $u): ?>
          <li><button onclick="startVisio('<?=e($u['username'])?>')">Visio avec <?=e($u['username'])?></button></li>
        <?php endforeach; ?>
      </div>
      <div class="visio-box">
        <div class="visio-video" id="visio-local">(Vidéo locale)</div>
        <div class="visio-video" id="visio-remote">(Vidéo distante)</div>
        <div id="visio-status" class="meta"></div>
      </div>
    </div>
    <div style="margin-top:2em;"><a href="/o_point/h0me.php" class="btn btn-secondary">Retour au h0me</a></div>
  </main>
  <script>
    function showTab(tab) {
      document.querySelectorAll('.messagerie-tabs button').forEach(b=>b.classList.remove('active'));
      document.querySelectorAll('.messagerie-panel').forEach(p=>p.classList.remove('active'));
      document.getElementById('tab-'+tab).classList.add('active');
      document.getElementById('panel-'+tab).classList.add('active');
    }
    // Simule une conversation (à remplacer par backend plus tard)
    let currentUser = null;
    function openThread(user) {
      currentUser = user;
      document.getElementById('msg-thread').textContent = 'Conversation avec '+user+' (fictif)';
      document.getElementById('msg-input').style.display = '';
      document.getElementById('msg-send').style.display = '';
    }
    document.getElementById('msg-send').onclick = function() {
      if(currentUser && document.getElementById('msg-input').value) {
        document.getElementById('msg-thread').textContent += '\nMoi : '+document.getElementById('msg-input').value;
        document.getElementById('msg-input').value = '';
      }
    };
    // Simule appel audio
    function startCall(user) {
      document.getElementById('call-status').textContent = 'Appel audio vers '+user+'... (simulation)';
    }
    // Simule visio
    function startVisio(user) {
      document.getElementById('visio-status').textContent = 'Visio avec '+user+'... (simulation)';
      document.getElementById('visio-local').textContent = 'Vidéo locale (simulation)';
      document.getElementById('visio-remote').textContent = 'Vidéo distante (simulation)';
    }
  </script>
  <script src="/o_point/feedback.js"></script>
  <script src="/o_point/micro.js"></script>
</body>
</html>
