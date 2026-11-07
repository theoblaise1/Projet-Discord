/*
 * Script commun à toutes les pages (chargé par templates/base.html.twig).
 * JavaScript natif, sans dépendance ni étape de build.
 *
 * Tout le site fonctionne aussi sans JavaScript : ce code améliore seulement
 * l'expérience (notifications, confirmations, menu sur mobile).
 * Le chat en temps réel est géré par chat.js, chargé uniquement dans les salons.
 */

/** Les notifications (messages flash) disparaissent d'elles-mêmes après quelques secondes. */
function initToasts() {
    document.querySelectorAll('[data-toast]').forEach((toast) => {
        let closed = false;
        const close = () => {
            if (closed) {
                return;
            }
            closed = true;
            toast.classList.add('is-leaving');
            setTimeout(() => toast.remove(), 300);
        };

        toast.querySelector('[data-toast-close]')?.addEventListener('click', close);
        setTimeout(close, 6000);
    });
}

/** Demande une confirmation avant les actions destructrices (formulaires avec data-confirm). */
function initConfirmations() {
    // Phase de capture : la confirmation passe avant les autres gestionnaires (ex. suppression en AJAX).
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
}

/** Sur petit écran, la liste des salons s'ouvre avec le bouton "menu". */
function initSidebar() {
    const app = document.querySelector('[data-app]');

    if (!app) {
        return;
    }

    const toggles = document.querySelectorAll('[data-sidebar-toggle]');
    const setOpen = (open) => {
        app.classList.toggle('is-sidebar-open', open);
        toggles.forEach((toggle) => toggle.setAttribute('aria-expanded', String(open)));
    };

    toggles.forEach((toggle) => toggle.addEventListener('click', () => setOpen(!app.classList.contains('is-sidebar-open'))));
    document.querySelectorAll('[data-sidebar-close]').forEach((element) => element.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', (event) => {
        if ('Escape' === event.key) {
            setOpen(false);
        }
    });
}

initToasts();
initConfirmations();
initSidebar();
