<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Servicio;
use App\Models\TicketCategoria;
use App\Models\TicketPrioridad;
use App\Models\User;
use App\Services\TicketAdjuntoService;
use App\Services\TicketService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TicketResource::campoCliente(),
            TicketResource::campoServicio(),
            TextInput::make('asunto')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Grid::make(2)->columnSpanFull()->schema([
                Select::make('ticket_categoria_id')
                    ->label('Categoría')
                    ->relationship('categoria', 'nombre', fn (Builder $query) => $query->orderBy('orden'))
                    ->preload()
                    ->default(fn () => TicketCategoria::default()?->id),
                Select::make('ticket_prioridad_id')
                    ->label('Prioridad')
                    ->relationship('prioridad', 'nombre', fn (Builder $query) => $query->orderBy('orden'))
                    ->preload()
                    ->default(fn () => TicketPrioridad::default()?->id),
            ]),
            TicketAdjuntoService::campoMensaje('mensaje', 'Mensaje inicial')
                ->helperText('El cliente recibirá este mensaje por email.')
                ->columnSpanFull(),
            TicketAdjuntoService::campoAdjuntos()
                ->columnSpanFull(),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return TicketService::crear(
            cliente: User::clientes()->findOrFail($data['user_id']),
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
