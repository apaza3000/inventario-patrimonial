<?php

namespace App\Services;

use App\Models\Ambiente;
use App\Models\Bien;
use App\Models\CondicionBien;
use App\Models\EstadoBien;
use App\Models\Especialidad;
use App\Models\Sede;
use App\Models\TipoEquipo;
use App\Models\TipoMantenimiento;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReporteConsultaService
{
    public function __construct(private readonly AlcanceDatosService $alcance)
    {
    }

    public function validar(string $tipo, Request $request): array
    {
        $sedeId = filter_var($request->input('sede_id'), FILTER_VALIDATE_INT);
        $categorias = ['todos', ...RegistroBienService::CATEGORIAS];
        if ($tipo === 'equipos') $categorias = array_values(array_diff($categorias, ['mobiliario']));
        $data = $request->validate([
            'q' => ['sometimes', 'required', 'string', 'max:150'],
            'sede_id' => ['sometimes', 'integer', 'min:1', 'exists:sedes,id'],
            'ambiente_id' => ['sometimes', 'integer', 'min:1', Rule::exists('ambientes', 'id')->when($sedeId !== false, fn ($rule) => $rule->where('sede_id', $sedeId))],
            'especialidad_id' => ['sometimes', 'integer', 'min:1', 'exists:especialidades,id'],
            'estado_id' => ['sometimes', 'integer', 'min:1', 'exists:estados_bien,id'],
            'condicion_id' => ['sometimes', 'integer', 'min:1', 'exists:condiciones_bien,id'],
            'categoria' => ['sometimes', Rule::in($categorias)],
            'tipo_equipo_id' => ['sometimes', 'integer', 'min:1', 'exists:tipos_equipo,id'],
            'anio' => $tipo === 'inventario' ? ['sometimes', 'integer', 'between:-2147483648,2147483647'] : ['prohibited'],
            'desde' => $tipo === 'mantenimientos' ? ['sometimes', 'date_format:Y-m-d'] : ['prohibited'],
            'hasta' => $tipo === 'mantenimientos' ? ['sometimes', 'date_format:Y-m-d', ...($request->filled('desde') ? ['after_or_equal:desde'] : [])] : ['prohibited'],
            'tipo_mantenimiento_id' => $tipo === 'mantenimientos' ? ['sometimes', 'integer', 'min:1', 'exists:tipos_mantenimiento,id'] : ['prohibited'],
            'completo' => ['sometimes', 'boolean'],
        ], [
            'exists' => 'La opción seleccionada para :attribute no existe o no corresponde a la sede.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'prohibited' => 'El campo :attribute no corresponde a este reporte.',
            'date_format' => 'La fecha :attribute debe tener el formato año-mes-día.',
            'after_or_equal' => 'La fecha final no puede ser anterior a la fecha inicial.',
        ]);
        if ($request->boolean('completo') && count(array_diff_key($data, ['completo' => true]))) {
            throw ValidationException::withMessages(['completo' => ['Solicita el reporte completo sin combinarlo con filtros.']]);
        }
        return array_diff_key($data, ['completo' => true]);
    }

    public function base(string $tipo, Usuario $usuario): Builder
    {
        return match ($tipo) {
            'inventario' => $this->alcance->inventarios($usuario),
            'equipos' => $this->alcance->equipos($usuario),
            'mantenimientos' => $this->alcance->mantenimientos($usuario),
        };
    }

    public function consulta(string $tipo, Usuario $usuario, array $filtros): Builder
    {
        // Scope is always the outer constraint; no filter can widen it.
        $query = $this->base($tipo, $usuario);
        if ($filtros) $query->whereHas('bien', function (Builder $bien) use ($filtros) {
            if (isset($filtros['q'])) {
                $term = BusquedaBienesService::patron($filtros['q']);
                $bien->where(fn ($q) => $q->whereRaw("cbi LIKE ? ESCAPE '!'", [$term])->orWhereRaw("descripcion LIKE ? ESCAPE '!'", [$term]));
            }
            foreach (['ambiente_id', 'estado_id', 'condicion_id'] as $field) {
                if (isset($filtros[$field])) $bien->where($field, $filtros[$field]);
            }
            foreach (['sede_id', 'especialidad_id'] as $field) {
                if (isset($filtros[$field])) $bien->whereHas('ambiente', fn ($q) => $q->where($field, $filtros[$field]));
            }
            if (isset($filtros['tipo_equipo_id'])) $bien->whereHas('equipo', fn ($q) => $q->where('tipo_equipo_id', $filtros['tipo_equipo_id']));
            match ($filtros['categoria'] ?? 'todos') {
                'computadoras' => $bien->whereHas('equipo.equipoComputo'),
                'monitores' => $bien->whereHas('equipo.monitor'),
                'mobiliario' => $bien->whereHas('mueble'),
                'otros-equipos' => $bien->whereHas('equipo', fn ($q) => $q->whereDoesntHave('equipoComputo')->whereDoesntHave('monitor')),
                default => null,
            };
        });
        if (isset($filtros['anio'])) $query->where('anio', $filtros['anio']);
        if (isset($filtros['desde'])) $query->whereDate('fecha_mantenimiento', '>=', $filtros['desde']);
        if (isset($filtros['hasta'])) $query->whereDate('fecha_mantenimiento', '<=', $filtros['hasta']);
        if (isset($filtros['tipo_mantenimiento_id'])) $query->where('tipo_mantenimiento_id', $filtros['tipo_mantenimiento_id']);
        return $query;
    }

    public function opciones(string $tipo, Usuario $usuario): array
    {
        // Only catalog entries related to records this user may export are exposed.
        $base = $this->base($tipo, $usuario);
        $bienes = Bien::whereIn('id', (clone $base)->select('bien_id'));
        $ambientes = Ambiente::whereIn('id', (clone $bienes)->select('ambiente_id'))
            ->orderBy('nombre')->get(['id', 'nombre', 'sede_id', 'especialidad_id']);
        $equipoIds = (clone $bienes)->whereHas('equipo')->select('id');
        $opciones = [
            'sedes' => Sede::whereIn('id', $ambientes->pluck('sede_id'))->orderBy('nombre')->get(['id', 'nombre']),
            'ambientes' => $ambientes,
            'especialidades' => Especialidad::whereIn('id', $ambientes->pluck('especialidad_id'))->orderBy('nombre')->get(['id', 'nombre']),
            'estados' => EstadoBien::whereIn('id', (clone $bienes)->select('estado_id'))->orderBy('nombre')->get(['id', 'nombre']),
            'condiciones' => CondicionBien::whereIn('id', (clone $bienes)->select('condicion_id'))->orderBy('nombre')->get(['id', 'nombre']),
            'tipos_equipo' => TipoEquipo::whereHas('equipos', fn ($q) => $q->whereIn('bien_id', $equipoIds))->orderBy('nombre')->get(['id', 'nombre']),
        ];
        if ($tipo === 'inventario') $opciones['anios'] = (clone $base)->select('anio')->distinct()->orderByDesc('anio')->pluck('anio');
        if ($tipo === 'mantenimientos') $opciones['tipos_mantenimiento'] = TipoMantenimiento::whereIn('id', (clone $base)->select('tipo_mantenimiento_id'))->orderBy('nombre')->get(['id', 'nombre']);
        return $opciones;
    }

    public function descripcionFiltros(array $filtros): string
    {
        if (! $filtros) return 'Reporte completo dentro del alcance autorizado';
        $labels = ['q' => 'CBI o descripción', 'anio' => 'Año', 'desde' => 'Desde', 'hasta' => 'Hasta', 'categoria' => 'Categoría'];
        $models = ['sede_id' => Sede::class, 'ambiente_id' => Ambiente::class, 'especialidad_id' => Especialidad::class,
            'estado_id' => EstadoBien::class, 'condicion_id' => CondicionBien::class, 'tipo_equipo_id' => TipoEquipo::class, 'tipo_mantenimiento_id' => TipoMantenimiento::class];
        $names = ['sede_id' => 'Sede', 'ambiente_id' => 'Ambiente', 'especialidad_id' => 'Especialidad', 'estado_id' => 'Estado', 'condicion_id' => 'Condición', 'tipo_equipo_id' => 'Tipo de equipo', 'tipo_mantenimiento_id' => 'Tipo de mantenimiento'];
        $parts = [];
        foreach ($filtros as $key => $value) {
            $parts[] = isset($models[$key]) ? $names[$key].': '.($models[$key]::find($value)?->nombre ?? '—') : ($labels[$key] ?? $key).': '.$value;
        }
        return implode(' · ', $parts);
    }
}
