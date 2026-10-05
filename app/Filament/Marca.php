<?php

namespace App\Filament;

use App\Models\Ajuste;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Identidad visual común a los paneles admin y portal. Los valores por defecto son
 * los del manual MDMG v1.0 (paleta monocroma, Exo 2 / Exo / PT Sans, icono de MDMG Soporte)
 * y se pueden cambiar desde /admin/configuracion (tabla `ajustes`).
 * Los estilos CSS están en resources/views/filament/marca.blade.php.
 */
class Marca
{
    /** Disco donde viven el icono y el logotipo (public/images/marca). */
    public const DISCO = 'marca';

    public const CACHE = 'ajustes.marca';

    /**
     * Valores del manual. `nombre` vacío = APP_NAME del .env.
     * Logotipo: el manual prohíbe reconstruirlo con texto, así que solo se usa si existe
     * el archivo; si no, se muestra el icono junto al nombre de la app.
     * En la barra lateral cabe la firma reducida (mín. 96 px de ancho); la completa exige 190 px.
     */
    public const DEFAULTS = [
        'nombre' => null,
        'icono' => 'mdmg-soporte-icono-negro.svg',
        'icono_oscuro' => 'mdmg-soporte-icono-claro.svg',
        'logo' => 'mdmg-reducido.svg',
        'logo_oscuro' => 'mdmg-reducido-negativo.svg',
        // Tipografías: Exo 2 (títulos), Exo (navegación), PT Sans (texto).
        'fuente_titulos' => 'Exo 2',
        'fuente_navegacion' => 'Exo',
        'fuente_texto' => 'PT Sans',
        // Escala de grises del manual.
        'color_principal' => '#000000',
        'color_titulo' => '#0a0a0a',
        'color_texto' => '#333333',
        'color_gris' => '#5a5a5a',
        'color_linea' => '#cccccc',
        'color_fondo' => '#f1f1f1',
    ];

    /** Paleta primaria con el negro del manual (#F1F1F1 fondo, #CCCCCC línea, #5A5A5A medio, #333333 texto, #0A0A0A título). */
    public const GRISES = [
        50 => '#f1f1f1',
        100 => '#e6e6e6',
        200 => '#cccccc',
        300 => '#b3b3b3',
        400 => '#8c8c8c',
        500 => '#5a5a5a',
        600 => '#333333',
        700 => '#262626',
        800 => '#1a1a1a',
        900 => '#0a0a0a',
        950 => '#000000',
    ];

    private static ?array $ajustes = null;

    /** Ajustes efectivos: lo guardado en la tabla sobre los valores del manual. */
    public static function ajustes(): array
    {
        return self::$ajustes ??= array_merge(
            self::DEFAULTS,
            array_filter(self::guardados(), fn ($valor) => filled($valor)),
        );
    }

    public static function get(string $clave): ?string
    {
        return self::ajustes()[$clave] ?? null;
    }

    /** Guarda los ajustes (null o vacío = volver al valor del manual). */
    public static function guardar(array $valores): void
    {
        foreach (array_intersect_key($valores, self::DEFAULTS) as $clave => $valor) {
            if (str_starts_with($clave, 'color_') && filled($valor)) {
                $valor = strtolower($valor);
            }

            if (blank($valor) || $valor === self::DEFAULTS[$clave]) {
                Ajuste::whereKey($clave)->delete();
            } else {
                Ajuste::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
            }
        }

        self::olvidar();
    }

    public static function restaurar(): void
    {
        Ajuste::query()->delete();
        self::olvidar();
    }

    private static function olvidar(): void
    {
        Cache::forget(self::CACHE);
        self::$ajustes = null;
    }

    private static function guardados(): array
    {
        try {
            return Cache::rememberForever(self::CACHE, fn () => Ajuste::pluck('valor', 'clave')->all());
        } catch (Throwable) {
            // Sin base de datos o sin migrar: valores del manual.
            return [];
        }
    }

    /** Ruta del archivo de marca dentro del disco, solo si existe. */
    public static function archivo(string $clave): ?string
    {
        $ruta = self::get($clave);

        return $ruta && Storage::disk(self::DISCO)->exists($ruta) ? $ruta : null;
    }

    public static function url(string $clave): ?string
    {
        $ruta = self::archivo($clave);

        return $ruta ? Storage::disk(self::DISCO)->url($ruta) : null;
    }

    public static function fuentesUrl(): string
    {
        $familias = collect(['fuente_titulos', 'fuente_navegacion', 'fuente_texto'])
            ->map(fn (string $clave) => Str::slug(self::get($clave)))
            ->unique()
            ->map(fn (string $slug) => "{$slug}:400,400i,500,600,700")
            ->implode('|');

        return "https://fonts.bunny.net/css?family={$familias}&display=swap";
    }

    public static function paletaPrimaria(): array
    {
        $principal = strtolower(self::get('color_principal'));

        return $principal === self::DEFAULTS['color_principal'] ? self::GRISES : Color::hex($principal);
    }

    public static function aplicar(Panel $panel): Panel
    {
        return $panel
            ->colors(fn (): array => [
                'primary' => self::paletaPrimaria(),
                'gray' => Color::Neutral,
                'info' => Color::Neutral,
            ])
            ->font(fn () => self::get('fuente_texto'), url: fn () => self::fuentesUrl())
            ->favicon(fn () => self::url('icono') ?? asset('favicon.ico'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => sprintf(
                '<link rel="alternate icon" href="%s" sizes="any"><link rel="apple-touch-icon" href="%s">',
                asset('favicon.ico'),
                self::get('icono') === self::DEFAULTS['icono'] ? asset('images/marca/apple-touch-icon.png') : self::url('icono'),
            ))
            ->brandLogo(fn () => self::url('logo') ?? view('filament.logo'))
            ->darkModeBrandLogo(fn () => self::url('logo') ? (self::url('logo_oscuro') ?? self::url('logo')) : null)
            ->brandLogoHeight('2rem');
    }
}
