<script>
// 0wlslw0 : IA de veille et de présence poétique
const iaMessages = [
  "Je veille à la clarté et à la bienveillance.",
  "Ici, chaque présence compte.",
  "L’eau relie, l’amour guide.",
  "Respect, écoute, entraide : la convention du bain.",
  "Prends soin de toi, et des autres.",
  "La meilleure pratique : agir avec sens et douceur.",
  "Je suis là, discrète, mais attentive à l’harmonie.",
  "L’universel commence par un geste simple."
];
function randomIAMessage() {
  const msg = iaMessages[Math.floor(Math.random()*iaMessages.length)];
  document.getElementById('ia-message').textContent = msg;
}
document.addEventListener('DOMContentLoaded', () => {
  if(document.getElementById('ia-message')) {
    randomIAMessage();
    setInterval(randomIAMessage, 9000);
  }
});
</script>
