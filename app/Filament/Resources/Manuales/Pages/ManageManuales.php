<?php

namespace App\Filament\Resources\Manuales\Pages;

use App\Filament\Resources\Manuales\ManualResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageManuales extends ManageRecords
{
    protected static string $resource = ManualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
