<?php

namespace App\Models;

use App\Enums\MeterType;
use App\Observers\MeterObserver;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

/**
 * @property-read Collection<Reading> $readings
 * @property-read User $user
 * @property MeterType $type
 * @property-read ?Meter $previousMeter
 * @property-read ?Meter $successor
 * @property ?CarbonInterface $installed_at
 */
#[ObservedBy(MeterObserver::class)]
class Meter extends Model
{
    use HasFactory, HasJsonRelationships, HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'description',
        'settings',
        'shared_users',
        'previous_meter_id',
        'installed_at',
    ];

    protected $hidden = [
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => MeterType::class,
            'settings' => 'array',
            'shared_users' => 'json',
            'installed_at' => 'date',
            'retired_total_consumption' => 'float',
        ];
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function previousMeter(): BelongsTo
    {
        return $this->belongsTo(Meter::class, 'previous_meter_id');
    }

    public function successor(): HasOne
    {
        return $this->hasOne(Meter::class, 'previous_meter_id');
    }

    public function refreshRetiredTotalConsumption(): void
    {
        /** @var ?Reading $first */
        $first = $this->readings()->oldest('date')->first();
        /** @var ?Reading $last */
        $last = $this->readings()->latest('date')->first();

        $ownConsumption = ($first && $last) ? $last->value - $first->value : 0.0;

        $this->retired_total_consumption = $ownConsumption + ($this->previousMeter?->retired_total_consumption);
        $this->saveQuietly();
    }

    public static function getFilamentTenant(): ?Meter
    {
        /** @var Meter $tenant */
        $tenant = Filament::getTenant();

        return $tenant;
    }
}
