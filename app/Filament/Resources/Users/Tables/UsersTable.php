<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Rol;
use App\Models\User;
use App\Services\UsuarioService;
use Filament\Actions\Action;
use App\Services\SlackService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rol')
                    ->label('Rol')
                    ->badge()
                    ->sortable(),
                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('servicios.nombre')
                    ->label('Servicios')
                    ->badge()
                    ->color('gray')
                    ->limitList(3)
                    ->placeholder('—'),
                IconColumn::make('slack')
                    ->label('Slack')
                    ->state(fn (User $record) => $record->tieneSlack())
                    ->tooltip(fn (User $record) => $record->slack_user_id)
                    ->boolean()
                    ->trueIcon('heroicon-o-chat-bubble-oval-left-ellipsis')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray'),
                TextColumn::make('tickets_count')
                    ->label('Tickets')
                    ->counts('tickets')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Alta')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('rol')
                    ->label('Rol')
                    ->options(Rol::class),
                TernaryFilter::make('activo')
                    ->label('Activo'),
                SelectFilter::make('servicios')
                    ->label('Servicio')
                    ->relationship('servicios', 'nombre')
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('reenviar_bienvenida')
                    ->label('Reenviar acceso')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Se enviará un email con un enlace para establecer una nueva contraseña.')
                    ->action(function (User $record) {
                        UsuarioService::enviarBienvenida($record);
                        Notification::make()->title('Email de acceso en cola')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('vincular_slack')
                        ->label('Vincular Slack por email')
                        ->icon('heroicon-o-link')
                        ->visible(fn () => SlackService::configurado())
                        ->requiresConfirmation()
                        ->modalDescription('Busca en Slack, por su email, a los clientes seleccionados que aún no tienen ID de Slack.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $pendientes = $records->filter(fn (User $u) => $u->rol === Rol::Cliente && ! $u->tieneSlack());
                            $vinculados = 0;

                            try {
                                foreach ($pendientes as $user) {
                                    $vinculados += UsuarioService::vincularSlack($user) ? 1 : 0;
                                }
                            } catch (Throwable $e) {
                                Notification::make()->title('Error al consultar Slack')->body($e->getMessage())->danger()->persistent()->send();

                                return;
                            }

                            Notification::make()
                                ->title("Vinculados con Slack: {$vinculados} de {$pendientes->count()}")
                                ->body($vinculados < $pendientes->count() ? 'Los demás no tienen cuenta en el espacio de trabajo con ese email.' : null)
                                ->status($vinculados === $pendientes->count() ? 'success' : 'warning')
                                ->send();
                        }),
                    DeleteBulkAction::make()
                        // No se borra a uno mismo aunque esté seleccionado.
                        ->using(fn (Collection $records) => $records->reject(fn (User $u) => $u->is(auth()->user()))->each->delete()),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
