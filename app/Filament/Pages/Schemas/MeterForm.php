<?php

namespace App\Filament\Pages\Schemas;

use App\Enums\MeterType;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class MeterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('type')
                    ->label(__('meter.type'))
                    ->required()
                    ->options(MeterType::class),
                Forms\Components\TextInput::make('name')
                    ->label(__('meter.name'))
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label(__('meter.description')),
                Forms\Components\Select::make('shared_users')
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
                            ->map(fn (string $item): int => str($item)->toInteger())
                            ->toArray();
                    }),
            ]);
    }
}
