    <dialog class="catalog-dialog catalog-detail-dialog" data-bien-detail-dialog aria-labelledby="{{ $detailPrefix }}-title">
        <div class="catalog-detail-content">
            <div class="catalog-dialog-heading">
                <div>
                    <p class="catalog-eyebrow">{{ $detailContext ?? 'Catálogo de Bienes' }}</p>
                    <h2 id="{{ $detailPrefix }}-title">Detalle del bien</h2>
                </div>
                <button type="button" class="catalog-dialog-close" data-close-bien-detail aria-label="Cerrar">×</button>
            </div>

            <p class="catalog-message" data-bien-detail-message role="status" aria-live="polite"></p>
            <div class="catalog-detail-sections" data-bien-detail-fields hidden>
                <section class="catalog-detail-section" aria-labelledby="{{ $detailPrefix }}-main-title">
                    <h3 id="{{ $detailPrefix }}-main-title">Datos principales</h3>
                    <dl class="catalog-detail-grid">
                        <div><dt>ID</dt><dd data-detail-id></dd></div>
                        <div><dt>CBI</dt><dd data-detail-cbi></dd></div>
                        <div class="catalog-detail-wide"><dt>Descripción</dt><dd data-detail-descripcion></dd></div>
                        <div class="catalog-detail-wide"><dt>Fecha de registro</dt><dd data-detail-fecha></dd></div>
                    </dl>
                </section>

                <section class="catalog-detail-section" aria-labelledby="{{ $detailPrefix }}-status-title">
                    <h3 id="{{ $detailPrefix }}-status-title">Estado, condición y ubicación</h3>
                    <dl class="catalog-detail-grid">
                        <div><dt>Estado</dt><dd data-detail-estado></dd></div>
                        <div><dt>Condición</dt><dd data-detail-condicion></dd></div>
                        <div><dt>Sede</dt><dd data-detail-sede></dd></div>
                        <div><dt>Ambiente</dt><dd data-detail-ambiente></dd></div>
                        <div class="catalog-detail-wide"><dt>Activo</dt><dd data-detail-activo></dd></div>
                    </dl>
                </section>

                <section class="catalog-detail-section" aria-labelledby="{{ $detailPrefix }}-technical-title">
                    <h3 id="{{ $detailPrefix }}-technical-title">Información técnica disponible</h3>
                    <p class="catalog-detail-empty" data-technical-empty hidden>No hay información técnica disponible.</p>
                    <dl class="catalog-detail-grid" data-technical-fields></dl>
                </section>

                <section class="catalog-detail-section" aria-labelledby="{{ $detailPrefix }}-observations-title">
                    <h3 id="{{ $detailPrefix }}-observations-title">Observaciones</h3>
                    <p class="catalog-detail-observations" data-detail-observaciones></p>
                </section>
            </div>
            <form method="dialog" class="catalog-detail-actions">
                <button type="submit" class="catalog-secondary-button">Cerrar</button>
            </form>
        </div>
    </dialog>
