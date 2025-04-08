<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReadingResource\Pages;
use App\Filament\Resources\ReadingResource\Widgets;
use App\Models\Reading;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReadingResource extends Resource
{
    protected static ?string $model = Reading::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard';

    public static function getModelLabel(): string
    {
        return __('reading.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('reading.pluralLabel');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('value')
                    ->label(__('reading.value'))
                    ->suffix(Filament::getTenant()->type->getUnit()->getLabel())
                    ->numeric(),
                Forms\Components\DatePicker::make('date')
                    ->label(__('reading.date'))
                    ->default(today()),
            ])
            ->columns(1);
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\AverageConsumption::class,
            Widgets\MonthlyConsumptionChart::class,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('value')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(str(Filament::getTenant()->type->getUnit()->getLabel())->prepend(' '))
                    ->label(__('reading.value'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date(format: 'Y-m-d')
                    ->label(__('reading.date'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('difference')
                    ->toggleable()
                    ->numeric(thousandsSeparator: ' ')
                    ->label(__('reading.difference'))
                    ->suffix(str(Filament::getTenant()->type->getUnit()->getLabel())->prepend(' '))
                    ->getStateUsing(function (Reading $record) {
                        $previous_reading = $record->previous;

                        if (! $previous_reading) {
                            return null;
                        }

                        return $record->value - $previous_reading->value;
                    })
                    ->color('primary'),
                Tables\Columns\TextColumn::make('daily_avg')
                    ->toggleable()
                    ->numeric(thousandsSeparator: ' ')
                    ->label(__('reading.daily_avg'))
                    ->suffix(str(Filament::getTenant()->type->getUnit()->getLabel())->prepend(' '))
                    ->getStateUsing(function (Reading $record) {
                        $previous_reading = $record->previous;

                        if (! $previous_reading) {
                            return null;
                        }

                        $num_days = $record->date->diffInDays($previous_reading->date);
                        $value = ($record->value - $previous_reading?->value) / $num_days;

                        return number_format(
                            num: round($value, 2),
                            decimals: 2,
                            thousands_separator: ' '
                        );
                    })
                    ->color('primary'),
            ])
            ->defaultPaginationPageOption(25)
            ->filters([
                Tables\Filters\Filter::make('current_year')
                    ->label(__('reading.filter.current_year'))
                    ->default()
                    ->query(static fn (Builder $query): Builder => $query->year()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->extraModalFooterActions([
                        Tables\Actions\DeleteAction::make(),
                    ]),
            ])
            ->defaultSort('date')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageValues::route('/'),
        ];
    }
}
