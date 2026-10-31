<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Schemas\MeterForm;
use App\Models\Meter;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RegisterMeter extends RegisterTenant
{
    protected Width|string|null $maxContentWidth = Width::Large;

    public static function getLabel(): string
    {
        return __('meter.create');
    }

    public function form(Schema $schema): Schema
    {
        return MeterForm::configure($schema);
    }

    protected function handleRegistration(array $data): Model
    {
        $openingValue = Arr::pull($data, 'opening_value');

        return DB::transaction(function () use ($data, $openingValue): Meter {
            $meter = Meter::create([...$data, 'user_id' => auth()->user()->id]);
            $meter->recordOpeningReading(is_numeric($openingValue) ? (float) $openingValue : null);

            return $meter;
        });
    }
}
