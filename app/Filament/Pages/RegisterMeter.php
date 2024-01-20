<?php

namespace App\Filament\Pages;

use App\Enums\MeterType;
use App\Models\Meter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\RegisterTenant;
use Illuminate\Database\Eloquent\Model;

class RegisterMeter extends RegisterTenant
{
    public static function getLabel(): string
    {
        return trans('meter.create');
    }

    public function form(Form $form): Form
    {
        return $form
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
            ]);
    }

    protected function handleRegistration(array $data): Model
    {
        $meter = Meter::create([...$data, 'user_id' => auth()->user()->id]);

        return $meter;
    }
}
