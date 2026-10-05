<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Convierte el HTML saneado de un mensaje de ticket al formato mrkdwn de Slack.
 */
class SlackMensaje
{
    private const MAX_CARACTERES = 2500;

    /**
     * Escapa texto para Slack: &, < y > tienen significado especial.
     */
    public static function escapar(string $texto): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $texto);
    }

    public static function desdeHtml(string $html): string
    {
        $html = Str::sanitizeHtml($html);

        // Marcadores para las etiquetas que tienen equivalente en Slack, antes de quitar el resto.
        $reemplazos = [
            '/<a\s[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is' => "\u{E000}$1\u{E001}$2\u{E002}",
            '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is' => "\u{E003}$1\u{E003}\n",
            '/<(strong|b)>(.*?)<\/\1>/is' => "\u{E003}$2\u{E003}",
            '/<(em|i)>(.*?)<\/\1>/is' => "\u{E004}$2\u{E004}",
            '/<(s|del|strike)>(.*?)<\/\1>/is' => "\u{E005}$2\u{E005}",
            '/<code>(.*?)<\/code>/is' => "\u{E006}$1\u{E006}",
            '/<li[^>]*>/i' => "\n• ",
            '/<br\s*\/?>/i' => "\n",
            '/<\/(p|div|h[1-6]|li|blockquote|pre|tr)>/i' => "\n",
            '/<blockquote[^>]*>/i' => "\u{E007}",
        ];

        $texto = preg_replace(array_keys($reemplazos), array_values($reemplazos), $html);
        $texto = html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = self::escapar($texto);

        $texto = strtr($texto, [
            "\u{E000}" => '<', "\u{E001}" => '|', "\u{E002}" => '>',
            "\u{E003}" => '*', "\u{E004}" => '_', "\u{E005}" => '~', "\u{E006}" => '`',
        ]);

        // Citas: cada línea del bloque citado empieza por "> ".
        $texto = preg_replace_callback('/\x{E007}([^\n]*)/u', fn ($m) => '> ' . $m[1], $texto);

        $texto = trim(preg_replace("/\n{3,}/", "\n\n", $texto));

        return Str::limit($texto, self::MAX_CARACTERES);
    }
}
