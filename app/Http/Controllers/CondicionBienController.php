<?php

namespace App\Http\Controllers;

use App\Models\CondicionBien;
use Illuminate\Validation\Rule;

class CondicionBienController extends CatalogoController
{
    protected string $modelClass = CondicionBien::class;

    protected string $entityName = 'la condición del bien';

    protected string $dependentRelation = 'bienes';

    protected string $dependentName = 'bienes';

    protected function rules(?int $id = null): array
    {
        return [
            'nombre' => [$id === null ? 'required' : 'sometimes', 'string', 'max:50', Rule::unique('condiciones_bien', 'nombre')->ignore($id)],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:150'],
        ];
    }
}
