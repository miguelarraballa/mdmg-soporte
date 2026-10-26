<?php

namespace App\Filament\Pages;

use App\Services\ActualizacionService;
use App\Support\Actualizador;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use RuntimeException;
use Throwable;

/**
 * Instala la última release desde el panel. Cada paso es una petición distinta (wire:init y luego
 * $wire.siguiente() tras cada uno), para no topar con el max_execution_time del hosting y mostrar el
 * progreso. El último paso redirige a public/actualizar.php, que sustituye el código.
 */
class ActualizarAplicacion extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'configuracion/actualizar';

    protected static ?string $title = 'Actualizar la aplicación';

    protected string $view = 'filament.pages.actualizar-aplicacion';

    public const PASOS = [
        'comprobar' => 'Comprobar el servidor',
        'descargar' => 'Descargar la versión',
        'verificar' => 'Verificar la firma',
        'extraer' => 'Extraer los archivos',
        'instalar' => 'Sustituir el código',
    ];

    public ?string $version = null;

    public int $paso = 0;

    public ?string $error = null;

    /** @var list<string> */
    public array $pendientes = [];

    public function mount(): void
    {
        ActualizacionService::olvidar();

        if (! ActualizacionService::hayActualizacion()) {
            Notification::make()->title('No hay ninguna actualización disponible')->warning()->send();
            $this->redirect(Configuracion::getUrl());

            return;
        }

        $this->version = ActualizacionService::ultima();
    }

    public function getSubheading(): ?string
    {
        return $this->version ? 'De v'.ActualizacionService::instalada()." a v{$this->version}. No cierres esta página hasta que termine." : null;
    }

    public function siguiente(): void
    {
        if ($this->error !== null || $this->version === null || $this->paso >= count(self::PASOS)) {
            return;
        }

        try {
            $release = ActualizacionService::release();

            if (($release['version'] ?? null) !== $this->version) {
                throw new RuntimeException('La última release ha cambiado mientras se actualizaba. Vuelve a empezar.');
            }

            match (array_keys(self::PASOS)[$this->paso]) {
                'comprobar' => $this->comprobar($release),
                'descargar' => Actualizador::descargar($release),
                'verificar' => Actualizador::verificar($this->version),
                'extraer' => Actualizador::extraer($this->version),
                'instalar' => $this->redirect(Actualizador::prepararActualizacion($this->version, Configuracion::getUrl())),
            };
        } catch (Throwable $e) {
            report($e);
            $this->error = $e->getMessage();

            return;
        }

        $this->paso++;

        if ($this->paso < count(self::PASOS)) {
            $this->js('$wire.siguiente()');
        }
    }

    /** @param  array{version: string, zip: string, firma: ?string, tamano: int}  $release */
    private function comprobar(array $release): void
    {
        $this->pendientes = Actualizador::requisitosPendientes();

        if (blank($release['firma'])) {
            $this->pendientes[] = "La release v{$release['version']} no está firmada.";
        }

        if ($this->pendientes !== []) {
            throw new RuntimeException('No se puede actualizar desde aquí. Actualiza por FTP o corrige lo siguiente:');
        }
    }
}
