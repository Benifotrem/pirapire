<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EscrowDisputeResource\Pages;
use App\Models\EscrowDispute;
use App\Services\Escrow\EscrowService;
use DomainException;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class EscrowDisputeResource extends Resource
{
    protected static ?string $model = EscrowDispute::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Escrow Lightning';

    protected static ?string $navigationLabel = 'Disputas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Textarea::make('reason')->disabled(),
            Forms\Components\Textarea::make('resolution_notes'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('escrowJob.id')->label('Escrow')->limit(8),
                Tables\Columns\TextColumn::make('openedBy.whatsapp_number')->label('Abierto por')->placeholder('—'),
                Tables\Columns\TextColumn::make('reason')->limit(50),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'open' ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['open' => 'Abierta', 'resolved' => 'Resuelta']),
            ])
            ->actions([
                Tables\Actions\Action::make('release_to_freelancer')
                    ->label('Liberar al freelancer')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (EscrowDispute $record) => $record->status === 'open')
                    ->form([
                        Forms\Components\Textarea::make('payout_bolt11')
                            ->label('Factura del freelancer (bolt11)')
                            ->helperText('Pedísela justo antes de resolver — las facturas Lightning expiran.')
                            ->required(),
                        Forms\Components\Textarea::make('resolution_notes')->label('Notas de resolución'),
                    ])
                    ->action(fn (EscrowDispute $record, array $data) => self::resolve(
                        $record, 'release', $data['payout_bolt11'], $data['resolution_notes'] ?? null,
                    )),
                Tables\Actions\Action::make('refund_client')
                    ->label('Reembolsar al cliente')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (EscrowDispute $record) => $record->status === 'open')
                    ->form([
                        Forms\Components\Textarea::make('payout_bolt11')
                            ->label('Factura del cliente para el reembolso (bolt11)')
                            ->helperText('Pedísela justo antes de resolver — las facturas Lightning expiran.')
                            ->required(),
                        Forms\Components\Textarea::make('resolution_notes')->label('Notas de resolución'),
                    ])
                    ->action(fn (EscrowDispute $record, array $data) => self::resolve(
                        $record, 'refund', $data['payout_bolt11'], $data['resolution_notes'] ?? null,
                    )),
            ]);
    }

    private static function resolve(EscrowDispute $dispute, string $action, string $payoutBolt11, ?string $notes): void
    {
        try {
            $escrowService = app(EscrowService::class);
            $job = $dispute->escrowJob;

            if ($action === 'release') {
                $escrowService->release($job, $payoutBolt11);
            } else {
                $escrowService->refund($job, $payoutBolt11);
            }

            $dispute->update([
                'resolution_notes' => $notes,
                'resolved_by_user_id' => Auth::id(),
            ]);

            Notification::make()->title('Disputa resuelta')->success()->send();
        } catch (DomainException $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEscrowDisputes::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
