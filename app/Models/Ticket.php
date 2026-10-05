<?php

namespace App\Models;

use App\Services\TicketAdjuntoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $table = 'tickets';

    protected $fillable = [
        'user_id',
        'servicio_id',
        'ticket_categoria_id',
        'ticket_estado_id',
        'ticket_prioridad_id',
        'asunto',
        'canal',
        'ultima_actividad_en',
        'cerrado_en',
    ];

    protected $casts = [
        'ultima_actividad_en' => 'datetime',
        'cerrado_en' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Los mensajes se borran en cascada en la BD; sus archivos hay que borrarlos aquí.
        static::deleting(fn (Ticket $ticket) => $ticket->mensajes->each(
            fn (TicketMensaje $mensaje) => TicketAdjuntoService::borrar($mensaje)
        ));
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(TicketCategoria::class, 'ticket_categoria_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(TicketEstado::class, 'ticket_estado_id');
    }

    public function prioridad(): BelongsTo
    {
        return $this->belongsTo(TicketPrioridad::class, 'ticket_prioridad_id');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(TicketMensaje::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Número visible del ticket: el id con 6 cifras (000015).
     */
    public function numero(): string
    {
        return str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Asunto común a todos los emails del ticket, idéntico en cada aviso para
     * que el gestor de correo los agrupe en una sola conversación:
     * [{APP_NAME} TICKET #000015] - {servicio} - {asunto}
     */
    public function asuntoEmail(): string
    {
        return collect([
            '[' . config('app.name') . " TICKET #{$this->numero()}]",
            $this->servicio?->nombre,
            $this->asunto,
        ])->filter()->implode(' - ');
    }

    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->whereHas('estado', fn ($q) => $q->where('es_cierre', false));
    }

    public function estaCerrado(): bool
    {
        return (bool) $this->estado?->es_cierre;
    }

    /**
     * El cliente solo puede borrar un ticket mientras soporte no haya intervenido en él.
     */
    public function tieneRespuestaDeSoporte(): bool
    {
        return $this->mensajes()->where('autor_tipo', 'agente')->exists();
    }
}
