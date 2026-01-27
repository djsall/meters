<?php

namespace App\Models;

use App\Enums\MeterType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

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

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
