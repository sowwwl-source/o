<script src="/sowwwl_assets/js/o-light.js"></script>
<script>
// Affiche l'effet O. lumineux et une inversion douce sur validation du formulaire
const form = document.querySelector('form');
if(form) {
  form.addEventListener('submit', function(e) {
    // On laisse le submit normal, mais on déclenche l'effet visuel
    document.body.classList.add('inverted');
    showOLight('O.');
    setTimeout(() => document.body.classList.remove('inverted'), 900);
  });
}
</script>
