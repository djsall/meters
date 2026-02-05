<?php

namespace App\Filament\Resources\Reading\Widgets;

use App\Models\Meter;
use App\Services\InterpolatedConsumptionService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AverageConsumption extends BaseWidget
{
    protected string $defaultValue = '-';

    protected ?string $pollingInterval = null;

    protected InterpolatedConsumptionService $service;

    protected function getColumns(): int
    {
        return 4;
    }

    protected Meter $meter {
        get {
            return Meter::getFilamentTenant();
        }
    }

    public function __construct()
    {
        $this->service = new InterpolatedConsumptionService($this->meter);
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
        [$start, $end] = [today()->startOfMonth(), today()->endOfMonth()];

        $value = $this->service->getAverageDailyConsumption($start, $end);

        $chart = $this->cacheDailyConsumption('current_month', $start, $end);

        return $this->makeStat(__('reading.average_consumption.monthly.current'), $value)->chart($chart)->chartColor('primary');
    }

    protected function getCurrentYearMonthlyAverage(): Stat
    {
        [$start, $end] = [today()->startOfYear(), today()->endOfYear()];

        $value = $this->service->getAverageDailyConsumption($start, $end);

        if ($value !== null) {
            $value *= 30.44;
        }

        $chart = $this->cacheMonthlyConsumption('current_year', $start, $end);

        return $this->makeStat(__('reading.average_consumption.yearly.current'), $value)->chart($chart)->chartColor('primary');
    }

    protected function getDailyAveragePreviousMonth(): Stat
    {
        [$start, $end] = [today()->subMonth()->startOfMonth(), today()->subMonth()->endOfMonth()];

        $value = $this->service->getAverageDailyConsumption($start, $end);

        $chart = $this->cacheDailyConsumption('previous_month', $start, $end);

        return $this->makeStat(__('reading.average_consumption.monthly.previous'), $value)->chart($chart);
    }

    protected function getPreviousYearMonthlyAverage(): Stat
    {
        [$start, $end] = [today()->subYear()->startOfYear(), today()->subYear()->endOfYear()];

        $value = $this->service->getAverageDailyConsumption($start, $end);

        if ($value !== null) {
            $value *= 30.44;
        }

        $chart = $this->cacheMonthlyConsumption('previous_year', $start, $end);

        return $this->makeStat(__('reading.average_consumption.yearly.previous'), $value)->chart($chart);
    }

    protected function cacheDailyConsumption(string $key, Carbon $start, Carbon $end): array
    {
        $dailyConsumption = Cache::remember(
            "{$key}_daily_consumption_{$this->meter->id}",
            60,
            fn () => $this->service->getDailyConsumption($start, $end)
        );

        return data_get($dailyConsumption, '*.consumption');
    }

    protected function cacheMonthlyConsumption(string $key, Carbon $start, Carbon $end): array
    {
        $monthlyConsumption = Cache::remember(
            "{$key}_monthly_consumption_{$this->meter->id}",
            60,
            fn () => $this->service->getMonthlyConsumption($start, $end)
        );

        return data_get($monthlyConsumption, '*.consumption');
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
