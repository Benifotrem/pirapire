<?php

namespace App\Filament\Resources\LedAdResource\Pages;

use App\Filament\Resources\LedAdResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLedAd extends EditRecord
{
    protected static string $resource = LedAdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
