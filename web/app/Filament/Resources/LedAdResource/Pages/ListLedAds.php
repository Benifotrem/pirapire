<?php

namespace App\Filament\Resources\LedAdResource\Pages;

use App\Filament\Resources\LedAdResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLedAds extends ListRecords
{
    protected static string $resource = LedAdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
