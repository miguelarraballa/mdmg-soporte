<?php

namespace App\Filament\Resources\TicketPrioridades;

use App\Filament\Resources\TicketPrioridades\Pages\ManageTicketPrioridades;
use App\Filament\Support\ColoresBadge;
use App\Models\TicketPrioridad;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TicketPrioridadResource extends Resource
{
    protected static ?string $model = TicketPrioridad::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $modelLabel = 'prioridad';

    protected static ?string $pluralModelLabel = 'prioridades';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'ticket-prioridades';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre')
                ->required()
                ->maxLength(255),
            Select::make('color')
                ->options(ColoresBadge::opciones())
                ->placeholder('Gris'),
            TextInput::make('orden')
                ->numeric()
                ->default(0)
                ->required(),
            Toggle::make('es_default')
                ->label('Por defecto')
                ->helperText('Se asigna a los tickets nuevos.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->badge()
                    ->color(fn (TicketPrioridad $record) => $record->color()),
                TextColumn::make('orden')
                    ->sortable(),
                IconColumn::make('es_default')
                    ->label('Por defecto')
                    ->boolean(),
                TextColumn::make('tickets_count')
                    ->label('Tickets')
                    ->counts('tickets'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (TicketPrioridad $record) => $record->tickets()->exists()),
            ])
            ->defaultSort('orden')
            ->reorderable('orden');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTicketPrioridades::route('/'),
        ];
    }
}
