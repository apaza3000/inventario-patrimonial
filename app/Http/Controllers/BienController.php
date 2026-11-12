<?php

namespace App\Http\Controllers;

use App\Models\Bien;
use App\Models\Marca;
use App\Models\TipoEquipo;
use App\Models\TipoMueble;
use App\Services\RegistroBienService;
use App\Services\AlcanceDatosService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BienController extends Controller
{
    private const RELATIONS = ['ambiente', 'estado', 'condicion'];
    private const TECHNICAL_RELATIONS = ['equipo.tipoEquipo', 'equipo.marca', 'equipo.equipoComputo', 'equipo.monitor', 'mueble.tipoMueble'];

    private const DEPENDENCIES = [
        'inventarios' => 'inventarios',
        'muebles' => 'muebles',
        'equipos' => 'equipos',
        'mantenimientos' => 'mantenimientos',
        'movimientos' => 'movimientos',
        'estacion_componentes' => 'componentes de estación',
    ];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'categoria' => ['sometimes', Rule::in(['todos', ...RegistroBienService::CATEGORIAS])],
            'cbi' => ['sometimes', 'required', 'string', 'max:25'],
        ]);
        $query = Bien::with(self::RELATIONS);
        if ($request->has('cbi')) {
            $query->where('cbi', $request->input('cbi'));
        }
        match ($request->input('categoria', 'todos')) {
            'computadoras' => $query->whereHas('equipo.equipoComputo'),
            'monitores' => $query->whereHas('equipo.monitor'),
            'mobiliario' => $query->whereHas('mueble'),
            'otros-equipos' => $query->whereHas('equipo', fn ($q) => $q->whereDoesntHave('equipoComputo')->whereDoesntHave('monitor')),
            default => $query,
        };
        $bienes = $query
            ->orderBy('id')
            ->paginate(15);

        return response()->json($bienes);
    }

    public function show(int $id): JsonResponse
    {
        $bien = Bien::with([...self::RELATIONS, ...self::TECHNICAL_RELATIONS])->find($id);

        if ($bien === null) {
            return $this->notFound();
        }

        return response()->json(['data' => $bien]);
    }

    public function buscar(Request $request, AlcanceDatosService $alcance): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:150'], 'page' => ['sometimes', 'integer', 'min:1']]);
        // Literal substring matching: %, _ and the escape character are not wildcards.
        $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($data['q'])).'%';
        $query = $alcance->bienesConsulta($request->user('web'));
        $query->where(function ($query) use ($term) {
            $query->whereRaw("cbi LIKE ? ESCAPE '!'", [$term])
                ->orWhereRaw("descripcion LIKE ? ESCAPE '!'", [$term])
                ->orWhereHas('equipo', fn ($equipment) => $equipment->whereRaw("serie LIKE ? ESCAPE '!'", [$term]));
        });

        return response()->json($query->with(['ambiente.sede', 'equipo' => fn ($q) => $q->select('bien_id', 'serie')])
            ->orderBy('id')->paginate(15));
    }

    public function detalleBusqueda(Request $request, int $id, AlcanceDatosService $alcance): JsonResponse
    {
        $bien = $alcance->bienesConsulta($request->user('web'))
            ->with([...self::RELATIONS, 'ambiente.sede', ...self::TECHNICAL_RELATIONS])->find($id);

        return $bien === null ? $this->notFound() : response()->json(['data' => $bien]);
    }

    public function opciones(): JsonResponse
    {
        return response()->json(['data' => [
            'marcas' => Marca::orderBy('nombre')->get(['id', 'nombre']),
            'tipos_mueble' => TipoMueble::orderBy('nombre')->get(['id', 'nombre']),
            'tipos_equipo' => TipoEquipo::orderBy('nombre')->get(['id', 'nombre'])->map(fn ($tipo) => [
                'id' => $tipo->id, 'nombre' => $tipo->nombre, 'categoria' => RegistroBienService::categoriaTipo($tipo->nombre),
            ]),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $bien = $request->exists('categoria')
                ? app(RegistroBienService::class)->registrar($data, $request->all())
                : Bien::create($data);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return $this->duplicateCbi();
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Se creó el bien correctamente.',
            'data' => $bien->load($request->exists('categoria') ? [...self::RELATIONS, ...self::TECHNICAL_RELATIONS] : self::RELATIONS),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $bien = Bien::find($id);

        if ($bien === null) {
            return $this->notFound();
        }

        $data = $this->validatedData($request, $id);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $bien->update($data);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return $this->duplicateCbi();
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Se actualizó el bien correctamente.',
            'data' => $bien->refresh()->load(self::RELATIONS),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $bien = Bien::find($id);

        if ($bien === null) {
            return $this->notFound();
        }

        foreach (self::DEPENDENCIES as $table => $label) {
            if (DB::table($table)->where('bien_id', $id)->exists()) {
                return $this->inUse($label);
            }
        }

        try {
            $bien->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1451) {
                return $this->inUse('otros registros');
            }

            throw $exception;
        }

        return response()->json(['message' => 'Se eliminó el bien correctamente.']);
    }

    private function validatedData(Request $request, ?int $id = null): array|JsonResponse
    {
        if ($id !== null && array_key_exists('ambiente_id', $request->all())) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => ['ambiente_id' => ['La ubicación de un bien solo puede cambiarse mediante movimientos.']],
            ], 422);
        }

        $required = $id === null ? 'required' : 'sometimes';

        $validator = Validator::make($request->all(), [
            'cbi' => ['sometimes', 'nullable', 'string', 'max:25', Rule::unique('bienes', 'cbi')->ignore($id)],
            'descripcion' => [$required, 'string', 'max:150'],
            'estado_id' => ['sometimes', 'nullable', 'integer', 'exists:estados_bien,id'],
            'condicion_id' => ['sometimes', 'nullable', 'integer', 'exists:condiciones_bien,id'],
            'ambiente_id' => ['sometimes', 'nullable', 'integer', 'exists:ambientes,id'],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
            'fecha_registro' => ['sometimes', 'nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        return $validator->validated();
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Bien no encontrado.'], 404);
    }

    private function duplicateCbi(): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => ['cbi' => ['El CBI ya está registrado.']],
        ], 422);
    }

    private function inUse(string $relation): JsonResponse
    {
        return response()->json([
            'message' => "No se puede eliminar el bien porque está en uso por $relation.",
        ], 409);
    }
}
