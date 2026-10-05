<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Rol: string implements HasLabel, HasColor
{
    case Admin = 'admin';
    case Cliente = 'cliente';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Cliente => 'Cliente',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Cliente => 'primary',
        };
    }

    /**
     * Panel de Filament al que entra cada rol.
     */
    public function panel(): string
    {
        return match ($this) {
            self::Admin => 'admin',
            self::Cliente => 'portal',
        };
    }
}
