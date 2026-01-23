<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasMeterForm;
use App\Models\Meter;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class RegisterMeter extends RegisterTenant
{
    use HasMeterForm;

    public static function getLabel(): string
    {
        return __('meter.create');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema(static::getMeterForm());
    }

    protected function handleRegistration(array $data): Model
    {
        return Meter::create([...$data, 'user_id' => auth()->user()->id]);
    }
}
