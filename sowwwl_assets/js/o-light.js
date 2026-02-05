// Utilitaire JS pour déclencher l'animation O. lumineuse partout
// À inclure dans tes pages principales
function showOLight(message = 'O.', duration = 1200) {
  let o = document.getElementById('o-light-global');
  if (!o) {
    o = document.createElement('div');
    o.id = 'o-light-global';
    o.className = 'o-light';
    document.body.appendChild(o);
  }
  o.textContent = message;
  o.classList.remove('active');
  // Force reflow pour relancer l'animation
  void o.offsetWidth;
  o.classList.add('active');
  setTimeout(() => o.classList.remove('active'), duration);
}
// Exemple d'utilisation : showOLight('O.') lors d'une validation, succès, etc.