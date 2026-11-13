// Shared combobox for activity forms. The selected asset is independent of the query.
export function createBienSearch({ input, panel, list, previous, next, clear, pageLabel, message, url, selected, onSelect, onResults, onClear, onLoading, errorText }) {
    let request = 0, timer, page = 1, last = 1, busy = false, disabled = false;
    let items = [], active = -1, resultQuery = '', selecting = false;
    const syncSelection = () => input.setCustomValidity(selected() ? '' : 'Selecciona un bien de las sugerencias.');
    const close = () => {
        panel.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        Array.from(list.children).forEach((option) => option.setAttribute('aria-selected', 'false'));
        active = -1;
    };
    const open = () => {
        if (disabled || input.disabled || !items.length) return;
        panel.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };
    const controls = (value = disabled) => {
        disabled = value;
        previous.disabled = disabled || busy || page <= 1;
        next.disabled = disabled || busy || page >= last;
        clear.disabled = disabled;
        input.setAttribute('aria-busy', String(busy));
        if (disabled) close();
    };
    const invalidate = () => {
        clearTimeout(timer); timer = undefined; request += 1; busy = false; selecting = false;
        close(); onLoading(false); controls();
    };
    const choose = async (index) => {
        if (disabled || busy || input.disabled || !items[index]) return;
        const item = items[index];
        invalidate(); const token = request;
        busy = true; selecting = true; onLoading(true); controls();
        try {
            await onSelect(item);
            if (token !== request) return;
            if (selected()?.id === item.id) {
                input.value = item.cbi ?? item.descripcion ?? '';
                message.textContent = 'Bien seleccionado. Puedes completar el formulario o buscar otro bien.';
            }
        } catch (error) {
            if (token === request) message.textContent = errorText(error);
        } finally {
            if (token === request) { busy = false; selecting = false; onLoading(false); syncSelection(); controls(); }
        }
    };
    const render = (results) => {
        items = results.slice(0, 15); active = -1;
        list.replaceChildren(); input.removeAttribute('aria-activedescendant');
        items.forEach((item, index) => {
            const option = document.createElement('div');
            option.id = `${list.id}-${index}`;
            option.className = 'bien-search-option';
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            const title = document.createElement('strong'), description = document.createElement('span'), location = document.createElement('small');
            title.textContent = `CBI ${item.cbi ?? '—'} · ID ${item.id}`;
            description.textContent = item.descripcion ?? '—';
            location.textContent = `Ubicación: ${item.ambiente?.nombre ?? 'Sin ambiente asignado'}${item.equipo?.serie ? ` · Serie ${item.equipo.serie}` : ''}`;
            option.append(title, description, location);
            // Keep focus in the combobox, including when selecting with a touch screen.
            option.addEventListener('pointerdown', (event) => event.preventDefault());
            option.addEventListener('click', () => choose(index));
            list.append(option);
        });
        onResults(items); syncSelection();
        if (items.length) open(); else close();
    };
    const search = async (target = 1) => {
        clearTimeout(timer); timer = undefined;
        if (disabled || input.disabled) return;
        const q = input.value.trim();
        invalidate(); const token = request;
        if (q.length < 2 || q.length > 150) {
            page = last = 1; render([]); pageLabel.textContent = '';
            message.textContent = 'Escribe entre 2 y 150 caracteres para buscar.';
            controls(); return;
        }
        busy = true; onLoading(true); controls(); message.textContent = 'Buscando bienes…';
        try {
            const { data } = await window.axios.get(url, { params: { q, page: target } });
            if (token !== request) return;
            page = data.current_page; last = Math.max(1, data.last_page); resultQuery = q;
            render(data.data);
            pageLabel.textContent = `Página ${page} de ${last}`;
            message.textContent = data.total
                ? `${data.total} coincidencias. Se muestran ${items.length}. Selecciona una sugerencia o escribe más caracteres para precisar la búsqueda.`
                : 'No hay coincidencias. Se conserva la selección actual, si existe.';
        } catch (error) {
            if (token === request) { render([]); message.textContent = errorText(error); page = last = 1; pageLabel.textContent = ''; }
        } finally { if (token === request) { busy = false; onLoading(false); controls(); } }
    };
    const activate = (index) => {
        active = (index + items.length) % items.length;
        Array.from(list.children).forEach((option, i) => option.setAttribute('aria-selected', String(i === active)));
        const option = list.children[active];
        input.setAttribute('aria-activedescendant', option.id);
        option.scrollIntoView({ block: 'nearest' });
    };
    input.addEventListener('input', () => {
        invalidate(); page = last = 1; pageLabel.textContent = ''; render([]); controls();
        message.textContent = 'Escribe al menos 2 caracteres. La selección actual se conserva.';
        if (input.value.trim().length >= 2) timer = setTimeout(() => search(1), 300);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && (!panel.hidden || busy || timer !== undefined)) {
            event.preventDefault(); event.stopPropagation(); if (selecting) close(); else invalidate(); return;
        }
        if (event.key === 'Tab') { if (selecting) close(); else invalidate(); return; }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (!busy && items.length && input.value.trim() === resultQuery) {
                open(); activate(active < 0 ? (event.key === 'ArrowDown' ? 0 : items.length - 1) : active + (event.key === 'ArrowDown' ? 1 : -1));
            }
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (!panel.hidden && active >= 0) choose(active); else search(1);
        }
    });
    input.addEventListener('focus', () => { if (input.value.trim() === resultQuery) open(); });
    input.addEventListener('blur', () => { if (selecting) close(); else invalidate(); });
    input.addEventListener('invalid', () => { message.textContent = input.validationMessage; });
    previous.addEventListener('click', () => { if (!previous.disabled) { input.focus(); search(page - 1); } });
    next.addEventListener('click', () => { if (!next.disabled) { input.focus(); search(page + 1); } });
    clear.addEventListener('click', () => {
        invalidate(); input.value = ''; page = last = 1; onClear(); render([]); pageLabel.textContent = '';
        message.textContent = 'Selección vacía. Busca y selecciona un bien.'; controls(); input.focus();
    });
    const reset = () => {
        invalidate(); page = last = 1; input.value = ''; pageLabel.textContent = ''; message.textContent = '';
        render([]); controls();
    };
    close(); syncSelection();
    return { controls, invalidate, search, reset, syncSelection };
}
