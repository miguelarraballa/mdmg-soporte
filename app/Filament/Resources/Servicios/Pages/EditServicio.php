<?php

namespace App\Filament\Resources\Servicios\Pages;

use App\Filament\Resources\Servicios\ServicioResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditServicio extends EditRecord
{
    protected static string $resource = ServicioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('Los tickets de este servicio se conservan, pero quedarán sin servicio asignado.'),
        ];
    }
}
