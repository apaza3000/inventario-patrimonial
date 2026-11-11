<p class="catalog-form-message" data-form-message role="alert" aria-live="polite"></p>
<button type="button" class="catalog-retry-button" data-form-retry hidden>Reintentar carga del formulario</button>
<fieldset class="maintenance-picker">
    <legend><span class="maintenance-field-label">Bien <span aria-hidden="true">*</span></span></legend>
    <p class="catalog-subtitle">Busca el CBI completo en todos los bienes autorizados y selecciona la coincidencia. Se conservan los ceros iniciales.</p>
    <div class="catalog-form-grid">
        <label>CBI completo<input type="search" maxlength="25" data-bien-search placeholder="Ejemplo: 0243831" aria-describedby="maintenance-bien-message"></label>
        <div class="maintenance-search-action"><button type="button" class="catalog-secondary-button" data-bien-search-button>Buscar por CBI</button></div>
        <label class="catalog-form-wide"><span class="maintenance-field-label">Seleccionar bien <span aria-hidden="true">*</span></span><select data-bien-options required disabled><option value="">Selecciona un bien</option></select></label>
    </div>
    <p id="maintenance-bien-message" class="catalog-message" data-bien-message role="status" aria-live="polite"></p>
    <p class="stations-selection" data-selected-bien aria-live="polite">Ningún bien seleccionado.</p>
    <section class="maintenance-bien-card" data-bien-card hidden aria-labelledby="maintenance-bien-title">
        <h3 id="maintenance-bien-title">Datos y características del bien seleccionado</h3>
        <dl class="catalog-detail-grid" data-bien-technical-fields></dl>
    </section>
</fieldset>
<fieldset class="maintenance-picker">
    <legend>Técnico</legend>
    <p class="catalog-subtitle">Selecciona un usuario existente o deja Sin asignar. La selección se conserva al cambiar de página.</p>
    <div class="catalog-form-grid">
        <label>Nombre o apellido en esta página<input type="search" data-user-search placeholder="Buscar en esta página" disabled></label>
        <label>Seleccionar técnico<select name="tecnico_id" data-user-options disabled><option value="">Sin asignar</option></select></label>
    </div>
    <p class="catalog-message" data-user-message role="status" aria-live="polite"></p><p class="catalog-search-summary" data-user-summary aria-live="polite"></p>
    <button type="button" class="catalog-retry-button" data-user-retry hidden>Reintentar carga</button>
    <div class="catalog-pagination" aria-label="Paginación de usuarios"><span data-user-page>Página —</span><div class="catalog-pagination-actions"><button type="button" data-user-previous disabled>Anterior</button><button type="button" data-user-next disabled>Siguiente</button></div></div>
    <p class="stations-selection" data-selected-user aria-live="polite"></p>
</fieldset>
<fieldset class="maintenance-picker" data-condition-section hidden>
    <legend>Condición del bien</legend>
    <p class="stations-selection" data-current-condition aria-live="polite">Selecciona un bien para consultar su condición actual.</p>
    <div class="catalog-form-grid"><label>Cambiar condición (opcional)<select name="condicion_id" disabled><option value="">Mantener la condición actual</option></select></label></div>
    <p class="catalog-subtitle">Si eliges otra condición, se aplicará al bien al guardar este mantenimiento.</p>
    <p class="catalog-message" data-condition-message role="status" aria-live="polite"></p>
    <button type="button" class="catalog-retry-button" data-condition-retry hidden>Reintentar carga de condiciones</button>
</fieldset>
<div class="catalog-form-grid">
    <label><span class="maintenance-field-label">Tipo de mantenimiento <span aria-hidden="true">*</span></span><select name="tipo_mantenimiento_id" required disabled><option value="">Selecciona un tipo</option></select></label>
    <label><span class="maintenance-field-label">Fecha de mantenimiento <span aria-hidden="true">*</span></span><input name="fecha_mantenimiento" type="date" required></label>
    <label class="catalog-form-wide"><span class="maintenance-field-label">Descripción del mantenimiento <span aria-hidden="true">*</span></span><textarea name="descripcion" rows="3" maxlength="255" required></textarea></label>
    <label class="catalog-form-wide">Diagnóstico<textarea name="diagnostico" rows="3" maxlength="255"></textarea></label>
    <label class="catalog-form-wide">Trabajo realizado<textarea name="trabajo_realizado" rows="3" maxlength="255"></textarea></label>
    <label class="catalog-form-wide">Observaciones<textarea name="observaciones" rows="3" maxlength="255"></textarea></label>
</div>
<div class="catalog-form-actions">
    @if ($register)
        <a href="{{ route('mantenimientos.index') }}" class="catalog-secondary-button" data-cancel-registration>Cancelar</a>
    @else
        <button type="button" class="catalog-secondary-button" data-close-dialog>Cancelar</button>
    @endif
    <button type="submit" class="catalog-primary-button" data-save-maintenance disabled>{{ $register ? 'Guardar mantenimiento' : 'Guardar cambios' }}</button>
</div>
