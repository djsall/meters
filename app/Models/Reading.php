<?php

namespace App\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    use HasUuids;

    protected $fillable = [
        'meter_id', 'value', 'date',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
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

    public static function firstOfYear(?int $year = null): ?self
    {
        return self::query()
            ->oldest('date')
            ->tenant()
            ->year($year)
            ->first();
    }
}
