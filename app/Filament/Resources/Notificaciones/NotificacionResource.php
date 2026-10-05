<?php

namespace App\Filament\Resources\Notificaciones;

use App\Enums\NotificacionEstado;
use App\Filament\Resources\Notificaciones\Pages\ListNotificaciones;
use App\Models\Notificacion;
use App\Services\NotificacionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Cola de notificaciones (email y Slack): solo lectura, con opción de reintentar los fallidos.
 */
class NotificacionResource extends Resource
{
    protected static ?string $model = Notificacion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Cola de notificaciones';

    protected static ?string $modelLabel = 'notificación';

    protected static ?string $pluralModelLabel = 'cola de notificaciones';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'notificaciones';

    public static function getNavigationBadge(): ?string
    {
        $errores = Notificacion::where('estado', NotificacionEstado::Error)
            ->where('intentos', '>=', NotificacionService::MAX_INTENTOS)
            ->count();

        return $errores ? (string) $errores : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextEntry::make('destino')->label('Destinatario')->state(fn (Notificacion $record) => $record->destino()),
                TextEntry::make('estado')->badge(),
                TextEntry::make('asunto')->columnSpanFull(),
                TextEntry::make('created_at')->label('En cola desde')->dateTime('d-m-Y H:i:s'),
                TextEntry::make('enviado_en')->label('Enviado')->dateTime('d-m-Y H:i:s')->placeholder('—'),
                TextEntry::make('intentos'),
                TextEntry::make('error')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Mensaje de Slack')->visible(fn (Notificacion $record) => $record->esSlack())->schema([
                TextEntry::make('payload.text')
                    ->hiddenLabel()
                    ->fontFamily('mono')
                    ->extraAttributes(['style' => 'white-space: pre-wrap']),
            ]),
            Section::make('Contenido')->visible(fn (Notificacion $record) => ! $record->esSlack())->schema([
                TextEntry::make('cuerpo_html')
                    ->hiddenLabel()
                    // Vista previa aislada en un iframe para que los estilos del email no afecten al panel.
                    ->state(fn (Notificacion $record) => new HtmlString(
                        '<iframe sandbox srcdoc="' . e($record->cuerpo_html) . '" style="width:100%;height:520px;border:0;border-radius:8px;background:#fff"></iframe>'
                    )),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                TextColumn::make('canal')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'slack' ? 'Slack' : 'Email')
                    ->color(fn (string $state) => $state === 'slack' ? 'info' : 'gray'),
                TextColumn::make('email_destinatario')
                    ->label('Destinatario')
                    ->state(fn (Notificacion $record) => $record->destino())
                    ->searchable(),
                TextColumn::make('asunto')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('estado')
                    ->badge(),
                TextColumn::make('intentos')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('error')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options(NotificacionEstado::class),
                SelectFilter::make('canal')
                    ->options(['email' => 'Email', 'slack' => 'Slack']),
            ])
            ->recordActions([
                ViewAction::make()->modalWidth('4xl'),
                Action::make('reintentar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Notificacion $record) => $record->estado === NotificacionEstado::Error)
                    ->action(function (Notificacion $record) {
                        NotificacionService::reintentar($record);
                        Notification::make()->title('Notificación vuelta a poner en cola')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('reintentar')
                        ->label('Reintentar')
                        ->icon('heroicon-o-arrow-path')
                        ->action(fn (Collection $records) => $records
                            ->filter(fn (Notificacion $n) => $n->estado === NotificacionEstado::Error)
                            ->each(fn (Notificacion $n) => NotificacionService::reintentar($n))),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificaciones::route('/'),
        ];
    }
}
