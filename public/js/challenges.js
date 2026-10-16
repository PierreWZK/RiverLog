// RiverLog · défis du jour : curseur d'équité et partage du résultat

(() => {
  // Curseur « chance de gagner de la main A » : affiche la valeur choisie
  document.querySelectorAll('[data-equity-range]').forEach((range) => {
    const output = range.closest('label').querySelector('[data-equity-output]');
    const update = () => {
      output.textContent = `${range.value} %`;
      range.style.setProperty('--value', `${range.value}%`);
    };
    range.addEventListener('input', update);
    update();
  });

  // Évite un double envoi
  const form = document.querySelector('[data-quiz]');
  form?.addEventListener('submit', () => {
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    button.textContent = 'Correction…';
  });

  // Copier le résultat (texte + carrés de couleur), comme les jeux du jour
  document.querySelectorAll('[data-share]').forEach((button) => {
    button.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(button.dataset.share);
        button.textContent = 'Résultat copié ✓';
      } catch {
        window.prompt('Copiez votre résultat :', button.dataset.share);
      }
    });
  });
})();
