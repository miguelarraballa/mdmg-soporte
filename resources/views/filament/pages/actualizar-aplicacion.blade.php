{{-- Progreso de App\Filament\Pages\ActualizarAplicacion. Estilos en línea: las utilidades de Tailwind no están en el CSS de Filament. --}}
<x-filament-panels::page>
    <div wire:init="siguiente">
        <x-filament::section>
            <ol style="list-style: none; margin: 0; padding: 0; display: grid; gap: 0.75rem;">
                @foreach (array_values(\App\Filament\Pages\ActualizarAplicacion::PASOS) as $indice => $etiqueta)
                    @php
                        $estado = match (true) {
                            $indice < $paso => 'hecho',
                            $indice === $paso && $error !== null => 'error',
                            $indice === $paso => 'actual',
                            default => 'pendiente',
                        };
                    @endphp
                    <li style="display: flex; align-items: center; gap: 0.75rem;">
                        @if ($estado === 'hecho')
                            <x-filament::icon icon="heroicon-o-check-circle" style="width: 1.5rem; height: 1.5rem; color: var(--success-600);" />
                        @elseif ($estado === 'error')
                            <x-filament::icon icon="heroicon-o-x-circle" style="width: 1.5rem; height: 1.5rem; color: var(--danger-600);" />
                        @elseif ($estado === 'actual')
                            <x-filament::loading-indicator style="width: 1.5rem; height: 1.5rem;" />
                        @else
                            <x-filament::icon icon="heroicon-o-minus-circle" style="width: 1.5rem; height: 1.5rem; color: var(--gray-400);" />
                        @endif
                        <span @style(['color: var(--gray-400)' => $estado === 'pendiente', 'font-weight: 600' => $estado === 'actual'])>{{ $etiqueta }}</span>
                    </li>
                @endforeach
            </ol>
        </x-filament::section>

        @if ($error !== null)
            <div style="margin-top: 1.5rem;">
                <x-filament::section>
                    <p style="color: var(--danger-600); font-weight: 600;">{{ $error }}</p>
                    @if ($pendientes !== [])
                        <ul style="margin: 0.75rem 0 0; padding-left: 1.25rem; list-style: disc;">
                            @foreach ($pendientes as $pendiente)
                                <li>{{ $pendiente }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <div style="margin-top: 1rem;">
                        <x-filament::button tag="a" :href="\App\Filament\Pages\Configuracion::getUrl()" color="gray">
                            Volver a Configuración
                        </x-filament::button>
                    </div>
                </x-filament::section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
