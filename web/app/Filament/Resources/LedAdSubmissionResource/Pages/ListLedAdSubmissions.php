<?php

namespace App\Filament\Resources\LedAdSubmissionResource\Pages;

use App\Filament\Resources\LedAdSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListLedAdSubmissions extends ListRecords
{
    protected static string $resource = LedAdSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
