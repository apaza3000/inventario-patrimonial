@extends('layouts.app')
@section('title', 'Registro de Mantenimientos | Sistema de Inventario')
@section('content')
@php
    $canManage = app(\App\Services\AlcanceDatosService::class)->tieneAccesoCompleto(auth('web')->user());
@endphp
<div class="catalog-page" data-maintenance-root data-maintenance-url="{{ url('/api/mantenimientos') }}" data-login-url="{{ route('login') }}"
    @if ($canManage)
        data-types-url="{{ url('/api/tipos-mantenimiento') }}" data-bienes-url="{{ url('/api/bienes') }}"
        data-users-url="{{ url('/api/usuarios') }}" data-csrf-url="{{ url('/sanctum/csrf-cookie') }}" data-conditions-url="{{ url('/api/condiciones-bien') }}"
    @endif
>
    <nav class="breadcrumb" aria-label="Ruta de navegación"><span>Mantenimiento</span><span class="breadcrumb-separator" aria-hidden="true">›</span><span aria-current="page">Registro de Mantenimientos</span></nav>
    <div class="catalog-card">
        <header class="catalog-header">
            <div><p class="catalog-eyebrow">Mantenimiento patrimonial</p><h1>Registro de Mantenimientos</h1><p class="catalog-subtitle">Consulta los mantenimientos registrados y el trabajo realizado sobre cada bien.</p></div>
            @if ($canManage)
                <button type="button" class="catalog-primary-button" data-new-maintenance>Registrar mantenimiento</button>
            @endif
        </header>
        <div class="inventory-toolbar">
            <p class="catalog-subtitle">{{ $canManage ? 'Alcance: todos los registros de mantenimiento.' : 'Solo consulta: mantenimientos de equipos ubicados actualmente en ambientes de tu especialidad.' }}</p>
            <div class="inventory-export-actions">
                <button type="button" class="catalog-secondary-button" data-export-url="{{ url('/api/reportes/mantenimientos/pdf') }}" data-export-filename="reporte-mantenimientos.pdf">Descargar PDF</button>
                <button type="button" class="catalog-secondary-button" data-export-url="{{ url('/api/reportes/mantenimientos/excel') }}" data-export-filename="reporte-mantenimientos.xlsx">Descargar Excel</button>
            </div>
            <p class="catalog-subtitle">Las descargas incluyen todos los registros permitidos por tu alcance, no solamente la página visible.</p>
            <p class="catalog-message" data-export-message role="status" aria-live="polite"></p>
        </div>
        <section class="catalog-filters" aria-labelledby="maintenance-search-title">
            <div class="catalog-section-heading"><h2 id="maintenance-search-title">Buscar registros</h2><span>La búsqueda se limita a la página visible</span></div>
            <div class="catalog-filter-grid"><label>ID, fecha, CBI, descripción del bien, tipo o técnico<input type="search" data-maintenance-search placeholder="Buscar en esta página" disabled></label></div>
            <p class="catalog-search-summary" data-maintenance-summary aria-live="polite"></p>
        </section>
        <section class="catalog-results" aria-labelledby="maintenance-results-title">
            <div class="catalog-section-heading"><h2 id="maintenance-results-title">Mantenimientos registrados</h2><span data-maintenance-count></span></div>
            <p class="catalog-message" data-maintenance-message role="status" aria-live="polite">Cargando mantenimientos…</p><button type="button" class="catalog-retry-button" data-maintenance-retry hidden>Reintentar carga</button>
            <div class="catalog-table-scroll"><table class="catalog-table"><thead><tr><th scope="col">ID</th><th scope="col">Fecha</th><th scope="col">CBI</th><th scope="col">Descripción del bien</th><th scope="col">Tipo de mantenimiento</th><th scope="col">Técnico</th><th scope="col">Acciones</th></tr></thead><tbody data-maintenance-rows></tbody></table></div>
            <div class="catalog-pagination" aria-label="Paginación de mantenimientos"><span data-maintenance-page>Página —</span><div class="catalog-pagination-actions"><button type="button" data-maintenance-previous disabled>Anterior</button><button type="button" data-maintenance-next disabled>Siguiente</button></div></div>
        </section>
    </div>
    <dialog class="catalog-dialog" data-maintenance-detail-dialog aria-labelledby="maintenance-detail-title">
        <div class="catalog-detail-content">
            <div class="catalog-dialog-heading"><div><p class="catalog-eyebrow">Mantenimiento</p><h2 id="maintenance-detail-title">Detalle de mantenimiento</h2></div><button type="button" class="catalog-dialog-close" data-close-dialog aria-label="Cerrar">×</button></div>
            <p class="catalog-message" data-detail-message role="status" aria-live="polite"></p><button type="button" class="catalog-retry-button" data-detail-retry hidden>Reintentar carga</button>
            <dl class="catalog-detail-grid" data-maintenance-detail-fields hidden>
                <div><dt>ID</dt><dd data-detail-id></dd></div><div><dt>Fecha de mantenimiento</dt><dd data-detail-fecha></dd></div>
                <div><dt>Bien ID</dt><dd data-detail-bien></dd></div><div><dt>CBI</dt><dd data-detail-cbi></dd></div>
                <div class="catalog-detail-wide"><dt>Descripción del bien</dt><dd data-detail-bien-descripcion></dd></div>
                <div><dt>Tipo de mantenimiento</dt><dd data-detail-tipo></dd></div><div><dt>Técnico</dt><dd data-detail-tecnico></dd></div>
                <div class="catalog-detail-wide"><dt>Descripción del mantenimiento</dt><dd data-detail-descripcion></dd></div>
                <div class="catalog-detail-wide"><dt>Diagnóstico</dt><dd data-detail-diagnostico></dd></div>
                <div class="catalog-detail-wide"><dt>Trabajo realizado</dt><dd data-detail-trabajo></dd></div>
                <div class="catalog-detail-wide"><dt>Observaciones</dt><dd data-detail-observaciones></dd></div>
            </dl>
            <form method="dialog" class="catalog-detail-actions"><button type="submit" class="catalog-secondary-button">Cerrar</button></form>
        </div>
    </dialog>
    @if ($canManage)
        <dialog class="catalog-dialog maintenance-form-dialog" data-maintenance-form-dialog aria-labelledby="maintenance-form-title">
            <form class="catalog-form" data-maintenance-form>
                <div class="catalog-dialog-heading"><div><p class="catalog-eyebrow">Mantenimiento</p><h2 id="maintenance-form-title">Registrar mantenimiento</h2></div><button type="button" class="catalog-dialog-close" data-close-dialog aria-label="Cerrar">×</button></div>
                <p class="catalog-form-message" data-form-message role="alert" aria-live="polite"></p><button type="button" class="catalog-retry-button" data-form-retry hidden>Reintentar carga del formulario</button>
                @foreach (['bien' => 'Bien', 'user' => 'Técnico'] as $prefix => $label)
                    <fieldset class="maintenance-picker">
                        <legend>{{ $label }}@if ($prefix === 'bien') <span aria-hidden="true">*</span>@endif</legend>
                        <p class="catalog-subtitle">{{ $prefix === 'bien' ? 'Consulta una página de bienes a la vez.' : 'Selecciona un usuario existente o deja Sin asignar.' }} La selección se conserva al cambiar de página.</p>
                        <div class="catalog-form-grid">
                            <label>{{ $prefix === 'bien' ? 'CBI o descripción' : 'Nombre o apellido' }} en esta página<input type="search" data-{{ $prefix }}-search placeholder="Buscar en esta página" disabled></label>
                            <label>Seleccionar {{ $prefix === 'bien' ? 'bien' : 'técnico' }}<select name="{{ $prefix === 'bien' ? 'bien_id' : 'tecnico_id' }}" data-{{ $prefix }}-options @if ($prefix === 'bien') required @endif disabled><option value="">{{ $prefix === 'bien' ? 'Selecciona un bien' : 'Sin asignar' }}</option></select></label>
                        </div>
                        <p class="catalog-message" data-{{ $prefix }}-message role="status" aria-live="polite"></p><p class="catalog-search-summary" data-{{ $prefix }}-summary aria-live="polite"></p><button type="button" class="catalog-retry-button" data-{{ $prefix }}-retry hidden>Reintentar carga</button>
                        <div class="catalog-pagination" aria-label="Paginación de {{ $prefix === 'bien' ? 'bienes' : 'usuarios' }}"><span data-{{ $prefix }}-page>Página —</span><div class="catalog-pagination-actions"><button type="button" data-{{ $prefix }}-previous disabled>Anterior</button><button type="button" data-{{ $prefix }}-next disabled>Siguiente</button></div></div>
                        <p class="stations-selection" data-selected-{{ $prefix }} aria-live="polite"></p>
                    </fieldset>
                @endforeach
                <fieldset class="maintenance-picker" data-condition-section hidden>
                    <legend>Condición del bien</legend>
                    <p class="stations-selection" data-current-condition aria-live="polite">Selecciona un bien para consultar su condición actual.</p>
                    <div class="catalog-form-grid">
                        <label>Cambiar condición (opcional)<select name="condicion_id" disabled><option value="">Mantener la condición actual</option></select></label>
                    </div>
                    <p class="catalog-subtitle">Si eliges otra condición, se aplicará al bien al guardar este mantenimiento.</p>
                    <p class="catalog-message" data-condition-message role="status" aria-live="polite"></p>
                    <button type="button" class="catalog-retry-button" data-condition-retry hidden>Reintentar carga de condiciones</button>
                </fieldset>
                <div class="catalog-form-grid">
                    <label>Tipo de mantenimiento <span aria-hidden="true">*</span><select name="tipo_mantenimiento_id" required disabled><option value="">Selecciona un tipo</option></select></label>
                    <label>Fecha de mantenimiento <span aria-hidden="true">*</span><input name="fecha_mantenimiento" type="date" required></label>
                    <label class="catalog-form-wide">Descripción del mantenimiento <span aria-hidden="true">*</span><textarea name="descripcion" rows="3" maxlength="255" required></textarea></label>
                    <label class="catalog-form-wide">Diagnóstico<textarea name="diagnostico" rows="3" maxlength="255"></textarea></label>
                    <label class="catalog-form-wide">Trabajo realizado<textarea name="trabajo_realizado" rows="3" maxlength="255"></textarea></label>
                    <label class="catalog-form-wide">Observaciones<textarea name="observaciones" rows="3" maxlength="255"></textarea></label>
                </div>
                <div class="catalog-form-actions"><button type="button" class="catalog-secondary-button" data-close-dialog>Cancelar</button><button type="submit" class="catalog-primary-button" data-save-maintenance disabled>Guardar mantenimiento</button></div>
            </form>
        </dialog>
        <dialog class="catalog-dialog" data-maintenance-delete-dialog aria-labelledby="maintenance-delete-title">
            <form class="catalog-form" data-maintenance-delete-form>
                <div class="catalog-dialog-heading"><div><p class="catalog-eyebrow">Mantenimiento</p><h2 id="maintenance-delete-title">Eliminar mantenimiento</h2></div><button type="button" class="catalog-dialog-close" data-close-dialog aria-label="Cerrar">×</button></div>
                <p class="stations-selection" data-delete-identity></p><p class="catalog-subtitle">La eliminación de este registro de mantenimiento es definitiva y no se puede deshacer. El bien permanece en el catálogo.</p>
                <p class="catalog-form-message" data-form-message role="alert" aria-live="polite"></p>
                <div class="catalog-form-actions"><button type="button" class="catalog-secondary-button" data-close-dialog>Cancelar</button><button type="submit" class="catalog-primary-button">Eliminar definitivamente</button></div>
            </form>
        </dialog>
    @endif
</div>
@endsection
