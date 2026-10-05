{{-- Identidad visual (por defecto, manual MDMG v1.0) para los paneles admin y portal. Valores en App\Filament\Marca, editables en /admin/configuracion. --}}
@php($marca = \App\Filament\Marca::ajustes())
<style>
    :root {
        --mdmg-negro: {{ $marca['color_principal'] }};
        --mdmg-titulo: {{ $marca['color_titulo'] }};
        --mdmg-texto: {{ $marca['color_texto'] }};
        --mdmg-gris: {{ $marca['color_gris'] }};
        --mdmg-linea: {{ $marca['color_linea'] }};
        --mdmg-fondo: {{ $marca['color_fondo'] }};
        --mdmg-fuente-titulos: '{{ $marca['fuente_titulos'] }}', '{{ $marca['fuente_texto'] }}', ui-sans-serif, system-ui, sans-serif;
        --mdmg-fuente-apoyo: '{{ $marca['fuente_navegacion'] }}', '{{ $marca['fuente_texto'] }}', ui-sans-serif, system-ui, sans-serif;
    }

    /* Texto corrido: fuente de texto, color de texto del manual, interlineado 1,5 */
    body { color: var(--mdmg-texto); }
    .fi-body { background: #fff; }
    .fi-prose, .fi-prose p { line-height: 1.5; }

    /* Títulos y jerarquía: fuente de títulos (regular en H1/H2, semibold en H3) */
    .fi-header-heading,
    .fi-simple-header-heading {
        font-family: var(--mdmg-fuente-titulos);
        font-weight: 400;
        letter-spacing: 0;
        color: var(--mdmg-titulo);
    }
    .fi-section-header-heading,
    .fi-modal-heading,
    .fi-ta-header-heading,
    .fi-wi-stats-overview-stat-value,
    .fi-prose :is(h1, h2, h3, h4) {
        font-family: var(--mdmg-fuente-titulos);
    }
    .fi-section-header-heading,
    .fi-modal-heading { font-weight: 600; }
    .fi-prose :is(h1, h2) { font-weight: 400; }
    .fi-prose :is(h3, h4) { font-weight: 600; }

    /* Navegación y apoyo: fuente de navegación */
    .fi-logo,
    .fi-sidebar-item-label,
    .fi-sidebar-group-label,
    .fi-topbar-item-label,
    .fi-breadcrumbs,
    .fi-tabs-item-label,
    .fi-dropdown-list-item-label,
    .fi-pagination,
    .fi-btn,
    .fi-badge,
    .fi-ta-header-cell {
        font-family: var(--mdmg-fuente-apoyo);
    }
    .fi-logo { font-weight: 500; letter-spacing: 0; }
    .fi-sidebar-group-label,
    .fi-ta-header-cell { letter-spacing: 0.02em; }

    /* Botones principales: fondo negro, texto blanco, sin degradado (negativo en modo oscuro) */
    .fi-btn.fi-color-primary:not(.fi-outlined) {
        --bg: var(--mdmg-negro);
        --hover-bg: var(--mdmg-texto);
        --text: #fff;
        --hover-text: #fff;
        --dark-bg: #fff;
        --dark-hover-bg: var(--mdmg-linea);
        --dark-text: var(--mdmg-negro);
        --dark-hover-text: var(--mdmg-negro);
        box-shadow: none;
    }
    .fi-btn.fi-color-primary.fi-outlined {
        --text: var(--mdmg-negro);
        --dark-text: #fff;
    }

    /* Elemento activo de la navegación: barra negra a la izquierda, como separador de código */
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        box-shadow: inset 3px 0 0 var(--mdmg-negro);
    }
    .fi-sidebar-item.fi-active .fi-sidebar-item-label { color: var(--mdmg-negro); font-weight: 500; }

    /* Logo de la cabecera: icono + nombre del panel (resources/views/filament/logo.blade.php) */
    .mdmg-logo { display: inline-flex; align-items: center; gap: 0.625rem; height: 100%; white-space: nowrap; }
    .mdmg-logo__icono { height: 100%; width: auto; border-radius: 22%; }
    .mdmg-logo__icono--oscuro { display: none; }
    .mdmg-logo__nombre { line-height: 1; }

    /* Fondo suave para las páginas de acceso y barras laterales */
    .fi-simple-layout { background: var(--mdmg-fondo); }
    .fi-simple-main { box-shadow: none; border: 1px solid var(--mdmg-linea); }

    /* Modo oscuro: negro de marca y textos blancos */
    :where(.dark) body { color: var(--gray-200); }
    :where(.dark) .fi-body { background: #000; }
    :where(.dark) :is(.fi-header-heading, .fi-simple-header-heading) { color: #fff; }
    :where(.dark) .fi-sidebar-item.fi-active > .fi-sidebar-item-btn { box-shadow: inset 3px 0 0 #fff; }
    :where(.dark) .fi-sidebar-item.fi-active .fi-sidebar-item-label { color: #fff; }
    :where(.dark) .fi-simple-layout { background: #000; }
    :where(.dark) .mdmg-logo__icono--claro { display: none; }
    :where(.dark) .mdmg-logo__icono--oscuro { display: block; }
    :where(.dark) .fi-simple-main { border-color: rgb(255 255 255 / 0.15); }
</style>
