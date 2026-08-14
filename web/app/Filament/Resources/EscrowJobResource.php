<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EscrowJobResource\Pages;
use App\Models\EscrowJob;
use App\Services\Escrow\EscrowService;
use DomainException;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EscrowJobResource extends Resource
{
    protected static ?string $model = EscrowJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationGroup = 'Escrow Lightning';

    protected static ?string $navigationLabel = 'Trabajos';

    public static function form(Form $form): Form
    {
        // Escrow jobs are created via the WhatsApp bot / API, never manually
        // in the admin panel — this resource is read/act-only.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->limit(8)->copyable(),
                Tables\Columns\TextColumn::make('creator.whatsapp_number')->label('Cliente')->placeholder('—'),
                Tables\Columns\TextColumn::make('description')->limit(40),
                Tables\Columns\TextColumn::make('amount_sats')->label('Sats'),
                Tables\Columns\TextColumn::make('fee_sats')->label('Comisión'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'completed' => 'success',
                    'disputed' => 'danger',
                    'refunded', 'cancelled' => 'gray',
                    default => 'warning',
                }),
                Tables\Columns\TextColumn::make('expires_at')->dateTime()->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'created' => 'Creado',
                    'funded' => 'Financiado',
                    'in_progress' => 'En progreso',
                    'completed' => 'Completado',
                    'disputed' => 'En disputa',
                    'refunded' => 'Reembolsado',
                    'cancelled' => 'Cancelado',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('release')
                    ->label('Liberar')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (EscrowJob $record) => in_array($record->status, ['funded', 'in_progress', 'disputed'], true))
                    ->form([
                        Forms\Components\Textarea::make('payout_bolt11')
                            ->label('Factura del freelancer (bolt11)')
                            ->helperText('Pedísela al freelancer justo antes de liberar — las facturas Lightning expiran.')
                            ->required(),
                    ])
                    ->action(fn (EscrowJob $record, array $data) => self::runAction(
                        $record,
                        fn (EscrowService $s) => $s->release($record, $data['payout_bolt11']),
                    )),
                Tables\Actions\Action::make('refund')
                    ->label('Reembolsar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (EscrowJob $record) => in_array($record->status, ['funded', 'in_progress', 'disputed'], true))
                    ->form([
                        Forms\Components\Textarea::make('refund_bolt11')
                            ->label('Factura del cliente para el reembolso (bolt11)')
                            ->helperText('Pedísela al cliente justo antes de reembolsar — las facturas Lightning expiran.')
                            ->required(),
                    ])
                    ->action(fn (EscrowJob $record, array $data) => self::runAction(
                        $record,
                        fn (EscrowService $s) => $s->refund($record, $data['refund_bolt11']),
                    )),
            ]);
    }

    private static function runAction(EscrowJob $record, callable $callback): void
    {
        try {
            $callback(app(EscrowService::class));
            Notification::make()->title('Escrow actualizado')->success()->send();
        } catch (DomainException $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEscrowJobs::route('/'),
            'view' => Pages\ViewEscrowJob::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
