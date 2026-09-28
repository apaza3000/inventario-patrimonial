@extends('layouts.app')

@section('title', 'Estaciones | Sistema de Inventario')

@section('content')
<div class="catalog-page" data-stations-root
    data-stations-url="{{ url('/api/estaciones-pc') }}"
    data-components-url="{{ url('/api/estacion-componentes') }}"
    data-bienes-url="{{ url('/api/bienes') }}"
    data-csrf-url="{{ url('/sanctum/csrf-cookie') }}"
    data-login-url="{{ route('login') }}">
    <nav class="breadcrumb" aria-label="Ruta de navegación">
        <span>Inventario</span><span class="breadcrumb-separator" aria-hidden="true">›</span>
        <span aria-current="page">Estaciones</span>
    </nav>

    <div class="catalog-card">
        <header class="catalog-header">
            <div>
                <p class="catalog-eyebrow">Inventario patrimonial</p>
                <h1>Estaciones</h1>
                <p class="catalog-subtitle">Administra las estaciones y consulta el historial de sus componentes.</p>
            </div>
            <button type="button" class="catalog-primary-button" data-new-station>Registrar estación</button>
        </header>
        <nav class="catalog-tabs" aria-label="Secciones de inventario">
            <a href="{{ route('inventario.bienes') }}" class="catalog-tab">Catálogo de Bienes</a>
            <a href="{{ route('inventario.estaciones') }}" class="catalog-tab active" aria-current="page">Estaciones</a>
            <span class="catalog-tab catalog-tab-disabled" aria-disabled="true">Toma de Inventario</span>
        </nav>
        <section class="catalog-filters" aria-labelledby="stations-search-title">
            <div class="catalog-section-heading">
                <h2 id="stations-search-title">Buscar estaciones</h2>
                <span>La búsqueda se limita a la página visible</span>
            </div>
            <div class="catalog-filter-grid">
                <label>Código o descripción en esta página
                    <input type="search" data-stations-search placeholder="Buscar en esta página" disabled>
                </label>
            </div>
            <p class="catalog-search-summary" data-stations-search-summary aria-live="polite"></p>
        </section>
        <section class="catalog-results" aria-labelledby="stations-results-title">
            <div class="catalog-section-heading">
                <h2 id="stations-results-title">Estaciones registradas</h2><span data-stations-count></span>
            </div>
            <p class="catalog-message" data-stations-message role="status" aria-live="polite">Cargando estaciones…</p>
            <button type="button" class="catalog-retry-button" data-stations-retry hidden>Reintentar carga</button>
            <div class="catalog-table-scroll">
                <table class="catalog-table">
                    <thead><tr><th scope="col">Código</th><th scope="col">Descripción</th><th scope="col">Activo</th><th scope="col">Acciones</th></tr></thead>
                    <tbody data-stations-rows></tbody>
                </table>
            </div>
            <div class="catalog-pagination" aria-label="Paginación de estaciones">
                <span data-stations-page>Página —</span>
                <div class="catalog-pagination-actions">
                    <button type="button" data-stations-previous disabled>Anterior</button>
                    <button type="button" data-stations-next disabled>Siguiente</button>
                </div>
            </div>
        </section>
    </div>

    <dialog class="catalog-dialog" data-station-dialog aria-labelledby="station-form-title">
        <form class="catalog-form" data-station-form>
            <div class="catalog-dialog-heading">
                <div><p class="catalog-eyebrow">Estaciones</p><h2 id="station-form-title">Registrar estación</h2></div>
                <button type="button" class="catalog-dialog-close" data-close-dialog aria-label="Cerrar">×</button>
            </div>
            <p class="catalog-form-message" data-form-message role="alert"></p>
            <div class="catalog-form-grid">
                <label>Código <span aria-hidden="true">*</span><input name="codigo" maxlength="20" required></label>
                <label>Activo<select name="activo"><option value="1">Sí</option><option value="0">No</option></select></label>
                <label class="catalog-form-wide">Descripción<input name="descripcion" maxlength="100"></label>
            </div>
            <div class="catalog-form-actions">
                <button type="button" class="catalog-secondary-button" data-close-dialog>Cancelar</button>
                <button type="submit" class="catalog-primary-button">Guardar estación</button>
            </div>
        </form>
    </dialog>

    <dialog class="catalog-dialog stations-detail-dialog" data-station-detail-dialog aria-labelledby="station-detail-title">
        <div class="catalog-detail-content">
            <div class="catalog-dialog-heading">
                <div><p class="catalog-eyebrow">Estaciones</p><h2 id="station-detail-title">Detalle de estación</h2></div>
                <button type="button" class="catalog-dialog-close" data-close-dialog aria-label="Cerrar">×</button>
            </div>
            <p class="catalog-message" data-detail-message role="status" aria-live="polite"></p>
            <button type="button" class="catalog-retry-button" data-detail-retry hidden>Reintentar carga</button>
            <div data-station-detail-content hidden>
                <dl class="catalog-detail-grid">
                    <div><dt>Código</dt><dd data-detail-code></dd></div>
                    <div><dt>Activo</dt><dd data-detail-active></dd></div>
                    <div class="catalog-detail-wide"><dt>Descripción</dt><dd data-detail-description></dd></div>
                </dl>
                <div class="stations-toolbar">
                    <button type="button" class="catalog-primary-button" data-assign-bien>Asignar bien</button>
                    <button type="button" class="catalog-secondary-button" data-edit-detail>Editar estación</button>
                </div>
                <section class="stations-components" aria-labelledby="active-components-title">
                    <div class="catalog-section-heading"><h2 id="active-components-title">Componentes activos</h2><span data-active-count></span></div>
                    <div class="catalog-table-scroll">
                        <table class="catalog-table stations-active-table">
                            <colgroup><col><col><col><col></colgroup>
                            <thead><tr><th scope="col">CBI</th><th scope="col">Descripción del bien</th><th scope="col">Fecha de asignación</th><th scope="col">Acciones</th></tr></thead>
                            <tbody data-active-components></tbody>
                        </table>
                    </div>
                </section>
                <section class="stations-components" aria-labelledby="components-history-title">
                    <div class="catalog-section-heading"><h2 id="components-history-title">Historial de asignaciones</h2></div>
                    <div class="catalog-table-scroll">
                        <table class="catalog-table stations-history-table">
                            <colgroup><col><col><col><col><col></colgroup>
                            <thead><tr><th scope="col">CBI</th><th scope="col">Descripción del bien</th><th scope="col">Asignación</th><th scope="col">Retiro</th><th scope="col">Activo</th></tr></thead>
                            <tbody data-components-history></tbody>
                        </table>
                    </div>
                </section>
                <div class="stations-delete-section">
                    <p id="station-delete-note" data-delete-note>Solo se puede eliminar una estación sin componentes registrados en su historial.</p>
                    <button type="button" class="catalog-secondary-button stations-delete-button" data-delete-station aria-describedby="station-delete-note">Eliminar estación</button>
                </div>
            </div>
            <form method="dialog" class="catalog-detail-actions"><button class="catalog-secondary-button">Cerrar</button></form>
        </div>
    </dialog>

    <dialog class="catalog-dialog stations-detail-dialog" data-assignment-dialog aria-labelledby="assignment-title">
        <form class="catalog-form" data-assignment-form>
            <div class="catalog-dialog-heading">
                <div><p class="catalog-eyebrow" data-assignment-station></p><h2 id="assignment-title">Asignar bien</h2></div>
                <button type="button" class="catalog-dialog-close" data-close-dialog aria-label="Cerrar">×</button>
            </div>
            <p class="catalog-form-message" data-form-message role="alert"></p>
            <div class="catalog-form-grid">
                <label class="catalog-form-wide">CBI o descripción en la página visible
                    <input type="search" data-bienes-search placeholder="Buscar en esta página" disabled>
                </label>
            </div>
            <p class="catalog-subtitle">Selecciona un bien del listado paginado. La búsqueda solo consulta la página visible; el servidor verifica si ya está asignado.</p>
            <p class="catalog-message" data-bienes-message role="status" aria-live="polite"></p>
            <p class="catalog-search-summary" data-bienes-summary aria-live="polite"></p>
            <button type="button" class="catalog-retry-button" data-bienes-retry hidden>Reintentar carga</button>
            <div class="catalog-table-scroll">
                <table class="catalog-table">
                    <thead><tr><th scope="col">Seleccionar</th><th scope="col">CBI</th><th scope="col">Descripción</th><th scope="col">Activo</th></tr></thead>
                    <tbody data-bienes-rows></tbody>
                </table>
            </div>
            <div class="catalog-pagination" aria-label="Paginación de bienes para asignar">
                <span data-bienes-page>Página —</span>
                <div class="catalog-pagination-actions">
                    <button type="button" data-bienes-previous disabled>Anterior</button>
                    <button type="button" data-bienes-next disabled>Siguiente</button>
                </div>
            </div>
            <p class="stations-selection" data-selected-bien aria-live="polite">Ningún bien seleccionado.</p>
            <div class="catalog-form-grid">
                <label>Fecha de asignación (opcional)<input name="fecha_asignacion" type="date"></label>
            </div>
            <div class="catalog-form-actions">
                <button type="button" class="catalog-secondary-button" data-close-dialog>Cancelar</button>
                <button type="submit" class="catalog-primary-button" data-save-assignment disabled>Asignar bien</button>
            </div>
        </form>
    </dialog>

    <dialog class="catalog-dialog" data-withdraw-dialog aria-labelledby="withdraw-title">
        <form class="catalog-form" data-withdraw-form>
            <div class="catalog-dialog-heading">
                <div><p class="catalog-eyebrow">Componentes</p><h2 id="withdraw-title">Retirar componente</h2></div>
                <button type="button" class="catalog-dialog-close" data-close-dialog aria-label="Cerrar">×</button>
            </div>
            <p data-withdraw-bien class="stations-selection"></p>
            <p class="catalog-subtitle">La asignación quedará en el historial y no podrá reactivarse.</p>
            <p class="catalog-form-message" data-form-message role="alert"></p>
            <div class="catalog-form-grid"><label>Fecha de retiro <span aria-hidden="true">*</span><input name="fecha_retiro" type="date" required></label></div>
            <div class="catalog-form-actions">
                <button type="button" class="catalog-secondary-button" data-close-dialog>Cancelar</button>
                <button type="submit" class="catalog-primary-button">Retirar componente</button>
            </div>
        </form>
    </dialog>

    <dialog class="catalog-dialog" data-delete-dialog aria-labelledby="delete-title">
        <form class="catalog-form" data-delete-form>
            <div class="catalog-dialog-heading">
                <div><p class="catalog-eyebrow">Estaciones</p><h2 id="delete-title">Eliminar estación</h2></div>
                <button type="button" class="catalog-dialog-close" data-close-dialog aria-label="Cerrar">×</button>
            </div>
            <p data-delete-description></p>
            <p class="catalog-form-message" data-form-message role="alert"></p>
            <div class="catalog-form-actions">
                <button type="button" class="catalog-secondary-button" data-close-dialog>Cancelar</button>
                <button type="submit" class="catalog-primary-button">Eliminar estación</button>
            </div>
        </form>
    </dialog>
</div>
@endsection
