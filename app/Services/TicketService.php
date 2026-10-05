<?php

namespace App\Services;

use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\TicketCategoria;
use App\Models\TicketEstado;
use App\Models\TicketMensaje;
use App\Models\TicketPrioridad;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TicketService
{
    /**
     * Crea un ticket con su primer mensaje. Si lo abre un administrador en nombre
     * del cliente ($autor admin), se avisa al cliente; si lo abre el cliente,
     * se le confirma la recepción y se avisa a los administradores.
     */
    public static function crear(
        User $cliente,
        Servicio $servicio,
        string $asunto,
        string $mensaje,
        User $autor,
        ?TicketCategoria $categoria = null,
        ?TicketPrioridad $prioridad = null,
        ?array $adjuntos = null,
    ): Ticket {
        self::comprobarServicio($cliente, $servicio);

        return DB::transaction(function () use ($cliente, $servicio, $asunto, $mensaje, $autor, $categoria, $prioridad, $adjuntos) {
            $esAgente = $autor->esAdmin();

            $ticket = Ticket::create([
                'user_id' => $cliente->id,
                'servicio_id' => $servicio->id,
                'ticket_categoria_id' => ($categoria ?? TicketCategoria::default())?->id,
                'ticket_estado_id' => TicketEstado::default()?->id,
                'ticket_prioridad_id' => ($prioridad ?? TicketPrioridad::default())?->id,
                'asunto' => $asunto,
                'canal' => $esAgente ? 'manual' : 'portal',
                'ultima_actividad_en' => now(),
            ]);

            $primerMensaje = $ticket->mensajes()->create([
                'autor_tipo' => $esAgente ? 'agente' : 'cliente',
                'user_id' => $autor->id,
                'cuerpo' => self::limpiarHtml($mensaje),
                'adjuntos' => $adjuntos,
            ]);

            TicketNotificador::ticketAbierto($ticket, $primerMensaje);

            if (! $esAgente) {
                TicketNotificador::ticketNuevoAdmin($ticket, $primerMensaje);
            }

            return $ticket;
        });
    }

    /**
     * Añade una respuesta y transiciona el estado según quién responde: el cliente
     * deja el ticket "en proceso" (y lo reabre si estaba cerrado); el agente lo deja
     * "pendiente cliente", salvo que ya estuviera cerrado.
     */
    public static function responder(Ticket $ticket, string $cuerpo, User $autor, ?array $adjuntos = null): TicketMensaje
    {
        return DB::transaction(function () use ($ticket, $cuerpo, $autor, $adjuntos) {
            $esAgente = $autor->esAdmin();

            $mensaje = $ticket->mensajes()->create([
                'autor_tipo' => $esAgente ? 'agente' : 'cliente',
                'user_id' => $autor->id,
                'cuerpo' => self::limpiarHtml($cuerpo),
                'adjuntos' => $adjuntos,
            ]);

            $cambios = ['ultima_actividad_en' => now()];

            if (! $esAgente && ($estado = TicketEstado::enProceso())) {
                $cambios += ['ticket_estado_id' => $estado->id, 'cerrado_en' => null];
            } elseif ($esAgente && ! $ticket->estaCerrado() && ($estado = TicketEstado::pendienteCliente())) {
                $cambios['ticket_estado_id'] = $estado->id;
            }

            $ticket->update($cambios);
            $ticket->load('estado');

            $esAgente
                ? TicketNotificador::ticketRespondido($ticket, $mensaje)
                : TicketNotificador::ticketRespuestaClienteAdmin($ticket, $mensaje);

            return $mensaje;
        });
    }

    /**
     * Cambia el estado, deja constancia en la conversación y avisa al cliente.
     */
    public static function cambiarEstado(Ticket $ticket, TicketEstado $estado, User $autor, ?string $comentario = null): void
    {
        if ($ticket->ticket_estado_id === $estado->id && blank($comentario)) {
            return;
        }

        DB::transaction(function () use ($ticket, $estado, $autor, $comentario) {
            $ticket->update([
                'ticket_estado_id' => $estado->id,
                'cerrado_en' => $estado->es_cierre ? now() : null,
                'ultima_actividad_en' => now(),
            ]);
            $ticket->load('estado');

            $mensaje = $ticket->mensajes()->create([
                'autor_tipo' => 'agente',
                'user_id' => $autor->id,
                'cuerpo' => filled($comentario) ? self::limpiarHtml($comentario) : '<p>' . e("Estado actualizado a: {$estado->nombre}") . '</p>',
            ]);

            $estado->es_cierre
                ? TicketNotificador::ticketCerrado($ticket, $mensaje)
                : TicketNotificador::ticketRespondido($ticket, $mensaje);
        });
    }

    public static function cerrar(Ticket $ticket, User $autor, ?string $comentario = null): void
    {
        if ($estado = TicketEstado::cierre()) {
            self::cambiarEstado($ticket, $estado, $autor, $comentario);
        }
    }

    /**
     * Un ticket solo puede abrirse sobre un servicio activo asignado al cliente.
     */
    public static function comprobarServicio(User $cliente, Servicio $servicio): void
    {
        if (! $cliente->serviciosDisponibles()->whereKey($servicio->id)->exists()) {
            throw new InvalidArgumentException("El servicio «{$servicio->nombre}» no está disponible para {$cliente->email}.");
        }
    }

    /**
     * El mensaje llega como HTML del editor: se guarda ya saneado (sin scripts,
     * eventos ni enlaces javascript:), igual que se vuelve a sanear al mostrarlo.
     * El texto plano (p. ej. un comentario de estado) se convierte en párrafos.
     */
    public static function limpiarHtml(string $cuerpo): string
    {
        if ($cuerpo === strip_tags($cuerpo)) {
            $cuerpo = collect(preg_split('/\R{2,}/', trim($cuerpo)))
                ->map(fn ($parrafo) => '<p>' . nl2br(e($parrafo)) . '</p>')
                ->implode('');
        }

        return Str::sanitizeHtml($cuerpo);
    }
}
