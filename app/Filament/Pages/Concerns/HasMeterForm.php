<?php

namespace App\Filament\Pages\Concerns;

use App\Enums\MeterType;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;

trait HasMeterForm
{
    protected static function getMeterForm(): array
    {
        return [
            Select::make('type')
                ->label(trans('meter.type'))
                ->required()
                ->options(MeterType::class),
            Forms\Components\TextInput::make('name')
                ->label(trans('meter.name'))
                ->required(),
            Forms\Components\Textarea::make('description')
                ->label(trans('meter.description')),
            Forms\Components\Select::make('shared_users')
                ->label(trans('meter.shared_with'))
                ->options(static fn () => User::all()->except(auth()->user()->id)->pluck('email', 'id'))
                ->multiple()
                ->searchable()
                ->mutateDehydratedStateUsing(fn ($state) => collect($state)->map(fn ($item) => str($item)->toInteger())->all()),
        ];
    }
}
