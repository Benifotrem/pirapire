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
use RuntimeException;

class EscrowJobResource extends Resource
{
    protected static ?string $model = EscrowJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationGroup = 'Escrow Lightning';

    protected static ?string $navigationLabel = 'Trabajos';

    public static function form(Form $form): Form
    {
        // Escrow jobs are created via the Telegram bot, never manually in
        // the admin panel — this resource is read/act-only.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->limit(8)->copyable(),
                Tables\Columns\TextColumn::make('creator.telegram_chat_id')->label('Cliente')->placeholder('—'),
                Tables\Columns\TextColumn::make('freelancer.telegram_chat_id')->label('Freelancer')->placeholder('sin asignar'),
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
                    'open' => 'Abierto (buscando freelancer)',
                    'assigned' => 'Asignado (esperando pago)',
                    'funded' => 'Financiado',
                    'in_progress' => 'En progreso',
                    'delivered' => 'Entregado (esperando liberación)',
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
                    ->visible(fn (EscrowJob $record) => in_array($record->status, ['delivered', 'disputed'], true))
                    ->form([
                        Forms\Components\Placeholder::make('freelancer_payout_invoice')
                            ->label('Factura que envió el freelancer al entregar')
                            ->content(fn (EscrowJob $record) => $record->freelancer_payout_invoice ?: '— no hay ninguna guardada —'),
                        Forms\Components\Textarea::make('payout_bolt11_override')
                            ->label('Factura alternativa (opcional)')
                            ->helperText('Dejalo vacío para usar la factura de arriba. Completalo solo si esa factura ya expiró y conseguiste una nueva del freelancer.'),
                    ])
                    ->action(fn (EscrowJob $record, array $data) => self::runAction(
                        $record,
                        fn (EscrowService $s) => $s->release($record, null, $data['payout_bolt11_override'] ?: null),
                    )),
                Tables\Actions\Action::make('refund')
                    ->label('Reembolsar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (EscrowJob $record) => in_array($record->status, ['funded', 'in_progress', 'delivered', 'disputed'], true))
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
        } catch (RuntimeException) {
            Notification::make()->title('Error')->body('No se pudo contactar al proveedor de pagos. Probá de nuevo en unos minutos.')->danger()->send();
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
