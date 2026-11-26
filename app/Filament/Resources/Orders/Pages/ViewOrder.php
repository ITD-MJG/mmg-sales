<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Actions\UpdateDeliveryAction;
use App\Filament\Actions\UpdatePaymentAction;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            // Same actions as the ones on the orders table, so the header
            // offers the identical delivery and payment forms and histories.
            // On a record page the actions resolve `$record` from the page.
            UpdateDeliveryAction::make(),
            UpdatePaymentAction::make(),
        ];
    }
}
