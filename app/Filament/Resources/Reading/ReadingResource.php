<?php

namespace App\Filament\Resources\Reading;

use App\Models\Reading;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
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
            ->modifyQueryUsing(static function (Builder $query): Builder {
                return $query
                    ->with('meter:id,type')
                    ->select('*')
                    ->selectRaw('value - LAG(value) OVER (ORDER BY date ASC) as difference');
            })
            ->columns([
                Tables\Columns\TextColumn::make('value')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(str(Filament::getTenant()->type->getUnit()->getLabel())->prepend(' '))
                    ->label(__('reading.value'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date(format: 'Y.m.d')
                    ->label(__('reading.date'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('difference')
                    ->numeric(thousandsSeparator: ' ')
                    ->label(__('reading.difference'))
                    ->suffix(static fn (Reading $record): string => str($record->meter->type->getUnit()->getLabel())->prepend(' '))
                    ->color('primary'),
                Tables\Columns\TextColumn::make('created_at')
                    ->date(format: 'Y.m.d H:i')
                    ->label(__('reading.created_at'))
                    ->sortable(),
            ])
            ->defaultPaginationPageOption(25)
            ->filters([
                Tables\Filters\Filter::make('current_year')
                    ->label(__('reading.filter.current_year'))
                    ->default()
                    ->query(static fn (Builder $query): Builder => $query->whereYear('date', today()->year)),
            ])
            ->recordActions([
                EditAction::make()
                    ->extraModalFooterActions([
                        DeleteAction::make(),
                    ]),
            ])
            ->defaultSort('date')
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageValues::route('/'),
        ];
    }
}
