<?php

namespace App\Filament\Resources\Reading\Pages;

use App\Filament\Resources\Reading;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageValues extends ManageRecords
{
    protected static string $resource = Reading\ReadingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->createAnother(false),
        ];
    }

    public function getHeaderWidgets(): array
    {
        return [
            Reading\Widgets\AverageConsumption::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            Reading\Widgets\MonthlyConsumptionChart::class,
        ];
    }
}
