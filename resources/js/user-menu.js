const userMenu = document.querySelector('[data-user-menu]');

if (userMenu) {
    const toggle = userMenu.querySelector('[data-user-menu-toggle]');
    const panel = userMenu.querySelector('[data-user-menu-panel]');
    const logout = userMenu.querySelector('[data-user-logout]');
    const status = userMenu.querySelector('[data-user-menu-status]');

    const setOpen = (open) => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
    };

    toggle.addEventListener('click', () => setOpen(panel.hidden));

    document.addEventListener('pointerdown', (event) => {
        if (!userMenu.contains(event.target)) setOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            toggle.focus();
        }
    });

    logout.addEventListener('click', async () => {
        if (logout.disabled) return;

        logout.disabled = true;
        logout.textContent = 'Cerrando sesión…';
        status.textContent = '';

        try {
            await window.axios.get(userMenu.dataset.csrfUrl);
            await window.axios.post(userMenu.dataset.logoutUrl);
            window.location.assign(userMenu.dataset.loginUrl);
        } catch (error) {
            if (error.response?.status === 401) {
                window.location.assign(userMenu.dataset.loginUrl);
                return;
            }

            status.textContent = error.response?.data?.message
                || 'No se pudo cerrar sesión. Inténtalo de nuevo.';
            logout.disabled = false;
            logout.textContent = 'Cerrar sesión';
            setOpen(true);
        }
    });
}
