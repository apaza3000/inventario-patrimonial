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
            <h1>¡Bienvenido(a), Administrador!</h1>
        </header>

        <section class="dashboard-section" aria-labelledby="summary-title">
            <h2 id="summary-title" class="section-title">Resumen de activos</h2>

            <div class="summary-grid">
                <article class="summary-box">
                    <span>Total de bienes</span>
                    <strong>464</strong>
                </article>
                <article class="summary-box">
                    <span>Operativos</span>
                    <strong>—</strong>
                </article>
                <article class="summary-box">
                    <span>Mantenimiento</span>
                    <strong>—</strong>
                </article>
                <article class="summary-box">
                    <span>Faltantes</span>
                    <strong>—</strong>
                </article>
            </div>
        </section>

        <section class="dashboard-section" aria-labelledby="actions-title">
            <h2 id="actions-title" class="section-title">Accesos rápidos</h2>

            <div class="quick-actions">
                <button type="button" disabled>Registrar nuevo bien</button>
                <button type="button" disabled>Nueva incidencia</button>
                <button type="button" disabled>Toma de inventario</button>
            </div>
        </section>

        <div class="dashboard-bottom">
            <section class="dashboard-panel" aria-labelledby="distribution-title">
                <h2 id="distribution-title">Distribución por ambiente</h2>
                <ul class="dashboard-list">
                    <li>Lab 01 · Informática 01</li>
                    <li>Lab 02 · Diseño UI/UX</li>
                    <li>Lab 03 · Programación</li>
                </ul>
            </section>

            <section class="dashboard-panel" aria-labelledby="activity-title">
                <h2 id="activity-title">Últimas actividades y movimientos</h2>
                <p class="dashboard-empty">Sin movimientos recientes</p>
            </section>
        </div>
    </div>
</div>

@endsection
