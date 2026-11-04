<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\TicketEstado;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TicketsPorEstado extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Tickets por estado';

    protected function getStats(): array
    {
        return TicketEstado::query()
            ->where('activo', true)
            ->withCount('tickets')
            ->orderBy('orden')
            ->get()
            ->map(fn (TicketEstado $estado) => Stat::make($estado->nombre, $estado->tickets_count)
                ->color($estado->color())
                ->description($estado->es_cierre ? 'Cerrados' : 'Abiertos')
                ->url(TicketResource::getUrl('index', [
                    'filters' => [
                        'abiertos' => ['isActive' => $estado->es_cierre ? '0' : '1'],
                        'ticket_estado_id' => ['value' => $estado->id],
                    ],
                ])))
            ->all();
    }
}
