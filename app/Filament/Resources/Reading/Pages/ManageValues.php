<?php

namespace App\Filament\Resources\Reading\Pages;

use App\Filament\Resources\Reading;
use App\Models\Meter;
use Filament\Actions;
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
        $meter = Meter::getFilamentTenant();
        $titleString = mb_strtolower(__('reading.pluralLabel'));

        return "{$meter->name} {$titleString}";
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
