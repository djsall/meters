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

    protected function getColumns(): int
    {
        return 4;
    }

    protected Meter $meter {
        get {
            return Filament::getTenant();
        }
    }

    protected function getStats(): array
    {
        return [
            $this->getDailyAverage(),
            $this->getCurrentYearMonthlyAverage(),
            $this->getDailyAveragePreviousMonth(),
            $this->getPreviousYearMonthlyAverage(),
        ];
    }

    protected function getDailyAverage(): Stat
    {
        $latest = $this->meter->lastReading;
        $previous = $this->meter->firstReadingThisMonth;

        $days = ($latest && $previous) ? $latest->date->diffInDays($previous->date, absolute: true) : 0;
        $value = $this->calculateAverage($latest, $previous, $days);

        return $this->makeStat(__('reading.average_consumption.monthly.current'), $value);
    }

    protected function getCurrentYearMonthlyAverage(): Stat
    {
        $latest = $this->meter->firstReadingThisYear;
        $previous = $this->meter->lastReading;

        $months = ($latest && $previous) ? $latest->date->startOfMonth()->diffInMonths($previous->date->endOfMonth()) : 0;
        $value = $this->calculateAverage($previous, $latest, max(1, $months));

        return $this->makeStat(__('reading.average_consumption.yearly.current'), $value);
    }

    protected function getDailyAveragePreviousMonth(): Stat
    {
        $readings = $this->meter->readings()
            ->whereDate('date', '>=', today()->subMonth()->startOfMonth())
            ->whereDate('date', '<=', today()->subMonth()->endOfMonth())
            ->orderBy('date')
            ->get();

        $latest = $readings->first();
        $previous = $readings->last();

        $days = ($latest && $previous) ? $latest->date->diffInDays($previous->date, absolute: true) : 0;
        $value = $this->calculateAverage($latest, $previous, $days);

        return $this->makeStat(__('reading.average_consumption.monthly.previous'), $value);
    }

    protected function getPreviousYearMonthlyAverage(): Stat
    {
        $readings = $this->meter->readings()
            ->whereDate('date', '>=', today()->subYear()->startOfYear())
            ->whereDate('date', '<=', today()->subYear()->endOfYear())
            ->orderBy('date')
            ->get();

        $latest = $readings->first();
        $previous = $readings->last();

        $months = ($latest && $previous) ? $latest->date->startOfMonth()->diffInMonths($previous->date->endOfMonth()) : 0;
        $value = $this->calculateAverage($previous, $latest, max(1, $months));

        return $this->makeStat(__('reading.average_consumption.yearly.previous'), $value);
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
            $displayText = "{$formattedValue} {$this->meter->type->getUnit()->getLabel()}";
        }

        return Stat::make($title, $displayText);
    }
}
