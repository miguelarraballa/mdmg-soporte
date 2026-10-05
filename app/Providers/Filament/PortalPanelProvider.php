<?php

namespace App\Providers\Filament;

use App\Filament\Marca;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Área de clientes: cada cliente entra con su usuario y gestiona sus tickets.
 */
class PortalPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return Marca::aplicar($panel)
            ->id('portal')
            ->path('portal')
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            ->brandName(fn () => config('app.name') . ' · Soporte')
            ->discoverResources(in: app_path('Filament/Portal/Resources'), for: 'App\Filament\Portal\Resources')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
