<?php

namespace App\Models;

use App\Enums\MeasurmentUnit;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    use HasUuids;

    protected $fillable = [
        'meter_id', 'value', 'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function previous(): Attribute
    {
        return Attribute::get(
            fn (): ?Reading => self::query()
                ->tenant()
                ->latest('date')
                ->whereDate('date', '<', $this->date)
                ->first()
        );
    }

    public function scopeTenant(Builder $query): void
    {
        $query->whereBelongsTo(Filament::getTenant(), 'meter');
    }

    public function scopeCurrentYear(Builder $query): void
    {
        $query
            ->whereBetween('date', [
                today()->startOfYear(),
                today()->endOfYear(),
            ]);
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function unit(): MeasurmentUnit
    {
        return $this->meter->type->getUnit();
    }

    public static function firstOfYear(): ?self
    {
        return self::query()
            ->tenant()
            ->currentYear()
            ->orderBy('date')
            ->first();
    }

    public static function lastOfYear(): ?self
    {
        return self::query()
            ->tenant()
            ->currentYear()
            ->orderByDesc('date')
            ->first();
    }
}
