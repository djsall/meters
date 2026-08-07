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
            $this->dailyAverageStat(
                'current_month',
                'monthly.current',
                today()->startOfMonth(),
                today(),
                'primary'
            ),
            $this->monthlyAverageStat(
                'current_year',
                'yearly.current',
                today()->startOfYear(),
                today(),
                today()->month,
                'primary'
            ),
            $this->dailyAverageStat(
                'previous_month',
                'monthly.previous',
                today()->subMonth()->startOfMonth(),
                today()->subMonth()->endOfMonth(),
            ),
            $this->monthlyAverageStat(
                'previous_year',
                'yearly.previous',
                today()->subYear()->startOfYear(),
                today()->subYear()->endOfYear(),
                12,
            ),
        ];
    }

    protected function dailyAverageStat(string $cacheKey, string $labelKey, Carbon $start, Carbon $end, ?string $chartColor = null): Stat
    {
        $value = $this->cacheAverageDailyConsumption($cacheKey, $start, $end);
        $chart = $this->cacheDailyConsumption($cacheKey, $start);

        return $this->makeStat(__("reading.average_consumption.{$labelKey}"), $value)->chart($chart)->chartColor($chartColor);
    }

    protected function monthlyAverageStat(string $cacheKey, string $labelKey, Carbon $start, Carbon $end, int $monthsElapsed, ?string $chartColor = null): Stat
    {
        $value = $this->cacheTotalConsumption($cacheKey, $start, $end);

        if ($value !== null) {
            $value /= $monthsElapsed;
        }

        $chart = $this->cacheMonthlyConsumption($cacheKey, $start);

        return $this->makeStat(__("reading.average_consumption.{$labelKey}"), $value)->chart($chart)->chartColor($chartColor);
    }

    protected function cacheDailyConsumption(string $key, Carbon $start): array
    {
        $dailyConsumption = Cache::remember(
            "{$key}_daily_consumption_{$this->meter->id}",
            60,
            fn () => $this->service->getDailyConsumption($start, $start->copy()->endOfMonth())
        );

        return data_get($dailyConsumption, '*.consumption');
    }

    protected function cacheAverageDailyConsumption(string $key, Carbon $start, Carbon $end): ?float
    {
        return Cache::remember(
            "{$key}_average_daily_consumption_{$this->meter->id}",
            60,
            fn () => $this->service->getAverageDailyConsumption($start, $end)
        );
    }

    protected function cacheTotalConsumption(string $key, Carbon $start, Carbon $end): ?float
    {
        return Cache::remember(
            "{$key}_total_consumption_{$this->meter->id}",
            60,
            fn () => $this->service->getTotalConsumption($start, $end)
        );
    }

    protected function cacheMonthlyConsumption(string $key, Carbon $start): array
    {
        $monthlyConsumption = Cache::remember(
            "{$key}_monthly_consumption_{$this->meter->id}",
            60,
            fn () => $this->service->getMonthlyConsumption($start, $start->copy()->endOfYear())
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
