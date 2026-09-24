const form = document.querySelector('[data-login-form]');

if (form) {
    const password = form.querySelector('[name="password"]');
    const togglePassword = form.querySelector('[data-toggle-password]');
    const submitButton = form.querySelector('[type="submit"]');
    const status = form.querySelector('[data-login-status]');

    togglePassword.addEventListener('click', () => {
        const isVisible = password.type === 'password';
        password.type = isVisible ? 'text' : 'password';
        togglePassword.setAttribute('aria-label', isVisible ? 'Ocultar contraseña' : 'Mostrar contraseña');
        togglePassword.setAttribute('aria-pressed', String(isVisible));
    });

    form.addEventListener('input', () => {
        status.textContent = '';
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!form.reportValidity()) {
            return;
        }

        status.textContent = '';
        submitButton.disabled = true;

        try {
            await window.axios.get('/sanctum/csrf-cookie');
            await window.axios.post('/api/login', {
                correo: form.elements.correo.value.trim(),
                password: password.value,
            });

            window.location.assign(form.dataset.dashboardUrl);
        } catch (error) {
            const code = error.response?.status;

            if (code === 401) {
                status.textContent = 'Correo o contraseña incorrectos, o usuario inactivo.';
            } else if (code === 422) {
                status.textContent = 'Verifica el correo institucional y la contraseña.';
            } else if (code === 429) {
                status.textContent = 'Demasiados intentos. Espera un minuto y vuelve a intentarlo.';
            } else {
                status.textContent = 'No se pudo iniciar sesión. Inténtalo de nuevo.';
            }

            submitButton.disabled = false;
        }
    });
}
