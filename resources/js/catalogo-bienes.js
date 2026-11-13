import { renderBienDetail } from './bien-detail';

const catalog = document.querySelector('[data-catalog-root]');

if (catalog) {
    const rows = catalog.querySelector('[data-catalog-rows]');
    const message = catalog.querySelector('[data-catalog-message]');
    const retryButton = catalog.querySelector('[data-catalog-retry]');
    const count = catalog.querySelector('[data-catalog-count]');
    const pageSearch = catalog.querySelector('[data-catalog-search]');
    const searchSummary = catalog.querySelector('[data-catalog-search-summary]');
    const pageLabel = catalog.querySelector('[data-catalog-page-label]');
    const previousButton = catalog.querySelector('[data-catalog-previous]');
    const nextButton = catalog.querySelector('[data-catalog-next]');
    const dialog = catalog.querySelector('[data-bien-dialog]');
    const form = catalog.querySelector('[data-bien-form]');
    const formMessage = catalog.querySelector('[data-bien-form-message]');
    const saveButton = catalog.querySelector('[data-save-bien]');
    const detailDialog = catalog.querySelector('[data-bien-detail-dialog]');
    const detailMessage = catalog.querySelector('[data-bien-detail-message]');
    const detailFields = catalog.querySelector('[data-bien-detail-fields]');

    let currentPage = 1;
    let lastPage = 1;
    let total = 0;
    let loading = false;
    let sedes = new Map();
    let sedesRequest;
    let optionsLoaded = false;
    let currentBienes = [];
    let pageLoaded = false;
    let detailRequestId = 0;
    let pageRequestId = 0;
    let category = 'todos';
    let searchTimer;
    let filterOptionsReady = false;
    let filterOptionsRequest;
    let environments = [];
    const filters = Object.fromEntries(['sede_id', 'ambiente_id', 'estado_id', 'condicion_id'].map((key) => [key, catalog.querySelector('[data-catalog-' + key.replaceAll('_', '-') + ']')]));
    const requestParams = (page) => {
        const params = { page, categoria: category };
        const q = pageSearch.value.trim();
        if (q) params.q = q;
        Object.entries(filters).forEach(([key, field]) => { if (field.value) params[key] = field.value; });
        return params;
    };
    let technicalOptions = null;
    const categoryButtons = catalog.querySelectorAll('[data-catalog-category]');
    const setCategory = (value) => {
        category = value;
        categoryButtons.forEach((button) => {
            const active = button.dataset.catalogCategory === value;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', String(active));
        });
    };

    const handleAuthError = (error) => {
        if (error.response?.status === 401) {
            window.location.assign(catalog.dataset.loginUrl);
            return true;
        }

        return false;
    };

    const loadSedes = () => {
        if (!sedesRequest) {
            sedesRequest = window.axios.get(catalog.dataset.sedesUrl)
                .then((response) => {
                    sedes = new Map(response.data.data.map((sede) => [sede.id, sede.nombre]));
                })
                .catch((error) => {
                    sedesRequest = undefined;
                    throw error;
                });
        }

        return sedesRequest;
    };

    const addCell = (row, value, className = '') => {
        const cell = document.createElement('td');
        cell.textContent = value ?? '—';
        if (className) cell.className = className;
        row.append(cell);
        return cell;
    };

    const renderRows = (bienes) => {
        rows.replaceChildren();

        if (bienes.length === 0) {
            const row = document.createElement('tr');
            const cell = addCell(row, 'No hay bienes que coincidan con la búsqueda, categoría y filtros.');
            cell.colSpan = 8;
            cell.className = 'catalog-empty-cell';
            rows.append(row);
            return;
        }

        for (const bien of bienes) {
            const row = document.createElement('tr');
            const sedeId = bien.ambiente?.sede_id;

            addCell(row, bien.cbi, 'catalog-code');
            addCell(row, bien.descripcion, 'catalog-description');
            addCell(row, sedes.get(sedeId));
            addCell(row, bien.ambiente?.nombre);
            addCell(row, bien.estado?.nombre);
            addCell(row, bien.condicion?.nombre);

            const activeCell = addCell(row, '');
            const badge = document.createElement('span');
            badge.className = bien.activo ? 'catalog-badge catalog-badge-active' : 'catalog-badge catalog-badge-inactive';
            badge.textContent = bien.activo ? 'Sí' : 'No';
            activeCell.append(badge);

            const actions = addCell(row, '', 'catalog-row-actions');
            const viewButton = document.createElement('button');
            viewButton.type = 'button';
            viewButton.dataset.viewBien = String(bien.id);
            viewButton.textContent = 'Ver detalle';

            const editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'catalog-action-edit';
            editButton.dataset.editBien = String(bien.id);
            editButton.textContent = 'Editar';
            editButton.disabled = true;
            editButton.title = 'Edición disponible próximamente';

            actions.append(viewButton, editButton);

            rows.append(row);
        }
    };

    const updatePagination = () => {
        pageLabel.textContent = pageLoaded ? `Página ${currentPage} de ${lastPage}` : 'Página —';
        count.textContent = pageLoaded ? `${total} ${total === 1 ? 'bien encontrado' : 'bienes encontrados'}` : '';
        previousButton.disabled = loading || !pageLoaded || currentPage <= 1;
        nextButton.disabled = loading || !pageLoaded || currentPage >= lastPage;
        categoryButtons.forEach((button) => { button.disabled = loading; });
    };

    const loadPage = async (page, notice = '') => {
        clearTimeout(searchTimer);
        const requestId = ++pageRequestId;
        loading = true;
        previousButton.disabled = true;
        nextButton.disabled = true;

        retryButton.hidden = true;
        message.textContent = 'Cargando bienes…';
        pageLoaded = false;
        currentBienes = [];
        rows.replaceChildren();
        updatePagination();

        const [bienesResult, sedesResult, filtersResult] = await Promise.allSettled([
            window.axios.get(catalog.dataset.bienesUrl, { params: requestParams(page) }),
            loadSedes(),
            loadFilterOptions(),
        ]);

        if (requestId !== pageRequestId) return;

        if (bienesResult.status === 'fulfilled') {
            const pagination = bienesResult.value.data;
            if (page > Math.max(1, pagination.last_page)) return loadPage(Math.max(1, pagination.last_page), notice);
            currentPage = pagination.current_page;
            lastPage = Math.max(1, pagination.last_page);
            total = pagination.total;
            currentBienes = pagination.data;
            pageLoaded = true;
            renderRows(currentBienes);
            searchSummary.textContent = total + ' coincidencias en todos los registros autorizados.';
            message.textContent = sedesResult.status === 'rejected'
                ? 'No se pudieron cargar los nombres de sede. Reintenta la carga.'
                : notice;
            if (filtersResult.status === 'rejected') message.textContent = 'No se pudieron cargar los filtros. Reintenta la carga.';
            retryButton.hidden = sedesResult.status !== 'rejected' && filtersResult.status !== 'rejected';
        } else if (!handleAuthError(bienesResult.reason)) {
            message.textContent = bienesResult.reason.response?.status === 422
                ? 'Revisa la búsqueda y los filtros seleccionados.'
                : bienesResult.reason.response?.status === 403
                ? 'No tienes permiso para consultar este catálogo.'
                : 'No se pudieron cargar los bienes. Inténtalo de nuevo.';
            retryButton.hidden = bienesResult.reason.response?.status === 403;
        }

        if (sedesResult.status === 'rejected') handleAuthError(sedesResult.reason);
        loading = false;

        updatePagination();
    };

    const fillOptions = (select, items, labelFor, placeholder = 'Sin asignar') => {
        select.replaceChildren(new Option(placeholder, ''));
        for (const item of items) {
            select.add(new Option(labelFor(item), String(item.id)));
        }
    };

    const syncTechnicalForm = () => {
        const selected = form.elements.categoria.value;
        catalog.querySelectorAll('[data-category-fields]').forEach((section) => {
            const visible = section.dataset.categoryFields === selected || (section.dataset.categoryFields === 'equipo' && ['computadoras', 'monitores', 'otros-equipos'].includes(selected));
            section.hidden = !visible;
            section.disabled = !visible;
        });
        if (technicalOptions) {
            const previous = form.elements.tipo_equipo_id.value;
            fillOptions(form.elements.tipo_equipo_id, technicalOptions.tipos_equipo.filter((type) => type.categoria === selected), (type) => type.nombre, 'Selecciona un tipo');
            if (Array.from(form.elements.tipo_equipo_id.options).some((option) => option.value === previous)) form.elements.tipo_equipo_id.value = previous;
        }
    };
    form.elements.categoria.addEventListener('change', syncTechnicalForm);
    categoryButtons.forEach((button) => button.addEventListener('click', () => {
        if (loading) return;
        setCategory(button.dataset.catalogCategory);
        loadPage(1);
    }));

    const loadFormOptions = async () => {
        if (optionsLoaded) return true;

        saveButton.disabled = true;
        formMessage.textContent = 'Cargando opciones…';

        try {
            const [ambientes, estados, condiciones, technical] = await Promise.all([
                window.axios.get(catalog.dataset.ambientesUrl),
                window.axios.get(catalog.dataset.estadosUrl),
                window.axios.get(catalog.dataset.condicionesUrl),
                window.axios.get(catalog.dataset.optionsUrl),
            ]);

            fillOptions(form.elements.ambiente_id, ambientes.data.data, (ambiente) =>
                ambiente.sede?.nombre ? `${ambiente.nombre} · ${ambiente.sede.nombre}` : ambiente.nombre);
            fillOptions(form.elements.estado_id, estados.data.data, (estado) => estado.nombre);
            fillOptions(form.elements.condicion_id, condiciones.data.data, (condicion) => condicion.nombre);
            technicalOptions = technical.data.data;
            fillOptions(form.elements.marca_id, technicalOptions.marcas, (item) => item.nombre);
            fillOptions(form.elements.tipo_mueble_id, technicalOptions.tipos_mueble, (item) => item.nombre, 'Selecciona un tipo');
            syncTechnicalForm();

            optionsLoaded = true;
            formMessage.textContent = '';
            saveButton.disabled = false;
            return true;
        } catch (error) {
            if (!handleAuthError(error)) {
                formMessage.textContent = 'No se pudieron cargar las opciones. Cierra y vuelve a abrir el formulario.';
            }
            return false;
        }
    };

    const closeDialog = () => {
        dialog.close();
        form.reset();
        formMessage.textContent = '';
    };

    catalog.querySelector('[data-open-bien-dialog]').addEventListener('click', () => {
        form.elements.categoria.value = category === 'todos' ? '' : category;
        syncTechnicalForm();
        const today = new Date();
        form.elements.fecha_registro.value = [
            today.getFullYear(),
            String(today.getMonth() + 1).padStart(2, '0'),
            String(today.getDate()).padStart(2, '0'),
        ].join('-');
        dialog.showModal();
        loadFormOptions();
    });
    catalog.querySelector('[data-close-bien-dialog]').addEventListener('click', closeDialog);
    catalog.querySelector('[data-cancel-bien-dialog]').addEventListener('click', closeDialog);
    dialog.addEventListener('close', () => {
        form.reset();
        formMessage.textContent = '';
    });

    const updateEnvironments = () => {
        const current = filters.ambiente_id.value;
        const visible = environments.filter((item) => !filters.sede_id.value || String(item.sede_id) === filters.sede_id.value);
        fillOptions(filters.ambiente_id, visible, (item) => item.sede?.nombre ? item.nombre + ' (' + item.sede.nombre + ')' : item.nombre, 'Todos los ambientes');
        filters.ambiente_id.value = visible.some((item) => String(item.id) === current) ? current : '';
    };
    const loadFilterOptions = async () => {
        if (filterOptionsReady) return;
        if (filterOptionsRequest) return filterOptionsRequest;
        filterOptionsRequest = (async () => {
            const [, ambientes, estados, condiciones] = await Promise.all([
                loadSedes(), window.axios.get(catalog.dataset.ambientesUrl),
                window.axios.get(catalog.dataset.estadosUrl), window.axios.get(catalog.dataset.condicionesUrl),
            ]);
            fillOptions(filters.sede_id, Array.from(sedes, ([id, nombre]) => ({ id, nombre })), (item) => item.nombre, 'Todas las sedes');
            environments = ambientes.data.data; updateEnvironments();
            fillOptions(filters.estado_id, estados.data.data, (item) => item.nombre, 'Todos los estados');
            fillOptions(filters.condicion_id, condiciones.data.data, (item) => item.nombre, 'Todas las condiciones');
            Object.values(filters).forEach((field) => { field.disabled = false; });
            filterOptionsReady = true;
        })().finally(() => { filterOptionsRequest = null; });
        return filterOptionsRequest;
    };
    const refreshSearch = (delay = 0) => {
        clearTimeout(searchTimer); pageRequestId += 1;
        rows.replaceChildren(); currentBienes = []; pageLoaded = false; loading = true; updatePagination();
        count.textContent = ''; searchSummary.textContent = '';
        message.textContent = 'Buscando bienes...';
        if (delay) searchTimer = setTimeout(() => loadPage(1), delay);
        else loadPage(1);
    };
    pageSearch.addEventListener('input', () => refreshSearch(300));
    pageSearch.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') { event.preventDefault(); refreshSearch(); }
    });
    Object.entries(filters).forEach(([key, field]) => field.addEventListener('change', () => {
        if (key === 'sede_id') updateEnvironments();
        refreshSearch();
    }));
    catalog.querySelector('[data-catalog-clear]').addEventListener('click', () => {
        pageSearch.value = ''; Object.values(filters).forEach((field) => { field.value = ''; });
        updateEnvironments(); refreshSearch();
    });
    previousButton.addEventListener('click', () => loadPage(currentPage - 1));
    nextButton.addEventListener('click', () => loadPage(currentPage + 1));
    retryButton.addEventListener('click', () => loadPage(currentPage));

    const showDetail = async (id) => {
        const requestId = ++detailRequestId;
        detailFields.hidden = true;
        detailMessage.textContent = 'Cargando detalle…';
        detailDialog.showModal();

        const [bienResult, sedesResult] = await Promise.allSettled([
            window.axios.get(`${catalog.dataset.bienesUrl}/${encodeURIComponent(id)}`),
            loadSedes(),
        ]);

        if (requestId !== detailRequestId) return;

        if (bienResult.status === 'rejected') {
            if (!handleAuthError(bienResult.reason)) {
                detailMessage.textContent = bienResult.reason.response?.status === 404
                    ? 'Este bien ya no está disponible.'
                    : 'No se pudo cargar el detalle del bien.';
            }
            return;
        }

        if (sedesResult.status === 'rejected') handleAuthError(sedesResult.reason);

        const bien = bienResult.value.data.data;
        renderBienDetail(catalog, bien, sedes.get(bien.ambiente?.sede_id));
        detailMessage.textContent = sedesResult.status === 'rejected' ? 'No se pudo cargar el nombre de sede.' : '';
        detailFields.hidden = false;
    };

    rows.addEventListener('click', (event) => {
        const button = event.target.closest('[data-view-bien]');
        if (button) showDetail(button.dataset.viewBien);
    });
    catalog.querySelector('[data-close-bien-detail]').addEventListener('click', () => detailDialog.close());
    detailDialog.addEventListener('close', () => { detailRequestId += 1; });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (saveButton.disabled || !form.reportValidity() || !optionsLoaded) return;
        if (!form.elements.categoria.value) { formMessage.textContent = 'Selecciona una categoría.'; return; }

        const descripcion = form.elements.descripcion.value.trim();
        if (!descripcion) {
            formMessage.textContent = 'Ingresa la descripción del bien.';
            form.elements.descripcion.focus();
            return;
        }

        const payload = {
            descripcion,
            activo: form.elements.activo.value === '1',
            categoria: form.elements.categoria.value,
        };

        for (const field of ['cbi', 'observaciones', 'fecha_registro']) {
            const value = form.elements[field].value.trim();
            if (value) payload[field] = value;
        }

        for (const field of ['estado_id', 'condicion_id', 'ambiente_id']) {
            const value = form.elements[field].value;
            if (value) payload[field] = Number(value);
        }

        catalog.querySelectorAll('[data-category-fields]').forEach((section) => {
            if (section.disabled) return;
            section.querySelectorAll('[name]').forEach((input) => {
                payload[input.name] = input.name.endsWith('_id') ? (input.value ? Number(input.value) : null) : (input.value.trim() || null);
            });
        });

        saveButton.disabled = true;
        formMessage.textContent = '';

        try {
            await window.axios.get(catalog.dataset.csrfUrl);
            await window.axios.post(catalog.dataset.bienesUrl, payload);
            closeDialog();
            pageSearch.value = '';
            setCategory(payload.categoria);
            await loadPage(1, 'Bien registrado correctamente.');
            if (lastPage > 1) await loadPage(lastPage, 'Bien registrado correctamente.');
        } catch (error) {
            if (!handleAuthError(error)) {
                const errors = error.response?.data?.errors;
                const firstError = errors && Object.values(errors).flat()[0];
                formMessage.textContent = firstError || error.response?.data?.message || 'No se pudo registrar el bien.';
            }
        } finally {
            saveButton.disabled = false;
        }
    });

    loadPage(1);
}
