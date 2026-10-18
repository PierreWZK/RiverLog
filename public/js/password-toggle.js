/*
 * Un bouton « œil » dans chaque champ mot de passe (connexion, inscription), pour afficher ce qui est tapé.
 * Sans JavaScript, le champ reste masqué comme d'habitude.
 */
(() => {
    const EYE = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
    const EYE_OFF = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.6 5.1A10.7 10.7 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-2.4 3.4M6.6 6.6C3.8 8.4 2 12 2 12s3.5 7 10 7c1.9 0 3.6-.6 5-1.4"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m2 2 20 20"/></svg>';

    document.querySelectorAll('input[type="password"]').forEach((input) => {
        const wrapper = document.createElement('span');
        wrapper.className = 'password-field';
        input.before(wrapper);
        wrapper.append(input);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'password-toggle';
        const render = (visible) => {
            button.innerHTML = visible ? EYE_OFF : EYE;
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            button.title = button.getAttribute('aria-label');
        };
        render(false);
        wrapper.append(button);

        button.addEventListener('click', (event) => {
            // Le champ est dans un <label> : le clic ne doit rien faire d'autre
            event.preventDefault();
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            render(visible);
            input.focus();
        });
        // Remasqué à l'envoi : le navigateur ne le mémorise pas comme un simple texte
        input.form?.addEventListener('submit', () => {
            input.type = 'password';
            render(false);
        });
    });
})();
