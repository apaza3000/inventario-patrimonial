@extends('layouts.app')

@section('title', 'Catálogo General de Bienes | Sistema de Inventario')

@section('content')
<div
    class="catalog-page"
    data-catalog-root
    data-bienes-url="{{ url('/api/bienes') }}"
    data-options-url="{{ url('/api/bienes/opciones') }}"
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
            <a href="{{ route('inventario.estaciones') }}" class="catalog-tab">Estaciones</a>
            <a href="{{ route('inventario.toma-inventario') }}" class="catalog-tab">Toma de Inventario</a>
        </nav>

        <nav class="catalog-category-tabs" aria-label="Categorías de bienes">
            @foreach (['todos' => 'Todos', 'computadoras' => 'Computadoras', 'monitores' => 'Monitores', 'mobiliario' => 'Mobiliario', 'otros-equipos' => 'Otros equipos'] as $key => $label)
                <button type="button" class="catalog-tab {{ $key === 'todos' ? 'active' : '' }}" data-catalog-category="{{ $key }}" aria-pressed="{{ $key === 'todos' ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </nav>

        <section class="catalog-filters" aria-labelledby="catalog-filters-title">
            <div class="catalog-section-heading">
                <h2 id="catalog-filters-title">Filtros</h2>
                <span>Búsqueda en todos los bienes autorizados</span>
            </div>
            <div class="catalog-filter-grid">
                <label>CBI o descripción<input type="search" maxlength="150" placeholder="Ejemplo: 02" data-catalog-search aria-describedby="catalog-search-help"></label>
                <label>Sede<select data-catalog-sede-id disabled><option value="">Todas las sedes</option></select></label>
                <label>Ambiente<select data-catalog-ambiente-id disabled><option value="">Todos los ambientes</option></select></label>
                <label>Estado<select data-catalog-estado-id disabled><option value="">Todos los estados</option></select></label>
                <label>Condición<select data-catalog-condicion-id disabled><option value="">Todas las condiciones</option></select></label>
            </div>
            <p id="catalog-search-help" class="catalog-subtitle">Escribe un CBI parcial o una descripción. Las coincidencias aparecen en la tabla; usa Ver detalle para consultar el bien.</p>
            <button type="button" class="catalog-secondary-button" data-catalog-clear>Limpiar búsqueda y filtros</button>
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
                <label class="catalog-form-wide"><span class="catalog-field-label">Categoría <span aria-hidden="true">*</span></span><select name="categoria" required><option value="">Selecciona una categoría</option><option value="computadoras">Computadoras</option><option value="monitores">Monitores</option><option value="mobiliario">Mobiliario</option><option value="otros-equipos">Otros equipos</option></select></label>
                <label><span class="catalog-field-label">Descripción <span aria-hidden="true">*</span></span><input name="descripcion" type="text" maxlength="150" required></label>
                <label>CBI<input name="cbi" type="text" maxlength="25"></label>
                <label>Estado<select name="estado_id" data-estado-options><option value="">Sin asignar</option></select></label>
                <label>Condición<select name="condicion_id" data-condicion-options><option value="">Sin asignar</option></select></label>
                <label>Ambiente<select name="ambiente_id" data-ambiente-options><option value="">Sin asignar</option></select></label>
                <label>Activo<select name="activo"><option value="1">Sí</option><option value="0">No</option></select></label>
                <label>Fecha de registro<input name="fecha_registro" type="date"></label>
                <label class="catalog-form-wide">Observaciones<textarea name="observaciones" maxlength="255" rows="3"></textarea></label>
            </div>

            <fieldset class="catalog-technical-form" data-category-fields="equipo" hidden disabled>
                <legend>Características del equipo</legend>
                <div class="catalog-form-grid">
                    <label><span class="catalog-field-label">Tipo de equipo <span aria-hidden="true">*</span></span><select name="tipo_equipo_id" required></select></label>
                    <label>Marca<select name="marca_id"></select></label>
                    <label>Modelo<input name="modelo" maxlength="100"></label>
                    <label>Número de serie<input name="serie" maxlength="100"></label>
                </div>
            </fieldset>
            <fieldset class="catalog-technical-form" data-category-fields="computadoras" hidden disabled>
                <legend>Características de cómputo</legend>
                <div class="catalog-form-grid">
                    <label>Procesador<input name="procesador" maxlength="100"></label>
                    <label>Memoria RAM<input name="ram" maxlength="100"></label>
                    <label>Almacenamiento<input name="almacenamiento" maxlength="150"></label>
                    <label>Detalle adicional<textarea name="detalle_adicional" maxlength="255" rows="2"></textarea></label>
                </div>
            </fieldset>
            <fieldset class="catalog-technical-form" data-category-fields="monitores" hidden disabled>
                <legend>Características de pantalla</legend>
                <div class="catalog-form-grid">
                    <label>Tamaño de pantalla<input name="tamano_pantalla" maxlength="50"></label>
                    <label>Detalle de pantalla<input name="detalle_pantalla" maxlength="150"></label>
                </div>
            </fieldset>
            <fieldset class="catalog-technical-form" data-category-fields="mobiliario" hidden disabled>
                <legend>Características del mobiliario</legend>
                <div class="catalog-form-grid">
                    <label><span class="catalog-field-label">Tipo de mueble <span aria-hidden="true">*</span></span><select name="tipo_mueble_id" required></select></label>
                    <label>Material<input name="material" maxlength="150"></label>
                    <label>Color<input name="color" maxlength="80"></label>
                </div>
            </fieldset>

            <div class="catalog-form-actions">
                <button type="button" class="catalog-secondary-button" data-cancel-bien-dialog>Cancelar</button>
                <button type="submit" class="catalog-primary-button" data-save-bien>Guardar bien</button>
            </div>
        </form>
    </dialog>

    @include('components.bien-detail', ['detailPrefix' => 'bien-detail'])
</div>
@endsection
