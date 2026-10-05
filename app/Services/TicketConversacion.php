<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Renderiza el hilo de mensajes de un ticket, del más reciente al más antiguo
 * (como en el correo), cada uno en su caja: azul para el cliente y ámbar para
 * soporte. Estilos en resources/views/tickets/estilos.blade.php.
 */
class TicketConversacion
{
    public static function html(Ticket $ticket, bool $vistaCliente): HtmlString
    {
        if ($ticket->mensajes->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-500">Sin mensajes.</p>');
        }

        $mensajes = $ticket->mensajes->sortByDesc(fn ($m) => [$m->created_at, $m->id])->values();

        $html = '<div class="tk-hilo">';

        foreach ($mensajes as $posicion => $mensaje) {
            $esCliente = $mensaje->autor_tipo === 'cliente';

            $autor = match (true) {
                $vistaCliente && $esCliente => 'Tú',
                $vistaCliente => 'Equipo de soporte',
                default => $mensaje->autor?->name ?? ($esCliente ? 'Cliente' : 'Soporte'),
            };

            $html .= sprintf(
                '<article class="tk-msg %s"><header class="tk-msg__cabecera"><span class="tk-msg__autor">%s</span><span class="tk-msg__rol">%s</span>%s<time class="tk-msg__fecha" datetime="%s">%s</time></header><div class="tk-msg__cuerpo"><div class="fi-prose">%s</div>%s</div></article>',
                $esCliente ? 'tk-msg--cliente' : 'tk-msg--soporte',
                e($autor),
                $esCliente ? 'Cliente' : 'Soporte',
                $posicion === 0 && $mensajes->count() > 1 ? '<span class="tk-msg__ultimo">Último</span>' : '',
                $mensaje->created_at->toIso8601String(),
                $mensaje->created_at->format('d-m-Y H:i'),
                Str::sanitizeHtml($mensaje->cuerpo),
                TicketAdjuntoService::html($mensaje),
            );
        }

        return new HtmlString($html . '</div>');
    }
}
