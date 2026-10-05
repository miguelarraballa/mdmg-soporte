<?php

namespace App\Filament\Portal\Resources\Tickets\Pages;

use App\Filament\Portal\Resources\Tickets\TicketResource;
use App\Filament\Shared\ResponderTicketPage;

class ResponderTicket extends ResponderTicketPage
{
    protected static string $resource = TicketResource::class;

    protected function vistaCliente(): bool
    {
        return true;
    }
}
