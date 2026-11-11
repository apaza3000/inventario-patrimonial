<?php

namespace App\Services;

use App\Models\Bien;
use App\Models\Equipo;
use App\Models\EquipoComputo;
use App\Models\Monitor;
use App\Models\Mueble;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RegistroBienService
{
    public const CATEGORIAS = ['computadoras', 'monitores', 'mobiliario', 'otros-equipos'];

    public static function categoriaTipo(string $nombre): string
    {
        return match ($nombre) {
            'CPU', 'LAPTOP' => 'computadoras',
            'MONITOR' => 'monitores',
            default => 'otros-equipos',
        };
    }

    public function registrar(array $general, array $input): Bien
    {
        $categoria = $input['categoria'] ?? null;
        $rules = ['categoria' => ['required', Rule::in(self::CATEGORIAS)]];
        $fields = [
            'tipo_equipo_id' => ['required', 'integer', Rule::exists('tipos_equipo', 'id')->where(function ($query) use ($categoria) {
                return match ($categoria) {
                    'computadoras' => $query->whereIn('nombre', ['CPU', 'LAPTOP']),
                    'monitores' => $query->where('nombre', 'MONITOR'),
                    default => $query->whereNotIn('nombre', ['CPU', 'LAPTOP', 'MONITOR']),
                };
            })],
            'tipo_mueble_id' => ['required', 'integer', 'exists:tipos_mueble,id'],
            'marca_id' => ['nullable', 'integer', 'exists:marcas,id'],
            'modelo' => ['nullable', 'string', 'max:100'], 'serie' => ['nullable', 'string', 'max:100'],
            'procesador' => ['nullable', 'string', 'max:100'], 'ram' => ['nullable', 'string', 'max:100'],
            'almacenamiento' => ['nullable', 'string', 'max:150'], 'detalle_adicional' => ['nullable', 'string', 'max:255'],
            'tamano_pantalla' => ['nullable', 'string', 'max:50'], 'detalle_pantalla' => ['nullable', 'string', 'max:150'],
            'material' => ['nullable', 'string', 'max:150'], 'color' => ['nullable', 'string', 'max:80'],
        ];
        $allowed = $categoria === 'mobiliario' ? ['tipo_mueble_id', 'material', 'color'] : ['tipo_equipo_id', 'marca_id', 'modelo', 'serie'];
        if ($categoria === 'computadoras') {
            $allowed = [...$allowed, 'procesador', 'ram', 'almacenamiento', 'detalle_adicional'];
        }
        if ($categoria === 'monitores') {
            $allowed = [...$allowed, 'tamano_pantalla', 'detalle_pantalla'];
        }
        foreach ($fields as $key => $rule) {
            $rules[$key] = in_array($key, $allowed, true) ? $rule : ['prohibited'];
        }
        $data = Validator::make($input, $rules)->validate();

        return DB::transaction(function () use ($general, $data, $categoria) {
            $bien = Bien::create($general);
            if ($categoria === 'mobiliario') {
                Mueble::create(['bien_id' => $bien->id, ...array_intersect_key($data, array_flip(['tipo_mueble_id', 'material', 'color']))]);
            } else {
                Equipo::create(['bien_id' => $bien->id, ...array_intersect_key($data, array_flip(['tipo_equipo_id', 'marca_id', 'modelo', 'serie']))]);
                if ($categoria === 'computadoras') {
                    EquipoComputo::create(['bien_id' => $bien->id, ...array_intersect_key($data, array_flip(['procesador', 'ram', 'almacenamiento', 'detalle_adicional']))]);
                } elseif ($categoria === 'monitores') {
                    Monitor::create(['bien_id' => $bien->id, 'tamanio_pantalla' => $data['tamano_pantalla'] ?? null, 'detalle_pantalla' => $data['detalle_pantalla'] ?? null]);
                }
            }
            return $bien;
        });
    }
}
