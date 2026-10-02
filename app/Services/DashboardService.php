<?php

namespace App\Services;

use App\Models\Ambiente;
use App\Models\Bien;
use App\Models\Movimiento;
use App\Models\Usuario;

class DashboardService
{
    public function __construct(private readonly AlcanceDatosService $alcance)
    {
    }

    public function datos(Usuario $usuario): array
    {
        $completo = $this->alcance->tieneAccesoCompleto($usuario);
        $rol = $usuario->rol?->nombre;
        $puedeRegistrarMovimiento = $completo || $rol === 'asistente';
        $resumen = [];
        $distribucion = collect();
        $hayMasAmbientes = false;
        $movimientos = collect();

        if ($completo) {
            $resumen['total'] = Bien::count();
            $resumen['operativos'] = Bien::whereHas('condicion', fn ($query) => $query->where('nombre', 'OPERATIVO'))->count();
            $resumen['ausentes'] = Bien::whereHas('condicion', fn ($query) => $query->where('nombre', 'AUSENTE'))->count();

            $distribucion = Ambiente::with('sede:id,nombre')
                ->whereHas('bienes')
                ->withCount('bienes')
                ->get()
                ->map(fn ($ambiente) => [
                    'id' => $ambiente->id,
                    'nombre' => $ambiente->nombre,
                    'sede' => $ambiente->sede?->nombre,
                    'total' => $ambiente->bienes_count,
                ]);
            $sinAmbiente = Bien::whereNull('ambiente_id')->count();
            if ($sinAmbiente > 0) {
                $distribucion->push([
                    'id' => null,
                    'nombre' => 'Sin ambiente asignado',
                    'sede' => null,
                    'total' => $sinAmbiente,
                ]);
            }
            $distribucion = $distribucion->sort(fn ($a, $b) =>
                ($b['total'] <=> $a['total']) ?: strcmp($a['nombre'], $b['nombre'])
            )->values();
            $hayMasAmbientes = $distribucion->count() > 5;
            $distribucion = $distribucion->take(5);

            $movimientos = Movimiento::with(['bien', 'ambienteOrigen', 'ambienteDestino'])
                ->orderByDesc('fecha_movimiento')
                ->orderByDesc('id')
                ->limit(5)
                ->get();
        }

        if ($completo || $rol === 'coordinador') {
            $resumen['mantenimientos'] = $this->alcance->mantenimientos($usuario)->count();
        }

        return [
            'resumen' => $resumen,
            'distribucion' => $distribucion,
            'hayMasAmbientes' => $hayMasAmbientes,
            'movimientos' => $movimientos,
            'puedeConsultarBienes' => $completo,
            'puedeConsultarMovimientos' => $completo,
            'puedeRegistrarMovimiento' => $puedeRegistrarMovimiento,
            'puedeConsultarInventario' => $completo || $rol === 'asistente',
            'mantenimientoPorEspecialidad' => $rol === 'coordinador',
        ];
    }
}
