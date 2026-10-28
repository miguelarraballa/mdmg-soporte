<?php

namespace App\Http\Controllers;

use App\Models\Manual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve el index.html de un manual a un administrador o a un cliente con su servicio.
 * El HTML lo sube un administrador pero se trata como no confiable: la cabecera CSP
 * "sandbox" (sin allow-same-origin) lo aísla en un origen propio, así que sus scripts
 * no pueden leer la sesión ni actuar en la aplicación en nombre del usuario.
 */
class ManualController
{
    public function __invoke(Request $request, Manual $manual): StreamedResponse
    {
        abort_unless($manual->esVisiblePara($request->user()), 404);

        $disco = Storage::disk(Manual::DISCO);

        abort_unless($disco->exists($manual->archivo), 404);

        return $disco->response($manual->archivo, 'index.html', [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => 'sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox allow-modals allow-downloads',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-cache',
        ], 'inline');
    }
}
