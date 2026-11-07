/*
 * Salon de discussion :
 *  - envoi des messages en AJAX (Entrée pour envoyer, Maj + Entrée pour aller à la ligne) ;
 *  - affichage des erreurs de validation renvoyées par Symfony ;
 *  - rafraîchissement automatique (nouveaux messages des autres membres) ;
 *  - chargement des messages plus anciens ;
 *  - suppression d'un message sans recharger la page.
 *
 * Sans JavaScript, les formulaires fonctionnent normalement (rechargement de page).
 * Ce script est chargé uniquement par templates/channel/show.html.twig.
 */

/** Délai entre deux vérifications de nouveaux messages, en millisecondes. */
const POLL_INTERVAL = 3000;

/** Distance (en px) en dessous de laquelle on considère être "en bas" de la conversation. */
const BOTTOM_THRESHOLD = 120;

function initChat(root) {
    const scroller = root.querySelector('[data-chat-scroller]');
    const list = root.querySelector('[data-chat-messages]');
    const form = root.querySelector('[data-chat-form]');
    const input = root.querySelector('[data-chat-input]');
    const box = input.closest('.composer__box');
    const errorsBox = root.querySelector('[data-chat-errors]');
    const counter = root.querySelector('[data-chat-counter]');
    const submitButton = root.querySelector('[data-chat-submit]');
    const loadMoreButton = root.querySelector('[data-chat-load-more]');
    const welcome = root.querySelector('[data-chat-welcome]');
    const messagesUrl = root.dataset.messagesUrl;
    const homeUrl = root.dataset.homeUrl;
    const maxLength = Number(counter?.dataset.maxLength) || 2000;

    let lastId = lastMessageId();
    let polling = false;
    let pollRequested = false;
    let sending = false;
    let stopped = false;
    let scrollOnNextAppend = false;
    let atBottom = true;

    // ------------------------------------------------------------------ Outils

    function messageItems() {
        return list.querySelectorAll('[data-message-id]');
    }

    function lastMessageId() {
        const items = messageItems();

        return items.length ? Number(items[items.length - 1].dataset.messageId) : 0;
    }

    function firstMessageId() {
        const first = list.querySelector('[data-message-id]');

        return first ? Number(first.dataset.messageId) : 0;
    }

    function isNearBottom() {
        return scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < BOTTOM_THRESHOLD;
    }

    function scrollToBottom() {
        scroller.scrollTop = scroller.scrollHeight;
        atBottom = true;
    }

    function parseHtml(html) {
        const template = document.createElement('template');
        template.innerHTML = html.trim();

        return template.content;
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const isJson = (response.headers.get('Content-Type') || '').includes('application/json');

        return { response, isJson, data: isJson ? await response.json() : null };
    }

    /** Un séparateur de date ne doit apparaître qu'une fois par jour. */
    function removeDuplicateDayDividers() {
        const seen = new Set();

        list.querySelectorAll('.day-divider').forEach((divider) => {
            if (seen.has(divider.dataset.day)) {
                divider.remove();
            } else {
                seen.add(divider.dataset.day);
            }
        });
    }

    // ------------------------------------------------- Rafraîchissement automatique

    function appendMessages(html) {
        const stick = scrollOnNextAppend || isNearBottom();
        const fragment = parseHtml(html);

        fragment.querySelectorAll('[data-message-id]').forEach((item) => {
            // Évite d'afficher deux fois le même message
            if (list.querySelector(`[data-message-id="${item.dataset.messageId}"]`)) {
                item.remove();
            } else {
                item.classList.add('is-new');
            }
        });

        list.append(fragment);
        removeDuplicateDayDividers();
        scrollOnNextAppend = false;

        if (stick) {
            scrollToBottom();
        }
    }

    async function poll() {
        if (stopped) {
            return;
        }

        // Une seule requête à la fois : si une requête est en cours, on en relance une juste après.
        if (polling) {
            pollRequested = true;

            return;
        }

        polling = true;

        try {
            const { response, isJson, data } = await request(`${messagesUrl}?after=${lastId}`);

            if (404 === response.status) {
                // Le salon vient d'être supprimé par son créateur
                stopped = true;
                window.location.assign(homeUrl);

                return;
            }

            if (response.redirected) {
                // Session expirée : Symfony redirige vers la page de connexion
                stopped = true;
                window.location.reload();

                return;
            }

            if (response.ok && isJson) {
                if (data.html) {
                    appendMessages(data.html);
                }
                lastId = Math.max(lastId, Number(data.lastId) || 0);
            }
        } catch (error) {
            // Problème réseau passager : on réessaiera au prochain tour.
        } finally {
            polling = false;

            if (pollRequested) {
                pollRequested = false;
                poll();
            }
        }
    }

    function startPolling() {
        setTimeout(async function tick() {
            if (!document.hidden) {
                await poll();
            }
            if (!stopped) {
                setTimeout(tick, POLL_INTERVAL);
            }
        }, POLL_INTERVAL);

        // Retour sur l'onglet : on vérifie tout de suite s'il y a du nouveau.
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                poll();
            }
        });
    }

    // -------------------------------------------------------- Envoi d'un message

    function showErrors(messages) {
        errorsBox.replaceChildren();
        box.classList.toggle('has-error', messages.length > 0);

        if (0 === messages.length) {
            return;
        }

        const ul = document.createElement('ul');
        ul.className = 'field__errors';
        ul.setAttribute('role', 'alert');

        messages.forEach((message) => {
            const li = document.createElement('li');
            li.textContent = message;
            ul.append(li);
        });

        errorsBox.append(ul);
    }

    /** La zone de saisie s'agrandit avec son contenu (jusqu'à 40 % de l'écran, voir le CSS). */
    function autoResize() {
        input.style.height = 'auto';

        // Champ vide : hauteur naturelle d'une ligne. On évite ainsi un calcul
        // faussé si la page est affichée alors qu'elle n'a pas encore de largeur.
        if ('' === input.value || 0 === input.clientWidth) {
            return;
        }

        input.style.height = `${input.scrollHeight}px`;
    }

    function updateCounter() {
        if (!counter) {
            return;
        }

        // Même calcul que Symfony : espaces de début/fin retirés, caractères Unicode (et non octets).
        const length = [...input.value.trim()].length;
        const nearLimit = length > maxLength - 200;

        counter.textContent = nearLimit ? `${length} / ${maxLength}` : '';
        counter.classList.toggle('is-over', length > maxLength);
    }

    async function send(event) {
        event.preventDefault();

        if (sending) {
            return;
        }

        sending = true;
        submitButton.disabled = true;

        try {
            const { response, isJson, data } = await request(form.action, {
                method: 'POST',
                body: new FormData(form),
            });

            if (201 === response.status) {
                input.value = '';
                autoResize();
                updateCounter();
                showErrors([]);
                // Les erreurs éventuellement affichées par le serveur (sans JS) ne sont plus d'actualité
                form.querySelector(':scope > .form-errors')?.remove();
                scrollOnNextAppend = true;
                poll();

                return;
            }

            if (response.redirected) {
                window.location.reload();

                return;
            }

            if (isJson && Array.isArray(data.errors)) {
                // Erreurs de validation Symfony (message vide, trop long, jeton CSRF…)
                showErrors(data.errors);

                return;
            }

            showErrors(['Le message n\'a pas pu être envoyé. Veuillez réessayer.']);
        } catch (error) {
            showErrors(['Impossible de joindre le serveur. Vérifiez votre connexion puis réessayez.']);
        } finally {
            sending = false;
            submitButton.disabled = false;
            input.focus();
        }
    }

    // ----------------------------------------------------- Messages plus anciens

    async function loadOlder() {
        const beforeId = firstMessageId();

        if (!beforeId) {
            return;
        }

        const label = loadMoreButton.textContent;
        loadMoreButton.disabled = true;
        loadMoreButton.textContent = 'Chargement…';

        try {
            const { response, isJson, data } = await request(`${messagesUrl}?before=${beforeId}`);

            if (!response.ok || !isJson) {
                throw new Error(`HTTP ${response.status}`);
            }

            // On conserve la position de lecture : le contenu est ajouté AU-DESSUS.
            const previousHeight = scroller.scrollHeight;
            const previousTop = scroller.scrollTop;

            list.prepend(parseHtml(data.html));
            removeDuplicateDayDividers();

            if (!data.hasMore) {
                loadMoreButton.closest('[data-chat-load-more-container]')?.remove();
                welcome?.classList.remove('is-hidden');
            }

            scroller.scrollTop = previousTop + (scroller.scrollHeight - previousHeight);
        } catch (error) {
            loadMoreButton.textContent = 'Erreur, réessayer';
            loadMoreButton.disabled = false;

            return;
        }

        loadMoreButton.textContent = label;
        loadMoreButton.disabled = false;
    }

    // ------------------------------------------------------ Suppression d'un message

    function removeMessage(item) {
        const next = item.nextElementSibling;
        const previous = item.previousElementSibling;

        // Si on supprime le premier message d'un groupe, le suivant récupère l'avatar et le nom.
        if (!item.classList.contains('message--grouped') && next?.classList.contains('message--grouped')) {
            next.classList.remove('message--grouped');
        }

        item.remove();

        // Séparateur de date devenu inutile (plus aucun message ce jour-là)
        const nextAfter = previous?.nextElementSibling;
        if (previous?.classList.contains('day-divider') && (!nextAfter || nextAfter.classList.contains('day-divider'))) {
            previous.remove();
        }
    }

    async function deleteMessage(event) {
        const deleteForm = event.target.closest('[data-chat-delete]');

        if (!deleteForm) {
            return;
        }

        event.preventDefault();
        const item = deleteForm.closest('[data-message-id]');
        item.classList.add('is-deleting');

        try {
            const { response } = await request(deleteForm.action, {
                method: 'POST',
                body: new FormData(deleteForm),
            });

            if (!response.ok || response.redirected) {
                throw new Error(`HTTP ${response.status}`);
            }

            removeMessage(item);
        } catch (error) {
            item.classList.remove('is-deleting');
            window.alert('Le message n\'a pas pu être supprimé. Rechargez la page puis réessayez.');
        }
    }

    // ------------------------------------------------------------------ Événements

    form.addEventListener('submit', send);

    input.addEventListener('keydown', (event) => {
        if ('Enter' === event.key && !event.shiftKey && !event.isComposing) {
            event.preventDefault();

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        }
    });

    input.addEventListener('input', () => {
        autoResize();
        updateCounter();
    });

    list.addEventListener('submit', deleteMessage);
    loadMoreButton?.addEventListener('click', loadOlder);

    scroller.addEventListener('scroll', () => {
        atBottom = isNearBottom();
    }, { passive: true });

    window.addEventListener('resize', () => {
        autoResize();

        if (atBottom) {
            scrollToBottom();
        }
    });

    autoResize();
    updateCounter();
    scrollToBottom();
    startPolling();
}

const chatRoot = document.querySelector('[data-chat]');
if (chatRoot) {
    initChat(chatRoot);
}
