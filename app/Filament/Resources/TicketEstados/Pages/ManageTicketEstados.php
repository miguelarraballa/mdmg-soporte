<?php

namespace App\Filament\Resources\TicketEstados\Pages;

use App\Filament\Resources\TicketEstados\TicketEstadoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTicketEstados extends ManageRecords
{
    protected static string $resource = TicketEstadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
