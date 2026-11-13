const reports = document.querySelector('[data-reports-root]');

if (reports) {
    const errorText = async (error) => {
        const code = error.response?.status;
        let data = error.response?.data;
        if (data instanceof Blob) { try { data = JSON.parse(await data.text()); } catch { data = null; } }
        if (code === 401) window.location.assign(reports.dataset.loginUrl);
        const errors = data?.errors && Object.values(data.errors).flat();
        return ({
            401: 'Tu sesión ha expirado. Inicia sesión nuevamente.',
            403: 'No puedes consultar este reporte. Comprueba tus permisos y que tu usuario esté activo.',
            404: 'El reporte solicitado no está disponible.',
            419: 'La sesión ha expirado. Recarga la página e inicia sesión nuevamente.',
        }[code]) || (code < 500 && (errors?.length ? errors.join(' ') : data?.message)) || 'No se pudo preparar el reporte. Inténtalo de nuevo.';
    };
    reports.querySelectorAll('[data-report-card]').forEach((card) => {
        const find = (selector) => card.querySelector(selector);
        const panel = find('[data-report-panel]'), form = find('[data-report-form]'), fields = find('[data-report-filters]');
        const complete = find('[data-report-complete]'), countButton = find('[data-report-count]'), clear = find('[data-report-clear]');
        const total = find('[data-report-total]'), status = find('[data-report-status]'), retry = find('[data-report-options-retry]');
        const downloads = Array.from(card.querySelectorAll('[data-report-url]'));
        const inputs = Array.from(fields.querySelectorAll('input, select'));
        let options = null, optionsLoading = false, countLoading = false, downloading = false, request = 0;
        let confirmed = null, matches = null;
        const params = () => complete.checked ? { completo: 1 } : Object.fromEntries(inputs.filter((field) => field.value.trim() !== '').map((field) => [field.name, field.value.trim()]));
        const controls = () => {
            fields.disabled = !options || optionsLoading || downloading || complete.checked;
            complete.disabled = optionsLoading || downloading;
            countButton.disabled = !options || optionsLoading || countLoading || downloading;
            clear.disabled = optionsLoading || downloading;
            retry.disabled = optionsLoading || downloading;
            downloads.forEach((button) => { button.disabled = downloading || countLoading || matches === null || matches === 0 || !confirmed; });
            card.setAttribute('aria-busy', String(optionsLoading || countLoading || downloading));
        };
        const invalidate = () => {
            request += 1; countLoading = false; confirmed = null; matches = null;
            total.textContent = 'Consulta las coincidencias de los criterios actuales antes de descargar.';
            status.textContent = ''; controls();
        };
        const fill = (name, items, placeholder) => {
            const select = form.elements[name]; if (!select) return;
            const current = select.value;
            select.replaceChildren(new Option(placeholder, ''));
            items.forEach((item) => select.add(new Option(item.nombre ?? String(item), String(item.id ?? item))));
            select.value = Array.from(select.options).some((option) => option.value === current) ? current : '';
        };
        const environments = () => {
            fill('ambiente_id', options?.ambientes.filter((item) => !form.elements.sede_id.value || String(item.sede_id) === form.elements.sede_id.value) ?? [], 'Todos los ambientes');
        };
        const loadOptions = async () => {
            if (optionsLoading || downloading) return;
            optionsLoading = true; retry.hidden = true; status.textContent = 'Cargando filtros disponibles…'; controls();
            try {
                const { data } = await window.axios.get(card.dataset.optionsUrl);
                options = data.data;
                [['sede_id', 'sedes', 'Todas las sedes'], ['especialidad_id', 'especialidades', 'Todas las especialidades'],
                    ['estado_id', 'estados', 'Todos los estados'], ['condicion_id', 'condiciones', 'Todas las condiciones'],
                    ['tipo_equipo_id', 'tipos_equipo', 'Todos los tipos'], ['anio', 'anios', 'Todos los años'],
                    ['tipo_mantenimiento_id', 'tipos_mantenimiento', 'Todos los tipos']].forEach(([name, key, placeholder]) => fill(name, options[key] ?? [], placeholder));
                environments(); status.textContent = '';
            } catch (error) { status.textContent = await errorText(error); retry.hidden = false; }
            finally { optionsLoading = false; controls(); }
        };
        panel.addEventListener('toggle', () => { if (panel.open && !options) loadOptions(); });
        retry.addEventListener('click', loadOptions);
        inputs.forEach((field) => {
            field.addEventListener('input', invalidate);
            field.addEventListener('change', () => { if (field.name === 'sede_id') environments(); invalidate(); });
        });
        complete.addEventListener('change', invalidate);
        clear.addEventListener('click', () => { form.reset(); environments(); invalidate(); });
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (countButton.disabled || !form.reportValidity()) return;
            const filters = params();
            invalidate();
            if (!Object.keys(filters).length) { status.textContent = 'Selecciona al menos un filtro o marca Reporte completo.'; return; }
            const token = ++request; countLoading = true; status.textContent = 'Consultando coincidencias…'; controls();
            try {
                const { data } = await window.axios.get(card.dataset.summaryUrl, { params: filters });
                if (token !== request) return;
                matches = data.data.total; confirmed = filters;
                total.textContent = matches ? `${matches} registros coinciden. PDF y Excel incluirán todos estos resultados.` : 'No hay registros que coincidan dentro de tu alcance. No se generará un archivo vacío.';
                status.textContent = '';
            } catch (error) { const text = await errorText(error); if (token === request) status.textContent = text; }
            finally { if (token === request) { countLoading = false; controls(); } }
        });
        downloads.forEach((button) => button.addEventListener('click', async () => {
            if (button.disabled || downloading || !confirmed || !matches) return;
            // Immutable checked criteria; changing a field always invalidates this confirmation.
            const filters = { ...confirmed };
            downloading = true; status.textContent = 'Preparando descarga de los resultados consultados…'; controls();
            try {
                const response = await window.axios.get(button.dataset.reportUrl, { params: filters, responseType: 'blob' });
                const url = URL.createObjectURL(response.data), link = document.createElement('a');
                link.href = url; link.download = button.dataset.reportFilename;
                document.body.append(link); link.click(); link.remove();
                window.setTimeout(() => URL.revokeObjectURL(url), 1000);
                status.textContent = 'Descarga preparada con los filtros consultados.';
            } catch (error) {
                status.textContent = await errorText(error);
                if (error.response?.status === 422) { confirmed = null; matches = null; total.textContent = 'Vuelve a consultar las coincidencias antes de descargar.'; }
            } finally { downloading = false; controls(); }
        }));
        controls();
    });
}
