<?php

namespace App\Filament\Resources\Tickets;

use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ResponderTicket;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\Tables\TicketsTable;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketConversacion;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $modelLabel = 'ticket';

    protected static ?string $pluralModelLabel = 'tickets';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'asunto';

    public static function getNavigationBadge(): ?string
    {
        $abiertos = Ticket::abiertos()->count();

        return $abiertos ? (string) $abiertos : null;
    }

    /**
     * Formulario de edición (el alta usa su propio formulario con el mensaje inicial).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            self::campoCliente(),
            self::campoServicio(),
            TextInput::make('asunto')
                ->required()
                ->maxLength(255),
            Select::make('ticket_categoria_id')
                ->label('Categoría')
                ->relationship('categoria', 'nombre', fn (Builder $query) => $query->orderBy('orden'))
                ->preload(),
            Select::make('ticket_prioridad_id')
                ->label('Prioridad')
                ->relationship('prioridad', 'nombre', fn (Builder $query) => $query->orderBy('orden'))
                ->preload(),
        ]);
    }

    public static function campoCliente(): Select
    {
        return Select::make('user_id')
            ->label('Cliente')
            ->relationship('cliente', 'name', fn (Builder $query) => $query->clientes()->orderBy('name'))
            ->getOptionLabelFromRecordUsing(fn (User $u) => "{$u->name} ({$u->email})")
            ->searchable(['name', 'email'])
            ->preload()
            ->live()
            ->afterStateUpdated(fn (Set $set) => $set('servicio_id', null))
            ->required();
    }

    /**
     * Servicios activos del cliente elegido (más el actual del ticket, aunque ya no lo esté).
     */
    public static function campoServicio(): Select
    {
        return Select::make('servicio_id')
            ->label('Servicio')
            ->options(fn (Get $get, ?Ticket $record) => Servicio::query()
                ->where(fn ($query) => $query
                    ->whereHas('clientes', fn ($query) => $query->whereKey($get('user_id')))
                    ->where('activo', true))
                ->when($record?->servicio_id, fn ($query, $id) => $query->orWhereKey($id))
                ->orderBy('nombre')
                ->pluck('nombre', 'id'))
            ->disabled(fn (Get $get) => blank($get('user_id')))
            ->helperText(fn (Get $get) => filled($get('user_id')) && ! Servicio::whereHas('clientes', fn ($query) => $query->whereKey($get('user_id')))->exists()
                ? 'Este cliente no tiene servicios asignados. Asígnaselos en Usuarios o en Servicios.'
                : null)
            ->required();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 2, 'md' => 5])
                ->schema([
                    TextEntry::make('cliente.name')
                        ->label('Cliente')
                        ->helperText(fn (Ticket $record) => $record->cliente?->email),
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
                    TextEntry::make('categoria.nombre')
                        ->label('Categoría')
                        ->badge()
                        ->color(fn (Ticket $record) => $record->categoria?->color())
                        ->placeholder('—'),
                ]),
            Section::make('Conversación')
                ->schema([
                    TextEntry::make('conversacion')
                        ->hiddenLabel()
                        ->state(fn (Ticket $record) => TicketConversacion::html($record, vistaCliente: false)),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return TicketsTable::configure($table);
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
