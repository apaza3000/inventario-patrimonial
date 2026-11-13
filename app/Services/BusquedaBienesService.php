<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class BusquedaBienesService
{
    public static function patron(string $text): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($text)).'%';
    }

    public static function aplicar(Builder $query, string $text): Builder
    {
        $term = self::patron($text);
        return $query->where(fn ($q) => $q->whereRaw("cbi LIKE ? ESCAPE '!'", [$term])
            ->orWhereRaw("descripcion LIKE ? ESCAPE '!'", [$term])
            ->orWhereHas('equipo', fn ($equipment) => $equipment->whereRaw("serie LIKE ? ESCAPE '!'", [$term])));
    }
}
