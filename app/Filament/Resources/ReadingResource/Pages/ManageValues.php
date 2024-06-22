<?php

namespace App\Filament\Resources\ReadingResource\Pages;

use App\Filament\Resources\ReadingResource;
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
            ReadingResource\Widgets\AverageConsumption::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ReadingResource\Widgets\MonthlyConsumptionChart::class,
        ];
    }
}
