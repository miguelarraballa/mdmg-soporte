<?php

namespace App\Filament\Portal\Resources\Tickets;

use App\Filament\Portal\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Portal\Resources\Tickets\Pages\EditTicket;
use App\Filament\Portal\Resources\Tickets\Pages\ListTickets;
use App\Filament\Portal\Resources\Tickets\Pages\ResponderTicket;
use App\Filament\Portal\Resources\Tickets\Pages\ViewTicket;
use App\Models\Ticket;
use App\Models\TicketCategoria;
use App\Models\TicketPrioridad;
use App\Services\TicketConversacion;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tickets del cliente autenticado. Todas las consultas se limitan a sus propios
 * tickets; editar solo mientras esté abierto y borrar solo si soporte aún no ha respondido.
 */
class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Mis tickets';

    protected static ?string $modelLabel = 'ticket';

    protected static ?string $pluralModelLabel = 'mis tickets';

    protected static ?string $slug = 'tickets';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    // Filament 5 autoriza acciones y páginas con estos métodos (no con canEdit/canDelete).

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return $record->estaCerrado()
            ? Response::deny('No se puede editar un ticket cerrado.')
            : Response::allow();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return $record->tieneRespuestaDeSoporte()
            ? Response::deny('No se puede borrar un ticket en el que ya ha respondido soporte.')
            : Response::allow();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    /**
     * Campos editables por el cliente (el alta añade además el mensaje).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::camposEditables());
    }

    public static function camposEditables(): array
    {
        return [
            Select::make('servicio_id')
                ->label('Servicio')
                ->options(fn (?Ticket $record) => auth()->user()->serviciosDisponibles()
                    ->pluck('servicios.nombre', 'servicios.id')
                    // Si el servicio del ticket ya no está activo, se mantiene como opción al editar.
                    ->when($record?->servicio, fn ($opciones, $servicio) => $opciones->put($servicio->id, $servicio->nombre)))
                ->required()
                ->columnSpanFull(),
            TextInput::make('asunto')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Select::make('ticket_categoria_id')
                ->label('Categoría')
                ->relationship('categoria', 'nombre', fn (Builder $query) => $query->orderBy('orden'))
                ->default(fn () => TicketCategoria::default()?->id)
                // Con una sola categoría no tiene sentido pedir que el cliente elija.
                ->visible(fn () => TicketCategoria::count() > 1)
                ->required(),
            Select::make('ticket_prioridad_id')
                ->label('Prioridad')
                ->relationship('prioridad', 'nombre', fn (Builder $query) => $query->orderBy('orden'))
                ->default(fn () => TicketPrioridad::default()?->id)
                ->required(),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('servicio.nombre')
                        ->label('Servicio')
                        ->placeholder('—'),
                    TextEntry::make('estado.nombre')
                        ->label('Estado')
                        ->badge()
                        ->color(fn (Ticket $record) => $record->estado?->color()),
                    TextEntry::make('prioridad.nombre')
                        ->label('Prioridad')
                        ->badge()
                        ->color(fn (Ticket $record) => $record->prioridad?->color())
                        ->placeholder('—'),
                    TextEntry::make('ultima_actividad_en')
                        ->label('Última actividad')
                        ->dateTime('d-m-Y H:i'),
                ]),
            Section::make('Conversación')
                ->schema([
                    TextEntry::make('conversacion')
                        ->hiddenLabel()
                        ->state(fn (Ticket $record) => TicketConversacion::html($record, vistaCliente: true)),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->formatStateUsing(fn (Ticket $record) => $record->numero())
                    // Permite buscar por "000015" o "15".
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('tickets.id', (int) ltrim($search, '0#')))
                    ->sortable(),
                TextColumn::make('asunto')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('servicio.nombre')
                    ->label('Servicio')
                    ->placeholder('—'),
                TextColumn::make('prioridad.nombre')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (Ticket $record) => $record->prioridad?->color()),
                TextColumn::make('estado.nombre')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (Ticket $record) => $record->estado?->color()),
                TextColumn::make('ultima_actividad_en')
                    ->label('Última actividad')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('ultima_actividad_en', 'desc')
            ->emptyStateHeading('Todavía no tienes tickets')
            ->emptyStateDescription('Abre un ticket y te responderemos lo antes posible.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'create' => CreateTicket::route('/create'),
            'view' => ViewTicket::route('/{record}'),
            'edit' => EditTicket::route('/{record}/edit'),
            'responder' => ResponderTicket::route('/{record}/responder'),
        ];
    }
}
