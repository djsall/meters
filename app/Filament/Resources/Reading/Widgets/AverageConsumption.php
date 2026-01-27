<?php

namespace App\Filament\Resources\Reading\Widgets;

use App\Models\Meter;
use App\Services\RateService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AverageConsumption extends BaseWidget
{
    protected string $defaultValue = '-';

    protected RateService $service;

    protected function getColumns(): int
    {
        return 4;
    }

    protected Meter $meter {
        get {
            return Filament::getTenant();
        }
    }

    public function __construct()
    {
        $this->service = new RateService($this->meter);
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
        $value = $this->service->getEstimatedRate(
            start: today()->startOfMonth(),
            end: today(),
        );

        return $this->makeStat(__('reading.average_consumption.monthly.current'), $value);
    }

    protected function getCurrentYearMonthlyAverage(): Stat
    {
        $value = $this->service->getEstimatedRate(
            start: today()->startOfYear(),
            end: today()
        );

        if ($value !== null) {
            $value *= 30.44;
        }

        return $this->makeStat(__('reading.average_consumption.yearly.current'), $value);
    }

    protected function getDailyAveragePreviousMonth(): Stat
    {
        $value = $this->service->getEstimatedRate(
            start: today()->subMonth()->startOfMonth(),
            end: today()->subMonth()->endOfMonth()
        );

        return $this->makeStat(__('reading.average_consumption.monthly.previous'), $value);
    }

    protected function getPreviousYearMonthlyAverage(): Stat
    {
        $value = $this->service->getEstimatedRate(
            start: today()->subYear()->startOfYear(),
            end: today()->subYear()->endOfYear()
        );

        if ($value !== null) {
            $value *= 30.44;
        }

        return $this->makeStat(__('reading.average_consumption.yearly.previous'), $value);
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
