<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMensaje extends Model
{
    protected $table = 'ticket_mensajes';

    protected $fillable = [
        'ticket_id',
        'autor_tipo',
        'user_id',
        'cuerpo',
        'adjuntos',
    ];

    protected $casts = [
        'adjuntos' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
