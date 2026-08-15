<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LedAdSubmissionResource\Pages;
use App\Models\LedAd;
use App\Models\LedAdSubmission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Moderation queue for merchant self-service submissions
 * (App\Http\Controllers\LedAdSubmissionController, /anunciar) — approving
 * one creates the corresponding App\Models\LedAd; nothing here reaches the
 * public ticker until an admin acts on it.
 */
class LedAdSubmissionResource extends Resource
{
    protected static ?string $model = LedAdSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Publicidad';

    protected static ?string $navigationLabel = 'Solicitudes de comercios';

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    private static function categoryLabel(string $value): string
    {
        return [
            'cafeteria' => 'Cafetería',
            'restaurante' => 'Restaurante',
            'tienda' => 'Tienda / Retail',
            'hotel' => 'Hotel / Alojamiento',
            'servicios' => 'Servicios profesionales',
            'otro' => 'Otro',
        ][$value] ?? $value;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Comercio')
                ->schema([
                    Forms\Components\TextInput::make('business_name')->label('Nombre')->disabled(),
                    Forms\Components\TextInput::make('category')->label('Rubro')->disabled()
                        ->formatStateUsing(fn (?string $state) => $state ? self::categoryLabel($state) : null),
                    Forms\Components\TextInput::make('city')->label('Ciudad')->disabled(),
                    Forms\Components\TextInput::make('address')->label('Dirección')->disabled(),
                    Forms\Components\TextInput::make('business_hours')->label('Horario')->disabled(),
                    Forms\Components\Textarea::make('description')->label('Descripción del comercio')->disabled()->columnSpanFull(),
                    Forms\Components\Toggle::make('accepts_lightning')->label('Acepta Lightning')->disabled(),
                    Forms\Components\Toggle::make('accepts_onchain')->label('Acepta on-chain')->disabled(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Contacto (no se publica)')
                ->schema([
                    Forms\Components\TextInput::make('contact_name')->label('Nombre')->disabled(),
                    Forms\Components\TextInput::make('contact_email')->label('Email')->disabled(),
                    Forms\Components\TextInput::make('contact_phone')->label('Teléfono')->disabled(),
                ])
                ->columns(3),

            Forms\Components\Section::make('Cartel LED')
                ->schema([
                    Forms\Components\TextInput::make('message')->label('Mensaje')->maxLength(200)->required(),
                    Forms\Components\TextInput::make('url')->label('Enlace')->url()->required(),
                ]),

            Forms\Components\Textarea::make('admin_notes')->label('Notas internas')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('business_name')->label('Comercio')->searchable(),
                Tables\Columns\TextColumn::make('category')->label('Rubro')
                    ->formatStateUsing(fn (string $state) => self::categoryLabel($state)),
                Tables\Columns\TextColumn::make('city')->label('Ciudad')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->label('Estado')->badge()->color(fn (string $state) => match ($state) {
                    'approved' => 'success',
                    'rejected' => 'danger',
                    default => 'warning',
                })->formatStateUsing(fn (string $state) => ['pending' => 'Pendiente', 'approved' => 'Aprobada', 'rejected' => 'Rechazada'][$state] ?? $state),
                Tables\Columns\TextColumn::make('created_at')->label('Recibida')->dateTime()->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Estado')->options([
                    'pending' => 'Pendiente',
                    'approved' => 'Aprobada',
                    'rejected' => 'Rechazada',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Ver / editar'),
                Tables\Actions\Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (LedAdSubmission $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\TextInput::make('message')->label('Mensaje en el cartel')->maxLength(200)->required(),
                        Forms\Components\TextInput::make('url')->label('Enlace')->url()->required(),
                        Forms\Components\TextInput::make('sort_order')->label('Orden en el carrusel')->numeric()->default(0),
                    ])
                    ->fillForm(fn (LedAdSubmission $record) => [
                        'message' => $record->message,
                        'url' => $record->url,
                    ])
                    ->action(function (LedAdSubmission $record, array $data) {
                        $ad = LedAd::create([
                            'message' => $data['message'],
                            'url' => $data['url'],
                            'sort_order' => $data['sort_order'] ?? 0,
                            'is_active' => true,
                        ]);

                        $record->update(['status' => 'approved', 'led_ad_id' => $ad->id]);

                        Notification::make()->title('Comercio publicado en el cartel')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (LedAdSubmission $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')->label('Motivo (opcional)'),
                    ])
                    ->action(function (LedAdSubmission $record, array $data) {
                        $record->update(['status' => 'rejected', 'admin_notes' => $data['admin_notes'] ?? null]);

                        Notification::make()->title('Solicitud rechazada')->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLedAdSubmissions::route('/'),
            'edit' => Pages\EditLedAdSubmission::route('/{record}/edit'),
        ];
    }
}
