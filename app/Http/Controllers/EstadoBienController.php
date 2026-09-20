<?php

namespace App\Http\Controllers;

use App\Models\EstadoBien;
use Illuminate\Validation\Rule;

class EstadoBienController extends CatalogoController
{
    protected string $modelClass = EstadoBien::class;

    protected string $entityName = 'el estado del bien';

    protected string $dependentRelation = 'bienes';

    protected string $dependentName = 'bienes';

    protected function rules(?int $id = null): array
    {
        return [
            'nombre' => [$id === null ? 'required' : 'sometimes', 'string', 'max:50', Rule::unique('estados_bien', 'nombre')->ignore($id)],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:150'],
        ];
    }
}
