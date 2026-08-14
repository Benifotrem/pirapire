<?php

namespace App\Filament\Resources\VipSubscriptionResource\Pages;

use App\Filament\Resources\VipSubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVipSubscriptions extends ListRecords
{
    protected static string $resource = VipSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
