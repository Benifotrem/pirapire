<?php

namespace App\Filament\Resources\LedAdSubmissionResource\Pages;

use App\Filament\Resources\LedAdSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLedAdSubmission extends EditRecord
{
    protected static string $resource = LedAdSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
