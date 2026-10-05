<?php

namespace App\Services;

use App\Models\TicketMensaje;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Editor con formato y adjuntos de los mensajes de tickets. Los archivos se
 * guardan en el disco privado "local" y solo se sirven a través de
 * AdjuntoController, que comprueba que quien los pide puede ver el ticket.
 */
class TicketAdjuntoService
{
    public const DISCO = 'local';

    public const DIRECTORIO = 'tickets/adjuntos';

    public const MAX_ARCHIVOS = 5;

    public const MAX_KB = 10240;

    public const LADO_MAXIMO = 1024;

    public const CALIDAD_JPG = 82;

    public const TIPOS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/zip' => 'zip',
        // Tipo que envían los navegadores en Windows para los ZIP.
        'application/x-zip-compressed' => 'zip',
    ];

    public static function campoMensaje(string $nombre = 'cuerpo', string $label = 'Mensaje'): RichEditor
    {
        return RichEditor::make($nombre)
            ->label($label)
            ->required()
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'link'],
                ['h2', 'h3'],
                ['bulletList', 'orderedList', 'blockquote', 'codeBlock'],
                ['table'],
                ['undo', 'redo'],
            ])
            // Los archivos van en el campo de adjuntos, no incrustados en el texto.
            ->fileAttachments(false)
            ->extraInputAttributes(['style' => 'min-height: 12rem;']);
    }

    public static function campoAdjuntos(): FileUpload
    {
        return FileUpload::make('adjuntos')
            ->label('Adjuntos')
            ->multiple()
            ->disk(self::DISCO)
            ->directory(self::DIRECTORIO)
            ->visibility('private')
            ->acceptedFileTypes(array_keys(self::TIPOS))
            ->maxSize(self::MAX_KB)
            ->maxFiles(self::MAX_ARCHIVOS)
            ->storeFileNamesIn('adjuntos_nombres')
            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, FileUpload $component): string {
                $mime = $file->getMimeType();
                $ruta = trim($component->getDirectory() . '/' . Str::ulid() . '.' . (self::TIPOS[$mime] ?? $file->guessExtension()), '/');

                $imagen = isset(self::TIPOS[$mime]) && str_starts_with($mime, 'image/')
                    ? self::redimensionar($file->getRealPath(), $mime)
                    : null;

                $imagen === null
                    ? $component->getDisk()->putFileAs(dirname($ruta), $file, basename($ruta))
                    : $component->getDisk()->put($ruta, $imagen);

                return $ruta;
            })
            ->previewable(false)
            ->helperText('Hasta ' . self::MAX_ARCHIVOS . ' archivos JPG, PNG, PDF, DOCX, XLSX o ZIP de ' . (self::MAX_KB / 1024) . ' MB como máximo. Las imágenes se reducen a ' . self::LADO_MAXIMO . ' px.');
    }

    /**
     * Convierte las rutas subidas por el formulario en la lista que se guarda en el mensaje.
     *
     * @param  array<string>|null  $rutas
     * @param  array<string, string>|null  $nombres  ruta => nombre original
     */
    public static function desdeFormulario(?array $rutas, ?array $nombres): ?array
    {
        $disco = Storage::disk(self::DISCO);

        $adjuntos = collect($rutas ?? [])
            ->filter(fn ($ruta) => is_string($ruta) && $disco->exists($ruta))
            ->map(fn (string $ruta) => [
                'ruta' => $ruta,
                'nombre' => $nombres[$ruta] ?? basename($ruta),
                'mime' => $disco->mimeType($ruta),
                'tamano' => $disco->size($ruta),
            ])
            ->values()
            ->all();

        return $adjuntos ?: null;
    }

    public static function esImagen(array $adjunto): bool
    {
        return in_array($adjunto['mime'] ?? null, ['image/jpeg', 'image/png'], true);
    }

    public static function url(TicketMensaje $mensaje, int $indice): string
    {
        return route('tickets.adjunto', ['mensaje' => $mensaje, 'indice' => $indice]);
    }

    /**
     * Miniaturas de las imágenes y enlaces al resto de archivos de un mensaje.
     */
    public static function html(TicketMensaje $mensaje): string
    {
        if (blank($mensaje->adjuntos)) {
            return '';
        }

        $imagenes = '';
        $archivos = '';

        foreach ($mensaje->adjuntos as $indice => $adjunto) {
            $url = e(self::url($mensaje, $indice));
            $nombre = e($adjunto['nombre']);

            if (self::esImagen($adjunto)) {
                $imagenes .= "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener\" title=\"{$nombre}\"><img src=\"{$url}\" alt=\"{$nombre}\" class=\"tk-adj__miniatura\" loading=\"lazy\"></a>";

                continue;
            }

            $extension = Str::upper(self::TIPOS[$adjunto['mime']] ?? pathinfo($adjunto['nombre'], PATHINFO_EXTENSION));
            $tamano = e(Number::fileSize($adjunto['tamano'] ?? 0, precision: 1));

            $archivos .= "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener\" class=\"tk-adj__archivo\">"
                . "<span class=\"tk-adj__tipo\">{$extension}</span>"
                . "<span class=\"tk-adj__nombre\">{$nombre}</span><span class=\"tk-adj__tamano\">{$tamano}</span></a>";
        }

        return ($imagenes ? "<div class=\"tk-adj\">{$imagenes}</div>" : '')
            . ($archivos ? "<div class=\"tk-adj\">{$archivos}</div>" : '');
    }

    public static function borrar(TicketMensaje $mensaje): void
    {
        $rutas = array_column($mensaje->adjuntos ?? [], 'ruta');

        if ($rutas) {
            Storage::disk(self::DISCO)->delete($rutas);
        }
    }

    /**
     * Reduce la imagen a LADO_MAXIMO px en su lado mayor (sin ampliar) y corrige la
     * orientación EXIF de las fotos de móvil. Conserva el formato (y la transparencia
     * de los PNG). Devuelve null si la imagen ya cumple y se puede guardar tal cual.
     */
    public static function redimensionar(string $rutaAbsoluta, string $mime): ?string
    {
        $info = @getimagesize($rutaAbsoluta);

        if (! $info) {
            return null;
        }

        [$ancho, $alto] = $info;
        $orientacion = $mime === 'image/jpeg' && function_exists('exif_read_data')
            ? ((@exif_read_data($rutaAbsoluta) ?: [])['Orientation'] ?? 1)
            : 1;
        $ratio = min(1, self::LADO_MAXIMO / max($ancho, $alto));

        if ($ratio === 1 && in_array($orientacion, [1, 2, 4, 5, 7], true)) {
            return null;
        }

        $imagen = $mime === 'image/png' ? @imagecreatefrompng($rutaAbsoluta) : @imagecreatefromjpeg($rutaAbsoluta);

        if (! $imagen) {
            return null;
        }

        $nuevoAncho = max(1, (int) round($ancho * $ratio));
        $nuevoAlto = max(1, (int) round($alto * $ratio));

        $lienzo = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

        if ($mime === 'image/png') {
            imagealphablending($lienzo, false);
            imagesavealpha($lienzo, true);
            imagefill($lienzo, 0, 0, imagecolorallocatealpha($lienzo, 0, 0, 0, 127));
        }

        imagecopyresampled($lienzo, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
        imagedestroy($imagen);

        $rotada = match ($orientacion) {
            3 => imagerotate($lienzo, 180, 0),
            6 => imagerotate($lienzo, 270, 0),
            8 => imagerotate($lienzo, 90, 0),
            default => $lienzo,
        };

        ob_start();
        $mime === 'image/png' ? imagepng($rotada, null, 6) : imagejpeg($rotada, null, self::CALIDAD_JPG);

        return ob_get_clean();
    }
}
