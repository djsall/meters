<?php

namespace App\Filament\Resources\ReadingResource\Widgets;

use App\Models\Meter;
use App\Models\Reading;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AverageConsumption extends BaseWidget
{
    protected string $defaultValue = '-';

    protected Meter $meter {
        get {
            return Filament::getTenant();
        }
    }

    protected function getUnitLabel(): string
    {
        return $this->meter->type->getUnit()->getLabel();
    }

    protected function getStats(): array
    {
        return [
            $this->getDailyAverage(),
            $this->getCurrentYearAverage(),
        ];
    }

    protected function getCurrentYearAverage(): Stat
    {
        $latest = $this->meter->firstReadingThisYear;
        $previous = $this->meter->lastReading;

        $months = ($latest && $previous) ? $latest->date->startOfMonth()->diffInMonths($previous->date->endOfMonth()) : 0;
        $value = $this->calculateAverage($previous, $latest, max(1, $months));

        return $this->makeStat(__('reading.average_consumption'), $value);
    }

    protected function getDailyAverage(): Stat
    {
        $latest = $this->meter->lastReading;
        $previous = $this->meter->firstReadingThisMonth;

        $days = ($latest && $previous) ? $latest->date->diffInDays($previous->date, absolute: true) : 0;
        $value = $this->calculateAverage($latest, $previous, $days);

        return $this->makeStat(__('reading.average_daily_consumption_this_month'), $value);
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
