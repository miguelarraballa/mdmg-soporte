<?php

namespace App\Services;

use App\Filament\Portal\Resources\Tickets\TicketResource as PortalTicketResource;
use App\Filament\Resources\Tickets\TicketResource as AdminTicketResource;
use App\Models\Ticket;
use App\Models\TicketMensaje;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Encola los avisos de los eventos de un ticket: al cliente cuando soporte
 * actúa y a los administradores cuando actúa el cliente. Además del email
 * (y Slack), los tickets urgentes avisan con notificación push.
 */
class TicketNotificador
{
    public static function ticketAbierto(Ticket $ticket, TicketMensaje $mensaje): void
    {
        self::alCliente($ticket, 'ticket_abierto', 'Hemos recibido tu ticket', [
            'Hemos registrado tu consulta <strong>'.e($ticket->asunto).'</strong>'
                .($ticket->servicio ? ' sobre el servicio <strong>'.e($ticket->servicio->nombre).'</strong>' : '')
                .'. Te responderemos lo antes posible.',
        ], $mensaje);
    }

    public static function ticketRespondido(Ticket $ticket, TicketMensaje $mensaje): void
    {
        self::alCliente($ticket, 'ticket_respuesta', 'Nueva respuesta en tu ticket', [
            'Hay una nueva respuesta en tu ticket <strong>'.e($ticket->asunto).'</strong>.',
            'Estado actual: <strong>'.e($ticket->estado?->nombre).'</strong>',
        ], $mensaje);
    }

    public static function ticketCerrado(Ticket $ticket, TicketMensaje $mensaje): void
    {
        self::alCliente($ticket, 'ticket_cerrado', 'Tu ticket se ha cerrado', [
            'Tu ticket <strong>'.e($ticket->asunto).'</strong> se ha cerrado.',
            'Si necesitas algo más, puedes responder en el ticket para reabrirlo o crear uno nuevo.',
        ], $mensaje);
    }

    public static function ticketNuevoAdmin(Ticket $ticket, TicketMensaje $mensaje): void
    {
        self::aAdmins($ticket, 'ticket_nuevo_admin', 'Nuevo ticket', [
            '<strong>'.e($ticket->cliente?->name).'</strong> ha abierto el ticket <strong>'.e($ticket->asunto).'</strong>'
                .($ticket->servicio ? ' (servicio: <strong>'.e($ticket->servicio->nombre).'</strong>)' : '').'.',
        ], $mensaje);
    }

    public static function ticketRespuestaClienteAdmin(Ticket $ticket, TicketMensaje $mensaje): void
    {
        self::aAdmins($ticket, 'ticket_respuesta_cliente_admin', 'Respuesta del cliente', [
            '<strong>'.e($ticket->cliente?->name).'</strong> ha respondido en el ticket <strong>'.e($ticket->asunto).'</strong>.',
        ], $mensaje);
    }

    private static function alCliente(Ticket $ticket, string $tipo, string $titulo, array $parrafos, TicketMensaje $mensaje): void
    {
        $cliente = $ticket->cliente;

        if (! $cliente) {
            return;
        }

        NotificacionService::encolar(
            tipo: $tipo,
            email: $cliente->email,
            asunto: $ticket->asuntoEmail(),
            contenido: [
                'evento' => $titulo,
                'nombre' => $cliente->name,
                'parrafos' => $parrafos,
                'cita' => $mensaje->cuerpo,
                'adjuntos' => array_column($mensaje->adjuntos ?? [], 'nombre'),
                'boton' => [
                    'url' => PortalTicketResource::getUrl('view', ['record' => $ticket], panel: 'portal'),
                    'texto' => 'Ver ticket',
                ],
            ],
            ticket: $ticket,
            user: $cliente,
        );

        if ($cliente->tieneSlack()) {
            NotificacionService::encolarSlack(
                tipo: $tipo,
                user: $cliente,
                resumen: $ticket->asuntoEmail(),
                texto: self::textoSlack($ticket, $titulo, $mensaje),
                ticket: $ticket,
            );
        }

        // Al abrir su propio ticket el cliente recibe la confirmación por email, pero no un push.
        if ($mensaje->autor_tipo === 'agente') {
            self::push($ticket, $cliente, $tipo, $titulo, $mensaje, PortalTicketResource::getUrl('view', ['record' => $ticket], panel: 'portal'));
        }
    }

    /**
     * Notificación push del evento, solo en tickets urgentes.
     */
    private static function push(Ticket $ticket, User $user, string $tipo, string $titulo, TicketMensaje $mensaje, string $url): void
    {
        if (! $ticket->esUrgente()) {
            return;
        }

        NotificacionService::encolarPush(
            tipo: $tipo,
            user: $user,
            titulo: "Urgente · {$titulo}",
            cuerpo: "#{$ticket->numero()} {$ticket->asunto}\n".self::extracto($mensaje->cuerpo),
            url: $url,
            ticket: $ticket,
        );
    }

    /**
     * Texto plano y corto del mensaje (HTML) para el cuerpo del push.
     */
    private static function extracto(?string $html): string
    {
        $texto = html_entity_decode(preg_replace('/<[^>]+>/', ' ', (string) $html), ENT_QUOTES | ENT_HTML5);

        return Str::limit(Str::squish($texto), 140);
    }

    /**
     * Mensaje de Slack: asunto del ticket, tipo de aviso, el mensaje y enlace al portal.
     */
    private static function textoSlack(Ticket $ticket, string $titulo, TicketMensaje $mensaje): string
    {
        $url = PortalTicketResource::getUrl('view', ['record' => $ticket], panel: 'portal');
        $adjuntos = count($mensaje->adjuntos ?? []);

        return collect([
            '*'.SlackMensaje::escapar($ticket->asuntoEmail()).'*',
            SlackMensaje::escapar($titulo).' · Estado: '.SlackMensaje::escapar((string) $ticket->estado?->nombre),
            '',
            SlackMensaje::desdeHtml($mensaje->cuerpo),
            $adjuntos ? "\n📎 {$adjuntos} ".($adjuntos === 1 ? 'adjunto' : 'adjuntos').' (disponibles en el ticket)' : null,
            '',
            "<{$url}|Ver ticket #{$ticket->numero()}>",
        ])->reject(fn ($linea) => $linea === null)->implode("\n");
    }

    private static function aAdmins(Ticket $ticket, string $tipo, string $titulo, array $parrafos, TicketMensaje $mensaje): void
    {
        $url = AdminTicketResource::getUrl('view', ['record' => $ticket], panel: 'admin');

        foreach (User::admins()->get() as $admin) {
            NotificacionService::encolar(
                tipo: $tipo,
                email: $admin->email,
                asunto: $ticket->asuntoEmail(),
                contenido: [
                    'evento' => $titulo,
                    'nombre' => $admin->name,
                    'parrafos' => $parrafos,
                    'cita' => $mensaje->cuerpo,
                    'adjuntos' => array_column($mensaje->adjuntos ?? [], 'nombre'),
                    'boton' => ['url' => $url, 'texto' => 'Abrir en el panel'],
                ],
                ticket: $ticket,
                user: $admin,
            );

            self::push($ticket, $admin, $tipo, $titulo, $mensaje, $url);
        }
    }
}
