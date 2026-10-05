<?php

namespace App\Filament\Resources\TicketEstados;

use App\Filament\Resources\TicketEstados\Pages\ManageTicketEstados;
use App\Filament\Support\ColoresBadge;
use App\Models\TicketEstado;
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

class TicketEstadoResource extends Resource
{
    protected static ?string $model = TicketEstado::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $modelLabel = 'estado';

    protected static ?string $pluralModelLabel = 'estados';

    protected static ?int $navigationSort = 20;

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
            Toggle::make('es_cierre')
                ->label('Cierra el ticket'),
            Toggle::make('es_en_proceso')
                ->label('Al responder el cliente')
                ->helperText('El ticket pasa a este estado cuando responde el cliente.'),
            Toggle::make('es_pendiente_cliente')
                ->label('Al responder soporte')
                ->helperText('El ticket pasa a este estado cuando responde un administrador.'),
            Toggle::make('activo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->badge()
                    ->color(fn (TicketEstado $record) => $record->color()),
                TextColumn::make('orden')
                    ->sortable(),
                IconColumn::make('es_default')
                    ->label('Por defecto')
                    ->boolean(),
                IconColumn::make('es_cierre')
                    ->label('Cierre')
                    ->boolean(),
                IconColumn::make('activo')
                    ->boolean(),
                TextColumn::make('tickets_count')
                    ->label('Tickets')
                    ->counts('tickets'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (TicketEstado $record) => $record->tickets()->exists()),
            ])
            ->defaultSort('orden')
            ->reorderable('orden');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTicketEstados::route('/'),
        ];
    }
}
