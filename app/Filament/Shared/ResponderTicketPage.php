<?php

namespace App\Filament\Shared;

use App\Services\TicketAdjuntoService;
use App\Services\TicketConversacion;
use App\Services\TicketService;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Página para responder un ticket, compartida por el admin y el portal.
 * Avisa si se intenta abandonar la página con texto sin enviar.
 */
abstract class ResponderTicketPage extends Page
{
    use HasUnsavedDataChangesAlert;
    use InteractsWithRecord;

    public ?array $data = [];

    abstract protected function vistaCliente(): bool;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(static::getResource()::canView($this->getRecord()), 403);

        $this->record->load('mensajes.autor');
        $this->form->fill();
        $this->rememberData();
    }

    protected function hasUnsavedDataChangesAlert(): bool
    {
        return true;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Responder: ' . $this->record->asunto;
    }

    public function getBreadcrumb(): string
    {
        return 'Responder';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TicketAdjuntoService::campoMensaje(),
                TicketAdjuntoService::campoAdjuntos(),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('responder')
                ->footer([
                    Actions::make([
                        Action::make('enviar')
                            ->label('Enviar respuesta')
                            ->icon('heroicon-o-paper-airplane')
                            ->submit('responder')
                            ->keyBindings(['mod+s']),
                        Action::make('cancelar')
                            ->label('Cancelar')
                            ->color('gray')
                            ->url($this->urlVolver()),
                    ]),
                ]),
            Section::make('Conversación')
                ->collapsible()
                ->schema([
                    TextEntry::make('conversacion')
                        ->hiddenLabel()
                        ->state(fn () => TicketConversacion::html($this->record, $this->vistaCliente())),
                ]),
        ]);
    }

    public function responder(): void
    {
        $data = $this->form->getState();

        TicketService::responder(
            $this->record,
            $data['cuerpo'],
            auth()->user(),
            TicketAdjuntoService::desdeFormulario($data['adjuntos'] ?? null, $data['adjuntos_nombres'] ?? null),
        );
        $this->rememberData();

        Notification::make()
            ->title($this->vistaCliente() ? 'Mensaje enviado' : 'Respuesta enviada')
            ->success()
            ->send();

        $this->redirect($this->urlVolver());
    }

    protected function urlVolver(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
