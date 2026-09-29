@extends('layouts.app')
@section('title', 'Movimientos | Sistema de Inventario')
@section('content')
@php
    $canList = app(\App\Services\AlcanceDatosService::class)->tieneAccesoCompleto(auth('web')->user());
@endphp
<div class="catalog-page" data-movements-root
    data-movements-url="{{ url('/api/movimientos') }}"
    data-options-url="{{ url('/api/movimientos/opciones') }}"
    data-csrf-url="{{ url('/sanctum/csrf-cookie') }}"
    data-login-url="{{ route('login') }}">
    <nav class="breadcrumb" aria-label="Ruta de navegación"><span>Movimientos</span><span class="breadcrumb-separator" aria-hidden="true">›</span><span aria-current="page">Movimientos de Bienes</span></nav>
    <div class="catalog-card">
        <header class="catalog-header">
            <div><p class="catalog-eyebrow">Inventario patrimonial</p><h1>Movimientos de Bienes</h1><p class="catalog-subtitle">Registra traslados y completa su documentación firmada.</p></div>
            <button type="button" class="catalog-primary-button" data-new-movement>Registrar movimiento</button>
        </header>
        <p class="movement-flow-note">El registro genera una constancia pendiente de firma. La ubicación del bien cambia únicamente al subir el PDF firmado y finalizar el movimiento.</p>
        <p class="catalog-message" data-movement-notice role="status" aria-live="polite"></p>
        <section class="catalog-filters" aria-labelledby="movement-lookup-title">
            <div class="catalog-section-heading"><h2 id="movement-lookup-title">Consultar por ID</h2></div>
            @unless ($canList)<p class="catalog-subtitle">Tu rol permite registrar movimientos y consultarlos por su ID. Conserva el ID obtenido al registrar para continuar el proceso de firma.</p>@endunless
            <form class="movement-lookup" data-movement-lookup-form>
                <div class="catalog-form-grid"><label>ID del movimiento<input name="id" type="number" min="1" max="2147483647" step="1" required></label></div>
                <button type="submit" class="catalog-secondary-button">Consultar movimiento</button>
            </form>
        </section>
        @if ($canList)
            <section class="catalog-filters" aria-labelledby="movement-search-title">
                <div class="catalog-section-heading"><h2 id="movement-search-title">Buscar movimientos</h2><span>La búsqueda se limita a la página visible</span></div>
                <div class="catalog-filter-grid"><label>ID, CBI, descripción o ambiente en esta página<input type="search" data-movements-search placeholder="Buscar en esta página" disabled></label></div>
                <p class="catalog-search-summary" data-movements-summary aria-live="polite"></p>
            </section>
            <section class="catalog-results" aria-labelledby="movement-list-title" data-movements-list>
                <div class="catalog-section-heading"><h2 id="movement-list-title">Movimientos registrados</h2><span data-movements-count></span></div>
                <p class="catalog-message" data-movements-message role="status" aria-live="polite">Cargando movimientos…</p>
                <button type="button" class="catalog-retry-button" data-movements-retry hidden>Reintentar carga</button>
                <div class="catalog-table-scroll"><table class="catalog-table">
                    <thead><tr><th scope="col">ID</th><th scope="col">Fecha</th><th scope="col">CBI</th><th scope="col">Descripción del bien</th><th scope="col">Ambiente de origen</th><th scope="col">Ambiente de destino</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
                    <tbody data-movements-rows></tbody>
                </table></div>
                <div class="catalog-pagination" aria-label="Paginación de movimientos"><span data-movements-page>Página —</span><div class="catalog-pagination-actions"><button type="button" data-movements-previous disabled>Anterior</button><button type="button" data-movements-next disabled>Siguiente</button></div></div>
            </section>
        @endif
    </div>

    <dialog class="catalog-dialog" data-movement-create-dialog aria-labelledby="movement-create-title">
        <form class="catalog-form" data-movement-create-form>
            <div class="catalog-dialog-heading"><div><p class="catalog-eyebrow">Movimientos</p><h2 id="movement-create-title">Registrar movimiento</h2></div><button type="button" class="catalog-dialog-close" data-close-dialog aria-label="Cerrar">×</button></div>
            <p class="catalog-form-message" data-form-message role="alert"></p>
            <button type="button" class="catalog-retry-button" data-options-retry hidden>Actualizar opciones</button>
            <div class="catalog-form-grid">
                <label class="catalog-form-wide">Bien <span aria-hidden="true">*</span><select name="bien_id" required><option value="">Selecciona un bien</option></select></label>
                <label>Origen según ubicación actual<input type="text" data-origin-label readonly value="—"></label>
                <label>Destino <span aria-hidden="true">*</span><select name="ambiente_destino_id" required><option value="">Selecciona un destino</option></select></label>
                <label>Ordenado por <span aria-hidden="true">*</span><select name="ordenado_por" required><option value="">Selecciona un usuario</option></select></label>
                <label>Ejecutado por <span aria-hidden="true">*</span><select name="ejecutado_por" required><option value="">Selecciona un usuario</option></select></label>
                <label class="catalog-form-wide">Fecha y hora <span aria-hidden="true">*</span><input name="fecha_movimiento" type="datetime-local" step="1" required></label>
                <label class="catalog-form-wide">Motivo <span aria-hidden="true">*</span><textarea name="motivo" maxlength="255" rows="2" required></textarea></label>
                <label class="catalog-form-wide">Observaciones<textarea name="observaciones" maxlength="255" rows="3"></textarea></label>
            </div>
            <p class="movement-flow-note">Al guardar se generará el PDF. El bien permanecerá en su ubicación actual hasta cargar el documento firmado.</p>
            <div class="catalog-form-actions"><button type="button" class="catalog-secondary-button" data-close-dialog>Cancelar</button><button type="submit" class="catalog-primary-button" data-save-movement disabled>Registrar movimiento</button></div>
        </form>
    </dialog>

    <dialog class="catalog-dialog" data-movement-detail-dialog aria-labelledby="movement-detail-title">
        <div class="catalog-detail-content">
            <div class="catalog-dialog-heading"><div><p class="catalog-eyebrow">Movimientos</p><h2 id="movement-detail-title">Detalle de movimiento</h2></div><button type="button" class="catalog-dialog-close" data-close-dialog aria-label="Cerrar">×</button></div>
            <p class="catalog-message" data-detail-message role="status" aria-live="polite"></p>
            <button type="button" class="catalog-retry-button" data-detail-retry hidden>Reintentar carga</button>
            <div data-movement-detail-content hidden>
                <dl class="catalog-detail-grid">
                    <div><dt>ID del movimiento</dt><dd data-detail-id></dd></div><div><dt>Estado</dt><dd data-detail-estado></dd></div>
                    <div><dt>Fecha del movimiento</dt><dd data-detail-fecha></dd></div><div><dt>Bien ID</dt><dd data-detail-bien></dd></div>
                    <div><dt>CBI</dt><dd data-detail-cbi></dd></div><div><dt>Descripción del bien</dt><dd data-detail-descripcion></dd></div>
                    <div><dt>Ambiente de origen</dt><dd data-detail-origen></dd></div><div><dt>Ambiente de destino</dt><dd data-detail-destino></dd></div>
                    <div><dt>Ordenado por</dt><dd data-detail-ordenado></dd></div><div><dt>Ejecutado por</dt><dd data-detail-ejecutado></dd></div>
                    <div class="catalog-detail-wide"><dt>Motivo</dt><dd data-detail-motivo></dd></div><div class="catalog-detail-wide"><dt>Observaciones</dt><dd data-detail-observaciones></dd></div>
                    <div class="catalog-detail-wide" data-sign-date hidden><dt>Fecha de firma registrada en el sistema</dt><dd data-detail-firma></dd></div>
                </dl>
                <p class="movement-flow-note" data-detail-flow></p>
                <div class="movement-actions">
                    <button type="button" class="catalog-secondary-button" data-download-generated>Descargar PDF generado</button>
                    <button type="button" class="catalog-primary-button" data-open-upload hidden>Subir PDF firmado</button>
                    <button type="button" class="catalog-secondary-button" data-download-signed hidden>Descargar PDF firmado</button>
                </div>
                <p class="catalog-message" data-download-message role="status" aria-live="polite"></p>
            </div>
            <form method="dialog" class="catalog-detail-actions"><button class="catalog-secondary-button">Cerrar</button></form>
        </div>
    </dialog>

    <dialog class="catalog-dialog" data-movement-upload-dialog aria-labelledby="movement-upload-title">
        <form class="catalog-form" data-movement-upload-form>
            <div class="catalog-dialog-heading"><div><p class="catalog-eyebrow">Movimientos</p><h2 id="movement-upload-title">Subir PDF firmado y finalizar</h2></div><button type="button" class="catalog-dialog-close" data-close-dialog aria-label="Cerrar">×</button></div>
            <p class="stations-selection" data-upload-identity></p>
            <p class="movement-flow-note" data-upload-warning></p>
            <p class="catalog-subtitle">Sube la constancia firmada por quien ordena. Una vez finalizado, el PDF firmado no podrá reemplazarse.</p>
            <p class="catalog-form-message" data-form-message role="alert"></p>
            <div class="catalog-form-grid"><label class="catalog-form-wide">PDF firmado (máximo 10 MB) <span aria-hidden="true">*</span><input name="archivo" type="file" accept="application/pdf,.pdf" required></label></div>
            <div class="catalog-form-actions"><button type="button" class="catalog-secondary-button" data-close-dialog>Cancelar</button><button type="submit" class="catalog-primary-button">Subir PDF y finalizar</button></div>
        </form>
    </dialog>
</div>
@endsection
