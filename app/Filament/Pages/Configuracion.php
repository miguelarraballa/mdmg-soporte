<?php

namespace App\Filament\Pages;

use App\Filament\Marca;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Nombre, icono/logotipo, fuentes y colores de la app (admin y portal).
 * Por defecto, los valores del manual de identidad corporativa (App\Filament\Marca::DEFAULTS).
 */
class Configuracion extends Page
{
    use HasUnsavedDataChangesAlert;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Configuración';

    protected static ?string $title = 'Configuración';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'configuracion';

    /** Sugerencias de Bunny Fonts (se puede escribir cualquier otra familia de fonts.bunny.net). */
    private const FUENTES = [
        'Exo 2', 'Exo', 'PT Sans', 'Inter', 'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Poppins',
        'Source Sans 3', 'Nunito', 'Raleway', 'Work Sans', 'DM Sans', 'IBM Plex Sans', 'Fira Sans',
    ];

    private const IMAGENES = ['image/svg+xml', 'image/png', 'image/webp', 'image/jpeg'];

    public ?array $data = [];

    public function mount(): void
    {
        $this->rellenar();
    }

    protected function hasUnsavedDataChangesAlert(): bool
    {
        return true;
    }

    private function rellenar(): void
    {
        $this->form->fill([
            ...Marca::ajustes(),
            'nombre' => config('app.name'),
            'icono' => Marca::archivo('icono'),
            'icono_oscuro' => Marca::archivo('icono_oscuro'),
            'logo' => Marca::archivo('logo'),
            'logo_oscuro' => Marca::archivo('logo_oscuro'),
        ]);
        $this->rememberData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Aplicación')
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre de la app')
                            ->helperText('Aparece en la cabecera de los paneles, en los emails y en los avisos de Slack. Vacío = APP_NAME del .env.')
                            ->maxLength(60),
                    ]),
                Section::make('Icono y logotipo')
                    ->description('SVG, PNG, WebP o JPG (máx. 1 MB). Si se quita un archivo se vuelve al del manual.')
                    ->columns(2)
                    ->schema([
                        $this->imagen('icono', 'Icono')
                            ->helperText('Cuadrado. Se muestra junto al nombre y como favicon.'),
                        $this->imagen('icono_oscuro', 'Icono (modo oscuro)')
                            ->helperText('Versión clara del icono para fondo negro.'),
                        $this->imagen('logo', 'Logotipo')
                            ->helperText('Opcional. Si se sube, sustituye al icono + nombre en la cabecera (alto 2rem, mín. 96 px de ancho).'),
                        $this->imagen('logo_oscuro', 'Logotipo (modo oscuro)')
                            ->helperText('Versión en negativo del logotipo.'),
                    ]),
                Section::make('Fuentes')
                    ->description('Familias de fonts.bunny.net (compatibles con Google Fonts).')
                    ->columns(3)
                    ->schema([
                        $this->fuente('fuente_titulos', 'Títulos'),
                        $this->fuente('fuente_navegacion', 'Navegación y botones'),
                        $this->fuente('fuente_texto', 'Texto'),
                    ]),
                Section::make('Colores')
                    ->columns(3)
                    ->schema([
                        $this->color('color_principal', 'Principal')
                            ->helperText('Botones, enlaces y elemento activo del menú.'),
                        $this->color('color_titulo', 'Títulos'),
                        $this->color('color_texto', 'Texto'),
                        $this->color('color_gris', 'Gris medio'),
                        $this->color('color_linea', 'Líneas y bordes'),
                        $this->color('color_fondo', 'Fondo')
                            ->helperText('Página de acceso.'),
                    ]),
            ])
            ->statePath('data');
    }

    private function imagen(string $campo, string $etiqueta): FileUpload
    {
        return FileUpload::make($campo)
            ->label($etiqueta)
            ->image()
            ->disk(Marca::DISCO)
            ->directory('subidas')
            ->visibility('public')
            ->acceptedFileTypes(self::IMAGENES)
            ->maxSize(1024);
    }

    private function fuente(string $campo, string $etiqueta): TextInput
    {
        return TextInput::make($campo)
            ->label($etiqueta)
            ->datalist(self::FUENTES)
            ->placeholder(Marca::DEFAULTS[$campo])
            ->regex('/^[\pL\pN ]+$/u')
            ->maxLength(60);
    }

    private function color(string $campo, string $etiqueta): ColorPicker
    {
        return ColorPicker::make($campo)
            ->label($etiqueta)
            ->hex()
            ->regex('/^#[0-9a-fA-F]{6}$/')
            ->placeholder(Marca::DEFAULTS[$campo]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('guardar')
                ->footer([
                    Actions::make([
                        Action::make('guardar')
                            ->label('Guardar')
                            ->submit('guardar')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('restaurar')
                ->label('Restaurar valores del manual')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Se recuperan el icono, las fuentes y los colores del manual de identidad corporativa, y el nombre del .env.')
                ->action(function (): void {
                    Marca::restaurar();
                    $this->recargar('Valores del manual restaurados');
                }),
        ];
    }

    public function guardar(): void
    {
        Marca::guardar($this->form->getState());
        $this->recargar('Configuración guardada');
    }

    /** Recarga la página para que cabecera, favicon, fuentes y colores se apliquen al momento. */
    private function recargar(string $mensaje): void
    {
        $this->rememberData();

        Notification::make()->title($mensaje)->success()->send();

        $this->redirect(static::getUrl());
    }
}
