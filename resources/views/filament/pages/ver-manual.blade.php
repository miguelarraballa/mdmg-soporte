{{-- Manual de App\Filament\Shared\VerManual. Estilos en línea: las utilidades de Tailwind no están en el CSS de Filament. --}}
<x-filament-panels::page>
    <iframe
        src="{{ $this->getUrlContenido() }}"
        title="{{ $manual->titulo }}"
        sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox allow-modals allow-downloads"
        style="display: block; width: 100%; height: calc(100vh - 14rem); min-height: 30rem; border: 1px solid var(--gray-200); border-radius: 0.75rem; background: #fff;"
    ></iframe>
</x-filament-panels::page>
