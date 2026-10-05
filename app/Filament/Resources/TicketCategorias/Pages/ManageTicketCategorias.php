<?php

namespace App\Filament\Resources\TicketCategorias\Pages;

use App\Filament\Resources\TicketCategorias\TicketCategoriaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTicketCategorias extends ManageRecords
{
    protected static string $resource = TicketCategoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
