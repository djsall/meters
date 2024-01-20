<?php

namespace App\Models;

use App\Enums\MeterType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meter extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'type', 'name', 'description', 'settings',
    ];

    protected $hidden = [
        'user_id',
    ];

    protected $casts = [
        'type' => MeterType::class,
        'settings' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }
}
