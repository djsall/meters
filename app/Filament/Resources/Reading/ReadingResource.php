<?php

namespace App\Filament\Resources\Reading;

use App\Models\Reading;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReadingResource extends Resource
{
    protected static ?string $model = Reading::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard';

    public static function getModelLabel(): string
    {
        return __('reading.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('reading.pluralLabel');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('value')
                    ->label(__('reading.value'))
                    ->required()
                    ->suffix(Filament::getTenant()->type->getUnit()->getLabel())
                    ->numeric(),
                Forms\Components\DateTimePicker::make('date')
                    ->label(__('reading.date'))
                    ->required()
                    ->displayFormat('Y.m.d H:i')
                    ->native(false)
                    ->seconds(false)
                    ->default(now()),
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
            ->modifyQueryUsing(static function (Builder $query): Builder {
                return $query
                    ->with('meter:id,type')
                    ->select('*')
                    ->selectRaw('value - LAG(value) OVER (ORDER BY date ASC) as difference');
            })
            ->columns([
                Tables\Columns\TextColumn::make('value')
                    ->numeric(decimalSeparator: '.', thousandsSeparator: ' ', maxDecimalPlaces: 1)
                    ->suffix(str(Filament::getTenant()->type->getUnit()->getLabel())->prepend(' '))
                    ->label(__('reading.value'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('difference')
                    ->label(__('reading.difference'))
                    ->numeric(decimalSeparator: '.', thousandsSeparator: ' ', maxDecimalPlaces: 1)
                    ->suffix(static fn (Reading $record): string => str($record->meter->type->getUnit()->getLabel())->prepend(' '))
                    ->color('primary'),
                Tables\Columns\TextColumn::make('date')
                    ->dateTime(format: 'Y.m.d H:i')
                    ->label(__('reading.date'))
                    ->sortable(),
            ])
            ->defaultPaginationPageOption(25)
            ->filters([
                Tables\Filters\Filter::make('current_year')
                    ->label(__('reading.filter.current_year'))
                    ->default()
                    ->query(static fn (Builder $query): Builder => $query->whereYear('date', today()->year)),
            ], Tables\Enums\FiltersLayout::AboveContent)
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->extraModalFooterActions([
                        DeleteAction::make(),
                    ]),
            ])
            ->defaultSort('date');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageValues::route('/'),
        ];
    }
}
