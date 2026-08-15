<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlertResource\Pages;
use App\Models\Alert;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AlertResource extends Resource
{
    protected static ?string $model = Alert::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Comunidad';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('customer_id')
                ->relationship('customer', 'telegram_chat_id')
                ->searchable()
                ->required(),
            Forms\Components\Select::make('currency')
                ->options(['PYG' => 'PYG', 'USD' => 'USD'])
                ->required(),
            Forms\Components\Select::make('order_type')
                ->options(['BUY' => 'Compra', 'SELL' => 'Venta', 'ANY' => 'Cualquiera'])
                ->required(),
            Forms\Components\TextInput::make('min_amount')->numeric(),
            Forms\Components\TextInput::make('max_amount')->numeric(),
            Forms\Components\TagsInput::make('payment_methods'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.telegram_chat_id')->label('Telegram')->placeholder('—'),
                Tables\Columns\TextColumn::make('currency'),
                Tables\Columns\TextColumn::make('order_type'),
                Tables\Columns\TextColumn::make('min_amount')->placeholder('—'),
                Tables\Columns\TextColumn::make('max_amount')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('currency')->options(['PYG' => 'PYG', 'USD' => 'USD']),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListAlerts::route('/'),
            'create' => Pages\CreateAlert::route('/create'),
            'edit' => Pages\EditAlert::route('/{record}/edit'),
        ];
    }
}
