<?php

namespace App\Filament\Resources\EscrowJobResource\Pages;

use App\Filament\Resources\EscrowJobResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEscrowJobs extends ListRecords
{
    protected static string $resource = EscrowJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
