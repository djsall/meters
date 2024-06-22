<?php

namespace App\Filament\Resources\ReadingResource\Pages;

use App\Filament\Resources\ReadingResource;
use App\Filament\Resources\ReadingResource\Widgets\AverageConsumption;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageValues extends ManageRecords
{
    protected static string $resource = ReadingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getHeaderWidgets(): array
    {
        return [
            AverageConsumption::class,
        ];
    }
}
