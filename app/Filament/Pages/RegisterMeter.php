<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasMeterForm;
use App\Models\Meter;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\RegisterTenant;
use Illuminate\Database\Eloquent\Model;

class RegisterMeter extends RegisterTenant
{
    use HasMeterForm;

    public static function getLabel(): string
    {
        return trans('meter.create');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(static::getMeterForm());
    }

    protected function handleRegistration(array $data): Model
    {
        return Meter::create([...$data, 'user_id' => auth()->user()->id]);
    }
}
