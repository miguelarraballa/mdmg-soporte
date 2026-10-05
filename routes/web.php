<?php

use App\Http\Controllers\AdjuntoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/portal');

Route::get('/adjuntos/{mensaje}/{indice}', AdjuntoController::class)
    ->middleware('auth')
    ->whereNumber('indice')
    ->name('tickets.adjunto');
