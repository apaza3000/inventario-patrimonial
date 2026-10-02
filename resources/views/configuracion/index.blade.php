@extends('layouts.app')
@section('title', 'Configuración | Sistema de Inventario')
@section('content')
<div class="catalog-page" data-config-root data-api-url="{{ url('/api') }}" data-csrf-url="{{ url('/sanctum/csrf-cookie') }}" data-login-url="{{ route('login') }}" data-user-id="{{ auth('web')->id() }}">
    <nav class="breadcrumb" aria-label="Ruta de navegación"><span>Configuración</span><span class="breadcrumb-separator" aria-hidden="true">›</span><span aria-current="page">Administración del sistema</span></nav>
    <div class="catalog-card">
        <header class="catalog-header"><div><p class="catalog-eyebrow">Administración</p><h1>Configuración del sistema</h1><p class="catalog-subtitle">Gestiona usuarios, ubicación y catálogos. Abre una sección para consultar sus datos.</p></div></header>
        @foreach ([
            'Usuarios y roles' => ['usuarios' => 'Usuarios', 'roles' => 'Roles'],
            'Ubicación y organización' => ['sedes' => 'Sedes', 'ambientes' => 'Ambientes', 'especialidades' => 'Especialidades', 'tipos-ambiente' => 'Tipos de ambiente'],
            'Catálogos del sistema' => ['estados-bien' => 'Estados del bien', 'condiciones-bien' => 'Condiciones del bien', 'tipos-mantenimiento' => 'Tipos de mantenimiento'],
        ] as $group => $modules)
            <section class="config-group" aria-label="{{ $group }}"><h2>{{ $group }}</h2><div class="config-module-grid">
                @foreach ($modules as $key => $label)
                    <button type="button" class="config-module-button" data-config-module="{{ $key }}" aria-pressed="false" aria-controls="config-workspace"><span>{{ $label }}</span><small>{{ $key === 'tipos-mantenimiento' ? 'Solo consulta' : 'Gestionar registros' }}</small></button>
                @endforeach
            </div></section>
        @endforeach
        <section id="config-workspace" class="catalog-results" aria-labelledby="config-module-title" data-workspace hidden>
            <div class="catalog-section-heading"><h2 id="config-module-title" tabindex="-1"></h2><button type="button" class="catalog-primary-button" data-create>Registrar</button></div>
            <p class="catalog-subtitle" data-module-note></p>
            <label class="config-search">Buscar registros<input type="search" data-search placeholder="Buscar" disabled></label>
            <p class="catalog-search-summary" data-search-summary aria-live="polite"></p>
            <p class="catalog-message" data-list-message role="status" aria-live="polite"></p><button type="button" class="catalog-retry-button" data-list-retry hidden>Reintentar carga</button>
            <div class="catalog-table-container"><table class="catalog-table config-table"><thead data-head></thead><tbody data-rows></tbody></table></div>
            <div class="catalog-pagination" data-pagination><span data-page></span><div class="catalog-pagination-actions"><button type="button" data-previous>Anterior</button><button type="button" data-next>Siguiente</button></div></div>
        </section>
    </div>
    <dialog class="catalog-dialog" data-detail-dialog aria-labelledby="config-detail-title"><div class="catalog-dialog-header"><div><p class="catalog-eyebrow">Configuración</p><h2 id="config-detail-title">Detalle del registro</h2></div><button type="button" class="catalog-dialog-close" data-close aria-label="Cerrar">×</button></div><p class="catalog-message" data-detail-message role="status" aria-live="polite"></p><button type="button" class="catalog-retry-button" data-detail-retry hidden>Reintentar detalle</button><dl class="catalog-detail-grid config-detail" data-detail-fields></dl><div class="catalog-form-actions"><button type="button" class="catalog-secondary-button" data-close>Cerrar</button></div></dialog>
    <dialog class="catalog-dialog" data-form-dialog aria-labelledby="config-form-title"><form data-form><div class="catalog-dialog-header"><div><p class="catalog-eyebrow">Configuración</p><h2 id="config-form-title"></h2></div><button type="button" class="catalog-dialog-close" data-close aria-label="Cerrar">×</button></div><p class="catalog-message" data-form-message role="alert"></p><button type="button" class="catalog-retry-button" data-form-retry hidden>Reintentar carga del formulario</button><div class="catalog-form-grid" data-form-fields></div><div class="catalog-form-actions"><button type="button" class="catalog-secondary-button" data-close>Cancelar</button><button type="submit" class="catalog-primary-button" data-save>Guardar</button></div></form></dialog>
    <dialog class="catalog-dialog" data-confirm-dialog aria-labelledby="config-confirm-title"><form data-confirm-form><div class="catalog-dialog-header"><h2 id="config-confirm-title"></h2><button type="button" class="catalog-dialog-close" data-close aria-label="Cerrar">×</button></div><p class="stations-selection" data-confirm-identity></p><p class="catalog-subtitle" data-confirm-note></p><p class="catalog-message" data-confirm-message role="alert"></p><div class="catalog-form-actions"><button type="button" class="catalog-secondary-button" data-close>Cancelar</button><button type="submit" class="catalog-primary-button" data-confirm-submit>Confirmar</button></div></form></dialog>
</div>
@endsection
