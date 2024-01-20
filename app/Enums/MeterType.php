<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MeterType: string implements HasLabel
{
    case Electricity = 'electricity';
    case Gas = 'gas';
    case Water = 'water';

    public function getLabel(): ?string
    {
        return match ($this) {
            MeterType::Electricity => trans('meter.electricity'),
            MeterType::Gas => trans('meter.gas'),
            MeterType::Water => trans('meter.water'),
        };
    }

    public function getUnit(): MeasurmentUnit
    {
        return match ($this) {
            MeterType::Electricity => MeasurmentUnit::KWh,
            MeterType::Gas, MeterType::Water => MeasurmentUnit::CubicMeters,
        };
    }
}
