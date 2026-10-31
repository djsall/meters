<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;
use Staudenmeir\EloquentJsonRelations\Relations\HasManyJson;

/**
 * @property-read Collection<Meter> $meters
 * @property-read Collection<Meter> $overdueMeters
 * @property-read Collection<Meter> $sharedMeters
 * @property-read Collection<Meter> $overdueSharedMeters
 */
class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasApiTokens, HasFactory, HasJsonRelationships, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'last_notified',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'last_notified' => 'datetime',
        ];
    }

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    public function overdueMeters(): HasMany
    {
        return $this->meters()
            ->whereDoesntHave('readings', function (Builder $query) {
                $query->whereDate('date', '>', today()->subMonth());
            });
    }

    public function sharedMeters(): HasManyJson
    {
        return $this->hasManyJson(Meter::class, 'shared_users');
    }

    public function overdueSharedMeters(): HasMany
    {
        return $this->sharedMeters()
            ->whereDoesntHave('readings', function (Builder $query) {
                $query->whereDate('date', '>', today()->subMonth());
            });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->meters->contains($tenant) || $this->sharedMeters->contains($tenant);
    }

    public function getTenants(Panel $panel): array|Collection
    {
        $isAdmin = Filament::auth()->user()->role->isAdmin();

        return $this->meters()
            ->when(! $isAdmin, static fn (Builder $query): Builder => $query->whereDoesntHave('successor'))
            ->get()
            ->merge($this->sharedMeters);
    }
}
