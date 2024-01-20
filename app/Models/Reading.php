<?php

namespace App\Models;

use App\Enums\MeasurmentUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    use HasUuids;

    protected $fillable = [
        'meter_id', 'value', 'date',
    ];

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function unit(): MeasurmentUnit
    {
        return $this->meter->type->getUnit();
    }
}
