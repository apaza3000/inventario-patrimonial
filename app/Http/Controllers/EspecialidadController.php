<?php

namespace App\Http\Controllers;

use App\Models\Especialidad;
use Illuminate\Validation\Rule;

class EspecialidadController extends CatalogoController
{
    protected string $modelClass = Especialidad::class;

    protected string $entityName = 'la especialidad';

    protected string $dependentRelation = 'ambientes';

    protected string $dependentName = 'ambientes';

    protected function rules(?int $id = null): array
    {
        return [
            'nombre' => [$id === null ? 'required' : 'sometimes', 'string', 'max:100', Rule::unique('especialidades', 'nombre')->ignore($id)],
        ];
    }
}
