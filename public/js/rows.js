// RiverLog · une ligne de tableau « .row-link » est cliquable en entier (le lien reste dans la 1re cellule)

document.querySelectorAll('tr.row-link').forEach((row) => {
  const link = row.querySelector('a[href]');
  if (!link) {
    return;
  }

  row.addEventListener('click', (event) => {
    if (event.target.closest('a, button') || window.getSelection().toString()) {
      return;
    }
    link.click();
  });
});
