@extends('layouts.app')
@section('title', 'Toma de Inventario | Sistema de Inventario')
@section('content')
@php
    $canManage = app(\App\Services\AlcanceDatosService::class)->tieneAccesoCompleto(auth('web')->user());
@endphp
<div class="catalog-page" data-inventory-root
    data-inventories-url="{{ url('/api/inventarios') }}"
    data-login-url="{{ route('login') }}"
    @if ($canManage)
        data-bienes-url="{{ url('/api/bienes') }}"
        data-csrf-url="{{ url('/sanctum/csrf-cookie') }}"
    @endif
>
    <nav class="breadcrumb" aria-label="Ruta de navegación">
        <span>Inventario</span><span class="breadcrumb-separator" aria-hidden="true">›</span>
        <span aria-current="page">Toma de Inventario</span>
    </nav>
    <div class="catalog-card">
        <header class="catalog-header">
            <div><p class="catalog-eyebrow">Inventario patrimonial</p><h1>Toma de Inventario</h1>
                <p class="catalog-subtitle">Consulta los registros de inventario de cada bien y año.</p>
            </div>
            @if ($canManage)
                <button type="button" class="catalog-primary-button" data-new-inventory>Registrar inventario</button>
            @endif
        </header>
        <nav class="catalog-tabs" aria-label="Secciones de inventario">
            @if ($canManage)
                <a href="{{ route('inventario.bienes') }}" class="catalog-tab">Catálogo de Bienes</a>
                <a href="{{ route('inventario.estaciones') }}" class="catalog-tab">Estaciones</a>
            @else
                <span class="catalog-tab catalog-tab-disabled" aria-disabled="true" title="Sin permiso para esta sección">Catálogo de Bienes</span>
                <span class="catalog-tab catalog-tab-disabled" aria-disabled="true" title="Sin permiso para esta sección">Estaciones</span>
            @endif
            <a href="{{ route('inventario.toma-inventario') }}" class="catalog-tab active" aria-current="page">Toma de Inventario</a>
        </nav>
        <div class="inventory-toolbar">
            <p class="catalog-subtitle">{{ $canManage ? 'Alcance: todos los registros de inventario.' : 'Solo consulta: inventarios de bienes en ambientes de tipo LABORATORIO.' }}</p>
            <div class="inventory-export-actions">
                <button type="button" class="catalog-secondary-button" data-export-url="{{ url('/api/reportes/inventario/pdf') }}" data-export-filename="reporte-inventario.pdf">Descargar PDF</button>
                <button type="button" class="catalog-secondary-button" data-export-url="{{ url('/api/reportes/inventario/excel') }}" data-export-filename="reporte-inventario.xlsx">Descargar Excel</button>
            </div>
            <p class="catalog-subtitle">Las descargas incluyen todos los registros permitidos por tu rol, no solo la página visible.</p>
            <p class="catalog-message" data-export-message role="status" aria-live="polite"></p>
        </div>
        <section class="catalog-filters" aria-labelledby="inventory-search-title">
            <div class="catalog-section-heading"><h2 id="inventory-search-title">Buscar registros</h2><span>La búsqueda se limita a la página visible</span></div>
            <div class="catalog-filter-grid"><label>CBI, descripción, año o inventario en esta página<input type="search" data-inventory-search placeholder="Buscar en esta página" disabled></label></div>
            <p class="catalog-search-summary" data-inventory-summary aria-live="polite"></p>
        </section>
        <section class="catalog-results" aria-labelledby="inventory-results-title">
            <div class="catalog-section-heading"><h2 id="inventory-results-title">Registros de inventario</h2><span data-inventory-count></span></div>
            <p class="catalog-message" data-inventory-message role="status" aria-live="polite">Cargando inventarios…</p>
            <button type="button" class="catalog-retry-button" data-inventory-retry hidden>Reintentar carga</button>
            <div class="catalog-table-scroll"><table class="catalog-table">
                <thead><tr><th scope="col">Bien ID</th><th scope="col">CBI</th><th scope="col">Descripción del bien</th><th scope="col">Año</th><th scope="col">Inventario</th><th scope="col">Acciones</th></tr></thead>
                <tbody data-inventory-rows></tbody>
            </table></div>
            <div class="catalog-pagination" aria-label="Paginación de inventarios"><span data-inventory-page>Página —</span><div class="catalog-pagination-actions">
                <button type="button" data-inventory-previous disabled>Anterior</button><button type="button" data-inventory-next disabled>Siguiente</button>
            </div></div>
        </section>
    </div>
    <dialog class="catalog-dialog" data-inventory-detail-dialog aria-labelledby="inventory-detail-title">
        <div class="catalog-detail-content">
            <div class="catalog-dialog-heading"><div><p class="catalog-eyebrow">Toma de Inventario</p><h2 id="inventory-detail-title">Detalle de inventario</h2></div><button type="button" class="catalog-dialog-close" data-close-dialog aria-label="Cerrar">×</button></div>
            <p class="catalog-message" data-detail-message role="status" aria-live="polite"></p>
            <button type="button" class="catalog-retry-button" data-detail-retry hidden>Reintentar carga</button>
            <dl class="catalog-detail-grid" data-inventory-detail-fields hidden>
                <div><dt>Bien ID</dt><dd data-detail-bien></dd></div><div><dt>Año</dt><dd data-detail-anio></dd></div>
                <div><dt>CBI</dt><dd data-detail-cbi></dd></div><div><dt>Inventario</dt><dd data-detail-inventario></dd></div>
                <div class="catalog-detail-wide"><dt>Descripción del bien</dt><dd data-detail-descripcion></dd></div>
            </dl>
            <form method="dialog" class="catalog-detail-actions"><button class="catalog-secondary-button">Cerrar</button></form>
        </div>
    </dialog>
    @if ($canManage)
        <dialog class="catalog-dialog stations-detail-dialog" data-create-inventory-dialog aria-labelledby="create-inventory-title">
            <form class="catalog-form" data-create-inventory-form>
                <div class="catalog-dialog-heading"><div><p class="catalog-eyebrow">Toma de Inventario</p><h2 id="create-inventory-title">Registrar inventario</h2></div><button type="button" class="catalog-dialog-close" data-close-dialog aria-label="Cerrar">×</button></div>
                <p class="catalog-form-message" data-form-message role="alert"></p>
                <div class="catalog-form-grid"><label class="catalog-form-wide">CBI o descripción en la página visible<input type="search" data-picker-search placeholder="Buscar en esta página" disabled></label></div>
                <p class="catalog-subtitle">Selecciona un bien del listado paginado. La búsqueda solo consulta la página visible.</p>
                <p class="catalog-message" data-picker-message role="status" aria-live="polite"></p><p class="catalog-search-summary" data-picker-summary aria-live="polite"></p>
                <button type="button" class="catalog-retry-button" data-picker-retry hidden>Reintentar carga</button>
                <div class="catalog-table-scroll"><table class="catalog-table"><thead><tr><th scope="col">Seleccionar</th><th scope="col">Bien ID</th><th scope="col">CBI</th><th scope="col">Descripción</th></tr></thead><tbody data-picker-rows></tbody></table></div>
                <div class="catalog-pagination" aria-label="Paginación de bienes"><span data-picker-page>Página —</span><div class="catalog-pagination-actions"><button type="button" data-picker-previous disabled>Anterior</button><button type="button" data-picker-next disabled>Siguiente</button></div></div>
                <p class="stations-selection" data-selected-bien aria-live="polite">Ningún bien seleccionado.</p>
                <div class="catalog-form-grid">
                    <label>Año <span aria-hidden="true">*</span><input name="anio" type="number" step="1" min="-2147483648" max="2147483647" required></label>
                    <label>Inventario <span aria-hidden="true">*</span><input name="inventario" type="text" maxlength="20" required></label>
                </div>
                <div class="catalog-form-actions"><button type="button" class="catalog-secondary-button" data-close-dialog>Cancelar</button><button type="submit" class="catalog-primary-button" data-save-inventory disabled>Guardar inventario</button></div>
            </form>
        </dialog>
        <dialog class="catalog-dialog" data-edit-inventory-dialog aria-labelledby="edit-inventory-title">
            <form class="catalog-form" data-edit-inventory-form>
                <div class="catalog-dialog-heading"><div><p class="catalog-eyebrow">Toma de Inventario</p><h2 id="edit-inventory-title">Editar inventario</h2></div><button type="button" class="catalog-dialog-close" data-close-dialog aria-label="Cerrar">×</button></div>
                <p class="stations-selection" data-edit-identity></p>
                <p class="catalog-subtitle">Bien ID y año identifican el registro y no se pueden modificar.</p>
                <p class="catalog-form-message" data-form-message role="alert"></p>
                <div class="catalog-form-grid"><label class="catalog-form-wide">Inventario <span aria-hidden="true">*</span><input name="inventario" type="text" maxlength="20" required></label></div>
                <div class="catalog-form-actions"><button type="button" class="catalog-secondary-button" data-close-dialog>Cancelar</button><button type="submit" class="catalog-primary-button">Guardar cambios</button></div>
            </form>
        </dialog>
        <dialog class="catalog-dialog" data-delete-inventory-dialog aria-labelledby="delete-inventory-title">
            <form class="catalog-form" data-delete-inventory-form>
                <div class="catalog-dialog-heading"><div><p class="catalog-eyebrow">Toma de Inventario</p><h2 id="delete-inventory-title">Eliminar inventario</h2></div><button type="button" class="catalog-dialog-close" data-close-dialog aria-label="Cerrar">×</button></div>
                <p class="stations-selection" data-delete-identity></p><p class="catalog-subtitle">Se eliminará únicamente este registro de inventario. El bien permanece en el catálogo. Esta acción no se puede deshacer.</p>
                <p class="catalog-form-message" data-form-message role="alert"></p>
                <div class="catalog-form-actions"><button type="button" class="catalog-secondary-button" data-close-dialog>Cancelar</button><button type="submit" class="catalog-primary-button">Eliminar registro</button></div>
            </form>
        </dialog>
    @endif
</div>
@endsection
