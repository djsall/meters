<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Schemas\MeterForm;
use App\Models\Meter;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EditMeter extends EditTenantProfile
{
    protected Width|string|null $maxContentWidth = Width::ExtraLarge;

    protected string $view = 'filament-panels::pages.simple';

    protected static string $layout = 'filament-panels::components.layout.simple';

    public function hasLogo(): bool
    {
        return false;
    }

    public static function getLabel(): string
    {
        return __('meter.edit.actions.edit');
    }

    public function form(Schema $schema): Schema
    {
        return MeterForm::configure($schema);
    }

    /**
     * @param  Meter  $record
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $openingValue = Arr::pull($data, 'opening_value');

        return DB::transaction(function () use ($record, $data, $openingValue): Meter {
            $record->update($data);
            $record->recordOpeningReading(is_numeric($openingValue) ? (float) $openingValue : null);

            return $record;
        });
    }

    protected function getFormActions(): array
    {
        return [
            ...parent::getFormActions(),
            Action::make('cancel')
                ->label(__('meter.edit.actions.cancel'))
                ->color(Color::Gray)
                ->outlined()
                ->url(static fn (): string => Filament::getUrl(Filament::getTenant())),
        ];
    }
}
