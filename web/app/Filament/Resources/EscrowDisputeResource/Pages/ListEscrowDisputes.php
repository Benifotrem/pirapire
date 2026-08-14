<?php

namespace App\Filament\Resources\EscrowDisputeResource\Pages;

use App\Filament\Resources\EscrowDisputeResource;
use Filament\Resources\Pages\ListRecords;

class ListEscrowDisputes extends ListRecords
{
    protected static string $resource = EscrowDisputeResource::class;

    protected function getHeaderActions(): array
    {
        // No CreateAction: disputes are only ever opened via the escrow flow.
        return [];
    }
}
