// Active l’IA vocale uniquement si on est dans l’environnement 0wlslw0 (dossier ou domaine)
(function(){
  const is0wlslw0 = window.location.hostname.includes('0wlslw0') || window.location.pathname.includes('0wlslw0_portail');
  if (!is0wlslw0) return;
  const script = document.createElement('script');
  script.src = 'ia_vocal.js';
  document.body.appendChild(script);
})();
