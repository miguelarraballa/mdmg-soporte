<?php

namespace App\Filament\Portal\Resources\Tickets\Pages;

use App\Filament\Portal\Resources\Tickets\TicketResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->load(['estado', 'prioridad', 'mensajes']);
    }

    public function getTitle(): string|Htmlable
    {
        return "#{$this->record->numero()} · {$this->record->asunto}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('responder')
                ->label(fn () => $this->record->estaCerrado() ? 'Responder y reabrir' : 'Responder')
                ->icon('heroicon-o-arrow-uturn-left')
                ->url(fn () => TicketResource::getUrl('responder', ['record' => $this->record])),
            EditAction::make()->color('gray'),
            DeleteAction::make(),
        ];
    }
}
