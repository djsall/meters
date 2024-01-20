<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MeasurmentUnit: string implements HasLabel
{
    case KWh = 'kwh';
    case CubicMeters = 'cubic_meters';

    public function getLabel(): ?string
    {
        return match ($this) {
            MeasurmentUnit::KWh => 'KWh',
            MeasurmentUnit::CubicMeters => 'm³',
        };
    }
}
