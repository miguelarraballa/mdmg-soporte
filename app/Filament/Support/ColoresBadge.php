<?php

namespace App\Filament\Support;

/**
 * Colores de Filament disponibles para las etiquetas de los catálogos de tickets.
 */
class ColoresBadge
{
    public static function opciones(): array
    {
        return [
            'gray' => 'Gris',
            'primary' => 'Principal (ámbar)',
            'info' => 'Azul',
            'success' => 'Verde',
            'warning' => 'Naranja',
            'danger' => 'Rojo',
        ];
    }
}
