<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Suscripción Web Push de un navegador. El navegador la crea al activar los
 * avisos desde el panel y se borra cuando el servicio push la da por caducada.
 */
class PushSuscripcion extends Model
{
    protected $table = 'push_suscripciones';

    protected $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'clave_p256dh',
        'clave_auth',
        'navegador',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Servicios push de los navegadores: Chrome/Edge/Opera (FCM), Firefox (Mozilla),
     * Safari (Apple) y Edge heredado (WNS).
     */
    public const SERVICIOS = [
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        'push.services.mozilla.com',
        'web.push.apple.com',
        'notify.windows.com',
    ];

    public static function endpointPermitido(string $endpoint): bool
    {
        if (parse_url($endpoint, PHP_URL_SCHEME) !== 'https') {
            return false;
        }

        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));

        foreach (self::SERVICIOS as $servicio) {
            if ($host === $servicio || str_ends_with($host, ".{$servicio}")) {
                return true;
            }
        }

        return false;
    }

    public static function hash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
