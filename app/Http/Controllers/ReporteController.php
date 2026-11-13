<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\ReporteConsultaService;
use App\Services\XlsxService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ReporteController extends Controller
{
    public function __construct(
        private readonly ReporteConsultaService $consultas,
        private readonly XlsxService $xlsx,
    ) {
    }

    public function inventarioPdf(Request $request): Response
    {
        return $this->pdf('inventario', $request);
    }

    public function inventarioExcel(Request $request): Response
    {
        return $this->excel('inventario', $request);
    }

    public function equiposPdf(Request $request): Response
    {
        return $this->pdf('equipos', $request);
    }

    public function equiposExcel(Request $request): Response
    {
        return $this->excel('equipos', $request);
    }

    public function mantenimientosPdf(Request $request): Response
    {
        return $this->pdf('mantenimientos', $request);
    }

    public function mantenimientosExcel(Request $request): Response
    {
        return $this->excel('mantenimientos', $request);
    }

    public function opciones(Request $request, string $tipo): JsonResponse
    {
        return response()->json(['data' => $this->consultas->opciones($tipo, $request->user('web'))]);
    }

    public function resumen(Request $request, string $tipo): JsonResponse
    {
        $filtros = $this->consultas->validar($tipo, $request);
        return response()->json(['data' => ['total' => $this->consultas->consulta($tipo, $request->user('web'), $filtros)->count()]]);
    }

    private function pdf(string $tipo, Request $request): Response
    {
        $filtros = $this->consultas->validar($tipo, $request);
        $usuario = $request->user('web');
        $datos = $this->datos($tipo, $usuario, $filtros);
        $principales = match ($tipo) {
            'inventario' => ['CBI', 'Descripción', 'Inventario', 'Año', 'Ambiente'],
            'equipos' => ['CBI', 'Descripción', 'Tipo', 'Ambiente', 'Activo'],
            'mantenimientos' => ['ID', 'CBI', 'Equipo', 'Tipo', 'Fecha', 'Técnico'],
        };

        return Pdf::loadView('pdf.reporte-tabular', [
            ...$datos,
            'columnasPdf' => array_map(fn ($header) => array_search($header, $datos['encabezados'], true), $principales),
            'columnaDetalle' => array_search($tipo === 'mantenimientos' ? 'Equipo' : 'Descripción', $datos['encabezados'], true),
            'filtrosAplicados' => $this->consultas->descripcionFiltros($filtros),
            'alcance' => $this->descripcionAlcance($usuario),
            'generadoEn' => now(),
        ])->setPaper('a4', 'landscape')->download("reporte-$tipo.pdf");
    }

    private function excel(string $tipo, Request $request): Response
    {
        $filtros = $this->consultas->validar($tipo, $request);
        $datos = $this->datos($tipo, $request->user('web'), $filtros);
        $contenido = $this->xlsx->generar($datos['encabezados'], $datos['filas'], $datos['titulo']);

        return response($contenido, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"reporte-$tipo.xlsx\"",
            'Content-Length' => (string) strlen($contenido),
        ]);
    }

    private function datos(string $tipo, Usuario $usuario, array $filtros): array
    {
        $datos = match ($tipo) {
            'inventario' => $this->datosInventario($usuario, $filtros),
            'equipos' => $this->datosEquipos($usuario, $filtros),
            'mantenimientos' => $this->datosMantenimientos($usuario, $filtros),
        };
        if (! count($datos['filas'])) throw ValidationException::withMessages(['reporte' => ['No hay registros que coincidan con la consulta dentro de tu alcance. No se generó ningún archivo.']]);
        return $datos;
    }

    private function datosInventario(Usuario $usuario, array $filtros): array
    {
        $registros = $this->consultas->consulta('inventario', $usuario, $filtros)
            ->with([
                'bien.ambiente.sede',
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
            'encabezados' => ['Bien ID', 'CBI', 'Inventario', 'Año', 'Descripción', 'Ambiente', 'Tipo de ambiente', 'Especialidad', 'Estado', 'Condición', 'Activo', 'Sede'],
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
                $registro->bien?->ambiente?->sede?->nombre,
            ])->all(),
        ];
    }

    private function datosEquipos(Usuario $usuario, array $filtros): array
    {
        $registros = $this->consultas->consulta('equipos', $usuario, $filtros)
            ->with(['bien.ambiente.especialidad', 'bien.ambiente.sede', 'bien.estado', 'bien.condicion', 'tipoEquipo', 'marca', 'equipoComputo'])
            ->orderBy('bien_id')
            ->get();

        return [
            'titulo' => 'Equipos patrimoniales',
            'encabezados' => ['Bien ID', 'CBI', 'Descripción', 'Tipo', 'Marca', 'Modelo', 'Serie', 'Ambiente', 'Especialidad', 'Activo', 'Procesador', 'Memoria RAM', 'Almacenamiento', 'Sede', 'Estado', 'Condición'],
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
                $equipo->equipoComputo?->procesador,
                $equipo->equipoComputo?->ram,
                $equipo->equipoComputo?->almacenamiento,
                $equipo->bien?->ambiente?->sede?->nombre,
                $equipo->bien?->estado?->nombre,
                $equipo->bien?->condicion?->nombre,
            ])->all(),
        ];
    }

    private function datosMantenimientos(Usuario $usuario, array $filtros): array
    {
        $registros = $this->consultas->consulta('mantenimientos', $usuario, $filtros)
            ->with(['bien.ambiente.especialidad', 'bien.ambiente.sede', 'bien.estado', 'bien.condicion', 'tipoMantenimiento', 'tecnico'])
            ->orderBy('id')
            ->get();

        return [
            'titulo' => 'Mantenimientos',
            'encabezados' => ['ID', 'Bien ID', 'CBI', 'Equipo', 'Tipo', 'Fecha', 'Técnico', 'Diagnóstico', 'Trabajo realizado', 'Observaciones', 'Ambiente', 'Especialidad', 'Descripción del mantenimiento', 'Sede', 'Estado', 'Condición'],
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
                $mantenimiento->descripcion,
                $mantenimiento->bien?->ambiente?->sede?->nombre,
                $mantenimiento->bien?->estado?->nombre,
                $mantenimiento->bien?->condicion?->nombre,
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
