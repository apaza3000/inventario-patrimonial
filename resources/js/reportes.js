const reports = document.querySelector('[data-reports-root]');

if (reports) {
    reports.querySelectorAll('[data-report-url]').forEach((button) => {
        button.addEventListener('click', async () => {
            const card = button.closest('[data-report-card]');
            const status = card.querySelector('[data-report-status]');
            if (card.getAttribute('aria-busy') === 'true') return;
            card.setAttribute('aria-busy', 'true');
            const buttons = card.querySelectorAll('[data-report-url]');
            buttons.forEach((item) => { item.disabled = true; });
            status.textContent = 'Preparando descarga…';
            try {
                // GET preserves the existing session and downloads the complete server scope.
                const response = await window.axios.get(button.dataset.reportUrl, { responseType: 'blob' });
                const url = URL.createObjectURL(response.data);
                const link = document.createElement('a');
                link.href = url;
                link.download = button.dataset.reportFilename;
                document.body.append(link);
                link.click();
                link.remove();
                window.setTimeout(() => URL.revokeObjectURL(url), 1000);
                status.textContent = 'Descarga preparada.';
            } catch (error) {
                const code = error.response?.status;
                let data = error.response?.data;
                if (data instanceof Blob) {
                    try { data = JSON.parse(await data.text()); } catch { data = null; }
                }
                status.textContent = ({
                    401: 'Tu sesión ha expirado. Inicia sesión nuevamente.',
                    403: 'No puedes descargar este reporte. Comprueba tus permisos y que tu usuario esté activo.',
                    404: 'El reporte solicitado no está disponible.',
                    419: 'La sesión ha expirado. Recarga la página e inicia sesión nuevamente.',
                }[code]) || (code < 500 && data?.message) || 'No se pudo preparar la descarga. Inténtalo de nuevo.';
                if (code === 401) window.location.assign(reports.dataset.loginUrl);
            } finally {
                card.setAttribute('aria-busy', 'false');
                buttons.forEach((item) => { item.disabled = false; });
            }
        });
    });
}
