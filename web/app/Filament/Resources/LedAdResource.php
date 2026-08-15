<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LedAdResource\Pages;
use App\Models\LedAd;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** CRUD for the header LED ticker's rotating messages. See resources/views/components/led-display.blade.php. */
class LedAdResource extends Resource
{
    protected static ?string $model = LedAd::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Publicidad';

    protected static ?string $navigationLabel = 'Anuncios LED';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('message')
                ->label('Mensaje')
                ->required()
                ->maxLength(255)
                ->helperText('El texto que se desplaza en el cartel LED del header.'),
            Forms\Components\TextInput::make('url')
                ->label('Enlace')
                ->url()
                ->required()
                ->helperText('Se abre en una pestaña nueva al hacer clic en el cartel.'),
            Forms\Components\TextInput::make('sort_order')
                ->label('Orden')
                ->numeric()
                ->default(0)
                ->helperText('Menor número aparece primero en el carrusel.'),
            Forms\Components\Toggle::make('is_active')
                ->label('Activo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('Orden')->sortable(),
                Tables\Columns\TextColumn::make('message')->label('Mensaje')->limit(50),
                Tables\Columns\TextColumn::make('url')->label('Enlace')->limit(40),
                Tables\Columns\IconColumn::make('is_active')->label('Activo')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('Creado')->dateTime()->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activo'),
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
            'index' => Pages\ListLedAds::route('/'),
            'create' => Pages\CreateLedAd::route('/create'),
            'edit' => Pages\EditLedAd::route('/{record}/edit'),
        ];
    }
}
