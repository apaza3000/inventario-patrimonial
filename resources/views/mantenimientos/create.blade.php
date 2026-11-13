@extends('layouts.app')
@section('title', 'Registrar mantenimiento | Sistema de Inventario')
@section('content')
<div class="catalog-page" data-maintenance-root data-maintenance-url="{{ url('/api/mantenimientos') }}"
    data-login-url="{{ route('login') }}" data-list-url="{{ route('mantenimientos.index') }}"
    data-types-url="{{ url('/api/tipos-mantenimiento') }}" data-bien-search-url="{{ url('/api/busqueda/bienes') }}" data-bienes-url="{{ url('/api/bienes') }}"
    data-users-url="{{ url('/api/usuarios') }}" data-csrf-url="{{ url('/sanctum/csrf-cookie') }}"
    data-conditions-url="{{ url('/api/condiciones-bien') }}">
    <nav class="breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('mantenimientos.index') }}" class="maintenance-breadcrumb-link">Mantenimiento</a><span class="breadcrumb-separator" aria-hidden="true">›</span><span aria-current="page">Registrar mantenimiento</span></nav>
    <div class="catalog-card" data-maintenance-form-page>
        <header class="catalog-header"><div><p class="catalog-eyebrow">Mantenimiento patrimonial</p><h1 id="maintenance-form-title">Registrar mantenimiento</h1><p class="catalog-subtitle">Selecciona un bien y registra el trabajo realizado.</p></div></header>
        <form class="catalog-form" data-maintenance-form>
            @include('mantenimientos._form', ['register' => true])
        </form>
    </div>
</div>
@endsection
