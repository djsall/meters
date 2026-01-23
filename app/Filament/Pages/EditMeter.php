<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasMeterForm;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;

class EditMeter extends EditTenantProfile
{
    use HasMeterForm;

    public static function getLabel(): string
    {
        return __('meter.edit');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema(static::GetMeterForm());
    }
}
