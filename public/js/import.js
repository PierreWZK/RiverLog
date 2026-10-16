// RiverLog · page Import : glisser-déposer et nom des fichiers choisis

(() => {
  const form = document.querySelector('[data-dropzone]');
  if (!form) {
    return;
  }

  const input = form.querySelector('[data-dropzone-input]');
  const label = form.querySelector('[data-dropzone-label]');
  const initial = label.textContent;

  const showNames = () => {
    const names = [...input.files].map((file) => file.name);
    label.textContent = names.length ? names.join(', ') : initial;
    form.classList.toggle('has-files', names.length > 0);
  };

  input.addEventListener('change', showNames);

  ['dragenter', 'dragover'].forEach((type) => form.addEventListener(type, (event) => {
    event.preventDefault();
    form.classList.add('is-dragging');
  }));

  ['dragleave', 'drop'].forEach((type) => form.addEventListener(type, () => form.classList.remove('is-dragging')));

  form.addEventListener('drop', (event) => {
    event.preventDefault();
    input.files = event.dataTransfer.files;
    showNames();
  });

  // Évite un double envoi pendant l'import
  form.addEventListener('submit', () => {
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    button.textContent = 'Import en cours…';
  });
})();
