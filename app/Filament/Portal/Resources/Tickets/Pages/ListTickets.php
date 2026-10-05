<?php

namespace App\Filament\Portal\Resources\Tickets\Pages;

use App\Filament\Portal\Resources\Tickets\TicketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo ticket')
                ->disabled(fn () => ! auth()->user()->serviciosDisponibles()->exists())
                ->tooltip(fn () => auth()->user()->serviciosDisponibles()->exists() ? null : 'No tienes servicios asignados'),
        ];
    }
}
