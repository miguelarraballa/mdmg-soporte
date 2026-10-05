<?php

namespace App\Filament\Resources\Tickets\Tables;

use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\TicketCategoria;
use App\Models\TicketEstado;
use App\Models\TicketPrioridad;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->formatStateUsing(fn (Ticket $record) => $record->numero())
                    // Permite buscar por "000015" o "15".
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('tickets.id', (int) ltrim($search, '0#')))
                    ->sortable(),
                TextColumn::make('cliente.name')
                    ->label('Cliente')
                    ->description(fn ($record) => $record->cliente?->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),
                TextColumn::make('asunto')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('servicio.nombre')
                    ->label('Servicio')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('categoria.nombre')
                    ->label('Categoría')
                    ->badge()
                    ->color(fn ($record) => $record->categoria?->color())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('prioridad.nombre')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn ($record) => $record->prioridad?->color()),
                TextColumn::make('estado.nombre')
                    ->label('Estado')
                    ->badge()
                    ->color(fn ($record) => $record->estado?->color()),
                TextColumn::make('mensajes_count')
                    ->label('Mensajes')
                    ->counts('mensajes'),
                TextColumn::make('ultima_actividad_en')
                    ->label('Última actividad')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('abiertos')
                    ->label('Solo abiertos')
                    ->query(fn (Builder $query) => $query->abiertos())
                    ->default(),
                SelectFilter::make('servicio_id')
                    ->label('Servicio')
                    ->options(fn () => Servicio::orderBy('nombre')->pluck('nombre', 'id')),
                SelectFilter::make('ticket_estado_id')
                    ->label('Estado')
                    ->options(fn () => TicketEstado::orderBy('orden')->pluck('nombre', 'id')),
                SelectFilter::make('ticket_prioridad_id')
                    ->label('Prioridad')
                    ->options(fn () => TicketPrioridad::orderBy('orden')->pluck('nombre', 'id')),
                SelectFilter::make('ticket_categoria_id')
                    ->label('Categoría')
                    ->options(fn () => TicketCategoria::orderBy('orden')->pluck('nombre', 'id')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('ultima_actividad_en', 'desc');
    }
}
