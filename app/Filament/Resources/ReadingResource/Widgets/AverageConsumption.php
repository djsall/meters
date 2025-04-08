<?php

namespace App\Filament\Resources\ReadingResource\Widgets;

use App\Models\Reading;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AverageConsumption extends BaseWidget
{
    protected string $defaultValue = '-';

    protected function unit(): string
    {
        return Filament::getTenant()->type->getUnit()->getLabel();
    }

    protected function getCurrentYear(): Stat
    {
        $first = Reading::firstOfYear();
        $last = Reading::lastOfYear();

        $value = $this->defaultValue;

        if ($first && $last && $last->date->notEqualTo($first->date)) {
            $value = ($last->value - $first->value) / $first->date->startOfMonth()->diffInMonths($last->date->endOfMonth());
            $value = number_format($value, thousands_separator: ' ');
        }

        return Stat::make(__('reading.average_consumption'), "$value {$this->unit()}");
    }

    protected function getPreviousYear(): Stat
    {
        $first = Reading::firstOfYear(today()->subYear()->format('Y'));
        $last = Reading::lastOfYear(today()->subYear()->format('Y'));

        $value = $this->defaultValue;

        if ($first && $last && $last->date->notEqualTo($first->date)) {
            $value = ($last->value - $first->value) / $first->date->startOfMonth()->diffInMonths($last->date->endOfMonth());
            $value = number_format($value, thousands_separator: ' ');
        }

        return Stat::make(__('reading.average_consumption_last_year'), "$value {$this->unit()}");
    }

    protected function getStats(): array
    {

        return [
            $this->getCurrentYear(),
            $this->getPreviousYear(),
        ];
    }
}
