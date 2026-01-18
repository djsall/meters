<?php

namespace App\Filament\Resources\ReadingResource\Widgets;

use App\Models\Reading;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AverageConsumption extends BaseWidget
{
    protected string $defaultValue = '-';

    protected function getUnitLabel(): string
    {
        return Filament::getTenant()->type->getUnit()->getLabel();
    }

    protected function getStats(): array
    {
        return [
            $this->getDailyAverage(),
            $this->getCurrentYearAverage(),
            $this->getPreviousDailyAverage(),
            $this->getPreviousYearAverage(),
        ];
    }

    protected function getCurrentYearAverage(): Stat
    {
        $first = Reading::firstOfYear();
        $last = Reading::lastOfYear();

        $months = $first && $last ? $first->date->startOfMonth()->diffInMonths($last->date->endOfMonth()) : 0;
        $value = $this->calculateAverage($last, $first, max(1, $months));

        return $this->makeStat(__('reading.average_consumption'), $value);
    }

    protected function getPreviousYearAverage(): Stat
    {
        $year = today()->subYear()->year;
        $first = Reading::firstOfYear($year);
        $last = Reading::lastOfYear($year);

        $months = $first && $last ? $first->date->startOfMonth()->diffInMonths($last->date->endOfMonth()) : 0;
        $value = $this->calculateAverage($last, $first, $months);

        return $this->makeStat(__('reading.average_consumption_last_year'), $value);
    }

    protected function getDailyAverage(): Stat
    {
        $latest = Reading::lastOfYear();
        $prev = $latest?->previous_month;

        $days = ($latest && $prev) ? $latest->date->diffInDays($prev->date, absolute: true) : 0;
        $value = $this->calculateAverage($latest, $prev, $days);

        return $this->makeStat(__('reading.average_daily_consumption_this_month'), $value);
    }

    protected function getPreviousDailyAverage(): Stat
    {
        $latest = Reading::lastOfYear()?->previous_month;
        $prev = $latest?->previous_month;

        $days = ($latest && $prev) ? $latest->date->diffInDays($prev->date, absolute: true) : 0;
        $value = $this->calculateAverage($latest, $prev, $days);

        return $this->makeStat(__('reading.average_daily_consumption_previous_month'), $value);
    }

    protected function calculateAverage(?Reading $end, ?Reading $start, int $divisor): ?float
    {
        if (! $end || ! $start || $divisor <= 0) {
            return null;
        }

        return ($end->value - $start->value) / $divisor;
    }

    protected function makeStat(string $title, ?float $value): Stat
    {
        $displayText = $this->defaultValue;

        if ($value !== null) {
            $formattedValue = number_format(round($value, 1), 1, '.', ' ');
            $displayText = "{$formattedValue} {$this->getUnitLabel()}";
        }

        return Stat::make($title, $displayText);
    }
}
