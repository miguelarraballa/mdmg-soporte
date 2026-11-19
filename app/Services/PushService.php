<?php

namespace App\Services;

use App\Filament\Marca;
use App\Models\Ajuste;
use App\Models\Notificacion;
use App\Models\PushSuscripcion;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use RuntimeException;

/**
 * Notificaciones push del navegador (Web Push con claves VAPID).
 *
 * Las claves VAPID se generan solas la primera vez y se guardan en `ajustes`,
 * porque en producción no hay consola para generarlas. Si se borran, todas
 * las suscripciones dejan de valer y cada usuario tiene que volver a activarlas.
 */
class PushService
{
    private const AJUSTE_CLAVES = 'push_vapid';

    /**
     * Clave pública VAPID, la que usa el navegador para suscribirse.
     */
    public static function clavePublica(): string
    {
        return self::claves()['publicKey'];
    }

    /**
     * @return array{publicKey: string, privateKey: string}
     */
    private static function claves(): array
    {
        $guardadas = Ajuste::find(self::AJUSTE_CLAVES)?->valor;

        if (blank($guardadas)) {
            // insertOrIgnore: si dos peticiones las generan a la vez, se queda la primera.
            Ajuste::query()->insertOrIgnore([
                'clave' => self::AJUSTE_CLAVES,
                'valor' => json_encode(VAPID::createVapidKeys()),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $guardadas = Ajuste::find(self::AJUSTE_CLAVES)->valor;
        }

        return json_decode($guardadas, true);
    }

    /**
     * Guarda (o reasigna al usuario actual) la suscripción de un navegador.
     *
     * @param  array{endpoint: string, keys: array{p256dh: string, auth: string}}  $datos
     */
    public static function suscribir(User $user, array $datos, ?string $navegador = null): PushSuscripcion
    {
        return PushSuscripcion::updateOrCreate(
            ['endpoint_hash' => PushSuscripcion::hash($datos['endpoint'])],
            [
                'user_id' => $user->id,
                'endpoint' => $datos['endpoint'],
                'clave_p256dh' => $datos['keys']['p256dh'],
                'clave_auth' => $datos['keys']['auth'],
                'navegador' => $navegador ? mb_substr($navegador, 0, 255) : null,
            ],
        );
    }

    public static function desuscribir(User $user, string $endpoint): void
    {
        $user->pushSuscripciones()->where('endpoint_hash', PushSuscripcion::hash($endpoint))->delete();
    }

    /**
     * Contenido del aviso tal como lo recibe el service worker (public/sw-push.js).
     *
     * @return array{title: string, body: string, url: string, tag: ?string, icon: string}
     */
    public static function payload(string $titulo, string $cuerpo, string $url, ?string $tag = null): array
    {
        return [
            'title' => $titulo,
            'body' => $cuerpo,
            'url' => $url,
            'tag' => $tag,
            'icon' => Marca::url('icono') ?? asset('images/marca/apple-touch-icon.png'),
        ];
    }

    /**
     * Envía la notificación a todos los navegadores suscritos del usuario. Borra las
     * suscripciones caducadas y falla solo si no ha llegado a ningún navegador.
     */
    public static function enviar(Notificacion $notificacion): void
    {
        $suscripciones = $notificacion->user?->pushSuscripciones ?? collect();

        if ($suscripciones->isEmpty()) {
            throw new RuntimeException('El usuario no tiene ningún navegador con las notificaciones push activadas.');
        }

        $webPush = self::cliente();
        $payload = json_encode($notificacion->payload);

        foreach ($suscripciones as $suscripcion) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $suscripcion->endpoint,
                'publicKey' => $suscripcion->clave_p256dh,
                'authToken' => $suscripcion->clave_auth,
                'contentEncoding' => 'aes128gcm',
            ]), $payload);
        }

        $entregadas = 0;
        $errores = [];

        foreach ($webPush->flush() as $informe) {
            if ($informe->isSuccess()) {
                $entregadas++;
            } elseif ($informe->isSubscriptionExpired()) {
                PushSuscripcion::where('endpoint_hash', PushSuscripcion::hash($informe->getEndpoint()))->delete();
            } else {
                $errores[] = $informe->getReason();
            }
        }

        if ($entregadas === 0) {
            throw new RuntimeException($errores
                ? implode(' | ', array_unique($errores))
                : 'Las suscripciones push del usuario habían caducado y se han eliminado.');
        }
    }

    private static function cliente(): WebPush
    {
        $url = (string) config('app.url');

        return new WebPush(
            auth: ['VAPID' => [
                // El servicio push exige una URL https o un mailto de contacto.
                'subject' => str_starts_with($url, 'https://') ? $url : 'mailto:'.config('mail.from.address'),
                ...self::claves(),
            ]],
            defaultOptions: ['TTL' => 86400, 'urgency' => 'high'],
            // Con logger, los avisos de la librería (p. ej. sin GMP/BCMath) van al log en vez de lanzar errores.
            logger: Log::getLogger(),
        );
    }
}
