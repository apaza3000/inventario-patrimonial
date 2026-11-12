<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\Bien;
use App\Models\Inventario;
use App\Models\Mantenimiento;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;

class AlcanceDatosService
{
    private const ROLES_ACCESO_COMPLETO = ['superadmin', 'administrador', 'director'];

    public function equipos(Usuario $usuario): Builder
    {
        $query = Equipo::query();

        if ($this->tieneAccesoCompleto($usuario)) {
            return $query;
        }

        if ($this->rol($usuario) !== 'coordinador' || $usuario->especialidad_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('bien.ambiente', function (Builder $query) use ($usuario) {
            $query->where('especialidad_id', $usuario->especialidad_id);
        });
    }

    public function mantenimientos(Usuario $usuario): Builder
    {
        $query = Mantenimiento::query();

        if ($this->tieneAccesoCompleto($usuario)) {
            return $query;
        }

        if ($this->rol($usuario) !== 'coordinador' || $usuario->especialidad_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereHas('bien.equipo')
            ->whereHas('bien.ambiente', function (Builder $query) use ($usuario) {
                $query->where('especialidad_id', $usuario->especialidad_id);
            });
    }

    public function inventarios(Usuario $usuario): Builder
    {
        $query = Inventario::query();
        $rol = $this->rol($usuario);

        if ($this->tieneAccesoCompleto($usuario)) {
            return $query;
        }

        if ($rol === 'asistente') {
            return $query->whereIn('bien_id', $this->bienesInventario($usuario)->select('bienes.id'));
        }

        if ($rol === 'coordinador' && $usuario->especialidad_id !== null) {
            return $query
                ->whereHas('bien.equipo')
                ->whereHas('bien.ambiente', function (Builder $query) use ($usuario) {
                    $query->where('especialidad_id', $usuario->especialidad_id);
                });
        }

        return $query->whereRaw('1 = 0');
    }

    public function bienesInventario(Usuario $usuario): Builder
    {
        $query = Bien::query();

        if ($this->tieneAccesoCompleto($usuario)) {
            return $query;
        }

        if ($this->rol($usuario) === 'asistente') {
            return $query->whereHas('ambiente.tipoAmbiente', fn (Builder $query) => $query->where('nombre', 'LABORATORIO'));
        }

        return $query->whereRaw('1 = 0');
    }

    public function bienesConsulta(Usuario $usuario): Builder
    {
        if ($this->tieneAccesoCompleto($usuario)) {
            return Bien::query();
        }

        if ($this->rol($usuario) === 'coordinador') {
            return Bien::whereIn('id', $this->equipos($usuario)->select('equipos.bien_id'));
        }

        return $this->bienesInventario($usuario);
    }

    public function tieneAccesoCompleto(Usuario $usuario): bool
    {
        return in_array($this->rol($usuario), self::ROLES_ACCESO_COMPLETO, true);
    }

    private function rol(Usuario $usuario): ?string
    {
        return $usuario->rol?->nombre;
    }
}
