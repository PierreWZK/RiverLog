// RiverLog · détail d'un tournoi : tapis du Hero en big blinds, main par main

(() => {
  const canvas = document.getElementById('stack-chart');
  const points = window.RIVERLOG_STACK;
  if (!canvas || !points || !points.length || typeof window.Chart === 'undefined') {
    return;
  }

  const css = getComputedStyle(document.documentElement);
  const color = (name) => css.getPropertyValue(name).trim();
  const blue = color('--blue');
  const gold = color('--gold');

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  new window.Chart(canvas, {
    type: 'line',
    data: {
      labels: points.map((p) => p.label),
      datasets: [{
        data: points.map((p) => p.value),
        borderColor: blue,
        backgroundColor: `${blue}33`,
        fill: 'origin',
        borderWidth: 2.5,
        tension: 0.2,
        pointRadius: 3,
        pointBackgroundColor: blue,
      }, {
        // Repère : zone push/fold sous 15 BB
        data: points.map(() => 15),
        borderColor: gold,
        borderDash: [5, 5],
        borderWidth: 1,
        pointRadius: 0,
        fill: false,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: reduceMotion ? false : { duration: 700 },
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          displayColors: false,
          filter: (item) => item.datasetIndex === 0,
          callbacks: {
            label: (item) => `Tapis : ${item.parsed.y.toLocaleString('fr-FR')} BB`,
          },
        },
      },
      scales: {
        x: { grid: { display: false }, ticks: { display: false }, border: { color: color('--line') } },
        y: {
          beginAtZero: true,
          ticks: { color: color('--muted'), callback: (v) => `${v} BB` },
          grid: { color: color('--line') },
          border: { display: false },
        },
      },
    },
  });
})();
