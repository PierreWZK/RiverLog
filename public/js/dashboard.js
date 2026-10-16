// RiverLog · dashboard : courbe du profit cumulé et confirmation de suppression

(() => {
  // ---------- Confirmation avant de supprimer une session ----------
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm)) {
        event.preventDefault();
      }
    });
  });

  // ---------- Courbe (Chart.js) ----------
  const canvas = document.getElementById('profit-chart');
  const curve = window.RIVERLOG_CURVE;
  if (!canvas || !curve || typeof window.Chart === 'undefined') {
    return;
  }

  const css = getComputedStyle(document.documentElement);
  const color = (name) => css.getPropertyValue(name).trim();
  const felt = color('--felt');
  const red = color('--red');
  const muted = color('--muted');
  const line = color('--line');

  const money = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    signDisplay: 'exceptZero',
  });

  // Avec une seule session, on part de 0 pour tracer un vrai segment
  const points = curve.length === 1 ? [{ label: 'Départ', value: 0 }, ...curve] : curve;
  const last = points[points.length - 1].value;
  const mainColor = last < 0 ? red : felt;

  const ctx = canvas.getContext('2d');
  const gradient = ctx.createLinearGradient(0, 0, 0, canvas.parentElement.clientHeight || 280);
  gradient.addColorStop(0, `${mainColor}55`);
  gradient.addColorStop(1, `${mainColor}00`);

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  new window.Chart(canvas, {
    type: 'line',
    data: {
      labels: points.map((p) => p.label),
      datasets: [{
        data: points.map((p) => p.value),
        borderColor: mainColor,
        backgroundColor: gradient,
        fill: 'origin',
        borderWidth: 2.5,
        tension: 0.25,
        pointRadius: points.length > 40 ? 0 : 3,
        pointHoverRadius: 5,
        pointBackgroundColor: mainColor,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: reduceMotion ? false : { duration: 900 },
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          displayColors: false,
          callbacks: {
            title: (items) => items[0].label,
            label: (item) => `Cumul : ${money.format(item.parsed.y)}`,
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: muted, maxTicksLimit: window.innerWidth < 600 ? 3 : 6, maxRotation: 0 },
          border: { color: line },
        },
        y: {
          ticks: { color: muted, callback: (v) => money.format(v) },
          // La ligne du zéro est mise en avant : au-dessus on gagne, en dessous on perd
          grid: { color: (c) => (c.tick.value === 0 ? muted : line) },
          border: { display: false },
        },
      },
    },
  });
})();
