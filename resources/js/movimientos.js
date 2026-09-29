const root = document.querySelector('[data-movements-root]');

if (root) {
    const find = (selector, parent = root) => parent.querySelector(selector);
    const base = root.dataset.movementsUrl;
    const endpoint = (id, suffix = '') => `${base}/${encodeURIComponent(id)}${suffix}`;
    const createDialog = find('[data-movement-create-dialog]');
    const createForm = find('[data-movement-create-form]');
    const detailDialog = find('[data-movement-detail-dialog]');
    const detailContent = find('[data-movement-detail-content]');
    const detailMessage = find('[data-detail-message]');
    const uploadDialog = find('[data-movement-upload-dialog]');
    const uploadForm = find('[data-movement-upload-form]');
    const notice = find('[data-movement-notice]');
    let current = null, detailId = null, detailRequest = 0, downloadRequest = 0;
    let options = null, optionsLoaded = false, uploadTarget = null;
    const labels = { pendiente_firma: 'Pendiente de firma', finalizado: 'Finalizado' };
    const state = (value) => labels[value] ?? value ?? '—';
    const normalize = (value) => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
    const userName = (user) => user ? [user.nombres, user.apellidos].filter(Boolean).join(' ') || '—' : '—';
    const date = (value) => {
        if (!value) return '—';
        const parsed = new Date(value);
        return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleString('es-PE');
    };
    const errorText = (error) => {
        const status = error.response?.status;
        if (status === 401) window.location.assign(root.dataset.loginUrl);
        const data = error.response?.data;
        const errors = data?.errors && Object.values(data.errors).flat();
        return errors?.length ? errors.join(' ') : data?.message || ({
            401: 'La sesión ha expirado. Inicia sesión nuevamente.',
            403: 'No tienes permiso para realizar esta operación.',
            404: 'El movimiento o el documento solicitado no está disponible.',
            409: 'El movimiento o la ubicación del bien ha cambiado. Actualiza la información antes de continuar.',
            422: 'Revisa los datos enviados.',
            419: 'El token de seguridad ha expirado. Recarga la página.',
        }[status]) || 'No se pudo completar la operación. Inténtalo de nuevo.';
    };
    const busy = (dialog, value) => {
        dialog.dataset.busy = value ? '1' : '0';
        dialog.querySelectorAll('button, input, select, textarea').forEach((element) => { element.disabled = value; });
    };
    root.querySelectorAll('dialog').forEach((dialog) => {
        dialog.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => {
            if (dialog.dataset.busy !== '1') dialog.close();
        }));
        dialog.addEventListener('cancel', (event) => { if (dialog.dataset.busy === '1') event.preventDefault(); });
    });
    const write = async (url, payload) => {
        await window.axios.get(root.dataset.csrfUrl);
        return window.axios.post(url, payload);
    };
    const cell = (row, value) => {
        const td = document.createElement('td');
        td.textContent = value ?? '—';
        row.append(td);
        return td;
    };

    let reloadList = async () => {};
    if (find('[data-movements-list]')) {
        const body = find('[data-movements-rows]');
        const search = find('[data-movements-search]');
        const message = find('[data-movements-message]');
        const retry = find('[data-movements-retry]');
        const previous = find('[data-movements-previous]');
        const next = find('[data-movements-next]');
        let rows = [], page = 1, last = 1, request = 0, loading = false, loaded = false;
        const controls = () => {
            search.disabled = loading || !loaded;
            previous.disabled = loading || !loaded || page <= 1;
            next.disabled = loading || !loaded || page >= last;
        };
        const render = () => {
            const query = normalize(search.value.trim());
            const matches = rows.filter((item) => !query || [item.id, item.bien?.cbi, item.bien?.descripcion,
                item.ambiente_origen?.nombre, item.ambiente_destino?.nombre].some((value) => normalize(value).includes(query)));
            body.replaceChildren();
            find('[data-movements-summary]').textContent = query ? `${matches.length} coincidencias en esta página.` : '';
            if (!matches.length) {
                const row = document.createElement('tr');
                const td = cell(row, query ? 'No hay coincidencias en esta página.' : 'No hay movimientos para mostrar.');
                td.colSpan = 8; td.className = 'catalog-empty-cell'; body.append(row);
            }
            matches.forEach((item) => {
                const row = document.createElement('tr');
                [item.id, date(item.fecha_movimiento), item.bien?.cbi, item.bien?.descripcion,
                    item.ambiente_origen?.nombre, item.ambiente_destino?.nombre].forEach((value) => cell(row, value));
                const badge = document.createElement('span');
                badge.className = `catalog-badge ${item.estado === 'finalizado' ? 'catalog-badge-active' : 'movement-badge-pending'}`;
                badge.textContent = state(item.estado); cell(row, '').append(badge);
                const actions = cell(row, ''); actions.className = 'catalog-row-actions';
                const button = document.createElement('button'); button.type = 'button'; button.textContent = 'Ver detalle';
                button.addEventListener('click', () => showDetail(item.id)); actions.append(button); body.append(row);
            });
        };
        const load = async (target = page) => {
            const token = ++request;
            loading = true; loaded = false; rows = []; search.value = ''; render(); controls();
            message.textContent = 'Cargando movimientos…'; retry.hidden = true;
            try {
                const { data } = await window.axios.get(base, { params: { page: target } });
                if (token !== request) return;
                if (target > Math.max(1, data.last_page)) return load(Math.max(1, data.last_page));
                rows = data.data; page = data.current_page; last = Math.max(1, data.last_page); loaded = true;
                find('[data-movements-page]').textContent = `Página ${page} de ${last}`;
                find('[data-movements-count]').textContent = `${data.total} movimientos registrados`;
                render(); message.textContent = '';
            } catch (error) {
                if (token !== request) return;
                message.textContent = errorText(error); retry.hidden = [401, 403].includes(error.response?.status);
                find('[data-movements-page]').textContent = 'Página no disponible';
            } finally { if (token === request) { loading = false; controls(); } }
        };
        reloadList = () => load();
        search.addEventListener('input', render);
        previous.addEventListener('click', () => load(page - 1)); next.addEventListener('click', () => load(page + 1));
        retry.addEventListener('click', () => load());
        load(1);
    }
    const renderDetail = (item, message = '') => {
        current = item; detailId = item.id;
        const fields = { id: item.id, estado: state(item.estado), fecha: date(item.fecha_movimiento), bien: item.bien_id,
            cbi: item.bien?.cbi, descripcion: item.bien?.descripcion, origen: item.ambiente_origen?.nombre,
            destino: item.ambiente_destino?.nombre, ordenado: userName(item.ordenado_por), ejecutado: userName(item.ejecutado_por),
            motivo: item.motivo, observaciones: item.observaciones, firma: date(item.fecha_firma) };
        Object.entries(fields).forEach(([key, value]) => { find(`[data-detail-${key}]`).textContent = value ?? '—'; });
        find('[data-open-upload]').hidden = item.estado !== 'pendiente_firma';
        find('[data-download-signed]').hidden = item.estado !== 'finalizado';
        find('[data-sign-date]').hidden = item.estado !== 'finalizado';
        find('[data-detail-flow]').textContent = item.estado === 'pendiente_firma'
            ? 'Pendiente de firma: la ubicación del bien aún no cambia. Descarga la constancia y sube el PDF firmado para finalizar el traslado.'
            : 'Movimiento finalizado. La ubicación del bien se actualizó al destino al cargar el PDF firmado.';
        find('[data-download-message]').textContent = '';
        find('[data-download-generated]').disabled = false; find('[data-download-signed]').disabled = false;
        find('[data-detail-retry]').hidden = true;
        detailMessage.textContent = message; detailContent.hidden = false;
        if (!detailDialog.open) detailDialog.showModal();
    };
    async function showDetail(id) {
        const token = ++detailRequest;
        downloadRequest += 1; current = null; detailId = id; detailContent.hidden = true;
        detailMessage.textContent = 'Cargando detalle…'; find('[data-detail-retry]').hidden = true;
        if (!detailDialog.open) detailDialog.showModal();
        try {
            const { data } = await window.axios.get(endpoint(id));
            if (token !== detailRequest) return;
            renderDetail(data.data);
        } catch (error) {
            if (token !== detailRequest) return;
            detailMessage.textContent = errorText(error);
            find('[data-detail-retry]').hidden = [401, 403, 404].includes(error.response?.status);
        }
    }
    detailDialog.addEventListener('close', () => { detailRequest += 1; downloadRequest += 1; current = null; });
    find('[data-detail-retry]').addEventListener('click', () => showDetail(detailId));
    find('[data-movement-lookup-form]').addEventListener('submit', (event) => {
        event.preventDefault(); const form = event.currentTarget;
        if (form.reportValidity()) showDetail(Number(form.elements.id.value));
    });
    const fill = (select, items, placeholder, label) => {
        select.replaceChildren(new Option(placeholder, ''));
        items.forEach((item) => select.add(new Option(label(item), String(item.id))));
    };
    const selectedBien = () => options?.bienes.find((item) => item.id === Number(createForm.elements.bien_id.value));
    const updateOrigin = () => {
        const bien = selectedBien();
        const origin = options?.ambientes.find((item) => item.id === bien?.ambiente_id);
        find('[data-origin-label]').value = !bien ? '—' : bien.ambiente_id === null ? '—' : origin?.nombre ?? `Ambiente ID ${bien.ambiente_id}`;
        const destination = createForm.elements.ambiente_destino_id.value;
        fill(createForm.elements.ambiente_destino_id, options?.ambientes.filter((item) => item.id !== bien?.ambiente_id) ?? [], 'Selecciona un destino', (item) => item.nombre);
        if (Array.from(createForm.elements.ambiente_destino_id.options).some((item) => item.value === destination)) createForm.elements.ambiente_destino_id.value = destination;
    };
    const loadOptions = async () => {
        optionsLoaded = false; busy(createDialog, true);
        const message = find('[data-form-message]', createForm); message.textContent = 'Cargando opciones…';
        const previous = Object.fromEntries(['bien_id', 'ordenado_por', 'ejecutado_por'].map((key) => [key, createForm.elements[key].value]));
        find('[data-options-retry]').hidden = true;
        try {
            const { data } = await window.axios.get(root.dataset.optionsUrl);
            options = data.data;
            fill(createForm.elements.bien_id, options.bienes, 'Selecciona un bien', (item) => `ID ${item.id} · CBI ${item.cbi ?? '—'} · ${item.descripcion}`);
            ['ordenado_por', 'ejecutado_por'].forEach((key) => fill(createForm.elements[key], options.responsables, 'Selecciona un usuario', userName));
            Object.entries(previous).forEach(([key, value]) => {
                if (Array.from(createForm.elements[key].options).some((item) => item.value === value)) createForm.elements[key].value = value;
            });
            updateOrigin(); optionsLoaded = true; message.textContent = '';
        } catch (error) { message.textContent = errorText(error); find('[data-options-retry]').hidden = [401, 403].includes(error.response?.status); }
        finally { busy(createDialog, false); find('[data-save-movement]').disabled = !optionsLoaded; }
    };
    createForm.elements.bien_id.addEventListener('change', updateOrigin);
    find('[data-options-retry]').addEventListener('click', loadOptions);
    find('[data-new-movement]').addEventListener('click', () => {
        createForm.reset(); options = null; find('[data-origin-label]').value = '—';
        const now = new Date(); const pad = (value) => String(value).padStart(2, '0');
        createForm.elements.fecha_movimiento.value = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
        createDialog.showModal(); loadOptions();
    });
    createForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!optionsLoaded || createDialog.dataset.busy === '1' || !createForm.reportValidity()) return;
        const bien = selectedBien(); if (!bien) return;
        const message = find('[data-form-message]', createForm);
        if (!createForm.elements.motivo.value.trim()) { message.textContent = 'Ingresa el motivo del movimiento.'; return; }
        let timestamp = createForm.elements.fecha_movimiento.value.replace('T', ' ');
        if (timestamp.length === 16) timestamp += ':00';
        const payload = { bien_id: bien.id, ambiente_origen_id: bien.ambiente_id ?? null,
            ambiente_destino_id: Number(createForm.elements.ambiente_destino_id.value), ordenado_por: Number(createForm.elements.ordenado_por.value),
            ejecutado_por: Number(createForm.elements.ejecutado_por.value), fecha_movimiento: timestamp,
            motivo: createForm.elements.motivo.value, observaciones: createForm.elements.observaciones.value || null };
        busy(createDialog, true); message.textContent = '';
        try {
            const { data } = await write(base, payload);
            createDialog.close(); detailRequest += 1;
            notice.textContent = `Movimiento ID ${data.data.id} registrado. Estado: ${state(data.data.estado)}. Conserva este ID para consultar el movimiento.`;
            renderDetail(data.data, notice.textContent); reloadList();
        } catch (error) {
            message.textContent = errorText(error);
            if (error.response?.status === 409) find('[data-options-retry]').hidden = false;
        } finally { busy(createDialog, false); find('[data-save-movement]').disabled = !optionsLoaded; }
    });
    const download = async (signed) => {
        if (!current) return;
        const id = current.id, token = ++downloadRequest;
        const buttons = [find('[data-download-generated]'), find('[data-download-signed]')];
        buttons.forEach((button) => { button.disabled = true; });
        const message = find('[data-download-message]'); message.textContent = 'Preparando descarga…';
        try {
            const response = await window.axios.get(endpoint(id, signed ? '/pdf-firmado' : '/pdf-generado'), { responseType: 'blob' });
            const url = URL.createObjectURL(response.data), link = document.createElement('a');
            link.href = url; link.download = `movimiento-${id}${signed ? '-firmado' : ''}.pdf`;
            document.body.append(link); link.click(); link.remove(); window.setTimeout(() => URL.revokeObjectURL(url), 1000);
            if (token === downloadRequest) message.textContent = 'Descarga preparada.';
        } catch (error) {
            if (error.response?.data instanceof Blob) {
                try { error.response.data = JSON.parse(await error.response.data.text()); } catch { /* Non-JSON server error. */ }
            }
            if (token === downloadRequest) message.textContent = errorText(error);
        } finally { if (token === downloadRequest) buttons.forEach((button) => { button.disabled = false; }); }
    };
    find('[data-download-generated]').addEventListener('click', () => download(false));
    find('[data-download-signed]').addEventListener('click', () => download(true));
    find('[data-open-upload]').addEventListener('click', () => {
        if (current?.estado !== 'pendiente_firma') return;
        uploadTarget = current.id; uploadForm.reset(); busy(uploadDialog, false);
        find('[data-form-message]', uploadForm).textContent = '';
        find('[data-upload-identity]').textContent = `Movimiento ID ${current.id} · Pendiente de firma`;
        find('[data-upload-warning]').textContent = `Al subir este PDF firmado, el movimiento se finalizará y recién entonces se cambiará la ubicación del bien al ambiente destino: ${current.ambiente_destino?.nombre ?? '—'}.`;
        uploadDialog.showModal();
    });
    uploadForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (uploadDialog.dataset.busy === '1' || !uploadForm.reportValidity()) return;
        const file = uploadForm.elements.archivo.files[0], message = find('[data-form-message]', uploadForm);
        if (file.size > 10240 * 1024) { message.textContent = 'El PDF firmado no puede superar los 10 MB.'; return; }
        const payload = new FormData(); payload.append('archivo', file);
        busy(uploadDialog, true); message.textContent = '';
        try {
            const { data } = await write(endpoint(uploadTarget, '/pdf-firmado'), payload);
            uploadDialog.close(); detailRequest += 1;
            notice.textContent = `Movimiento ID ${data.data.id} finalizado. La ubicación del bien se actualizó al destino.`;
            renderDetail(data.data, notice.textContent); reloadList();
        } catch (error) {
            message.textContent = errorText(error);
            if ([404, 409].includes(error.response?.status)) await showDetail(uploadTarget);
        } finally {
            busy(uploadDialog, false);
            uploadForm.querySelector('[type="submit"]').disabled = current?.id !== uploadTarget || current?.estado !== 'pendiente_firma';
        }
    });
}
