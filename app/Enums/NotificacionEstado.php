<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum NotificacionEstado: string implements HasLabel, HasColor
{
    case EnCola = 'en_cola';
    case Enviado = 'enviado';
    case Error = 'error';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnCola => 'En cola',
            self::Enviado => 'Enviado',
            self::Error => 'Error',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EnCola => 'warning',
            self::Enviado => 'success',
            self::Error => 'danger',
        };
    }
}
