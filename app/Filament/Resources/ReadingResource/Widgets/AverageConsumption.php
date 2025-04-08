<?php

namespace App\Filament\Resources\ReadingResource\Widgets;

use App\Models\Reading;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AverageConsumption extends BaseWidget
{
    protected string $defaultValue = '-';

    protected function getColumns(): int
    {
        return 2;
    }

    protected function unit(): string
    {
        return Filament::getTenant()->type->getUnit()->getLabel();
    }

    protected function getCurrentYear(): Stat
    {
        $first = Reading::firstOfYear();
        $last = Reading::lastOfYear();

        $value = null;

        if ($first && $last && $last->date->notEqualTo($first->date)) {
            $value = ($last->value - $first->value) / $first->date->startOfMonth()->diffInMonths($last->date->endOfMonth());
        }

        return $this->makeStat(__('reading.average_consumption'), $value);
    }

    protected function getPreviousYear(): Stat
    {
        $first = Reading::firstOfYear(today()->subYear()->format('Y'));
        $last = Reading::lastOfYear(today()->subYear()->format('Y'));

        $value = null;

        if ($first && $last && $last->date->notEqualTo($first->date)) {
            $value = ($last->value - $first->value) / $first->date->startOfMonth()->diffInMonths($last->date->endOfMonth());
        }

        return $this->makeStat(title: __('reading.average_consumption_last_year'), value: $value);
    }

    protected function getDailyAverage(): Stat
    {

        $latest_reading = Reading::lastOfYear();
        $previous_reading = $latest_reading?->previous;

        $value = null;

        if ($previous_reading && $latest_reading) {
            $num_days = $latest_reading->date->diffInDays($previous_reading->date);
            $value = ($latest_reading->value - $previous_reading->value) / $num_days;
        }

        return $this->makeStat(title: __('reading.average_daily_consumption_this_month'), value: $value);
    }

    protected function getPreviousDailyAverage(): Stat
    {

        $latest_reading = Reading::lastOfYear()?->previous;
        $previous_reading = $latest_reading?->previous;

        $value = null;

        if ($previous_reading && $latest_reading) {
            $num_days = $latest_reading->date->diffInDays($previous_reading->date);
            $value = ($latest_reading->value - $previous_reading->value) / $num_days;
        }

        return $this->makeStat(title: __('reading.average_daily_consumption_previous_month'), value: $value);
    }

    protected function makeStat(string $title, ?float $value): Stat
    {
        $final_text = $this->defaultValue;

        if ($value) {
            $value = round($value, 2);
            $value = number_format($value, decimals: 2, thousands_separator: ' ');
            $final_text = "$value {$this->unit()}";
        }

        return Stat::make($title, $final_text);
    }

    protected function getStats(): array
    {

        return [
            $this->getDailyAverage(),
            $this->getCurrentYear(),
            $this->getPreviousDailyAverage(),
            $this->getPreviousYear(),
        ];
    }
}
