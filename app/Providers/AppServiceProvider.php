<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Estilos de la conversación de tickets en los dos paneles (admin y portal).
        FilamentView::registerRenderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('tickets.estilos'));
    }
}
