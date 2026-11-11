const maintenance = document.querySelector('[data-maintenance-root]');

if (maintenance) {
    const find = (selector, parent = maintenance) => parent.querySelector(selector);
    const normalize = (value) => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
    const date = (value) => value ? String(value).slice(0, 10) : null;
    const userName = (user) => user ? (`${user.nombres ?? ''} ${user.apellidos ?? ''}`.trim() || `Usuario ID ${user.id}`) : null;
    const recordUrl = (id) => `${maintenance.dataset.maintenanceUrl}/${encodeURIComponent(id)}`;
    const errorText = (error) => {
        const code = error.response?.status;
        if (code === 401) window.location.assign(maintenance.dataset.loginUrl);
        const data = error.response?.data;
        const errors = data?.errors && Object.values(data.errors).flat();
        return errors?.length ? errors.join(' ') : data?.message || ({
            401: 'La sesión ha expirado.', 403: 'No tienes permiso para esta operación.',
            404: 'El mantenimiento no existe o no está disponible para tu usuario.',
            409: 'No se pudo completar la operación por un conflicto.',
            419: 'El token de seguridad ha expirado. Reintenta la operación.',
        }[code]) || 'No se pudo completar la operación. Inténtalo de nuevo.';
    };
    const cell = (row, value, className = '') => {
        const td = document.createElement('td');
        td.textContent = value ?? '—';
        td.className = className;
        row.append(td);
        return td;
    };
    const action = (parent, label, handler) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.addEventListener('click', handler);
        parent.append(button);
    };
    maintenance.querySelectorAll('dialog').forEach((dialog) => {
        dialog.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => {
            if (dialog.dataset.busy !== '1') dialog.close();
        }));
        dialog.addEventListener('cancel', (event) => { if (dialog.dataset.busy === '1') event.preventDefault(); });
    });

    const pagination = (prefix, url, values, render, onChange = () => {}) => {
        const search = find(`[data-${prefix}-search]`);
        const previous = find(`[data-${prefix}-previous]`);
        const next = find(`[data-${prefix}-next]`);
        const retry = find(`[data-${prefix}-retry]`);
        const message = find(`[data-${prefix}-message]`);
        let page = 1, last = 1, items = [], request = 0, loading = false, loaded = false;
        const blocked = () => {
            const dialog = search.closest('[data-maintenance-form-dialog], [data-maintenance-form-page]');
            return dialog?.dataset.busy === '1' || dialog?.dataset.loading === '1';
        };
        const controls = () => {
            search.disabled = blocked() || loading || !loaded;
            previous.disabled = blocked() || loading || !loaded || page <= 1;
            next.disabled = blocked() || loading || !loaded || page >= last;
            retry.disabled = blocked() || loading;
            const select = find(`[data-${prefix}-options]`);
            if (select) select.disabled = blocked() || loading || !loaded;
        };
        const filter = () => {
            const query = normalize(search.value.trim());
            const matches = items.filter((item) => !query || values(item).some((value) => normalize(value).includes(query)));
            render(matches, Boolean(query));
            find(`[data-${prefix}-summary]`).textContent = query ? `${matches.length} coincidencias en esta página.` : '';
        };
        const load = async (target = page, notice = '') => {
            const token = ++request;
            loading = true;
            loaded = false;
            items = [];
            search.value = '';
            filter();
            controls();
            onChange();
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
                message.textContent = notice;
                filter();
            } catch (error) {
                if (token !== request) return;
                message.textContent = errorText(error);
                find(`[data-${prefix}-page]`).textContent = 'Página no disponible';
                retry.hidden = [401, 403].includes(error.response?.status);
            } finally {
                if (token === request) { loading = false; controls(); onChange(); }
            }
        };
        search.addEventListener('input', filter);
        previous.addEventListener('click', () => load(page - 1));
        next.addEventListener('click', () => load(page + 1));
        retry.addEventListener('click', () => load(page));
        return { load, controls, filter, items: () => items, ready: () => loaded && !loading, invalidate: () => { request += 1; loaded = false; } };
    };

    const detailDialog = find('[data-maintenance-detail-dialog]');
    const detailFields = find('[data-maintenance-detail-fields]');
    const detailMessage = find('[data-detail-message]');
    let detailId, detailRequest = 0;
    const showDetail = async (id) => {
        detailId = id;
        const token = ++detailRequest;
        detailFields.hidden = true;
        detailMessage.textContent = 'Cargando detalle…';
        find('[data-detail-retry]').hidden = true;
        if (!detailDialog.open) detailDialog.showModal();
        try {
            const { data } = await window.axios.get(recordUrl(id));
            if (token !== detailRequest) return;
            const item = data.data;
            const fields = {
                id: item.id, fecha: date(item.fecha_mantenimiento), bien: item.bien_id,
                cbi: item.bien?.cbi, 'bien-descripcion': item.bien?.descripcion,
                tipo: item.tipo_mantenimiento?.nombre, tecnico: userName(item.tecnico),
                descripcion: item.descripcion, diagnostico: item.diagnostico,
                trabajo: item.trabajo_realizado, observaciones: item.observaciones,
            };
            Object.entries(fields).forEach(([key, value]) => { find(`[data-detail-${key}]`).textContent = value ?? '—'; });
            detailFields.hidden = false;
            detailMessage.textContent = '';
        } catch (error) {
            if (token !== detailRequest) return;
            detailMessage.textContent = errorText(error);
            find('[data-detail-retry]').hidden = [401, 403, 404].includes(error.response?.status);
        }
    };
    find('[data-detail-retry]')?.addEventListener('click', () => showDetail(detailId));
    detailDialog?.addEventListener('close', () => { detailRequest += 1; });
    const isCreatePage = Boolean(find('[data-maintenance-form-page]'));
    const formDialog = find('[data-maintenance-form-dialog]') || find('[data-maintenance-form-page]');
    let openForm, openDelete;
    const list = isCreatePage ? null : pagination('maintenance', maintenance.dataset.maintenanceUrl,
        (item) => [item.id, date(item.fecha_mantenimiento), item.bien?.cbi, item.bien?.descripcion, item.tipo_mantenimiento?.nombre, userName(item.tecnico)],
        (items, searched) => {
            const body = find('[data-maintenance-rows]');
            body.replaceChildren();
            if (!items.length) {
                const row = document.createElement('tr');
                cell(row, searched ? 'No hay coincidencias en esta página.' : 'No hay mantenimientos para mostrar.', 'catalog-empty-cell').colSpan = 7;
                body.append(row);
            }
            items.forEach((item) => {
                const row = document.createElement('tr');
                [item.id, date(item.fecha_mantenimiento), item.bien?.cbi, item.bien?.descripcion, item.tipo_mantenimiento?.nombre, userName(item.tecnico)].forEach((value) => cell(row, value));
                const actions = cell(row, '', 'catalog-row-actions inventory-row-actions');
                action(actions, 'Ver detalle', () => showDetail(item.id));
                if (formDialog) {
                    action(actions, 'Editar', () => openForm(item.id));
                    action(actions, 'Eliminar', () => openDelete(item));
                }
                body.append(row);
            });
        });

    maintenance.querySelectorAll('[data-export-url]').forEach((button) => button.addEventListener('click', async () => {
        const buttons = maintenance.querySelectorAll('[data-export-url]');
        const message = find('[data-export-message]');
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
                try { error.response.data = JSON.parse(await error.response.data.text()); } catch { /* The server may return a non-JSON error. */ }
            }
            message.textContent = errorText(error);
        } finally { buttons.forEach((item) => { item.disabled = false; }); }
    }));

    if (formDialog) {
        const form = find('[data-maintenance-form]');
        const formMessage = find('[data-form-message]', form);
        const save = find('[data-save-maintenance]');
        const typeSelect = form.elements.tipo_mantenimiento_id;
        const conditionSelect = form.elements.condicion_id;
        const conditionSection = find('[data-condition-section]');
        let conditionsReady = false, conditionRequest = 0;
        let editingId = null, formRequest = 0, recordReady = false, typesReady = false;
        let selectedBien = null, selectedUser = null;
        const labelBien = (bien) => `ID ${bien.id} · CBI ${bien.cbi ?? '—'} · ${bien.descripcion ?? '—'}`;
        let userPicker;
        let bienLoading = false, bienRequest = 0, matches = [];
        const bienSearch = find('[data-bien-search]');
        const bienOptions = find('[data-bien-options]');
        const searchButton = find('[data-bien-search-button]');
        const refresh = () => {
            const writing = formDialog.dataset.busy === '1';
            const opening = formDialog.dataset.loading === '1';
            form.querySelectorAll('textarea, input[type="date"]').forEach((field) => { field.disabled = writing || opening || !recordReady; });
            form.querySelectorAll('[data-close-dialog]').forEach((button) => { button.disabled = writing; });
            find('[data-form-retry]').disabled = writing || opening;
            typeSelect.disabled = writing || opening || !typesReady;
            conditionSection.hidden = editingId !== null;
            conditionSelect.disabled = editingId !== null || writing || opening || !conditionsReady || !selectedBien;
            find('[data-condition-retry]').disabled = writing || opening;
            find('[data-current-condition]').textContent = selectedBien ? `Condición actual: ${selectedBien.condicion?.nombre ?? '—'}` : 'Selecciona un bien para consultar su condición actual.';
            bienSearch.disabled = writing || opening;
            bienOptions.disabled = writing || opening || bienLoading;
            searchButton.disabled = writing || opening || bienLoading;
            userPicker?.controls();
            save.disabled = writing || opening || bienLoading || !recordReady || !typesReady || !selectedBien || !userPicker?.ready();
            find('[data-selected-bien]').textContent = selectedBien ? labelBien(selectedBien) : 'Ningún bien seleccionado.';
            find('[data-selected-user]').textContent = selectedUser ? `Técnico: ${userName(selectedUser)} · ID ${selectedUser.id}` : 'Técnico: Sin asignar';
        };
        const picker = (prefix, url, label, values, selected, setSelected) => {
            const select = find(`[data-${prefix}-options]`);
            const page = pagination(prefix, url, values, (items) => {
                const current = selected();
                select.replaceChildren(new Option(prefix === 'bien' ? 'Selecciona un bien' : 'Sin asignar', ''));
                // Keep the selection independently of the fetched page and its local search.
                if (current && !items.some((item) => item.id === current.id)) {
                    select.add(new Option(`${label(current)} (selección actual)`, String(current.id)));
                }
                items.forEach((item) => select.add(new Option(label(item), String(item.id))));
                select.value = current ? String(current.id) : '';
            }, refresh);
            select.addEventListener('change', () => {
                const id = select.value;
                const current = selected();
                setSelected(id ? (page.items().find((item) => String(item.id) === id) || current) : null);
                refresh();
            });
            return page;
        };
        const renderBienOptions = () => {
            bienOptions.replaceChildren(new Option('Selecciona un bien', ''));
            if (selectedBien && !matches.some((item) => item.id === selectedBien.id)) {
                bienOptions.add(new Option(`${labelBien(selectedBien)} (selección actual)`, String(selectedBien.id)));
            }
            matches.forEach((item) => bienOptions.add(new Option(labelBien(item), String(item.id))));
            bienOptions.value = selectedBien ? String(selectedBien.id) : '';
        };
        const renderBienCard = () => {
            const card = find('[data-bien-card]');
            const fields = find('[data-bien-technical-fields]'); fields.replaceChildren();
            card.hidden = !selectedBien;
            if (!selectedBien) return;
            const bien = selectedBien, equipment = bien.equipo, furniture = bien.mueble;
            const entries = [['Bien ID', bien.id], ['CBI', bien.cbi], ['Descripción', bien.descripcion],
                ['Estado', bien.estado?.nombre], ['Condición actual', bien.condicion?.nombre], ['Ambiente', bien.ambiente?.nombre]];
            if (equipment) entries.push(['Tipo de equipo', equipment.tipo_equipo?.nombre], ['Marca', equipment.marca?.nombre], ['Modelo', equipment.modelo], ['Número de serie', equipment.serie]);
            if (equipment?.equipo_computo) {
                const pc = equipment.equipo_computo;
                entries.push(['Procesador', pc.procesador], ['Memoria RAM', pc.ram], ['Almacenamiento', pc.almacenamiento], ['Detalle adicional', pc.detalle_adicional]);
            }
            if (equipment?.monitor) entries.push(['Tamaño de pantalla', equipment.monitor.tamano_pantalla ?? equipment.monitor.tamanio_pantalla], ['Detalle de pantalla', equipment.monitor.detalle_pantalla]);
            if (furniture) entries.push(['Tipo de mueble', furniture.tipo_mueble?.nombre], ['Material', furniture.material], ['Color', furniture.color]);
            entries.forEach(([label, value]) => {
                const group = document.createElement('div'), title = document.createElement('dt'), content = document.createElement('dd');
                title.textContent = label; content.textContent = value === null || value === undefined || String(value).trim() === '' ? '—' : value;
                group.append(title, content); fields.append(group);
            });
        };
        const searchBien = async () => {
            if (searchButton.disabled) return;
            const cbi = bienSearch.value.trim();
            const message = find('[data-bien-message]');
            const token = ++bienRequest, formToken = formRequest;
            matches = []; renderBienOptions();
            if (!cbi || cbi.length > 25) { message.textContent = 'Ingresa el CBI completo (máximo 25 caracteres).'; return; }
            bienLoading = true; message.textContent = 'Buscando CBI…'; refresh();
            try {
                const { data } = await window.axios.get(maintenance.dataset.bienesUrl, { params: { cbi } });
                if (token !== bienRequest || formToken !== formRequest) return;
                matches = data.data;
                renderBienOptions();
                message.textContent = matches.length ? 'Selecciona explícitamente el bien encontrado.' : 'No hay bienes con ese CBI.';
                if (selectedBien) message.textContent += ' Se conserva el bien seleccionado hasta elegir otro.';
            } catch (error) { if (token === bienRequest && formToken === formRequest) message.textContent = errorText(error); }
            finally { if (token === bienRequest && formToken === formRequest) { bienLoading = false; refresh(); } }
        };
        searchButton.addEventListener('click', searchBien);
        bienSearch.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); searchBien(); } });
        bienSearch.addEventListener('input', () => {
            bienRequest += 1; bienLoading = false; matches = []; renderBienOptions();
            find('[data-bien-message]').textContent = ''; refresh();
        });
        bienOptions.addEventListener('change', async () => {
            const id = bienOptions.value;
            const token = ++bienRequest, formToken = formRequest;
            if (!id) {
                selectedBien = null; conditionSelect.value = ''; renderBienCard(); renderBienOptions(); refresh(); return;
            }
            bienLoading = true; refresh();
            try {
                const { data } = await window.axios.get(`${maintenance.dataset.bienesUrl}/${encodeURIComponent(id)}`);
                if (token !== bienRequest || formToken !== formRequest) return;
                if (selectedBien?.id !== data.data.id) conditionSelect.value = '';
                selectedBien = data.data; renderBienCard(); renderBienOptions();
                find('[data-bien-message]').textContent = '';
            } catch (error) {
                if (token === bienRequest && formToken === formRequest) {
                    selectedBien = null; conditionSelect.value = ''; renderBienCard(); renderBienOptions();
                    find('[data-bien-message]').textContent = errorText(error);
                }
            } finally { if (token === bienRequest && formToken === formRequest) { bienLoading = false; refresh(); } }
        });
        userPicker = picker('user', maintenance.dataset.usersUrl, userName, (item) => [item.nombres, item.apellidos], () => selectedUser, (item) => { selectedUser = item; });
        const loadConditions = async () => {
            if (editingId !== null) return;
            const token = formRequest, request = ++conditionRequest;
            conditionsReady = false;
            find('[data-condition-message]').textContent = 'Cargando condiciones…';
            find('[data-condition-retry]').hidden = true;
            refresh();
            try {
                const { data } = await window.axios.get(maintenance.dataset.conditionsUrl);
                if (token !== formRequest || request !== conditionRequest || editingId !== null) return;
                const previous = conditionSelect.value;
                conditionSelect.replaceChildren(new Option('Mantener la condición actual', ''));
                data.data.forEach((condition) => conditionSelect.add(new Option(condition.nombre, String(condition.id))));
                conditionSelect.value = previous;
                conditionsReady = data.data.length > 0;
                find('[data-condition-message]').textContent = conditionsReady ? '' : 'No hay condiciones disponibles. Se mantendrá la condición actual.';
            } catch (error) {
                if (token !== formRequest || request !== conditionRequest) return;
                conditionSelect.value = '';
                find('[data-condition-message]').textContent = `${errorText(error)} Se mantendrá la condición actual.`;
                find('[data-condition-retry]').hidden = [401, 403].includes(error.response?.status);
            } finally { if (token === formRequest && request === conditionRequest) refresh(); }
        };
        find('[data-condition-retry]').addEventListener('click', loadConditions);
        const loadForm = async () => {
            const token = ++formRequest;
            bienRequest += 1; bienLoading = false;
            find('[data-bien-message]').textContent = '';
            formDialog.dataset.loading = '1';
            formMessage.textContent = 'Cargando datos del formulario…';
            find('[data-form-retry]').hidden = true;
            refresh();
            try {
                if (!recordReady && editingId !== null) {
                    const { data } = await window.axios.get(recordUrl(editingId));
                    if (token !== formRequest) return;
                    const item = data.data;
                    selectedBien = item.bien;
                    if (selectedBien) {
                        const response = await window.axios.get(`${maintenance.dataset.bienesUrl}/${encodeURIComponent(selectedBien.id)}`);
                        if (token !== formRequest) return;
                        selectedBien = response.data.data;
                    }
                    renderBienCard(); renderBienOptions();
                    selectedUser = item.tecnico;
                    ['descripcion', 'diagnostico', 'trabajo_realizado', 'observaciones'].forEach((key) => { form.elements[key].value = item[key] ?? ''; });
                    form.elements.fecha_mantenimiento.value = date(item.fecha_mantenimiento) ?? '';
                    typeSelect.dataset.selectedId = String(item.tipo_mantenimiento_id);
                    recordReady = true;
                }
                const [typesResult] = await Promise.allSettled([
                    window.axios.get(maintenance.dataset.typesUrl), userPicker.load(1),
                    ...(editingId === null ? [loadConditions()] : []),
                ]);
                if (token !== formRequest) return;
                if (typesResult.status === 'rejected') throw typesResult.reason;
                const types = typesResult.value.data.data;
                const currentType = typeSelect.value || typeSelect.dataset.selectedId || '';
                typeSelect.replaceChildren(new Option('Selecciona un tipo', ''));
                types.forEach((type) => typeSelect.add(new Option(type.nombre, String(type.id))));
                typeSelect.value = currentType;
                typesReady = types.length > 0;
                if (!typesReady) {
                    formMessage.textContent = 'No hay tipos de mantenimiento disponibles. No se puede guardar el registro.';
                    find('[data-form-retry]').hidden = false;
                } else {
                    formMessage.textContent = '';
                }
            } catch (error) {
                if (token !== formRequest) return;
                formMessage.textContent = errorText(error);
                find('[data-form-retry]').hidden = [401, 403, 404].includes(error.response?.status);
            } finally {
                if (token === formRequest) { formDialog.dataset.loading = '0'; refresh(); }
            }
        };
        openForm = (id = null) => {
            form.reset();
            editingId = id;
            selectedBien = null;
            bienRequest += 1; bienLoading = false; matches = []; bienSearch.value = '';
            renderBienCard(); renderBienOptions(); find('[data-bien-message]').textContent = '';
            selectedUser = null;
            recordReady = id === null;
            typesReady = false;
            conditionsReady = false;
            conditionRequest += 1;
            conditionSelect.replaceChildren(new Option('Mantener la condición actual', ''));
            conditionSelect.value = '';
            find('[data-condition-message]').textContent = '';
            find('[data-condition-retry]').hidden = true;
            typeSelect.replaceChildren(new Option('Selecciona un tipo', ''));
            delete typeSelect.dataset.selectedId;
            find('#maintenance-form-title').textContent = id === null ? 'Registrar mantenimiento' : `Editar mantenimiento #${id}`;
            save.textContent = id === null ? 'Guardar mantenimiento' : 'Guardar cambios';
            formDialog.dataset.busy = '0';
            if (!isCreatePage) formDialog.showModal();
            loadForm();
        };
        find('[data-form-retry]').addEventListener('click', loadForm);
        formDialog.addEventListener('close', () => { formRequest += 1; bienRequest += 1; userPicker.invalidate(); });
        form.querySelector('[data-cancel-registration]')?.addEventListener('click', (event) => { if (formDialog.dataset.busy === '1') event.preventDefault(); });
        const write = async (method, url, payload) => {
            await window.axios.get(maintenance.dataset.csrfUrl);
            return window.axios.request({ method, url, data: payload });
        };
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (save.disabled || !form.reportValidity()) return;
            if (!form.elements.descripcion.value.trim()) { formMessage.textContent = 'Ingresa la descripción del mantenimiento.'; return; }
            const payload = {
                bien_id: selectedBien.id,
                tecnico_id: selectedUser?.id ?? null,
                tipo_mantenimiento_id: Number(typeSelect.value),
                fecha_mantenimiento: form.elements.fecha_mantenimiento.value,
                descripcion: form.elements.descripcion.value.trim(),
            };
            ['diagnostico', 'trabajo_realizado', 'observaciones'].forEach((key) => { payload[key] = form.elements[key].value.trim() || null; });
            if (editingId === null && conditionsReady && conditionSelect.value !== '' && String(selectedBien.condicion_id ?? '') !== conditionSelect.value) {
                payload.condicion_id = Number(conditionSelect.value);
            }
            formDialog.dataset.busy = '1';
            formMessage.textContent = '';
            refresh();
            try {
                const response = await write(editingId === null ? 'post' : 'patch', editingId === null ? maintenance.dataset.maintenanceUrl : recordUrl(editingId), payload);
                if (isCreatePage) { window.location.assign(`${maintenance.dataset.listUrl}?registrado=1`); return; }
                formDialog.close();
                await list.load(undefined, response.data.message);
            } catch (error) { formMessage.textContent = errorText(error); }
            finally { formDialog.dataset.busy = '0'; refresh(); }
        });

        if (isCreatePage) openForm();
        const deleteDialog = find('[data-maintenance-delete-dialog]');
        if (deleteDialog) {
            const deleteForm = find('[data-maintenance-delete-form]');
            const deleteMessage = find('[data-form-message]', deleteForm);
            let deletingId;
            openDelete = (item) => {
                deletingId = item.id;
                deleteMessage.textContent = '';
                find('[data-delete-identity]').textContent = `¿Eliminar el mantenimiento #${item.id}, de fecha ${date(item.fecha_mantenimiento) ?? '—'}, del bien ${item.bien?.cbi ?? item.bien_id}?`;
                deleteDialog.showModal();
            };
            deleteForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (deleteDialog.dataset.busy === '1') return;
                deleteDialog.dataset.busy = '1';
                deleteForm.querySelectorAll('button').forEach((button) => { button.disabled = true; });
                deleteMessage.textContent = '';
                try {
                    const response = await write('delete', recordUrl(deletingId));
                    deleteDialog.close();
                    await list.load(undefined, response.data.message);
                } catch (error) { deleteMessage.textContent = errorText(error); }
                finally {
                    deleteDialog.dataset.busy = '0';
                    deleteForm.querySelectorAll('button').forEach((button) => { button.disabled = false; });
                }
            });
        }
    }
    list?.load(1, maintenance.dataset.createdNotice || '');
}
