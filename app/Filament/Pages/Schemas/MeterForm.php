<?php

namespace App\Filament\Pages\Schemas;

use App\Enums\MeterType;
use App\Models\Meter;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MeterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('type')
                    ->label(__('meter.type'))
                    ->live()
                    ->required()
                    ->options(MeterType::class),
                Forms\Components\TextInput::make('name')
                    ->label(__('meter.name'))
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label(__('meter.description')),
                Forms\Components\DatePicker::make('installed_at')
                    ->label(__('meter.installed_at'))
                    ->default(today()),
                Select::make('previous_meter_id')
                    ->label(__('meter.previous_meter'))
                    ->helperText(__('meter.previous_meter_help'))
                    ->disableOptionWhen(static function (string $value): bool {
                        $count = Cache::remember(
                            key: "meter_{$value}_successors_count",
                            ttl: 60 * 60,
                            callback: fn() => Meter::query()->whereKey($value)->has('successor')->count()
                        );

                        return $count > 0;
                    })
                    ->options(static function (Get $get): Collection {
                        $type = $get('type');

                        if (! $type) {
                            return new Collection;
                        }

                        return Meter::query()
                            ->where('user_id', Filament::auth()->id())
                            ->where('type', $type)
                            ->when(
                                Meter::getFilamentTenant(),
                                fn(Builder $query, Meter $tenant): Builder => $query->whereKeyNot($tenant->getKey())
                            )
                            ->pluck('name', 'id');
                    })
                    ->searchable(),
                Select::make('shared_users')
                    ->label(__('meter.shared_with'))
                    ->options(static function (): Collection {
                        return User::query()
                            ->whereKeyNot(auth()->id())
                            ->pluck('email', 'id');
                    })
                    ->multiple()
                    ->searchable()
                    ->mutateDehydratedStateUsing(static function (array $state): array {
                        return collect($state)
                            ->map(fn(string $item): int => str($item)->toInteger())
                            ->toArray();
                    }),
            ]);
    }
}
