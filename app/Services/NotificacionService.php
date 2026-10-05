<?php

namespace App\Services;

use App\Enums\NotificacionEstado;
use App\Mail\NotificacionEmail;
use App\Models\Notificacion;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cola de emails de notificación. Los emails se renderizan al encolarse y se
 * guardan en la tabla `notificaciones`; el comando `notificaciones:enviar`
 * (programado cada minuto) los envía y registra el resultado.
 */
class NotificacionService
{
    public const MAX_INTENTOS = 3;

    /**
     * @param  array{evento?: ?string, nombre: string, parrafos: array<string>, cita?: ?string, adjuntos?: ?array<string>, boton?: ?array{url: string, texto: string}}  $contenido
     *         Los párrafos se insertan como HTML: escapa con e() cualquier dato de usuario.
     *         La cita es el HTML del mensaje del ticket y se sanea aquí.
     */
    public static function encolar(
        string $tipo,
        string $email,
        string $asunto,
        array $contenido,
        ?Ticket $ticket = null,
        ?User $user = null,
    ): ?Notificacion {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Notificación descartada: email no válido', ['tipo' => $tipo, 'email' => $email]);

            return null;
        }

        return Notificacion::create([
            'tipo' => $tipo,
            'email_destinatario' => $email,
            'asunto' => $asunto,
            'cuerpo_html' => view('emails.notificacion', [
                'titulo' => $asunto,
                'evento' => null,
                'boton' => null,
                'adjuntos' => null,
                ...$contenido,
                'cita' => filled($contenido['cita'] ?? null) ? Str::sanitizeHtml($contenido['cita']) : null,
            ])->render(),
            'estado' => NotificacionEstado::EnCola,
            'ticket_id' => $ticket?->id,
            'user_id' => $user?->id,
        ]);
    }

    /**
     * Encola un mensaje directo de Slack para el usuario. Su ID de Slack se lee
     * en el momento del envío, por si se ha corregido mientras estaba en cola.
     */
    public static function encolarSlack(string $tipo, User $user, string $resumen, string $texto, ?Ticket $ticket = null): ?Notificacion
    {
        if (! $user->tieneSlack()) {
            return null;
        }

        return Notificacion::create([
            'tipo' => $tipo,
            'canal' => 'slack',
            'asunto' => Str::limit($resumen, 250),
            'payload' => ['text' => $texto],
            'estado' => NotificacionEstado::EnCola,
            'ticket_id' => $ticket?->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Notificaciones pendientes: las nuevas y las fallidas que aún tienen intentos.
     */
    public static function pendientes(int $limite)
    {
        return Notificacion::query()
            ->where(fn ($q) => $q
                ->where('estado', NotificacionEstado::EnCola)
                ->orWhere(fn ($q) => $q
                    ->where('estado', NotificacionEstado::Error)
                    ->where('intentos', '<', self::MAX_INTENTOS)))
            ->orderBy('created_at')
            ->limit($limite)
            ->get();
    }

    public static function enviar(Notificacion $notificacion): bool
    {
        $notificacion->increment('intentos');

        try {
            $notificacion->esSlack()
                ? self::enviarSlack($notificacion)
                : Mail::to($notificacion->email_destinatario)->send(new NotificacionEmail($notificacion));

            $notificacion->update([
                'estado' => NotificacionEstado::Enviado,
                'enviado_en' => now(),
                'error' => null,
            ]);

            return true;
        } catch (Throwable $e) {
            $notificacion->update([
                'estado' => NotificacionEstado::Error,
                'error' => $e->getMessage(),
            ]);

            Log::warning('Error al enviar notificación', ['id' => $notificacion->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private static function enviarSlack(Notificacion $notificacion): void
    {
        $slackUserId = $notificacion->user?->slack_user_id;

        if (blank($slackUserId)) {
            throw new \RuntimeException('El usuario ya no tiene vinculado su usuario de Slack.');
        }

        SlackService::mensajeDirecto($slackUserId, $notificacion->payload['text']);
    }

    /**
     * Vuelve a poner en cola una notificación (reinicia los intentos).
     */
    public static function reintentar(Notificacion $notificacion): void
    {
        $notificacion->update([
            'estado' => NotificacionEstado::EnCola,
            'intentos' => 0,
            'error' => null,
        ]);
    }
}
