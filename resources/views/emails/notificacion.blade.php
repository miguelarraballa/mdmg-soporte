{{-- Plantilla única para todos los emails de la cola de notificaciones. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:8px;">
                    <tr>
                        <td style="padding:24px 28px;border-bottom:1px solid #e4e4e7;font-size:18px;font-weight:bold;">
                            {{ config('app.name') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;font-size:15px;line-height:1.6;">
                            @if (filled($evento ?? null))
                                <h1 style="margin:0 0 20px;font-size:20px;line-height:1.3;color:#18181b;">{{ $evento }}</h1>
                            @endif

                            <p style="margin:0 0 16px;">Hola {{ $nombre }},</p>

                            @foreach ($parrafos as $parrafo)
                                <p style="margin:0 0 16px;">{!! $parrafo !!}</p>
                            @endforeach

                            @if (filled($cita ?? null))
                                {{-- $cita es HTML ya saneado (ver NotificacionService::encolar) --}}
                                <div style="margin:0 0 16px;padding:4px 16px;background:#f4f4f5;border-left:4px solid #f59e0b;">{!! $cita !!}</div>
                            @endif

                            @if (filled($adjuntos ?? null))
                                <p style="margin:0 0 16px;font-size:13px;color:#52525b;">
                                    Adjuntos ({{ count($adjuntos) }}): {{ implode(', ', $adjuntos) }}<br>
                                    Puedes verlos o descargarlos desde el ticket.
                                </p>
                            @endif

                            @if (filled($boton ?? null))
                                <p style="margin:24px 0 8px;">
                                    <a href="{{ $boton['url'] }}" style="display:inline-block;padding:12px 20px;background:#f59e0b;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">{{ $boton['texto'] }}</a>
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px;border-top:1px solid #e4e4e7;font-size:12px;color:#71717a;">
                            Este es un mensaje automático. Para responder, entra en tu área de cliente.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
