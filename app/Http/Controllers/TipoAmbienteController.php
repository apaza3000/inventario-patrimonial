<?php

namespace App\Http\Controllers;

use App\Models\TipoAmbiente;
use Illuminate\Validation\Rule;

class TipoAmbienteController extends CatalogoController
{
    protected string $modelClass = TipoAmbiente::class;

    protected string $entityName = 'el tipo de ambiente';

    protected string $dependentRelation = 'ambientes';

    protected string $dependentName = 'ambientes';

    protected function rules(?int $id = null): array
    {
        return [
            'nombre' => [$id === null ? 'required' : 'sometimes', 'string', 'max:50', Rule::unique('tipos_ambiente', 'nombre')->ignore($id)],
        ];
    }
}
