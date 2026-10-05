{{-- Logo de la cabecera: icono de la app (claro y oscuro) + nombre del panel. Configurable en /admin/configuracion. --}}
@php($icono = \App\Filament\Marca::url('icono'))
@php($iconoOscuro = \App\Filament\Marca::url('icono_oscuro') ?? $icono)
<span class="mdmg-logo">
    @if ($icono)
        <img src="{{ $icono }}" alt="" class="mdmg-logo__icono mdmg-logo__icono--claro">
        <img src="{{ $iconoOscuro }}" alt="" class="mdmg-logo__icono mdmg-logo__icono--oscuro">
    @endif
    <span class="mdmg-logo__nombre">{{ filament()->getBrandName() }}</span>
</span>
