<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketPrioridad extends Model
{
    protected $table = 'ticket_prioridades';

    protected $fillable = [
        'nombre',
        'color',
        'orden',
        'es_default',
    ];

    protected $casts = [
        'orden' => 'integer',
        'es_default' => 'bool',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'ticket_prioridad_id');
    }

    public function color(): string
    {
        return $this->color ?? 'gray';
    }

    public static function default(): ?self
    {
        return static::where('es_default', true)->first()
            ?? static::orderBy('orden')->first();
    }
}
