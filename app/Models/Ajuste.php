<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Par clave/valor de la configuración de la app. Se lee a través de App\Filament\Marca.
 */
class Ajuste extends Model
{
    protected $table = 'ajustes';

    protected $primaryKey = 'clave';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'clave',
        'valor',
    ];
}
