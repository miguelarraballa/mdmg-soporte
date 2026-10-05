<?php

namespace App\Console\Commands;

use App\Services\NotificacionService;
use Illuminate\Console\Command;

class EnviarNotificacionesCommand extends Command
{
    protected $signature = 'notificaciones:enviar {--limit=50 : Número máximo de emails por ejecución}';

    protected $description = 'Envía los emails pendientes de la cola de notificaciones';

    public function handle(): int
    {
        $enviados = 0;
        $errores = 0;

        foreach (NotificacionService::pendientes((int) $this->option('limit')) as $notificacion) {
            NotificacionService::enviar($notificacion) ? $enviados++ : $errores++;
        }

        if ($enviados || $errores) {
            $this->info("Notificaciones: {$enviados} enviadas, {$errores} con error.");
        }

        return self::SUCCESS;
    }
}
