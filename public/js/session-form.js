// RiverLog · formulaire de session : affiche la durée en heures sous le champ (150 → « 2 h 30 »)

(() => {
  const input = document.querySelector('input[name="duration_minutes"]');
  const hint = document.querySelector('[data-duration-hint]');
  if (!input || !hint) {
    return;
  }

  const update = () => {
    const minutes = Number.parseInt(input.value, 10);
    if (!Number.isFinite(minutes) || minutes <= 0) {
      hint.textContent = '';
      return;
    }
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    hint.textContent = h === 0 ? `= ${m} min` : `= ${h} h${m ? ` ${String(m).padStart(2, '0')}` : ''}`;
  };

  input.addEventListener('input', update);
  update();
})();
