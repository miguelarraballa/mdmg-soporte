<?php

namespace App\Models;

use App\Enums\NotificacionEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = [
        'tipo',
        'canal',
        'email_destinatario',
        'asunto',
        'cuerpo_html',
        'payload',
        'estado',
        'intentos',
        'error',
        'enviado_en',
        'ticket_id',
        'user_id',
    ];

    protected $casts = [
        'estado' => NotificacionEstado::class,
        'payload' => 'array',
        'intentos' => 'integer',
        'enviado_en' => 'datetime',
    ];

    public function esSlack(): bool
    {
        return $this->canal === 'slack';
    }

    public function esPush(): bool
    {
        return $this->canal === 'push';
    }

    /**
     * Destinatario legible para el panel.
     */
    public function destino(): string
    {
        $usuario = $this->user?->name ?? 'usuario eliminado';

        return match ($this->canal) {
            'slack' => "Slack · {$usuario}",
            'push' => "Push · {$usuario}",
            default => (string) $this->email_destinatario,
        };
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
