<?php

namespace App\Filament\Resources\ReadingResource\Widgets;

use App\Models\Reading;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AverageConsumption extends BaseWidget
{
    protected function getStats(): array
    {
        $label = trans('reading.average_consumption');
        $tenant = Filament::getTenant();
        $first = Reading::whereBelongsTo($tenant, 'meter')->whereBetween('date', [today()->startOfYear(), today()->endOfYear()])->orderBy('date')->first();
        $last = Reading::whereBelongsTo($tenant, 'meter')->whereBetween('date', [today()->startOfYear(), today()->endOfYear()])->orderByDesc('date')->first();

        $value = '-';

        if ($last->date->notEqualTo($first->date)) {
            $value = ($last->value - $first->value) / (today()->startOfYear()->diffInMonths($last->date->startOfMonth()));
            $value = number_format($value, thousands_separator: ' ');
        }

        $unit = $tenant->type->getUnit()->getLabel();

        return [
            Stat::make($label, "$value $unit"),
        ];
    }
}
