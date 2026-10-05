<?php

namespace App\Filament\Resources\TicketPrioridades\Pages;

use App\Filament\Resources\TicketPrioridades\TicketPrioridadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTicketPrioridades extends ManageRecords
{
    protected static string $resource = TicketPrioridadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
