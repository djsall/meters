<?php

namespace App\Filament\Resources\Reading\Pages;

use App\Filament\Resources\Reading;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

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

    public function getTitle(): string|Htmlable
    {
        $meterName = Filament::getTenant()->name;
        $titleString = mb_strtolower(__('reading.pluralLabel'));

        return "{$meterName} {$titleString}";
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
