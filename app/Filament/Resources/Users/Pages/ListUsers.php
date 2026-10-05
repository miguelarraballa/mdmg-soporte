<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\SlackService;
use App\Services\UsuarioService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Text;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importar_csv')
                ->label('Importar CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Importar usuarios desde CSV')
                ->modalSubmitActionLabel('Importar')
                ->schema([
                    Text::make(new HtmlString(
                        'El archivo debe tener una fila de cabecera con las columnas <strong>nombre</strong> y <strong>email</strong>, '
                        . 'y opcionalmente <strong>rol</strong> (<code>admin</code> o <code>cliente</code>; por defecto, cliente) '
                        . 'y <strong>slack_id</strong> (ID de miembro de Slack del cliente, U0123ABCD; solo para clientes). '
                        . 'Separador: coma o punto y coma. Las filas con errores se omiten y se informa de ellas.'
                    )),
                    FileUpload::make('archivo')
                        ->label('Archivo CSV')
                        ->disk('local')
                        ->directory('importaciones')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->maxSize(2048)
                        ->required(),
                    Toggle::make('enviar_bienvenida')
                        ->label('Enviar email de bienvenida a cada usuario')
                        ->default(true),
                    Toggle::make('vincular_slack')
                        ->label('Buscar en Slack por email a los clientes sin slack_id')
                        ->default(true)
                        ->visible(fn () => SlackService::configurado()),
                ])
                ->action(function (array $data) {
                    $ruta = Storage::disk('local')->path($data['archivo']);

                    try {
                        $resultado = UsuarioService::importarCsv($ruta, (bool) $data['enviar_bienvenida'], (bool) ($data['vincular_slack'] ?? false));
                    } finally {
                        Storage::disk('local')->delete($data['archivo']);
                    }

                    $errores = collect($resultado['errores'])
                        ->map(fn ($error, $linea) => "Línea {$linea}: " . e($error))
                        ->take(10)
                        ->implode('<br>');

                    if (count($resultado['errores']) > 10) {
                        $errores .= '<br>… y ' . (count($resultado['errores']) - 10) . ' errores más.';
                    }

                    Notification::make()
                        ->title("Usuarios creados: {$resultado['creados']}" . ($resultado['slack_vinculados'] ? " · con Slack: {$resultado['slack_vinculados']}" : ''))
                        ->body(filled($errores) ? new HtmlString("Filas omitidas:<br>{$errores}") : null)
                        ->status(match (true) {
                            empty($resultado['errores']) => 'success',
                            $resultado['creados'] > 0 => 'warning',
                            default => 'danger',
                        })
                        ->persistent(filled($errores))
                        ->send();
                }),
            Action::make('plantilla_csv')
                ->label('Ejemplo CSV')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->modalHeading('Ejemplo de CSV')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->schema([
                    Text::make(new HtmlString('<pre style="font-size:0.8125rem;white-space:pre-wrap;word-break:break-all">nombre,email,rol,slack_id' . "\n" . 'Ana García,ana@example.com,cliente,U0123ABCD' . "\n" . 'Luis Pérez,luis@example.com,cliente,' . "\n" . 'Marta Ruiz,marta@example.com,admin,</pre>'
                        . '<p style="font-size:0.8125rem">Las columnas <strong>rol</strong> y <strong>slack_id</strong> son opcionales; pueden omitirse o dejarse vacías. Sin slack_id, se puede buscar a cada cliente en Slack por su email.</p>')),
                ]),
            CreateAction::make(),
        ];
    }
}
