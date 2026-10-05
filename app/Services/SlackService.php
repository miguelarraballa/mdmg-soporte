<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bot de Slack de nuestro espacio de trabajo. Envía mensajes directos a los
 * clientes y busca su ID por email. Requiere SLACK_BOT_USER_OAUTH_TOKEN con
 * los permisos chat:write, users:read y users:read.email.
 */
class SlackService
{
    // Admite espacios alrededor y minúsculas (se normaliza a mayúsculas al guardar).
    public const PATRON_ID_USUARIO = '/^\s*[UW][A-Z0-9]{6,30}\s*$/i';

    private const API = 'https://slack.com/api/';

    /**
     * Cómo resolver los errores de configuración más habituales de la app de Slack.
     */
    private const AYUDA_ERRORES = [
        'messages_tab_disabled' => 'Activa la pestaña «Messages Tab» en App Home de la app de Slack.',
        'missing_scope' => 'Al bot le falta algún permiso (chat:write, users:read, users:read.email): añádelo en OAuth & Permissions y reinstala la app.',
        'not_authed' => 'Falta el token: revisa SLACK_BOT_USER_OAUTH_TOKEN en el .env.',
        'invalid_auth' => 'El token no es válido: copia de nuevo el Bot User OAuth Token (xoxb-…) en el .env.',
        'token_revoked' => 'El token se ha revocado: reinstala la app y copia el nuevo token al .env.',
        'account_inactive' => 'La app o el token están desactivados: reinstala la app.',
        'channel_not_found' => 'El ID de Slack del cliente no existe en el espacio de trabajo: revísalo o usa «Buscar por email».',
        'user_not_found' => 'El ID de Slack del cliente no existe en el espacio de trabajo: revísalo o usa «Buscar por email».',
        'cannot_dm_bot' => 'El ID de Slack corresponde a un bot, no a una persona.',
        'ratelimited' => 'Slack ha limitado temporalmente los envíos; se reintentará.',
    ];

    public static function configurado(): bool
    {
        return filled(config('services.slack.notifications.bot_user_oauth_token'));
    }

    /**
     * Envía un mensaje directo (del bot) al usuario de Slack indicado.
     */
    public static function mensajeDirecto(string $slackUserId, string $texto): void
    {
        self::llamar('chat.postMessage', [
            'channel' => $slackUserId,
            'text' => $texto,
            'unfurl_links' => false,
            'unfurl_media' => false,
        ]);
    }

    /**
     * ID del usuario del espacio de trabajo con ese email, o null si no existe.
     */
    public static function buscarPorEmail(string $email): ?string
    {
        try {
            return self::llamar('users.lookupByEmail', ['email' => $email], get: true)['user']['id'] ?? null;
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'users_not_found')) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * La API de Slack responde 200 también en los errores ({"ok": false, "error": "..."}).
     */
    private static function llamar(string $metodo, array $datos, bool $get = false): array
    {
        $token = config('services.slack.notifications.bot_user_oauth_token');

        if (blank($token)) {
            throw new RuntimeException('Slack no está configurado: falta SLACK_BOT_USER_OAUTH_TOKEN en el .env.');
        }

        $peticion = Http::withToken($token)->timeout(10)->acceptJson();
        $respuesta = $get
            ? $peticion->get(self::API . $metodo, $datos)
            : $peticion->asJson()->post(self::API . $metodo, $datos);

        $respuesta->throw();

        if (! $respuesta->json('ok')) {
            $error = $respuesta->json('error') ?? 'respuesta no válida';
            $ayuda = self::AYUDA_ERRORES[$error] ?? null;

            throw new RuntimeException("Slack ({$metodo}): {$error}" . ($ayuda ? ". {$ayuda}" : ''));
        }

        return $respuesta->json();
    }
}
