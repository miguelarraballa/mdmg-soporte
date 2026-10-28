<?php

namespace App\Filament\Shared;

use App\Models\Manual;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

use function Filament\Support\original_request;

/**
 * Muestra un manual dentro del panel (admin y portal). El menú "Ayuda" tiene un
 * enlace por cada manual que puede consultar el usuario (Manual::visiblesPara).
 */
class VerManual extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Ayuda';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'ayuda/{manual}';

    protected string $view = 'filament.pages.ver-manual';

    public Manual $manual;

    public function mount(Manual $manual): void
    {
        abort_unless($manual->esVisiblePara(auth()->user()), 404);

        $this->manual = $manual;
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'ayuda';
    }

    public function getTitle(): string|Htmlable
    {
        return $this->manual->titulo;
    }

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return [];
        }

        return Manual::visiblesPara($user)
            ->orderBy('titulo')
            ->get()
            ->map(fn (Manual $manual) => NavigationItem::make($manual->titulo)
                ->key(static::class.'.'.$manual->getKey())
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon())
                ->sort(static::getNavigationSort())
                ->isActiveWhen(fn (): bool => original_request()->routeIs(static::getRouteName())
                    && (string) self::idEnRuta() === (string) $manual->getKey())
                ->url(static::getUrl(['manual' => $manual])))
            ->all();
    }

    /** La ruta puede traer el id o el modelo ya resuelto. */
    private static function idEnRuta(): mixed
    {
        $parametro = original_request()->route('manual');

        return $parametro instanceof Manual ? $parametro->getKey() : $parametro;
    }

    public function getUrlContenido(): string
    {
        return route('manuales.contenido', $this->manual);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('abrir')
                ->label('Abrir en otra pestaña')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn () => $this->getUrlContenido(), shouldOpenInNewTab: true),
        ];
    }
}
