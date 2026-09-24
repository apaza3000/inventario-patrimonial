@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

<div class="dashboard-page">

    <div class="breadcrumb">
        INICIO <span>›</span> DASHBOARD GENERAL
    </div>

    <div class="dashboard-card">

        <h2>¡Bienvenido(a), Administrador!</h2>

        <hr>

        <h3>RESUMEN DE ACTIVOS</h3>

        <div class="summary-grid">

            <div class="summary-box">
                <span>TOTAL DE BIENES</span>
                <strong>464</strong>
            </div>

            <div class="summary-box">
                <span>OPERATIVOS</span>
                <strong>---</strong>
            </div>

            <div class="summary-box">
                <span>MANTENIMIENTO</span>
                <strong>---</strong>
            </div>

            <div class="summary-box">
                <span>FALTANTES</span>
                <strong>---</strong>
            </div>

        </div>

        <h3 class="section-title">ACCESOS RÁPIDOS</h3>

        <div class="quick-actions">

            <button>
                + Registrar Nuevo Bien
            </button>

            <button>
                🔧 Nueva Incidencia
            </button>

            <button>
                📋 Toma de Inventario
            </button>

        </div>

        <div class="dashboard-bottom">

            <div class="dashboard-panel">
                <h3>DISTRIBUCIÓN POR AMBIENTE</h3>

                <p>• Lab 01 - Informática 01</p>
                <p>• Lab 02 - Diseño UI/UX</p>
                <p>• Lab 03 - Programación</p>
            </div>

            <div class="dashboard-panel">
                <h3>ÚLTIMAS ACTIVIDADES Y MOVIMIENTOS</h3>

                <p>Sin movimientos recientes</p>
            </div>

        </div>

    </div>

</div>

@endsection