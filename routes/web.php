<?php

use App\Http\Controllers\AdjuntoController;
use App\Http\Controllers\InstalacionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/portal');

Route::get('/adjuntos/{mensaje}/{indice}', AdjuntoController::class)
    ->middleware('auth')
    ->whereNumber('indice')
    ->name('tickets.adjunto');

// Solo accesible sin instalar (middleware ComprobarInstalacion).
Route::get('/instalar', [InstalacionController::class, 'create'])->name('instalacion');
Route::post('/instalar', [InstalacionController::class, 'store'])->name('instalacion.store');
