<?php

namespace App\Models;

use App\Enums\MeasurmentUnit;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Scope;
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

    public function previousMonth(): Attribute
    {
        return Attribute::get(
            fn (): ?Reading => self::query()
                ->tenant()
                ->latest('date')
                ->whereDate('date', '<', $this->date->startOfMonth())
                ->first()
        );
    }

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

    #[Scope]
    public function tenant(Builder $query): Builder
    {
        return $query->whereBelongsTo(Filament::getTenant(), 'meter');
    }

    #[Scope]
    public function year(Builder $query, ?int $year = null): Builder
    {
        $year ??= today()->year;

        return $query->whereYear('date', $year);
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function unit(): Attribute
    {
        return Attribute::get(
            fn (): MeasurmentUnit => $this->meter->type->getUnit()
        );
    }

    public static function firstOfYear(?int $year = null): ?self
    {
        return self::query()
            ->oldest('date')
            ->tenant()
            ->year($year)
            ->first();
    }

    public static function lastOfYear(?int $year = null): ?self
    {
        return self::query()
            ->latest('date')
            ->tenant()
            ->year($year)
            ->first();
    }
}
