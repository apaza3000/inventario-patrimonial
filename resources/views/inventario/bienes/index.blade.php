@extends('layouts.app')

@section('title', 'Catálogo General de Bienes | Sistema de Inventario')

@section('content')
<div
    class="catalog-page"
    data-catalog-root
    data-bienes-url="{{ url('/api/bienes') }}"
    data-sedes-url="{{ url('/api/sedes') }}"
    data-ambientes-url="{{ url('/api/ambientes') }}"
    data-estados-url="{{ url('/api/estados-bien') }}"
    data-condiciones-url="{{ url('/api/condiciones-bien') }}"
    data-csrf-url="{{ url('/sanctum/csrf-cookie') }}"
    data-login-url="{{ route('login') }}"
>
    <nav class="breadcrumb" aria-label="Ruta de navegación">
        <span>Inventario</span>
        <span class="breadcrumb-separator" aria-hidden="true">›</span>
        <span aria-current="page">Catálogo General de Bienes</span>
    </nav>

    <div class="catalog-card">
        <header class="catalog-header">
            <div>
                <p class="catalog-eyebrow">Inventario patrimonial</p>
                <h1>Catálogo General de Bienes</h1>
                <p class="catalog-subtitle">Consulta los bienes registrados en el sistema.</p>
            </div>
            <button type="button" class="catalog-primary-button" data-open-bien-dialog>Registrar nuevo bien</button>
        </header>

        <nav class="catalog-tabs" aria-label="Secciones de inventario">
            <a href="{{ route('inventario.bienes') }}" class="catalog-tab active" aria-current="page">Catálogo de Bienes</a>
            <span class="catalog-tab catalog-tab-disabled" aria-disabled="true">Estaciones</span>
            <span class="catalog-tab catalog-tab-disabled" aria-disabled="true">Toma de Inventario</span>
        </nav>

        <section class="catalog-filters" aria-labelledby="catalog-filters-title">
            <div class="catalog-section-heading">
                <h2 id="catalog-filters-title">Filtros</h2>
                <span>La búsqueda se limita a la página visible</span>
            </div>
            <div class="catalog-filter-grid">
                <label>CBI o descripción en esta página<input type="search" placeholder="Buscar en esta página" data-catalog-search disabled></label>
                <label>Sede<select disabled><option>Todas las sedes</option></select></label>
                <label>Ambiente<select disabled><option>Todos los ambientes</option></select></label>
                <label>Estado<select disabled><option>Todos los estados</option></select></label>
                <label>Condición<select disabled><option>Todas las condiciones</option></select></label>
            </div>
            <p class="catalog-search-summary" data-catalog-search-summary aria-live="polite"></p>
        </section>

        <section class="catalog-results" aria-labelledby="catalog-results-title">
            <div class="catalog-section-heading">
                <h2 id="catalog-results-title">Bienes registrados</h2>
                <span data-catalog-count></span>
            </div>

            <p class="catalog-message" data-catalog-message role="status" aria-live="polite">Cargando bienes…</p>
            <button type="button" class="catalog-retry-button" data-catalog-retry hidden>Reintentar carga</button>

            <div class="catalog-table-scroll">
                <table class="catalog-table">
                    <thead>
                        <tr>
                            <th scope="col">CBI</th>
                            <th scope="col">Descripción</th>
                            <th scope="col">Sede</th>
                            <th scope="col">Ambiente</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Condición</th>
                            <th scope="col">Activo</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody data-catalog-rows></tbody>
                </table>
            </div>

            <div class="catalog-pagination" aria-label="Paginación del catálogo">
                <span data-catalog-page-label>Página —</span>
                <div class="catalog-pagination-actions">
                    <button type="button" data-catalog-previous disabled>Anterior</button>
                    <button type="button" data-catalog-next disabled>Siguiente</button>
                </div>
            </div>
        </section>
    </div>

    <dialog class="catalog-dialog" data-bien-dialog aria-labelledby="bien-dialog-title">
        <form class="catalog-form" data-bien-form novalidate>
            <div class="catalog-dialog-heading">
                <div>
                    <p class="catalog-eyebrow">Catálogo de Bienes</p>
                    <h2 id="bien-dialog-title">Registrar nuevo bien</h2>
                </div>
                <button type="button" class="catalog-dialog-close" data-close-bien-dialog aria-label="Cerrar">×</button>
            </div>

            <p class="catalog-form-message" data-bien-form-message role="alert" aria-live="polite"></p>

            <div class="catalog-form-grid">
                <label>Descripción <span aria-hidden="true">*</span><input name="descripcion" type="text" maxlength="150" required></label>
                <label>CBI<input name="cbi" type="text" maxlength="25"></label>
                <label>Estado<select name="estado_id" data-estado-options><option value="">Sin asignar</option></select></label>
                <label>Condición<select name="condicion_id" data-condicion-options><option value="">Sin asignar</option></select></label>
                <label>Ambiente<select name="ambiente_id" data-ambiente-options><option value="">Sin asignar</option></select></label>
                <label>Activo<select name="activo"><option value="1">Sí</option><option value="0">No</option></select></label>
                <label>Fecha de registro<input name="fecha_registro" type="date"></label>
                <label class="catalog-form-wide">Observaciones<textarea name="observaciones" maxlength="255" rows="3"></textarea></label>
            </div>

            <div class="catalog-form-actions">
                <button type="button" class="catalog-secondary-button" data-cancel-bien-dialog>Cancelar</button>
                <button type="submit" class="catalog-primary-button" data-save-bien>Guardar bien</button>
            </div>
        </form>
    </dialog>

    <dialog class="catalog-dialog catalog-detail-dialog" data-bien-detail-dialog aria-labelledby="bien-detail-title">
        <div class="catalog-detail-content">
            <div class="catalog-dialog-heading">
                <div>
                    <p class="catalog-eyebrow">Catálogo de Bienes</p>
                    <h2 id="bien-detail-title">Detalle del bien</h2>
                </div>
                <button type="button" class="catalog-dialog-close" data-close-bien-detail aria-label="Cerrar">×</button>
            </div>

            <p class="catalog-message" data-bien-detail-message role="status" aria-live="polite"></p>
            <dl class="catalog-detail-grid" data-bien-detail-fields hidden>
                <div><dt>ID</dt><dd data-detail-id></dd></div>
                <div><dt>CBI</dt><dd data-detail-cbi></dd></div>
                <div class="catalog-detail-wide"><dt>Descripción</dt><dd data-detail-descripcion></dd></div>
                <div><dt>Sede</dt><dd data-detail-sede></dd></div>
                <div><dt>Ambiente</dt><dd data-detail-ambiente></dd></div>
                <div><dt>Estado</dt><dd data-detail-estado></dd></div>
                <div><dt>Condición</dt><dd data-detail-condicion></dd></div>
                <div><dt>Activo</dt><dd data-detail-activo></dd></div>
                <div><dt>Fecha de registro</dt><dd data-detail-fecha></dd></div>
                <div class="catalog-detail-wide"><dt>Observaciones</dt><dd data-detail-observaciones></dd></div>
            </dl>
            <form method="dialog" class="catalog-detail-actions">
                <button type="submit" class="catalog-secondary-button">Cerrar</button>
            </form>
        </div>
    </dialog>
</div>
@endsection
