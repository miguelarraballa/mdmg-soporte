<?php

namespace App\Providers;

use App\Filament\Marca;
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
        // Nombre de la app definido en /admin/configuracion (si no, APP_NAME del .env): paneles, emails y Slack.
        if ($nombre = Marca::get('nombre')) {
            config(['app.name' => $nombre]);
        }

        // Identidad visual MDMG en los dos paneles (admin y portal).
        FilamentView::registerRenderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('filament.marca'));

        // Estilos de la conversación de tickets en los dos paneles (admin y portal).
        FilamentView::registerRenderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('tickets.estilos'));
    }
}
