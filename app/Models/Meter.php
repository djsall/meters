<?php

namespace App\Models;

use App\Enums\MeterType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;
use Staudenmeir\EloquentJsonRelations\Relations\BelongsToJson;

class Meter extends Model
{
    use HasJsonRelationships, HasUuids;

    protected $fillable = [
        'user_id', 'type', 'name', 'description', 'settings', 'shared_users',
    ];

    protected $hidden = [
        'user_id',
    ];

    protected $casts = [
        'type' => MeterType::class,
        'settings' => 'array',
        'shared_users' => 'json',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function sharedWith(): BelongsToJson
    {
        return $this->belongsToJson(User::class, 'shared_users');
    }
}
