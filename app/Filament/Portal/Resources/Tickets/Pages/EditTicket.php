<?php

namespace App\Filament\Portal\Resources\Tickets\Pages;

use App\Filament\Portal\Resources\Tickets\TicketResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return TicketResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
