import { renderBienDetail } from './bien-detail';

const search = document.querySelector('[data-global-search]');
if (search) {
    const find = (selector) => search.querySelector(`[data-global-search-${selector}]`);
    const input = find('input'), form = find('form'), panel = find('panel'), list = find('list');
    const status = find('status'), pager = find('pagination'), previous = find('previous'), next = find('next');
    const detailRoot = document.querySelector('[data-global-detail-root]');
    const dialog = detailRoot.querySelector('[data-bien-detail-dialog]');
    const detailMessage = detailRoot.querySelector('[data-bien-detail-message]');
    const detailFields = detailRoot.querySelector('[data-bien-detail-fields]');
    let timer, request = 0, detailRequest = 0, page = 1, lastPage = 1, busy = false;
    const open = (visible) => { panel.hidden = !visible; input.setAttribute('aria-expanded', String(visible)); };
    const close = () => {
        clearTimeout(timer); request += 1; busy = false;
        panel.removeAttribute('aria-busy'); open(false);
    };
    const errorText = (error) => {
        const code = error.response?.status;
        if (code === 401) window.location.assign(search.dataset.loginUrl);
        return ({ 401: 'Tu sesión ha expirado.', 403: 'No tienes permiso para consultar estos bienes.',
            404: 'El bien ya no está disponible o está fuera de tu alcance.',
            422: 'Escribe entre 2 y 150 caracteres para buscar.' }[code]) || 'No se pudo completar la consulta. Inténtalo nuevamente.';
    };
    const showDetail = async (id) => {
        close(); const token = ++detailRequest;
        detailFields.hidden = true; detailMessage.textContent = 'Cargando detalle…';
        if (!dialog.open) dialog.showModal();
        try {
            const { data } = await window.axios.get(`${search.dataset.searchUrl}/${encodeURIComponent(id)}`);
            if (token !== detailRequest) return;
            renderBienDetail(detailRoot, data.data);
            detailFields.hidden = false; detailMessage.textContent = '';
        } catch (error) { if (token === detailRequest) detailMessage.textContent = errorText(error); }
    };
    const load = async (target = 1) => {
        clearTimeout(timer);
        if (input.disabled) return;
        const q = input.value.trim(); const token = ++request;
        list.replaceChildren(); pager.hidden = true; open(true);
        if (q.length < 2 || q.length > 150) {
            busy = false; panel.removeAttribute('aria-busy');
            status.textContent = 'Escribe al menos 2 caracteres para buscar bienes.'; return;
        }
        busy = true; panel.setAttribute('aria-busy', 'true'); status.textContent = 'Buscando bienes…';
        try {
            const { data } = await window.axios.get(search.dataset.searchUrl, { params: { q, page: target } });
            if (token !== request) return;
            page = data.current_page; lastPage = data.last_page;
            data.data.forEach((bien) => {
                const item = document.createElement('li'), button = document.createElement('button');
                button.type = 'button'; button.className = 'global-search-result';
                const title = document.createElement('strong');
                title.textContent = `CBI ${bien.cbi ?? '—'} · ${bien.descripcion ?? '—'}`;
                const info = document.createElement('span');
                info.textContent = `Serie: ${bien.equipo?.serie ?? '—'} · ${bien.ambiente?.nombre ?? 'Sin ambiente asignado'}`;
                button.append(title, info); button.addEventListener('click', () => showDetail(bien.id));
                item.append(button); list.append(item);
            });
            status.textContent = data.total ? `${data.total} coincidencias dentro de tu alcance. Selecciona un bien para ver su detalle.` : 'No hay coincidencias dentro de tu alcance.';
            pager.hidden = lastPage <= 1;
            find('page').textContent = `Página ${page} de ${lastPage}`;
            previous.disabled = page <= 1; next.disabled = page >= lastPage;
        } catch (error) { if (token === request) status.textContent = errorText(error); }
        finally { if (token === request) { busy = false; panel.removeAttribute('aria-busy'); } }
    };
    input.addEventListener('input', () => {
        clearTimeout(timer); request += 1; list.replaceChildren(); pager.hidden = true;
        busy = false; panel.removeAttribute('aria-busy');
        if (!input.value.trim()) { open(false); status.textContent = ''; return; }
        open(true); status.textContent = 'Escribe al menos 2 caracteres para buscar bienes.';
        if (input.value.trim().length >= 2) {
            status.textContent = 'Preparando búsqueda…'; timer = setTimeout(() => load(1), 300);
        }
    });
    form.addEventListener('submit', (event) => { event.preventDefault(); load(1); });
    previous.addEventListener('click', () => { if (!busy && page > 1) load(page - 1); });
    next.addEventListener('click', () => { if (!busy && page < lastPage) load(page + 1); });
    document.addEventListener('pointerdown', (event) => { if (!search.contains(event.target)) close(); });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) { close(); input.focus(); }
    });
    detailRoot.querySelector('[data-close-bien-detail]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { detailRequest += 1; input.focus(); });
}
