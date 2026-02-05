// 0wlslw0 : chat vocal uniquement, sans interface visible, sans copier-coller
// Utilise Web Speech API (SpeechRecognition + SpeechSynthesis)

// Démarre l'écoute vocale dès l'arrivée sur la page
window.addEventListener('DOMContentLoaded', () => {
  if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) return;
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  const synth = window.speechSynthesis;
  const recognition = new SpeechRecognition();
  recognition.lang = 'fr-FR';
  recognition.continuous = false;
  recognition.interimResults = false;
  let active = true;

  function speak(text) {
    const utter = new SpeechSynthesisUtterance(text);
    utter.lang = 'fr-FR';
    synth.speak(utter);
  }

  function handleInput(text) {
    // Réponses poétiques IA (exemple simple)
    let response = "Je veille, je t'écoute.";
    if (/amour|aimer/i.test(text)) response = "L'amour universel est la seule action sensée.";
    else if (/respect|écoute/i.test(text)) response = "Le respect mutuel est la clé du bain.";
    else if (/pratique|conseil/i.test(text)) response = "Clarté, sobriété, ouverture, partage.";
    else if (/bonjour|salut/i.test(text)) response = "Bienvenue dans le grand bain.";
    else if (/merci/i.test(text)) response = "Merci à toi d'être présent·e.";
    speak(response);
  }

  recognition.onresult = function(event) {
    if (!active) return;
    const text = event.results[0][0].transcript;
    handleInput(text);
    setTimeout(() => recognition.start(), 1200); // Relance l'écoute après la réponse
  };
  recognition.onerror = function() {
    if (active) setTimeout(() => recognition.start(), 2000);
  };
  recognition.onend = function() {
    if (active) setTimeout(() => recognition.start(), 1000);
  };

  // Démarre l'écoute automatiquement
  recognition.start();

  // Empêche tout copier-coller
  document.addEventListener('copy', e => e.preventDefault());
  document.addEventListener('cut', e => e.preventDefault());
  document.addEventListener('paste', e => e.preventDefault());
});
