<?php

namespace App\Models;

use App\Enums\MeterType;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;
use Staudenmeir\EloquentJsonRelations\Relations\BelongsToJson;

class Meter extends Model
{
    use HasFactory, HasJsonRelationships, HasUuids;

    protected $fillable = [
        'user_id', 'type', 'name', 'description', 'settings', 'shared_users',
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
        ];
    }

    #[Scope]
    public function noRecentReadings(Builder $builder): Builder
    {
        return $builder
            ->whereDoesntHave('readings', function (Builder $query) {
                $query->whereDate('date', '>', today()->subMonth());
            });
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function firstReadingThisYear(): HasOne
    {
        return $this->readings()
            ->oldest('date')
            ->whereDate('date', '>=', today()->subYear()->startOfYear())
            ->one();
    }

    public function firstReadingThisMonth(): HasOne
    {
        return $this->readings()
            ->oldest('date')
            ->whereDate('date', '>=', today()->startOfMonth())
            ->one();
    }

    public function lastReading(): HasOne
    {
        return $this->readings()->latest('date')->one();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sharedWith(): BelongsToJson
    {
        return $this->belongsToJson(User::class, 'shared_users');
    }
}
