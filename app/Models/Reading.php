<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read Meter $meter
 * @property-read Carbon $date
 */
class Reading extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'meter_id', 'value', 'date',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'date' => 'datetime',
        ];
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }
}
