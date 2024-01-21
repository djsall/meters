<?php

namespace App\Filament\Pages;

use App\Enums\MeterType;
use App\Models\Meter;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile;

class EditMeter extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return trans('meter.edit');
    }

    public function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Select::make('type')
                    ->label(trans('meter.type'))
                    ->required()
                    ->options(MeterType::class),
                TextInput::make('name')
                    ->label(trans('meter.name'))
                    ->required(),
                Textarea::make('description')
                    ->label(trans('meter.description')),
                Select::make('shared_users')
                    ->hidden(static fn (Meter $record) => $record->user->id !== auth()->user()->id)
                    ->label(trans('meter.shared_with'))
                    ->options(static fn () => User::all()->except(auth()->user()->id)->pluck('email', 'id'))
                    ->multiple()
                    ->searchable()
                    ->mutateDehydratedStateUsing(fn ($state) => collect($state)->map(fn ($item) => str($item)->toInteger())->all()),
            ]);
    }
}
