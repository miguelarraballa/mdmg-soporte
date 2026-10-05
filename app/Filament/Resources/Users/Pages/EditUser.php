<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\SlackMensaje;
use App\Services\SlackService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('probar_slack')
                ->label('Probar Slack')
                ->icon('heroicon-o-chat-bubble-oval-left-ellipsis')
                ->color('gray')
                ->visible(fn () => $this->record->tieneSlack())
                ->requiresConfirmation()
                ->modalDescription('El bot le enviará ahora un mensaje directo de prueba en Slack.')
                ->action(function () {
                    try {
                        SlackService::mensajeDirecto(
                            $this->record->slack_user_id,
                            '*' . SlackMensaje::escapar(config('app.name')) . '* · Mensaje de prueba: a partir de ahora recibirás aquí los avisos de tus tickets de soporte.',
                        );

                        Notification::make()->title('Mensaje de prueba enviado a Slack')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('No se pudo enviar a Slack')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            DeleteAction::make(),
        ];
    }


    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
