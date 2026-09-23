<?php

namespace App\Http\Controllers;

use App\Models\Especialidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class EspecialidadController extends CatalogoController
{
    protected string $modelClass = Especialidad::class;

    protected string $entityName = 'la especialidad';

    protected string $dependentRelation = 'ambientes';

    protected string $dependentName = 'ambientes';

    public function destroy(int $id): JsonResponse
    {
        $especialidad = Especialidad::query()->find($id);

        if ($especialidad?->usuarios()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la especialidad porque está asignada a uno o más usuarios.',
            ], 409);
        }

        return parent::destroy($id);
    }

    protected function rules(?int $id = null): array
    {
        return [
            'nombre' => [$id === null ? 'required' : 'sometimes', 'string', 'max:100', Rule::unique('especialidades', 'nombre')->ignore($id)],
        ];
    }
}
