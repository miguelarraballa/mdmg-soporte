<?php

namespace App\Http\Controllers;

use App\Models\TicketMensaje;
use App\Services\TicketAdjuntoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve un adjunto de un mensaje solo a un administrador o al cliente dueño del ticket.
 */
class AdjuntoController
{
    public function __invoke(Request $request, TicketMensaje $mensaje, int $indice): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user->activo && ($user->esAdmin() || $mensaje->ticket->user_id === $user->id), 404);

        $adjunto = $mensaje->adjuntos[$indice] ?? abort(404);
        $disco = Storage::disk(TicketAdjuntoService::DISCO);

        abort_unless($disco->exists($adjunto['ruta']), 404);

        // Imágenes y PDF se abren en el navegador; DOCX y XLSX se descargan.
        $enLinea = TicketAdjuntoService::esImagen($adjunto) || $adjunto['mime'] === 'application/pdf';

        return $disco->response($adjunto['ruta'], $adjunto['nombre'], [
            'Content-Type' => $adjunto['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ], $enLinea ? 'inline' : 'attachment');
    }
}
