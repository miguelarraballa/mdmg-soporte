<?php

namespace App\Http\Controllers;

use App\Models\PushSuscripcion;
use App\Services\PushService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Alta y baja de la suscripción push del navegador del usuario (botón de la campana del panel).
 */
class PushSuscripcionController
{
    public function store(Request $request): Response
    {
        $datos = $request->validate([
            'endpoint' => [
                'required', 'string', 'max:2048', 'url:https',
                // El servidor hará peticiones a esta URL: solo se aceptan servicios push conocidos.
                fn (string $atributo, mixed $valor, \Closure $fallo) => PushSuscripcion::endpointPermitido($valor)
                    ? null
                    : $fallo('Servicio de notificaciones push no admitido.'),
            ],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        PushService::suscribir($request->user(), $datos, $request->userAgent());

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $datos = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        PushService::desuscribir($request->user(), $datos['endpoint']);

        return response()->noContent();
    }
}
