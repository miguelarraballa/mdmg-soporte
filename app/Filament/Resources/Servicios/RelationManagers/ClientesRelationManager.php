<?php

namespace App\Filament\Resources\Servicios\RelationManagers;

use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Clientes que tienen asignado el servicio.
 */
class ClientesRelationManager extends RelationManager
{
    protected static string $relationship = 'clientes';

    protected static ?string $title = 'Clientes';

    protected static ?string $modelLabel = 'cliente';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (User $record) => "{$record->name} ({$record->email})")
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('pivot.created_at')
                    ->label('Asignado')
                    ->dateTime('d-m-Y'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Asignar clientes')
                    ->multiple()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->clientes()->orderBy('name')),
            ])
            ->recordActions([
                DetachAction::make()->label('Quitar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()->label('Quitar seleccionados'),
                ]),
            ])
            ->defaultSort('name');
    }
}
