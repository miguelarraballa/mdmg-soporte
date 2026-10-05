{{-- Estilos de la conversación de tickets (TicketConversacion). Usan las variables de color de Filament. --}}
<style>
    .tk-hilo { display: flex; flex-direction: column; gap: 1rem; }
    .tk-msg {
        --tk-acento: var(--info-500);
        --tk-fondo: var(--info-50);
        --tk-texto: var(--info-700);
        border: 1px solid var(--gray-200);
        border-left: 4px solid var(--tk-acento);
        border-radius: 0.5rem;
        background: #fff;
        overflow: hidden;
    }
    .tk-msg--soporte {
        --tk-acento: var(--primary-500);
        --tk-fondo: var(--primary-50);
        --tk-texto: var(--primary-700);
    }
    .tk-msg__cabecera {
        display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: var(--tk-fondo);
        border-bottom: 1px solid var(--gray-200);
        font-size: 0.8125rem;
    }
    .tk-msg__autor { font-weight: 600; color: var(--gray-950); }
    .tk-msg__rol {
        padding: 0.0625rem 0.5rem; border-radius: 9999px;
        font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;
        color: #fff; background: var(--tk-acento);
    }
    .tk-msg__ultimo {
        padding: 0.0625rem 0.5rem; border-radius: 9999px; font-size: 0.6875rem; font-weight: 600;
        color: var(--tk-texto); border: 1px solid var(--tk-acento);
    }
    .tk-msg__fecha { margin-left: auto; color: var(--gray-500); white-space: nowrap; }
    .tk-msg__cuerpo { padding: 0.875rem 1rem; }
    .tk-msg__cuerpo > .fi-prose { font-size: 0.875rem; }

    .tk-adj { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.75rem; }
    .tk-adj__miniatura {
        display: block; width: 5rem; height: 5rem; object-fit: cover;
        border-radius: 0.5rem; border: 1px solid var(--gray-200);
    }
    .tk-adj__archivo {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.375rem 0.75rem; border-radius: 0.5rem; border: 1px solid var(--gray-200);
        font-size: 0.8125rem; color: var(--gray-950); text-decoration: none;
    }
    .tk-adj__archivo:hover { background: var(--gray-50); }
    .tk-adj__tipo {
        padding: 0.0625rem 0.375rem; border-radius: 0.25rem;
        font-size: 0.6875rem; font-weight: 700; background: var(--gray-100); color: var(--gray-700);
    }
    .tk-adj__nombre { font-weight: 500; }
    .tk-adj__tamano { font-size: 0.75rem; color: var(--gray-500); }

    :where(.dark) .tk-adj__miniatura,
    :where(.dark) .tk-adj__archivo { border-color: rgb(255 255 255 / 0.15); }
    :where(.dark) .tk-adj__archivo { color: #fff; }
    :where(.dark) .tk-adj__archivo:hover { background: rgb(255 255 255 / 0.05); }
    :where(.dark) .tk-adj__tipo { background: rgb(255 255 255 / 0.1); color: var(--gray-200); }
    :where(.dark) .tk-adj__tamano { color: var(--gray-400); }

    :where(.dark) .tk-msg {
        --tk-acento: var(--info-400);
        --tk-fondo: color-mix(in oklab, var(--info-500) 15%, transparent);
        --tk-texto: var(--info-300);
        border-color: rgb(255 255 255 / 0.1);
        border-left-color: var(--tk-acento);
        background: var(--gray-900);
    }
    :where(.dark) .tk-msg--soporte {
        --tk-acento: var(--primary-400);
        --tk-fondo: color-mix(in oklab, var(--primary-500) 15%, transparent);
        --tk-texto: var(--primary-300);
    }
    :where(.dark) .tk-msg__cabecera { border-bottom-color: rgb(255 255 255 / 0.1); }
    :where(.dark) .tk-msg__autor { color: #fff; }
    :where(.dark) .tk-msg__rol { color: var(--gray-950); }
    :where(.dark) .tk-msg__fecha { color: var(--gray-400); }
</style>
