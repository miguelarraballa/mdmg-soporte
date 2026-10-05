<?php

namespace App\Filament\Resources\Servicios\Pages;

use App\Filament\Resources\Servicios\ServicioResource;
use Filament\Resources\Pages\CreateRecord;

class CreateServicio extends CreateRecord
{
    protected static string $resource = ServicioResource::class;

    /**
     * Tras crearlo se va a la edición para poder asignarle clientes.
     */
    protected function getRedirectUrl(): string
    {
        return ServicioResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
