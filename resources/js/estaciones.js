const root = document.querySelector('[data-stations-root]');

if (root) {
    const find = (selector, parent = root) => parent.querySelector(selector);
    const stationDialog = find('[data-station-dialog]');
    const stationForm = find('[data-station-form]');
    const detailDialog = find('[data-station-detail-dialog]');
    const detailMessage = find('[data-detail-message]');
    const detailContent = find('[data-station-detail-content]');
    const assignmentDialog = find('[data-assignment-dialog]');
    const assignmentForm = find('[data-assignment-form]');
    const withdrawDialog = find('[data-withdraw-dialog]');
    const withdrawForm = find('[data-withdraw-form]');
    const deleteDialog = find('[data-delete-dialog]');
    const deleteForm = find('[data-delete-form]');
    let station = null;
    let detailId = null;
    let detailRequest = 0;
    let editingId = null;
    let formRequest = 0;
    let component = null;
    let selectedBien = null;
    let assignmentStationId = null;
    let deleteId = null;

    const normalize = (value) => String(value ?? '').normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
    const date = (value) => value ? String(value).slice(0, 10) : '—';
    const endpoint = (base, id) => `${base}/${encodeURIComponent(id)}`;
    const cell = (row, value) => {
        const td = document.createElement('td');
        td.textContent = value ?? '—';
        row.append(td);
        return td;
    };
    const emptyRow = (body, columns, text) => {
        const td = cell(document.createElement('tr'), text);
        td.colSpan = columns;
        td.className = 'catalog-empty-cell';
        body.append(td.parentElement);
    };
    const badge = (row, active) => {
        const span = document.createElement('span');
        span.className = `catalog-badge catalog-badge-${active ? 'active' : 'inactive'}`;
        span.textContent = active ? 'Sí' : 'No';
        cell(row, '').append(span);
    };
    const action = (parent, text, handler) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = text;
        button.addEventListener('click', handler);
        parent.append(button);
        return button;
    };
    const errorText = (error) => {
        const status = error.response?.status;
        if (status === 401) {
            window.location.assign(root.dataset.loginUrl);
            return 'La sesión ha expirado.';
        }
        const data = error.response?.data;
        const errors = data?.errors && Object.values(data.errors).flat();
        if (status === 422 && errors?.length) return errors.join(' ');
        return data?.message || ({
            403: 'No tienes permiso para realizar esta acción.',
            404: 'El registro ya no está disponible. Actualiza el listado.',
            409: 'La operación no se puede completar por un conflicto con el registro actual.',
            419: 'La sesión o el token de seguridad ha expirado. Recarga la página.',
        }[status]) || 'No se pudo completar la operación. Inténtalo de nuevo.';
    };
    const write = async (method, url, payload) => {
        await window.axios.get(root.dataset.csrfUrl);
        return window.axios.request({ method, url, data: payload });
    };
    const busy = (dialog, value) => {
        dialog.dataset.busy = value ? '1' : '0';
        dialog.querySelectorAll('button, input, select').forEach((element) => { element.disabled = value; });
    };
    root.querySelectorAll('dialog').forEach((dialog) => {
        dialog.querySelectorAll('[data-close-dialog]').forEach((button) => {
            button.addEventListener('click', () => {
                if (dialog.dataset.busy !== '1') dialog.close();
            });
        });
        dialog.addEventListener('cancel', (event) => {
            if (dialog.dataset.busy === '1') event.preventDefault();
        });
    });

    // Fetch only the requested backend page. Search never fetches additional pages.
    const paginatedList = (prefix, url, searchFields, render) => {
        const search = find(`[data-${prefix}-search]`);
        const summary = find(`[data-${prefix}-search-summary]`) || find(`[data-${prefix}-summary]`);
        const message = find(`[data-${prefix}-message]`);
        const previous = find(`[data-${prefix}-previous]`);
        const next = find(`[data-${prefix}-next]`);
        const retry = find(`[data-${prefix}-retry]`);
        const pageLabel = find(`[data-${prefix}-page]`);
        let items = [];
        let page = 1;
        let lastPage = 1;
        let request = 0;
        let loading = false;
        let loaded = false;
        const filter = () => {
            const query = normalize(search.value.trim());
            const matches = items.filter((item) => !query || searchFields.some((key) => normalize(item[key]).includes(query)));
            render(matches, Boolean(query));
            summary.textContent = query ? `${matches.length} coincidencias en esta página.` : '';
        };
        const controls = () => {
            const blocked = search.closest('dialog')?.dataset.busy === '1';
            search.disabled = blocked || loading || !loaded;
            previous.disabled = blocked || loading || !loaded || page <= 1;
            next.disabled = blocked || loading || !loaded || page >= lastPage;
        };
        const load = async (target = page, notice = '') => {
            const token = ++request;
            loading = true;
            loaded = false;
            items = [];
            search.value = '';
            filter();
            controls();
            message.textContent = 'Cargando…';
            retry.hidden = true;
            try {
                const { data } = await window.axios.get(url, { params: { page: target } });
                if (token !== request) return;
                // A deletion may remove the final page.
                if (target > Math.max(1, data.last_page)) return load(Math.max(1, data.last_page), notice);
                items = data.data;
                page = data.current_page;
                lastPage = Math.max(1, data.last_page);
                loaded = true;
                pageLabel.textContent = `Página ${page} de ${lastPage} · ${data.total} registros`;
                const count = find(`[data-${prefix}-count]`);
                if (count) count.textContent = `${data.total} estaciones registradas`;
                filter();
                message.textContent = notice;
            } catch (error) {
                if (token !== request) return;
                message.textContent = errorText(error);
                pageLabel.textContent = 'Página no disponible';
                retry.hidden = [401, 403].includes(error.response?.status);
            } finally {
                if (token === request) {
                    loading = false;
                    controls();
                }
            }
        };
        search.addEventListener('input', filter);
        previous.addEventListener('click', () => load(page - 1));
        next.addEventListener('click', () => load(page + 1));
        retry.addEventListener('click', () => load(page));
        return { load, filter, controls, invalidate: () => { request += 1; } };
    };

    const stationsList = paginatedList('stations', root.dataset.stationsUrl, ['codigo', 'descripcion'], (items, searched) => {
        const body = find('[data-stations-rows]');
        body.replaceChildren();
        if (!items.length) emptyRow(body, 4, searched ? 'No hay coincidencias en esta página.' : 'No hay estaciones para mostrar.');
        items.forEach((item) => {
            const row = document.createElement('tr');
            cell(row, item.codigo).className = 'catalog-code';
            cell(row, item.descripcion);
            badge(row, item.activo);
            const actions = cell(row, '');
            actions.className = 'catalog-row-actions stations-row-actions';
            action(actions, 'Ver detalle', () => showDetail(item.id));
            action(actions, 'Editar', () => openStationForm(item.id));
            body.append(row);
        });
    });

    const renderComponents = (items) => {
        const activeBody = find('[data-active-components]');
        const historyBody = find('[data-components-history]');
        activeBody.replaceChildren();
        historyBody.replaceChildren();
        const active = items.filter((item) => item.activo);
        find('[data-active-count]').textContent = `${active.length} activos`;
        if (!active.length) emptyRow(activeBody, 4, 'No hay componentes activos.');
        if (!items.length) emptyRow(historyBody, 5, 'No hay asignaciones registradas.');
        active.forEach((item) => {
            const row = document.createElement('tr');
            cell(row, item.bien?.cbi);
            cell(row, item.bien?.descripcion);
            cell(row, date(item.fecha_asignacion));
            const actions = cell(row, '');
            actions.className = 'catalog-row-actions';
            action(actions, 'Retirar', () => openWithdrawal(item));
            activeBody.append(row);
        });
        items.forEach((item) => {
            const row = document.createElement('tr');
            cell(row, item.bien?.cbi);
            cell(row, item.bien?.descripcion);
            cell(row, date(item.fecha_asignacion));
            cell(row, date(item.fecha_retiro));
            badge(row, item.activo);
            historyBody.append(row);
        });
    };
    const showDetail = async (id, notice = '') => {
        const token = ++detailRequest;
        detailId = id;
        station = null;
        detailContent.hidden = true;
        detailMessage.textContent = 'Cargando detalle…';
        find('[data-detail-retry]').hidden = true;
        if (!detailDialog.open) detailDialog.showModal();
        try {
            const { data } = await window.axios.get(endpoint(root.dataset.stationsUrl, id));
            if (token !== detailRequest || !detailDialog.open) return;
            station = data.data;
            find('[data-detail-code]').textContent = station.codigo;
            find('[data-detail-active]').textContent = station.activo ? 'Sí' : 'No';
            find('[data-detail-description]').textContent = station.descripcion ?? '—';
            renderComponents(station.componentes);
            const remove = find('[data-delete-station]');
            remove.disabled = station.componentes.length > 0;
            remove.title = remove.disabled ? 'La estación tiene componentes registrados en su historial.' : 'Eliminar estación sin historial';
            find('[data-delete-note]').textContent = remove.disabled
                ? 'No se puede eliminar esta estación porque tiene componentes registrados en su historial, aunque ya hayan sido retirados. El historial de asignaciones se conserva.'
                : 'Esta estación no tiene componentes registrados en su historial. Puedes eliminarla; esta acción no se puede deshacer.';
            detailMessage.textContent = notice;
            detailContent.hidden = false;
        } catch (error) {
            if (token !== detailRequest) return;
            detailMessage.textContent = errorText(error);
            find('[data-detail-retry]').hidden = [401, 403, 404].includes(error.response?.status);
        }
    };
    detailDialog.addEventListener('close', () => { detailRequest += 1; station = null; });
    find('[data-detail-retry]').addEventListener('click', () => showDetail(detailId));

    const openStationForm = async (id = null) => {
        const token = ++formRequest;
        editingId = id;
        stationForm.reset();
        busy(stationDialog, false);
        const message = find('[data-form-message]', stationForm);
        message.textContent = '';
        find('#station-form-title').textContent = id === null ? 'Registrar estación' : 'Editar estación';
        stationDialog.showModal();
        if (id === null) return;
        busy(stationDialog, true);
        message.textContent = 'Cargando estación…';
        try {
            const { data } = await window.axios.get(endpoint(root.dataset.stationsUrl, id));
            if (token !== formRequest) return;
            stationForm.elements.codigo.value = data.data.codigo;
            stationForm.elements.descripcion.value = data.data.descripcion ?? '';
            stationForm.elements.activo.value = data.data.activo ? '1' : '0';
            busy(stationDialog, false);
            message.textContent = '';
        } catch (error) {
            if (token !== formRequest) return;
            busy(stationDialog, false);
            stationForm.querySelector('[type="submit"]').disabled = true;
            message.textContent = errorText(error);
        }
    };
    stationDialog.addEventListener('close', () => { formRequest += 1; });
    find('[data-new-station]').addEventListener('click', () => openStationForm());
    find('[data-edit-detail]').addEventListener('click', () => { if (station) openStationForm(station.id); });
    stationForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (stationDialog.dataset.busy === '1' || !stationForm.reportValidity()) return;
        const message = find('[data-form-message]', stationForm);
        const codigo = stationForm.elements.codigo.value.trim();
        if (!codigo) { message.textContent = 'Ingresa el código de la estación.'; return; }
        const id = editingId;
        const payload = { codigo, descripcion: stationForm.elements.descripcion.value.trim() || null, activo: stationForm.elements.activo.value === '1' };
        busy(stationDialog, true);
        message.textContent = '';
        try {
            await write(id === null ? 'post' : 'patch', id === null ? root.dataset.stationsUrl : endpoint(root.dataset.stationsUrl, id), payload);
            stationDialog.close();
            await stationsList.load(undefined, id === null ? 'Estación registrada correctamente.' : 'Estación actualizada correctamente.');
            if (detailDialog.open && detailId === id) await showDetail(id, 'Estación actualizada correctamente.');
        } catch (error) {
            message.textContent = errorText(error);
        } finally { busy(stationDialog, false); }
    });

    const updateSelection = () => {
        find('[data-selected-bien]').textContent = selectedBien
            ? `Bien seleccionado: ${selectedBien.cbi ?? 'Sin CBI'} · ${selectedBien.descripcion}` : 'Ningún bien seleccionado.';
        find('[data-save-assignment]').disabled = !selectedBien || assignmentDialog.dataset.busy === '1';
    };
    const bienesList = paginatedList('bienes', root.dataset.bienesUrl, ['cbi', 'descripcion'], (items, searched) => {
        const body = find('[data-bienes-rows]');
        body.replaceChildren();
        if (!items.length) emptyRow(body, 4, searched ? 'No hay coincidencias en esta página.' : 'No hay bienes para mostrar.');
        items.forEach((item) => {
            const row = document.createElement('tr');
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'bien_seleccionado';
            input.value = String(item.id);
            input.checked = selectedBien?.id === item.id;
            input.disabled = assignmentDialog.dataset.busy === '1';
            input.setAttribute('aria-label', `Seleccionar ${item.cbi ?? 'bien'}: ${item.descripcion}`);
            input.addEventListener('change', () => { selectedBien = item; updateSelection(); });
            cell(row, '').append(input);
            cell(row, item.cbi);
            cell(row, item.descripcion);
            badge(row, item.activo);
            body.append(row);
        });
    });
    find('[data-assign-bien]').addEventListener('click', () => {
        if (!station) return;
        assignmentStationId = station.id;
        selectedBien = null;
        assignmentForm.reset();
        find('[data-form-message]', assignmentForm).textContent = '';
        find('[data-assignment-station]').textContent = `Estación ${station.codigo}`;
        updateSelection();
        assignmentDialog.showModal();
        bienesList.load(1);
    });
    assignmentDialog.addEventListener('close', () => bienesList.invalidate());
    assignmentForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!selectedBien || assignmentDialog.dataset.busy === '1' || !assignmentForm.reportValidity()) return;
        const id = assignmentStationId;
        const payload = { estacion_id: id, bien_id: selectedBien.id, activo: true };
        const assigned = assignmentForm.elements.fecha_asignacion.value;
        if (assigned) payload.fecha_asignacion = assigned;
        busy(assignmentDialog, true);
        const message = find('[data-form-message]', assignmentForm);
        message.textContent = '';
        try {
            await write('post', root.dataset.componentsUrl, payload);
            assignmentDialog.close();
            if (detailDialog.open) await showDetail(id, 'Bien asignado correctamente.');
        } catch (error) { message.textContent = errorText(error); }
        finally { busy(assignmentDialog, false); bienesList.controls(); updateSelection(); }
    });

    const openWithdrawal = (item) => {
        component = item;
        withdrawForm.reset();
        find('[data-form-message]', withdrawForm).textContent = '';
        find('[data-withdraw-bien]').textContent = `${item.bien?.cbi ?? 'Sin CBI'} · ${item.bien?.descripcion ?? 'Bien no disponible'}`;
        withdrawForm.elements.fecha_retiro.min = item.fecha_asignacion ? date(item.fecha_asignacion) : '';
        withdrawDialog.showModal();
    };
    withdrawForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!component || withdrawDialog.dataset.busy === '1' || !withdrawForm.reportValidity()) return;
        const payload = { activo: false, fecha_retiro: withdrawForm.elements.fecha_retiro.value };
        const id = component.estacion_id;
        busy(withdrawDialog, true);
        const message = find('[data-form-message]', withdrawForm);
        message.textContent = '';
        try {
            await write('patch', endpoint(root.dataset.componentsUrl, component.id), payload);
            withdrawDialog.close();
            if (detailDialog.open) await showDetail(id, 'Componente retirado. La asignación permanece en el historial.');
        } catch (error) { message.textContent = errorText(error); }
        finally { busy(withdrawDialog, false); }
    });
    find('[data-delete-station]').addEventListener('click', () => {
        if (!station || station.componentes.length) return;
        deleteId = station.id;
        busy(deleteDialog, false);
        find('[data-delete-description]').textContent = `¿Eliminar la estación ${station.codigo}? Esta acción no se puede deshacer.`;
        find('[data-form-message]', deleteForm).textContent = '';
        deleteDialog.showModal();
    });
    deleteForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (deleteDialog.dataset.busy === '1') return;
        busy(deleteDialog, true);
        const message = find('[data-form-message]', deleteForm);
        message.textContent = '';
        try {
            await write('delete', endpoint(root.dataset.stationsUrl, deleteId));
            deleteDialog.close();
            detailDialog.close();
            await stationsList.load(undefined, 'Estación eliminada correctamente.');
        } catch (error) {
            message.textContent = errorText(error);
            if ([404, 409].includes(error.response?.status) && detailDialog.open) await showDetail(deleteId);
        } finally {
            busy(deleteDialog, false);
            deleteForm.querySelector('[type="submit"]').disabled = !station || station.componentes.length > 0;
        }
    });

    stationsList.load(1);
}
