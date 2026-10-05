<?php

namespace App\Filament\Resources\Servicios;

use App\Filament\Resources\Servicios\Pages\CreateServicio;
use App\Filament\Resources\Servicios\Pages\EditServicio;
use App\Filament\Resources\Servicios\Pages\ListServicios;
use App\Filament\Resources\Servicios\RelationManagers\ClientesRelationManager;
use App\Models\Servicio;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ServicioResource extends Resource
{
    protected static ?string $model = Servicio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $modelLabel = 'servicio';

    protected static ?string $pluralModelLabel = 'servicios';

    protected static ?string $slug = 'servicios';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre')
                ->required()
                ->maxLength(255),
            Toggle::make('activo')
                ->helperText('Los servicios inactivos no se pueden elegir al abrir un ticket.')
                ->default(true)
                ->inline(false),
            Textarea::make('descripcion')
                ->label('Descripción')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Servicio $record) => str($record->descripcion)->limit(80)),
                IconColumn::make('activo')
                    ->boolean(),
                TextColumn::make('clientes_count')
                    ->label('Clientes')
                    ->counts('clientes')
                    ->sortable(),
                TextColumn::make('tickets_count')
                    ->label('Tickets')
                    ->counts('tickets')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('activo'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Los tickets de este servicio se conservan, pero quedarán sin servicio asignado.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nombre');
    }

    public static function getRelations(): array
    {
        return [
            ClientesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServicios::route('/'),
            'create' => CreateServicio::route('/create'),
            'edit' => EditServicio::route('/{record}/edit'),
        ];
    }
}
