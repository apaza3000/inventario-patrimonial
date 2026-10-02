@extends('layouts.app')

@section('title', 'Dashboard | Sistema de Inventario')

@section('content')

<div class="dashboard-page">
    <nav class="breadcrumb" aria-label="Ruta de navegación">
        <span>Inicio</span>
        <span class="breadcrumb-separator" aria-hidden="true">›</span>
        <span aria-current="page">Dashboard general</span>
    </nav>

    <div class="dashboard-card">
        <header class="dashboard-intro">
            <h1>¡Bienvenido(a), {{ auth('web')->user()->nombres }}!</h1>
        </header>

        @if ($resumen !== [])
        <section class="dashboard-section" aria-labelledby="summary-title">
            <h2 id="summary-title" class="section-title">Resumen de activos</h2>

            <div class="summary-grid">
                @if ($puedeConsultarBienes)
                <article class="summary-box">
                    <span>Total de bienes</span>
                    <strong>{{ $resumen['total'] }}</strong>
                </article>
                <article class="summary-box">
                    <span>Operativos</span>
                    <strong>{{ $resumen['operativos'] }}</strong>
                </article>
                @endif
                @if (array_key_exists('mantenimientos', $resumen))
                <article class="summary-box">
                    <span>Registros de mantenimiento{{ $mantenimientoPorEspecialidad ? ' de tu especialidad' : '' }}</span>
                    <strong>{{ $resumen['mantenimientos'] }}</strong>
                </article>
                @endif
                @if ($puedeConsultarBienes)
                <article class="summary-box">
                    <span>Ausentes</span>
                    <strong>{{ $resumen['ausentes'] }}</strong>
                </article>
                @endif
            </div>
        </section>
        @endif

        @if ($puedeConsultarBienes || $puedeRegistrarMovimiento || $puedeConsultarInventario)
        <section class="dashboard-section" aria-labelledby="actions-title">
            <h2 id="actions-title" class="section-title">Accesos rápidos</h2>

            <div class="quick-actions">
                @if ($puedeConsultarBienes)
                    <a href="{{ route('inventario.bienes') }}">Registrar nuevo bien</a>
                @endif
                @if ($puedeRegistrarMovimiento)
                    <a href="{{ route('movimientos.index') }}">Registrar movimiento</a>
                @endif
                @if ($puedeConsultarInventario)
                    <a href="{{ route('inventario.toma-inventario') }}">Toma de inventario</a>
                @endif
            </div>
        </section>
        @endif

        @if ($puedeConsultarBienes || $puedeConsultarMovimientos)
        <div class="dashboard-bottom">
            @if ($puedeConsultarBienes)
            <section class="dashboard-panel" aria-labelledby="distribution-title">
                <h2 id="distribution-title">Distribución por ambiente</h2>
                <ul class="dashboard-list">
                    @foreach ($distribucion as $ambiente)
                        <li>{{ $ambiente['nombre'] }}{{ $ambiente['sede'] ? ' · ' . $ambiente['sede'] : '' }}: <strong>{{ $ambiente['total'] }} {{ $ambiente['total'] === 1 ? 'bien' : 'bienes' }}</strong></li>
                    @endforeach
                </ul>
                @if ($hayMasAmbientes)
                    <a class="dashboard-more-link" href="{{ route('inventario.bienes') }}">Ver todos los ambientes</a>
                @endif
            </section>
            @endif

            @if ($puedeConsultarMovimientos)
            <section class="dashboard-panel" aria-labelledby="activity-title">
                <h2 id="activity-title">Últimos movimientos</h2>
                @if ($movimientos->isEmpty())
                    <p class="dashboard-empty">No hay movimientos registrados.</p>
                @else
                    <ul class="dashboard-list">
                        @foreach ($movimientos as $movimiento)
                            <li>
                                <strong>#{{ $movimiento->id }} · {{ $movimiento->fecha_movimiento->format('d/m/Y H:i') }}</strong><br>
                                {{ $movimiento->bien?->cbi ?? '—' }} · {{ $movimiento->bien?->descripcion ?? '—' }}<br>
                                {{ $movimiento->ambienteOrigen?->nombre ?? '—' }} → {{ $movimiento->ambienteDestino?->nombre ?? '—' }}<br>
                                {{ $movimiento->estado === \App\Models\Movimiento::ESTADO_FINALIZADO ? 'Finalizado' : 'Pendiente de firma' }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
            @endif
        </div>
        @endif
    </div>
</div>

@endsection
