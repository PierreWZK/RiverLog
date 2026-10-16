// RiverLog · page Import : glisser-déposer, nom des fichiers choisis, envoi avec barre de progression

(() => {
  const form = document.querySelector('[data-dropzone]');
  if (!form) {
    return;
  }

  const input = form.querySelector('[data-dropzone-input]');
  const label = form.querySelector('[data-dropzone-label]');
  const errorBox = form.querySelector('[data-dropzone-error]');
  const button = form.querySelector('button[type="submit"]');
  const initial = label.textContent;
  const maxFiles = Number(form.dataset.maxFiles);
  const maxSize = Number(form.dataset.maxSize);

  const showError = (message) => {
    errorBox.textContent = message;
    errorBox.hidden = message === '';
  };

  const showNames = () => {
    const names = [...input.files].map((file) => file.name);
    label.textContent = names.length ? names.join(', ') : initial;
    form.classList.toggle('has-files', names.length > 0);
    showError(names.length > maxFiles ? `${maxFiles} fichiers maximum par envoi.` : '');
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

  // ---------- Envoi fichier par fichier, avec avancement ----------

  const box = form.querySelector('[data-import-progress]');
  const progressLabel = box.querySelector('[data-progress-label]');
  const progressPercent = box.querySelector('[data-progress-percent]');
  const track = box.querySelector('[data-progress-track]');
  const bar = box.querySelector('[data-progress-bar]');
  const list = box.querySelector('[data-progress-files]');
  const doneLink = form.querySelector('[data-progress-done]');

  // Part de chaque étape dans l'avancement d'un fichier (l'enregistrement des mains est le plus long)
  const STEPS = {
    upload: { start: 0, span: 0.15, text: 'Envoi' },
    parse: { start: 0.15, span: 0.1, text: 'Lecture des mains' },
    store: { start: 0.25, span: 0.65, text: 'Enregistrement des mains' },
    refresh: { start: 0.9, span: 0.1, text: 'Calcul des tournois et des sessions' },
  };

  const setProgress = (ratio, text) => {
    const percent = Math.min(100, Math.floor(ratio * 100));
    bar.style.width = `${percent}%`;
    track.setAttribute('aria-valuenow', String(percent));
    progressPercent.textContent = `${percent} %`;
    progressLabel.textContent = text;
  };

  /** Message d'erreur lisible pour une réponse en échec. */
  const failureMessage = (xhr) => {
    if (xhr.status === 422) {
      try {
        const errors = Object.values(JSON.parse(xhr.responseText).errors ?? {});
        if (errors.length) {
          return errors[0][0];
        }
      } catch {
        // réponse non JSON : message générique ci-dessous
      }
    }
    if (xhr.status === 413) {
      return 'fichier trop lourd pour le serveur.';
    }
    if (xhr.status === 419) {
      return 'session expirée, rechargez la page.';
    }

    return xhr.status === 0 ? 'connexion interrompue.' : `erreur du serveur (${xhr.status}).`;
  };

  /**
   * Envoie un fichier ; onStep(step, fraction) suit l'avancement.
   * Le serveur répond une ligne JSON par étape, puis {"step":"done","message":"…"}.
   */
  const sendFile = (file, onStep) => new Promise((resolve) => {
    const data = new FormData();
    data.append('_token', form.querySelector('input[name="_token"]').value);
    data.append('files[]', file);

    const xhr = new XMLHttpRequest();
    let read = 0;
    let result = null;

    const readLines = () => {
      const lines = xhr.responseText.slice(read).split('\n');
      const partial = lines.pop(); // dernière ligne peut-être incomplète
      read = xhr.responseText.length - partial.length;

      lines.filter((line) => line.trim() !== '').forEach((line) => {
        let event;
        try {
          event = JSON.parse(line);
        } catch {
          return;
        }
        if (event.step === 'done' || event.step === 'error') {
          result = { ok: event.step === 'done', message: event.message };
        } else if (STEPS[event.step]) {
          onStep(event.step, event.total > 0 ? event.done / event.total : 1, event);
        }
      });
    };

    xhr.upload.addEventListener('progress', (event) => {
      if (event.lengthComputable) {
        onStep('upload', event.loaded / event.total);
      }
    });
    xhr.addEventListener('progress', () => {
      if (xhr.status === 200) {
        readLines();
      }
    });
    xhr.addEventListener('loadend', () => {
      if (xhr.status === 200) {
        readLines();
        resolve(result ?? { ok: false, message: `« ${file.name} » : réponse incomplète du serveur.` });
      } else {
        resolve({ ok: false, message: `« ${file.name} » : ${failureMessage(xhr)}` });
      }
    });

    xhr.open('POST', form.action);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('X-Import-Progress', '1');
    xhr.send(data);
  });

  const warnBeforeLeaving = (event) => {
    event.preventDefault();
    event.returnValue = '';
  };

  const retryButton = form.querySelector('[data-progress-retry]');
  let failedFiles = [];

  /** Envoie les fichiers un par un en mettant à jour la barre et la liste. */
  const runImport = async (files) => {
    // Évite un double envoi et une fermeture de page accidentelle pendant l'import
    button.hidden = false;
    button.disabled = true;
    button.textContent = 'Import en cours…';
    retryButton.hidden = true;
    doneLink.hidden = true;
    input.disabled = true;
    showError('');
    window.addEventListener('beforeunload', warnBeforeLeaving);

    box.hidden = false;
    box.classList.remove('is-failed');
    list.replaceChildren(...files.map((file) => {
      const item = document.createElement('li');
      item.textContent = file.name;

      return item;
    }));

    // Chaque fichier pèse dans la barre selon sa taille
    const totalSize = files.reduce((sum, file) => sum + Math.max(file.size, 1), 0);
    let before = 0;
    failedFiles = [];

    for (const [index, file] of files.entries()) {
      const item = list.children[index];
      const weight = Math.max(file.size, 1) / totalSize;
      const prefix = files.length > 1 ? `Fichier ${index + 1}/${files.length} · ` : '';
      item.classList.add('is-current');

      let result;
      if (file.size > maxSize) {
        result = { ok: false, message: `« ${file.name} » : chaque fichier doit faire 10 Mo maximum.` };
      } else {
        result = await sendFile(file, (step, fraction, detail) => {
          const { start, span, text } = STEPS[step];
          const count = step === 'store' && detail ? ` (${detail.done} / ${detail.total})` : '';
          setProgress(before + weight * (start + span * fraction), `${prefix}${text}${count}…`);
        });
        if (!result.ok) {
          failedFiles.push(file); // un fichier trop lourd échouerait de nouveau : pas de nouvel essai
        }
      }

      before += weight;
      item.classList.remove('is-current');
      item.classList.add(result.ok ? 'is-ok' : 'is-failed');
      item.textContent = result.message;
    }

    window.removeEventListener('beforeunload', warnBeforeLeaving);
    const failures = list.querySelectorAll('.is-failed').length;

    if (failures === 0) {
      setProgress(1, 'Import terminé, chargement des résultats…');
      window.location.assign(doneLink.href);

      return;
    }

    // Des échecs : on laisse le détail affiché, avec un nouvel essai possible
    setProgress(1, failures === files.length ? 'Import échoué.' : 'Import terminé avec des erreurs.');
    box.classList.toggle('is-failed', failures === files.length);
    button.hidden = true;
    retryButton.hidden = failedFiles.length === 0;
    doneLink.hidden = false;
  };

  form.addEventListener('submit', (event) => {
    const files = [...input.files];
    event.preventDefault();

    if (files.length === 0) {
      showError('Choisissez au moins un fichier.');

      return;
    }
    if (files.length > maxFiles) {
      showError(`${maxFiles} fichiers maximum par envoi.`);

      return;
    }

    runImport(files);
  });

  retryButton.addEventListener('click', () => runImport(failedFiles));

  // Bouton « Relancer » de l'historique : évite un double clic pendant la relance
  document.querySelectorAll('form[data-retry]').forEach((retryForm) => {
    retryForm.addEventListener('submit', () => {
      const retry = retryForm.querySelector('button');
      retry.disabled = true;
      retry.classList.add('is-busy');
    });
  });
})();
