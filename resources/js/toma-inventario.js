const root = document.querySelector('[data-inventory-root]');

if (root) {
    const find = (selector, parent = root) => parent.querySelector(selector);
    const detailDialog = find('[data-inventory-detail-dialog]');
    const detailMessage = find('[data-detail-message]');
    const detailFields = find('[data-inventory-detail-fields]');
    let detailRecord;
    let detailRequest = 0;
    const recordUrl = (record) => `${root.dataset.inventoriesUrl}/${encodeURIComponent(record.bien_id)}/${encodeURIComponent(record.anio)}`;
    const normalize = (value) => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
    const cell = (row, value) => {
        const td = document.createElement('td');
        td.textContent = value ?? '—';
        row.append(td);
        return td;
    };
    const empty = (body, columns, searched) => {
        const row = document.createElement('tr');
        const td = cell(row, searched ? 'No hay coincidencias en esta página.' : 'No hay registros para mostrar.');
        td.colSpan = columns;
        td.className = 'catalog-empty-cell';
        body.append(row);
    };
    const action = (parent, label, handler) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.addEventListener('click', handler);
        parent.append(button);
    };
    const errorText = (error) => {
        const status = error.response?.status;
        if (status === 401) window.location.assign(root.dataset.loginUrl);
        const data = error.response?.data;
        const errors = data?.errors && Object.values(data.errors).flat();
        return errors?.length ? errors.join(' ') : data?.message || ({
            401: 'La sesión ha expirado.', 403: 'No tienes permiso para esta operación.',
            404: 'El registro no existe o ya no está disponible para tu usuario.',
            419: 'El token de seguridad ha expirado. Recarga la página.',
        }[status]) || 'No se pudo completar la operación. Inténtalo de nuevo.';
    };
    const busy = (dialog, value) => {
        dialog.dataset.busy = value ? '1' : '0';
        dialog.querySelectorAll('button, input').forEach((element) => { element.disabled = value; });
    };
    root.querySelectorAll('dialog').forEach((dialog) => {
        dialog.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => {
            if (dialog.dataset.busy !== '1') dialog.close();
        }));
        dialog.addEventListener('cancel', (event) => { if (dialog.dataset.busy === '1') event.preventDefault(); });
    });
    const pagination = (prefix, url, values, render) => {
        const search = find(`[data-${prefix}-search]`);
        const summary = find(`[data-${prefix}-summary]`);
        const message = find(`[data-${prefix}-message]`);
        const previous = find(`[data-${prefix}-previous]`);
        const next = find(`[data-${prefix}-next]`);
        const retry = find(`[data-${prefix}-retry]`);
        let page = 1, last = 1, items = [], request = 0, loading = false, loaded = false;
        const controls = () => {
            const blocked = search.closest('dialog')?.dataset.busy === '1';
            search.disabled = blocked || loading || !loaded;
            previous.disabled = blocked || loading || !loaded || page <= 1;
            next.disabled = blocked || loading || !loaded || page >= last;
        };
        const filter = () => {
            const query = normalize(search.value.trim());
            const matches = items.filter((item) => !query || values(item).some((value) => normalize(value).includes(query)));
            render(matches, Boolean(query));
            summary.textContent = query ? `${matches.length} coincidencias en esta página.` : '';
        };
        const load = async (target = page, notice = '') => {
            const token = ++request;
            loading = true;
            loaded = false;
            search.value = '';
            items = [];
            filter();
            controls();
            message.textContent = 'Cargando…';
            retry.hidden = true;
            try {
                const { data } = await window.axios.get(url, { params: { page: target } });
                if (token !== request) return;
                if (target > Math.max(1, data.last_page)) return load(Math.max(1, data.last_page), notice);
                items = data.data;
                page = data.current_page;
                last = Math.max(1, data.last_page);
                loaded = true;
                find(`[data-${prefix}-page]`).textContent = `Página ${page} de ${last}`;
                const count = find(`[data-${prefix}-count]`);
                if (count) count.textContent = `${data.total} registros`;
                filter();
                message.textContent = notice;
            } catch (error) {
                if (token !== request) return;
                message.textContent = errorText(error);
                find(`[data-${prefix}-page]`).textContent = 'Página no disponible';
                retry.hidden = [401, 403].includes(error.response?.status);
            } finally { if (token === request) { loading = false; controls(); } }
        };
        search.addEventListener('input', filter);
        previous.addEventListener('click', () => load(page - 1));
        next.addEventListener('click', () => load(page + 1));
        retry.addEventListener('click', () => load(page));
        return { load, controls, invalidate: () => { request += 1; } };
    };
    const showDetail = async (record) => {
        detailRecord = record;
        const token = ++detailRequest;
        detailFields.hidden = true;
        detailMessage.textContent = 'Cargando detalle…';
        find('[data-detail-retry]').hidden = true;
        if (!detailDialog.open) detailDialog.showModal();
        try {
            const { data } = await window.axios.get(recordUrl(record));
            if (token !== detailRequest) return;
            const item = data.data;
            const fields = { bien: item.bien_id, anio: item.anio, cbi: item.bien?.cbi, inventario: item.inventario, descripcion: item.bien?.descripcion };
            Object.entries(fields).forEach(([key, value]) => { find(`[data-detail-${key}]`).textContent = value ?? '—'; });
            detailFields.hidden = false;
            detailMessage.textContent = '';
        } catch (error) {
            if (token !== detailRequest) return;
            detailMessage.textContent = errorText(error);
            find('[data-detail-retry]').hidden = [401, 403, 404].includes(error.response?.status);
        }
    };
    detailDialog.addEventListener('close', () => { detailRequest += 1; });
    find('[data-detail-retry]').addEventListener('click', () => showDetail(detailRecord));
    const createDialog = find('[data-create-inventory-dialog]');
    const inventoryList = pagination('inventory', root.dataset.inventoriesUrl,
        (item) => [item.bien?.cbi, item.bien?.descripcion, item.anio, item.inventario], (items, searched) => {
            const body = find('[data-inventory-rows]');
            body.replaceChildren();
            if (!items.length) empty(body, 6, searched);
            items.forEach((item) => {
                const row = document.createElement('tr');
                [item.bien_id, item.bien?.cbi, item.bien?.descripcion, item.anio, item.inventario].forEach((value) => cell(row, value));
                const actions = cell(row, '');
                actions.className = 'catalog-row-actions inventory-row-actions';
                action(actions, 'Ver detalle', () => showDetail(item));
                if (createDialog) {
                    action(actions, 'Editar', () => openEdit(item));
                    action(actions, 'Eliminar', () => openDelete(item));
                }
                body.append(row);
            });
        });
    root.querySelectorAll('[data-export-url]').forEach((button) => button.addEventListener('click', async () => {
        const message = find('[data-export-message]');
        const buttons = root.querySelectorAll('[data-export-url]');
        buttons.forEach((item) => { item.disabled = true; });
        message.textContent = 'Preparando descarga…';
        try {
            const response = await window.axios.get(button.dataset.exportUrl, { responseType: 'blob' });
            const url = URL.createObjectURL(response.data);
            const link = document.createElement('a');
            link.href = url;
            link.download = button.dataset.exportFilename;
            document.body.append(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
            message.textContent = 'Descarga preparada.';
        } catch (error) {
            if (error.response?.data instanceof Blob) {
                try { error.response.data = JSON.parse(await error.response.data.text()); } catch { /* Non-JSON server error. */ }
            }
            message.textContent = errorText(error);
        } finally { buttons.forEach((item) => { item.disabled = false; }); }
    }));

    let openEdit, openDelete;
    if (createDialog) {
        const createForm = find('[data-create-inventory-form]');
        const editDialog = find('[data-edit-inventory-dialog]');
        const editForm = find('[data-edit-inventory-form]');
        const deleteDialog = find('[data-delete-inventory-dialog]');
        const deleteForm = find('[data-delete-inventory-form]');
        let selected = null, editing = null, deleting = null;
        const write = async (method, url, payload) => {
            await window.axios.get(root.dataset.csrfUrl);
            return window.axios.request({ method, url, data: payload });
        };
        const selection = () => {
            find('[data-selected-bien]').textContent = selected
                ? `Bien ID ${selected.id} · CBI ${selected.cbi ?? '—'} · ${selected.descripcion ?? '—'}` : 'Ningún bien seleccionado.';
            find('[data-save-inventory]').disabled = !selected || createDialog.dataset.busy === '1';
        };
        const picker = pagination('picker', root.dataset.bienesUrl, (item) => [item.cbi, item.descripcion], (items, searched) => {
            const body = find('[data-picker-rows]');
            body.replaceChildren();
            if (!items.length) empty(body, 4, searched);
            items.forEach((item) => {
                const row = document.createElement('tr');
                const radio = document.createElement('input');
                radio.type = 'radio'; radio.name = 'selected_bien'; radio.value = String(item.id);
                radio.checked = selected?.id === item.id;
                radio.disabled = createDialog.dataset.busy === '1';
                radio.setAttribute('aria-label', `Seleccionar bien ${item.id}: ${item.descripcion}`);
                radio.addEventListener('change', () => { selected = item; selection(); });
                cell(row, '').append(radio);
                [item.id, item.cbi, item.descripcion].forEach((value) => cell(row, value));
                body.append(row);
            });
        });
        const messageFor = (form) => find('[data-form-message]', form);
        const inventoryValue = (form) => {
            const value = form.elements.inventario.value;
            if (!value.trim()) { messageFor(form).textContent = 'Ingresa el texto de inventario.'; return null; }
            return value;
        };
        find('[data-new-inventory]').addEventListener('click', () => {
            busy(createDialog, false);
            createForm.reset(); selected = null; selection();
            messageFor(createForm).textContent = '';
            createDialog.showModal(); picker.load(1);
        });
        createDialog.addEventListener('close', () => picker.invalidate());
        createForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!selected || createDialog.dataset.busy === '1' || !createForm.reportValidity()) return;
            const inventario = inventoryValue(createForm);
            if (inventario === null) return;
            const payload = { bien_id: selected.id, anio: Number(createForm.elements.anio.value), inventario };
            busy(createDialog, true); messageFor(createForm).textContent = '';
            try {
                await write('post', root.dataset.inventoriesUrl, payload);
                createDialog.close(); await inventoryList.load(undefined, 'Inventario registrado correctamente.');
            } catch (error) { messageFor(createForm).textContent = errorText(error); }
            finally { busy(createDialog, false); picker.controls(); selection(); }
        });
        openEdit = async (record) => {
            editing = { bien_id: record.bien_id, anio: record.anio };
            editForm.reset(); editDialog.showModal(); busy(editDialog, true);
            find('[data-edit-identity]').textContent = `Bien ID ${record.bien_id} · Año ${record.anio}`;
            messageFor(editForm).textContent = 'Cargando registro…';
            try {
                const { data } = await window.axios.get(recordUrl(editing));
                editForm.elements.inventario.value = data.data.inventario;
                busy(editDialog, false); messageFor(editForm).textContent = '';
            } catch (error) {
                busy(editDialog, false);
                editForm.querySelector('[type="submit"]').disabled = true;
                messageFor(editForm).textContent = errorText(error);
            }
        };
        editForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (editDialog.dataset.busy === '1' || !editForm.reportValidity()) return;
            const inventario = inventoryValue(editForm);
            if (inventario === null) return;
            busy(editDialog, true); messageFor(editForm).textContent = '';
            try {
                // The composite identity stays in the URL; only inventario is editable.
                await write('patch', recordUrl(editing), { inventario });
                editDialog.close(); await inventoryList.load(undefined, 'Inventario actualizado correctamente.');
            } catch (error) { messageFor(editForm).textContent = errorText(error); }
            finally { busy(editDialog, false); }
        });
        openDelete = (record) => {
            deleting = { bien_id: record.bien_id, anio: record.anio };
            busy(deleteDialog, false);
            messageFor(deleteForm).textContent = '';
            find('[data-delete-identity]').textContent = `¿Eliminar el inventario ${record.inventario} del bien ID ${record.bien_id}, año ${record.anio}?`;
            deleteDialog.showModal();
        };
        deleteForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (deleteDialog.dataset.busy === '1') return;
            busy(deleteDialog, true); messageFor(deleteForm).textContent = '';
            try {
                await write('delete', recordUrl(deleting));
                deleteDialog.close(); await inventoryList.load(undefined, 'Registro de inventario eliminado correctamente.');
            } catch (error) { messageFor(deleteForm).textContent = errorText(error); }
            finally { busy(deleteDialog, false); }
        });
    }
    inventoryList.load(1);
}
