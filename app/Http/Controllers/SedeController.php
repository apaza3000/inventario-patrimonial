<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use Illuminate\Validation\Rule;

class SedeController extends CatalogoController
{
    protected string $modelClass = Sede::class;

    protected string $entityName = 'la sede';

    protected string $dependentRelation = 'ambientes';

    protected string $dependentName = 'ambientes';

    protected function rules(?int $id = null): array
    {
        return [
            'nombre' => [$id === null ? 'required' : 'sometimes', 'string', 'max:100', Rule::unique('sedes', 'nombre')->ignore($id)],
            'direccion' => ['sometimes', 'nullable', 'string', 'max:200'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
