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

class ReadingResource extends Resource
{
    protected static ?string $model = Reading::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard';

    public static function getModelLabel(): string
    {
        return trans('reading.label');
    }

    public static function getPluralModelLabel(): string
    {
        return trans('reading.pluralLabel');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('value')
                    ->label(trans('reading.value'))
                    ->suffix(Filament::getTenant()->type->getUnit()->getLabel())
                    ->numeric(),
                Forms\Components\DatePicker::make('date')
                    ->label(trans('reading.date'))
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
                    ->label(trans('reading.value')),
                Tables\Columns\TextColumn::make('date')
                    ->date(format: 'Y-m-d')
                    ->label(trans('reading.date')),
            ])
            ->defaultPaginationPageOption(25)
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->extraModalFooterActions([
                        Tables\Actions\DeleteAction::make(),
                    ]),
            ])
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
