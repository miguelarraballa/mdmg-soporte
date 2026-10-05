<?php

namespace App\Filament\Portal\Resources\Tickets\Pages;

use App\Filament\Portal\Resources\Tickets\TicketResource;
use App\Models\Servicio;
use App\Models\TicketCategoria;
use App\Models\TicketPrioridad;
use App\Services\TicketAdjuntoService;
use App\Services\TicketService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected static bool $canCreateAnother = false;

    /**
     * Sin servicios asignados no se puede abrir un ticket.
     */
    public function mount(): void
    {
        if (! auth()->user()->serviciosDisponibles()->exists()) {
            Notification::make()
                ->title('No tienes servicios asignados')
                ->body('Para abrir un ticket necesitas tener algún servicio contratado. Ponte en contacto con nosotros.')
                ->warning()
                ->persistent()
                ->send();

            $this->redirect(TicketResource::getUrl('index'));

            return;
        }

        parent::mount();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            ...TicketResource::camposEditables(),
            TicketAdjuntoService::campoMensaje('mensaje', '¿En qué podemos ayudarte?')
                ->columnSpanFull(),
            TicketAdjuntoService::campoAdjuntos()
                ->columnSpanFull(),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return TicketService::crear(
            cliente: auth()->user(),
            servicio: Servicio::findOrFail($data['servicio_id']),
            asunto: $data['asunto'],
            mensaje: $data['mensaje'],
            autor: auth()->user(),
            categoria: TicketCategoria::find($data['ticket_categoria_id'] ?? null),
            prioridad: TicketPrioridad::find($data['ticket_prioridad_id'] ?? null),
            adjuntos: TicketAdjuntoService::desdeFormulario($data['adjuntos'] ?? null, $data['adjuntos_nombres'] ?? null),
        );
    }

    protected function getRedirectUrl(): string
    {
        return TicketResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
