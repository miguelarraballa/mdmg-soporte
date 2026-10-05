<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\TicketEstado;
use App\Services\TicketService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    private const RELACIONES = ['cliente', 'categoria', 'estado', 'prioridad', 'mensajes.autor'];

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->load(self::RELACIONES);
    }

    public function getTitle(): string|Htmlable
    {
        return "#{$this->record->numero()} · {$this->record->asunto}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('responder')
                ->label('Responder')
                ->icon('heroicon-o-arrow-uturn-left')
                ->url(fn () => TicketResource::getUrl('responder', ['record' => $this->record])),

            Action::make('cambiar_estado')
                ->label('Cambiar estado')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->schema([
                    Select::make('ticket_estado_id')
                        ->label('Nuevo estado')
                        ->options(fn () => TicketEstado::where('activo', true)->orderBy('orden')->pluck('nombre', 'id'))
                        ->default(fn () => $this->record->ticket_estado_id)
                        ->required(),
                    Textarea::make('comentario')
                        ->label('Comentario para el cliente (opcional)')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    TicketService::cambiarEstado(
                        $this->record,
                        TicketEstado::findOrFail($data['ticket_estado_id']),
                        auth()->user(),
                        $data['comentario'] ?? null,
                    );

                    $this->record->refresh()->load(self::RELACIONES);

                    Notification::make()->title('Estado actualizado')->success()->send();
                }),

            Action::make('cerrar')
                ->label('Cerrar ticket')
                ->icon('heroicon-o-check-circle')
                ->color('danger')
                ->visible(fn () => ! $this->record->estaCerrado())
                ->requiresConfirmation()
                ->modalDescription('Se avisará al cliente de que el ticket se ha cerrado.')
                ->action(function () {
                    TicketService::cerrar($this->record, auth()->user());

                    $this->record->refresh()->load(self::RELACIONES);

                    Notification::make()->title('Ticket cerrado')->success()->send();
                }),

            EditAction::make(),
        ];
    }
}
