<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Schemas\MeterForm;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;

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
