<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasMeterForm;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile;

class EditMeter extends EditTenantProfile
{
    use HasMeterForm;

    public static function getLabel(): string
    {
        return trans('meter.edit');
    }

    public function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema(static::GetMeterForm());
    }
}
