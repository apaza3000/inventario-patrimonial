<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\AlcanceDatosService;
use App\Services\XlsxService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReporteController extends Controller
{
    public function __construct(
        private readonly AlcanceDatosService $alcance,
        private readonly XlsxService $xlsx,
    ) {
    }

    public function inventarioPdf(Request $request): Response
    {
        return $this->pdf('inventario', $request->user('web'));
    }

    public function inventarioExcel(Request $request): Response
    {
        return $this->excel('inventario', $request->user('web'));
    }

    public function equiposPdf(Request $request): Response
    {
        return $this->pdf('equipos', $request->user('web'));
    }

    public function equiposExcel(Request $request): Response
    {
        return $this->excel('equipos', $request->user('web'));
    }

    public function mantenimientosPdf(Request $request): Response
    {
        return $this->pdf('mantenimientos', $request->user('web'));
    }

    public function mantenimientosExcel(Request $request): Response
    {
        return $this->excel('mantenimientos', $request->user('web'));
    }

    private function pdf(string $tipo, Usuario $usuario): Response
    {
        $datos = $this->datos($tipo, $usuario);

        return Pdf::loadView('pdf.reporte-tabular', [
            ...$datos,
            'alcance' => $this->descripcionAlcance($usuario),
            'generadoEn' => now(),
        ])->setPaper('a4', 'landscape')->download("reporte-$tipo.pdf");
    }

    private function excel(string $tipo, Usuario $usuario): Response
    {
        $datos = $this->datos($tipo, $usuario);
        $contenido = $this->xlsx->generar($datos['encabezados'], $datos['filas'], $datos['titulo']);

        return response($contenido, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"reporte-$tipo.xlsx\"",
            'Content-Length' => (string) strlen($contenido),
        ]);
    }

    private function datos(string $tipo, Usuario $usuario): array
    {
        return match ($tipo) {
            'inventario' => $this->datosInventario($usuario),
            'equipos' => $this->datosEquipos($usuario),
            'mantenimientos' => $this->datosMantenimientos($usuario),
        };
    }

    private function datosInventario(Usuario $usuario): array
    {
        $registros = $this->alcance->inventarios($usuario)
            ->with([
                'bien.ambiente.tipoAmbiente',
                'bien.ambiente.especialidad',
                'bien.estado',
                'bien.condicion',
            ])
            ->orderBy('bien_id')
            ->orderBy('anio')
            ->get();

        return [
            'titulo' => 'Inventario patrimonial',
            'encabezados' => ['Bien ID', 'CBI', 'Inventario', 'Año', 'Descripción', 'Ambiente', 'Tipo de ambiente', 'Especialidad', 'Estado', 'Condición', 'Activo'],
            'filas' => $registros->map(fn ($registro) => [
                $registro->bien_id,
                $registro->bien?->cbi,
                $registro->inventario,
                $registro->anio,
                $registro->bien?->descripcion,
                $registro->bien?->ambiente?->nombre,
                $registro->bien?->ambiente?->tipoAmbiente?->nombre,
                $registro->bien?->ambiente?->especialidad?->nombre,
                $registro->bien?->estado?->nombre,
                $registro->bien?->condicion?->nombre,
                $registro->bien?->activo ? 'Sí' : 'No',
            ])->all(),
        ];
    }

    private function datosEquipos(Usuario $usuario): array
    {
        $registros = $this->alcance->equipos($usuario)
            ->with(['bien.ambiente.especialidad', 'tipoEquipo', 'marca'])
            ->orderBy('bien_id')
            ->get();

        return [
            'titulo' => 'Equipos patrimoniales',
            'encabezados' => ['Bien ID', 'CBI', 'Descripción', 'Tipo', 'Marca', 'Modelo', 'Serie', 'Ambiente', 'Especialidad', 'Activo'],
            'filas' => $registros->map(fn ($equipo) => [
                $equipo->bien_id,
                $equipo->bien?->cbi,
                $equipo->bien?->descripcion,
                $equipo->tipoEquipo?->nombre,
                $equipo->marca?->nombre,
                $equipo->modelo,
                $equipo->serie,
                $equipo->bien?->ambiente?->nombre,
                $equipo->bien?->ambiente?->especialidad?->nombre,
                $equipo->bien?->activo ? 'Sí' : 'No',
            ])->all(),
        ];
    }

    private function datosMantenimientos(Usuario $usuario): array
    {
        $registros = $this->alcance->mantenimientos($usuario)
            ->with(['bien.ambiente.especialidad', 'tipoMantenimiento', 'tecnico'])
            ->orderBy('id')
            ->get();

        return [
            'titulo' => 'Mantenimientos de equipos',
            'encabezados' => ['ID', 'Bien ID', 'CBI', 'Equipo', 'Tipo', 'Fecha', 'Técnico', 'Diagnóstico', 'Trabajo realizado', 'Observaciones', 'Ambiente', 'Especialidad'],
            'filas' => $registros->map(fn ($mantenimiento) => [
                $mantenimiento->id,
                $mantenimiento->bien_id,
                $mantenimiento->bien?->cbi,
                $mantenimiento->bien?->descripcion,
                $mantenimiento->tipoMantenimiento?->nombre,
                $mantenimiento->fecha_mantenimiento?->format('Y-m-d'),
                $mantenimiento->tecnico
                    ? trim($mantenimiento->tecnico->nombres.' '.$mantenimiento->tecnico->apellidos)
                    : null,
                $mantenimiento->diagnostico,
                $mantenimiento->trabajo_realizado,
                $mantenimiento->observaciones,
                $mantenimiento->bien?->ambiente?->nombre,
                $mantenimiento->bien?->ambiente?->especialidad?->nombre,
            ])->all(),
        ];
    }

    private function descripcionAlcance(Usuario $usuario): string
    {
        return match ($usuario->rol?->nombre) {
            'asistente' => 'Inventario de laboratorios',
            'coordinador' => 'Especialidad: '.($usuario->especialidad?->nombre ?? 'sin asignar'),
            default => 'Información completa',
        };
    }
}
