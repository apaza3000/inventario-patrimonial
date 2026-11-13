@extends('layouts.app')
@section('title', 'Reportes | Sistema de Inventario')
@section('content')
@php
    $user = auth('web')->user();
    $role = $user->rol?->nombre;
    $reports = [
        'inventario' => ['Inventario patrimonial', 'Registros de inventario con año, identificación del bien, ubicación, estado, condición y activo.'],
    ];
    if ($role !== 'asistente') {
        $reports['equipos'] = ['Equipos patrimoniales', 'Equipos con CBI, descripción, tipo, marca, modelo, serie y características técnicas actuales, cuando estén registradas.'];
        $reports['mantenimientos'] = ['Mantenimientos', 'Registros con identificación del bien, tipo, fecha, técnico, diagnóstico, trabajo realizado, observaciones y ubicación.'];
    }
@endphp
<div class="catalog-page" data-reports-root data-login-url="{{ route('login') }}">
    <nav class="breadcrumb" aria-label="Ruta de navegación"><span>Reportes</span><span class="breadcrumb-separator" aria-hidden="true">›</span><span aria-current="page">Descarga de reportes</span></nav>
    <div class="catalog-card">
        <header class="catalog-header">
            <div><p class="catalog-eyebrow">Información patrimonial</p><h1>Descarga de reportes</h1><p class="catalog-subtitle">Consulta y exporta la información disponible para tu usuario.</p></div>
        </header>
        <div class="reports-scope">
            <h2>Alcance de las descargas</h2>
            <p class="catalog-subtitle">
                @if ($role === 'asistente')
                    Inventarios de bienes ubicados actualmente en ambientes de tipo LABORATORIO.
                @elseif ($role === 'coordinador')
                    Equipos, sus inventarios y mantenimientos, ubicados actualmente en ambientes de tu especialidad: {{ $user->especialidad?->nombre ?? 'sin asignar' }}.
                @else
                    Información completa de inventarios, equipos y mantenimientos.
                @endif
            </p>
            @if ($role === 'coordinador' && $user->especialidad_id === null)
                <p class="catalog-message">No tienes una especialidad asignada. Los reportes no incluirán registros.</p>
            @endif
            <p class="catalog-subtitle">Cada descarga incluye todos los registros permitidos por tu alcance que coincidan con los filtros, no solamente una página de datos. Para descargar todo, selecciona explícitamente Reporte completo.</p>
        </div>
        <div class="reports-grid">
            @foreach ($reports as $type => [$title, $description])
                <section class="reports-card" aria-labelledby="report-{{ $type }}-title" data-report-card="{{ $type }}" data-options-url="{{ url('/api/reportes/'.$type.'/opciones') }}" data-summary-url="{{ url('/api/reportes/'.$type.'/resumen') }}">
                    <div class="reports-card-heading"><span class="reports-format-label">PDF · Excel</span><h2 id="report-{{ $type }}-title">{{ $title }}</h2></div>
                    <p class="catalog-subtitle">{{ $description }}</p>
                    <details class="reports-filter-panel" data-report-panel>
                        <summary>Seleccionar filtros y consultar coincidencias</summary>
                        <form data-report-form>
                            <fieldset class="reports-filter-fields catalog-filter-grid" data-report-filters disabled aria-label="Filtros de {{ $title }}">
                                <label>CBI o descripción<input type="search" name="q" maxlength="150" placeholder="Coincidencias parciales; conserva los ceros iniciales"></label>
                                <label>Sede<select name="sede_id"><option value="">Todas las sedes</option></select></label>
                                <label>Ambiente<select name="ambiente_id"><option value="">Todos los ambientes</option></select></label>
                                <label>Especialidad<select name="especialidad_id"><option value="">Todas las especialidades</option></select></label>
                                <label>Categoría<select name="categoria"><option value="">Todas las categorías</option><option value="computadoras">Computadoras</option><option value="monitores">Monitores</option>@if ($type !== 'equipos')<option value="mobiliario">Mobiliario</option>@endif<option value="otros-equipos">Otros equipos</option></select></label>
                                <label>Tipo de equipo<select name="tipo_equipo_id"><option value="">Todos los tipos</option></select></label>
                                <label>Estado del bien<select name="estado_id"><option value="">Todos los estados</option></select></label>
                                <label>Condición del bien<select name="condicion_id"><option value="">Todas las condiciones</option></select></label>
                                @if ($type === 'inventario')
                                    <label>Año de inventario<select name="anio"><option value="">Todos los años</option></select></label>
                                @elseif ($type === 'mantenimientos')
                                    <label>Desde<input type="date" name="desde"></label><label>Hasta<input type="date" name="hasta"></label>
                                    <label>Tipo de mantenimiento<select name="tipo_mantenimiento_id"><option value="">Todos los tipos</option></select></label>
                                @endif
                            </fieldset>
                            <label class="reports-complete"><input type="checkbox" data-report-complete> Reporte completo: todos los registros permitidos por mi alcance</label>
                            <div class="inventory-export-actions"><button type="submit" class="catalog-secondary-button" data-report-count disabled>Consultar coincidencias</button><button type="button" class="catalog-secondary-button" data-report-clear>Limpiar filtros</button></div>
                        </form>
                    </details>
                    <button type="button" class="catalog-retry-button" data-report-options-retry hidden>Reintentar carga de filtros</button>
                    <p class="catalog-search-summary" data-report-total role="status" aria-live="polite">Selecciona filtros o solicita el reporte completo y consulta las coincidencias antes de descargar.</p>
                    <div class="inventory-export-actions">
                        <button type="button" class="catalog-primary-button" aria-label="Descargar PDF de {{ $title }}" data-report-url="{{ url('/api/reportes/'.$type.'/pdf') }}" data-report-filename="reporte-{{ $type }}.pdf" disabled>Descargar PDF</button>
                        <button type="button" class="catalog-secondary-button" aria-label="Descargar Excel de {{ $title }}" data-report-url="{{ url('/api/reportes/'.$type.'/excel') }}" data-report-filename="reporte-{{ $type }}.xlsx" disabled>Descargar Excel</button>
                    </div>
                    <p class="catalog-message reports-download-status" data-report-status role="status" aria-live="polite" aria-atomic="true"></p>
                </section>
            @endforeach
        </div>
    </div>
</div>
@endsection
