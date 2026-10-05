<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Rol;
use App\Filament\Resources\Users\UserResource;
use App\Services\UsuarioService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $rol = $data['rol'] instanceof Rol ? $data['rol'] : Rol::from($data['rol']);

        $user = UsuarioService::crear(
            nombre: $data['name'],
            email: $data['email'],
            rol: $rol,
            password: $data['password'] ?? null,
            enviarBienvenida: (bool) ($data['enviar_bienvenida'] ?? false),
        );

        if (filled($data['slack_user_id'] ?? null)) {
            $user->update(['slack_user_id' => $data['slack_user_id']]);
        }

        return $user;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
