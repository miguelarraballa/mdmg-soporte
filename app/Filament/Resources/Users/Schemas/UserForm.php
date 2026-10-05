<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Rol;
use App\Services\SlackService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Throwable;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('rol')
                    ->label('Rol')
                    ->options(Rol::class)
                    ->default(Rol::Cliente)
                    ->required()
                    ->live()
                    // Un administrador no puede quitarse a sí mismo el rol.
                    ->disabled(fn ($record) => $record?->is(auth()->user())),
                Toggle::make('activo')
                    ->label('Activo')
                    ->helperText('Un usuario inactivo no puede iniciar sesión.')
                    ->default(true)
                    ->disabled(fn ($record) => $record?->is(auth()->user()))
                    ->inline(false),
                Select::make('servicios')
                    ->label('Servicios')
                    ->relationship('servicios', 'nombre')
                    ->multiple()
                    ->preload()
                    ->helperText('Servicios sobre los que el cliente puede abrir tickets.')
                    ->visible(fn (Get $get) => in_array($get('rol'), [Rol::Cliente, Rol::Cliente->value], true))
                    ->columnSpanFull(),
                TextInput::make('slack_user_id')
                    ->label('ID de usuario de Slack')
                    ->placeholder('U0123ABCD')
                    ->regex(SlackService::PATRON_ID_USUARIO)
                    ->validationMessages(['regex' => 'Debe ser un ID de miembro de Slack (por ejemplo U0123ABCD).'])
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Str::upper(trim($state)) : null)
                    ->helperText('Opcional. Los avisos de sus tickets se le enviarán también por mensaje directo de Slack. '
                        . 'En Slack: su perfil → ⋮ → «Copiar ID de miembro», o usa «Buscar por email».')
                    ->hintAction(
                        Action::make('buscar_slack')
                            ->label('Buscar por email')
                            ->icon('heroicon-o-magnifying-glass')
                            ->visible(fn () => SlackService::configurado())
                            ->action(function (Get $get, Set $set) {
                                try {
                                    $id = filled($get('email')) ? SlackService::buscarPorEmail($get('email')) : null;
                                } catch (Throwable $e) {
                                    Notification::make()->title('Error al consultar Slack')->body($e->getMessage())->danger()->send();

                                    return;
                                }

                                if (! $id) {
                                    Notification::make()->title('No hay ningún usuario de Slack con ese email')->warning()->send();

                                    return;
                                }

                                $set('slack_user_id', $id);
                                Notification::make()->title("Encontrado: {$id}")->body('Guarda el formulario para vincularlo.')->success()->send();
                            })
                    )
                    ->visible(fn (Get $get) => in_array($get('rol'), [Rol::Cliente, Rol::Cliente->value], true))
                    ->columnSpanFull(),
                TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'create'
                        ? 'Opcional. Si la dejas vacía, el usuario recibirá un email para establecerla.'
                        : 'Déjala vacía para no cambiarla.'),
                Toggle::make('enviar_bienvenida')
                    ->label('Enviar email de bienvenida')
                    ->helperText('Incluye un enlace para establecer la contraseña.')
                    ->default(true)
                    ->visibleOn('create')
                    ->inline(false),
            ]);
    }
}
