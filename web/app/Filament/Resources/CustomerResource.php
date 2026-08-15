<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Comunidad';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('linking_key')
                ->label('Clave pública (LNURL-auth)')
                ->disabled()
                ->dehydrated(false),
            Forms\Components\TextInput::make('display_name')->maxLength(255),
            Forms\Components\TextInput::make('telegram_chat_id')->maxLength(32),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('display_name')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('telegram_chat_id')->label('Telegram')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('linking_key')
                    ->label('Clave')
                    ->formatStateUsing(fn (?string $state) => $state ? substr($state, 0, 12).'…' : '—'),
                Tables\Columns\IconColumn::make('is_vip')
                    ->label('VIP')
                    ->state(fn (Customer $record) => $record->isVip())
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_login_at')->dateTime()->since()->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->since()->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
