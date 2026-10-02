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
        $reports['equipos'] = ['Equipos patrimoniales', 'Equipos registrados con CBI, descripción, tipo, marca, modelo, serie, ubicación y activo.'];
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
            <p class="catalog-subtitle">Cada descarga incluye todos los registros permitidos por tu alcance, no solamente una página de datos.</p>
        </div>
        <div class="reports-grid">
            @foreach ($reports as $type => [$title, $description])
                <section class="reports-card" aria-labelledby="report-{{ $type }}-title" data-report-card>
                    <div class="reports-card-heading"><span class="reports-format-label">PDF · Excel</span><h2 id="report-{{ $type }}-title">{{ $title }}</h2></div>
                    <p class="catalog-subtitle">{{ $description }}</p>
                    <div class="inventory-export-actions">
                        <button type="button" class="catalog-primary-button" aria-label="Descargar PDF de {{ $title }}" data-report-url="{{ url('/api/reportes/'.$type.'/pdf') }}" data-report-filename="reporte-{{ $type }}.pdf">Descargar PDF</button>
                        <button type="button" class="catalog-secondary-button" aria-label="Descargar Excel de {{ $title }}" data-report-url="{{ url('/api/reportes/'.$type.'/excel') }}" data-report-filename="reporte-{{ $type }}.xlsx">Descargar Excel</button>
                    </div>
                    <p class="catalog-message reports-download-status" data-report-status role="status" aria-live="polite" aria-atomic="true"></p>
                </section>
            @endforeach
        </div>
    </div>
</div>
@endsection
