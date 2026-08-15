<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VipSubscriptionResource\Pages;
use App\Models\VipSubscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VipSubscriptionResource extends Resource
{
    protected static ?string $model = VipSubscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Comunidad';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('customer_id')
                ->relationship('customer', 'telegram_chat_id')
                ->searchable()
                ->required(),
            Forms\Components\Select::make('status')
                ->options(['active' => 'Activa', 'expired' => 'Vencida', 'cancelled' => 'Cancelada'])
                ->required(),
            Forms\Components\TextInput::make('amount_sats')->numeric()->required(),
            Forms\Components\TextInput::make('payment_hash'),
            Forms\Components\DateTimePicker::make('starts_at')->required(),
            Forms\Components\DateTimePicker::make('expires_at')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.telegram_chat_id')->label('Telegram')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('amount_sats')->label('Sats'),
                Tables\Columns\TextColumn::make('starts_at')->dateTime(),
                Tables\Columns\TextColumn::make('expires_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Activa', 'expired' => 'Vencida', 'cancelled' => 'Cancelada',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListVipSubscriptions::route('/'),
            'create' => Pages\CreateVipSubscription::route('/create'),
            'edit' => Pages\EditVipSubscription::route('/{record}/edit'),
        ];
    }
}
