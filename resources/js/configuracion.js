const config = document.querySelector('[data-config-root]');

if (config) {
    const find = (selector, parent = config) => parent.querySelector(selector);
    const reserved = ['superadmin', 'administrador', 'director', 'coordinador', 'asistente'];
    const textField = (key, label, max, required = false) => ({ key, label, max, required, type: 'text' });
    const relation = (key, label, source, required = false) => ({ key, label, source, required, type: 'select' });
    const active = { key: 'activo', label: 'Activo', type: 'checkbox' };
    const name = (max) => textField('nombre', 'Nombre', max, true);
    const description = textField('descripcion', 'Descripción', 150);
    const modules = {
        usuarios: { title: 'Usuarios', paged: true, fields: [textField('nombres', 'Nombres', 100, true), textField('apellidos', 'Apellidos', 100, true), { key: 'correo', label: 'Correo', type: 'email', max: 120, required: true }, { key: 'password', label: 'Contraseña', type: 'password', max: 72, min: 8 }, relation('rol_id', 'Rol', 'roles', true), relation('especialidad_id', 'Especialidad', 'especialidades'), active], columns: [['id', 'ID'], ['nombres', 'Nombres'], ['apellidos', 'Apellidos'], ['correo', 'Correo'], ['rol.nombre', 'Rol'], ['especialidad.nombre', 'Especialidad'], ['activo', 'Activo']] },
        roles: { title: 'Roles', paged: true, fields: [name(50), description], columns: [['id', 'ID'], ['nombre', 'Nombre'], ['descripcion', 'Descripción']] },
        sedes: { title: 'Sedes', fields: [name(100), textField('direccion', 'Dirección', 200), active], columns: [['id', 'ID'], ['nombre', 'Nombre'], ['direccion', 'Dirección'], ['activo', 'Activo']] },
        ambientes: { title: 'Ambientes', fields: [name(100), relation('sede_id', 'Sede', 'sedes', true), relation('tipo_ambiente_id', 'Tipo de ambiente', 'tipos-ambiente'), relation('especialidad_id', 'Especialidad', 'especialidades'), ...['area', 'ancho', 'largo'].map((key) => ({ key, label: { area: 'Área', ancho: 'Ancho', largo: 'Largo' }[key], type: 'number' })), active], columns: [['id', 'ID'], ['nombre', 'Nombre'], ['sede.nombre', 'Sede'], ['tipo_ambiente.nombre', 'Tipo de ambiente'], ['especialidad.nombre', 'Especialidad'], ['activo', 'Activo']] },
        especialidades: { title: 'Especialidades', fields: [name(100)], columns: [['id', 'ID'], ['nombre', 'Nombre']] },
        'tipos-ambiente': { title: 'Tipos de ambiente', fields: [name(50)], columns: [['id', 'ID'], ['nombre', 'Nombre']] },
        'estados-bien': { title: 'Estados del bien', fields: [name(50), description], columns: [['id', 'ID'], ['nombre', 'Nombre'], ['descripcion', 'Descripción']] },
        'condiciones-bien': { title: 'Condiciones del bien', fields: [name(50), description], columns: [['id', 'ID'], ['nombre', 'Nombre'], ['descripcion', 'Descripción']] },
        'tipos-mantenimiento': { title: 'Tipos de mantenimiento', readonly: true, fields: [name(100), description], columns: [['id', 'ID'], ['nombre', 'Nombre'], ['descripcion', 'Descripción']] },
    };
    const endpoint = (module, id) => `${config.dataset.apiUrl}/${module}${id === undefined ? '' : `/${encodeURIComponent(id)}`}`;
    const value = (record, path) => path.split('.').reduce((item, key) => item?.[key], record);
    const display = (item) => item === null || item === undefined || item === '' ? '—' : typeof item === 'boolean' ? (item ? 'Sí' : 'No') : String(item);
    const normalized = (item) => String(item ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
    const errorText = (error) => {
        const code = error.response?.status;
        if (code === 401) window.location.assign(config.dataset.loginUrl);
        const data = error.response?.data;
        const errors = data?.errors ? Object.values(data.errors).flat() : [];
        return errors.length ? `${data.message || 'Revisa los datos.'} ${errors.join(' ')}` : data?.message || ({ 401: 'La sesión ha expirado.', 403: 'No tienes permiso para esta operación.', 404: 'El registro ya no existe.', 409: 'No se puede completar la operación porque el registro está en uso.', 419: 'La sesión ha expirado. Recarga la página.' }[code]) || 'No se pudo completar la operación. Inténtalo de nuevo.';
    };
    const element = (tag, content, parent, className = '') => {
        const node = document.createElement(tag);
        if (content !== undefined) node.textContent = content;
        node.className = className;
        parent?.append(node);
        return node;
    };
    const button = (label, parent, handler) => {
        const node = element('button', label, parent);
        node.type = 'button';
        node.addEventListener('click', handler);
        return node;
    };
    const dialogBusy = (dialog, busy) => {
        dialog.dataset.busy = busy ? '1' : '0';
        dialog.querySelectorAll('button').forEach((node) => { node.disabled = busy; });
    };
    config.querySelectorAll('dialog').forEach((dialog) => {
        dialog.querySelectorAll('[data-close]').forEach((node) => node.addEventListener('click', () => { if (dialog.dataset.busy !== '1') dialog.close(); }));
        dialog.addEventListener('cancel', (event) => { if (dialog.dataset.busy === '1') event.preventDefault(); });
    });
    let current = null, page = 1, last = 1, records = [], listToken = 0, listLoading = false, listReady = false;
    const search = find('[data-search]');
    const listMessage = find('[data-list-message]');
    const listControls = () => {
        find('[data-previous]').disabled = listLoading || !listReady || page <= 1;
        find('[data-next]').disabled = listLoading || !listReady || page >= last;
        search.disabled = listLoading || !listReady;
    };
    const render = () => {
        const schema = modules[current];
        const query = normalized(search.value.trim());
        const matches = records.filter((record) => !query || schema.columns.some(([path]) => normalized(display(value(record, path))).includes(query)));
        find('[data-search-summary]').textContent = query ? `${matches.length} coincidencias${schema.paged ? ' en esta página' : ' en este catálogo'}.` : '';
        const body = find('[data-rows]');
        body.replaceChildren();
        if (!matches.length) element('td', listLoading ? 'Cargando…' : query ? 'No hay coincidencias.' : 'No hay registros.', element('tr', undefined, body), 'catalog-empty-cell').colSpan = schema.columns.length + 1;
        matches.forEach((record) => {
            const row = element('tr', undefined, body);
            schema.columns.forEach(([path]) => element('td', display(value(record, path)), row));
            const actions = element('td', undefined, row, 'catalog-row-actions inventory-row-actions');
            button('Ver detalle', actions, () => openDetail(current, record));
            if (schema.readonly) return;
            button('Editar', actions, () => openEditor(current, record.id));
            if (current === 'usuarios') {
                const toggle = button(record.activo ? 'Desactivar' : 'Activar', actions, () => confirmAction(current, record, 'toggle'));
                if (record.activo && String(record.id) === config.dataset.userId) { toggle.disabled = true; toggle.title = 'No puedes desactivar tu propia cuenta.'; }
            } else {
                const remove = button('Eliminar', actions, () => confirmAction(current, record, 'delete'));
                if (current === 'roles' && reserved.includes(record.nombre)) { remove.disabled = true; remove.title = 'Los roles reservados no se pueden eliminar.'; }
            }
        });
    };
    const loadList = async (target = page, notice = '') => {
        const token = ++listToken;
        const key = current;
        listLoading = true; listReady = false; records = []; search.value = '';
        listMessage.textContent = 'Cargando…'; find('[data-list-retry]').hidden = true;
        listControls(); render();
        try {
            const { data } = await window.axios.get(endpoint(key), modules[key].paged ? { params: { page: target } } : {});
            if (token !== listToken) return;
            if (modules[key].paged && target > Math.max(1, data.last_page)) return loadList(Math.max(1, data.last_page), notice);
            records = data.data; page = modules[key].paged ? data.current_page : 1; last = modules[key].paged ? Math.max(1, data.last_page) : 1;
            listReady = true;
            find('[data-page]').textContent = modules[key].paged ? `Página ${page} de ${last} · ${data.total} registros` : '';
            listMessage.textContent = notice || `${modules[key].paged ? data.total : records.length} registros disponibles.`;
        } catch (error) {
            if (token !== listToken) return;
            listMessage.textContent = errorText(error); find('[data-page]').textContent = 'Página no disponible';
            find('[data-list-retry]').hidden = [401, 403].includes(error.response?.status);
        } finally { if (token === listToken) { listLoading = false; listControls(); render(); } }
    };
    config.querySelectorAll('[data-config-module]').forEach((node) => node.addEventListener('click', () => {
        current = node.dataset.configModule; page = 1;
        config.querySelectorAll('[data-config-module]').forEach((item) => item.setAttribute('aria-pressed', String(item === node)));
        const schema = modules[current];
        find('[data-workspace]').hidden = false; find('#config-module-title').textContent = schema.title;
        find('[data-create]').hidden = Boolean(schema.readonly);
        find('[data-module-note]').textContent = schema.readonly ? 'Solo consulta. Este catálogo no admite registro, edición ni eliminación.' : schema.paged ? 'La búsqueda se limita a la página visible.' : 'La búsqueda incluye el catálogo completo recibido de la API.';
        find('[data-pagination]').hidden = !schema.paged;
        const head = find('[data-head]'); head.replaceChildren();
        const row = element('tr', undefined, head);
        [...schema.columns.map(([, label]) => label), 'Acciones'].forEach((label) => element('th', label, row).scope = 'col');
        find('#config-module-title').focus(); loadList(1);
    }));
    search.addEventListener('input', render);
    find('[data-previous]').addEventListener('click', () => loadList(page - 1));
    find('[data-next]').addEventListener('click', () => loadList(page + 1));
    find('[data-list-retry]').addEventListener('click', () => loadList());

    const detailDialog = find('[data-detail-dialog]');
    let detailToken = 0, detailKey, detailRecord;
    const openDetail = async (key, record) => {
        detailKey = key; detailRecord = record;
        const token = ++detailToken;
        const fields = find('[data-detail-fields]'); fields.replaceChildren();
        const message = find('[data-detail-message]'); message.textContent = 'Cargando detalle…'; find('[data-detail-retry]').hidden = true;
        if (!detailDialog.open) detailDialog.showModal();
        try {
            const item = modules[key].readonly ? record : (await window.axios.get(endpoint(key, record.id))).data.data;
            if (token !== detailToken) return;
            const entries = [['id', 'ID'], ...modules[key].fields.filter((field) => field.key !== 'password').map((field) => [field.key, field.label])];
            if (key === 'usuarios') entries.push(['fecha_registro', 'Fecha de registro']);
            entries.forEach(([path, label]) => {
                const block = element('div', undefined, fields); element('dt', label, block);
                const related = { sede_id: 'sede', rol_id: 'rol', tipo_ambiente_id: 'tipo_ambiente', especialidad_id: 'especialidad' }[path];
                element('dd', related && item[related] ? `${display(item[related].nombre)} · ID ${item[path]}` : display(item[path]), block);
            });
            message.textContent = '';
        } catch (error) { if (token === detailToken) { message.textContent = errorText(error); find('[data-detail-retry]').hidden = [401, 403, 404].includes(error.response?.status); } }
    };
    detailDialog.addEventListener('close', () => { detailToken += 1; });
    find('[data-detail-retry]').addEventListener('click', () => openDetail(detailKey, detailRecord));

    const formDialog = find('[data-form-dialog]');
    const form = find('[data-form]');
    const save = find('[data-save]');
    let formToken = 0, editKey, editing = null, controls = new Map(), rolePicker = null;
    const editorState = () => {
        const waiting = formDialog.dataset.loading === '1' || formDialog.dataset.busy === '1';
        save.disabled = waiting || !formDialog.dataset.ready || Boolean(rolePicker && !rolePicker.ready);
        controls.forEach(({ input, locked }) => { input.disabled = waiting || locked; });
        if (rolePicker) rolePicker.updateButtons();
    };
    const buildField = (field, item, key) => {
        const label = element('label', undefined, find('[data-form-fields]'));
        element('span', `${field.label}${field.required || (field.key === 'password' && !item) ? ' *' : ''}`, label);
        const input = document.createElement(field.type === 'select' ? 'select' : 'input');
        input.name = field.key;
        if (field.type !== 'select') input.type = field.type;
        input.required = Boolean(field.required || (field.key === 'password' && !item));
        if (field.max) input.maxLength = field.max;
        if (field.min) input.minLength = field.min;
        if (field.type === 'number') { input.min = '0'; input.max = '99999999.99'; input.step = '0.01'; }
        if (field.type === 'checkbox') input.checked = item ? Boolean(item[field.key]) : true;
        else input.value = field.key === 'password' ? '' : item?.[field.key] ?? '';
        if (field.type === 'password') { input.autocomplete = 'new-password'; element('small', item ? 'Deja vacío para conservar la contraseña actual.' : 'Entre 8 y 72 caracteres.', label, 'config-field-note'); }
        const locked = Boolean((key === 'roles' && field.key === 'nombre' && reserved.includes(item?.nombre)) || (key === 'usuarios' && field.key === 'activo' && String(item?.id) === config.dataset.userId));
        if (locked) element('small', field.key === 'nombre' ? 'El nombre del rol reservado no puede modificarse.' : 'No puedes desactivar tu propia cuenta.', label, 'config-field-note');
        label.append(input); controls.set(field.key, { input, locked });
        return { input, label };
    };
    const fillSelect = (input, items, selected, required) => {
        input.replaceChildren(new Option(required ? 'Selecciona una opción' : 'Sin asignar', ''));
        if (selected && !items.some((item) => String(item.id) === String(selected.id))) input.add(new Option(`${selected.nombre} (selección actual)`, String(selected.id)));
        items.forEach((item) => input.add(new Option(item.nombre, String(item.id))));
        input.value = selected ? String(selected.id) : '';
    };
    const setupRolePicker = (input, label, initial, token) => {
        let selected = initial, rows = [], rolePage = 1, roleLast = 1, request = 0;
        const pager = element('div', undefined, label, 'config-picker-controls');
        const previous = button('Roles anteriores', pager, () => load(rolePage - 1)); previous.className = 'catalog-secondary-button';
        const next = button('Roles siguientes', pager, () => load(rolePage + 1)); next.className = 'catalog-secondary-button';
        const info = element('small', 'Cargando roles…', label, 'config-field-note');
        const retry = button('Reintentar roles', pager, () => load(rolePage)); retry.className = 'catalog-secondary-button'; retry.hidden = true;
        const state = { ready: false, updateButtons: () => {
            const blocked = !state.ready || formDialog.dataset.busy === '1' || formDialog.dataset.loading === '1';
            previous.disabled = blocked || rolePage <= 1; next.disabled = blocked || rolePage >= roleLast;
            input.disabled = blocked; retry.disabled = formDialog.dataset.busy === '1' || formDialog.dataset.loading === '1';
        } };
        const load = async (target) => {
            const localToken = ++request; state.ready = false; retry.hidden = true; info.textContent = 'Cargando roles…'; editorState(); state.updateButtons();
            try {
                const { data } = await window.axios.get(endpoint('roles'), { params: { page: target } });
                if (token !== formToken || localToken !== request) return;
                rows = data.data; rolePage = data.current_page; roleLast = data.last_page;
                fillSelect(input, rows, selected, true); state.ready = true; info.textContent = `Roles: página ${rolePage} de ${roleLast}. La selección se conserva al paginar.`;
            } catch (error) { if (token === formToken && localToken === request) { info.textContent = errorText(error); retry.hidden = false; } }
            finally { if (token === formToken && localToken === request) { editorState(); state.updateButtons(); } }
        };
        input.addEventListener('change', () => { selected = rows.find((item) => String(item.id) === input.value) || (String(selected?.id) === input.value ? selected : null); syncSpecialty(); });
        return { state, load };
    };
    const syncSpecialty = () => {
        if (editKey !== 'usuarios' || !controls.has('especialidad_id')) return;
        const role = controls.get('rol_id').input;
        // Reserved role names determine permissions in the existing backend.
        const isCoordinator = role.selectedOptions[0]?.textContent.replace(' (selección actual)', '') === 'coordinador';
        const specialty = controls.get('especialidad_id').input;
        specialty.required = isCoordinator;
        controls.get('especialidad_id').locked = !isCoordinator;
        if (!isCoordinator) specialty.value = '';
        editorState();
    };
    const openEditor = async (key, id = null) => {
        if (modules[key].readonly) return;
        editKey = key; editing = id; const token = ++formToken;
        controls = new Map(); rolePicker = null; form.reset(); find('[data-form-fields]').replaceChildren();
        formDialog.dataset.loading = '1'; delete formDialog.dataset.ready;
        find('#config-form-title').textContent = `${id === null ? 'Registrar' : 'Editar'} · ${modules[key].title}`;
        find('[data-form-message]').textContent = 'Cargando formulario…'; find('[data-form-retry]').hidden = true;
        if (!formDialog.open) formDialog.showModal(); editorState();
        try {
            const item = id === null ? null : (await window.axios.get(endpoint(key, id))).data.data;
            if (token !== formToken) return;
            const dependencies = [];
            modules[key].fields.forEach((field) => {
                const { input, label } = buildField(field, item, key);
                if (field.type !== 'select') return;
                const related = { rol_id: 'rol', sede_id: 'sede', especialidad_id: 'especialidad', tipo_ambiente_id: 'tipo_ambiente' }[field.key];
                const selected = item?.[related] ?? null;
                if (field.source === 'roles') {
                    const picker = setupRolePicker(input, label, selected, token); rolePicker = picker.state; dependencies.push(picker.load(1));
                } else dependencies.push(window.axios.get(endpoint(field.source)).then(({ data }) => {
                    if (token !== formToken) return;
                    const items = key === 'usuarios' && field.key === 'especialidad_id' ? data.data.filter((entry) => [1, 2, 3].includes(entry.id)) : data.data;
                    fillSelect(input, items, selected, field.required);
                }));
            });
            editorState(); await Promise.all(dependencies);
            if (token !== formToken) return;
            formDialog.dataset.ready = '1'; find('[data-form-message]').textContent = '';
        } catch (error) { if (token === formToken) { find('[data-form-message]').textContent = errorText(error); find('[data-form-retry]').hidden = [401, 403, 404].includes(error.response?.status); } }
        finally { if (token === formToken) { formDialog.dataset.loading = '0'; syncSpecialty(); editorState(); } }
    };
    formDialog.addEventListener('close', () => { formToken += 1; });
    find('[data-create]').addEventListener('click', () => openEditor(current));
    find('[data-form-retry]').addEventListener('click', () => openEditor(editKey, editing));
    const write = async (method, key, id, data) => {
        await window.axios.get(config.dataset.csrfUrl);
        return window.axios.request({ method, url: endpoint(key, id), data });
    };
    form.addEventListener('submit', async (event) => {
        event.preventDefault(); if (save.disabled || !form.reportValidity()) return;
        const data = {};
        modules[editKey].fields.forEach((field) => {
            const { input, locked } = controls.get(field.key);
            if (locked && field.key !== 'especialidad_id') return;
            if (field.key === 'password' && editing !== null && !input.value) return;
            data[field.key] = field.type === 'checkbox' ? input.checked : field.type === 'select' || field.type === 'number' ? (input.value === '' ? null : Number(input.value)) : (input.value.trim() || (field.required || field.key === 'password' ? '' : null));
            if (field.key === 'password') data[field.key] = input.value;
        });
        dialogBusy(formDialog, true); editorState(); find('[data-form-message]').textContent = 'Guardando…';
        try {
            const response = await write(editing === null ? 'post' : 'patch', editKey, editing === null ? undefined : editing, data);
            formDialog.close(); await loadList(page, response.data.message);
        } catch (error) { find('[data-form-message]').textContent = errorText(error); }
        finally { dialogBusy(formDialog, false); editorState(); }
    });
    const confirmDialog = find('[data-confirm-dialog]'); let pending;
    const confirmAction = (key, item, action) => {
        if (modules[key].readonly || (key === 'roles' && reserved.includes(item.nombre)) || (key === 'usuarios' && item.activo && String(item.id) === config.dataset.userId)) return;
        pending = { key, item, action };
        const label = action === 'delete' ? 'Eliminar definitivamente' : item.activo ? 'Desactivar usuario' : 'Activar usuario';
        find('#config-confirm-title').textContent = label; find('[data-confirm-submit]').textContent = label;
        find('[data-confirm-identity]').textContent = `${modules[key].title} · ID ${item.id} · ${item.nombre ?? `${item.nombres} ${item.apellidos}`}`;
        find('[data-confirm-note]').textContent = action === 'delete' ? 'La eliminación es definitiva y no se puede deshacer. Si el registro está en uso, el backend impedirá eliminarlo.' : item.activo ? 'El usuario dejará de poder acceder al sistema. No se permite desactivar la propia cuenta ni al último superadmin activo.' : 'El usuario podrá acceder nuevamente al sistema con sus permisos actuales.';
        find('[data-confirm-message]').textContent = ''; confirmDialog.showModal();
    };
    find('[data-confirm-form]').addEventListener('submit', async (event) => {
        event.preventDefault(); if (confirmDialog.dataset.busy === '1') return;
        dialogBusy(confirmDialog, true); find('[data-confirm-message]').textContent = 'Procesando…';
        try {
            const { key, item, action } = pending;
            const response = await write(action === 'delete' ? 'delete' : 'patch', key, item.id, action === 'delete' ? undefined : { activo: !item.activo });
            confirmDialog.close(); await loadList(page, response.data.message);
        } catch (error) { find('[data-confirm-message]').textContent = errorText(error); }
        finally { dialogBusy(confirmDialog, false); }
    });
}
