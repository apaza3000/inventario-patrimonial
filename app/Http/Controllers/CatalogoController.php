<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

abstract class CatalogoController extends Controller
{
    protected string $modelClass;

    protected string $entityName;

    protected string $dependentRelation;

    protected string $dependentName;

    abstract protected function rules(?int $id = null): array;

    public function index(): JsonResponse
    {
        $model = $this->modelClass;

        return response()->json([
            'data' => $model::query()->orderBy('id')->get(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $model = $this->modelClass;
        $record = $model::find($id);

        if ($record === null) {
            return $this->notFound();
        }

        return response()->json(['data' => $record]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $model = $this->modelClass;

        try {
            $record = $model::create($data);
        } catch (QueryException $exception) {
            if ($this->isDuplicateName($exception)) {
                return $this->duplicateName();
            }

            throw $exception;
        }

        return response()->json([
            'message' => "Se creó $this->entityName correctamente.",
            'data' => $record,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $model = $this->modelClass;
        $record = $model::find($id);

        if ($record === null) {
            return $this->notFound();
        }

        $data = $this->validatedData($request, $id);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $record->update($data);
        } catch (QueryException $exception) {
            if ($this->isDuplicateName($exception)) {
                return $this->duplicateName();
            }

            throw $exception;
        }

        return response()->json([
            'message' => "Se actualizó $this->entityName correctamente.",
            'data' => $record->refresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $model = $this->modelClass;
        $record = $model::find($id);

        if ($record === null) {
            return $this->notFound();
        }

        if ($record->{$this->dependentRelation}()->exists()) {
            return $this->inUse();
        }

        try {
            $record->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1451) {
                return $this->inUse();
            }

            throw $exception;
        }

        return response()->json(['message' => "Se eliminó $this->entityName correctamente."]);
    }

    private function validatedData(Request $request, ?int $id = null): array|JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules($id));

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
        return response()->json(['message' => ucfirst($this->entityName).' no existe.'], 404);
    }

    private function inUse(): JsonResponse
    {
        return response()->json([
            'message' => "No se puede eliminar $this->entityName porque está en uso por $this->dependentName.",
        ], 409);
    }

    private function isDuplicateName(QueryException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === 1062;
    }

    private function duplicateName(): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => ['nombre' => ['El nombre ya está registrado.']],
        ], 422);
    }
}
