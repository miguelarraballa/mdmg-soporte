<?php

use App\Http\Controllers\AdjuntoController;
use App\Http\Controllers\InstalacionController;
use App\Http\Controllers\ManualController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/portal');

Route::get('/adjuntos/{mensaje}/{indice}', AdjuntoController::class)
    ->middleware('auth')
    ->whereNumber('indice')
    ->name('tickets.adjunto');

// Contenido del manual; se muestra en un iframe desde App\Filament\Shared\VerManual.
Route::get('/manuales/{manual}', ManualController::class)
    ->middleware('auth')
    ->whereNumber('manual')
    ->name('manuales.contenido');

// Solo accesible sin instalar (middleware ComprobarInstalacion).
Route::get('/instalar', [InstalacionController::class, 'create'])->name('instalacion');
Route::post('/instalar', [InstalacionController::class, 'store'])->name('instalacion.store');
