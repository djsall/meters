<?php

namespace App\Observers;

use App\Models\Meter;

class MeterObserver
{
    public function saved(Meter $meter): void
    {
        if ($meter->wasChanged('previous_meter_id') && $meter->previous_meter_id) {
            $meter->previousMeter?->refreshRetiredTotalConsumption();
        }
    }
}
